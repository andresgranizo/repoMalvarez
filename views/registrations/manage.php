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
$registrations = [];
$events = [];

try {
    $db = new Database();
    $conn = $db->getConnection();
    $user_id = $_SESSION['user']['id'];

    // Obtener eventos del organizador para el filtro
    $stmt = $conn->prepare("
        SELECT id, title 
        FROM events 
        WHERE organizer_id = ?
        ORDER BY start_date DESC
    ");
    $stmt->execute([$user_id]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Filtros
    $event_id = $_GET['event_id'] ?? '';
    $status = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';

    // Construir la consulta base
    $query = "
        SELECT 
            r.id,
            r.registration_code,
            r.status,
            r.created_at,
            pc.price,
            e.title as event_title,
            u.name as user_name,
            u.email as user_email,
            pc.name as category_name,
            p.transaction_id,
            p.status as payment_status
        FROM registrations r
        INNER JOIN events e ON r.event_id = e.id
        INNER JOIN users u ON r.user_id = u.id
        INNER JOIN pricing_categories pc ON r.pricing_category_id = pc.id
        LEFT JOIN payments p ON r.id = p.registration_id
        WHERE e.organizer_id = :organizer_id
    ";
    $params = ['organizer_id' => $user_id];

    // Aplicar filtros
    if ($event_id !== '') {
        $query .= " AND e.id = :event_id";
        $params['event_id'] = $event_id;
    }
    if ($status !== '') {
        $query .= " AND r.status = :status";
        $params['status'] = $status;
    }
    if ($search !== '') {
        $query .= " AND (u.name LIKE :search OR u.email LIKE :search OR e.title LIKE :search)";
        $search_term = "%$search%";
        $params['search'] = $search_term;
    }

    $query .= " ORDER BY r.created_at DESC";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Procesar acciones
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $registration_id = $_POST['registration_id'] ?? '';
        $action = $_POST['action'] ?? '';

        if ($registration_id && $action) {
            // Verificar que el registro pertenece a un evento del organizador
            $stmt = $conn->prepare("
                SELECT r.id 
                FROM registrations r
                JOIN events e ON r.event_id = e.id
                WHERE r.id = :registration_id AND e.organizer_id = :organizer_id
            ");
            $stmt->execute(['registration_id' => $registration_id, 'organizer_id' => $user_id]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception('No tienes permiso para modificar este registro');
            }

            // Actualizar el estado del registro
            $stmt = $conn->prepare("
                UPDATE registrations 
                SET status = :action, updated_at = NOW()
                WHERE id = :registration_id
            ");
            $stmt->execute(['registration_id' => $registration_id, 'action' => $action]);

            $success = "El registro ha sido actualizado exitosamente";
            
            // Recargar la página para mostrar los cambios
            header("Location: " . $_SERVER['PHP_SELF'] . "?" . http_build_query($_GET));
            exit;
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
    <title>Gestionar Registros - EventManager</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
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
                        <a class="nav-link active" href="manage.php">
                            <i class="fas fa-users"></i> Registros
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../reports/organizer.php">
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">
                <i class="fas fa-users"></i> Gestionar Registros
            </h1>
            <a href="../dashboard/organizer.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver al Dashboard
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

        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="event_id" class="form-label">Evento</label>
                        <select class="form-select" id="event_id" name="event_id">
                            <option value="">Todos los eventos</option>
                            <?php foreach ($events as $event): ?>
                                <option value="<?php echo $event['id']; ?>" <?php echo ($event_id == $event['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($event['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">Estado</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">Todos los estados</option>
                            <option value="pending" <?php echo ($status === 'pending') ? 'selected' : ''; ?>>Pendiente</option>
                            <option value="confirmed" <?php echo ($status === 'confirmed') ? 'selected' : ''; ?>>Confirmado</option>
                            <option value="cancelled" <?php echo ($status === 'cancelled') ? 'selected' : ''; ?>>Cancelado</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="search" class="form-label">Buscar</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Nombre, email o evento">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla de Registros -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="registrationsTable">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Participante</th>
                                <th>Categoría</th>
                                <th>Precio</th>
                                <th>Estado</th>
                                <th>Fecha</th>
                                <th>Comprobante</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registrations as $reg): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($reg['registration_code']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($reg['user_name']); ?><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($reg['user_email']); ?></small>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($reg['category_name']); ?><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($reg['event_title']); ?></small>
                                    </td>
                                    <td>$<?php echo number_format($reg['price'], 2); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $reg['status'] === 'confirmed' ? 'success' : 
                                                ($reg['status'] === 'cancelled' ? 'danger' : 'warning'); 
                                        ?>">
                                            <?php echo ucfirst($reg['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo Utilities::formatDate($reg['created_at']); ?></td>
                                    <td>
                                        <?php if ($reg['transaction_id']): ?>
                                            <a href="/EventManager/uploads/receipts/<?php echo htmlspecialchars($reg['transaction_id']); ?>" target="_blank" class="btn btn-sm btn-info">
                                                <i class="fas fa-file-alt"></i> Ver Comprobante
                                            </a>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Pendiente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($reg['status'] === 'pending'): ?>
                                            <div class="btn-group">
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="registration_id" value="<?php echo $reg['id']; ?>">
                                                    <input type="hidden" name="action" value="confirmed">
                                                    <button type="submit" class="btn btn-sm btn-success" title="Confirmar">
                                                        <i class="fas fa-check"></i> Confirmar
                                                    </button>
                                                </form>
                                                <form method="POST" class="d-inline ms-1">
                                                    <input type="hidden" name="registration_id" value="<?php echo $reg['id']; ?>">
                                                    <input type="hidden" name="action" value="cancelled">
                                                    <button type="submit" class="btn btn-sm btn-danger" title="Cancelar">
                                                        <i class="fas fa-times"></i> Cancelar
                                                    </button>
                                                </form>
                                            </div>
                                        <?php elseif ($reg['status'] === 'cancelled'): ?>
                                            <span class="text-muted">Inscripción cancelada</span>
                                        <?php else: ?>
                                            <span class="text-success">Inscripción confirmada</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#registrationsTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json'
                },
                order: [[5, 'desc']], // Ordenar por fecha de creación
                pageLength: 25,
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip'
            });
        });
    </script>
</body>
</html> 