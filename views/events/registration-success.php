<?php
session_start();
require_once '../../includes/Database.php';
require_once '../../includes/utilities.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user'])) {
    header('Location: /EventManager/views/auth/login.php');
    exit;
}

// Verificar si se proporcionó un ID de evento
if (!isset($_GET['event_id'])) {
    header('Location: index.php');
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Obtener los detalles del evento y la inscripción
    $stmt = $conn->prepare("
        SELECT e.title as event_title, 
               e.start_date,
               e.location,
               r.registration_code,
               r.status as registration_status,
               pc.name as category_name,
               pc.price,
               p.status as payment_status
        FROM registrations r
        INNER JOIN events e ON r.event_id = e.id
        INNER JOIN pricing_categories pc ON r.pricing_category_id = pc.id
        LEFT JOIN payments p ON r.id = p.registration_id
        WHERE e.id = :event_id 
        AND r.user_id = :user_id
        ORDER BY r.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([
        'event_id' => $_GET['event_id'],
        'user_id' => $_SESSION['user']['id']
    ]);
    
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$registration) {
        throw new Exception('Registro no encontrado');
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
    <title>Registro Exitoso - EventManager</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <?php include '../templates/navigation.php'; ?>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php else: ?>
                    <div class="card border-success">
                        <div class="card-header bg-success text-white">
                            <h4 class="mb-0">¡Registro Completado con Éxito!</h4>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-4">
                                <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                            </div>

                            <h5 class="card-title">Detalles de tu Registro</h5>
                            <table class="table table-bordered">
                                <tr>
                                    <th>Evento:</th>
                                    <td><?php echo htmlspecialchars($registration['event_title']); ?></td>
                                </tr>
                                <tr>
                                    <th>Fecha:</th>
                                    <td><?php echo Utilities::formatDate($registration['start_date']); ?></td>
                                </tr>
                                <tr>
                                    <th>Ubicación:</th>
                                    <td><?php echo htmlspecialchars($registration['location']); ?></td>
                                </tr>
                                <tr>
                                    <th>Categoría:</th>
                                    <td><?php echo htmlspecialchars($registration['category_name']); ?></td>
                                </tr>
                                <tr>
                                    <th>Código de Registro:</th>
                                    <td>
                                        <strong class="text-primary">
                                            <?php echo htmlspecialchars($registration['registration_code']); ?>
                                        </strong>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Estado:</th>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo match($registration['registration_status']) {
                                                'confirmed' => 'success',
                                                'pending' => 'warning',
                                                'cancelled' => 'danger',
                                                default => 'secondary'
                                            };
                                        ?>">
                                            <?php echo htmlspecialchars(ucfirst($registration['registration_status'])); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php if ($registration['price'] > 0): ?>
                                    <tr>
                                        <th>Estado del Pago:</th>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo match($registration['payment_status']) {
                                                    'completed' => 'success',
                                                    'pending' => 'warning',
                                                    'rejected' => 'danger',
                                                    default => 'secondary'
                                                };
                                            ?>">
                                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $registration['payment_status']))); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </table>

                            <div class="alert alert-info mt-4">
                                <i class="fas fa-info-circle"></i>
                                Se ha enviado un correo electrónico con los detalles de tu registro a 
                                <strong><?php echo htmlspecialchars($_SESSION['user']['email']); ?></strong>.
                                Por favor, guarda tu código de registro para futuras referencias.
                            </div>

                            <div class="text-center mt-4">
                                <a href="view.php?id=<?php echo htmlspecialchars($_GET['event_id']); ?>" 
                                   class="btn btn-primary me-2">
                                    <i class="fas fa-eye"></i> Ver Detalles del Evento
                                </a>
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-list"></i> Ver Todos los Eventos
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 