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

    // Estadísticas generales
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

    // Eventos por categoría
    $stmt = $conn->query("
        SELECT c.name, COUNT(e.id) as total
        FROM events e
        JOIN categories c ON e.category_id = c.id
        WHERE e.organizer_id = $user_id
        GROUP BY c.id, c.name
        ORDER BY total DESC
    ");
    $events_by_category = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Registros por mes (últimos 6 meses)
    $stmt = $conn->query("
        SELECT DATE_FORMAT(r.created_at, '%Y-%m') as month,
               COUNT(*) as total,
               SUM(CASE WHEN r.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
               SUM(CASE WHEN r.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM registrations r
        JOIN events e ON r.event_id = e.id
        WHERE e.organizer_id = $user_id
        AND r.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(r.created_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $registrations_by_month = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Eventos más populares
    $stmt = $conn->query("
        SELECT e.title, 
               COUNT(r.id) as total_registrations,
               SUM(CASE WHEN r.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_registrations
        FROM events e
        LEFT JOIN registrations r ON e.id = r.event_id
        WHERE e.organizer_id = $user_id
        GROUP BY e.id, e.title
        ORDER BY total_registrations DESC
        LIMIT 5
    ");
    $popular_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                            <i class="fas fa-calendar"></i> Mis Eventos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../registrations/manage.php">
                            <i class="fas fa-users"></i> Registros
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="organizer.php">
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">
                <i class="fas fa-chart-bar"></i> Reportes y Estadísticas
            </h1>
            <div>
                <a href="export.php" class="btn btn-success me-2">
                    <i class="fas fa-file-export"></i> Exportar Datos
                </a>
                <a href="../dashboard/organizer.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Volver al Dashboard
                </a>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Resumen General -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card border-primary h-100">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 text-primary">
                            <i class="fas fa-calendar"></i> Total Eventos
                        </h6>
                        <h2 class="card-title mb-0"><?php echo $stats['total_events']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success h-100">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 text-success">
                            <i class="fas fa-check-circle"></i> Eventos Publicados
                        </h6>
                        <h2 class="card-title mb-0"><?php echo $stats['events_by_status']['published']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info h-100">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 text-info">
                            <i class="fas fa-users"></i> Total Registros
                        </h6>
                        <h2 class="card-title mb-0"><?php echo $stats['total_registrations']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning h-100">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 text-warning">
                            <i class="fas fa-clock"></i> Registros Pendientes
                        </h6>
                        <h2 class="card-title mb-0"><?php echo $stats['registrations_by_status']['pending']; ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Estado de Eventos -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Estado de Eventos</h5>
                        <canvas id="eventsStatusChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Eventos por Categoría -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Eventos por Categoría</h5>
                        <canvas id="eventsByCategoryChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Registros por Mes -->
            <div class="col-md-8 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Registros por Mes</h5>
                        <canvas id="registrationsByMonthChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Eventos Más Populares -->
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Eventos Más Populares</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Evento</th>
                                        <th>Registros</th>
                                        <th>Confirmados</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($popular_events as $event): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($event['title']); ?></td>
                                            <td><?php echo $event['total_registrations']; ?></td>
                                            <td><?php echo $event['confirmed_registrations']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
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

        // Gráfico de estado de eventos
        new Chart(document.getElementById('eventsStatusChart'), {
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
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Gráfico de eventos por categoría
        new Chart(document.getElementById('eventsByCategoryChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($events_by_category, 'name')); ?>,
                datasets: [{
                    label: 'Número de Eventos',
                    data: <?php echo json_encode(array_column($events_by_category, 'total')); ?>,
                    backgroundColor: colors.primary
                }]
            },
            options: {
                responsive: true,
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

        // Gráfico de registros por mes
        new Chart(document.getElementById('registrationsByMonthChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($registrations_by_month, 'month')); ?>,
                datasets: [{
                    label: 'Total Registros',
                    data: <?php echo json_encode(array_column($registrations_by_month, 'total')); ?>,
                    borderColor: colors.primary,
                    fill: false
                }, {
                    label: 'Confirmados',
                    data: <?php echo json_encode(array_column($registrations_by_month, 'confirmed')); ?>,
                    borderColor: colors.success,
                    fill: false
                }, {
                    label: 'Cancelados',
                    data: <?php echo json_encode(array_column($registrations_by_month, 'cancelled')); ?>,
                    borderColor: colors.danger,
                    fill: false
                }]
            },
            options: {
                responsive: true,
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