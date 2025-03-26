<?php
session_start();

// Verificar si el usuario está autenticado y tiene permisos
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] !== 'admin' && $_SESSION['user']['role'] !== 'organizer')) {
    header('Location: ../auth/login.php');
    exit;
}

require_once '../../includes/Database.php';
require_once '../../includes/utilities.php';

$event = null;
$error = null;
$success = null;

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Si es una edición, cargar los datos del evento
    if (isset($_GET['id'])) {
        $stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verificar permisos de edición
        if ($event && $_SESSION['user']['role'] !== 'admin' && $event['organizer_id'] !== $_SESSION['user']['id']) {
            header('Location: index.php');
            exit;
        }
    }

    // Obtener categorías para el select
    $stmt = $conn->query("SELECT * FROM categories ORDER BY name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Procesar el formulario
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $location = trim($_POST['location']);
        $modality = $_POST['modality'];
        $capacity = (int)$_POST['capacity'];
        $category_id = (int)$_POST['category_id'];
        $status = $_POST['status'];
        $price = (float)$_POST['price'];

        // Validaciones básicas
        if (empty($title) || empty($description) || empty($start_date) || empty($end_date)) {
            throw new Exception("Todos los campos marcados con * son obligatorios");
        }

        if (strtotime($end_date) < strtotime($start_date)) {
            throw new Exception("La fecha de fin no puede ser anterior a la fecha de inicio");
        }

        // Preparar datos para la base de datos
        $data = [
            'title' => $title,
            'description' => $description,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'location' => $location,
            'modality' => $modality,
            'capacity' => $capacity,
            'category_id' => $category_id,
            'status' => $status,
            'price' => $price,
            'organizer_id' => $_SESSION['user']['id']
        ];

        if (isset($_GET['id'])) {
            // Actualizar evento existente
            $sql = "UPDATE events SET 
                    title = :title, 
                    description = :description,
                    start_date = :start_date,
                    end_date = :end_date,
                    location = :location,
                    modality = :modality,
                    capacity = :capacity,
                    category_id = :category_id,
                    status = :status,
                    price = :price,
                    updated_at = NOW()
                    WHERE id = :id";
            $data['id'] = $_GET['id'];
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($data);
            $success = "¡Evento actualizado exitosamente!";
        } else {
            // Crear nuevo evento
            $sql = "INSERT INTO events (
                    title, description, start_date, end_date, 
                    location, modality, capacity, category_id,
                    status, price, organizer_id, created_at, updated_at
                ) VALUES (
                    :title, :description, :start_date, :end_date,
                    :location, :modality, :capacity, :category_id,
                    :status, :price, :organizer_id, NOW(), NOW()
                )";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($data);
            $success = "¡Evento creado exitosamente!";
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
    <title><?php echo isset($event) ? 'Editar' : 'Crear'; ?> Evento - EventManager</title>
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
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-list"></i> Ver Eventos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage.php">
                            <i class="fas fa-tasks"></i> Gestionar Eventos
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
            <div class="col-md-10">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-calendar-plus"></i>
                            <?php echo isset($event) ? 'Editar' : 'Crear'; ?> Evento
                        </h4>
                    </div>
                    <div class="card-body">
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
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="title" class="form-label required">Título</label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           value="<?php echo htmlspecialchars($event['title'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="category_id" class="form-label required">Categoría</label>
                                    <select class="form-select" id="category_id" name="category_id" required>
                                        <option value="">Seleccionar categoría</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>" 
                                                <?php echo ($event['category_id'] ?? '') == $category['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label required">Descripción</label>
                                <textarea class="form-control" id="description" name="description" rows="4" required><?php 
                                    echo htmlspecialchars($event['description'] ?? ''); 
                                ?></textarea>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="start_date" class="form-label required">Fecha de inicio</label>
                                    <input type="datetime-local" class="form-control" id="start_date" name="start_date" 
                                           value="<?php echo $event ? date('Y-m-d\TH:i', strtotime($event['start_date'])) : ''; ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="end_date" class="form-label required">Fecha de fin</label>
                                    <input type="datetime-local" class="form-control" id="end_date" name="end_date" 
                                           value="<?php echo $event ? date('Y-m-d\TH:i', strtotime($event['end_date'])) : ''; ?>" required>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="location" class="form-label">Ubicación</label>
                                    <input type="text" class="form-control" id="location" name="location" 
                                           value="<?php echo htmlspecialchars($event['location'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="modality" class="form-label required">Modalidad</label>
                                    <select class="form-select" id="modality" name="modality" required>
                                        <option value="presencial" <?php echo ($event['modality'] ?? '') === 'presencial' ? 'selected' : ''; ?>>Presencial</option>
                                        <option value="virtual" <?php echo ($event['modality'] ?? '') === 'virtual' ? 'selected' : ''; ?>>Virtual</option>
                                        <option value="hibrido" <?php echo ($event['modality'] ?? '') === 'hibrido' ? 'selected' : ''; ?>>Híbrido</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="capacity" class="form-label">Capacidad</label>
                                    <input type="number" class="form-control" id="capacity" name="capacity" min="0" 
                                           value="<?php echo htmlspecialchars($event['capacity'] ?? '0'); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="price" class="form-label">Precio</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" id="price" name="price" min="0" step="0.01" 
                                               value="<?php echo htmlspecialchars($event['price'] ?? '0.00'); ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label for="status" class="form-label required">Estado</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="draft" <?php echo ($event['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Borrador</option>
                                        <option value="published" <?php echo ($event['status'] ?? '') === 'published' ? 'selected' : ''; ?>>Publicado</option>
                                        <option value="cancelled" <?php echo ($event['status'] ?? '') === 'cancelled' ? 'selected' : ''; ?>>Cancelado</option>
                                    </select>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <a href="manage.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancelar
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> 
                                    <?php echo isset($event) ? 'Actualizar' : 'Crear'; ?> Evento
                                </button>
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
                }, false)
            })
        })()

        // Validación de fechas
        document.getElementById('end_date').addEventListener('change', function() {
            var startDate = new Date(document.getElementById('start_date').value);
            var endDate = new Date(this.value);
            
            if (endDate < startDate) {
                this.setCustomValidity('La fecha de fin no puede ser anterior a la fecha de inicio');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html> 