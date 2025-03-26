<?php
$page_title = "Inicio";

// Obtener eventos destacados
require_once 'models/Event.php';
$event = new Event($db);
$featured_events = $event->read()->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Hero Section -->
<section class="hero bg-primary text-white py-5 mb-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h1 class="display-4 fw-bold mb-4">Gestiona tus eventos de manera eficiente</h1>
                <p class="lead mb-4">Organiza, promociona y administra eventos de todo tipo con nuestra plataforma integral.</p>
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <div class="d-grid gap-2 d-md-flex">
                        <a href="<?php echo BASE_URL; ?>register" class="btn btn-light btn-lg px-4 me-md-2">Registrarse</a>
                        <a href="<?php echo BASE_URL; ?>login" class="btn btn-outline-light btn-lg px-4">Iniciar Sesión</a>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <img src="<?php echo BASE_URL; ?>assets/images/hero-image.svg" alt="Event Management" class="img-fluid">
            </div>
        </div>
    </div>
</section>

<!-- Eventos Destacados -->
<section class="featured-events mb-5">
    <div class="container">
        <h2 class="text-center mb-4">Eventos Destacados</h2>
        
        <div class="row">
            <?php foreach ($featured_events as $event): ?>
                <div class="col-md-4 mb-4">
                    <div class="card event-card h-100">
                        <img src="<?php echo BASE_URL; ?>assets/images/events/<?php echo $event['id']; ?>.jpg" 
                             class="card-img-top" alt="<?php echo htmlspecialchars($event['title']); ?>"
                             onerror="this.src='<?php echo BASE_URL; ?>assets/images/event-placeholder.jpg'">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($event['title']); ?></h5>
                            <p class="card-text text-muted">
                                <i class="fas fa-calendar-alt me-2"></i>
                                <?php echo date('d/m/Y', strtotime($event['start_date'])); ?>
                            </p>
                            <p class="card-text"><?php echo substr(htmlspecialchars($event['description']), 0, 100); ?>...</p>
                            <a href="<?php echo BASE_URL; ?>events/view.php?id=<?php echo $event['id']; ?>" 
                               class="btn btn-primary">Ver Detalles</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="text-center mt-4">
            <a href="<?php echo BASE_URL; ?>events" class="btn btn-outline-primary">Ver Todos los Eventos</a>
        </div>
    </div>
</section>

<!-- Características -->
<section class="features bg-light py-5 mb-5">
    <div class="container">
        <h2 class="text-center mb-5">¿Por qué elegirnos?</h2>
        
        <div class="row g-4">
            <div class="col-md-4">
                <div class="text-center">
                    <i class="fas fa-calendar-check fa-3x text-primary mb-3"></i>
                    <h3>Gestión Completa</h3>
                    <p>Administra todos los aspectos de tus eventos desde una única plataforma intuitiva.</p>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="text-center">
                    <i class="fas fa-users fa-3x text-primary mb-3"></i>
                    <h3>Roles Personalizados</h3>
                    <p>Define roles y permisos específicos para cada miembro de tu equipo.</p>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="text-center">
                    <i class="fas fa-chart-line fa-3x text-primary mb-3"></i>
                    <h3>Análisis Detallado</h3>
                    <p>Obtén insights valiosos con nuestras herramientas de análisis y reportes.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Tipos de Eventos -->
<section class="event-types mb-5">
    <div class="container">
        <h2 class="text-center mb-5">Tipos de Eventos</h2>
        
        <div class="row g-4">
            <div class="col-md-4 col-lg-2">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <i class="fas fa-graduation-cap fa-2x text-primary mb-3"></i>
                        <h5 class="card-title">Académicos</h5>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 col-lg-2">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <i class="fas fa-briefcase fa-2x text-primary mb-3"></i>
                        <h5 class="card-title">Ejecutivos</h5>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 col-lg-2">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <i class="fas fa-users fa-2x text-primary mb-3"></i>
                        <h5 class="card-title">Sociales</h5>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 col-lg-2">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <i class="fas fa-theater-masks fa-2x text-primary mb-3"></i>
                        <h5 class="card-title">Culturales</h5>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 col-lg-2">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <i class="fas fa-futbol fa-2x text-primary mb-3"></i>
                        <h5 class="card-title">Deportivos</h5>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 col-lg-2">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <i class="fas fa-plus fa-2x text-primary mb-3"></i>
                        <h5 class="card-title">Y más...</h5>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Call to Action -->
<section class="cta bg-primary text-white py-5">
    <div class="container text-center">
        <h2 class="mb-4">¿Listo para comenzar?</h2>
        <p class="lead mb-4">Únete a nuestra comunidad y comienza a gestionar tus eventos de manera profesional.</p>
        <?php if (!isset($_SESSION['user_id'])): ?>
            <a href="<?php echo BASE_URL; ?>register" class="btn btn-light btn-lg">Crear Cuenta Gratis</a>
        <?php else: ?>
            <a href="<?php echo BASE_URL; ?>events/create" class="btn btn-light btn-lg">Crear Nuevo Evento</a>
        <?php endif; ?>
    </div>
</section> 