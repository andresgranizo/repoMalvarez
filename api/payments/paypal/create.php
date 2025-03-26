<?php
session_start();
require_once '../../../includes/Database.php';
require_once '../../../includes/utilities.php';
require_once '../../../vendor/autoload.php';

use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;

header('Content-Type: application/json');

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Usuario no autenticado']);
    exit;
}

// Verificar si se recibió el ID del pago
if (!isset($_POST['payment_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de pago no proporcionado']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Obtener los detalles del pago
    $stmt = $conn->prepare("
        SELECT p.*, e.title as event_title, pc.name as category_name
        FROM payments p
        INNER JOIN registrations r ON p.registration_id = r.id
        INNER JOIN events e ON r.event_id = e.id
        INNER JOIN pricing_categories pc ON r.pricing_category_id = pc.id
        WHERE p.id = :id AND r.user_id = :user_id AND p.status = 'pending'
    ");
    $stmt->execute([
        'id' => $_POST['payment_id'],
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

    // Crear la orden de PayPal
    $request = new OrdersCreateRequest();
    $request->prefer('return=representation');
    $request->body = [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'reference_id' => $payment['transaction_id'],
            'description' => "Registro para {$payment['event_title']} - {$payment['category_name']}",
            'amount' => [
                'currency_code' => 'USD',
                'value' => number_format($payment['amount'], 2, '.', '')
            ]
        ]],
        'application_context' => [
            'return_url' => 'https://tudominio.com/EventManager/api/payments/paypal/capture.php',
            'cancel_url' => 'https://tudominio.com/EventManager/views/events/register.php?event_id=' . $payment['event_id']
        ]
    ];

    // Ejecutar la solicitud
    $response = $client->execute($request);

    // Actualizar el pago con el ID de orden de PayPal
    $stmt = $conn->prepare("
        UPDATE payments 
        SET payment_data = :payment_data,
            updated_at = NOW()
        WHERE id = :id
    ");
    $stmt->execute([
        'payment_data' => json_encode([
            'paypal_order_id' => $response->result->id,
            'create_time' => $response->result->create_time
        ]),
        'id' => $payment['id']
    ]);

    // Devolver la URL de aprobación
    $approveLink = array_filter($response->result->links, function($link) {
        return $link->rel === 'approve';
    });
    $approveLink = reset($approveLink);

    echo json_encode([
        'success' => true,
        'redirect_url' => $approveLink->href
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} 