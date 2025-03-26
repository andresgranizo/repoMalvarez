<?php
require_once 'includes/Database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Leer y ejecutar el script SQL
    $sql = file_get_contents('add_categories.sql');
    $conn->exec($sql);
    
    echo "¡Categorías importadas exitosamente!";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
} 