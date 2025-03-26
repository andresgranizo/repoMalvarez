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
if (!isset($data['registration_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de registro no proporcionado']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Verificar que el registro pertenezca al usuario
    $stmt = $conn->prepare("
        SELECT r.*, e.title as event_title
        FROM registrations r
        INNER JOIN events e ON r.event_id = e.id
        WHERE r.id = :registration_id 
        AND r.user_id = :user_id
        AND r.status != 'cancelled'
    ");
    $stmt->execute([
        'registration_id' => $data['registration_id'],
        'user_id' => $_SESSION['user']['id']
    ]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$registration) {
        throw new Exception('Registro no encontrado o no tienes permiso para cancelarlo');
    }

    // Iniciar transacción
    $conn->beginTransaction();

    try {
        // Actualizar el estado del registro
        $stmt = $conn->prepare("
            UPDATE registrations 
            SET status = 'cancelled',
                payment_status = 'cancelled'
            WHERE id = :registration_id
        ");
        $stmt->execute(['registration_id' => $data['registration_id']]);

        // Si hay un pago pendiente, actualizar su estado
        if ($registration['payment_status'] === 'pending') {
            $stmt = $conn->prepare("
                UPDATE payments 
                SET status = 'cancelled',
                    payment_date = NOW()
                WHERE registration_id = :registration_id
            ");
            $stmt->execute(['registration_id' => $data['registration_id']]);
        }

        // Confirmar transacción
        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Registro cancelado exitosamente',
            'data' => [
                'event_title' => $registration['event_title'],
                'registration_code' => $registration['registration_code']
            ]
        ]);

    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $conn->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 