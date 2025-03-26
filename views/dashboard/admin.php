<?php
session_start();

// Verificar si el usuario está autenticado y es administrador
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

require_once '../../includes/Database.php';
require_once '../../includes/utilities.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Obtener estadísticas generales
    $stats = [
        'total_users' => $conn->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'total_events' => $conn->query("SELECT COUNT(*) FROM events")->fetchColumn(),
        'total_registrations' => $conn->query("SELECT COUNT(*) FROM registrations")->fetchColumn(),
        'active_events' => $conn->query("SELECT COUNT(*) FROM events WHERE status = 'published'")->fetchColumn()
    ];

    // Obtener últimos usuarios registrados
    $stmt = $conn->query("
        SELECT id, name, email, role, created_at 
        FROM users 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $recent_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener últimos eventos creados
    $stmt = $conn->query("
        SELECT e.*, u.name as organizer_name 
        FROM events e 
        JOIN users u ON e.organizer_id = u.id 
        ORDER BY e.created_at DESC 
        LIMIT 5
    ");
    $recent_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - EventManager</title>
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
            border: none;
            border-radius: 10px;
            transition: all 0.3s;
        }
        .action-card:hover {
            background-color: #f8f9fa;
            transform: translateY(-5px);
        }
        .sidebar {
            min-height: calc(100vh - 56px);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
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
                            <i class="fas fa-list"></i> Ver Eventos
                        </a>
                    </li>
                </ul>
                <div class="navbar-nav">
                    <span class="nav-link">
                        <i class="fas fa-user-shield"></i> 
                        <?php echo htmlspecialchars($_SESSION['user']['name']); ?>
                    </span>
                    <a class="nav-link" href="../auth/logout.php">
                        <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="admin.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../users/manage.php">
                                <i class="fas fa-users"></i> Gestión de Usuarios
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../events/manage.php">
                                <i class="fas fa-calendar-week"></i> Gestión de Eventos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../categories/manage.php">
                                <i class="fas fa-tags"></i> Categorías
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../reports/index.php">
                                <i class="fas fa-chart-bar"></i> Reportes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../settings/index.php">
                                <i class="fas fa-cog"></i> Configuración
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Contenido principal -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <h1 class="h2 mb-4">Panel de Administración</h1>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <!-- Estadísticas -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Usuarios Totales</h5>
                                <h2><?php echo $stats['total_users']; ?></h2>
                                <a href="../users/index.php" class="text-white text-decoration-none">
                                    Ver detalles <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Eventos Activos</h5>
                                <h2><?php echo $stats['active_events']; ?></h2>
                                <a href="../events/manage.php" class="text-white text-decoration-none">
                                    Ver detalles <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-info text-white">
                            <div class="card-body">
                                <h5 class="card-title">Eventos Totales</h5>
                                <h2><?php echo $stats['total_events']; ?></h2>
                                <a href="../events/manage.php" class="text-white text-decoration-none">
                                    Ver detalles <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-warning text-white">
                            <div class="card-body">
                                <h5 class="card-title">Registros Totales</h5>
                                <h2><?php echo $stats['total_registrations']; ?></h2>
                                <a href="../registrations/index.php" class="text-white text-decoration-none">
                                    Ver detalles <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Acciones Rápidas -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h3>Acciones Rápidas</h3>
                        <div class="row g-4">
                            <div class="col-md-3">
                                <a href="../users/form.php" class="text-decoration-none">
                                    <div class="card action-card">
                                        <div class="card-body text-center">
                                            <i class="fas fa-user-plus fa-2x text-primary mb-2"></i>
                                            <h5>Crear Usuario</h5>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="../events/form.php" class="text-decoration-none">
                                    <div class="card action-card">
                                        <div class="card-body text-center">
                                            <i class="fas fa-calendar-plus fa-2x text-success mb-2"></i>
                                            <h5>Crear Evento</h5>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="../categories/manage.php" class="text-decoration-none">
                                    <div class="card h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-tags fa-3x text-primary mb-3"></i>
                                            <h5 class="card-title">Gestionar Categorías</h5>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="../reports/generate.php" class="text-decoration-none">
                                    <div class="card action-card">
                                        <div class="card-body text-center">
                                            <i class="fas fa-file-alt fa-2x text-warning mb-2"></i>
                                            <h5>Generar Reporte</h5>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Últimos Registros -->
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Últimos Usuarios Registrados</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Nombre</th>
                                                <th>Email</th>
                                                <th>Rol</th>
                                                <th>Fecha</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_users as $user): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'organizer' ? 'success' : 'primary'); ?>">
                                                            <?php echo ucfirst($user['role']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo Utilities::formatDate($user['created_at']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Últimos Eventos Creados</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Título</th>
                                                <th>Organizador</th>
                                                <th>Estado</th>
                                                <th>Fecha</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_events as $event): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($event['title']); ?></td>
                                                    <td><?php echo htmlspecialchars($event['organizer_name']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo $event['status'] === 'published' ? 'success' : 
                                                                ($event['status'] === 'draft' ? 'warning' : 'danger'); 
                                                        ?>">
                                                            <?php echo ucfirst($event['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo Utilities::formatDate($event['created_at']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 