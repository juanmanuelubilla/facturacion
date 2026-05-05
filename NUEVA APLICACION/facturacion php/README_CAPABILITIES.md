# Sistema de Permisos por Capabilities - Guía Completa

## 🎯 **Objetivo del Sistema**

Reemplazar el sistema simple de roles (`admin`, `jefe`, `cajero`) por un sistema granular de permisos (capabilities) que permita un control preciso sobre cada función del sistema.

---

## 📊 **Estado Actual de la Implementación**

✅ **Base de Datos**: 59 capabilities creadas en 11 categorías  
✅ **Grupos Predefinidos**: 6 grupos de sistema configurados  
✅ **Migración**: 7 usuarios migrados desde roles a grupos  
✅ **Dashboard**: Actualizado para usar capabilities  
✅ **Interface**: Panel de administración de permisos funcional  

---

## 🏗️ **Estructura del Sistema**

### **Tablas Principales**
- `capabilities` - Permisos individuales (59 registrados)
- `capability_groups` - Grupos de permisos (6 predefinidos)
- `user_capabilities` - Permisos directos de usuarios
- `user_groups` - Grupos asignados a usuarios

### **Grupos del Sistema**
1. **Administrador** - Acceso completo (4 usuarios)
2. **Gerente** - Gestión completa excepto configuración crítica (1 usuario)
3. **Cajero** - Funciones básicas de ventas y caja (2 usuarios)
4. **Vendedor** - Ventas y gestión de clientes (0 usuarios)
5. **Inventario** - Gestión de productos e inventario (0 usuarios)
6. **Reportes** - Acceso solo a reportes (0 usuarios)

---

## 🔑 **Capabilities Disponibles**

### **Dashboard**
- `dashboard.view` - Ver dashboard principal
- `dashboard.stats` - Ver estadísticas del dashboard
- `dashboard.camera_stats` - Ver estadísticas de cámaras

### **Empresas**
- `empresas.view` - Ver lista de empresas
- `empresas.create` - Crear nuevas empresas
- `empresas.edit` - Editar empresas existentes
- `empresas.delete` - Eliminar empresas

### **Productos**
- `productos.view` - Ver lista de productos
- `productos.create` - Crear nuevos productos
- `productos.edit` - Editar productos existentes
- `productos.delete` - Eliminar productos
- `productos.precios` - Gestionar precios de productos

### **Inventario**
- `inventario.view` - Ver inventario
- `inventario.ajustar` - Ajustar inventario
- `inventario.movimientos` - Ver movimientos de inventario

### **Ventas**
- `ventas.view` - Ver lista de ventas
- `ventas.create` - Crear nuevas ventas
- `ventas.edit` - Editar ventas existentes
- `ventas.delete` - Eliminar ventas
- `ventas.anular` - Anular ventas
- `ventas.descuentos` - Aplicar descuentos

### **Caja**
- `caja.view` - Ver estado de caja
- `caja.abrir` - Abrir caja
- `caja.cerrar` - Cerrar caja
- `caja.arqueo` - Hacer arqueo de caja
- `caja.retiros` - Hacer retiros de caja

### **Clientes**
- `clientes.view` - Ver lista de clientes
- `clientes.create` - Crear nuevos clientes
- `clientes.edit` - Editar clientes existentes
- `clientes.delete` - Eliminar clientes

### **Presupuestos**
- `presupuestos.view` - Ver presupuestos
- `presupuestos.create` - Crear presupuestos
- `presupuestos.edit` - Editar presupuestos
- `presupuestos.delete` - Eliminar presupuestos
- `presupuestos.aprobar` - Aprobar presupuestos
- `presupuestos.convertir` - Convertir presupuesto a venta

### **Reportes**
- `reportes.view` - Ver reportes básicos
- `reportes.ventas` - Reportes de ventas
- `reportes.inventario` - Reportes de inventario
- `reportes.financieros` - Reportes financieros
- `reportes.exportar` - Exportar reportes

### **Cámaras**
- `camaras.view` - Ver cámaras
- `camaras.create` - Configurar cámaras
- `camaras.edit` - Editar configuración de cámaras
- `camaras.delete` - Eliminar cámaras
- `camaras.grabar` - Iniciar/parar grabación
- `camaras.alertas` - Configurar alertas de cámaras

### **Usuarios**
- `usuarios.view` - Ver lista de usuarios
- `usuarios.create` - Crear nuevos usuarios
- `usuarios.edit` - Editar usuarios existentes
- `usuarios.delete` - Eliminar usuarios
- `usuarios.permisos` - Gestionar permisos de usuarios

