<?php
session_start();
require_once '../../../includes/Database.php';
require_once '../../../includes/utilities.php';
require_once '../../../vendor/autoload.php';

use Stripe\Stripe;
use Stripe\Checkout\Session;

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

    // Configurar Stripe
    $stripeSecretKey = getenv('STRIPE_SECRET_KEY');
    if (!$stripeSecretKey) {
        throw new Exception('Configuración de Stripe no encontrada');
    }
    Stripe::setApiKey($stripeSecretKey);

    // Crear la sesión de pago de Stripe
    $session = Session::create([
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price_data' => [
                'currency' => 'usd',
                'unit_amount' => $payment['amount'] * 100, // Convertir a centavos
                'product_data' => [
                    'name' => $payment['event_title'],
                    'description' => "Registro - {$payment['category_name']}"
                ],
            ],
            'quantity' => 1,
        ]],
        'mode' => 'payment',
        'success_url' => 'https://tudominio.com/EventManager/api/payments/stripe/success.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => 'https://tudominio.com/EventManager/views/events/register.php?event_id=' . $payment['event_id'],
        'client_reference_id' => $payment['transaction_id'],
        'customer_email' => $_SESSION['user']['email'],
        'metadata' => [
            'payment_id' => $payment['id'],
            'registration_id' => $payment['registration_id']
        ]
    ]);

    // Actualizar el pago con el ID de sesión de Stripe
    $stmt = $conn->prepare("
        UPDATE payments 
        SET payment_data = :payment_data,
            updated_at = NOW()
        WHERE id = :id
    ");
    $stmt->execute([
        'payment_data' => json_encode([
            'stripe_session_id' => $session->id,
            'create_time' => date('Y-m-d H:i:s')
        ]),
        'id' => $payment['id']
    ]);

    // Devolver el ID de sesión
    echo json_encode([
        'success' => true,
        'session_id' => $session->id
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} 