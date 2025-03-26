<?php
session_start();

require_once '../../includes/AuthMiddleware.php';

// Verificar permisos
if(!AuthMiddleware::hasPermission('manage_events')) {
    header('Location: ../events/index.php');
    exit;
}

// Verificar que se proporcionó un ID de evento
if(!isset($_GET['event_id'])) {
    header('Location: ../events/manage.php');
    exit;
}

// Obtener detalles del evento
$ch = curl_init("http://localhost/EventManager/api/events.php?id=" . $_GET['event_id']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
if(!$result['success']) {
    header('Location: ../events/manage.php');
    exit;
}

$event = $result['data'];

// Verificar que el usuario es el organizador o admin
if(!AuthMiddleware::isAdmin() && $event['organizer_id'] != $_SESSION['user_id']) {
    header('Location: ../events/manage.php');
    exit;
}

// Obtener inscripciones del evento
$ch = curl_init("http://localhost/EventManager/api/registrations.php?event_id=" . $_GET['event_id']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
$registrations = $result['data'] ?? [];

// Agrupar inscripciones por estado
$registrationsByStatus = [
    'pending' => [],
    'confirmed' => [],
    'cancelled' => [],
    'attended' => []
];

foreach($registrations as $registration) {
    $registrationsByStatus[$registration['status']][] = $registration;
}

// Calcular estadísticas
$totalRegistrations = count($registrations);
$confirmedRegistrations = count($registrationsByStatus['confirmed']);
$attendedRegistrations = count($registrationsByStatus['attended']);
$cancelledRegistrations = count($registrationsByStatus['cancelled']);
$pendingRegistrations = count($registrationsByStatus['pending']);

// Calcular ingresos totales
$totalRevenue = 0;
foreach($registrations as $registration) {
    if(in_array($registration['status'], ['confirmed', 'attended'])) {
        $totalRevenue += $registration['amount_paid'];
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Inscripciones - <?php echo htmlspecialchars($event['title']); ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 5px 10px;
            border-radius: 15px;
        }
        .status-pending {
            background-color: #ffc107;
            color: #000;
        }
        .status-confirmed {
            background-color: #198754;
            color: white;
        }
        .status-cancelled {
            background-color: #dc3545;
            color: white;
        }
        .status-attended {
            background-color: #0dcaf0;
            color: white;
        }
        .qr-code {
            max-width: 150px;
            margin: 10px auto;
        }
        .registration-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .registration-card:hover {
            transform: translateY(-5px);
        }
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
                        <a class="nav-link" href="../events/manage.php">Gestionar Eventos</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <!-- Event Header -->
        <div class="row mb-4">
            <div class="col-md-8">
                <h2><?php echo htmlspecialchars($event['title']); ?></h2>
                <p class="text-muted">
                    <i class="fas fa-calendar-alt"></i>
                    <?php 
                        $start = new DateTime($event['start_date']);
                        echo $start->format('d/m/Y H:i');
                    ?>
                </p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="../events/view.php?id=<?php echo $event['id']; ?>" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left"></i> Volver al Evento
                </a>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="h5 mb-0"><?php echo $totalRegistrations; ?></h3>
                            <small class="text-muted">Total Inscripciones</small>
                        </div>
                        <i class="fas fa-users fa-2x text-primary"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="h5 mb-0"><?php echo $confirmedRegistrations; ?></h3>
                            <small class="text-muted">Confirmadas</small>
                        </div>
                        <i class="fas fa-check-circle fa-2x text-success"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="h5 mb-0"><?php echo $attendedRegistrations; ?></h3>
                            <small class="text-muted">Asistieron</small>
                        </div>
                        <i class="fas fa-star fa-2x text-info"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="h5 mb-0"><?php echo number_format($totalRevenue, 2); ?> €</h3>
                            <small class="text-muted">Ingresos Totales</small>
                        </div>
                        <i class="fas fa-euro-sign fa-2x text-warning"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Registrations Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="registrationsTable">
                        <thead>
                            <tr>
                                <th>Participante</th>
                                <th>Categoría</th>
                                <th>Fecha Registro</th>
                                <th>Estado</th>
                                <th>Monto Pagado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($registrations as $registration): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div>
                                                <div class="fw-bold"><?php echo htmlspecialchars($registration['user_name']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($registration['user_email']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($registration['category_name']); ?></td>
                                    <td>
                                        <?php 
                                            $regDate = new DateTime($registration['created_at']);
                                            echo $regDate->format('d/m/Y H:i');
                                        ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $registration['status']; ?>">
                                            <?php echo ucfirst($registration['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo number_format($registration['amount_paid'], 2); ?> €</td>
                                    <td>
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm btn-outline-primary view-qr" 
                                                data-id="<?php echo $registration['id']; ?>"
                                                data-qr="<?php echo htmlspecialchars($registration['qr_code']); ?>"
                                                title="Ver QR">
                                                <i class="fas fa-qrcode"></i>
                                            </button>
                                            <?php if($registration['status'] === 'pending'): ?>
                                                <button type="button" class="btn btn-sm btn-outline-success confirm-registration"
                                                    data-id="<?php echo $registration['id']; ?>" title="Confirmar">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if(in_array($registration['status'], ['confirmed', 'pending'])): ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger cancel-registration"
                                                    data-id="<?php echo $registration['id']; ?>" title="Cancelar">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if($registration['status'] === 'confirmed'): ?>
                                                <button type="button" class="btn btn-sm btn-outline-info mark-attended"
                                                    data-id="<?php echo $registration['id']; ?>" title="Marcar Asistencia">
                                                    <i class="fas fa-user-check"></i>
                                                </button>
                                            <?php endif; ?>
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

    <!-- QR Code Modal -->
    <div class="modal fade" id="qrModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Código QR de Inscripción</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="qrImage" src="" alt="QR Code" class="qr-code">
                    <p class="mt-3">Este código QR puede ser utilizado para verificar la inscripción en la entrada del evento.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <a href="#" id="downloadQr" class="btn btn-primary" download="qr-code.png">
                        <i class="fas fa-download"></i> Descargar
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar Acción</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="confirmationMessage"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="confirmAction">Confirmar</button>
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
                    <p>Tu plataforma integral para la gestión de eventos</p>
                </div>
                <div class="col-md-3">
                    <h5>Enlaces</h5>
                    <ul class="list-unstyled">
                        <li><a href="../events/index.php" class="text-light">Eventos</a></li>
                        <li><a href="#" class="text-light">Acerca de</a></li>
                        <li><a href="#" class="text-light">Contacto</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h5>Síguenos</h5>
                    <div class="social-links">
                        <a href="#" class="text-light me-2"><i class="fab fa-facebook"></i></a>
                        <a href="#" class="text-light me-2"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-light me-2"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-light"><i class="fab fa-linkedin"></i></a>
                    </div>
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
    
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            $('#registrationsTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json'
                },
                order: [[2, 'desc']] // Ordenar por fecha de registro descendente
            });

            // QR Code Modal
            const qrModal = new bootstrap.Modal(document.getElementById('qrModal'));
            $('.view-qr').click(function() {
                const qrCode = $(this).data('qr');
                $('#qrImage').attr('src', qrCode);
                $('#downloadQr').attr('href', qrCode);
                qrModal.show();
            });

            // Confirmation Modal
            const confirmationModal = new bootstrap.Modal(document.getElementById('confirmationModal'));
            let actionCallback = null;

            function showConfirmation(message, callback) {
                $('#confirmationMessage').text(message);
                actionCallback = callback;
                confirmationModal.show();
            }

            $('#confirmAction').click(function() {
                if(actionCallback) {
                    actionCallback();
                    confirmationModal.hide();
                }
            });

            // Handle registration actions
            $('.confirm-registration').click(function() {
                const registrationId = $(this).data('id');
                showConfirmation('¿Estás seguro de que deseas confirmar esta inscripción?', function() {
                    updateRegistrationStatus(registrationId, 'confirmed');
                });
            });

            $('.cancel-registration').click(function() {
                const registrationId = $(this).data('id');
                showConfirmation('¿Estás seguro de que deseas cancelar esta inscripción?', function() {
                    updateRegistrationStatus(registrationId, 'cancelled');
                });
            });

            $('.mark-attended').click(function() {
                const registrationId = $(this).data('id');
                showConfirmation('¿Confirmas que este participante ha asistido al evento?', function() {
                    updateRegistrationStatus(registrationId, 'attended');
                });
            });

            function updateRegistrationStatus(registrationId, status) {
                fetch(`http://localhost/EventManager/api/registrations.php?id=${registrationId}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ status: status })
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        location.reload();
                    } else {
                        alert(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Ha ocurrido un error al actualizar la inscripción');
                });
            }
        });
    </script>
</body>
</html> 