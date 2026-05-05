-- Sistema de Capabilities Simple - Adaptado a la estructura actual
-- Compatible con la tabla usuarios existente

-- Tabla de Capabilities (Permisos individuales)
CREATE TABLE IF NOT EXISTS capabilities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    category VARCHAR(50) NOT NULL,
    module VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de Permisos de Usuario
CREATE TABLE IF NOT EXISTS user_capabilities (
    user_id INT NOT NULL,
    capability_id INT NOT NULL,
    granted_by INT,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    PRIMARY KEY (user_id, capability_id),
    FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (capability_id) REFERENCES capabilities(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- Insertar capabilities básicas del sistema
INSERT INTO capabilities (name, description, category, module) VALUES
-- Usuarios y Permisos
('usuarios.ver', 'Ver lista de usuarios', 'usuarios', 'usuarios'),
('usuarios.crear', 'Crear nuevos usuarios', 'usuarios', 'usuarios'),
('usuarios.editar', 'Editar usuarios existentes', 'usuarios', 'usuarios'),
('usuarios.eliminar', 'Eliminar usuarios', 'usuarios', 'usuarios'),
('usuarios.permisos', 'Gestionar permisos de usuarios', 'usuarios', 'usuarios'),

-- Productos
('productos.ver', 'Ver lista de productos', 'productos', 'productos'),
('productos.crear', 'Crear nuevos productos', 'productos', 'productos'),
('productos.editar', 'Editar productos existentes', 'productos', 'productos'),
('productos.eliminar', 'Eliminar productos', 'productos', 'productos'),
('productos.precios', 'Cambiar precios de productos', 'productos', 'productos'),

-- Ventas
('ventas.ver', 'Ver historial de ventas', 'ventas', 'ventas'),
('ventas.crear', 'Crear nuevas ventas', 'ventas', 'ventas'),
('ventas.editar', 'Editar ventas existentes', 'ventas', 'ventas'),
('ventas.anular', 'Anular ventas', 'ventas', 'ventas'),
('ventas.descuentos', 'Aplicar descuentos', 'ventas', 'ventas'),

-- Clientes
('clientes.ver', 'Ver lista de clientes', 'clientes', 'clientes'),
('clientes.crear', 'Crear nuevos clientes', 'clientes', 'clientes'),
('clientes.editar', 'Editar clientes existentes', 'clientes', 'clientes'),
('clientes.eliminar', 'Eliminar clientes', 'clientes', 'clientes'),

-- Finanzas
('finanzas.ver', 'Ver reportes financieros', 'finanzas', 'finanzas'),
('finanzas.editar', 'Editar datos financieros', 'finanzas', 'finanzas'),
('finanzas.exportar', 'Exportar datos financieros', 'finanzas', 'finanzas'),

-- Contabilidad
('contabilidad.ver', 'Ver libros contables', 'contabilidad', 'contabilidad'),
('contabilidad.crear', 'Crear asientos contables', 'contabilidad', 'contabilidad'),
('contabilidad.editar', 'Editar asientos contables', 'contabilidad', 'contabilidad'),

-- Cámaras
('camaras.ver', 'Ver cámaras configuradas', 'camaras', 'camaras'),
('camaras.configurar', 'Configurar cámaras', 'camaras', 'camaras'),
('camaras.grabar', 'Iniciar/detener grabación', 'camaras', 'camaras'),
('camaras.alertas', 'Gestionar alertas de seguridad', 'camaras', 'camaras'),

-- Configuración
('configuracion.ver', 'Ver configuración del sistema', 'configuracion', 'configurar'),
('configuracion.editar', 'Editar configuración del sistema', 'configuracion', 'configurar'),
('configuracion.empresa', 'Configurar datos de la empresa', 'configuracion', 'configurar'),

-- Reportes
('reportes.ver', 'Ver reportes generales', 'reportes', 'reportes'),
('reportes.ventas', 'Ver reportes de ventas', 'reportes', 'reportes'),
('reportes.exportar', 'Exportar reportes', 'reportes', 'reportes'),

-- Inventario
('inventario.ver', 'Ver estado del inventario', 'inventario', 'inventario'),
('inventario.editar', 'Editar stock de productos', 'inventario', 'inventario'),
('inventario.ajustes', 'Realizar ajustes de inventario', 'inventario', 'inventario'),

-- Caja
('caja.ver', 'Ver estado de caja', 'caja', 'caja'),
('caja.abrir', 'Abrir caja', 'caja', 'caja'),
('caja.cerrar', 'Cerrar caja', 'caja', 'caja'),
('caja.movimientos', 'Gestionar movimientos de caja', 'caja', 'caja'),

-- Presupuestos
('presupuestos.ver', 'Ver presupuestos', 'presupuestos', 'presupuestos'),
('presupuestos.crear', 'Crear presupuestos', 'presupuestos', 'presupuestos'),
('presupuestos.editar', 'Editar presupuestos', 'presupuestos', 'presupuestos'),
('presupuestos.aprobar', 'Aprobar presupuestos', 'presupuestos', 'presupuestos'),

-- Marketing
('banners.ver', 'Ver banners', 'marketing', 'banners'),
('banners.crear', 'Crear banners', 'marketing', 'banners'),
('banners.editar', 'Editar banners', 'marketing', 'banners'),
('imagenes.ver', 'Ver imágenes', 'marketing', 'imagenes'),
('imagenes.crear', 'Subir imágenes', 'marketing', 'imagenes'),
('promociones.ver', 'Ver promociones', 'marketing', 'promociones'),
('promociones.crear', 'Crear promociones', 'marketing', 'promociones'),

-- Sistema
('sistema.backup', 'Realizar backups del sistema', 'sistema', 'configuracion'),
('sistema.logs', 'Ver logs del sistema', 'sistema', 'configuracion'),
('sistema.mantenimiento', 'Modo mantenimiento', 'sistema', 'configuracion');
