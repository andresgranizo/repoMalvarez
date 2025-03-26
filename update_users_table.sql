-- Agregar la columna status si no existe
ALTER TABLE users
ADD COLUMN IF NOT EXISTS status ENUM('active', 'inactive') NOT NULL DEFAULT 'active' AFTER role; 