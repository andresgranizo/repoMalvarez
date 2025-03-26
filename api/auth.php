<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

// Configuración de sesión
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Cambiar a 1 en producción con HTTPS
ini_set('session.cookie_samesite', 'Lax');

// Iniciar sesión al principio del script
session_start();

require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/utilities.php';

// Activar logs de errores para depuración
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Función para log
function debug_log($message) {
    error_log("[DEBUG] " . print_r($message, true));
}

// Manejar solicitud OPTIONS para CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Obtener datos de la solicitud
    $raw_data = file_get_contents('php://input');
    debug_log("Datos RAW recibidos: " . $raw_data);
    
    $data = json_decode($raw_data, true);
    debug_log("Datos JSON decodificados: " . print_r($data, true));
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Registro
        if (isset($data['action']) && $data['action'] === 'register') {
            if (!isset($data['name']) || !isset($data['email']) || !isset($data['password'])) {
                throw new Exception('Faltan datos requeridos');
            }

            $name = Utilities::sanitizeString($data['name']);
            $email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
            $password = password_hash($data['password'], PASSWORD_DEFAULT);
            $role = 'user'; // Rol predeterminado

            debug_log("Intento de registro - Email: " . $email);

            // Verificar si el email ya existe
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'El email ya está registrado'
                ]);
                exit;
            }

            // Insertar nuevo usuario
            $stmt = $conn->prepare("
                INSERT INTO users (name, email, password, role, status, is_active, ieee_member, ieee_member_id) 
                VALUES (?, ?, ?, ?, 'active', 1, ?, ?)
            ");
            
            if ($stmt->execute([
                $name, 
                $email, 
                $password, 
                $role,
                $data['ieee_member'] ?? 0,
                $data['ieee_member_id'] ?? null
            ])) {
                $userId = $conn->lastInsertId();
                
                // Obtener datos del usuario creado
                $stmt = $conn->prepare("SELECT id, name, email, role, status FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                debug_log("Registro exitoso para usuario: " . $user['name']);

                echo json_encode([
                    'success' => true,
                    'message' => 'Usuario registrado exitosamente. Por favor, inicia sesión.',
                    'data' => $user
                ]);
            } else {
                throw new Exception('Error al registrar el usuario');
            }
        }
        // Login
        else if (isset($data['email']) && isset($data['password']) && !isset($data['action'])) {
            $email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
            $password = $data['password'];
            
            debug_log("Intento de login - Email: " . $email);

            // Verificar credenciales
            $stmt = $conn->prepare("SELECT id, name, email, password, role, status FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] !== 'active') {
                    debug_log("Usuario inactivo");
                    http_response_code(403);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Tu cuenta está desactivada. Por favor, contacta al administrador.'
                    ]);
                    exit;
                }

                debug_log("Login exitoso para usuario: " . $user['name']);

                // Registrar el acceso
                $stmt = $conn->prepare("
                    INSERT INTO login_history (user_id, ip_address, user_agent) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([
                    $user['id'],
                    $_SERVER['REMOTE_ADDR'],
                    $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
                ]);

                // Eliminar la contraseña antes de almacenar en sesión
                unset($user['password']);

                // Almacenar datos del usuario en la sesión
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $user['email'];

                debug_log("Sesión iniciada: " . print_r($_SESSION, true));

                // Asegurar que la sesión se guarde
                session_write_close();

                echo json_encode([
                    'success' => true,
                    'message' => 'Login exitoso',
                    'data' => $user
                ]);
            } else {
                debug_log("Login fallido - Credenciales inválidas");
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'message' => 'Credenciales inválidas'
                ]);
            }
        }
        // Recuperación de contraseña
        else if (isset($data['action']) && $data['action'] === 'forgot_password') {
            if (!isset($data['email'])) {
                throw new Exception('Email requerido');
            }

            $email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
            
            // Verificar si el email existe
            $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Generar token temporal
                $token = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

                // Guardar token en la base de datos
                $stmt = $conn->prepare("
                    UPDATE users 
                    SET reset_token = ?, reset_token_expiry = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$token, $expiry, $user['id']]);

                // Enviar email con link de recuperación
                $resetLink = "http://localhost/EventManager/views/auth/reset-password.php?token=" . $token;
                $message = "
                    <h2>Recuperación de Contraseña</h2>
                    <p>Hola {$user['name']},</p>
                    <p>Has solicitado restablecer tu contraseña. Haz clic en el siguiente enlace:</p>
                    <p><a href='{$resetLink}'>{$resetLink}</a></p>
                    <p>Este enlace expirará en 1 hora.</p>
                    <p>Si no solicitaste este cambio, ignora este mensaje.</p>
                ";

                if (Utilities::sendEmail($email, "Recuperación de Contraseña - EventManager", $message)) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Se ha enviado un email con instrucciones para recuperar tu contraseña'
                    ]);
                } else {
                    throw new Exception('Error al enviar el email de recuperación');
                }
            } else {
                // Por seguridad, no indicamos si el email existe o no
                echo json_encode([
                    'success' => true,
                    'message' => 'Si el email está registrado, recibirás instrucciones para recuperar tu contraseña'
                ]);
            }
        }
        // Cambio de contraseña
        else if (isset($data['action']) && $data['action'] === 'reset_password') {
            if (!isset($data['token']) || !isset($data['password'])) {
                throw new Exception('Datos requeridos incompletos');
            }

            $token = $data['token'];
            $password = password_hash($data['password'], PASSWORD_DEFAULT);

            // Verificar token y actualizar contraseña
            $stmt = $conn->prepare("
                SELECT id 
                FROM users 
                WHERE reset_token = ? 
                AND reset_token_expiry > NOW()
            ");
            $stmt->execute([$token]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $stmt = $conn->prepare("
                    UPDATE users 
                    SET password = ?, reset_token = NULL, reset_token_expiry = NULL 
                    WHERE id = ?
                ");
                
                if ($stmt->execute([$password, $user['id']])) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Contraseña actualizada exitosamente'
                    ]);
                } else {
                    throw new Exception('Error al actualizar la contraseña');
                }
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Token inválido o expirado'
                ]);
            }
        }
    }
    // Verificar sesión actual
    else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_SESSION['user'])) {
            echo json_encode([
                'success' => true,
                'data' => $_SESSION['user']
            ]);
        } else {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'No hay sesión activa'
            ]);
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error en el servidor: ' . $e->getMessage()
    ]);
}
?> 