-- Agregar las columnas faltantes a la tabla events si no existen
ALTER TABLE events
ADD COLUMN IF NOT EXISTS capacity INT DEFAULT 0 AFTER modality,
ADD COLUMN IF NOT EXISTS price DECIMAL(10,2) DEFAULT 0.00 AFTER capacity,
ADD COLUMN IF NOT EXISTS status ENUM('draft', 'published', 'cancelled') NOT NULL DEFAULT 'draft' AFTER price,
ADD COLUMN IF NOT EXISTS category_id INT AFTER status,
ADD COLUMN IF NOT EXISTS organizer_id INT AFTER category_id,
ADD FOREIGN KEY IF NOT EXISTS (category_id) REFERENCES categories(id),
ADD FOREIGN KEY IF NOT EXISTS (organizer_id) REFERENCES users(id); 