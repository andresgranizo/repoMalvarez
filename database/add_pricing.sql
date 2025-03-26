-- Insertar categorías de precios por defecto para eventos existentes
INSERT IGNORE INTO pricing_categories (event_id, name, description, price, capacity, is_active)
SELECT 
    id as event_id,
    'General' as name,
    'Entrada general al evento' as description,
    0.00 as price,
    capacity,
    TRUE as is_active
FROM events
WHERE status = 'published'; 