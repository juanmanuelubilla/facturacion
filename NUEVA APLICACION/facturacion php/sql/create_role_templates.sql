-- Sistema de Roles Personalizados (Templates de Permisos)
-- Permite crear roles personalizados con combinaciones específicas de permisos

-- Tabla de Roles Personalizados (Templates)
CREATE TABLE IF NOT EXISTS role_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    company_id INT NOT NULL,
    is_system BOOLEAN DEFAULT FALSE, -- Roles del sistema (admin, jefe, cajero)
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla de Permisos por Rol Template (sin foreign keys temporariamente)
CREATE TABLE IF NOT EXISTS role_template_capabilities (
    template_id INT NOT NULL,
    capability_id INT NOT NULL,
    PRIMARY KEY (template_id, capability_id)
);

-- Insertar roles del sistema por defecto
INSERT INTO role_templates (name, description, company_id, is_system) VALUES
('admin', 'Administrador con acceso completo al sistema', 1, TRUE),
('jefe', 'Jefe con gestión y finanzas', 1, TRUE),
('cajero', 'Cajero con operaciones básicas', 1, TRUE)
ON DUPLICATE KEY UPDATE name = name;

-- Asignar permisos a los roles del sistema
-- Admin - Todos los permisos
INSERT INTO role_template_capabilities (template_id, capability_id)
SELECT rt.id, c.id 
FROM role_templates rt, capabilities c 
WHERE rt.name = 'admin' AND rt.company_id = 1
ON DUPLICATE KEY UPDATE template_id = template_id;

-- Jefe - Gestión y finanzas
INSERT INTO role_template_capabilities (template_id, capability_id)
SELECT rt.id, c.id 
FROM role_templates rt, capabilities c 
WHERE rt.name = 'jefe' AND rt.company_id = 1
AND c.category IN ('productos', 'ventas', 'clientes', 'finanzas', 'contabilidad', 'reportes', 'inventario', 'caja', 'presupuestos', 'banners', 'imagenes', 'promociones')
ON DUPLICATE KEY UPDATE template_id = template_id;

-- Cajero - Operaciones básicas
INSERT INTO role_template_capabilities (template_id, capability_id)
SELECT rt.id, c.id 
FROM role_templates rt, capabilities c 
WHERE rt.name = 'cajero' AND rt.company_id = 1
AND c.name IN ('productos.ver', 'ventas.ver', 'ventas.crear', 'clientes.ver', 'clientes.crear', 'inventario.ver', 'caja.ver', 'caja.movimientos', 'presupuestos.ver', 'presupuestos.crear')
ON DUPLICATE KEY UPDATE template_id = template_id;
