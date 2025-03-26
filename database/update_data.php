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
    
    // Crear categorías de precios por defecto para eventos existentes
    $sql = "
        INSERT IGNORE INTO pricing_categories (
            event_id, 
            name, 
            description, 
            price, 
            capacity, 
            is_active
        )
        SELECT 
            id as event_id,
            'General' as name,
            'Entrada general al evento' as description,
            0.00 as price,
            capacity,
            TRUE as is_active
        FROM events
        WHERE status = 'published'
    ";
    if (!$conn->query($sql)) {
        throw new Exception("Error al crear categorías de precios por defecto: " . $conn->error);
    }
    
    // Actualizar registros existentes con la categoría de precio por defecto
    $sql = "
        UPDATE registrations r
        JOIN events e ON r.event_id = e.id
        JOIN pricing_categories pc ON e.id = pc.event_id
        SET r.pricing_category_id = pc.id
        WHERE r.pricing_category_id IS NULL
        AND pc.name = 'General'
    ";
    if (!$conn->query($sql)) {
        throw new Exception("Error al actualizar registros existentes: " . $conn->error);
    }
    
    // Insertar métodos de pago por defecto
    $sql = "
        INSERT IGNORE INTO payment_methods (
            name, 
            description, 
            is_active
        ) VALUES 
        ('PayPal', 'Pago en línea a través de PayPal', TRUE),
        ('Stripe', 'Pago en línea a través de Stripe', TRUE),
        ('Transferencia Bancaria', 'Pago mediante transferencia bancaria', TRUE)
    ";
    if (!$conn->query($sql)) {
        throw new Exception("Error al insertar métodos de pago por defecto: " . $conn->error);
    }
    
    // Crear registros de pago para registros existentes
    $sql = "
        INSERT IGNORE INTO payments (
            registration_id,
            payment_method_id,
            amount,
            status,
            created_at
        )
        SELECT 
            r.id as registration_id,
            (SELECT id FROM payment_methods WHERE name = 'Transferencia Bancaria' LIMIT 1) as payment_method_id,
            0.00 as amount,
            CASE r.status
                WHEN 'confirmed' THEN 'completed'
                WHEN 'cancelled' THEN 'failed'
                ELSE 'pending'
            END as status,
            r.created_at
        FROM registrations r
        LEFT JOIN payments p ON r.id = p.registration_id
        WHERE p.id IS NULL
    ";
    if (!$conn->query($sql)) {
        throw new Exception("Error al crear registros de pago: " . $conn->error);
    }
    
    // Cerrar conexión
    $conn->close();
    
    echo json_encode([
        "success" => true,
        "message" => "Datos actualizados exitosamente"
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
} 