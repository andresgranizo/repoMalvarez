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
if (!isset($data['registration_id']) || !isset($data['payment_method_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Verificar el registro y obtener detalles
    $stmt = $conn->prepare("
        SELECT r.*, e.title as event_title, pc.name as category_name
        FROM registrations r
        INNER JOIN events e ON r.event_id = e.id
        INNER JOIN pricing_categories pc ON r.pricing_category_id = pc.id
        WHERE r.id = :id AND r.user_id = :user_id
        AND r.payment_status = 'pending'
    ");
    $stmt->execute([
        'id' => $data['registration_id'],
        'user_id' => $_SESSION['user']['id']
    ]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$registration) {
        throw new Exception('Registro no encontrado o ya fue pagado');
    }

    // Verificar método de pago
    $stmt = $conn->prepare("SELECT * FROM payment_methods WHERE id = :id AND is_active = 1");
    $stmt->execute(['id' => $data['payment_method_id']]);
    $paymentMethod = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$paymentMethod) {
        throw new Exception('Método de pago no válido');
    }

    // Generar ID de transacción único
    $transactionId = strtoupper(uniqid('TRX'));

    // Insertar registro de pago
    $stmt = $conn->prepare("
        INSERT INTO payments (
            registration_id, payment_method_id, amount,
            status, transaction_id, created_at
        ) VALUES (
            :registration_id, :payment_method_id, :amount,
            'pending', :transaction_id, NOW()
        )
    ");

    $stmt->execute([
        'registration_id' => $registration['id'],
        'payment_method_id' => $data['payment_method_id'],
        'amount' => $registration['payment_amount'],
        'transaction_id' => $transactionId
    ]);

    $paymentId = $conn->lastInsertId();

    // Preparar respuesta según el método de pago
    switch($paymentMethod['name']) {
        case 'PayPal':
            $response = [
                'success' => true,
                'redirect_url' => "/EventManager/views/payments/paypal.php?payment_id=" . $paymentId,
                'message' => 'Redirigiendo a PayPal...'
            ];
            break;
            
        case 'Stripe':
            $response = [
                'success' => true,
                'redirect_url' => "/EventManager/views/payments/stripe.php?payment_id=" . $paymentId,
                'message' => 'Redirigiendo a Stripe...'
            ];
            break;
            
        case 'Transferencia Bancaria':
            // Actualizar estado del pago
            $stmt = $conn->prepare("
                UPDATE payments 
                SET status = 'pending_confirmation',
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute(['id' => $paymentId]);

            $response = [
                'success' => true,
                'redirect_url' => "/EventManager/views/payments/bank-transfer.php?payment_id=" . $paymentId,
                'message' => 'Redirigiendo a las instrucciones de transferencia bancaria...'
            ];
            break;
            
        default:
            throw new Exception('Método de pago no implementado');
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 