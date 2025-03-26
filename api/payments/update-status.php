<?php
session_start();
header('Content-Type: application/json');

require_once '../../includes/Database.php';
require_once '../../includes/utilities.php';

// Verificar si el usuario está autenticado y es organizador
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'organizer') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Obtener datos del POST
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['registration_id']) || !isset($data['status'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Verificar que el registro pertenezca a un evento del organizador
    $stmt = $conn->prepare("
        SELECT r.id, e.organizer_id 
        FROM registrations r
        INNER JOIN events e ON r.event_id = e.id
        WHERE r.id = :registration_id
    ");
    $stmt->execute(['registration_id' => $data['registration_id']]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$registration || $registration['organizer_id'] != $_SESSION['user']['id']) {
        throw new Exception('No tienes permiso para modificar este registro');
    }

    // Iniciar transacción
    $conn->beginTransaction();

    try {
        // Actualizar estado del pago
        $stmt = $conn->prepare("
            UPDATE payments 
            SET status = :status,
                payment_date = NOW()
            WHERE registration_id = :registration_id
        ");
        $stmt->execute([
            'status' => $data['status'],
            'registration_id' => $data['registration_id']
        ]);

        // Actualizar estado del registro
        $registrationStatus = $data['status'] === 'completed' ? 'confirmed' : 'pending';
        $stmt = $conn->prepare("
            UPDATE registrations 
            SET status = :status,
                payment_status = :payment_status
            WHERE id = :registration_id
        ");
        $stmt->execute([
            'status' => $registrationStatus,
            'payment_status' => $data['status'],
            'registration_id' => $data['registration_id']
        ]);

        // Confirmar transacción
        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Estado actualizado exitosamente'
        ]);

    } catch (Exception $e) {
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