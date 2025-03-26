<?php
require_once '../../includes/auth.php';
require_once '../../includes/config.php';
require_once '../../includes/Database.php';

checkLogin();

$db = new Database();
$conn = $db->getConnection();
$user_id = $_SESSION['user']['id'];
$success_message = '';
$error_message = '';

// Procesar actualización del perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Verificar si el correo ya está en uso por otro usuario
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email AND id != :user_id");
    $stmt->execute([':email' => $email, ':user_id' => $user_id]);
    if ($stmt->rowCount() > 0) {
        $error_message = "El correo electrónico ya está en uso por otro usuario.";
    } else {
        // Actualizar información básica
        $stmt = $conn->prepare("UPDATE users SET name = :name, email = :email WHERE id = :user_id");
        if ($stmt->execute([':name' => $name, ':email' => $email, ':user_id' => $user_id])) {
            $_SESSION['user']['name'] = $name;
            $_SESSION['user']['email'] = $email;
            $success_message = "Perfil actualizado exitosamente.";
        } else {
            $error_message = "Error al actualizar el perfil.";
        }

        // Actualizar contraseña si se proporcionó
        if (!empty($current_password) && !empty($new_password)) {
            if ($new_password !== $confirm_password) {
                $error_message = "Las contraseñas nuevas no coinciden.";
            } else {
                // Verificar contraseña actual
                $stmt = $conn->prepare("SELECT password FROM users WHERE id = :user_id");
                $stmt->execute([':user_id' => $user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (password_verify($current_password, $user['password'])) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET password = :password WHERE id = :user_id");
                    if ($stmt->execute([':password' => $hashed_password, ':user_id' => $user_id])) {
                        $success_message = "Perfil y contraseña actualizados exitosamente.";
                    } else {
                        $error_message = "Error al actualizar la contraseña.";
                    }
                } else {
                    $error_message = "La contraseña actual es incorrecta.";
                }
            }
        }
    }
}

// Obtener información del usuario
$stmt = $conn->prepare("SELECT * FROM users WHERE id = :user_id");
$stmt->execute([':user_id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - EventManager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/navbar.php'; ?>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h2 class="mb-0">Mi Perfil</h2>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($success_message)): ?>
                            <div class="alert alert-success"><?php echo $success_message; ?></div>
                        <?php endif; ?>
                        
                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger"><?php echo $error_message; ?></div>
                        <?php endif; ?>

                        <form method="POST" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="name" class="form-label">Nombre</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($user['name']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Correo Electrónico</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>

                            <hr class="my-4">
                            <h4>Cambiar Contraseña</h4>
                            <p class="text-muted small">Deja estos campos en blanco si no deseas cambiar tu contraseña.</p>

                            <div class="mb-3">
                                <label for="current_password" class="form-label">Contraseña Actual</label>
                                <input type="password" class="form-control" id="current_password" name="current_password">
                            </div>

                            <div class="mb-3">
                                <label for="new_password" class="form-label">Nueva Contraseña</label>
                                <input type="password" class="form-control" id="new_password" name="new_password">
                            </div>

                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirmar Nueva Contraseña</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Guardar Cambios
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validación del formulario
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms).forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                })
            })
        })()
    </script>
</body>
</html> 