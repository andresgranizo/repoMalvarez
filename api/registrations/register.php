<?php
session_start();
header('Content-Type: application/json');

require_once '../../includes/Database.php';
require_once '../../includes/utilities.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
    exit;
}

// Obtener datos del POST
$data = json_decode(file_get_contents('php://input'), true);

// Verificar datos requeridos
if (!isset($data['event_id']) || !isset($data['pricing_category_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Verificar que el evento esté publicado y activo
    $stmt = $conn->prepare("
        SELECT e.*, 
               (SELECT COUNT(*) 
                FROM registrations r2 
                WHERE r2.event_id = e.id 
                AND r2.status != 'cancelled') as total_registrations
        FROM events e 
        WHERE e.id = :event_id 
        AND e.status = 'published'
    ");
    $stmt->execute(['event_id' => $data['event_id']]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        throw new Exception('El evento no está disponible para registro');
    }

    // Verificar capacidad del evento
    if ($event['capacity'] > 0 && $event['total_registrations'] >= $event['capacity']) {
        throw new Exception('El evento ha alcanzado su capacidad máxima');
    }

    // Verificar categoría y su disponibilidad
    $stmt = $conn->prepare("
        SELECT pc.*, 
               COALESCE((
                   SELECT COUNT(*) 
                   FROM registrations r 
                   WHERE r.event_id = pc.event_id 
                   AND r.pricing_category_id = pc.id 
                   AND r.status != 'cancelled'
               ), 0) as registered_count
        FROM pricing_categories pc
        WHERE pc.id = :category_id 
        AND pc.event_id = :event_id
        AND pc.is_active = 1
    ");
    $stmt->execute([
        'event_id' => $data['event_id'],
        'category_id' => $data['pricing_category_id']
    ]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$category) {
        throw new Exception('La categoría seleccionada no está disponible');
    }

    if ($category['capacity'] > 0 && $category['registered_count'] >= $category['capacity']) {
        throw new Exception('La categoría seleccionada ha alcanzado su capacidad máxima');
    }

    // Verificar si el usuario ya está registrado
    $stmt = $conn->prepare("
        SELECT id, status FROM registrations 
        WHERE event_id = :event_id 
        AND user_id = :user_id 
        AND status != 'cancelled'
    ");
    $stmt->execute([
        'event_id' => $data['event_id'],
        'user_id' => $_SESSION['user']['id']
    ]);
    $existingRegistration = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingRegistration) {
        if ($existingRegistration['status'] === 'pending') {
            // Si el registro está pendiente, permitir actualizar
            $registrationId = $existingRegistration['id'];
        } else {
            throw new Exception('Ya estás registrado en este evento');
        }
    } else {
        // Generar código de registro único
        $registration_code = strtoupper(uniqid('REG'));

        // Insertar nuevo registro
        $stmt = $conn->prepare("
            INSERT INTO registrations (
                event_id, user_id, pricing_category_id, registration_code,
                status, payment_status, payment_amount, comments, created_at
            ) VALUES (
                :event_id, :user_id, :category_id, :registration_code,
                'pending', 'pending', :payment_amount, :comments, NOW()
            )
        ");

        $stmt->execute([
            'event_id' => $data['event_id'],
            'user_id' => $_SESSION['user']['id'],
            'category_id' => $data['pricing_category_id'],
            'registration_code' => $registration_code,
            'payment_amount' => $category['price'],
            'comments' => $data['registration_data']['comments'] ?? null
        ]);

        $registrationId = $conn->lastInsertId();
    }

    // Si hay un monto a pagar, crear o actualizar el registro de pago
    if ($category['price'] > 0) {
        // Verificar que el método de pago sea válido
        $stmt = $conn->prepare("
            SELECT id, name, type FROM payment_methods 
            WHERE id = :payment_method_id AND is_active = 1
        ");
        $stmt->execute(['payment_method_id' => $data['registration_data']['payment_method_id']]);
        $paymentMethod = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$paymentMethod) {
            throw new Exception('El método de pago seleccionado no es válido');
        }

        // Crear nuevo pago
        $stmt = $conn->prepare("
            INSERT INTO payments (
                registration_id, payment_method_id, amount, status, created_at
            ) VALUES (
                :registration_id, :payment_method_id, :amount, 'pending', NOW()
            )
        ");
        $stmt->execute([
            'registration_id' => $registrationId,
            'payment_method_id' => $data['registration_data']['payment_method_id'],
            'amount' => $category['price']
        ]);

        // Si es transferencia bancaria, redirigir a la página de transferencia
        $redirectUrl = '';
        if ($paymentMethod['name'] === 'Transferencia Bancaria') {
            $redirectUrl = '/EventManager/views/payments/bank-transfer.php?registration_id=' . $registrationId;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Tu solicitud de registro ha sido enviada y está pendiente de confirmación por el organizador',
        'data' => [
            'registration_id' => $registrationId,
            'registration_code' => $registration_code ?? null,
            'amount' => $category['price'],
            'redirect_url' => $redirectUrl ?? null,
            'status' => 'pending'
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 