<?php
require_once '../../includes/auth.php';

// Destruir la sesión
session_start();
session_destroy();

// Redirigir al inicio
header('Location: /EventManager');
exit;
?> 