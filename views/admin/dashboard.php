<?php
require_once '../../includes/auth.php';
require_once '../../includes/config.php';
require_once '../../includes/Database.php';

// Verificar si el usuario está autenticado y es administrador
if (!isAuthenticated() || !hasRole('admin')) {
    header('Location: ../auth/login.php');
    exit;
}

$db = new Database();
$conn = $db->getConnection();
$success_message = '';
$error_message = '';

// Procesar acciones de usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $user_id = intval($_POST['user_id']);
    
    if ($_POST['action'] === 'change_role') {
        $new_role = $_POST['role'];
        $stmt = $conn->prepare("UPDATE users SET role = :role WHERE id = :user_id");
        if ($stmt->execute([':role' => $new_role, ':user_id' => $user_id])) {
            $success_message = "Rol actualizado exitosamente.";
        } else {
            $error_message = "Error al actualizar el rol.";
        }
    } elseif ($_POST['action'] === 'toggle_status') {
        $stmt = $conn->prepare("UPDATE users SET is_active = NOT is_active WHERE id = :user_id");
        if ($stmt->execute([':user_id' => $user_id])) {
            $success_message = "Estado actualizado exitosamente.";
        } else {
            $error_message = "Error al actualizar el estado.";
        }
    }
}

// Obtener estadísticas
$stats = [
    'total_users' => 0,
    'total_events' => 0,
    'total_registrations' => 0,
    'total_payments' => 0,
    'recent_users' => [],
    'recent_events' => [],
    'recent_registrations' => []
];

// Total usuarios
$stmt = $conn->query("SELECT COUNT(*) FROM users");
$stats['total_users'] = $stmt->fetchColumn();

// Total eventos
$stmt = $conn->query("SELECT COUNT(*) FROM events");
$stats['total_events'] = $stmt->fetchColumn();

// Total registros
$stmt = $conn->query("SELECT COUNT(*) FROM registrations");
$stats['total_registrations'] = $stmt->fetchColumn();

// Total pagos
$stmt = $conn->query("SELECT COUNT(*) FROM payments");
$stats['total_payments'] = $stmt->fetchColumn();

// Usuarios recientes
$stmt = $conn->query("
    SELECT * FROM users 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stats['recent_users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Eventos recientes
$stmt = $conn->query("
    SELECT e.*, u.name as organizer_name 
    FROM events e 
    JOIN users u ON e.organizer_id = u.id 
    ORDER BY e.created_at DESC 
    LIMIT 5
");
$stats['recent_events'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Registros recientes
$stmt = $conn->query("
    SELECT r.*, u.name as user_name, e.name as event_name 
    FROM registrations r 
    JOIN users u ON r.user_id = u.id 
    JOIN events e ON r.event_id = e.id 
    ORDER BY r.created_at DESC 
    LIMIT 5
");
$stats['recent_registrations'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener todos los usuarios para gestión
$stmt = $conn->query("
    SELECT u.*, 
           COUNT(DISTINCT e.id) as total_events,
           COUNT(DISTINCT r.id) as total_registrations
    FROM users u
    LEFT JOIN events e ON u.id = e.organizer_id
    LEFT JOIN registrations r ON u.id = r.user_id
    GROUP BY u.id
    ORDER BY u.created_at DESC
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - EventManager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <h2>Panel de Administración</h2>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <!-- Estadísticas -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-users"></i> Usuarios
                        </h5>
                        <h2 class="card-text"><?php echo $stats['total_users']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-calendar"></i> Eventos
                        </h5>
                        <h2 class="card-text"><?php echo $stats['total_events']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-ticket-alt"></i> Registros
                        </h5>
                        <h2 class="card-text"><?php echo $stats['total_registrations']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-money-bill"></i> Pagos
                        </h5>
                        <h2 class="card-text"><?php echo $stats['total_payments']; ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actividad Reciente -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-user-plus"></i> Usuarios Recientes
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <?php foreach ($stats['recent_users'] as $user): ?>
                                <div class="list-group-item">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($user['name']); ?></h6>
                                    <small class="text-muted">
                                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?><br>
                                        <i class="fas fa-clock"></i> <?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-calendar-plus"></i> Eventos Recientes
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <?php foreach ($stats['recent_events'] as $event): ?>
                                <div class="list-group-item">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($event['name']); ?></h6>
                                    <small class="text-muted">
                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($event['organizer_name']); ?><br>
                                        <i class="fas fa-clock"></i> <?php echo date('d/m/Y H:i', strtotime($event['created_at'])); ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-ticket-alt"></i> Registros Recientes
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <?php foreach ($stats['recent_registrations'] as $registration): ?>
                                <div class="list-group-item">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($registration['event_name']); ?></h6>
                                    <small class="text-muted">
                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($registration['user_name']); ?><br>
                                        <i class="fas fa-clock"></i> <?php echo date('d/m/Y H:i', strtotime($registration['created_at'])); ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gestión de Usuarios -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-users-cog"></i> Gestión de Usuarios
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Rol</th>
                                <th>Estado</th>
                                <th>Eventos</th>
                                <th>Registros</th>
                                <th>Creado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($user['name']); ?><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                                    </td>
                                    <td>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="change_role">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <select name="role" class="form-select form-select-sm" 
                                                    onchange="this.form.submit()"
                                                    <?php echo $user['id'] === $_SESSION['user']['id'] ? 'disabled' : ''; ?>>
                                                <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>Usuario</option>
                                                <option value="organizer" <?php echo $user['role'] === 'organizer' ? 'selected' : ''; ?>>Organizador</option>
                                                <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                                            </select>
                                        </form>
                                    </td>
                                    <td>
                                        <?php if ($user['id'] !== $_SESSION['user']['id']): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="btn btn-sm <?php echo $user['is_active'] ? 'btn-success' : 'btn-danger'; ?>">
                                                    <?php echo $user['is_active'] ? 'Activo' : 'Inactivo'; ?>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="badge bg-success">Activo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $user['total_events']; ?></td>
                                    <td><?php echo $user['total_registrations']; ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <a href="../profile/view.php?id=<?php echo $user['id']; ?>" 
                                           class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 