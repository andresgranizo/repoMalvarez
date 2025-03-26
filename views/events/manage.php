<?php
session_start();

// Verificar si el usuario está autenticado y tiene permisos
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] !== 'admin' && $_SESSION['user']['role'] !== 'organizer')) {
    header('Location: ../auth/login.php');
    exit;
}

require_once '../../includes/Database.php';
require_once '../../includes/utilities.php';

$success = $_GET['success'] ?? null;
$error = null;

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Verificar si existe la tabla categories
    $stmt = $conn->query("SHOW TABLES LIKE 'categories'");
    if ($stmt->rowCount() == 0) {
        throw new Exception("La tabla 'categories' no existe. Por favor, ejecute el script de base de datos.");
    }

    // Verificar si existe la tabla events
    $stmt = $conn->query("SHOW TABLES LIKE 'events'");
    if ($stmt->rowCount() == 0) {
        throw new Exception("La tabla 'events' no existe. Por favor, ejecute el script de base de datos.");
    }

    // Construir la consulta base
    $sql = "
        SELECT e.*, u.name as organizer_name, c.name as category_name,
               COUNT(r.id) as total_registrations
        FROM events e
        LEFT JOIN users u ON e.organizer_id = u.id
        LEFT JOIN categories c ON e.category_id = c.id
        LEFT JOIN registrations r ON e.id = r.event_id
    ";

    // Filtrar por organizador si no es admin
    if ($_SESSION['user']['role'] !== 'admin') {
        $sql .= " WHERE e.organizer_id = :organizer_id";
        $params = [':organizer_id' => $_SESSION['user']['id']];
    }

    $sql .= " GROUP BY e.id ORDER BY e.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    if (isset($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
    $events = []; // Inicializar como array vacío en caso de error
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Eventos - EventManager</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        .status-badge {
            width: 100px;
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
                        <a class="nav-link active" href="manage.php">
                            <i class="fas fa-tasks"></i> Gestionar Eventos
                        </a>
                    </li>
                    <?php if ($_SESSION['user']['role'] === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="../users/manage.php">
                            <i class="fas fa-users"></i> Gestionar Usuarios
                        </a>
                    </li>
                    <?php endif; ?>
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
            <h1 class="h2">Gestionar Eventos</h1>
            <a href="form.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Crear Evento
            </a>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($events) && !$error): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No hay eventos disponibles.
                <a href="form.php" class="alert-link">¡Crea tu primer evento!</a>
            </div>
        <?php elseif (!$error): ?>
            <div class="table-responsive">
                <table class="table table-hover" id="eventsTable">
                    <thead>
                        <tr>
                            <th>Título</th>
                            <th>Categoría</th>
                            <th>Organizador</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th>Registros</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $event): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($event['title']); ?></td>
                                <td><?php echo htmlspecialchars($event['category_name']); ?></td>
                                <td><?php echo htmlspecialchars($event['organizer_name']); ?></td>
                                <td>
                                    <?php 
                                        echo Utilities::formatDate($event['start_date']) . ' - ' . 
                                             Utilities::formatDate($event['end_date']); 
                                    ?>
                                </td>
                                <td>
                                    <span class="badge status-badge bg-<?php 
                                        echo $event['status'] === 'published' ? 'success' : 
                                            ($event['status'] === 'draft' ? 'warning' : 'danger'); 
                                    ?>">
                                        <?php echo ucfirst($event['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="registrations.php?event_id=<?php echo $event['id']; ?>" 
                                       class="text-decoration-none">
                                        <?php echo $event['total_registrations']; ?> 
                                        <i class="fas fa-users text-muted"></i>
                                    </a>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="view.php?id=<?php echo $event['id']; ?>" 
                                           class="btn btn-sm btn-info text-white" 
                                           title="Ver detalles">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="form.php?id=<?php echo $event['id']; ?>" 
                                           class="btn btn-sm btn-warning text-white"
                                           title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="pricing.php?event_id=<?php echo $event['id']; ?>" 
                                           class="btn btn-sm btn-success text-white"
                                           title="Gestionar Precios">
                                            <i class="fas fa-tags"></i>
                                        </a>
                                        <button type="button" 
                                                class="btn btn-sm btn-danger delete-event" 
                                                data-id="<?php echo $event['id']; ?>"
                                                data-title="<?php echo htmlspecialchars($event['title']); ?>"
                                                title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal de Confirmación de Eliminación -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar Eliminación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Estás seguro de que deseas eliminar el evento "<span id="eventTitle"></span>"?</p>
                    <p class="text-danger">Esta acción no se puede deshacer.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form id="deleteForm" method="POST" style="display: inline;">
                        <input type="hidden" name="event_id" id="eventId">
                        <button type="submit" class="btn btn-danger">Eliminar</button>
                    </form>
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
            // Inicializar DataTable
            $('#eventsTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json'
                }
            });

            // Manejo del modal de eliminación
            $('.delete-event').click(function() {
                const id = $(this).data('id');
                const title = $(this).data('title');
                
                $('#eventId').val(id);
                $('#eventTitle').text(title);
                $('#deleteModal').modal('show');
            });

            // Manejo del formulario de eliminación
            $('#deleteForm').submit(function(e) {
                e.preventDefault();
                const eventId = $('#eventId').val();
                const baseUrl = window.location.pathname.toLowerCase().includes('eventmanagerv2') 
                    ? '/eventmanagerv2' 
                    : '/EventManager';
                
                console.log('Intentando eliminar evento:', {
                    eventId: eventId,
                    baseUrl: baseUrl
                });
                
                $.ajax({
                    url: `${baseUrl}/api/events.php`,
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        _method: 'DELETE',
                        id: eventId
                    }),
                    success: function(response) {
                        console.log('Respuesta del servidor:', response);
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Error al eliminar el evento: ' + response.message);
                            $('#deleteModal').modal('hide');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error en la solicitud:', {
                            status: xhr.status,
                            statusText: xhr.statusText,
                            error: error,
                            response: xhr.responseText,
                            headers: xhr.getAllResponseHeaders()
                        });
                        
                        let errorMessage = 'Error al procesar la solicitud';
                        try {
                            const response = JSON.parse(xhr.responseText);
                            errorMessage = response.message || errorMessage;
                        } catch (e) {
                            console.error('Error al parsear la respuesta:', e);
                        }
                        
                        alert(errorMessage);
                        $('#deleteModal').modal('hide');
                    }
                });
            });

            // Auto-ocultar alertas después de 5 segundos
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
        });
    </script>
</body>
</html> 