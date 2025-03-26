<?php
session_start();
require_once '../../../includes/Database.php';
require_once '../../../includes/utilities.php';
require_once '../../../vendor/autoload.php';

use Stripe\Stripe;
use Stripe\Checkout\Session;

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user'])) {
    header('Location: /EventManager/views/auth/login.php');
    exit;
}

// Verificar si se recibió el ID de sesión de Stripe
if (!isset($_GET['session_id'])) {
    header('Location: /EventManager/views/events/index.php');
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Buscar el pago asociado con la sesión de Stripe
    $stmt = $conn->prepare("
        SELECT p.*, r.event_id
        FROM payments p
        INNER JOIN registrations r ON p.registration_id = r.id
        WHERE p.payment_data LIKE :session_id
        AND r.user_id = :user_id
        AND p.status = 'pending'
    ");
    $stmt->execute([
        'session_id' => '%' . $_GET['session_id'] . '%',
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

    // Verificar el estado de la sesión de pago
    $session = Session::retrieve($_GET['session_id']);
    
    if ($session->payment_status === 'paid') {
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
                'stripe_session_id' => $session->id,
                'payment_intent' => $session->payment_intent,
                'payment_status' => $session->payment_status,
                'customer' => $session->customer,
                'completed_at' => date('Y-m-d H:i:s')
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
        throw new Exception('El pago no ha sido completado');
    }

} catch (Exception $e) {
    // Redirigir a la página de error
    header('Location: /EventManager/views/events/registration-error.php?error=' . urlencode($e->getMessage()));
    exit;
} 