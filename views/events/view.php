<?php
session_start();

require_once '../../includes/Database.php';
require_once '../../includes/utilities.php';

// Verificar si se proporcionó un ID de evento
if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$eventId = $_GET['id'];
$error = null;
$event = null;
$categories = [];
$isLoggedIn = isset($_SESSION['user']);
$user = $_SESSION['user'] ?? null;

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Obtener información del evento
    $stmt = $conn->prepare("
        SELECT e.*, u.name as organizer_name,
               (SELECT COUNT(*) 
                FROM registrations r2 
                WHERE r2.event_id = e.id 
                AND r2.status != 'cancelled') as registration_count
        FROM events e
        INNER JOIN users u ON e.organizer_id = u.id
        WHERE e.id = :event_id
    ");
    $stmt->execute(['event_id' => $eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        $error = "El evento no existe.";
    } elseif ($event['status'] !== 'published' && (!$isLoggedIn || ($user['role'] !== 'admin' && $user['id'] !== $event['organizer_id']))) {
        $error = "Este evento no está disponible actualmente.";
    } else {
        // Obtener categorías disponibles con conteo de registros
        $stmt = $conn->prepare("
            SELECT pc.*, 
                   COALESCE((
                       SELECT COUNT(*) 
                       FROM registrations r 
                       WHERE r.event_id = pc.event_id 
                       AND r.pricing_category_id = pc.id 
                       AND r.status != 'cancelled'
                   ), 0) as registered_count
            FROM pricing_categories pc
            WHERE pc.event_id = :event_id
            AND pc.is_active = 1
            ORDER BY pc.price ASC
        ");
        $stmt->execute(['event_id' => $eventId]);
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Verificar si el usuario ya está registrado
        $isRegistered = false;
        if ($isLoggedIn) {
            $stmt = $conn->prepare("
                SELECT id, status 
                FROM registrations 
                WHERE event_id = :event_id 
                AND user_id = :user_id 
            ");
            $stmt->execute([
                'event_id' => $eventId,
                'user_id' => $user['id']
            ]);
            $registration = $stmt->fetch(PDO::FETCH_ASSOC);
            $isRegistered = $registration !== false;
            $registrationStatus = $registration['status'] ?? null;
        }
    }
} catch (Exception $e) {
    $error = "Error al cargar la información del evento: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $event ? htmlspecialchars($event['title']) : 'Evento'; ?> - EventManager</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .event-header {
            background: #0d6efd;
            color: white;
            padding: 60px 0;
        }
        .event-details {
            background-color: #f8f9fa;
            padding: 40px 0;
        }
        .detail-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .registration-card {
            position: sticky;
            top: 20px;
        }
        .category-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            transition: transform 0.2s;
        }
        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .category-card.selected {
            border-color: #0d6efd;
            background-color: #f8f9ff;
        }
    </style>
</head>
<body class="bg-light">
    <?php include '../templates/navigation.php'; ?>

    <?php if ($error): ?>
        <div class="container py-5">
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <a href="index.php" class="btn btn-primary">Volver a Eventos</a>
        </div>
    <?php else: ?>
        <!-- Event Header -->
        <header class="event-header">
            <div class="container text-center">
                <h1 class="display-4"><?php echo htmlspecialchars($event['title']); ?></h1>
                <p class="lead">
                    <i class="fas fa-calendar-alt me-2"></i>
                    <?php echo Utilities::formatDate($event['start_date']); ?>
                    <?php if ($event['start_date'] !== $event['end_date']): ?>
                        - <?php echo Utilities::formatDate($event['end_date']); ?>
                    <?php endif; ?>
                </p>
            </div>
        </header>

        <!-- Event Details -->
        <section class="event-details">
            <div class="container">
                <div class="row">
                    <!-- Event Information -->
                    <div class="col-lg-8">
                        <div class="detail-card">
                            <h3>Descripción</h3>
                            <p><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                        </div>

                        <div class="detail-card">
                            <h3>Detalles del Evento</h3>
                            <div class="row">
                                <div class="col-md-6">
                                    <p>
                                        <i class="fas fa-map-marker-alt me-2"></i>
                                        <strong>Ubicación:</strong> <?php echo htmlspecialchars($event['location']); ?>
                                    </p>
                                    <p>
                                        <i class="fas fa-users me-2"></i>
                                        <strong>Capacidad:</strong> 
                                        <?php echo $event['capacity'] ? "{$event['registration_count']}/{$event['capacity']}" : 'Ilimitada'; ?>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p>
                                        <i class="fas fa-user me-2"></i>
                                        <strong>Organizador:</strong> <?php echo htmlspecialchars($event['organizer_name']); ?>
                                    </p>
                                    <p>
                                        <i class="fas fa-clock me-2"></i>
                                        <strong>Estado:</strong> 
                                        <?php if ($event['capacity'] && $event['registration_count'] >= $event['capacity']): ?>
                                            <span class="badge bg-danger">Completo</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Disponible</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($categories)): ?>
                            <div class="detail-card">
                                <h3>Categorías de Participación</h3>
                                <div class="row">
                                    <?php foreach ($categories as $category): ?>
                                        <div class="col-md-6">
                                            <div class="category-card">
                                                <h5><?php echo htmlspecialchars($category['name']); ?></h5>
                                                <p class="text-muted">
                                                    <?php echo htmlspecialchars($category['description']); ?>
                                                </p>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="h4 mb-0">
                                                        $<?php echo number_format($category['price'], 2); ?>
                                                    </span>
                                                    <?php if ($category['capacity']): ?>
                                                        <span class="badge bg-info">
                                                            <?php echo $category['registered_count']; ?>/<?php echo $category['capacity']; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Registration Card -->
                    <div class="col-lg-4">
                        <div class="detail-card registration-card">
                            <h3>Inscripción</h3>
                            
                            <?php if ($isRegistered): ?>
                                <div class="alert alert-<?php 
                                    echo $registrationStatus === 'confirmed' ? 'success' : 
                                        ($registrationStatus === 'cancelled' ? 'danger' : 'warning'); 
                                ?>">
                                    <i class="fas fa-info-circle"></i> 
                                    <?php if ($registrationStatus === 'pending'): ?>
                                        Tu solicitud de inscripción está pendiente de confirmación por el organizador.
                                    <?php elseif ($registrationStatus === 'confirmed'): ?>
                                        ¡Tu inscripción ha sido confirmada! Ya estás registrado en este evento.
                                    <?php elseif ($registrationStatus === 'cancelled'): ?>
                                        Tu inscripción ha sido cancelada por el organizador.
                                    <?php endif; ?>
                                </div>
                                <?php if ($registrationStatus === 'cancelled'): ?>
                                    <a href="register.php?event_id=<?php echo $eventId; ?>" class="btn btn-primary w-100">
                                        Solicitar Nueva Inscripción
                                    </a>
                                <?php else: ?>
                                    <a href="../registrations/my-registrations.php" class="btn btn-primary w-100">
                                        Ver Mis Inscripciones
                                    </a>
                                <?php endif; ?>
                            <?php elseif ($event['capacity'] && $event['registration_count'] >= $event['capacity']): ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i> Evento Completo
                                </div>
                            <?php elseif ($isLoggedIn): ?>
                                <a href="register.php?event_id=<?php echo $event['id']; ?>" class="btn btn-success w-100 mb-3">
                                    <i class="fas fa-ticket-alt me-2"></i>Inscribirme
                                </a>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> Debes iniciar sesión para inscribirte
                                </div>
                                <a href="../auth/login.php" class="btn btn-primary w-100">
                                    <i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión
                                </a>
                            <?php endif; ?>

                            <hr>

                            <div class="d-grid gap-2">
                                <button class="btn btn-outline-primary" onclick="shareEvent()">
                                    <i class="fas fa-share-alt me-2"></i>Compartir Evento
                                </button>
                                <button class="btn btn-outline-primary" onclick="addToCalendar()">
                                    <i class="fas fa-calendar-plus me-2"></i>Agregar al Calendario
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    function shareEvent() {
        if (navigator.share) {
            navigator.share({
                title: '<?php echo htmlspecialchars($event['title']); ?>',
                text: '<?php echo htmlspecialchars(substr($event['description'], 0, 100)) . '...'; ?>',
                url: window.location.href
            });
        } else {
            alert('Compartir no está disponible en este navegador');
        }
    }

    function addToCalendar() {
        const startDate = new Date('<?php echo $event['start_date']; ?>');
        const endDate = new Date('<?php echo $event['end_date']; ?>');
        const title = '<?php echo htmlspecialchars($event['title']); ?>';
        const location = '<?php echo htmlspecialchars($event['location']); ?>';
        
        const googleCalendarUrl = `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${encodeURIComponent(title)}&dates=${startDate.toISOString().replace(/-|:|\.\d\d\d/g, '')}/${endDate.toISOString().replace(/-|:|\.\d\d\d/g, '')}&location=${encodeURIComponent(location)}`;
        
        window.open(googleCalendarUrl, '_blank');
    }
    </script>
</body>
</html> 