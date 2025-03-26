-- Crear la tabla categories solo si no existe
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insertar categorías solo si no existen
INSERT IGNORE INTO categories (name, description) VALUES
('Conferencia', 'Eventos de presentación y discusión de temas específicos'),
('Taller', 'Sesiones prácticas y de aprendizaje'),
('Seminario', 'Reuniones especializadas de naturaleza técnica o académica'),
('Networking', 'Eventos para establecer contactos profesionales'),
('Cultural', 'Eventos relacionados con arte, música y cultura'); 