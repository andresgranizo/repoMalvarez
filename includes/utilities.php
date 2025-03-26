<?php
require_once 'Database.php';

class Utilities {
    /**
     * Genera un código único para registros
     * @param int $length Longitud del código
     * @return string Código generado
     */
    public static function generateUniqueCode($length = 8) {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $code;
    }

    /**
     * Genera un código QR
     * @param string $data Datos para el código QR
     * @return string URL de la imagen del código QR
     */
    public static function generateQRCode($data) {
        // Aquí se implementaría la generación del código QR
        // Por ahora, solo devolvemos una URL de ejemplo
        return 'qr_' . md5($data) . '.png';
    }

    /**
     * Formatea una fecha para mostrar
     * @param string $date Fecha en formato MySQL
     * @param string $format Formato deseado (default: d/m/Y H:i)
     * @return string Fecha formateada
     */
    public static function formatDate($date, $format = 'd/m/Y H:i') {
        return date($format, strtotime($date));
    }

    /**
     * Formatea un precio para mostrar
     * @param float $amount Cantidad
     * @param string $currency Moneda (default: EUR)
     * @return string Precio formateado
     */
    public static function formatPrice($amount, $currency = 'EUR') {
        $symbols = [
            'EUR' => '€',
            'USD' => '$',
            'GBP' => '£'
        ];
        
        return number_format($amount, 2, ',', '.') . ' ' . ($symbols[$currency] ?? $currency);
    }

    /**
     * Sanitiza una cadena para uso seguro
     * @param string $string Cadena a sanitizar
     * @return string Cadena sanitizada
     */
    public static function sanitizeString($string) {
        // Reemplazar FILTER_SANITIZE_STRING (deprecado) con una alternativa segura
        $string = strip_tags($string);
        $string = htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
        return trim($string);
    }

    /**
     * Valida un email
     * @param string $email Email a validar
     * @return bool True si es válido
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Envía un email usando la configuración SMTP
     * @param string $to Destinatario
     * @param string $subject Asunto
     * @param string $message Mensaje
     * @return bool True si se envió correctamente
     */
    public static function sendEmail($to, $subject, $message) {
        // Configuración de cabeceras
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= 'From: EventManager <noreply@eventmanager.com>' . "\r\n";

        // Enviar email
        return mail($to, $subject, $message, $headers);
    }

    /**
     * Obtiene la configuración SMTP desde la base de datos
     * @return array Configuración SMTP
     */
    private static function getSmtpConfig() {
        try {
            $db = new PDO(
                "mysql:host=localhost;dbname=eventmanager;charset=utf8mb4",
                "root",
                "",
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'smtp_%'");
            $config = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $config[$row['setting_key']] = $row['setting_value'];
            }

            return [
                'host' => $config['smtp_host'] ?? 'smtp.gmail.com',
                'port' => $config['smtp_port'] ?? '587',
                'user' => $config['smtp_user'] ?? '',
                'password' => $config['smtp_password'] ?? '',
                'from_email' => $config['smtp_user'] ?? 'noreply@eventmanager.com'
            ];
        } catch (PDOException $e) {
            error_log("Error obteniendo configuración SMTP: " . $e->getMessage());
            return [
                'host' => 'smtp.gmail.com',
                'port' => '587',
                'user' => '',
                'password' => '',
                'from_email' => 'noreply@eventmanager.com'
            ];
        }
    }

    /**
     * Genera un slug a partir de un texto
     * @param string $text Texto a convertir
     * @return string Slug generado
     */
    public static function generateSlug($text) {
        // Convertir a minúsculas
        $text = strtolower($text);
        
        // Reemplazar caracteres especiales
        $text = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ', ' '],
            ['a', 'e', 'i', 'o', 'u', 'n', '-'],
            $text
        );
        
        // Eliminar caracteres no alfanuméricos
        $text = preg_replace('/[^a-z0-9\-]/', '', $text);
        
        // Eliminar guiones múltiples
        $text = preg_replace('/-+/', '-', $text);
        
