-- Script para instalar el sistema de categorías y tags
-- Ejecutar este script en la base de datos para agregar las nuevas funcionalidades

-- 1. Agregar campo tags a la tabla productos
ALTER TABLE productos ADD COLUMN tags VARCHAR(255) DEFAULT NULL AFTER categoria_id;

-- 2. Agregar índice para búsquedas más rápidas por tags
ALTER TABLE productos ADD INDEX idx_tags (tags);

-- 3. Crear algunas categorías de ejemplo (opcional)
INSERT IGNORE INTO categorias (id, nombre, empresa_id) VALUES
(1, 'Bebidas', 1),
(2, 'Alimentos', 1),
(3, 'Snacks', 1),
(4, 'Lácteos', 1),
(5, 'Panadería', 1),
(6, 'Limpieza', 1),
(7, 'Personal', 1),
(8, 'Electrónica', 1);

-- 4. Ejemplos de cómo asignar tags a productos existentes (opcional)
-- UPDATE productos SET tags = 'bebida,gaseosa,azucar' WHERE nombre LIKE '%Coca-Cola%';
-- UPDATE productos SET tags = 'snack,papas,salado' WHERE nombre LIKE '%Papas%';
-- UPDATE productos SET tags = 'lacteo,yogurt,frio' WHERE nombre LIKE '%Yogurt%';

-- 5. Asignar categorías a productos existentes (opcional)
-- UPDATE productos SET categoria_id = 1 WHERE nombre LIKE '%Coca-Cola%' OR nombre LIKE '%Pepsi%';
-- UPDATE productos SET categoria_id = 2 WHERE nombre LIKE '%Pan%' OR nombre LIKE '%Arroz%';
-- UPDATE productos SET categoria_id = 3 WHERE nombre LIKE '%Papas%' OR nombre LIKE '%Snack%';

COMMIT;
