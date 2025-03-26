<?php
header('Content-Type: application/json');

try {
    // Incluir archivo de configuración
    require_once __DIR__ . '/../includes/config.php';
    
    // Crear conexión
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Verificar conexión
    if ($conn->connect_error) {
        throw new Exception("Error de conexión: " . $conn->connect_error);
    }
    
    // Crear tabla pricing_categories si no existe
    $sql = "
        CREATE TABLE IF NOT EXISTS pricing_categories (
            id INT PRIMARY KEY AUTO_INCREMENT,
            event_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            description TEXT,
            capacity INT,
            is_active BOOLEAN DEFAULT true,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (event_id) REFERENCES events(id)
        )
    ";
    if (!$conn->query($sql)) {
        throw new Exception("Error al crear tabla pricing_categories: " . $conn->error);
    }
    
    // Crear tabla payment_methods si no existe
    $sql = "
        CREATE TABLE IF NOT EXISTS payment_methods (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            is_active BOOLEAN DEFAULT true,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ";
    if (!$conn->query($sql)) {
        throw new Exception("Error al crear tabla payment_methods: " . $conn->error);
    }

    // Crear tabla payments si no existe
    $sql = "
        CREATE TABLE IF NOT EXISTS payments (
            id INT PRIMARY KEY AUTO_INCREMENT,
            registration_id INT NOT NULL,
            payment_method_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
            transaction_id VARCHAR(255),
            payment_date TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (registration_id) REFERENCES registrations(id),
            FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id)
        )
    ";
    if (!$conn->query($sql)) {
        throw new Exception("Error al crear tabla payments: " . $conn->error);
    }
    
    // Eliminar la clave foránea existente si existe
    $sql = "
        ALTER TABLE registrations 
        DROP FOREIGN KEY IF EXISTS registrations_ibfk_3
    ";
    if (!$conn->query($sql)) {
        throw new Exception("Error al eliminar la clave foránea: " . $conn->error);
    }

    // Eliminar el índice si existe
    $sql = "
        ALTER TABLE registrations 
        DROP INDEX IF EXISTS category_id
    ";
    if (!$conn->query($sql)) {
        throw new Exception("Error al eliminar el índice: " . $conn->error);
    }

    // Renombrar la columna category_id a pricing_category_id si existe
    $sql = "
        SELECT COUNT(*) as count
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = '" . DB_NAME . "'
        AND TABLE_NAME = 'registrations'
        AND COLUMN_NAME = 'category_id'
    ";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        $sql = "
            ALTER TABLE registrations 
            CHANGE COLUMN category_id pricing_category_id INT
        ";
        if (!$conn->query($sql)) {
            throw new Exception("Error al renombrar la columna: " . $conn->error);
        }
    }

    // Agregar la nueva clave foránea
    $sql = "
        ALTER TABLE registrations 
        ADD FOREIGN KEY (pricing_category_id) REFERENCES pricing_categories(id)
    ";
    if (!$conn->query($sql)) {
        throw new Exception("Error al agregar la nueva clave foránea: " . $conn->error);
    }
    
    // Cerrar conexión
    $conn->close();
    
    echo json_encode([
        "success" => true,
        "message" => "Tablas actualizadas exitosamente"
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
} 