        // Eliminar guiones al inicio y final
        return trim($text, '-');
    }

    /**
     * Verifica si un usuario tiene permiso para una acción
     * @param string $action Acción a verificar
     * @param string $userRole Rol del usuario
     * @return bool True si tiene permiso
     */
    public static function checkPermission($action, $userRole) {
        $permissions = [
            'admin' => ['*'],
            'organizer' => [
                'create_event',
                'edit_event',
                'delete_event',
                'view_registrations',
                'manage_categories'
            ],
            'user' => [
                'view_event',
                'register_event'
            ]
        ];

        if ($userRole === 'admin') {
            return true;
        }

        return in_array($action, $permissions[$userRole] ?? []);
    }

    /**
     * Registra un error en el log
     * @param string $message Mensaje de error
     * @param string $type Tipo de error
     */
    public static function logError($message, $type = 'ERROR') {
        $logFile = __DIR__ . '/../logs/error.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $type: $message\n";
        
        if (!file_exists(dirname($logFile))) {
            mkdir(dirname($logFile), 0777, true);
        }
        
        error_log($logMessage, 3, $logFile);
    }

    /**
     * Obtiene el estado de un evento basado en sus fechas
     * @param string $startDate Fecha de inicio
     * @param string $endDate Fecha de fin
     * @param string $status Estado actual
     * @return string Estado calculado
     */
    public static function calculateEventStatus($startDate, $endDate, $status) {
        if ($status === 'cancelled') {
            return 'cancelled';
        }

        $now = time();
        $start = strtotime($startDate);
        $end = strtotime($endDate);

        if ($now < $start) {
            return 'upcoming';
        } elseif ($now >= $start && $now <= $end) {
            return 'in_progress';
        } else {
            return 'completed';
        }
    }

    /**
     * Genera una contraseña aleatoria segura
     * @param int $length Longitud de la contraseña
     * @return string Contraseña generada
     */
    public static function generatePassword($length = 12) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+';
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        return $password;
    }

    /**
     * Calcula la disponibilidad de un evento
     * @param int $maxCapacity Capacidad máxima
     * @param int $registered Registros actuales
     * @return array Información de disponibilidad
     */
    public static function calculateAvailability($maxCapacity, $registered) {
        $available = $maxCapacity - $registered;
        $percentage = ($registered / $maxCapacity) * 100;
        
        return [
            'available' => $available,
            'percentage' => round($percentage, 2),
            'status' => $available > 0 ? 'available' : 'full'
        ];
    }

    public static function generateRandomString($length = 32) {
        return bin2hex(random_bytes($length));
    }

    public static function validatePassword($password) {
        // Mínimo 8 caracteres, al menos una letra y un número
        return strlen($password) >= 8 && 
               preg_match('/[A-Za-z]/', $password) && 
               preg_match('/[0-9]/', $password);
    }
}

