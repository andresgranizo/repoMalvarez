<?php
$isLoggedIn = isset($_SESSION['user']);
$userRole = $isLoggedIn ? $_SESSION['user']['role'] : null;
$userName = $isLoggedIn ? $_SESSION['user']['name'] : null;
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="/EventManager/index.php">EventManager</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="/EventManager/views/events/index.php">Eventos</a>
                </li>
                <?php if($isLoggedIn): ?>
                    <?php if($userRole === 'admin' || $userRole === 'organizer'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/EventManager/views/events/create.php">Crear Evento</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/EventManager/views/events/manage.php">Gestionar Eventos</a>
                        </li>
                    <?php endif; ?>
                    <?php if($userRole === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/EventManager/views/admin/dashboard.php">Panel Admin</a>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav">
                <?php if($isLoggedIn): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($userName); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="/EventManager/views/profile/index.php">Mi Perfil</a></li>
                            <li><a class="dropdown-item" href="/EventManager/views/registrations/my-registrations.php">Mis Inscripciones</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/EventManager/views/auth/logout.php">Cerrar Sesión</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/EventManager/views/auth/login.php">Iniciar Sesión</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/EventManager/views/auth/register.php">Registrarse</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav> 