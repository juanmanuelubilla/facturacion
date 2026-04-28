-- Migracion complemento CRM: campo comentarios en clientes.
-- Ejecutar sobre base facturacion.

ALTER TABLE clientes
    ADD COLUMN IF NOT EXISTS comentarios TEXT NULL AFTER acepta_whatsapp;
