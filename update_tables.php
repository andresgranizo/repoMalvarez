<?php
require_once 'includes/Database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Primero, verificar si la tabla events existe
    $tableExists = $conn->query("SHOW TABLES LIKE 'events'")->rowCount() > 0;
    
    if (!$tableExists) {
        // Si la tabla no existe, crearla con toda la estructura
        $sql = "CREATE TABLE events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(200) NOT NULL,
            description TEXT,
            start_date DATETIME NOT NULL,
            end_date DATETIME NOT NULL,
            location VARCHAR(200),
            modality ENUM('presencial', 'virtual', 'hibrido') NOT NULL,
            capacity INT DEFAULT 0,
            price DECIMAL(10,2) DEFAULT 0.00,
            status ENUM('draft', 'published', 'cancelled') NOT NULL DEFAULT 'draft',
            category_id INT,
            organizer_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES categories(id),
            FOREIGN KEY (organizer_id) REFERENCES users(id)
        )";
        $conn->exec($sql);
        echo "Tabla events creada exitosamente.<br>";
    } else {
        // Si la tabla existe, agregar las columnas faltantes
        $sql = file_get_contents('update_events_table.sql');
        $conn->exec($sql);
        echo "Tabla events actualizada exitosamente.<br>";
    }
    
    echo "¡Actualización completada!";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
} 