-- Migración para mejorar el sistema de pagos virtuales
-- Fecha: 2026-04-24
-- Descripción: Agrega campos para validación automática y estados de pagos

-- Agregar campos de estado y validación a la tabla de pagos
ALTER TABLE pagos 
ADD COLUMN estado_validacion VARCHAR(20) DEFAULT 'pendiente' COMMENT 'Estado de validación del pago',
ADD COLUMN qr_data TEXT DEFAULT NULL COMMENT 'Datos del QR generado',
ADD COLUMN fecha_validacion DATETIME DEFAULT NULL COMMENT 'Fecha de validación del pago',
ADD COLUMN respuesta_gateway TEXT DEFAULT NULL COMMENT 'Respuesta del gateway de pago',
ADD COLUMN external_reference VARCHAR(100) DEFAULT NULL COMMENT 'Referencia externa del pago';

-- Agregar índices para mejor rendimiento
ALTER TABLE pagos 
ADD INDEX idx_estado_validacion (estado_validacion),
ADD INDEX idx_external_reference (external_reference),
ADD INDEX idx_fecha_validacion (fecha_validacion);
