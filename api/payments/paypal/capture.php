<?php
session_start();
require_once '../../../includes/Database.php';
require_once '../../../includes/utilities.php';
require_once '../../../vendor/autoload.php';

use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Orders\OrdersCaptureRequest;

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user'])) {
    header('Location: /EventManager/views/auth/login.php');
    exit;
}

// Verificar si se recibió el token de PayPal
if (!isset($_GET['token'])) {
    header('Location: /EventManager/views/events/index.php');
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Buscar el pago asociado con la orden de PayPal
    $stmt = $conn->prepare("
        SELECT p.*, r.event_id
        FROM payments p
        INNER JOIN registrations r ON p.registration_id = r.id
        WHERE p.payment_data LIKE :token
        AND r.user_id = :user_id
        AND p.status = 'pending'
    ");
    $stmt->execute([
        'token' => '%' . $_GET['token'] . '%',
        'user_id' => $_SESSION['user']['id']
    ]);
    
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment) {
        throw new Exception('Pago no encontrado o no autorizado');
    }

    // Configurar el cliente de PayPal
    $clientId = getenv('PAYPAL_CLIENT_ID');
    $clientSecret = getenv('PAYPAL_CLIENT_SECRET');

    if (!$clientId || !$clientSecret) {
        throw new Exception('Configuración de PayPal no encontrada');
    }

    $environment = new SandboxEnvironment($clientId, $clientSecret);
    $client = new PayPalHttpClient($environment);

    // Obtener el ID de la orden de PayPal
    $paymentData = json_decode($payment['payment_data'], true);
    $paypalOrderId = $paymentData['paypal_order_id'];

    // Capturar el pago
    $request = new OrdersCaptureRequest($paypalOrderId);
    $response = $client->execute($request);

    // Verificar el estado de la captura
    if ($response->result->status === 'COMPLETED') {
        // Actualizar el estado del pago
        $stmt = $conn->prepare("
            UPDATE payments 
            SET status = 'completed',
                payment_data = :payment_data,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'payment_data' => json_encode([
                'paypal_order_id' => $paypalOrderId,
                'capture_id' => $response->result->purchase_units[0]->payments->captures[0]->id,
                'capture_time' => $response->result->purchase_units[0]->payments->captures[0]->create_time,
                'capture_status' => $response->result->status
            ]),
            'id' => $payment['id']
        ]);

        // Actualizar el estado de la inscripción
        $stmt = $conn->prepare("
            UPDATE registrations 
            SET status = 'confirmed',
                updated_at = NOW()
            WHERE id = :registration_id
        ");
        $stmt->execute(['registration_id' => $payment['registration_id']]);

        // Redirigir a la página de éxito
        header('Location: /EventManager/views/events/registration-success.php?event_id=' . $payment['event_id']);
        exit;
    } else {
        throw new Exception('El pago no pudo ser completado');
    }

} catch (Exception $e) {
    // Redirigir a la página de error
    header('Location: /EventManager/views/events/registration-error.php?error=' . urlencode($e->getMessage()));
    exit;
} 