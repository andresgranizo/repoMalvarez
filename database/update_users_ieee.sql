-- Agregar campos para miembros IEEE
ALTER TABLE users
ADD COLUMN IF NOT EXISTS ieee_member BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS ieee_member_id VARCHAR(50) DEFAULT NULL;

-- Actualizar usuarios existentes
UPDATE users SET ieee_member = FALSE WHERE ieee_member IS NULL; 