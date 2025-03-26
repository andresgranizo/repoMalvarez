<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="/EventManager">EventManager</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>" 
                       href="/EventManager">Inicio</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'list.php' ? 'active' : ''; ?>" 
                       href="/EventManager/views/events/list.php">Eventos</a>
                </li>
                <?php if (isset($_SESSION['user'])): ?>
                    <?php if ($_SESSION['user']['role'] === 'organizer'): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?php echo $current_dir === 'events' || $current_dir === 'pricing' || $current_dir === 'payments' ? 'active' : ''; ?>" 
                               href="#" id="organizerDropdown" role="button" 
                               data-bs-toggle="dropdown" aria-expanded="false">
                                Gestión de Eventos
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="organizerDropdown">
                                <li>
                                    <a class="dropdown-item" href="/EventManager/views/events/manage.php">
                                        <i class="fas fa-list"></i> Mis Eventos
                                    </a>
                                </li>
                                <?php if (isset($_GET['event_id'])): ?>
                                <li>
                                    <a class="dropdown-item" href="/EventManager/views/pricing/manage.php?event_id=<?php echo htmlspecialchars($_GET['event_id']); ?>">
                                        <i class="fas fa-tags"></i> Gestionar Precios
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="/EventManager/views/payments/manage.php?event_id=<?php echo htmlspecialchars($_GET['event_id']); ?>">
                                        <i class="fas fa-money-bill"></i> Gestionar Pagos
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav">
                <?php if (isset($_SESSION['user'])): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" 
                           data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['user']['name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li>
                                <a class="dropdown-item" href="/EventManager/views/profile/view.php">
                                    <i class="fas fa-id-card"></i> Mi Perfil
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="/EventManager/views/registrations/my.php">
                                    <i class="fas fa-ticket-alt"></i> Mis Registros
                                </a>
                            </li>
                            <?php if ($_SESSION['user']['role'] === 'admin'): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item" href="/EventManager/views/admin/dashboard.php">
                                        <i class="fas fa-cogs"></i> Panel de Admin
                                    </a>
                                </li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="/EventManager/views/auth/logout.php">
                                    <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page === 'login.php' ? 'active' : ''; ?>" 
                           href="/EventManager/views/auth/login.php">
                            <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page === 'register.php' ? 'active' : ''; ?>" 
                           href="/EventManager/views/auth/register.php">
                            <i class="fas fa-user-plus"></i> Registrarse
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav> 