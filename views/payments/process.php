<?php
session_start();
require_once '../../includes/Database.php';
require_once '../../includes/utilities.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user'])) {
    header('Location: /EventManager/views/auth/login.php');
    exit;
}

// Verificar si se proporcionó un ID de registro
if (!isset($_GET['registration_id'])) {
    header('Location: /EventManager/views/events/index.php');
    exit;
}

$registrationId = $_GET['registration_id'];
$error = null;
$registration = null;
$paymentMethods = [];

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Obtener información del registro y evento
    $stmt = $conn->prepare("
        SELECT r.*, e.title as event_title, pc.name as category_name, pc.price
        FROM registrations r
        INNER JOIN events e ON r.event_id = e.id
        INNER JOIN pricing_categories pc ON r.pricing_category_id = pc.id
        WHERE r.id = :id AND r.user_id = :user_id
    ");
    $stmt->execute([
        'id' => $registrationId,
        'user_id' => $_SESSION['user']['id']
    ]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$registration) {
        throw new Exception('Registro no encontrado');
    }

    // Obtener métodos de pago disponibles
    $stmt = $conn->prepare("SELECT * FROM payment_methods WHERE is_active = 1");
    $stmt->execute();
    $paymentMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = "Error al cargar la información: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proceso de Pago - EventManager</title>
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
                            <h4 class="mb-0">Proceso de Pago</h4>
                        </div>
                        <div class="card-body">
                            <div class="mb-4">
                                <h5>Resumen de la Compra</h5>
                                <p><strong>Evento:</strong> <?php echo htmlspecialchars($registration['event_title']); ?></p>
                                <p><strong>Categoría:</strong> <?php echo htmlspecialchars($registration['category_name']); ?></p>
                                <p><strong>Código de Registro:</strong> <?php echo htmlspecialchars($registration['registration_code']); ?></p>
                                <p class="h4">Total a Pagar: $<?php echo number_format($registration['payment_amount'], 2); ?></p>
                            </div>

                            <h5 class="mb-3">Selecciona un Método de Pago</h5>
                            <form id="paymentForm">
                                <input type="hidden" name="registration_id" value="<?php echo $registrationId; ?>">
                                
                                <?php foreach ($paymentMethods as $method): ?>
                                    <div class="card mb-3">
                                        <div class="card-body">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" 
                                                       name="payment_method_id" 
                                                       id="method_<?php echo $method['id']; ?>" 
                                                       value="<?php echo $method['id']; ?>" required>
                                                <label class="form-check-label" for="method_<?php echo $method['id']; ?>">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($method['name']); ?></h6>
                                                    <p class="text-muted mb-0 small">
                                                        <?php echo htmlspecialchars($method['description']); ?>
                                                    </p>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                                <button type="submit" class="btn btn-primary w-100">
                                    Proceder al Pago
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
    document.getElementById('paymentForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (!this.checkValidity()) {
            e.stopPropagation();
            this.classList.add('was-validated');
            return;
        }

        const formData = {
            registration_id: this.elements['registration_id'].value,
            payment_method_id: this.elements['payment_method_id'].value
        };

        fetch('/EventManager/api/payments/process.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.redirect_url) {
                    window.location.href = data.redirect_url;
                } else {
                    alert('Pago procesado exitosamente');
                    window.location.href = '/EventManager/views/registrations/my-registrations.php';
                }
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al procesar el pago');
        });
    });
    </script>
</body>
</html> 