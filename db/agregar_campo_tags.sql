-- Agregar campo tags a la tabla productos
ALTER TABLE productos ADD COLUMN tags VARCHAR(255) DEFAULT NULL AFTER categoria_id;

-- Agregar índice para búsquedas más rápidas por tags
ALTER TABLE productos ADD INDEX idx_tags (tags);

-- Ejemplos de cómo se usarían los tags (opcional, solo para referencia)
-- UPDATE productos SET tags = 'bebida,gaseosa,azucar' WHERE nombre LIKE '%Coca-Cola%';
-- UPDATE productos SET tags = 'snack,papas,salado' WHERE nombre LIKE '%Papas%';
-- UPDATE productos SET tags = 'lacteo,yogurt,frio' WHERE nombre LIKE '%Yogurt%';
