<?php
session_start();

require_once '../../includes/AuthMiddleware.php';

// Verificar que es administrador
if(!AuthMiddleware::isAdmin()) {
    header('Location: ../events/index.php');
    exit;
}

// Obtener usuarios
$ch = curl_init("http://localhost/EventManager/api/users.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$users = json_decode($response, true)['data'] ?? [];

// Obtener estadísticas de usuarios
$ch = curl_init("http://localhost/EventManager/api/stats.php?type=users");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$stats = json_decode($response, true)['data'] ?? [];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - EventManager</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .stats-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
        }
        .bg-purple { background-color: #6f42c1; }
        .bg-cyan { background-color: #0dcaf0; }
        .bg-pink { background-color: #d63384; }
        .bg-orange { background-color: #fd7e14; }
        .role-badge {
            font-size: 0.8rem;
            padding: 5px 10px;
            border-radius: 15px;
        }
        .role-admin { background-color: #dc3545; color: white; }
        .role-organizer { background-color: #198754; color: white; }
        .role-user { background-color: #0dcaf0; color: white; }
        .status-active { color: #198754; }
        .status-inactive { color: #dc3545; }
    </style>
</head>
<body class="bg-light">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="../../index.php">EventManager</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../events/index.php">Eventos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">Usuarios</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../settings/index.php">Configuración</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <h2>Gestión de Usuarios</h2>
                <p class="text-muted">Administra los usuarios del sistema</p>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon bg-purple text-white">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 class="h5 mb-0"><?php echo $stats['total_users'] ?? 0; ?></h3>
                    <small class="text-muted">Total Usuarios</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon bg-cyan text-white">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <h3 class="h5 mb-0"><?php echo $stats['total_organizers'] ?? 0; ?></h3>
                    <small class="text-muted">Organizadores</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon bg-pink text-white">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <h3 class="h5 mb-0"><?php echo $stats['total_admins'] ?? 0; ?></h3>
                    <small class="text-muted">Administradores</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon bg-orange text-white">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <h3 class="h5 mb-0"><?php echo $stats['active_users'] ?? 0; ?></h3>
                    <small class="text-muted">Usuarios Activos</small>
                </div>
            </div>
        </div>

        <!-- Users Table -->
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="card-title mb-0">Lista de Usuarios</h4>
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-primary" id="exportCSV">
                            <i class="fas fa-download"></i> Exportar CSV
                        </button>
                    </div>
                </div>

                <!-- Role Filter -->
                <div class="mb-4">
                    <div class="btn-group" role="group" aria-label="Filtrar por rol">
                        <button type="button" class="btn btn-outline-secondary active" data-role="all">
                            Todos
                        </button>
                        <button type="button" class="btn btn-outline-secondary" data-role="user">
                            Usuarios
                        </button>
                        <button type="button" class="btn btn-outline-secondary" data-role="organizer">
                            Organizadores
                        </button>
                        <button type="button" class="btn btn-outline-secondary" data-role="admin">
                            Administradores
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover" id="usersTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Email</th>
                                <th>Rol</th>
                                <th>Estado</th>
                                <th>Fecha Registro</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="<?php echo $user['avatar_url'] ?? '../../assets/img/default-avatar.png'; ?>" 
                                                 class="rounded-circle me-2" 
                                                 width="32" height="32" 
                                                 alt="Avatar">
                                            <?php echo htmlspecialchars($user['name']); ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="role-badge role-<?php echo $user['role']; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                            <i class="fas fa-circle"></i>
                                            <?php echo $user['is_active'] ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                            $date = new DateTime($user['created_at']);
                                            echo $date->format('d/m/Y H:i');
                                        ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-primary edit-user"
                                                    data-id="<?php echo $user['id']; ?>"
                                                    title="Editar usuario">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-<?php echo $user['is_active'] ? 'danger' : 'success'; ?> toggle-status"
                                                    data-id="<?php echo $user['id']; ?>"
                                                    data-status="<?php echo $user['is_active']; ?>"
                                                    title="<?php echo $user['is_active'] ? 'Desactivar' : 'Activar'; ?> usuario">
                                                <i class="fas fa-<?php echo $user['is_active'] ? 'ban' : 'check'; ?>"></i>
                                            </button>
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-info view-activity"
                                                    data-id="<?php echo $user['id']; ?>"
                                                    title="Ver actividad">
                                                <i class="fas fa-history"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editUserForm">
                        <input type="hidden" id="userId" name="id">
                        <div class="mb-3">
                            <label for="userName" class="form-label">Nombre</label>
                            <input type="text" class="form-control" id="userName" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="userEmail" class="form-label">Email</label>
                            <input type="email" class="form-control" id="userEmail" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="userRole" class="form-label">Rol</label>
                            <select class="form-select" id="userRole" name="role" required>
                                <option value="user">Usuario</option>
                                <option value="organizer">Organizador</option>
                                <option value="admin">Administrador</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="saveUserChanges">Guardar Cambios</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Activity Modal -->
    <div class="modal fade" id="activityModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Actividad del Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <ul class="nav nav-tabs mb-3">
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="tab" href="#eventsTab">Eventos</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#registrationsTab">Inscripciones</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#loginHistoryTab">Historial de Acceso</a>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="eventsTab">
                            <div id="userEvents"></div>
                        </div>
                        <div class="tab-pane fade" id="registrationsTab">
                            <div id="userRegistrations"></div>
                        </div>
                        <div class="tab-pane fade" id="loginHistoryTab">
                            <div id="userLoginHistory"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-light py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>EventManager</h5>
                    <p>Plataforma integral para la gestión de eventos</p>
                </div>
                <div class="col-md-3">
                    <h5>Enlaces</h5>
                    <ul class="list-unstyled">
                        <li><a href="../events/index.php" class="text-light">Eventos</a></li>
                        <li><a href="index.php" class="text-light">Usuarios</a></li>
                        <li><a href="../settings/index.php" class="text-light">Configuración</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h5>Soporte</h5>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-light">Documentación</a></li>
                        <li><a href="#" class="text-light">Centro de Ayuda</a></li>
                        <li><a href="#" class="text-light">Contacto</a></li>
                    </ul>
                </div>
            </div>
            <hr class="mt-4">
            <div class="text-center">
                <small>&copy; 2024 EventManager. Todos los derechos reservados.</small>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        $(document).ready(function() {
            // Inicializar DataTable
            const table = $('#usersTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json'
                },
                order: [[5, 'desc']]
            });

            // Filtrado por rol
            $('.btn-group[role="group"] button').click(function() {
                $('.btn-group[role="group"] button').removeClass('active');
                $(this).addClass('active');
                
                const role = $(this).data('role');
                if(role === 'all') {
                    table.column(3).search('').draw();
                } else {
                    table.column(3).search(role).draw();
                }
            });

            // Editar usuario
            $('.edit-user').click(function() {
                const userId = $(this).data('id');
                
                // Obtener datos del usuario
                $.get(`http://localhost/EventManager/api/users.php?id=${userId}`, function(response) {
                    const user = response.data;
                    $('#userId').val(user.id);
                    $('#userName').val(user.name);
                    $('#userEmail').val(user.email);
                    $('#userRole').val(user.role);
                    $('#editUserModal').modal('show');
                });
            });

            // Guardar cambios del usuario
            $('#saveUserChanges').click(function() {
                const userData = {
                    id: $('#userId').val(),
                    name: $('#userName').val(),
                    email: $('#userEmail').val(),
                    role: $('#userRole').val()
                };

                $.ajax({
                    url: 'http://localhost/EventManager/api/users.php',
                    method: 'PUT',
                    data: JSON.stringify(userData),
                    contentType: 'application/json',
                    success: function(response) {
                        $('#editUserModal').modal('hide');
                        Swal.fire({
                            title: '¡Éxito!',
                            text: 'Usuario actualizado correctamente',
                            icon: 'success'
                        }).then(() => {
                            location.reload();
                        });
                    },
                    error: function() {
                        Swal.fire({
                            title: 'Error',
                            text: 'No se pudo actualizar el usuario',
                            icon: 'error'
                        });
                    }
                });
            });

            // Cambiar estado del usuario
            $('.toggle-status').click(function() {
                const userId = $(this).data('id');
                const currentStatus = $(this).data('status');
                const newStatus = !currentStatus;

                Swal.fire({
                    title: '¿Estás seguro?',
                    text: `¿Deseas ${newStatus ? 'activar' : 'desactivar'} este usuario?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí',
                    cancelButtonText: 'No'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'http://localhost/EventManager/api/users.php',
                            method: 'PUT',
                            data: JSON.stringify({
                                id: userId,
                                is_active: newStatus
                            }),
                            contentType: 'application/json',
                            success: function() {
                                location.reload();
                            },
                            error: function() {
                                Swal.fire({
                                    title: 'Error',
                                    text: 'No se pudo actualizar el estado del usuario',
                                    icon: 'error'
                                });
                            }
                        });
                    }
                });
            });

            // Ver actividad del usuario
            $('.view-activity').click(function() {
                const userId = $(this).data('id');
                
                // Cargar eventos del usuario
                $.get(`http://localhost/EventManager/api/events.php?user_id=${userId}`, function(response) {
                    const events = response.data;
                    let eventsHtml = '<div class="list-group">';
                    
                    if(events.length > 0) {
                        events.forEach(event => {
                            eventsHtml += `
                                <div class="list-group-item">
                                    <h6 class="mb-1">${event.title}</h6>
                                    <p class="mb-1 text-muted">
                                        <small>
                                            <i class="fas fa-calendar"></i> ${new Date(event.start_date).toLocaleDateString()}
                                        </small>
                                    </p>
                                </div>
                            `;
                        });
                    } else {
                        eventsHtml += '<p class="text-muted">No hay eventos para mostrar</p>';
                    }
                    
                    eventsHtml += '</div>';
                    $('#userEvents').html(eventsHtml);
                });

                // Cargar inscripciones del usuario
                $.get(`http://localhost/EventManager/api/registrations.php?user_id=${userId}`, function(response) {
                    const registrations = response.data;
                    let registrationsHtml = '<div class="list-group">';
                    
                    if(registrations.length > 0) {
                        registrations.forEach(reg => {
                            registrationsHtml += `
                                <div class="list-group-item">
                                    <h6 class="mb-1">${reg.event_title}</h6>
                                    <p class="mb-1">
                                        <span class="badge bg-${reg.status === 'confirmed' ? 'success' : 'warning'}">
                                            ${reg.status}
                                        </span>
                                    </p>
                                    <small class="text-muted">
                                        ${new Date(reg.created_at).toLocaleDateString()}
                                    </small>
                                </div>
                            `;
                        });
                    } else {
                        registrationsHtml += '<p class="text-muted">No hay inscripciones para mostrar</p>';
                    }
                    
                    registrationsHtml += '</div>';
                    $('#userRegistrations').html(registrationsHtml);
                });

                // Cargar historial de acceso
                $.get(`http://localhost/EventManager/api/login_history.php?user_id=${userId}`, function(response) {
                    const history = response.data;
                    let historyHtml = '<div class="list-group">';
                    
                    if(history.length > 0) {
                        history.forEach(login => {
                            historyHtml += `
                                <div class="list-group-item">
                                    <p class="mb-1">
                                        <i class="fas fa-clock"></i>
                                        ${new Date(login.login_time).toLocaleString()}
                                    </p>
                                    <small class="text-muted">
                                        <i class="fas fa-map-marker-alt"></i>
                                        ${login.ip_address}
                                    </small>
                                </div>
                            `;
                        });
                    } else {
                        historyHtml += '<p class="text-muted">No hay historial de acceso para mostrar</p>';
                    }
                    
                    historyHtml += '</div>';
                    $('#userLoginHistory').html(historyHtml);
                });

                $('#activityModal').modal('show');
            });

            // Exportar a CSV
            $('#exportCSV').click(function() {
                const csvContent = [];
                const headers = ['ID', 'Nombre', 'Email', 'Rol', 'Estado', 'Fecha Registro'];
                csvContent.push(headers);

                table.rows().every(function() {
                    const data = this.data();
                    const row = [
                        data[0], // ID
                        $(data[1]).text().trim(), // Nombre
                        data[2], // Email
                        $(data[3]).text().trim(), // Rol
                        $(data[4]).text().trim(), // Estado
                        data[5] // Fecha
                    ];
                    csvContent.push(row);
                });

                const csvString = csvContent.map(row => row.join(',')).join('\n');
                const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = 'usuarios.csv';
                link.click();
            });
        });
    </script>
</body>
</html> 