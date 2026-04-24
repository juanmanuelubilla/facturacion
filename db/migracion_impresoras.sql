-- MIGRACIÓN: AGREGAR CONFIGURACIÓN DE IMPRESORAS
-- Fecha: 2026-04-24
-- Autor: Sistema NEXUS POS

-- Agregar campos para configuración de impresoras automáticas a la tabla nombre_negocio
ALTER TABLE nombre_negocio 
ADD COLUMN impresora_auto BOOLEAN DEFAULT FALSE COMMENT 'Imprimir ticket automáticamente después de cada venta',
ADD COLUMN impresora_ticket VARCHAR(255) DEFAULT 'Default' COMMENT 'Nombre o IP de la impresora de tickets',
ADD COLUMN impresora_factura VARCHAR(255) DEFAULT 'Default' COMMENT 'Nombre o IP de la impresora de facturas';

-- Insertar configuración por defecto para empresas existentes
UPDATE nombre_negocio 
SET impresora_auto = FALSE, 
    impresora_ticket = 'Default',
    impresora_factura = 'Default'
WHERE empresa_id > 0;

-- Crear índices para mejor rendimiento
CREATE INDEX IF NOT EXISTS idx_impresora_auto ON nombre_negocio(impresora_auto);
CREATE INDEX IF NOT EXISTS idx_impresora_ticket ON nombre_negocio(impresora_ticket);
CREATE INDEX IF NOT EXISTS idx_impresora_factura ON nombre_negocio(impresora_factura);

-- Comentarios
-- Esta migración agrega soporte para impresión automática de tickets
-- Los campos permiten configurar:
-- 1. impresora_auto: BOOLEAN para activar/desactivar impresión automática
-- 2. impresora_ticket: VARCHAR para nombre o IP de impresora de tickets
-- 3. impresora_factura: VARCHAR para nombre o IP de impresora de facturas
-- Por defecto, todas las empresas tendrán valores configurados
