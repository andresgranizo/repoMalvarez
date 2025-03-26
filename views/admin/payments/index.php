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
$payments = [];
$filters = [
    'status' => $_GET['status'] ?? 'pending_review',
    'event_id' => $_GET['event_id'] ?? null,
    'payment_method' => $_GET['payment_method'] ?? null
];

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Construir la consulta base
    $query = "
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
        WHERE 1=1
    ";
    $params = [];

    // Aplicar filtros
    if ($filters['status']) {
        $query .= " AND p.status = :status";
        $params['status'] = $filters['status'];
    }
    if ($filters['event_id']) {
        $query .= " AND e.id = :event_id";
        $params['event_id'] = $filters['event_id'];
    }
    if ($filters['payment_method']) {
        $query .= " AND pm.id = :payment_method";
        $params['payment_method'] = $filters['payment_method'];
    }

    $query .= " ORDER BY p.created_at DESC";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener eventos para el filtro
    $stmt = $conn->prepare("SELECT id, title FROM events WHERE status = 'published' ORDER BY title");
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener métodos de pago para el filtro
    $stmt = $conn->prepare("SELECT id, name FROM payment_methods WHERE is_active = 1");
    $stmt->execute();
    $paymentMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = "Error al cargar los pagos: " . $e->getMessage();
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
    <?php include '../../templates/navigation.php'; ?>

    <div class="container-fluid py-5">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Gestión de Pagos</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php else: ?>
                            <!-- Filtros -->
                            <form class="row g-3 mb-4">
                                <div class="col-md-3">
                                    <label class="form-label">Estado</label>
                                    <select name="status" class="form-select">
                                        <option value="">Todos</option>
                                        <option value="pending" <?php echo $filters['status'] === 'pending' ? 'selected' : ''; ?>>Pendiente</option>
                                        <option value="pending_confirmation" <?php echo $filters['status'] === 'pending_confirmation' ? 'selected' : ''; ?>>Pendiente de Confirmación</option>
                                        <option value="pending_review" <?php echo $filters['status'] === 'pending_review' ? 'selected' : ''; ?>>Pendiente de Revisión</option>
                                        <option value="completed" <?php echo $filters['status'] === 'completed' ? 'selected' : ''; ?>>Completado</option>
                                        <option value="rejected" <?php echo $filters['status'] === 'rejected' ? 'selected' : ''; ?>>Rechazado</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Evento</label>
                                    <select name="event_id" class="form-select">
                                        <option value="">Todos</option>
                                        <?php foreach ($events as $event): ?>
                                            <option value="<?php echo $event['id']; ?>" 
                                                    <?php echo $filters['event_id'] == $event['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($event['title']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Método de Pago</label>
                                    <select name="payment_method" class="form-select">
                                        <option value="">Todos</option>
                                        <?php foreach ($paymentMethods as $method): ?>
                                            <option value="<?php echo $method['id']; ?>"
                                                    <?php echo $filters['payment_method'] == $method['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($method['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" class="btn btn-primary d-block">Filtrar</button>
                                </div>
                            </form>

                            <!-- Tabla de Pagos -->
                            <div class="table-responsive">
                                <table id="paymentsTable" class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Fecha</th>
                                            <th>Evento</th>
                                            <th>Usuario</th>
                                            <th>Método</th>
                                            <th>Monto</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($payments as $payment): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($payment['transaction_id']); ?></td>
                                                <td><?php echo Utilities::formatDate($payment['created_at']); ?></td>
                                                <td><?php echo htmlspecialchars($payment['event_title']); ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars($payment['user_name']); ?><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($payment['user_email']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($payment['payment_method_name']); ?></td>
                                                <td>$<?php echo number_format($payment['amount'], 2); ?></td>
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
                                                <td>
                                                    <a href="verify.php?id=<?php echo $payment['id']; ?>" 
                                                       class="btn btn-sm btn-primary">
                                                        <i class="fas fa-eye"></i> Revisar
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
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
        $('#paymentsTable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json'
            },
            order: [[1, 'desc']]
        });
    });
    </script>
</body>
</html> 