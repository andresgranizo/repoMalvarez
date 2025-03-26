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
    header('Location: /EventManager/views/events/index.php');
    exit;
}

$eventId = $_GET['event_id'];
$error = null;
$event = null;
$categories = [];
$paymentMethods = [];
$isIEEEMember = false;
$ieeeRegion = null;

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Verificar si el usuario es miembro IEEE
    $stmt = $conn->prepare("
        SELECT ieee_member, ieee_member_id, ieee_region 
        FROM users 
        WHERE id = :user_id
    ");
    $stmt->execute(['user_id' => $_SESSION['user']['id']]);
    $userInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    $isIEEEMember = (bool)($userInfo['ieee_member'] ?? false);
    $ieeeRegion = $userInfo['ieee_region'] ?? '';

    // Debug information
    error_log("User Info: " . print_r($userInfo, true));
    error_log("Is IEEE Member: " . ($isIEEEMember ? 'Yes' : 'No'));
    error_log("IEEE Region: " . $ieeeRegion);

    // Obtener información del evento
    $stmt = $conn->prepare("
        SELECT e.*, u.name as organizer_name,
               (SELECT COUNT(*) 
                FROM registrations r2 
                WHERE r2.event_id = e.id 
                AND r2.status != 'cancelled') as registration_count
        FROM events e
        INNER JOIN users u ON e.organizer_id = u.id
        WHERE e.id = :event_id AND e.status = 'published'
    ");
    $stmt->execute(['event_id' => $eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        header('Location: /EventManager/views/events/index.php');
        exit;
    }

    // Verificar si el usuario ya está registrado
    $stmt = $conn->prepare("
        SELECT id FROM registrations 
        WHERE event_id = :event_id AND user_id = :user_id AND status != 'cancelled'
    ");
    $stmt->execute([
        'event_id' => $eventId,
        'user_id' => $_SESSION['user']['id']
    ]);
    if ($stmt->fetch()) {
        header('Location: /EventManager/views/registrations/my-registrations.php');
        exit;
    }

    // Obtener categorías disponibles con conteo de registros
    if ($isIEEEMember) {
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
            AND (
                pc.ieee_member_discount > 0
                OR (
                    pc.ieee_region IS NULL 
                    OR pc.ieee_region = ''
                    OR pc.ieee_region = :ieee_region
                )
            )
            ORDER BY pc.price ASC
        ");
        $stmt->execute([
            'event_id' => $eventId,
            'ieee_region' => $ieeeRegion
        ]);
    } else {
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
            AND (pc.ieee_member_discount = 0 OR pc.ieee_member_discount IS NULL)
            ORDER BY pc.price ASC
        ");
        $stmt->execute(['event_id' => $eventId]);
    }
    
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Debug information
    error_log("Categories found: " . count($categories));
    
    // Obtener métodos de pago disponibles
    $stmt = $conn->prepare("
        SELECT pm.* 
        FROM payment_methods pm
        INNER JOIN events e ON JSON_EXTRACT(e.payment_config, '$.enabled_methods') LIKE CONCAT('%\"', pm.id, '\"%')
        WHERE e.id = :event_id 
        AND pm.is_active = 1
        ORDER BY pm.name
    ");
    $stmt->execute(['event_id' => $eventId]);
    $paymentMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Mostrar las categorías encontradas
    if (empty($categories)) {
        $error = "No hay categorías disponibles para este evento. Por favor, contacte al organizador.";
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
    <title>Registro para Evento - EventManager</title>
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
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0">Registro para Evento</h4>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($event['title']); ?></h5>
                            
                            <p class="text-primary mb-3">
                                <i class="fas fa-calendar-alt me-2"></i>
                                <?php echo Utilities::formatDate($event['start_date']); ?>
                            </p>

                            <?php if ($isIEEEMember): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> 
                                    Se muestran precios especiales para miembros IEEE 
                                    <?php echo $ieeeRegion ? "(Región $ieeeRegion)" : ''; ?>
                                </div>
                            <?php endif; ?>

                            <form id="registrationForm" class="needs-validation" novalidate>
                                <input type="hidden" name="event_id" value="<?php echo $eventId; ?>">

                                <div class="mb-3">
                                    <label for="category" class="form-label">Categoría de Participación <span class="text-danger">*</span></label>
                                    <div class="row">
                                        <?php foreach ($categories as $category): ?>
                                            <?php 
                                            $isAvailable = !$category['capacity'] || $category['registered_count'] < $category['capacity'];
                                            $remainingSpots = $category['capacity'] ? ($category['capacity'] - $category['registered_count']) : 'Ilimitado';
                                            ?>
                                            <div class="col-md-6 mb-3">
                                                <div class="card">
                                                    <div class="card-body">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" 
                                                                   name="pricing_category_id" 
                                                                   id="category_<?php echo $category['id']; ?>" 
                                                                   value="<?php echo $category['id']; ?>"
                                                                   <?php echo $isAvailable ? 'required' : 'disabled'; ?>>
                                                            <label class="form-check-label" for="category_<?php echo $category['id']; ?>">
                                                                <h6 class="mb-1"><?php echo htmlspecialchars($category['name']); ?></h6>
                                                                <p class="text-muted mb-1 small">
                                                                    <?php echo htmlspecialchars($category['description']); ?>
                                                                </p>
                                                                <div class="d-flex justify-content-between align-items-center">
                                                                    <div>
                                                                        <?php if ($isIEEEMember && $category['ieee_member_discount'] > 0): ?>
                                                                            <span class="h5 mb-0">
                                                                                $<?php echo number_format($category['price'] * (1 - $category['ieee_member_discount']/100), 2); ?>
                                                                            </span>
                                                                            <small class="text-muted text-decoration-line-through ms-2">
                                                                                $<?php echo number_format($category['price'], 2); ?>
                                                                            </small>
                                                                            <small class="text-success d-block">
                                                                                <?php echo number_format($category['ieee_member_discount'], 0); ?>% descuento IEEE
                                                                            </small>
                                                                        <?php else: ?>
                                                                            <span class="h5 mb-0">
                                                                                $<?php echo number_format($category['price'], 2); ?>
                                                                            </span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    <?php if ($category['capacity']): ?>
                                                                        <span class="badge bg-<?php echo $isAvailable ? 'info' : 'danger'; ?>">
                                                                            <?php echo $isAvailable ? "Disponibles: $remainingSpots" : 'Agotado'; ?>
                                                                        </span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="invalid-feedback">
                                        Por favor selecciona una categoría de participación.
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="comments" class="form-label">Comentarios o Requisitos Especiales</label>
                                    <textarea class="form-control" id="comments" name="comments" rows="3"></textarea>
                                </div>

                                <div class="mb-3">
                                    <label for="payment_method" class="form-label">Método de Pago <span class="text-danger">*</span></label>
                                    <select class="form-select" id="payment_method" name="payment_method_id" required>
                                        <option value="">Seleccione un método de pago</option>
                                        <?php foreach ($paymentMethods as $method): ?>
                                            <option value="<?php echo $method['id']; ?>">
                                                <?php echo htmlspecialchars($method['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">
                                        Por favor seleccione un método de pago.
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="terms" required>
                                        <label class="form-check-label" for="terms">
                                            Acepto los términos y condiciones del evento
                                        </label>
                                        <div class="invalid-feedback">
                                            Debes aceptar los términos y condiciones.
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary" id="submitButton" disabled>
                                    Confirmar Registro
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    document.getElementById('registrationForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (!this.checkValidity()) {
            e.stopPropagation();
            this.classList.add('was-validated');
            return;
        }

        const formData = {
            event_id: this.elements['event_id'].value,
            pricing_category_id: this.elements['pricing_category_id'].value,
            registration_data: {
                comments: this.elements['comments'].value,
                payment_method_id: this.elements['payment_method_id'].value
            }
        };

        fetch('/EventManager/api/registrations/register.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.data && data.data.redirect_url) {
                    window.location.href = data.data.redirect_url;
                } else {
                    window.location.href = '/EventManager/views/registrations/my-registrations.php';
                }
            } else {
                alert(data.message || 'Error al procesar el registro');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al procesar el registro');
        });
    });

    // Habilitar/deshabilitar el botón según el estado del checkbox
    document.getElementById('terms').addEventListener('change', function() {
        document.getElementById('submitButton').disabled = !this.checked;
    });

    // Actualizar el precio total cuando se selecciona una categoría
    document.querySelectorAll('input[name="pricing_category_id"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const selectedCard = this.closest('.card');
            document.querySelectorAll('.card').forEach(card => {
                card.classList.remove('border-primary');
            });
            if (selectedCard) {
                selectedCard.classList.add('border-primary');
            }
        });
    });
    </script>
</body>
</html> 