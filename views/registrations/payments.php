<?php
session_start();

// Verificar si el usuario está autenticado y es organizador
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'organizer') {
    header('Location: ../auth/login.php');
    exit;
}

require_once '../../includes/Database.php';
require_once '../../includes/utilities.php';

$error = null;
$success = null;
$payments = [];
$event_id = $_GET['event_id'] ?? null;

try {
    $db = new Database();
    $conn = $db->getConnection();
    $user_id = $_SESSION['user']['id'];

    // Obtener eventos del organizador
    $stmt = $conn->prepare("
        SELECT id, title 
        FROM events 
        WHERE organizer_id = ?
        ORDER BY start_date DESC
    ");
    $stmt->execute([$user_id]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($event_id) {
        // Verificar que el evento pertenece al organizador
        $stmt = $conn->prepare("
            SELECT id 
            FROM events 
            WHERE id = ? AND organizer_id = ?
        ");
        $stmt->execute([$event_id, $user_id]);
        if (!$stmt->fetch()) {
            throw new Exception('No tienes permiso para ver los pagos de este evento');
        }

        // Obtener pagos del evento
        $stmt = $conn->prepare("
            SELECT 
                p.id, p.amount, p.status, p.payment_method_id, p.transaction_id,
                p.payment_data, p.created_at, p.updated_at,
                r.id as registration_id, r.status as registration_status,
                u.name as user_name, u.email as user_email,
                e.title as event_title,
                pc.name as category_name,
                pm.name as payment_method_name, pm.type as payment_method_type
            FROM payments p
            JOIN registrations r ON p.registration_id = r.id
            JOIN users u ON r.user_id = u.id
            JOIN events e ON r.event_id = e.id
            JOIN pricing_categories pc ON r.pricing_category_id = pc.id
            JOIN payment_methods pm ON p.payment_method_id = pm.id
            WHERE r.event_id = ?
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$event_id]);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Procesar acciones
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            $payment_id = $_POST['payment_id'] ?? null;

            if ($action && $payment_id) {
                // Verificar que el pago pertenece al evento
                $stmt = $conn->prepare("
                    SELECT p.id, p.registration_id
                    FROM payments p
                    JOIN registrations r ON p.registration_id = r.id
                    WHERE p.id = ? AND r.event_id = ?
                ");
                $stmt->execute([$payment_id, $event_id]);
                $payment = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$payment) {
                    throw new Exception('Pago no encontrado');
                }

                switch ($action) {
                    case 'approve':
                        // Aprobar pago
                        $stmt = $conn->prepare("
                            UPDATE payments 
                            SET status = 'completed',
                                updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([$payment_id]);

                        // Confirmar registro
                        $stmt = $conn->prepare("
                            UPDATE registrations 
                            SET status = 'confirmed',
                                updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([$payment['registration_id']]);

                        $success = "Pago aprobado exitosamente";
                        break;

                    case 'reject':
                        // Rechazar pago
                        $stmt = $conn->prepare("
                            UPDATE payments 
                            SET status = 'failed',
                                updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([$payment_id]);

                        // Cancelar registro
                        $stmt = $conn->prepare("
                            UPDATE registrations 
                            SET status = 'cancelled',
                                updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([$payment['registration_id']]);

                        $success = "Pago rechazado exitosamente";
                        break;

                    default:
                        throw new Exception('Acción no válida');
                }

                // Recargar pagos
                $stmt = $conn->prepare("
                    SELECT 
                        p.id, p.amount, p.status, p.payment_method_id, p.transaction_id,
                        p.payment_data, p.created_at, p.updated_at,
                        r.id as registration_id, r.status as registration_status,
                        u.name as user_name, u.email as user_email,
                        e.title as event_title,
                        pc.name as category_name,
                        pm.name as payment_method_name, pm.type as payment_method_type
                    FROM payments p
                    JOIN registrations r ON p.registration_id = r.id
                    JOIN users u ON r.user_id = u.id
                    JOIN events e ON r.event_id = e.id
                    JOIN pricing_categories pc ON r.pricing_category_id = pc.id
                    JOIN payment_methods pm ON p.payment_method_id = pm.id
                    WHERE r.event_id = ?
                    ORDER BY p.created_at DESC
                ");
                $stmt->execute([$event_id]);
                $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
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
    <title>Gestión de Pagos - EventManager</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../../index.php">
                <i class="fas fa-calendar-alt"></i> EventManager
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../events/index.php">
                            <i class="fas fa-calendar"></i> Eventos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../events/manage.php">
                            <i class="fas fa-cog"></i> Mis Eventos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="payments.php">
                            <i class="fas fa-money-bill"></i> Pagos
                        </a>
                    </li>
                </ul>
                <div class="navbar-nav">
                    <span class="nav-link">
                        <i class="fas fa-user"></i> 
                        <?php echo htmlspecialchars($_SESSION['user']['name']); ?>
                    </span>
                    <a class="nav-link" href="../auth/logout.php">
                        <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0">
                        <i class="fas fa-money-bill"></i> Gestión de Pagos
                    </h1>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <!-- Selector de Evento -->
                <div class="mb-4">
                    <form method="GET" class="row g-3 align-items-center">
                        <div class="col-md-6">
                            <label for="event_id" class="form-label">Seleccionar Evento</label>
                            <select class="form-select" id="event_id" name="event_id" onchange="this.form.submit()">
                                <option value="">Selecciona un evento...</option>
                                <?php foreach ($events as $event): ?>
                                    <option value="<?php echo $event['id']; ?>" 
                                            <?php echo $event_id == $event['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($event['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>

                <?php if ($event_id && !empty($payments)): ?>
                    <!-- Tabla de Pagos -->
                    <div class="table-responsive">
                        <table class="table table-striped" id="paymentsTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Usuario</th>
                                    <th>Categoría</th>
                                    <th>Monto</th>
                                    <th>Método</th>
                                    <th>Estado</th>
                                    <th>Fecha</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td><?php echo $payment['id']; ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($payment['user_name']); ?><br>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($payment['user_email']); ?>
                                            </small>
                                        </td>
                                        <td><?php echo htmlspecialchars($payment['category_name']); ?></td>
                                        <td>$<?php echo number_format($payment['amount'], 2); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($payment['payment_method_name']); ?>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo ucfirst($payment['payment_method_type']); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo match($payment['status']) {
                                                    'completed' => 'success',
                                                    'pending' => 'warning',
                                                    'failed' => 'danger',
                                                    default => 'secondary'
                                                };
                                            ?>">
                                                <?php echo ucfirst($payment['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo date('d/m/Y H:i', strtotime($payment['created_at'])); ?>
                                        </td>
                                        <td>
                                            <?php if ($payment['status'] === 'pending'): ?>
                                                <div class="btn-group">
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                                        <input type="hidden" name="action" value="approve">
                                                        <button type="submit" class="btn btn-success btn-sm" 
                                                                onclick="return confirm('¿Estás seguro de aprobar este pago?')">
                                                            <i class="fas fa-check"></i> Aprobar
                                                        </button>
                                                    </form>
                                                    <form method="POST" class="d-inline ms-1">
                                                        <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                                        <input type="hidden" name="action" value="reject">
                                                        <button type="submit" class="btn btn-danger btn-sm"
                                                                onclick="return confirm('¿Estás seguro de rechazar este pago?')">
                                                            <i class="fas fa-times"></i> Rechazar
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Botón para ver detalles -->
                                            <button type="button" class="btn btn-info btn-sm ms-1" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#paymentModal<?php echo $payment['id']; ?>">
                                                <i class="fas fa-eye"></i> Ver
                                            </button>

                                            <!-- Modal de Detalles -->
                                            <div class="modal fade" id="paymentModal<?php echo $payment['id']; ?>" 
                                                 tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Detalles del Pago</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <dl class="row mb-0">
                                                                <dt class="col-sm-4">ID Pago:</dt>
                                                                <dd class="col-sm-8"><?php echo $payment['id']; ?></dd>

                                                                <dt class="col-sm-4">ID Registro:</dt>
                                                                <dd class="col-sm-8"><?php echo $payment['registration_id']; ?></dd>

                                                                <dt class="col-sm-4">Usuario:</dt>
                                                                <dd class="col-sm-8">
                                                                    <?php echo htmlspecialchars($payment['user_name']); ?><br>
                                                                    <small class="text-muted">
                                                                        <?php echo htmlspecialchars($payment['user_email']); ?>
                                                                    </small>
                                                                </dd>

                                                                <dt class="col-sm-4">Evento:</dt>
                                                                <dd class="col-sm-8">
                                                                    <?php echo htmlspecialchars($payment['event_title']); ?>
                                                                </dd>

                                                                <dt class="col-sm-4">Categoría:</dt>
                                                                <dd class="col-sm-8">
                                                                    <?php echo htmlspecialchars($payment['category_name']); ?>
                                                                </dd>

                                                                <dt class="col-sm-4">Monto:</dt>
                                                                <dd class="col-sm-8">
                                                                    $<?php echo number_format($payment['amount'], 2); ?>
                                                                </dd>

                                                                <dt class="col-sm-4">Método:</dt>
                                                                <dd class="col-sm-8">
                                                                    <?php echo htmlspecialchars($payment['payment_method_name']); ?>
                                                                    (<?php echo ucfirst($payment['payment_method_type']); ?>)
                                                                </dd>

                                                                <?php if ($payment['transaction_id']): ?>
                                                                    <dt class="col-sm-4">Transacción:</dt>
                                                                    <dd class="col-sm-8">
                                                                        <?php echo htmlspecialchars($payment['transaction_id']); ?>
                                                                    </dd>
                                                                <?php endif; ?>

                                                                <?php if ($payment['payment_data']): ?>
                                                                    <dt class="col-sm-4">Datos:</dt>
                                                                    <dd class="col-sm-8">
                                                                        <?php 
                                                                        $payment_data = json_decode($payment['payment_data'], true);
                                                                        foreach ($payment_data as $key => $value):
                                                                            if ($key !== 'provider_response'):
                                                                        ?>
                                                                            <strong><?php echo ucfirst(str_replace('_', ' ', $key)); ?>:</strong>
                                                                            <?php echo is_array($value) ? json_encode($value) : $value; ?><br>
                                                                        <?php 
                                                                            endif;
                                                                        endforeach;
                                                                        ?>
                                                                    </dd>
                                                                <?php endif; ?>

                                                                <dt class="col-sm-4">Estado:</dt>
                                                                <dd class="col-sm-8">
                                                                    <span class="badge bg-<?php 
                                                                        echo match($payment['status']) {
                                                                            'completed' => 'success',
                                                                            'pending' => 'warning',
                                                                            'failed' => 'danger',
                                                                            default => 'secondary'
                                                                        };
                                                                    ?>">
                                                                        <?php echo ucfirst($payment['status']); ?>
                                                                    </span>
                                                                </dd>

                                                                <dt class="col-sm-4">Creado:</dt>
                                                                <dd class="col-sm-8">
                                                                    <?php echo date('d/m/Y H:i', strtotime($payment['created_at'])); ?>
                                                                </dd>

                                                                <dt class="col-sm-4">Actualizado:</dt>
                                                                <dd class="col-sm-8">
                                                                    <?php echo date('d/m/Y H:i', strtotime($payment['updated_at'])); ?>
                                                                </dd>
                                                            </dl>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php elseif ($event_id): ?>
                    <div class="alert alert-info">
                        No hay pagos registrados para este evento.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Inicializar DataTable
            $('#paymentsTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json'
                },
                order: [[6, 'desc']], // Ordenar por fecha de creación descendente
                pageLength: 25
            });
        });
    </script>
</body>
</html> 