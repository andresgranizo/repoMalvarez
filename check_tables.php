<?php
require_once 'includes/Database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Obtener todas las tablas
    $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    echo "Tablas existentes:\n";
    foreach ($tables as $table) {
        echo "- $table\n";

        // Mostrar estructura de cada tabla
        $columns = $conn->query("SHOW COLUMNS FROM $table")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $column) {
            echo "  * {$column['Field']} ({$column['Type']})\n";
        }
        echo "\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
