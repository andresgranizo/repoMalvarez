-- Tabla de roles
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de permisos
CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de relación roles-permisos
CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar roles
INSERT IGNORE INTO roles (name, description) VALUES
('admin', 'Administrador del sistema'),
('organizer', 'Organizador de eventos'),
('user', 'Usuario regular');

-- Insertar permisos
INSERT IGNORE INTO permissions (name, description) VALUES
-- Permisos de eventos
('manage_events', 'Gestionar eventos'),
('view_events', 'Ver eventos'),
('create_event', 'Crear eventos'),
('edit_event', 'Editar eventos'),
('delete_event', 'Eliminar eventos'),
('publish_event', 'Publicar eventos'),

-- Permisos de usuarios
('manage_users', 'Gestionar usuarios'),
('view_users', 'Ver usuarios'),
('create_user', 'Crear usuarios'),
('edit_user', 'Editar usuarios'),
('delete_user', 'Eliminar usuarios'),

-- Permisos de categorías
('manage_categories', 'Gestionar categorías'),
('view_categories', 'Ver categorías'),
('create_category', 'Crear categorías'),
('edit_category', 'Editar categorías'),
('delete_category', 'Eliminar categorías'),

-- Permisos de registros
('manage_registrations', 'Gestionar registros'),
('view_registrations', 'Ver registros'),
('create_registration', 'Crear registros'),
('edit_registration', 'Editar registros'),
('delete_registration', 'Eliminar registros'),

-- Permisos de pagos
('manage_payments', 'Gestionar pagos'),
('view_payments', 'Ver pagos'),
('process_payments', 'Procesar pagos'),
('refund_payments', 'Reembolsar pagos'),

-- Permisos de métodos de pago
('manage_payment_methods', 'Gestionar métodos de pago'),
('view_payment_methods', 'Ver métodos de pago'),
('create_payment_method', 'Crear métodos de pago'),
('edit_payment_method', 'Editar métodos de pago'),
('delete_payment_method', 'Eliminar métodos de pago'),

-- Permisos de categorías de precios
('manage_pricing', 'Gestionar precios'),
('view_pricing', 'Ver precios'),
('create_pricing', 'Crear precios'),
('edit_pricing', 'Editar precios'),
('delete_pricing', 'Eliminar precios'),

-- Permisos de reportes
('manage_reports', 'Gestionar reportes'),
('view_reports', 'Ver reportes'),
('export_reports', 'Exportar reportes');

-- Asignar permisos a roles
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE 
    -- Administrador tiene todos los permisos
    r.name = 'admin'
    OR
    -- Organizador tiene permisos específicos
    (r.name = 'organizer' AND p.name IN (
        'view_events', 'create_event', 'edit_event', 'delete_event', 'publish_event',
        'view_users',
        'view_categories',
        'manage_registrations', 'view_registrations', 'edit_registration',
        'manage_payments', 'view_payments', 'process_payments',
        'view_payment_methods',
        'manage_pricing', 'view_pricing', 'create_pricing', 'edit_pricing', 'delete_pricing',
        'view_reports', 'export_reports'
    ))
    OR
    -- Usuario regular tiene permisos básicos
    (r.name = 'user' AND p.name IN (
        'view_events',
        'view_categories',
        'create_registration', 'view_registrations'
    )); 