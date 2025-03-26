<?php
require_once '../../includes/auth.php';
require_once '../../includes/config.php';
require_once '../../includes/Database.php';

// Verificar si el usuario está autenticado y es organizador
if (!isAuthenticated() || !hasRole('organizer')) {
    header('Location: ../auth/login.php');
    exit;
}

$db = new Database();
$conn = $db->getConnection();
$user_id = $_SESSION['user']['id'];
$success_message = '';
$error_message = '';

// Obtener el ID del evento si se proporciona
$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

// Verificar si el evento pertenece al organizador
if ($event_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM events WHERE id = :event_id AND organizer_id = :user_id");
    $stmt->execute([
        ':event_id' => $event_id,
        ':user_id' => $user_id
    ]);
    
    if ($stmt->rowCount() === 0) {
        header('Location: ../events/manage.php');
        exit;
    }
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Procesar acciones de pago
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $payment_id = intval($_POST['payment_id']);
    
    if ($_POST['action'] === 'confirm') {
        $stmt = $conn->prepare("
            UPDATE payments p
            JOIN registrations r ON p.registration_id = r.id
            SET p.status = 'completed', 
                p.payment_date = CURRENT_TIMESTAMP,
                r.status = 'confirmed'
            WHERE p.id = :payment_id AND r.event_id = :event_id
        ");
        
        if ($stmt->execute([':payment_id' => $payment_id, ':event_id' => $event_id])) {
            $success_message = "Pago confirmado exitosamente.";
        } else {
            $error_message = "Error al confirmar el pago.";
        }
    } elseif ($_POST['action'] === 'reject') {
        $stmt = $conn->prepare("
            UPDATE payments p
            JOIN registrations r ON p.registration_id = r.id
            SET p.status = 'failed',
                r.status = 'cancelled'
            WHERE p.id = :payment_id AND r.event_id = :event_id
        ");
        
        if ($stmt->execute([':payment_id' => $payment_id, ':event_id' => $event_id])) {
            $success_message = "Pago rechazado exitosamente.";
        } else {
            $error_message = "Error al rechazar el pago.";
        }
    }
}

// Obtener los pagos del evento
$payments = [];
if ($event_id > 0) {
    $stmt = $conn->prepare("
        SELECT p.*, r.*, u.name as user_name, u.email, 
               pc.name as category_name, pc.price,
               pm.name as payment_method
        FROM payments p
        JOIN registrations r ON p.registration_id = r.id
        JOIN users u ON r.user_id = u.id
        JOIN pricing_categories pc ON r.pricing_category_id = pc.id
        JOIN payment_methods pm ON p.payment_method_id = pm.id
        WHERE r.event_id = :event_id
        ORDER BY p.created_at DESC
    ");
    
    $stmt->execute([':event_id' => $event_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Pagos - EventManager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/navbar.php'; ?>

    <div class="container mt-4">
        <?php if ($event_id > 0): ?>
            <h2>Gestión de Pagos - <?php echo isset($event['name']) ? htmlspecialchars($event['name']) : ''; ?></h2>
            
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Categoría</th>
                            <th>Monto</th>
                            <th>Método de Pago</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($payment['user_name']); ?><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($payment['email']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($payment['category_name']); ?></td>
                                <td>$<?php echo number_format($payment['price'], 2); ?></td>
                                <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                <td>
                                    <span class="badge <?php 
                                        echo match($payment['status']) {
                                            'completed' => 'bg-success',
                                            'pending' => 'bg-warning',
                                            'failed' => 'bg-danger',
                                            'refunded' => 'bg-info',
                                            default => 'bg-secondary'
                                        };
                                    ?>">
                                        <?php echo match($payment['status']) {
                                            'completed' => 'Completado',
                                            'pending' => 'Pendiente',
                                            'failed' => 'Fallido',
                                            'refunded' => 'Reembolsado',
                                            default => 'Desconocido'
                                        }; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($payment['payment_date']): ?>
                                        <?php echo date('d/m/Y H:i', strtotime($payment['payment_date'])); ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($payment['status'] === 'pending'): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="confirm">
                                            <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-success" 
                                                    onclick="return confirm('¿Confirmar este pago?')">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger"
                                                    onclick="return confirm('¿Rechazar este pago?')">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($payments)): ?>
                            <tr>
                                <td colspan="7" class="text-center">No hay pagos registrados para este evento.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                Por favor, seleccione un evento para gestionar sus pagos.
                <a href="../events/manage.php" class="btn btn-primary ms-3">Ver Eventos</a>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 