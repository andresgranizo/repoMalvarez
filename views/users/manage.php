<?php
session_start();

// Verificar si el usuario está autenticado y es administrador
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
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

    // Obtener todos los usuarios excepto el administrador actual
    $sql = "
        SELECT u.*, 
               COUNT(DISTINCT e.id) as total_events,
               COUNT(DISTINCT r.id) as total_registrations
        FROM users u
        LEFT JOIN events e ON u.id = e.organizer_id
        LEFT JOIN registrations r ON u.id = r.user_id
        WHERE u.id != :current_user_id
        GROUP BY u.id
        ORDER BY u.created_at DESC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([':current_user_id' => $_SESSION['user']['id']]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
    $users = [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Usuarios - EventManager</title>
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
                            <i class="fas fa-calendar"></i> Eventos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="manage.php">
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2">Gestionar Usuarios</h1>
            <a href="form.php" class="btn btn-primary">
                <i class="fas fa-user-plus"></i> Crear Usuario
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

        <?php if (empty($users) && !$error): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No hay usuarios registrados.
                <a href="form.php" class="alert-link">¡Crea el primer usuario!</a>
            </div>
        <?php elseif (!$error): ?>
            <div class="table-responsive">
                <table class="table table-hover" id="usersTable">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Eventos</th>
                            <th>Registros</th>
                            <th>Fecha Registro</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $user['role'] === 'admin' ? 'danger' : 
                                            ($user['role'] === 'organizer' ? 'success' : 'info'); 
                                    ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $user['status'] === 'active' ? 'success' : 'danger'; 
                                    ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user['role'] === 'organizer'): ?>
                                        <a href="../events/manage.php?organizer=<?php echo $user['id']; ?>" 
                                           class="text-decoration-none">
                                            <?php echo $user['total_events']; ?> 
                                            <i class="fas fa-calendar text-muted"></i>
                                        </a>
                                    <?php else: ?>
                                        <?php echo $user['total_events']; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $user['total_registrations']; ?> 
                                    <i class="fas fa-ticket-alt text-muted"></i>
                                </td>
                                <td><?php echo Utilities::formatDate($user['created_at']); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="form.php?id=<?php echo $user['id']; ?>" 
                                           class="btn btn-sm btn-warning text-white"
                                           title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" 
                                                class="btn btn-sm <?php echo $user['status'] === 'active' ? 'btn-danger' : 'btn-success'; ?> toggle-status"
                                                data-id="<?php echo $user['id']; ?>"
                                                data-status="<?php echo $user['status']; ?>"
                                                data-name="<?php echo htmlspecialchars($user['name']); ?>"
                                                title="<?php echo $user['status'] === 'active' ? 'Desactivar' : 'Activar'; ?>">
                                            <i class="fas <?php echo $user['status'] === 'active' ? 'fa-user-slash' : 'fa-user-check'; ?>"></i>
                                        </button>
                                        <button type="button" 
                                                class="btn btn-sm btn-danger delete-user"
                                                data-id="<?php echo $user['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($user['name']); ?>"
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

    <!-- Modal de Confirmación de Estado -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar Cambio de Estado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Estás seguro de que deseas <span id="actionText"></span> al usuario "<span id="userName"></span>"?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="confirmStatus">Confirmar</button>
                </div>
            </div>
        </div>
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
                    <p>¿Estás seguro de que deseas eliminar al usuario "<span id="deleteUserName"></span>"?</p>
                    <p class="text-danger"><strong>¡Atención!</strong> Esta acción no se puede deshacer.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">Eliminar</button>
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
            $('#usersTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json'
                }
            });

            // Variables para el modal
            let userId, userStatus, userName;

            // Manejo del modal de cambio de estado
            $('.toggle-status').click(function() {
                userId = $(this).data('id');
                userStatus = $(this).data('status');
                userName = $(this).data('name');
                
                $('#actionText').text(userStatus === 'active' ? 'desactivar' : 'activar');
                $('#userName').text(userName);
                $('#statusModal').modal('show');
            });

            // Manejo de la confirmación de cambio de estado
            $('#confirmStatus').click(function() {
                const newStatus = userStatus === 'active' ? 'inactive' : 'active';
                
                $.ajax({
                    url: '../../api/users.php',
                    method: 'PUT',
                    data: JSON.stringify({ 
                        id: userId,
                        status: newStatus
                    }),
                    contentType: 'application/json',
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Error al cambiar el estado: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Error al procesar la solicitud');
                    }
                });
            });

            // Manejo del modal de eliminación
            $('.delete-user').click(function() {
                userId = $(this).data('id');
                userName = $(this).data('name');
                
                $('#deleteUserName').text(userName);
                $('#deleteModal').modal('show');
            });

            // Manejo de la confirmación de eliminación
            $('#confirmDelete').click(function() {
                $.ajax({
                    url: '../../api/users.php?id=' + userId,
                    method: 'DELETE',
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Error al eliminar el usuario: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        let errorMessage = 'Error al procesar la solicitud';
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.message) {
                                errorMessage = response.message;
                            }
                        } catch (e) {
                            console.error('Error parsing response:', e);
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