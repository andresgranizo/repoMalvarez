<?php
header('Content-Type: application/json');

try {
    // Incluir archivo de configuración
    require_once '../includes/config.php';
    
    // Crear conexión
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
    
    // Verificar conexión
    if ($conn->connect_error) {
        throw new Exception("Error de conexión: " . $conn->connect_error);
    }
    
    // Crear base de datos si no existe
    $sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
    if (!$conn->query($sql)) {
        throw new Exception("Error al crear la base de datos: " . $conn->error);
    }
    
    // Seleccionar base de datos
    $conn->select_db(DB_NAME);
    
    // Leer y ejecutar archivos SQL
    $sql_files = [
        'create_tables.sql',
        'add_roles.sql',
        'add_categories.sql',
        'add_payments.sql',
        'add_pricing.sql'
    ];
    
    foreach ($sql_files as $file) {
        $sql = file_get_contents(__DIR__ . '/' . $file);
        
        // Dividir el archivo en consultas individuales
        $queries = array_filter(array_map('trim', explode(';', $sql)));
        
        foreach ($queries as $query) {
            if (!empty($query)) {
                if (!$conn->query($query)) {
                    throw new Exception("Error al ejecutar la consulta: " . $conn->error . "\nConsulta: " . $query);
                }
            }
        }
    }
    
    // Verificar si existe el usuario administrador
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = 'admin@eventmanager.com'");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Crear usuario administrador por defecto
        $password_hash = password_hash('password', PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("
            INSERT INTO users (name, email, password, role) 
            VALUES ('Administrador', 'admin@eventmanager.com', ?, 'admin')
        ");
        $stmt->bind_param("s", $password_hash);
        
        if (!$stmt->execute()) {
            throw new Exception("Error al crear el usuario administrador: " . $stmt->error);
        }
    }
    
    // Cerrar conexión
    $conn->close();
    
    echo json_encode([
        "success" => true,
        "message" => "Base de datos configurada exitosamente"
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
} 