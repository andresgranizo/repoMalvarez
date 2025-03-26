<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("Access-Control-Max-Age: 3600");

// Activar el registro de errores
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Función para log
function debug_log($message) {
    error_log("[DEBUG] " . print_r($message, true));
}

require_once '../includes/Database.php';

// Verificar si el usuario está autenticado y es administrador
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Acceso denegado'
    ]);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Obtener el método de la solicitud
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Obtener datos del cuerpo de la solicitud
    $data = json_decode(file_get_contents("php://input"), true);
    
    // Manejar la solicitud OPTIONS para CORS
    if ($method === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
    
    switch ($method) {
        case 'PUT':
            // Actualizar estado del usuario
            if (!isset($data['id']) || !isset($data['status'])) {
                throw new Exception("Datos incompletos");
            }
            
            // Verificar que el usuario existe y no es el admin actual
            $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND id != ?");
            $stmt->execute([$data['id'], $_SESSION['user']['id']]);
            if ($stmt->rowCount() === 0) {
                throw new Exception("Usuario no encontrado o no se puede modificar");
            }
            
            // Actualizar estado
            $stmt = $conn->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$data['status'], $data['id']]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Estado actualizado exitosamente'
            ]);
            break;
            
        case 'DELETE':
            debug_log("Iniciando proceso de eliminación de usuario");
            // Obtener ID del usuario a eliminar
            $user_id = $_GET['id'] ?? null;
            debug_log("ID de usuario a eliminar: " . $user_id);
            
            if (!$user_id) {
                throw new Exception("ID de usuario no proporcionado");
            }
            
            // Verificar que el usuario existe y no es el admin actual
            $stmt = $conn->prepare("SELECT id, role FROM users WHERE id = ? AND id != ?");
            $stmt->execute([$user_id, $_SESSION['user']['id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            debug_log("Usuario encontrado: " . print_r($user, true));
            
            if (!$user) {
                throw new Exception("Usuario no encontrado o no se puede eliminar");
            }
            
            // Iniciar transacción
            $conn->beginTransaction();
            debug_log("Iniciando transacción");
            
            try {
                // Primero eliminar registros que dependen de events
                $stmt = $conn->prepare("DELETE FROM registrations WHERE event_id IN (SELECT id FROM events WHERE organizer_id = ?)");
                $stmt->execute([$user_id]);
                debug_log("Registros de eventos eliminados");
                
                // Luego eliminar los registros del usuario
                $stmt = $conn->prepare("DELETE FROM registrations WHERE user_id = ?");
                $stmt->execute([$user_id]);
                debug_log("Registros de usuario eliminados");
                
                // Eliminar historial de inicio de sesión
                $stmt = $conn->prepare("DELETE FROM login_history WHERE user_id = ?");
                $stmt->execute([$user_id]);
                debug_log("Historial de inicio de sesión eliminado");
                
                // Eliminar eventos organizados por el usuario
                $stmt = $conn->prepare("DELETE FROM events WHERE organizer_id = ?");
                $stmt->execute([$user_id]);
                debug_log("Eventos organizados eliminados");
                
                // Finalmente, eliminar el usuario
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                debug_log("Usuario eliminado");
                
                // Confirmar transacción
                $conn->commit();
                debug_log("Transacción completada");
                
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Usuario eliminado exitosamente'
                ]);
            } catch (Exception $e) {
                // Revertir cambios si hay error
                $conn->rollBack();
                debug_log("Error en la transacción: " . $e->getMessage());
                throw new Exception("Error al eliminar el usuario: " . $e->getMessage());
            }
            break;
            
        default:
            throw new Exception("Método no permitido");
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 