### **Configuración**
- `config.view` - Ver configuración del sistema
- `config.edit` - Editar configuración del sistema
- `config.empresa` - Configurar datos de empresa
- `config.ivas` - Configurar tasas de IVA

### **Sistema**
- `sistema.logs` - Ver logs del sistema
- `sistema.backup` - Hacer backup del sistema
- `sistema.mantenimiento` - Modo mantenimiento

---

## 🛠️ **Uso del Sistema**

### **Para Desarrolladores**

```php
// Inicializar PermissionManager
$pm = new PermissionManager();

// Verificar un permiso específico
if ($pm->hasCapability('ventas.create')) {
    // Permitir crear ventas
}

// Verificar múltiples permisos (cualquiera)
if ($pm->hasAnyCapability(['ventas.edit', 'ventas.delete'])) {
    // Permitir editar o eliminar
}

// Verificar múltiples permisos (todos)
if ($pm->hasAllCapabilities(['productos.view', 'inventario.view'])) {
    // Permitir acceso completo a productos
}

// Funciones helper globales
if (hasCapability('camaras.view')) {
    // Mostrar sección de cámaras
}

// Requerir permiso (redirige si no tiene)
requireCapability('usuarios.permisos');
```

### **Para Administradores**

1. **Acceder al panel de permisos**: `http://localhost/facturacion/permisos.php`
2. **Seleccionar usuario** del dropdown
3. **Asignar grupos** predefinidos o personalizados
4. **Otorgar permisos individuales** con fecha de expiración opcional
5. **Revocar permisos** según sea necesario

---

## 📱 **Interface de Administración**

### **Características**
- ✅ **Selector de usuarios** con información de rol actual
- ✅ **Gestión de grupos** - Asignar/remover grupos predefinidos
- ✅ **Permisos individuales** - Otorgar/revocar permisos específicos
- ✅ **Fecha de expiración** - Permisos temporales
- ✅ **Vista organizada** - Permisos agrupados por categoría
- ✅ **Auditoría** - Registro de quién otorgó cada permiso

### **Acceso**
- **URL**: `/permisos.php`
- **Requiere**: `usuarios.permisos`
- **Disponible para**: Administradores y usuarios con permisos de gestión

---

## 🔄 **Migración desde Roles**

### **Usuarios Migrados**
- **Administradores (4)** → Grupo "Administrador"
- **Gerentes (1)** → Grupo "Gerente"  
- **Cajeros (2)** → Grupo "Cajero"

### **Compatibilidad**
- ✅ **Roles originales** se mantienen temporalmente
- ✅ **Dashboard** actualizado para usar capabilities
- ✅ **Funciones helper** para fácil migración de código

### **Próximos Pasos**
1. Actualizar otras páginas para usar capabilities
2. Probar exhaustivamente el sistema
3. Eliminar columna `rol` después de validación completa

---

## 🎯 **Ejemplos de Uso Avanzado**

### **Permisos Temporales**
```php
// Otorgar permiso por 30 días
$pm->grantCapability($user_id, 'reportes.exportar', $admin_id, '2024-12-31 23:59:59');
```

### **Grupos Personalizados**
```php
// Crear grupo para auditores
$pm->createGroup('Auditores', 'Acceso solo a reportes y logs');
$pm->assignCapabilityToGroup('Auditores', 'reportes.view');
$pm->assignCapabilityToGroup('Auditores', 'sistema.logs');
```

### **Verificación Compleja**
```php
// Solo administradores pueden eliminar empresas
if ($pm->hasCapability('empresas.delete') && $pm->hasCapability('sistema.mantenimiento')) {
    // Permitir eliminación crítica
}
```

---

## 🚀 **Beneficios del Nuevo Sistema**

1. **Control Granular** - Permisos específicos por función
2. **Flexibilidad** - Combinación de grupos y permisos individuales
3. **Escalabilidad** - Fácil agregar nuevos permisos
4. **Auditoría** - Registro completo de cambios de permisos
5. **Temporales** - Permisos con fecha de expiración
6. **Herencia** - Grupos con múltiples permisos predefinidos

---

## 📞 **Soporte y Mantenimiento**

### **Monitoreo**
- Verificar tabla `migration_log` para auditoría
- Usar vista `user_capabilities_summary` para estado actual
- Revisar logs de errores en PermissionManager

### **Backup**
- Respaldar tablas de capabilities antes de cambios masivos
- Mantener registro de migraciones personalizadas

---

**🎯 El sistema está listo para producción y puede ser extendido según necesites permisos adicionales o grupos personalizados.**