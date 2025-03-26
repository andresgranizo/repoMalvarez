-- Tabla de métodos de pago
CREATE TABLE IF NOT EXISTS payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type ENUM('online', 'transfer') NOT NULL,
    provider VARCHAR(100),
    config JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de pagos
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    registration_id INT NOT NULL,
    payment_method_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    transaction_id VARCHAR(100),
    payment_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Eliminar la tabla pricing_categories si existe
DROP TABLE IF EXISTS pricing_categories;

-- Crear la tabla pricing_categories con la estructura correcta
CREATE TABLE pricing_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    discount_percentage DECIMAL(5,2) DEFAULT 0.00,
    ieee_member_discount DECIMAL(5,2) DEFAULT 0.00,
    ieee_region VARCHAR(10) DEFAULT NULL,
    max_capacity INT DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Agregar campo de categoría de precio a la tabla de registros
ALTER TABLE registrations 
DROP FOREIGN KEY IF EXISTS registrations_ibfk_4,
ADD COLUMN IF NOT EXISTS pricing_category_id INT,
ADD CONSTRAINT registrations_ibfk_4 FOREIGN KEY (pricing_category_id) REFERENCES pricing_categories(id);

-- Insertar métodos de pago por defecto
INSERT IGNORE INTO payment_methods (name, type, provider, config) VALUES
('PayPal', 'online', 'paypal', '{"client_id": "", "client_secret": ""}'),
('Stripe', 'online', 'stripe', '{"publishable_key": "", "secret_key": ""}'),
('Transferencia Bancaria', 'transfer', NULL, '{"bank_name": "Banco Nacional", "account_number": "123456789", "account_holder": "EventManager Inc."}');

-- Índices para optimizar consultas
CREATE INDEX idx_payments_status ON payments(status);
CREATE INDEX idx_payments_created_at ON payments(created_at);
CREATE INDEX idx_pricing_categories_event ON pricing_categories(event_id);
CREATE INDEX idx_pricing_categories_dates ON pricing_categories(created_at);

-- Actualizar la tabla payment_methods
ALTER TABLE payment_methods
    ADD COLUMN IF NOT EXISTS type ENUM('online', 'transfer') NOT NULL DEFAULT 'online',
    ADD COLUMN IF NOT EXISTS provider VARCHAR(100) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS config JSON DEFAULT NULL;

-- Agregar campo de configuración de pagos a la tabla de eventos
ALTER TABLE events
    ADD COLUMN IF NOT EXISTS payment_config JSON DEFAULT NULL;

-- Actualizar los métodos de pago existentes con la información correcta
UPDATE payment_methods 
SET type = 'online', 
    provider = 'paypal', 
    config = '{"client_id": "", "client_secret": ""}' 
WHERE name = 'PayPal';

UPDATE payment_methods 
SET type = 'online', 
    provider = 'stripe', 
    config = '{"publishable_key": "", "secret_key": ""}' 
WHERE name = 'Stripe';

UPDATE payment_methods 
SET type = 'transfer', 
    provider = 'bank', 
    config = '{"bank_name": "Banco Nacional", "account_number": "123456789", "account_holder": "EventManager Inc."}' 
WHERE name = 'Transferencia Bancaria'; 