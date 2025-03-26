<?php
session_start();

// Verificar si el usuario está autenticado y es administrador
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

require_once '../../includes/Database.php';
require_once '../../includes/utilities.php';

$user = null;
$error = null;
$success = null;

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Si es una edición, cargar los datos del usuario
    if (isset($_GET['id'])) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new Exception("Usuario no encontrado");
        }
    }

    // Procesar el formulario
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        $status = $_POST['status'];
        $password = trim($_POST['password'] ?? '');

        // Validaciones básicas
        if (empty($name) || empty($email)) {
            throw new Exception("Nombre y email son obligatorios");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Email inválido");
        }

        // Verificar si el email ya existe
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $_GET['id'] ?? 0]);
        if ($stmt->rowCount() > 0) {
            throw new Exception("El email ya está registrado");
        }

        // Preparar datos para la base de datos
        $data = [
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'status' => $status
        ];

        if (isset($_GET['id'])) {
            // Actualizar usuario existente
            if (!empty($password)) {
                $data['password'] = password_hash($password, PASSWORD_DEFAULT);
            }
            
            $sql = "UPDATE users SET 
                    name = :name, 
                    email = :email,
                    role = :role,
                    status = :status" .
                    (!empty($password) ? ", password = :password" : "") .
                    " WHERE id = :id";
            
            $data['id'] = $_GET['id'];
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($data);
            $success = "¡Usuario actualizado exitosamente!";
        } else {
            // Crear nuevo usuario
            if (empty($password)) {
                throw new Exception("La contraseña es obligatoria para nuevos usuarios");
            }
            
            $data['password'] = password_hash($password, PASSWORD_DEFAULT);
            
            $sql = "INSERT INTO users (name, email, password, role, status, created_at, updated_at)
                    VALUES (:name, :email, :password, :role, :status, NOW(), NOW())";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($data);
            $success = "¡Usuario creado exitosamente!";
        }

        // Redireccionar después de procesar
        header("Location: manage.php?success=" . urlencode($success));
        exit;
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
    <title><?php echo isset($user) ? 'Editar' : 'Crear'; ?> Usuario - EventManager</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .required::after {
            content: " *";
            color: red;
        }
    </style>
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
                        <a class="nav-link" href="../events/manage.php">
                            <i class="fas fa-calendar"></i> Eventos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage.php">
                            <i class="fas fa-users"></i> Usuarios
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
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-user-plus"></i>
                            <?php echo isset($user) ? 'Editar' : 'Crear'; ?> Usuario
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="name" class="form-label required">Nombre</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label required">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label <?php echo !isset($user) ? 'required' : ''; ?>">
                                    Contraseña
                                    <?php if (isset($user)): ?>
                                        <small class="text-muted">(dejar en blanco para mantener la actual)</small>
                                    <?php endif; ?>
                                </label>
                                <input type="password" class="form-control" id="password" name="password" 
                                       <?php echo !isset($user) ? 'required' : ''; ?>>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="role" class="form-label required">Rol</label>
                                    <select class="form-select" id="role" name="role" required>
                                        <option value="user" <?php echo ($user['role'] ?? '') === 'user' ? 'selected' : ''; ?>>Usuario</option>
                                        <option value="organizer" <?php echo ($user['role'] ?? '') === 'organizer' ? 'selected' : ''; ?>>Organizador</option>
                                        <option value="admin" <?php echo ($user['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="status" class="form-label required">Estado</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="active" <?php echo ($user['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Activo</option>
                                        <option value="inactive" <?php echo ($user['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactivo</option>
                                    </select>
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> 
                                    <?php echo isset($user) ? 'Actualizar' : 'Crear'; ?> Usuario
                                </button>
                                <a href="manage.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancelar
                                </a>
                            </div>
                        </form>
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
            var forms = document.querySelectorAll('.needs-validation');
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
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