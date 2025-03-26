<?php
session_start();

require_once '../../includes/Database.php';
require_once '../../includes/utilities.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user'])) {
    header('Location: /EventManager/views/auth/login.php');
    exit;
}

$user = $_SESSION['user'];
$error = null;
$registrations = [];

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Obtener las inscripciones del usuario con detalles del evento
    $sql = "SELECT r.*, e.title as event_title, e.start_date, e.location, 
                   e.capacity, u.name as organizer_name, pc.name as category_name,
                   (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND status != 'cancelled') as total_registrations
            FROM registrations r
            INNER JOIN events e ON r.event_id = e.id
            INNER JOIN users u ON e.organizer_id = u.id
            LEFT JOIN pricing_categories pc ON r.pricing_category_id = pc.id
            WHERE r.user_id = :user_id
            ORDER BY e.start_date ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute(['user_id' => $user['id']]);
    $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = "Error al cargar las inscripciones: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Inscripciones - EventManager</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .registration-card {
            transition: transform 0.3s;
        }
        .registration-card:hover {
            transform: translateY(-5px);
        }
        .status-badge {
            position: absolute;
            top: 10px;
            right: 10px;
        }
    </style>
</head>
<body class="bg-light">
    <?php include '../templates/navigation.php'; ?>

    <div class="container py-5">
        <h1 class="display-4 mb-4">Mis Inscripciones</h1>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($registrations)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No tienes inscripciones a eventos en este momento.
                <br>
                <a href="/EventManager/views/events/index.php" class="btn btn-primary mt-3">
                    Ver Eventos Disponibles
                </a>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($registrations as $registration): ?>
                    <div class="col-md-6">
                        <div class="card registration-card h-100">
                            <?php
                            $statusClass = match($registration['status']) {
                                'confirmed' => 'bg-success',
                                'pending' => 'bg-warning',
                                'cancelled' => 'bg-danger',
                                default => 'bg-secondary'
                            };
                            $statusText = match($registration['status']) {
                                'confirmed' => 'Confirmado',
                                'pending' => 'Pendiente',
                                'cancelled' => 'Cancelado',
                                default => 'Desconocido'
                            };
                            ?>
                            <span class="badge <?php echo $statusClass; ?> status-badge">
                                <?php echo $statusText; ?>
                            </span>

                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($registration['event_title']); ?></h5>
                                
                                <?php if ($registration['category_name']): ?>
                                    <span class="badge bg-info mb-2">
                                        <?php echo htmlspecialchars($registration['category_name']); ?>
                                    </span>
                                <?php endif; ?>

                                <?php if ($registration['qr_code'] && $registration['status'] === 'confirmed'): ?>
                                    <div class="text-center mb-3">
                                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode($registration['qr_code']); ?>" 
                                             alt="QR Code" 
                                             class="img-fluid">
                                    </div>
                                <?php endif; ?>

                                <p class="text-primary mb-2">
                                    <i class="fas fa-calendar-alt me-2"></i>
                                    <?php echo Utilities::formatDate($registration['start_date']); ?>
                                </p>
                                
                                <p class="mb-2">
                                    <i class="fas fa-map-marker-alt me-2"></i>
                                    <?php echo htmlspecialchars($registration['location']); ?>
                                </p>
                                
                                <p class="mb-2">
                                    <i class="fas fa-user me-2"></i>
                                    Organizador: <?php echo htmlspecialchars($registration['organizer_name']); ?>
                                </p>

                                <?php if ($registration['capacity']): ?>
                                    <div class="progress mb-3">
                                        <?php 
                                        $percentage = ($registration['total_registrations'] / $registration['capacity']) * 100;
                                        $progressClass = $percentage >= 90 ? 'bg-danger' : ($percentage >= 70 ? 'bg-warning' : 'bg-success');
                                        ?>
                                        <div class="progress-bar <?php echo $progressClass; ?>" 
                                             role="progressbar" 
                                             style="width: <?php echo $percentage; ?>%">
                                            <?php echo $registration['total_registrations']; ?>/<?php echo $registration['capacity']; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <a href="/EventManager/views/events/view.php?id=<?php echo $registration['event_id']; ?>" 
                                       class="btn btn-primary">
                                        Ver Detalles del Evento
                                    </a>

                                    <?php if ($registration['status'] !== 'cancelled'): ?>
                                        <button class="btn btn-danger" 
                                                onclick="cancelRegistration(<?php echo $registration['id']; ?>)">
                                            Cancelar Inscripción
                                        </button>
                                    <?php endif; ?>
                                </div>
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
    
    <!-- Script para cancelar inscripción -->
    <script>
    function cancelRegistration(registrationId) {
        if (confirm('¿Estás seguro de que deseas cancelar esta inscripción?')) {
            fetch('/EventManager/api/registrations/cancel.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    registration_id: registrationId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Inscripción cancelada exitosamente');
                    location.reload();
                } else {
                    alert('Error al cancelar la inscripción: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al procesar la solicitud');
            });
        }
    }
    </script>
</body>
</html> 