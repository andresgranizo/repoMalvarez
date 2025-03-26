<?php
session_start();

// Verificar si el usuario está autenticado y es administrador
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
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

    // Obtener estadísticas generales
    $stats = [
        'total_users' => $conn->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'total_events' => $conn->query("SELECT COUNT(*) FROM events")->fetchColumn(),
        'total_registrations' => $conn->query("SELECT COUNT(*) FROM registrations")->fetchColumn(),
        'events_by_status' => [
            'published' => $conn->query("SELECT COUNT(*) FROM events WHERE status = 'published'")->fetchColumn(),
            'draft' => $conn->query("SELECT COUNT(*) FROM events WHERE status = 'draft'")->fetchColumn(),
            'cancelled' => $conn->query("SELECT COUNT(*) FROM events WHERE status = 'cancelled'")->fetchColumn()
        ],
        'registrations_by_status' => [
            'pending' => $conn->query("SELECT COUNT(*) FROM registrations WHERE status = 'pending'")->fetchColumn(),
            'confirmed' => $conn->query("SELECT COUNT(*) FROM registrations WHERE status = 'confirmed'")->fetchColumn(),
            'cancelled' => $conn->query("SELECT COUNT(*) FROM registrations WHERE status = 'cancelled'")->fetchColumn()
        ]
    ];

    // Obtener eventos por categoría
    $stmt = $conn->query("
        SELECT c.name, COUNT(e.id) as total
        FROM categories c
        LEFT JOIN events e ON c.id = e.category_id
        GROUP BY c.id, c.name
        ORDER BY total DESC
    ");
    $stats['events_by_category'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener usuarios por rol
    $stmt = $conn->query("
        SELECT role, COUNT(*) as total
        FROM users
        GROUP BY role
    ");
    $stats['users_by_role'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - EventManager</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                            <i class="fas fa-calendar"></i> Eventos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../users/manage.php">
                            <i class="fas fa-users"></i> Usuarios
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../categories/manage.php">
                            <i class="fas fa-tags"></i> Categorías
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2">
                <i class="fas fa-chart-bar"></i> Reportes y Estadísticas
            </h1>
            <a href="generate.php" class="btn btn-primary">
                <i class="fas fa-file-export"></i> Generar Reporte
            </a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Resumen General -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Usuarios</h5>
                        <canvas id="userRolesChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Estado de Eventos</h5>
                        <canvas id="eventStatusChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Estado de Registros</h5>
                        <canvas id="registrationStatusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Eventos por Categoría -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Eventos por Categoría</h5>
                <canvas id="eventsByCategoryChart"></canvas>
            </div>
        </div>

        <!-- Estadísticas Detalladas -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Resumen de Usuarios</h5>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <tbody>
                                <?php foreach ($stats['users_by_role'] as $role): ?>
                                    <tr>
                                        <td><?php echo ucfirst($role['role']); ?></td>
                                        <td><?php echo $role['total']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="table-primary">
                                    <th>Total</th>
                                    <th><?php echo $stats['total_users']; ?></th>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Resumen de Eventos</h5>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <tbody>
                                <tr>
                                    <td>Publicados</td>
                                    <td><?php echo $stats['events_by_status']['published']; ?></td>
                                </tr>
                                <tr>
                                    <td>Borradores</td>
                                    <td><?php echo $stats['events_by_status']['draft']; ?></td>
                                </tr>
                                <tr>
                                    <td>Cancelados</td>
                                    <td><?php echo $stats['events_by_status']['cancelled']; ?></td>
                                </tr>
                                <tr class="table-primary">
                                    <th>Total</th>
                                    <th><?php echo $stats['total_events']; ?></th>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Configuración de colores
        const colors = {
            primary: '#0d6efd',
            success: '#198754',
            danger: '#dc3545',
            warning: '#ffc107',
            info: '#0dcaf0'
        };

        // Gráfico de roles de usuario
        new Chart(document.getElementById('userRolesChart'), {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_column($stats['users_by_role'], 'role')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($stats['users_by_role'], 'total')); ?>,
                    backgroundColor: Object.values(colors)
                }]
            }
        });

        // Gráfico de estado de eventos
        new Chart(document.getElementById('eventStatusChart'), {
            type: 'pie',
            data: {
                labels: ['Publicados', 'Borradores', 'Cancelados'],
                datasets: [{
                    data: [
                        <?php echo $stats['events_by_status']['published']; ?>,
                        <?php echo $stats['events_by_status']['draft']; ?>,
                        <?php echo $stats['events_by_status']['cancelled']; ?>
                    ],
                    backgroundColor: [colors.success, colors.warning, colors.danger]
                }]
            }
        });

        // Gráfico de estado de registros
        new Chart(document.getElementById('registrationStatusChart'), {
            type: 'pie',
            data: {
                labels: ['Pendientes', 'Confirmados', 'Cancelados'],
                datasets: [{
                    data: [
                        <?php echo $stats['registrations_by_status']['pending']; ?>,
                        <?php echo $stats['registrations_by_status']['confirmed']; ?>,
                        <?php echo $stats['registrations_by_status']['cancelled']; ?>
                    ],
                    backgroundColor: [colors.warning, colors.success, colors.danger]
                }]
            }
        });

        // Gráfico de eventos por categoría
        new Chart(document.getElementById('eventsByCategoryChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($stats['events_by_category'], 'name')); ?>,
                datasets: [{
                    label: 'Número de Eventos',
                    data: <?php echo json_encode(array_column($stats['events_by_category'], 'total')); ?>,
                    backgroundColor: colors.primary
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    </script>
</body>
</html> 