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
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar Inscripciones - <?php echo htmlspecialchars($event['title']); ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        #qr-reader {
            width: 100%;
            max-width: 640px;
            margin: 0 auto;
        }
        #qr-reader__scan_region {
            position: relative;
            background: #000;
            border-radius: 10px;
            overflow: hidden;
        }
        #qr-reader__scan_region video {
            width: 100%;
            height: auto;
        }
        .scan-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border: 2px solid #fff;
            border-radius: 10px;
            pointer-events: none;
        }
        .scan-line {
            position: absolute;
            width: 100%;
            height: 2px;
            background: #0d6efd;
            animation: scan 2s linear infinite;
        }
        @keyframes scan {
            0% { top: 0; }
            50% { top: 100%; }
            100% { top: 0; }
        }
        .result-card {
            display: none;
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .result-success {
            border-left: 4px solid #198754;
        }
        .result-error {
            border-left: 4px solid #dc3545;
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 5px 10px;
            border-radius: 15px;
        }
        .status-confirmed {
            background-color: #198754;
            color: white;
        }
        .status-attended {
            background-color: #0dcaf0;
            color: white;
        }
        .status-cancelled {
            background-color: #dc3545;
            color: white;
        }
        .manual-input {
            display: none;
            margin-top: 20px;
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
                <a href="list.php?event_id=<?php echo $event['id']; ?>" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left"></i> Volver a Inscripciones
                </a>
            </div>
        </div>

        <!-- Scanner Section -->
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title text-center mb-4">Escanear Código QR</h4>
                        
                        <!-- Scanner Controls -->
                        <div class="text-center mb-4">
                            <button id="startButton" class="btn btn-primary">
                                <i class="fas fa-camera"></i> Iniciar Cámara
                            </button>
                            <button id="stopButton" class="btn btn-secondary" style="display: none;">
                                <i class="fas fa-stop"></i> Detener Cámara
                            </button>
                            <button id="toggleInput" class="btn btn-outline-primary ms-2">
                                <i class="fas fa-keyboard"></i> Entrada Manual
                            </button>
                        </div>

                        <!-- QR Scanner -->
                        <div id="qr-reader">
                            <div id="qr-reader__scan_region">
                                <div class="scan-overlay">
                                    <div class="scan-line"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Manual Input -->
                        <div class="manual-input">
                            <div class="input-group">
                                <input type="text" id="manualCode" class="form-control" 
                                    placeholder="Ingrese el código manualmente">
                                <button class="btn btn-primary" id="verifyManual">Verificar</button>
                            </div>
                        </div>

                        <!-- Result Card -->
                        <div id="resultCard" class="result-card">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h5 class="mb-1" id="participantName"></h5>
                                    <p class="text-muted mb-0" id="participantEmail"></p>
                                </div>
                                <span class="status-badge" id="registrationStatus"></span>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <small class="text-muted d-block">Categoría</small>
                                    <span id="categoryName"></span>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted d-block">Fecha de Registro</small>
                                    <span id="registrationDate"></span>
                                </div>
                            </div>
                            <div class="mt-3" id="actionButtons">
                                <!-- Los botones de acción se agregarán dinámicamente -->
                            </div>
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
    <!-- HTML5 QR Code Scanner -->
    <script src="https://unpkg.com/html5-qrcode"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const html5QrCode = new Html5Qrcode("qr-reader");
            let isScanning = false;

            // Toggle manual input
            document.getElementById('toggleInput').addEventListener('click', function() {
                const manualInput = document.querySelector('.manual-input');
                const qrReader = document.getElementById('qr-reader');
                
                if(manualInput.style.display === 'none') {
                    manualInput.style.display = 'block';
                    qrReader.style.display = 'none';
                    if(isScanning) {
                        stopScanning();
                    }
                } else {
                    manualInput.style.display = 'none';
                    qrReader.style.display = 'block';
                }
            });

            // Start scanning
            document.getElementById('startButton').addEventListener('click', function() {
                Html5Qrcode.getCameras().then(devices => {
                    if (devices && devices.length) {
                        html5QrCode.start(
                            { facingMode: "environment" },
                            {
                                fps: 10,
                                qrbox: { width: 250, height: 250 }
                            },
                            onScanSuccess,
                            onScanFailure
                        ).then(() => {
                            isScanning = true;
                            document.getElementById('startButton').style.display = 'none';
                            document.getElementById('stopButton').style.display = 'inline-block';
                        });
                    }
                });
            });

            // Stop scanning
            document.getElementById('stopButton').addEventListener('click', stopScanning);

            function stopScanning() {
                if(isScanning) {
                    html5QrCode.stop().then(() => {
                        isScanning = false;
                        document.getElementById('startButton').style.display = 'inline-block';
                        document.getElementById('stopButton').style.display = 'none';
                    });
                }
            }

            // Manual verification
            document.getElementById('verifyManual').addEventListener('click', function() {
                const code = document.getElementById('manualCode').value;
                if(code) {
                    verifyRegistration(code);
                }
            });

            // Handle successful scan
            function onScanSuccess(decodedText) {
                verifyRegistration(decodedText);
            }

            // Handle scan failure
            function onScanFailure(error) {
                console.warn(`Código QR no detectado: ${error}`);
            }

            // Verify registration
            function verifyRegistration(code) {
                fetch(`http://localhost/EventManager/api/registrations.php?qr_code=${encodeURIComponent(code)}`)
                    .then(response => response.json())
                    .then(data => {
                        if(data.success) {
                            showResult(data.data);
                        } else {
                            showError(data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showError('Ha ocurrido un error al verificar la inscripción');
                    });
            }

            // Show verification result
            function showResult(registration) {
                const resultCard = document.getElementById('resultCard');
                resultCard.className = 'result-card result-success';
                resultCard.style.display = 'block';

                // Update registration info
                document.getElementById('participantName').textContent = registration.user_name;
                document.getElementById('participantEmail').textContent = registration.user_email;
                document.getElementById('categoryName').textContent = registration.category_name;
                
                const regDate = new Date(registration.created_at);
                document.getElementById('registrationDate').textContent = regDate.toLocaleDateString('es-ES') + 
                    ' ' + regDate.toLocaleTimeString('es-ES', {hour: '2-digit', minute:'2-digit'});

                // Update status badge
                const statusBadge = document.getElementById('registrationStatus');
                statusBadge.className = 'status-badge status-' + registration.status;
                statusBadge.textContent = registration.status.charAt(0).toUpperCase() + registration.status.slice(1);

                // Update action buttons
                const actionButtons = document.getElementById('actionButtons');
                actionButtons.innerHTML = '';

                if(registration.status === 'confirmed') {
                    actionButtons.innerHTML = `
                        <button type="button" class="btn btn-success" 
                            onclick="updateStatus('${registration.id}', 'attended')">
                            <i class="fas fa-check"></i> Marcar Asistencia
                        </button>
                    `;
                } else if(registration.status === 'attended') {
                    actionButtons.innerHTML = `
                        <div class="alert alert-success mb-0">
                            <i class="fas fa-check-circle"></i> Asistencia registrada
                        </div>
                    `;
                } else {
                    actionButtons.innerHTML = `
                        <div class="alert alert-warning mb-0">
                            <i class="fas fa-exclamation-triangle"></i> 
                            Esta inscripción no está confirmada
                        </div>
                    `;
                }
            }

            // Show error message
            function showError(message) {
                const resultCard = document.getElementById('resultCard');
                resultCard.className = 'result-card result-error';
                resultCard.style.display = 'block';
                resultCard.innerHTML = `
                    <div class="alert alert-danger mb-0">
                        <i class="fas fa-exclamation-circle"></i> ${message}
                    </div>
                `;
            }

            // Update registration status
            window.updateStatus = function(registrationId, status) {
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
                        showResult(data.data);
                    } else {
                        showError(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError('Ha ocurrido un error al actualizar el estado');
                });
            };
        });
    </script>
</body>
</html> 