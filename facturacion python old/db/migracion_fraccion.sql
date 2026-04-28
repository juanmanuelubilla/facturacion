-- Migracion para habilitar cantidades fraccionadas en ventas por peso
-- Ejecutar sobre la base de datos en uso (facturacion).

ALTER TABLE productos
    MODIFY COLUMN stock DECIMAL(10,3) NOT NULL DEFAULT 0.000;

ALTER TABLE venta_items
    MODIFY COLUMN cantidad DECIMAL(10,3) NOT NULL DEFAULT 0.000;
