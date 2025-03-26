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
$stats = [];

try {
    $db = new Database();
    $conn = $db->getConnection();
    $user_id = $_SESSION['user']['id'];

    // Obtener estadísticas de eventos del organizador
    $stats = [
        'total_events' => $conn->query("SELECT COUNT(*) FROM events WHERE organizer_id = $user_id")->fetchColumn(),
        'events_by_status' => [
            'published' => $conn->query("SELECT COUNT(*) FROM events WHERE organizer_id = $user_id AND status = 'published'")->fetchColumn(),
            'draft' => $conn->query("SELECT COUNT(*) FROM events WHERE organizer_id = $user_id AND status = 'draft'")->fetchColumn(),
            'cancelled' => $conn->query("SELECT COUNT(*) FROM events WHERE organizer_id = $user_id AND status = 'cancelled'")->fetchColumn()
        ],
        'total_registrations' => $conn->query("
            SELECT COUNT(*) FROM registrations r 
            JOIN events e ON r.event_id = e.id 
            WHERE e.organizer_id = $user_id
        ")->fetchColumn(),
        'registrations_by_status' => [
            'pending' => $conn->query("
                SELECT COUNT(*) FROM registrations r 
                JOIN events e ON r.event_id = e.id 
                WHERE e.organizer_id = $user_id AND r.status = 'pending'
            ")->fetchColumn(),
            'confirmed' => $conn->query("
                SELECT COUNT(*) FROM registrations r 
                JOIN events e ON r.event_id = e.id 
                WHERE e.organizer_id = $user_id AND r.status = 'confirmed'
            ")->fetchColumn(),
            'cancelled' => $conn->query("
                SELECT COUNT(*) FROM registrations r 
                JOIN events e ON r.event_id = e.id 
                WHERE e.organizer_id = $user_id AND r.status = 'cancelled'
            ")->fetchColumn()
        ]
    ];

    // Obtener eventos recientes
    $stmt = $conn->query("
        SELECT e.*, c.name as category_name,
               (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id) as registration_count
        FROM events e
        LEFT JOIN categories c ON e.category_id = c.id
        WHERE e.organizer_id = $user_id
        ORDER BY e.created_at DESC
        LIMIT 5
    ");
    $recent_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener registros recientes
    $stmt = $conn->query("
        SELECT r.*, e.title as event_title, u.name as participant_name
        FROM registrations r
        JOIN events e ON r.event_id = e.id
        JOIN users u ON r.user_id = u.id
        WHERE e.organizer_id = $user_id
        ORDER BY r.created_at DESC
        LIMIT 5
    ");
    $recent_registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Organizador - EventManager</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .stat-card {
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .action-card {
            cursor: pointer;
            transition: all 0.3s;
        }
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
    </style>
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
                        <a class="nav-link" href="../events/manage.php">
                            <i class="fas fa-calendar"></i> Mis Eventos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../registrations/manage.php">
                            <i class="fas fa-users"></i> Registros
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../reports/organizer.php">
                            <i class="fas fa-chart-bar"></i> Reportes
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
        <h1 class="h3 mb-4">
            <i class="fas fa-tachometer-alt"></i> Panel de Control
        </h1>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Estadísticas Rápidas -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card stat-card h-100 border-primary">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 text-primary">
                            <i class="fas fa-calendar"></i> Total Eventos
                        </h6>
                        <h2 class="card-title mb-0"><?php echo $stats['total_events']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card h-100 border-success">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 text-success">
                            <i class="fas fa-check-circle"></i> Eventos Publicados
                        </h6>
                        <h2 class="card-title mb-0"><?php echo $stats['events_by_status']['published']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card h-100 border-info">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 text-info">
                            <i class="fas fa-users"></i> Total Registros
                        </h6>
                        <h2 class="card-title mb-0"><?php echo $stats['total_registrations']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card h-100 border-warning">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 text-warning">
                            <i class="fas fa-clock"></i> Registros Pendientes
                        </h6>
                        <h2 class="card-title mb-0"><?php echo $stats['registrations_by_status']['pending']; ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Acciones Rápidas -->
        <div class="row g-4 mb-5">
            <div class="col-md-3">
                <a href="../events/form.php" class="text-decoration-none">
                    <div class="card action-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-plus-circle fa-2x text-primary mb-2"></i>
                            <h5>Crear Evento</h5>
                            <p class="text-muted mb-0">Crear un nuevo evento</p>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-3">
                <a href="../registrations/manage.php" class="text-decoration-none">
                    <div class="card action-card">
                        <div class="card-body text-center">
                            <i class="fas fa-users fa-2x text-info mb-2"></i>
                            <h5>Gestionar Registros</h5>
                            <p class="text-muted mb-0">Administra los registros de tus eventos</p>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-3">
                <a href="../reports/export.php" class="text-decoration-none">
                    <div class="card action-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-file-export fa-2x text-success mb-2"></i>
                            <h5>Exportar Datos</h5>
                            <p class="text-muted mb-0">Descargar reportes</p>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-3">
                <a href="../notifications/send.php" class="text-decoration-none">
                    <div class="card action-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-bell fa-2x text-info mb-2"></i>
                            <h5>Enviar Notificaciones</h5>
                            <p class="text-muted mb-0">Comunicarse con participantes</p>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <div class="row">
            <!-- Eventos Recientes -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Eventos Recientes</h5>
                        <a href="../events/manage.php" class="btn btn-sm btn-primary">
                            Ver Todos
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_events)): ?>
                            <p class="text-muted text-center mb-0">
                                No hay eventos recientes.
                            </p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Título</th>
                                            <th>Categoría</th>
                                            <th>Estado</th>
                                            <th>Registros</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_events as $event): ?>
                                            <tr>
                                                <td>
                                                    <a href="../events/view.php?id=<?php echo $event['id']; ?>" class="text-decoration-none">
                                                        <?php echo htmlspecialchars($event['title']); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo htmlspecialchars($event['category_name']); ?></td>
                                                <td>
                                                    <?php
                                                    $status_class = match($event['status']) {
                                                        'published' => 'success',
                                                        'draft' => 'warning',
                                                        'cancelled' => 'danger',
                                                        default => 'secondary'
                                                    };
                                                    ?>
                                                    <span class="badge bg-<?php echo $status_class; ?>">
                                                        <?php echo ucfirst($event['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $event['registration_count']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Registros Recientes -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Registros Recientes</h5>
                        <a href="../registrations/manage.php" class="btn btn-sm btn-primary">
                            Ver Todos
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_registrations)): ?>
                            <p class="text-muted text-center mb-0">
                                No hay registros recientes.
                            </p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Participante</th>
                                            <th>Evento</th>
                                            <th>Estado</th>
                                            <th>Fecha</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_registrations as $registration): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($registration['participant_name']); ?></td>
                                                <td>
                                                    <a href="../events/view.php?id=<?php echo $registration['event_id']; ?>" class="text-decoration-none">
                                                        <?php echo htmlspecialchars($registration['event_title']); ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <?php
                                                    $status_class = match($registration['status']) {
                                                        'confirmed' => 'success',
                                                        'pending' => 'warning',
                                                        'cancelled' => 'danger',
                                                        default => 'secondary'
                                                    };
                                                    ?>
                                                    <span class="badge bg-<?php echo $status_class; ?>">
                                                        <?php echo ucfirst($registration['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('d/m/Y', strtotime($registration['created_at'])); ?></td>
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
</body>
</html> 