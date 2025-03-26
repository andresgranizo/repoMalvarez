<?php
session_start();

// Configuración de la aplicación
require_once 'config/database.php';
require_once 'includes/AuthMiddleware.php';

// Verificar si hay una sesión activa
$isLoggedIn = isset($_SESSION['user']);
$userRole = $isLoggedIn ? $_SESSION['user']['role'] : null;
$userName = $isLoggedIn ? $_SESSION['user']['name'] : null;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EventManager - Sistema de Gestión de Eventos</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #e3f2fd; /* Azul claro */
        }
        .navbar {
            background-color: #343a40 !important; /* Mantener el gris oscuro */
        }
        .hero-section {
            background-color: #e3f2fd;
            padding: 4rem 0;
        }
        .logo {
            max-width: 300px;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-calendar-alt"></i> EventManager
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="views/events/index.php">
                            <i class="fas fa-calendar"></i> Eventos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="views/auth/login.php">
                            <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="views/auth/register.php">
                            <i class="fas fa-user-plus"></i> Registrarse
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container text-center">
            <img src="resources/LogoSeccion50Ecuador.png" alt="Logo Sección 50 Ecuador" class="logo">
            <h1 class="display-4 mb-4">Bienvenido a EventManager</h1>
            <p class="lead mb-4">Tu plataforma integral para la gestión de eventos</p>
            <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
                <a href="views/events/index.php" class="btn btn-primary btn-lg px-4 gap-3">
                    <i class="fas fa-calendar"></i> Ver Eventos
                </a>
                <a href="views/auth/register.php" class="btn btn-outline-primary btn-lg px-4">
                    <i class="fas fa-user-plus"></i> Crear Cuenta
                </a>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-calendar-check fa-3x text-primary mb-3"></i>
                            <h3 class="card-title">Gestión de Eventos</h3>
                            <p class="card-text">Crea y gestiona eventos de manera sencilla y eficiente.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-users fa-3x text-primary mb-3"></i>
                            <h3 class="card-title">Registro de Participantes</h3>
                            <p class="card-text">Controla el registro y asistencia de participantes.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-chart-bar fa-3x text-primary mb-3"></i>
                            <h3 class="card-title">Reportes y Estadísticas</h3>
                            <p class="card-text">Obtén insights valiosos sobre tus eventos.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-light py-4">
        <div class="container text-center">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> EventManager. Todos los derechos reservados.</p>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 