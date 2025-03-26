-- Crear la base de datos
CREATE DATABASE IF NOT EXISTS eventmanager;
USE eventmanager;

-- Tabla de usuarios
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'organizer', 'admin') DEFAULT 'user',
    is_active BOOLEAN DEFAULT TRUE,
    avatar_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla de tipos de eventos
CREATE TABLE IF NOT EXISTS event_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de eventos
CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    type_id INT,
    organizer_id INT,
    modality ENUM('presential', 'virtual', 'hybrid') NOT NULL,
    location VARCHAR(255),
    virtual_link VARCHAR(255),
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    max_capacity INT,
    status ENUM('draft', 'published', 'completed', 'cancelled') DEFAULT 'draft',
    logo_url VARCHAR(255),
    banner_url VARCHAR(255),
    header_color VARCHAR(7),
    accent_color VARCHAR(7),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (type_id) REFERENCES event_types(id),
    FOREIGN KEY (organizer_id) REFERENCES users(id)
);

-- Tabla de categorías de participantes
CREATE TABLE IF NOT EXISTS participant_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    capacity INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
);

-- Tabla de inscripciones
CREATE TABLE IF NOT EXISTS registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    category_id INT NOT NULL,
    registration_code VARCHAR(50) UNIQUE NOT NULL,
    qr_code VARCHAR(255),
    status ENUM('pending', 'confirmed', 'cancelled', 'attended') DEFAULT 'pending',
    payment_status ENUM('pending', 'completed', 'refunded') DEFAULT 'pending',
    payment_amount DECIMAL(10,2) NOT NULL,
    payment_date DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (category_id) REFERENCES participant_categories(id)
);

-- Tabla de historial de acceso
CREATE TABLE IF NOT EXISTS login_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Tabla de configuración del sistema
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_description TEXT,
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insertar tipos de eventos predeterminados
INSERT INTO event_types (name, description) VALUES
('Conferencia', 'Eventos de presentación y discusión de temas específicos'),
('Taller', 'Sesiones prácticas de aprendizaje'),
('Seminario', 'Reuniones especializadas de naturaleza técnica o académica'),
('Congreso', 'Reuniones periódicas de profesionales del mismo campo'),
('Exposición', 'Muestras y exhibiciones de productos o servicios');

-- Insertar usuarios predeterminados
INSERT INTO users (name, email, password, role, is_active) VALUES
('Administrador', 'admin@eventmanager.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', TRUE),
('Organizador Demo', 'organizador@eventmanager.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'organizer', TRUE),
('Usuario Demo', 'usuario@eventmanager.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', TRUE);

-- Insertar configuraciones predeterminadas
INSERT INTO settings (setting_key, setting_value, setting_description, is_public) VALUES
('site_name', 'EventManager', 'Nombre del sitio web', TRUE),
('site_description', 'Plataforma integral para la gestión de eventos', 'Descripción del sitio web', TRUE),
('contact_email', 'contact@eventmanager.com', 'Email de contacto principal', TRUE),
('smtp_host', 'smtp.gmail.com', 'Servidor SMTP para envío de correos', FALSE),
('smtp_port', '587', 'Puerto del servidor SMTP', FALSE),
('smtp_user', 'your-email@gmail.com', 'Usuario SMTP', FALSE),
('smtp_password', 'your-password', 'Contraseña SMTP', FALSE),
('currency', 'EUR', 'Moneda predeterminada', TRUE),
('timezone', 'Europe/Madrid', 'Zona horaria del sistema', TRUE),
('max_registration_limit', '1000', 'Límite máximo de registros por evento', FALSE),
('enable_waitlist', 'true', 'Habilitar lista de espera cuando se alcanza el límite', TRUE);

-- Crear evento de ejemplo
INSERT INTO events (
    title, 
    description, 
    type_id, 
    organizer_id, 
    modality, 
    location, 
    start_date, 
    end_date, 
    max_capacity, 
    status
) VALUES (
    'Conferencia de Tecnología 2024',
    'Gran conferencia sobre las últimas tendencias en tecnología',
    1,
    2,
    'hybrid',
    'Centro de Convenciones Madrid',
    '2024-06-15 09:00:00',
    '2024-06-16 18:00:00',
    500,
    'published'
);

-- Crear categorías para el evento de ejemplo
INSERT INTO participant_categories (event_id, name, description, price, capacity) VALUES
(1, 'General', 'Acceso a todas las conferencias', 99.99, 300),
(1, 'VIP', 'Acceso preferencial y materiales exclusivos', 199.99, 100),
(1, 'Estudiante', 'Tarifa especial para estudiantes', 49.99, 100);

-- Crear índices para optimizar las consultas
CREATE INDEX idx_events_status ON events(status);
CREATE INDEX idx_events_start_date ON events(start_date);
CREATE INDEX idx_registrations_status ON registrations(status);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_email ON users(email);

-- Crear vistas para reportes comunes
CREATE VIEW view_event_stats AS
SELECT 
    e.id,
    e.title,
    e.status,
    COUNT(DISTINCT r.id) as total_registrations,
    SUM(r.payment_amount) as total_revenue,
    e.max_capacity,
    COUNT(DISTINCT CASE WHEN r.status = 'confirmed' THEN r.id END) as confirmed_registrations,
    COUNT(DISTINCT CASE WHEN r.status = 'attended' THEN r.id END) as total_attendees
FROM events e
LEFT JOIN registrations r ON e.id = r.event_id
GROUP BY e.id;

CREATE VIEW view_organizer_stats AS
SELECT 
    u.id as organizer_id,
    u.name as organizer_name,
    COUNT(DISTINCT e.id) as total_events,
    SUM(r.payment_amount) as total_revenue,
    COUNT(DISTINCT r.id) as total_registrations
FROM users u
LEFT JOIN events e ON u.id = e.organizer_id
LEFT JOIN registrations r ON e.id = r.event_id
WHERE u.role = 'organizer'
GROUP BY u.id; 