<?php
session_start();
require_once '../../../includes/Database.php';
require_once '../../../includes/utilities.php';

// Verificar si el usuario está autenticado y es administrador
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: /EventManager/views/auth/login.php');
    exit;
}

$error = null;
$success = null;
$payment = null;

// Verificar si se proporcionó un ID de pago
if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Obtener los detalles del pago
    $stmt = $conn->prepare("
        SELECT p.*, r.registration_code, r.user_id,
               e.title as event_title, 
               pc.name as category_name,
               pm.name as payment_method_name,
               u.name as user_name,
               u.email as user_email
        FROM payments p
        INNER JOIN registrations r ON p.registration_id = r.id
        INNER JOIN events e ON r.event_id = e.id
        INNER JOIN pricing_categories pc ON r.pricing_category_id = pc.id
        INNER JOIN payment_methods pm ON p.payment_method_id = pm.id
        INNER JOIN users u ON r.user_id = u.id
        WHERE p.id = :id
    ");
    $stmt->execute(['id' => $_GET['id']]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        throw new Exception("Pago no encontrado");
    }

    // Procesar la actualización del estado del pago
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['action']) || !in_array($_POST['action'], ['approve', 'reject'])) {
            throw new Exception("Acción inválida");
        }

        $newStatus = $_POST['action'] === 'approve' ? 'completed' : 'rejected';
        $stmt = $conn->prepare("
            UPDATE payments 
            SET status = :status,
                admin_notes = :notes,
                updated_at = NOW()
            WHERE id = :id
        ");
        
        $stmt->execute([
            'status' => $newStatus,
            'notes' => $_POST['notes'] ?? null,
            'id' => $payment['id']
        ]);

        // Si se aprueba el pago, actualizar el estado de la inscripción
        if ($newStatus === 'completed') {
            $stmt = $conn->prepare("
                UPDATE registrations 
                SET status = 'confirmed',
                    updated_at = NOW()
                WHERE id = :registration_id
            ");
            $stmt->execute(['registration_id' => $payment['registration_id']]);
        }

        $success = "El pago ha sido " . ($newStatus === 'completed' ? 'aprobado' : 'rechazado') . " exitosamente.";
        
        // Recargar los detalles del pago
        $stmt = $conn->prepare("SELECT * FROM payments WHERE id = :id");
        $stmt->execute(['id' => $payment['id']]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar Pago - EventManager</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <?php include '../../templates/navigation.php'; ?>

    <div class="container py-5">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Verificar Pago</h4>
                        <a href="index.php" class="btn btn-light btn-sm">
                            <i class="fas fa-arrow-left"></i> Volver
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php elseif ($success): ?>
                            <div class="alert alert-success">
                                <?php echo htmlspecialchars($success); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($payment): ?>
                            <!-- Detalles del Pago -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h5>Detalles de la Transacción</h5>
                                    <table class="table table-bordered">
                                        <tr>
                                            <th>ID de Transacción:</th>
                                            <td><?php echo htmlspecialchars($payment['transaction_id']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Fecha:</th>
                                            <td><?php echo Utilities::formatDate($payment['created_at']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Método de Pago:</th>
                                            <td><?php echo htmlspecialchars($payment['payment_method_name']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Monto:</th>
                                            <td>$<?php echo number_format($payment['amount'], 2); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Estado:</th>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo match($payment['status']) {
                                                        'pending' => 'warning',
                                                        'pending_confirmation' => 'info',
                                                        'pending_review' => 'primary',
                                                        'completed' => 'success',
                                                        'rejected' => 'danger',
                                                        default => 'secondary'
                                                    };
                                                ?>">
                                                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $payment['status']))); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <h5>Detalles del Evento y Usuario</h5>
                                    <table class="table table-bordered">
                                        <tr>
                                            <th>Evento:</th>
                                            <td><?php echo htmlspecialchars($payment['event_title']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Categoría:</th>
                                            <td><?php echo htmlspecialchars($payment['category_name']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Usuario:</th>
                                            <td>
                                                <?php echo htmlspecialchars($payment['user_name']); ?><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($payment['user_email']); ?></small>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Código de Registro:</th>
                                            <td><?php echo htmlspecialchars($payment['registration_code']); ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <?php if ($payment['receipt_url']): ?>
                                <!-- Comprobante de Pago -->
                                <div class="mb-4">
                                    <h5>Comprobante de Pago</h5>
                                    <div class="border p-3">
                                        <?php
                                        $extension = strtolower(pathinfo($payment['receipt_url'], PATHINFO_EXTENSION));
                                        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                            <img src="<?php echo htmlspecialchars($payment['receipt_url']); ?>" 
                                                 class="img-fluid" 
                                                 alt="Comprobante de pago">
                                        <?php else: ?>
                                            <a href="<?php echo htmlspecialchars($payment['receipt_url']); ?>" 
                                               class="btn btn-primary" 
                                               target="_blank">
                                                <i class="fas fa-file-download"></i> 
                                                Ver Comprobante
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (in_array($payment['status'], ['pending_review', 'pending_confirmation'])): ?>
                                <!-- Formulario de Verificación -->
                                <div class="card mt-4">
                                    <div class="card-header">
                                        <h5 class="mb-0">Verificar Pago</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" class="row g-3">
                                            <div class="col-12">
                                                <label for="notes" class="form-label">Notas Administrativas</label>
                                                <textarea name="notes" id="notes" class="form-control" rows="3"
                                                          placeholder="Agregar notas sobre la verificación del pago..."
                                                ><?php echo htmlspecialchars($payment['admin_notes'] ?? ''); ?></textarea>
                                            </div>
                                            <div class="col-12 text-end">
                                                <button type="submit" name="action" value="reject" 
                                                        class="btn btn-danger me-2"
                                                        onclick="return confirm('¿Está seguro de rechazar este pago?')">
                                                    <i class="fas fa-times"></i> Rechazar
                                                </button>
                                                <button type="submit" name="action" value="approve" 
                                                        class="btn btn-success"
                                                        onclick="return confirm('¿Está seguro de aprobar este pago?')">
                                                    <i class="fas fa-check"></i> Aprobar
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            <?php else: ?>
                                <!-- Notas Administrativas (Solo lectura) -->
                                <?php if (!empty($payment['admin_notes'])): ?>
                                    <div class="mt-4">
                                        <h5>Notas Administrativas</h5>
                                        <div class="border p-3 bg-light">
                                            <?php echo nl2br(htmlspecialchars($payment['admin_notes'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 