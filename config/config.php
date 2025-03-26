<?php
// Configuración de la aplicación
define('BASE_URL', 'http://localhost/EventManager/');
define('APP_NAME', 'Event Manager');

// Configuración de sesión
session_start();

// Configuración de zona horaria
date_default_timezone_set('America/Mexico_City');

// Configuración de roles
define('ROLE_ADMIN', 1);
define('ROLE_ORGANIZER', 2);
define('ROLE_ATTENDEE', 3);
define('ROLE_SPONSOR', 4);

// Configuración de tipos de eventos
define('EVENT_TYPE_ACADEMIC', 1);
define('EVENT_TYPE_EXECUTIVE', 2);
define('EVENT_TYPE_SOCIAL', 3);
define('EVENT_TYPE_CULTURAL', 4);
define('EVENT_TYPE_SPORTS', 5);

// Configuración de modalidades de evento
define('EVENT_MODE_PRESENTIAL', 1);
define('EVENT_MODE_VIRTUAL', 2);
define('EVENT_MODE_HYBRID', 3);

// Funciones de utilidad
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function hasRole($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] == $role;
}

function redirect($path) {
    header("Location: " . BASE_URL . $path);
    exit();
}

// Manejo de errores
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    $error = "Error [$errno] $errstr\nLine $errline in $errfile";
    error_log($error);
    
    if (ini_get('display_errors')) {
        echo "<p style='color: red;'>Un error ha ocurrido. Por favor, contacte al administrador.</p>";
    }
}

set_error_handler("customErrorHandler");
?> 