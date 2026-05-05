-- Agregar columnas SFTP y DLNA a la tabla nombre_negocio
-- Ejecutar en la base de datos de facturacion

-- Columnas para configuración DLNA
ALTER TABLE nombre_negocio 
ADD COLUMN IF NOT EXISTS dlna_tipo_servidor VARCHAR(20) DEFAULT 'local',
ADD COLUMN IF NOT EXISTS dlna_ip_servidor VARCHAR(50) DEFAULT '192.168.1.100',
ADD COLUMN IF NOT EXISTS dlna_puerto_servidor VARCHAR(10) DEFAULT '8200',
ADD COLUMN IF NOT EXISTS dlna_activo TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS dlna_auto_start TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS dlna_ssh_user VARCHAR(100) DEFAULT 'root',
ADD COLUMN IF NOT EXISTS dlna_ssh_password VARCHAR(255) DEFAULT '';

-- Columnas para configuración SFTP
ALTER TABLE nombre_negocio 
ADD COLUMN IF NOT EXISTS sftp_host VARCHAR(255) DEFAULT '192.168.31.102',
ADD COLUMN IF NOT EXISTS sftp_port VARCHAR(10) DEFAULT '22',
ADD COLUMN IF NOT EXISTS sftp_user VARCHAR(100) DEFAULT 'pi',
ADD COLUMN IF NOT EXISTS sftp_password VARCHAR(255) DEFAULT 'juanmanuel',
ADD COLUMN IF NOT EXISTS sftp_remote_path VARCHAR(500) DEFAULT '/mnt/R2/SD64GB/www/facturacion/html/banners/',
ADD COLUMN IF NOT EXISTS sftp_enabled TINYINT(1) DEFAULT 0;

-- Verificar columnas agregadas
DESCRIBE nombre_negocio;
