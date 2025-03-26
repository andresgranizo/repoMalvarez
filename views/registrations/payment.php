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
$registration = null;
$payment = null;
$payment_method = null;

// Verificar que se proporcionó un ID de registro
if (!isset($_GET['registration_id'])) {
    header('Location: ../events/index.php');
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    $user_id = $_SESSION['user']['id'];
    $registration_id = $_GET['registration_id'];

    // Obtener información del registro y pago
    $stmt = $conn->prepare("
        SELECT r.*, e.title as event_title, e.organizer_id,
               p.id as payment_id, p.amount, p.status as payment_status,
               p.payment_method_id, p.transaction_id,
               pc.name as category_name, pc.description as category_description
        FROM registrations r
        JOIN events e ON r.event_id = e.id
        JOIN pricing_categories pc ON r.pricing_category_id = pc.id
        JOIN payments p ON r.id = p.registration_id
        WHERE r.id = ? AND r.user_id = ?
    ");
    $stmt->execute([$registration_id, $user_id]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$registration) {
        throw new Exception('Registro no encontrado');
    }

    // Obtener información del método de pago
    $stmt = $conn->prepare("
        SELECT * FROM payment_methods 
        WHERE id = ?
    ");
    $stmt->execute([$registration['payment_method_id']]);
    $payment_method = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment_method) {
        throw new Exception('Método de pago no encontrado');
    }

    // Procesar pago
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'process_payment') {
            $payment_data = [];
            
            switch ($payment_method['type']) {
                case 'online':
                    // Aquí se integraría con la pasarela de pago correspondiente
                    // Por ahora, simulamos un pago exitoso
                    $payment_data = [
                        'transaction_id' => uniqid('TRX-'),
                        'status' => 'completed',
                        'provider_response' => json_encode([
                            'success' => true,
                            'message' => 'Pago procesado exitosamente'
                        ])
                    ];
                    break;

                case 'transfer':
                    // Para transferencias, guardamos los datos del comprobante
                    $payment_data = [
                        'transfer_date' => $_POST['transfer_date'],
                        'reference_number' => $_POST['reference_number'],
                        'bank_name' => $_POST['bank_name'],
                        'amount' => $_POST['amount'],
                        'status' => 'pending',
                        'notes' => $_POST['notes'] ?? ''
                    ];
                    break;

                default:
                    throw new Exception('Tipo de pago no soportado');
            }

            // Actualizar pago
            $stmt = $conn->prepare("
                UPDATE payments 
                SET payment_data = ?, 
                    status = ?,
                    transaction_id = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                json_encode($payment_data),
                $payment_data['status'],
                $payment_data['transaction_id'] ?? null,
                $registration['payment_id']
            ]);

            // Si el pago es exitoso, actualizar el registro
            if ($payment_data['status'] === 'completed') {
                $stmt = $conn->prepare("
                    UPDATE registrations 
                    SET status = 'confirmed',
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$registration_id]);

                $success = "¡Pago procesado exitosamente! Tu registro ha sido confirmado.";
            } else {
                $success = "Información de pago registrada. El organizador verificará los datos.";
            }

            // Recargar información
            $stmt = $conn->prepare("
                SELECT r.*, e.title as event_title,
                       p.id as payment_id, p.amount, p.status as payment_status,
                       p.payment_method_id, p.transaction_id, p.payment_data,
                       pc.name as category_name, pc.description as category_description
                FROM registrations r
                JOIN events e ON r.event_id = e.id
                JOIN pricing_categories pc ON r.pricing_category_id = pc.id
                JOIN payments p ON r.id = p.registration_id
                WHERE r.id = ?
            ");
            $stmt->execute([$registration_id]);
            $registration = $stmt->fetch(PDO::FETCH_ASSOC);
        }
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
    <title>Pago de Registro - EventManager</title>
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
                                <i class="fas fa-credit-card"></i> Pago de Registro
                            </h1>
                            <a href="../events/view.php?id=<?php echo $registration['event_id']; ?>" class="btn btn-secondary">
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

                        <?php if (!$error && $registration['payment_status'] !== 'completed'): ?>
                            <!-- Resumen del Registro -->
                            <div class="mb-4">
                                <h5><?php echo htmlspecialchars($registration['event_title']); ?></h5>
                                <p class="text-muted mb-2">
                                    <?php echo htmlspecialchars($registration['category_name']); ?>
                                    <?php if ($registration['category_description']): ?>
                                        - <?php echo htmlspecialchars($registration['category_description']); ?>
                                    <?php endif; ?>
                                </p>
                                <h4 class="text-primary mb-0">
                                    $<?php echo number_format($registration['amount'], 2); ?>
                                </h4>
                            </div>

                            <hr class="my-4">

                            <!-- Formulario de Pago -->
                            <form method="POST" class="needs-validation" novalidate>
                                <input type="hidden" name="action" value="process_payment">

                                <?php if ($payment_method['type'] === 'online'): ?>
                                    <!-- Pago en Línea -->
                                    <div class="alert alert-info">
                                        <h5 class="alert-heading">
                                            <i class="fas fa-info-circle"></i> Pago con <?php echo $payment_method['provider']; ?>
                                        </h5>
                                        <p class="mb-0">
                                            Serás redirigido a la plataforma de pago para completar la transacción.
                                        </p>
                                    </div>

                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="fas fa-lock"></i> Pagar Ahora
                                        </button>
                                    </div>

                                <?php elseif ($payment_method['type'] === 'transfer'): ?>
                                    <!-- Transferencia Bancaria -->
                                    <?php 
                                    $config = json_decode($payment_method['config'], true);
                                    ?>
                                    <div class="alert alert-info mb-4">
                                        <h5 class="alert-heading">Datos Bancarios</h5>
                                        <p class="mb-0">
                                            <strong>Banco:</strong> <?php echo htmlspecialchars($config['bank_name']); ?><br>
                                            <strong>Cuenta:</strong> <?php echo htmlspecialchars($config['account_number']); ?><br>
                                            <strong>Titular:</strong> <?php echo htmlspecialchars($config['account_holder']); ?><br>
                                            <strong>Monto:</strong> $<?php echo number_format($registration['amount'], 2); ?>
                                        </p>
                                    </div>

                                    <div class="mb-3">
                                        <label for="transfer_date" class="form-label">Fecha de Transferencia</label>
                                        <input type="date" class="form-control" id="transfer_date" name="transfer_date" 
                                               max="<?php echo date('Y-m-d'); ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="reference_number" class="form-label">Número de Referencia</label>
                                        <input type="text" class="form-control" id="reference_number" name="reference_number" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="bank_name" class="form-label">Banco Emisor</label>
                                        <input type="text" class="form-control" id="bank_name" name="bank_name" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="amount" class="form-label">Monto Transferido</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" class="form-control" id="amount" name="amount" 
                                                   step="0.01" min="<?php echo $registration['amount']; ?>" 
                                                   value="<?php echo $registration['amount']; ?>" required>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="notes" class="form-label">Notas Adicionales</label>
                                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                                    </div>

                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="fas fa-paper-plane"></i> Enviar Comprobante
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </form>

                        <?php else: ?>
                            <!-- Resumen del Pago -->
                            <div class="text-center">
                                <?php if ($registration['payment_status'] === 'completed'): ?>
                                    <div class="mb-4">
                                        <i class="fas fa-check-circle text-success fa-3x"></i>
                                        <h4 class="mt-2">¡Pago Completado!</h4>
                                        <p class="text-muted">
                                            Tu registro ha sido confirmado exitosamente.
                                        </p>
                                    </div>
                                <?php elseif ($registration['payment_status'] === 'pending'): ?>
                                    <div class="mb-4">
                                        <i class="fas fa-clock text-warning fa-3x"></i>
                                        <h4 class="mt-2">Pago Pendiente</h4>
                                        <p class="text-muted">
                                            Tu pago está siendo verificado por el organizador.
                                        </p>
                                    </div>
                                <?php endif; ?>

                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title">Detalles del Pago</h5>
                                        <dl class="row mb-0">
                                            <dt class="col-sm-4">Evento:</dt>
                                            <dd class="col-sm-8"><?php echo htmlspecialchars($registration['event_title']); ?></dd>
                                            
                                            <dt class="col-sm-4">Categoría:</dt>
                                            <dd class="col-sm-8"><?php echo htmlspecialchars($registration['category_name']); ?></dd>
                                            
                                            <dt class="col-sm-4">Monto:</dt>
                                            <dd class="col-sm-8">$<?php echo number_format($registration['amount'], 2); ?></dd>
                                            
                                            <dt class="col-sm-4">Método:</dt>
                                            <dd class="col-sm-8">
                                                <?php echo htmlspecialchars($payment_method['name']); ?>
                                                (<?php echo ucfirst($payment_method['type']); ?>)
                                            </dd>
                                            
                                            <?php if ($registration['transaction_id']): ?>
                                                <dt class="col-sm-4">Transacción:</dt>
                                                <dd class="col-sm-8"><?php echo htmlspecialchars($registration['transaction_id']); ?></dd>
                                            <?php endif; ?>
                                            
                                            <dt class="col-sm-4">Estado:</dt>
                                            <dd class="col-sm-8">
                                                <span class="badge bg-<?php 
                                                    echo match($registration['payment_status']) {
                                                        'completed' => 'success',
                                                        'pending' => 'warning',
                                                        'failed' => 'danger',
                                                        default => 'secondary'
                                                    };
                                                ?>">
                                                    <?php echo ucfirst($registration['payment_status']); ?>
                                                </span>
                                            </dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
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