// Función para verificar si un usuario tiene un permiso específico
function hasPermission($permission) {
    if (!isset($_SESSION['user'])) {
        return false;
    }

    try {
        $db = new Database();
        $conn = $db->getConnection();

        $stmt = $conn->prepare("
            SELECT COUNT(*) as count
            FROM role_permissions rp
            JOIN permissions p ON rp.permission_id = p.id
            JOIN roles r ON rp.role_id = r.id
            WHERE r.name = ? AND p.name = ?
        ");
        $stmt->execute([$_SESSION['user']['role'], $permission]);
        $result = $stmt->fetch();

        return $result['count'] > 0;

    } catch (Exception $e) {
        error_log("Error al verificar permiso: " . $e->getMessage());
        return false;
    }
}

// Función para generar un número de ticket único
function generateTicketNumber() {
    return 'TKT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

// Función para validar fechas
function validateDates($start_date, $end_date) {
    $start = strtotime($start_date);
    $end = strtotime($end_date);
    return $start && $end && $start <= $end;
}

// Función para obtener el estado de un registro en español
function getRegistrationStatus($status) {
    return match($status) {
        'pending' => 'Pendiente',
        'confirmed' => 'Confirmado',
        'cancelled' => 'Cancelado',
        default => 'Desconocido'
    };
}

// Función para obtener el estado de un pago en español
function getPaymentStatus($status) {
    return match($status) {
        'pending' => 'Pendiente',
        'completed' => 'Completado',
        'failed' => 'Fallido',
        default => 'Desconocido'
    };
}

// Función para obtener el tipo de método de pago en español
function getPaymentMethodType($type) {
    return match($type) {
        'online' => 'En línea',
        'transfer' => 'Transferencia',
        default => 'Desconocido'
    };
}

// Función para obtener las categorías de precios de un evento
function getEventPricingCategories($event_id) {
    try {
        $db = new Database();
        $conn = $db->getConnection();

        $stmt = $conn->prepare("
            SELECT *
            FROM pricing_categories
            WHERE event_id = ? AND is_active = TRUE
            AND (start_date IS NULL OR start_date <= NOW())
            AND (end_date IS NULL OR end_date >= NOW())
            ORDER BY price ASC
        ");
        $stmt->execute([$event_id]);
        return $stmt->fetchAll();

    } catch (Exception $e) {
        error_log("Error al obtener categorías de precios: " . $e->getMessage());
        return [];
    }
}

// Función para obtener los métodos de pago activos
function getActivePaymentMethods() {
    try {
        $db = new Database();
        $conn = $db->getConnection();

        $stmt = $conn->prepare("
            SELECT *
            FROM payment_methods
            WHERE is_active = TRUE
            ORDER BY name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();

    } catch (Exception $e) {
        error_log("Error al obtener métodos de pago: " . $e->getMessage());
        return [];
    }
}

// Función para procesar un pago
function processPayment($registration_id, $payment_method_id, $amount) {
    try {
        $db = new Database();
        $conn = $db->getConnection();

        // Iniciar transacción
        $conn->beginTransaction();

        // Crear el registro de pago
        $stmt = $conn->prepare("
            INSERT INTO payments (
                registration_id,
                payment_method_id,
                amount,
                status,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, 'pending', NOW(), NOW())
        ");
        $stmt->execute([$registration_id, $payment_method_id, $amount]);
        $payment_id = $conn->lastInsertId();

        // Obtener información del método de pago
        $stmt = $conn->prepare("
            SELECT type, provider, config
            FROM payment_methods
            WHERE id = ?
        ");
        $stmt->execute([$payment_method_id]);
        $payment_method = $stmt->fetch();

        // Procesar según el tipo de pago
        switch ($payment_method['type']) {
            case 'online':
                // Aquí se implementaría la integración con la pasarela de pago
                // Por ahora, simulamos un pago exitoso
                $payment_data = [
                    'transaction_id' => uniqid('TRX-'),
                    'status' => 'completed',
                    'provider_response' => json_encode([
                        'success' => true,
                        'message' => 'Pago procesado exitosamente'
                    ])
                ];

                // Actualizar el pago
                $stmt = $conn->prepare("
                    UPDATE payments
                    SET status = ?,
                        transaction_id = ?,
                        payment_data = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute(['completed', $payment_data['transaction_id'], json_encode($payment_data), $payment_id]);

                // Confirmar el registro
                $stmt = $conn->prepare("
                    UPDATE registrations
                    SET status = 'confirmed',
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$registration_id]);
                break;

            case 'transfer':
                // Para transferencias, el pago queda pendiente
                $payment_data = [
                    'bank_info' => json_decode($payment_method['config'], true)
                ];

                // Actualizar el pago con la información bancaria
                $stmt = $conn->prepare("
                    UPDATE payments
                    SET payment_data = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([json_encode($payment_data), $payment_id]);
                break;

            default:
                throw new Exception('Tipo de pago no soportado');
        }

        // Confirmar transacción
        $conn->commit();

        return [
            'success' => true,
            'payment_id' => $payment_id,
            'payment_data' => $payment_data
        ];

    } catch (Exception $e) {
        // Revertir transacción en caso de error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        error_log("Error al procesar pago: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

// Función para verificar si un usuario puede registrarse en un evento
function canRegisterForEvent($event_id, $user_id) {
    try {
        $db = new Database();
        $conn = $db->getConnection();

        // Verificar si el evento existe y está publicado
        $stmt = $conn->prepare("
            SELECT e.*, COUNT(r.id) as registration_count
            FROM events e
            LEFT JOIN registrations r ON e.id = r.event_id AND r.status != 'cancelled'
            WHERE e.id = ? AND e.status = 'published' AND e.is_active = TRUE
            GROUP BY e.id
        ");
        $stmt->execute([$event_id]);
        $event = $stmt->fetch();

        if (!$event) {
            return [
                'can_register' => false,
                'message' => 'El evento no está disponible para registro'
            ];
        }

        // Verificar si el evento está lleno
        if ($event['capacity'] && $event['registration_count'] >= $event['capacity']) {
            return [
                'can_register' => false,
                'message' => 'El evento está lleno'
            ];
        }

        // Verificar si el usuario ya está registrado
        $stmt = $conn->prepare("
            SELECT id
            FROM registrations
            WHERE event_id = ? AND user_id = ? AND status != 'cancelled'
        ");
        $stmt->execute([$event_id, $user_id]);
        if ($stmt->fetch()) {
            return [
                'can_register' => false,
                'message' => 'Ya estás registrado en este evento'
            ];
        }

        // Verificar si hay categorías de precio disponibles
        $pricing_categories = getEventPricingCategories($event_id);
        if (empty($pricing_categories)) {
            return [
                'can_register' => false,
                'message' => 'No hay categorías de precio disponibles'
            ];
        }

        return [
            'can_register' => true,
            'event' => $event,
            'pricing_categories' => $pricing_categories
        ];

    } catch (Exception $e) {
        error_log("Error al verificar registro: " . $e->getMessage());
        return [
            'can_register' => false,
            'message' => 'Error al verificar el registro'
        ];
    }
}

// Función para crear un nuevo registro
function createRegistration($event_id, $user_id, $pricing_category_id, $custom_fields = null) {
    try {
        $db = new Database();
        $conn = $db->getConnection();

        // Iniciar transacción
        $conn->beginTransaction();

        // Verificar si puede registrarse
        $check = canRegisterForEvent($event_id, $user_id);
        if (!$check['can_register']) {
            throw new Exception($check['message']);
        }

        // Generar número de ticket y código QR
        $ticket_number = generateTicketNumber();
        $qr_code = generateQRCode($ticket_number);

        // Crear el registro
        $stmt = $conn->prepare("
            INSERT INTO registrations (
                event_id,
                user_id,
                pricing_category_id,
                status,
                ticket_number,
                qr_code,
                custom_fields,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, 'pending', ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $event_id,
            $user_id,
            $pricing_category_id,
            $ticket_number,
            $qr_code,
            $custom_fields ? json_encode($custom_fields) : null
        ]);
        $registration_id = $conn->lastInsertId();

        // Confirmar transacción
        $conn->commit();

        return [
            'success' => true,
            'registration_id' => $registration_id,
            'ticket_number' => $ticket_number
        ];

    } catch (Exception $e) {
        // Revertir transacción en caso de error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        error_log("Error al crear registro: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}
?> 