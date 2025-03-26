<?php
session_start();

// Verificar si el usuario está autenticado y es organizador
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'organizer') {
    header('Location: ../auth/login.php');
    exit;
}

require_once '../../includes/Database.php';
require_once '../../includes/utilities.php';

$error = null;
$success = null;
$events = [];

try {
    $db = new Database();
    $conn = $db->getConnection();
    $user_id = $_SESSION['user']['id'];

    // Obtener eventos del organizador para el filtro
    $stmt = $conn->query("
        SELECT id, title 
        FROM events 
        WHERE organizer_id = $user_id 
        ORDER BY created_at DESC
    ");
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Procesar la solicitud de exportación
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $report_type = $_POST['report_type'] ?? '';
        $event_id = $_POST['event_id'] ?? '';
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        $format = $_POST['format'] ?? 'pdf';

        // Validar fechas
        if (!empty($start_date) && !empty($end_date)) {
            if (strtotime($end_date) < strtotime($start_date)) {
                throw new Exception('La fecha final debe ser posterior a la fecha inicial');
            }
        }

        // Aquí se implementaría la lógica de exportación
        // Por ahora solo mostraremos un mensaje de éxito
        $success = "La exportación estará disponible próximamente. Formato seleccionado: " . strtoupper($format);
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
    <title>Exportar Reportes - EventManager</title>
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
                            <i class="fas fa-calendar"></i> Mis Eventos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../registrations/manage.php">
                            <i class="fas fa-users"></i> Registros
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="organizer.php">
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
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h1 class="h3">
                                <i class="fas fa-file-export"></i> Exportar Reportes
                            </h1>
                            <a href="organizer.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Volver a Reportes
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

                        <form method="POST" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="report_type" class="form-label">Tipo de Reporte</label>
                                <select class="form-select" id="report_type" name="report_type" required>
                                    <option value="">Seleccione un tipo de reporte</option>
                                    <option value="event_summary">Resumen de Evento</option>
                                    <option value="registrations">Lista de Registros</option>
                                    <option value="attendance">Lista de Asistencia</option>
                                    <option value="statistics">Estadísticas Generales</option>
                                </select>
                                <div class="invalid-feedback">
                                    Por favor seleccione un tipo de reporte
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="event_id" class="form-label">Evento</label>
                                <select class="form-select" id="event_id" name="event_id">
                                    <option value="">Todos los eventos</option>
                                    <?php foreach ($events as $event): ?>
                                        <option value="<?php echo $event['id']; ?>">
                                            <?php echo htmlspecialchars($event['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="start_date" class="form-label">Fecha Inicial</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date">
                                </div>
                                <div class="col-md-6">
                                    <label for="end_date" class="form-label">Fecha Final</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Formato de Salida</label>
                                <div class="btn-group w-100" role="group">
                                    <input type="radio" class="btn-check" name="format" id="pdf" value="pdf" checked>
                                    <label class="btn btn-outline-primary" for="pdf">
                                        <i class="fas fa-file-pdf"></i> PDF
                                    </label>
                                    
                                    <input type="radio" class="btn-check" name="format" id="excel" value="excel">
                                    <label class="btn btn-outline-success" for="excel">
                                        <i class="fas fa-file-excel"></i> Excel
                                    </label>
                                    
                                    <input type="radio" class="btn-check" name="format" id="csv" value="csv">
                                    <label class="btn btn-outline-secondary" for="csv">
                                        <i class="fas fa-file-csv"></i> CSV
                                    </label>
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-download"></i> Generar Reporte
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

        // Validación de fechas
        document.getElementById('end_date').addEventListener('change', function() {
            const startDate = document.getElementById('start_date').value;
            const endDate = this.value;
            
            if (startDate && endDate && new Date(endDate) < new Date(startDate)) {
                this.setCustomValidity('La fecha final debe ser posterior a la fecha inicial');
            } else {
                this.setCustomValidity('');
            }
        });

        // Mostrar/ocultar campos según el tipo de reporte
        document.getElementById('report_type').addEventListener('change', function() {
            const eventSelect = document.getElementById('event_id');
            const dateInputs = document.querySelectorAll('input[type="date"]');
            
            if (this.value === 'event_summary' || this.value === 'registrations' || this.value === 'attendance') {
                eventSelect.required = true;
                dateInputs.forEach(input => input.required = false);
            } else {
                eventSelect.required = false;
                dateInputs.forEach(input => input.required = true);
            }
        });
    </script>
</body>
</html> 