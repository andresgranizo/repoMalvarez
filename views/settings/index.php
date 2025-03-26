<?php
session_start();

// Verificar si el usuario está autenticado y es administrador
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

require_once '../../includes/Database.php';
require_once '../../includes/utilities.php';

$error = null;
$success = null;

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Obtener configuraciones actuales
    $stmt = $conn->query("SELECT * FROM settings ORDER BY setting_key");
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Procesar formulario de actualización
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        foreach ($_POST['settings'] as $key => $value) {
            $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
            $stmt->execute([$value, $key]);
        }
        $success = "Configuraciones actualizadas exitosamente";
        
        // Recargar configuraciones
        $stmt = $conn->query("SELECT * FROM settings ORDER BY setting_key");
        $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}

// Agrupar configuraciones por categoría
$grouped_settings = [];
foreach ($settings as $setting) {
    $category = explode('_', $setting['setting_key'])[0];
    $grouped_settings[$category][] = $setting;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración - EventManager</title>
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
                        <a class="nav-link" href="../events/manage.php">
                            <i class="fas fa-calendar"></i> Eventos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../users/manage.php">
                            <i class="fas fa-users"></i> Usuarios
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../categories/manage.php">
                            <i class="fas fa-tags"></i> Categorías
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../reports/index.php">
                            <i class="fas fa-chart-bar"></i> Reportes
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
            <div class="col-lg-10">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h1 class="h3 mb-4">
                            <i class="fas fa-cog"></i> Configuración del Sistema
                        </h1>

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

                        <form method="POST" class="needs-validation" novalidate>
                            <div class="accordion" id="settingsAccordion">
                                <?php foreach ($grouped_settings as $category => $category_settings): ?>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button <?php echo $category === 'smtp' ? '' : 'collapsed'; ?>" 
                                                    type="button" 
                                                    data-bs-toggle="collapse" 
                                                    data-bs-target="#collapse<?php echo ucfirst($category); ?>">
                                                <?php
                                                $icon = match($category) {
                                                    'smtp' => 'fa-envelope',
                                                    'app' => 'fa-gear',
                                                    'notification' => 'fa-bell',
                                                    default => 'fa-circle'
                                                };
                                                ?>
                                                <i class="fas <?php echo $icon; ?> me-2"></i>
                                                <?php echo ucfirst($category); ?>
                                            </button>
                                        </h2>
                                        <div id="collapse<?php echo ucfirst($category); ?>" 
                                             class="accordion-collapse collapse <?php echo $category === 'smtp' ? 'show' : ''; ?>"
                                             data-bs-parent="#settingsAccordion">
                                            <div class="accordion-body">
                                                <?php foreach ($category_settings as $setting): ?>
                                                    <div class="mb-3">
                                                        <label for="<?php echo $setting['setting_key']; ?>" class="form-label">
                                                            <?php echo $setting['setting_description']; ?>
                                                        </label>
                                                        <?php if (strpos($setting['setting_key'], 'password') !== false): ?>
                                                            <input type="password" 
                                                                   class="form-control" 
                                                                   id="<?php echo $setting['setting_key']; ?>"
                                                                   name="settings[<?php echo $setting['setting_key']; ?>]"
                                                                   value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                                                   <?php echo $setting['is_public'] ? '' : 'readonly'; ?>>
                                                        <?php else: ?>
                                                            <input type="text" 
                                                                   class="form-control" 
                                                                   id="<?php echo $setting['setting_key']; ?>"
                                                                   name="settings[<?php echo $setting['setting_key']; ?>]"
                                                                   value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                                                   <?php echo $setting['is_public'] ? '' : 'readonly'; ?>>
                                                        <?php endif; ?>
                                                        <?php if (!$setting['is_public']): ?>
                                                            <small class="text-muted">
                                                                Esta configuración solo puede ser modificada directamente en la base de datos.
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="d-grid gap-2 mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Guardar Cambios
                                </button>
                                <a href="../dashboard/admin.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Volver al Dashboard
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