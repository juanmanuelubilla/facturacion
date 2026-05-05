-- Fix para aumentar el tamaño de la columna ip en la tabla camaras
-- Esto soluciona el error: Data too long for column 'ip'

ALTER TABLE camaras MODIFY COLUMN ip VARCHAR(45) NOT NULL;

-- Verificar el cambio
DESCRIBE camaras;
