<?php
session_start();
require_once '../../includes/Database.php';
require_once '../../includes/utilities.php';

// Verificar si el usuario está autenticado y es organizador
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'organizer') {
    header('Location: /EventManager/views/auth/login.php');
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Obtener eventos del organizador
    $stmt = $conn->prepare("
        SELECT e.id, e.title
        FROM events e
        WHERE e.organizer_id = :organizer_id
        ORDER BY e.start_date DESC
    ");
    $stmt->execute(['organizer_id' => $_SESSION['user']['id']]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Si se seleccionó un evento, obtener sus registros
    $selectedEventId = isset($_GET['event_id']) ? $_GET['event_id'] : null;
    $registrations = [];
    
    if ($selectedEventId) {
        $stmt = $conn->prepare("
            SELECT 
                r.id as registration_id,
                r.registration_code,
                r.status as registration_status,
                r.payment_status,
                r.created_at as registration_date,
                u.name as user_name,
                u.email as user_email,
                pc.name as category_name,
                pc.price,
                p.transaction_id,
                p.payment_date,
                p.amount as paid_amount,
                p.status as payment_status
            FROM registrations r
            INNER JOIN users u ON r.user_id = u.id
            INNER JOIN participant_categories pc ON r.category_id = pc.id
            LEFT JOIN payments p ON r.id = p.registration_id
            WHERE r.event_id = :event_id
            ORDER BY r.created_at DESC
        ");
        $stmt->execute(['event_id' => $selectedEventId]);
        $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    $_SESSION['error'] = 'Error al cargar los datos: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Registros - EventManager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .receipt-link {
            color: #0d6efd;
            text-decoration: none;
        }
        .receipt-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <?php include_once '../templates/navigation.php'; ?>

    <div class="container my-4">
        <h2 class="mb-4">Gestionar Registros</h2>

        <!-- Selector de eventos -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="get" class="row g-3 align-items-center">
                    <div class="col-md-6">
                        <label for="event_id" class="form-label">Seleccionar Evento:</label>
                        <select name="event_id" id="event_id" class="form-select" onchange="this.form.submit()">
                            <option value="">Seleccione un evento...</option>
                            <?php foreach ($events as $event): ?>
                                <option value="<?php echo $event['id']; ?>" 
                                        <?php echo ($selectedEventId == $event['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($event['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($selectedEventId && !empty($registrations)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Participante</th>
                            <th>Categoría</th>
                            <th>Precio</th>
                            <th>Estado Registro</th>
                            <th>Estado Pago</th>
                            <th>Fecha Pago</th>
                            <th>Comprobante</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registrations as $reg): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($reg['registration_code']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($reg['user_name']); ?><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($reg['user_email']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($reg['category_name']); ?></td>
                                <td>$<?php echo number_format($reg['price'], 2); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $reg['registration_status'] === 'confirmed' ? 'success' : 
                                            ($reg['registration_status'] === 'pending' ? 'warning' : 'danger'); 
                                    ?>">
                                        <?php echo ucfirst($reg['registration_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $reg['payment_status'] === 'completed' ? 'success' : 
                                            ($reg['payment_status'] === 'pending' ? 'warning' : 'danger'); 
                                    ?>">
                                        <?php echo ucfirst($reg['payment_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $reg['payment_date'] ? date('d/m/Y H:i', strtotime($reg['payment_date'])) : '-'; ?>
                                </td>
                                <td>
                                    <?php if ($reg['transaction_id']): ?>
                                        <a href="/EventManager/uploads/receipts/<?php echo $reg['transaction_id']; ?>" 
                                           target="_blank" 
                                           class="receipt-link"
                                           title="Ver comprobante">
                                            <i class="fas fa-file-alt"></i> Ver
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($reg['payment_status'] === 'pending' && $reg['transaction_id']): ?>
                                        <button class="btn btn-sm btn-success" 
                                                onclick="approvePayment(<?php echo $reg['registration_id']; ?>)">
                                            <i class="fas fa-check"></i> Aprobar
                                        </button>
                                        <button class="btn btn-sm btn-danger" 
                                                onclick="rejectPayment(<?php echo $reg['registration_id']; ?>)">
                                            <i class="fas fa-times"></i> Rechazar
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($selectedEventId): ?>
            <div class="alert alert-info">
                No hay registros para este evento.
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function approvePayment(registrationId) {
            if (confirm('¿Estás seguro de que deseas aprobar este pago?')) {
                updatePaymentStatus(registrationId, 'completed');
            }
        }

        function rejectPayment(registrationId) {
            if (confirm('¿Estás seguro de que deseas rechazar este pago?')) {
                updatePaymentStatus(registrationId, 'failed');
            }
        }

        function updatePaymentStatus(registrationId, status) {
            fetch('/EventManager/api/payments/update-status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    registration_id: registrationId,
                    status: status
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Estado del pago actualizado exitosamente');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al actualizar el estado del pago');
            });
        }
    </script>
</body>
</html> 