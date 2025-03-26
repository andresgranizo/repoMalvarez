<?php
session_start();
require_once '../../includes/Database.php';
require_once '../../includes/utilities.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user'])) {
    header('Location: /EventManager/views/auth/login.php');
    exit;
}

// Verificar si se proporcionó el ID de registro
if (!isset($_GET['registration_id'])) {
    header('Location: /EventManager/views/events/index.php');
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Obtener información del registro y el evento
    $stmt = $conn->prepare("
        SELECT r.*, e.title as event_title, pc.name as category_name, pc.price,
               p.id as payment_id, p.status as payment_status
        FROM registrations r
        INNER JOIN events e ON r.event_id = e.id
        INNER JOIN pricing_categories pc ON r.pricing_category_id = pc.id
        LEFT JOIN payments p ON r.id = p.registration_id
        WHERE r.id = :registration_id AND r.user_id = :user_id
    ");
    $stmt->execute([
        'registration_id' => $_GET['registration_id'],
        'user_id' => $_SESSION['user']['id']
    ]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$registration) {
        $_SESSION['error'] = 'Registro no encontrado';
        header('Location: /EventManager/views/events/index.php');
        exit;
    }

} catch (Exception $e) {
    $_SESSION['error'] = 'Error al cargar la información: ' . $e->getMessage();
    header('Location: /EventManager/views/events/index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transferencia Bancaria - <?php echo htmlspecialchars($registration['event_title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include_once '../templates/navigation.php'; ?>

    <div class="container my-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Instrucciones de Transferencia Bancaria</h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <h5>Detalles del Evento:</h5>
                            <p><strong>Evento:</strong> <?php echo htmlspecialchars($registration['event_title']); ?></p>
                            <p><strong>Categoría:</strong> <?php echo htmlspecialchars($registration['category_name']); ?></p>
                            <p><strong>Monto a Pagar:</strong> $<?php echo number_format($registration['price'], 2); ?></p>
                            <p><strong>Código de Registro:</strong> <?php echo htmlspecialchars($registration['registration_code']); ?></p>
                        </div>

                        <div class="alert alert-warning">
                            <h5>Información Bancaria:</h5>
                            <p><strong>Banco:</strong> Banco XYZ</p>
                            <p><strong>Número de Cuenta:</strong> 1234567890</p>
                            <p><strong>Titular:</strong> EventManager S.A.</p>
                            <p><strong>Tipo de Cuenta:</strong> Corriente</p>
                            <p><strong>RUC:</strong> 0123456789001</p>
                        </div>

                        <form id="uploadForm" class="mt-4">
                            <input type="hidden" name="registration_id" value="<?php echo $registration['id']; ?>">
                            <input type="hidden" name="payment_id" value="<?php echo $registration['payment_id']; ?>">
                            
                            <div class="mb-3">
                                <label for="amount" class="form-label">Monto Transferido <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="amount" name="amount" 
                                           step="0.01" min="<?php echo $registration['price']; ?>" 
                                           value="<?php echo $registration['price']; ?>" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="receipt" class="form-label">Comprobante de Transferencia <span class="text-danger">*</span></label>
                                <input type="file" class="form-control" id="receipt" name="receipt" 
                                       accept=".jpg,.jpeg,.png,.pdf" required>
                                <div class="form-text">
                                    Formatos permitidos: JPG, PNG, PDF. Tamaño máximo: 5MB
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="comments" class="form-label">Comentarios</label>
                                <textarea class="form-control" id="comments" name="comments" rows="3"></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload"></i> Subir Comprobante
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('uploadForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            try {
                const response = await fetch('/EventManager/api/payments/upload-receipt.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Comprobante subido exitosamente. El pago será verificado por el organizador.');
                    window.location.href = '/EventManager/views/events/index.php';
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Error al procesar la solicitud: ' + error.message);
            }
        });
    </script>
</body>
</html> 