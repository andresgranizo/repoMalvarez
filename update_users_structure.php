<?php
require_once 'includes/Database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Verificar si la columna status existe
    $stmt = $conn->query("SHOW COLUMNS FROM users LIKE 'status'");
    if ($stmt->rowCount() == 0) {
        // Si la columna no existe, agregarla
        $sql = file_get_contents('update_users_table.sql');
        $conn->exec($sql);
        echo "¡Estructura de la tabla users actualizada exitosamente!";
    } else {
        echo "La estructura ya está actualizada.";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
} 