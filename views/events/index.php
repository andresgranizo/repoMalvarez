<?php
session_start();

require_once '../../includes/Database.php';
require_once '../../includes/utilities.php';

// Inicializar variables
$user = $_SESSION['user'] ?? null;
$isLoggedIn = isset($_SESSION['user']);
$isAdmin = $isLoggedIn && $_SESSION['user']['role'] === 'admin';
$isOrganizer = $isLoggedIn && $_SESSION['user']['role'] === 'organizer';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Obtener eventos activos y publicados
    $sql = "SELECT e.*, u.name as organizer_name, COUNT(DISTINCT r.id) as registration_count
            FROM events e
            LEFT JOIN users u ON e.organizer_id = u.id
            LEFT JOIN registrations r ON e.id = r.event_id AND r.status != 'cancelled'
            WHERE e.status = 'published'
            GROUP BY e.id, e.title, e.description, e.start_date, e.end_date, 
                     e.location, e.capacity, e.organizer_id, e.created_at, 
                     e.updated_at, u.name
            ORDER BY e.start_date ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Error al cargar los eventos: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eventos - EventManager</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .event-card {
            transition: transform 0.3s;
        }
        .event-card:hover {
            transform: translateY(-5px);
        }
        .category-badge {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        .event-date {
            color: #0d6efd;
            font-weight: bold;
        }
        .registration-status {
            position: absolute;
            bottom: 10px;
            right: 10px;
        }
    </style>
</head>
<body class="bg-light">
    <?php include '../templates/navigation.php'; ?>

    <!-- Contenido principal -->
    <div class="container py-5">
        <div class="row mb-4">
            <div class="col">
                <h1 class="display-4 mb-3">Eventos Disponibles</h1>
            </div>
            <?php if ($isOrganizer || $isAdmin): ?>
            <div class="col-auto">
                <a href="create.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Crear Evento
                </a>
            </div>
            <?php endif; ?>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($events)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No hay eventos disponibles en este momento.
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($events as $event): ?>
                    <div class="col-md-4">
                        <div class="card event-card h-100">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($event['title']); ?></h5>
                                
                                <p class="event-date mb-2">
                                    <i class="fas fa-calendar-alt me-2"></i>
                                    <?php echo Utilities::formatDate($event['start_date']); ?>
                                </p>
                                
                                <p class="card-text">
                                    <?php echo htmlspecialchars(substr($event['description'], 0, 150)) . '...'; ?>
                                </p>
                                
                                <p class="mb-2">
                                    <i class="fas fa-map-marker-alt me-2"></i>
                                    <?php echo htmlspecialchars($event['location']); ?>
                                </p>
                                
                                <p class="mb-2">
                                    <i class="fas fa-user me-2"></i>
                                    Organizador: <?php echo htmlspecialchars($event['organizer_name']); ?>
                                </p>

                                <?php if ($event['capacity']): ?>
                                    <div class="progress mb-2">
                                        <?php 
                                        $percentage = ($event['registration_count'] / $event['capacity']) * 100;
                                        $progressClass = $percentage >= 90 ? 'bg-danger' : ($percentage >= 70 ? 'bg-warning' : 'bg-success');
                                        ?>
                                        <div class="progress-bar <?php echo $progressClass; ?>" 
                                             role="progressbar" 
                                             style="width: <?php echo $percentage; ?>%">
                                            <?php echo $event['registration_count']; ?>/<?php echo $event['capacity']; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <a href="view.php?id=<?php echo $event['id']; ?>" class="btn btn-primary">
                                    Ver Detalles
                                </a>

                                <?php if ($event['capacity'] && $event['registration_count'] >= $event['capacity']): ?>
                                    <span class="badge bg-danger registration-status">Completo</span>
                                <?php else: ?>
                                    <span class="badge bg-success registration-status">Disponible</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>EventManager</h5>
                    <p>Tu plataforma para gestionar eventos de manera eficiente.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <h5>Contacto</h5>
                    <p>
                        <i class="fas fa-envelope"></i> info@eventmanager.com<br>
                        <i class="fas fa-phone"></i> (123) 456-7890
                    </p>
                </div>
            </div>
            <hr>
            <div class="text-center">
                <small>&copy; <?php echo date('Y'); ?> EventManager. Todos los derechos reservados.</small>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 