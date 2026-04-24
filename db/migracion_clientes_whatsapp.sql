-- Migracion Fase 1 CRM: datos de contacto y consentimiento WhatsApp en clientes.
-- Ejecutar sobre base de datos facturacion.

ALTER TABLE clientes
    ADD COLUMN telefono VARCHAR(30) NULL AFTER condicion_iva,
    ADD COLUMN whatsapp VARCHAR(30) NULL AFTER telefono,
    ADD COLUMN acepta_whatsapp TINYINT(1) NOT NULL DEFAULT 0 AFTER whatsapp;
