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
    
    // Leer y ejecutar archivos SQL
    $sql_files = [
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
    
    // Cerrar conexión
    $conn->close();
    
    echo json_encode([
        "success" => true,
        "message" => "Base de datos actualizada exitosamente"
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
} 