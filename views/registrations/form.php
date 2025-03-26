<?php
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user'])) {
    header('Location: ../auth/login.php');
    exit;
}

require_once '../../includes/Database.php';
require_once '../../includes/utilities.php';

$error = null;
$success = null;
$event = null;
$pricing_categories = [];
$payment_methods = [];
$custom_fields = [];

// Verificar que se proporcionó un ID de evento
if (!isset($_GET['event_id'])) {
    header('Location: ../events/index.php');
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    $user_id = $_SESSION['user']['id'];
    $event_id = $_GET['event_id'];

    // Obtener información del evento
    $stmt = $conn->prepare("
        SELECT e.*, 
               (SELECT COUNT(*) FROM registrations WHERE event_id = e.id) as total_registrations
        FROM events e
        WHERE e.id = ? AND e.status = 'published'
    ");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        throw new Exception('El evento no existe o no está disponible');
    }

    // Verificar si ya está registrado
    $stmt = $conn->prepare("
        SELECT id, status FROM registrations 
        WHERE event_id = ? AND user_id = ?
    ");
    $stmt->execute([$event_id, $user_id]);
    $existing_registration = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing_registration) {
        throw new Exception('Ya estás registrado en este evento');
    }

    // Verificar capacidad del evento
    if ($event['max_capacity'] && $event['total_registrations'] >= $event['max_capacity']) {
        throw new Exception('El evento ha alcanzado su capacidad máxima');
    }

    // Obtener categorías de precio activas
    $stmt = $conn->prepare("
        SELECT * FROM pricing_categories 
        WHERE event_id = ? AND is_active = 1
        ORDER BY price ASC
    ");
    $stmt->execute([$event_id]);
    $pricing_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($pricing_categories)) {
        throw new Exception('No hay categorías de precio disponibles');
    }

    // Obtener métodos de pago habilitados
    $payment_config = json_decode($event['payment_config'] ?? '{}', true);
    $enabled_methods = $payment_config['enabled_methods'] ?? [];

    if (!empty($enabled_methods)) {
        $placeholders = str_repeat('?,', count($enabled_methods) - 1) . '?';
        $stmt = $conn->prepare("
            SELECT * FROM payment_methods 
            WHERE id IN ($placeholders) AND is_active = 1
        ");
        $stmt->execute($enabled_methods);
        $payment_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if (empty($payment_methods)) {
        throw new Exception('No hay métodos de pago disponibles');
    }

    // Obtener campos personalizados
    $stmt = $conn->prepare("
        SELECT * FROM custom_fields 
        WHERE event_id = ?
        ORDER BY display_order ASC
    ");
    $stmt->execute([$event_id]);
    $custom_fields = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Procesar formulario
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $category_id = $_POST['category_id'];
        $payment_method_id = $_POST['payment_method_id'];
        
        // Verificar categoría
        $category_key = array_search($category_id, array_column($pricing_categories, 'id'));
        if ($category_key === false) {
            throw new Exception('Categoría de precio inválida');
        }
        $selected_category = $pricing_categories[$category_key];

        // Verificar capacidad de la categoría
        if ($selected_category['max_capacity']) {
            $stmt = $conn->prepare("
                SELECT COUNT(*) FROM registrations 
                WHERE event_id = ? AND pricing_category_id = ?
            ");
            $stmt->execute([$event_id, $category_id]);
            if ($stmt->fetchColumn() >= $selected_category['max_capacity']) {
                throw new Exception('Esta categoría ha alcanzado su capacidad máxima');
            }
        }

        // Verificar método de pago
        $payment_key = array_search($payment_method_id, array_column($payment_methods, 'id'));
        if ($payment_key === false) {
            throw new Exception('Método de pago inválido');
        }

        // Calcular precio final
        $price = $selected_category['price'];
        if ($selected_category['discount_percentage'] > 0) {
            $price = $price * (1 - $selected_category['discount_percentage'] / 100);
        }
        if ($selected_category['ieee_member_discount'] > 0 && $_SESSION['user']['ieee_member']) {
            if (!$selected_category['ieee_region'] || $selected_category['ieee_region'] === $_SESSION['user']['ieee_region']) {
                $price = $price * (1 - $selected_category['ieee_member_discount'] / 100);
            }
        }

        // Crear registro
        $conn->beginTransaction();

        try {
            // Insertar registro
            $stmt = $conn->prepare("
                INSERT INTO registrations (
                    event_id, user_id, pricing_category_id,
                    status, qr_code, ticket_number
                ) VALUES (?, ?, ?, 'pending', ?, ?)
            ");
            $qr_code = generateQRCode();
            $ticket_number = generateTicketNumber();
            $stmt->execute([$event_id, $user_id, $category_id, $qr_code, $ticket_number]);
            $registration_id = $conn->lastInsertId();

            // Insertar pago
            $stmt = $conn->prepare("
                INSERT INTO payments (
                    registration_id, payment_method_id,
                    amount, status
                ) VALUES (?, ?, ?, 'pending')
            ");
            $stmt->execute([$registration_id, $payment_method_id, $price]);

            // Guardar respuestas de campos personalizados
            if (!empty($custom_fields)) {
                $stmt = $conn->prepare("
                    INSERT INTO custom_field_responses (
                        registration_id, custom_field_id, value
                    ) VALUES (?, ?, ?)
                ");
                foreach ($custom_fields as $field) {
                    $value = $_POST['custom_field_' . $field['id']] ?? null;
                    if ($field['is_required'] && empty($value)) {
                        throw new Exception('Por favor complete todos los campos requeridos');
                    }
                    if ($value !== null) {
                        $stmt->execute([$registration_id, $field['id'], $value]);
                    }
                }
            }

            $conn->commit();
            $success = "Registro creado exitosamente. Por favor proceda con el pago.";
            
            // Redirigir a la página de pago
            header("Location: payment.php?registration_id=$registration_id");
            exit;

        } catch (Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}

// Funciones auxiliares
function generateQRCode() {
    return uniqid('QR-', true);
}

function generateTicketNumber() {
    return strtoupper(uniqid('TKT-'));
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - <?php echo htmlspecialchars($event['title']); ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
                        <a class="nav-link" href="../events/index.php">
                            <i class="fas fa-calendar"></i> Eventos
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
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h1 class="h3">
                                <i class="fas fa-ticket-alt"></i> Registro de Evento
                            </h1>
                            <a href="../events/view.php?id=<?php echo $event_id; ?>" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Volver al Evento
                            </a>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <?php echo $success; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!$error): ?>
                            <form method="POST" class="needs-validation" novalidate>
                                <!-- Información del Evento -->
                                <div class="mb-4">
                                    <h5><?php echo htmlspecialchars($event['title']); ?></h5>
                                    <p class="text-muted mb-0">
                                        <?php echo date('d/m/Y H:i', strtotime($event['start_date'])); ?> - 
                                        <?php echo date('d/m/Y H:i', strtotime($event['end_date'])); ?>
                                    </p>
                                </div>

                                <!-- Categoría de Precio -->
                                <div class="mb-3">
                                    <label class="form-label">Categoría</label>
                                    <?php foreach ($pricing_categories as $category): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" 
                                                   name="category_id" 
                                                   value="<?php echo $category['id']; ?>"
                                                   id="category_<?php echo $category['id']; ?>"
                                                   required>
                                            <label class="form-check-label" for="category_<?php echo $category['id']; ?>">
                                                <?php echo htmlspecialchars($category['name']); ?>
                                                <small class="text-muted d-block">
                                                    <?php echo htmlspecialchars($category['description']); ?>
                                                </small>
                                                <div class="mt-1">
                                                    <strong>$<?php echo number_format($category['price'], 2); ?></strong>
                                                    <?php if ($category['discount_percentage'] > 0): ?>
                                                        <span class="badge bg-success">
                                                            <?php echo $category['discount_percentage']; ?>% descuento
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if ($category['ieee_member_discount'] > 0): ?>
                                                        <span class="badge bg-info">
                                                            <?php echo $category['ieee_member_discount']; ?>% descuento IEEE
                                                            <?php if ($category['ieee_region']): ?>
                                                                R<?php echo $category['ieee_region']; ?>
                                                            <?php endif; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Método de Pago -->
                                <div class="mb-3">
                                    <label class="form-label">Método de Pago</label>
                                    <?php foreach ($payment_methods as $method): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" 
                                                   name="payment_method_id" 
                                                   value="<?php echo $method['id']; ?>"
                                                   id="method_<?php echo $method['id']; ?>"
                                                   required>
                                            <label class="form-check-label" for="method_<?php echo $method['id']; ?>">
                                                <?php echo htmlspecialchars($method['name']); ?>
                                                <small class="text-muted d-block">
                                                    <?php echo ucfirst($method['type']); ?> - <?php echo $method['provider']; ?>
                                                </small>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Campos Personalizados -->
                                <?php if (!empty($custom_fields)): ?>
                                    <hr class="my-4">
                                    <h5 class="mb-3">Información Adicional</h5>
                                    
                                    <?php foreach ($custom_fields as $field): ?>
                                        <div class="mb-3">
                                            <label for="custom_field_<?php echo $field['id']; ?>" class="form-label">
                                                <?php echo htmlspecialchars($field['label']); ?>
                                                <?php if ($field['is_required']): ?>
                                                    <span class="text-danger">*</span>
                                                <?php endif; ?>
                                            </label>
                                            
                                            <?php if ($field['type'] === 'text'): ?>
                                                <input type="text" class="form-control"
                                                       id="custom_field_<?php echo $field['id']; ?>"
                                                       name="custom_field_<?php echo $field['id']; ?>"
                                                       <?php echo $field['is_required'] ? 'required' : ''; ?>>

                                            <?php elseif ($field['type'] === 'textarea'): ?>
                                                <textarea class="form-control"
                                                          id="custom_field_<?php echo $field['id']; ?>"
                                                          name="custom_field_<?php echo $field['id']; ?>"
                                                          rows="3"
                                                          <?php echo $field['is_required'] ? 'required' : ''; ?>></textarea>

                                            <?php elseif ($field['type'] === 'select'): ?>
                                                <select class="form-select"
                                                        id="custom_field_<?php echo $field['id']; ?>"
                                                        name="custom_field_<?php echo $field['id']; ?>"
                                                        <?php echo $field['is_required'] ? 'required' : ''; ?>>
                                                    <option value="">Seleccione una opción</option>
                                                    <?php 
                                                    $options = json_decode($field['options'], true);
                                                    foreach ($options as $option): 
                                                    ?>
                                                        <option value="<?php echo htmlspecialchars($option); ?>">
                                                            <?php echo htmlspecialchars($option); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>

                                            <?php elseif ($field['type'] === 'checkbox'): ?>
                                                <?php 
                                                $options = json_decode($field['options'], true);
                                                foreach ($options as $option): 
                                                ?>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox"
                                                               name="custom_field_<?php echo $field['id']; ?>[]"
                                                               value="<?php echo htmlspecialchars($option); ?>"
                                                               id="custom_field_<?php echo $field['id']; ?>_<?php echo $option; ?>">
                                                        <label class="form-check-label" 
                                                               for="custom_field_<?php echo $field['id']; ?>_<?php echo $option; ?>">
                                                            <?php echo htmlspecialchars($option); ?>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>

                                            <?php elseif ($field['type'] === 'radio'): ?>
                                                <?php 
                                                $options = json_decode($field['options'], true);
                                                foreach ($options as $option): 
                                                ?>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio"
                                                               name="custom_field_<?php echo $field['id']; ?>"
                                                               value="<?php echo htmlspecialchars($option); ?>"
                                                               id="custom_field_<?php echo $field['id']; ?>_<?php echo $option; ?>"
                                                               <?php echo $field['is_required'] ? 'required' : ''; ?>>
                                                        <label class="form-check-label"
                                                               for="custom_field_<?php echo $field['id']; ?>_<?php echo $option; ?>">
                                                            <?php echo htmlspecialchars($option); ?>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>

                                            <?php if ($field['is_required']): ?>
                                                <div class="invalid-feedback">
                                                    Este campo es requerido
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <div class="d-grid mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-check"></i> Confirmar Registro
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Validación del formulario
        (function() {
            'use strict';
            
            const forms = document.querySelectorAll('.needs-validation');
            
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    
                    form.classList.add('was-validated');
                }, false);
            });
        })();
    </script>
</body>
</html> 