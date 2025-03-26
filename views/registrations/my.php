<?php
require_once '../../includes/auth.php';
require_once '../../includes/config.php';
require_once '../../includes/Database.php';

// Verificar si el usuario está autenticado
if (!isAuthenticated()) {
    header('Location: ../auth/login.php');
    exit;
}

$db = new Database();
$conn = $db->getConnection();
$user_id = $_SESSION['user']['id'];

// Obtener los registros del usuario
$stmt = $conn->prepare("
    SELECT r.*, e.name as event_name, e.date, e.location,
           pc.name as category_name, pc.price,
           p.status as payment_status, p.payment_date,
           pm.name as payment_method
    FROM registrations r
    JOIN events e ON r.event_id = e.id
    JOIN pricing_categories pc ON r.pricing_category_id = pc.id
    LEFT JOIN payments p ON r.id = p.registration_id
    LEFT JOIN payment_methods pm ON p.payment_method_id = pm.id
    WHERE r.user_id = :user_id
    ORDER BY r.created_at DESC
");
$stmt->execute([':user_id' => $user_id]);
$registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Registros - EventManager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/navbar.php'; ?>

    <div class="container mt-4">
        <h2>Mis Registros</h2>

        <?php if (empty($registrations)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No tienes registros a eventos.
                <a href="../events/list.php" class="btn btn-primary ms-3">Ver Eventos Disponibles</a>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($registrations as $registration): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <?php echo htmlspecialchars($registration['event_name']); ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-2">
                                    <i class="fas fa-calendar"></i> 
                                    <?php echo date('d/m/Y H:i', strtotime($registration['date'])); ?>
                                </div>
                                <div class="mb-2">
                                    <i class="fas fa-map-marker-alt"></i> 
                                    <?php echo htmlspecialchars($registration['location']); ?>
                                </div>
                                <div class="mb-2">
                                    <i class="fas fa-tag"></i> 
                                    <?php echo htmlspecialchars($registration['category_name']); ?> - 
                                    $<?php echo number_format($registration['price'], 2); ?>
                                </div>
                                <div class="mb-2">
                                    <i class="fas fa-clock"></i> Estado: 
                                    <span class="badge <?php 
                                        echo match($registration['status']) {
                                            'confirmed' => 'bg-success',
                                            'pending' => 'bg-warning',
                                            'cancelled' => 'bg-danger',
                                            default => 'bg-secondary'
                                        };
                                    ?>">
                                        <?php echo match($registration['status']) {
                                            'confirmed' => 'Confirmado',
                                            'pending' => 'Pendiente',
                                            'cancelled' => 'Cancelado',
                                            default => 'Desconocido'
                                        }; ?>
                                    </span>
                                </div>
                                <?php if (isset($registration['payment_method'])): ?>
                                    <div class="mb-2">
                                        <i class="fas fa-money-bill"></i> Pago: 
                                        <?php echo htmlspecialchars($registration['payment_method']); ?> - 
                                        <span class="badge <?php 
                                            echo match($registration['payment_status']) {
                                                'completed' => 'bg-success',
                                                'pending' => 'bg-warning',
                                                'failed' => 'bg-danger',
                                                'refunded' => 'bg-info',
                                                default => 'bg-secondary'
                                            };
                                        ?>">
                                            <?php echo match($registration['payment_status']) {
                                                'completed' => 'Completado',
                                                'pending' => 'Pendiente',
                                                'failed' => 'Fallido',
                                                'refunded' => 'Reembolsado',
                                                default => 'Desconocido'
                                            }; ?>
                                        </span>
                                        <?php if ($registration['payment_date']): ?>
                                            <br>
                                            <small class="text-muted">
                                                Fecha: <?php echo date('d/m/Y H:i', strtotime($registration['payment_date'])); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if ($registration['status'] === 'pending'): ?>
                                <div class="card-footer">
                                    <a href="../events/view.php?id=<?php echo $registration['event_id']; ?>" 
                                       class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i> Ver Detalles
                                    </a>
                                    <?php if (isset($registration['payment_status']) && $registration['payment_status'] === 'pending'): ?>
                                        <a href="../payments/process.php?registration_id=<?php echo $registration['id']; ?>" 
                                           class="btn btn-success btn-sm">
                                            <i class="fas fa-money-bill"></i> Completar Pago
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 