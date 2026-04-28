-- Migración para agregar campos de fotos a la tabla clientes
-- Fecha: 2026-04-24
-- Descripción: Agrega dos campos para almacenar paths de fotos de clientes

ALTER TABLE clientes 
ADD COLUMN foto_cliente VARCHAR(255) DEFAULT NULL COMMENT 'Path a la foto principal del cliente',
ADD COLUMN foto_opcional VARCHAR(255) DEFAULT NULL COMMENT 'Path a una foto secundaria opcional del cliente';
