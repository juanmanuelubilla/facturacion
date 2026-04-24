-- Migración para agregar campo de icono de aplicación
-- Fecha: 2026-04-24
-- Descripción: Agrega campo para almacenar el path del icono de la aplicación

ALTER TABLE nombre_negocio 
ADD COLUMN icono_app VARCHAR(255) DEFAULT NULL COMMENT 'Path al icono de la aplicación (.ico, .png, .jpg)';
