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

// Verificar datos requeridos
if (!isset($_POST['registration_id']) || !isset($_FILES['receipt'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Verificar que el registro pertenezca al usuario
    $stmt = $conn->prepare("
        SELECT r.*, pc.price 
        FROM registrations r
        INNER JOIN pricing_categories pc ON r.pricing_category_id = pc.id
        WHERE r.id = :registration_id AND r.user_id = :user_id
    ");
    $stmt->execute([
        'registration_id' => $_POST['registration_id'],
        'user_id' => $_SESSION['user']['id']
    ]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$registration) {
        throw new Exception('Registro no encontrado');
    }

    // Validar el archivo
    $file = $_FILES['receipt'];
    $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
    $maxSize = 5 * 1024 * 1024; // 5MB

    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Tipo de archivo no permitido. Solo se permiten JPG, PNG y PDF');
    }

    if ($file['size'] > $maxSize) {
        throw new Exception('El archivo es demasiado grande. Tamaño máximo: 5MB');
    }

    // Iniciar transacción
    $conn->beginTransaction();

    try {
        // Verificar si ya existe un pago
        $stmt = $conn->prepare("
            SELECT id FROM payments 
            WHERE registration_id = :registration_id
        ");
        $stmt->execute(['registration_id' => $registration['id']]);
        $existingPayment = $stmt->fetch();

        if ($existingPayment) {
            // Actualizar pago existente
            $stmt = $conn->prepare("
                UPDATE payments 
                SET transaction_id = :transaction_id,
                    amount = :amount,
                    payment_date = NOW(),
                    status = 'pending'
                WHERE registration_id = :registration_id
            ");
        } else {
            // Crear nuevo pago
            $stmt = $conn->prepare("
                INSERT INTO payments (
                    registration_id, payment_method_id, amount,
                    transaction_id, status, payment_date
                ) VALUES (
                    :registration_id, 3, :amount,
                    :transaction_id, 'pending', NOW()
                )
            ");
        }

        // Guardar el archivo en la carpeta de uploads
        $uploadDir = '../../uploads/receipts/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Generar nombre único para el archivo
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = uniqid('receipt_') . '.' . $extension;
        $filePath = $uploadDir . $fileName;

        // Mover el archivo
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new Exception('Error al guardar el archivo');
        }

        $stmt->execute([
            'registration_id' => $registration['id'],
            'amount' => $_POST['amount'],
            'transaction_id' => $fileName // Usamos el nombre del archivo como transaction_id
        ]);

        // Actualizar estado del registro
        $stmt = $conn->prepare("
            UPDATE registrations 
            SET payment_status = 'pending'
            WHERE id = :registration_id
        ");
        $stmt->execute(['registration_id' => $registration['id']]);

        // Confirmar transacción
        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Comprobante subido exitosamente',
            'data' => [
                'transaction_id' => $fileName
            ]
        ]);

    } catch (Exception $e) {
        // Revertir transacción y eliminar archivo en caso de error
        $conn->rollBack();
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 