<?php

/**
 * PermissionManager Simple - Sistema de Capabilities Adaptado
 * Compatible con la estructura actual de la base de datos
 */
class PermissionManagerSimple {
    private $user_id;
    private $empresa_id;
    private $db;
    
    public function __construct($user_id = null, $empresa_id = null) {
        $this->db = getDB();
        
        if ($user_id) {
            $this->user_id = $user_id;
            $this->empresa_id = $empresa_id;
        } else {
            // Obtener del usuario actual
            if (isset($_SESSION['user_id'])) {
                $this->user_id = $_SESSION['user_id'];
                $this->empresa_id = $_SESSION['empresa_id'] ?? null;
            }
        }
    }
    
    /**
     * Verifica si un usuario tiene un permiso específico
     */
    public function hasCapability($capability_name) {
        if (!$this->user_id) {
            return false;
        }
        
        try {
            $sql = "
                SELECT COUNT(*) as count 
                FROM user_capabilities uc 
                JOIN capabilities c ON uc.capability_id = c.id 
                WHERE uc.user_id = ? AND c.name = ? 
                AND (uc.expires_at IS NULL OR uc.expires_at > NOW())
            ";
            
            $result = $this->db->prepare($sql);
            $result->execute([$this->user_id, $capability_name]);
            $count = $result->fetch(PDO::FETCH_ASSOC)['count'];
            
            return $count > 0;
        } catch (Exception $e) {
            error_log("Error checking capability: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Otorga un permiso a un usuario
     */
    public function grantCapability($user_id, $capability_name, $granted_by = null, $expires_at = null) {
        try {
            // Obtener el capability_id
            $capability = $this->db->prepare("SELECT id FROM capabilities WHERE name = ?");
            $capability->execute([$capability_name]);
            $capability_id = $capability->fetch(PDO::FETCH_ASSOC)['id'] ?? null;
            
            if (!$capability_id) {
                throw new Exception("Capability '$capability_name' no existe");
            }
            
            // Insertar o actualizar el permiso
            $sql = "
                INSERT INTO user_capabilities (user_id, capability_id, granted_by, expires_at) 
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                granted_by = VALUES(granted_by), 
                expires_at = VALUES(expires_at),
                granted_at = NOW()
            ";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$user_id, $capability_id, $granted_by, $expires_at]);
            
        } catch (Exception $e) {
            error_log("Error granting capability: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Revoca un permiso de un usuario
     */
    public function revokeCapability($user_id, $capability_name) {
        try {
            $sql = "
                DELETE uc FROM user_capabilities uc 
                JOIN capabilities c ON uc.capability_id = c.id 
                WHERE uc.user_id = ? AND c.name = ?
            ";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$user_id, $capability_name]);
            
        } catch (Exception $e) {
            error_log("Error revoking capability: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtiene todos los permisos de un usuario
     */
    public function getUserCapabilities($user_id = null) {
        $user_id = $user_id ?? $this->user_id;
        
        if (!$user_id) {
            return [];
        }
        
        try {
            $sql = "
                SELECT c.*, uc.granted_at, uc.expires_at, uc.granted_by
                FROM user_capabilities uc 
                JOIN capabilities c ON uc.capability_id = c.id 
                WHERE uc.user_id = ? 
                AND (uc.expires_at IS NULL OR uc.expires_at > NOW())
                ORDER BY c.category, c.module, c.name
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$user_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error getting user capabilities: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtiene todas las capabilities disponibles
     */
    public function getAvailableCapabilities() {
        try {
            $sql = "
                SELECT c.*, 
                       (SELECT COUNT(*) FROM user_capabilities WHERE capability_id = c.id) as users_count
                FROM capabilities c 
                ORDER BY c.category, c.module, c.name
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error getting available capabilities: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtiene capabilities por categoría
     */
    public function getCapabilitiesByCategory() {
        try {
            $sql = "
                SELECT c.*, 
                       (SELECT COUNT(*) FROM user_capabilities WHERE capability_id = c.id) as users_count
                FROM capabilities c 
                ORDER BY c.category, c.module, c.name
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $all_capabilities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Agrupar por categoría
            $grouped = [];
            foreach ($all_capabilities as $capability) {
                $grouped[$capability['category']][] = $capability;
            }
            
            return $grouped;
            
        } catch (Exception $e) {
            error_log("Error getting capabilities by category: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Verifica si un usuario tiene permisos para un módulo específico
     */
    public function hasModuleAccess($module, $action = 'ver') {
        $capability = "{$module}.{$action}";
        return $this->hasCapability($capability);
    }
    
    /**
     * Obtiene estadísticas de permisos
     */
    public function getCapabilityStats() {
        try {
            $stats = [];
            
            // Total de capabilities
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM capabilities");
            $stats['total_capabilities'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Total de asignaciones
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM user_capabilities WHERE expires_at IS NULL OR expires_at > NOW()");
            $stats['total_assignments'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Usuarios con permisos
            $stmt = $this->db->query("SELECT COUNT(DISTINCT user_id) as total FROM user_capabilities WHERE expires_at IS NULL OR expires_at > NOW()");
            $stats['users_with_permissions'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Error getting capability stats: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Inicializa permisos básicos para usuarios existentes basados en su rol
     */
    public function initializeUserPermissions($user_id, $role) {
        $role_permissions = $this->getDefaultPermissionsForRole($role);
        
        foreach ($role_permissions as $capability) {
            $this->grantCapability($user_id, $capability);
        }
    }
    
    /**
     * Obtiene permisos por defecto para cada rol
     */
    public function getDefaultPermissionsForRole($role) {
        // Primero buscar en templates de roles personalizados
        $template_permissions = $this->getTemplatePermissions($role);
        if (!empty($template_permissions)) {
            return $template_permissions;
        }
        
        // Si no hay template, usar los roles del sistema por defecto
        $permissions = [
            'admin' => [
                // Sistema completo
                'usuarios.ver', 'usuarios.crear', 'usuarios.editar', 'usuarios.eliminar', 'usuarios.permisos',
                'productos.ver', 'productos.crear', 'productos.editar', 'productos.eliminar', 'productos.precios',
                'ventas.ver', 'ventas.crear', 'ventas.editar', 'ventas.anular', 'ventas.descuentos',
                'clientes.ver', 'clientes.crear', 'clientes.editar', 'clientes.eliminar',
                'finanzas.ver', 'finanzas.editar', 'finanzas.exportar',
                'contabilidad.ver', 'contabilidad.crear', 'contabilidad.editar',
                'camaras.ver', 'camaras.configurar', 'camaras.grabar', 'camaras.alertas',
                'configuracion.ver', 'configuracion.editar', 'configuracion.empresa',
                'reportes.ver', 'reportes.ventas', 'reportes.exportar',
                'inventario.ver', 'inventario.editar', 'inventario.ajustes',
                'caja.ver', 'caja.abrir', 'caja.cerrar', 'caja.movimientos',
                'presupuestos.ver', 'presupuestos.crear', 'presupuestos.editar', 'presupuestos.aprobar',
                'banners.ver', 'banners.crear', 'banners.editar',
                'imagenes.ver', 'imagenes.crear',
                'promociones.ver', 'promociones.crear',
                'sistema.backup', 'sistema.logs', 'sistema.mantenimiento'
            ],
            'jefe' => [
                // Gestión y finanzas
                'productos.ver', 'productos.crear', 'productos.editar', 'productos.precios',
                'ventas.ver', 'ventas.crear', 'ventas.descuentos',
                'clientes.ver', 'clientes.crear', 'clientes.editar',
                'finanzas.ver', 'finanzas.editar', 'finanzas.exportar',
                'contabilidad.ver', 'contabilidad.crear',
                'reportes.ver', 'reportes.ventas', 'reportes.exportar',
                'inventario.ver', 'inventario.editar', 'inventario.ajustes',
                'caja.ver', 'caja.abrir', 'caja.cerrar',
                'presupuestos.ver', 'presupuestos.crear', 'presupuestos.editar', 'presupuestos.aprobar',
                'banners.ver', 'banners.crear', 'banners.editar',
                'imagenes.ver', 'imagenes.crear',
                'promociones.ver', 'promociones.crear'
            ],
            'cajero' => [
                // Operaciones básicas
                'productos.ver',
                'ventas.ver', 'ventas.crear',
                'clientes.ver', 'clientes.crear',
                'inventario.ver',
                'caja.ver', 'caja.movimientos',
                'presupuestos.ver', 'presupuestos.crear'
            ]
        ];
        
        return $permissions[$role] ?? [];
    }
    
    /**
     * Obtiene los permisos de un template de rol
     */
    public function getTemplatePermissions($template_name) {
        try {
            $sql = "
                SELECT c.name 
                FROM role_templates rt 
                JOIN role_template_capabilities rtc ON rt.id = rtc.template_id 
                JOIN capabilities c ON rtc.capability_id = c.id 
                WHERE rt.name = ? AND rt.company_id = ?
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$template_name, $this->empresa_id]);
            $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            return $permissions;
        } catch (Exception $e) {
            error_log("Error getting template permissions: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Crea un nuevo rol personalizado (template)
     */
    public function createRoleTemplate($name, $description, $capability_ids = []) {
        try {
            // Insertar el template
            $sql = "INSERT INTO role_templates (name, description, company_id, created_by) VALUES (?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$name, $description, $this->empresa_id, $this->user_id]);
            $template_id = $this->db->lastInsertId();
            
            // Asignar capabilities
            if (!empty($capability_ids)) {
                foreach ($capability_ids as $capability_id) {
                    $sql = "INSERT INTO role_template_capabilities (template_id, capability_id) VALUES (?, ?)";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([$template_id, $capability_id]);
                }
            }
            
            return $template_id;
        } catch (Exception $e) {
            error_log("Error creating role template: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtiene todos los roles personalizados de la empresa
     */
    public function getRoleTemplates() {
        try {
            $sql = "
                SELECT rt.*, 
                       (SELECT COUNT(*) FROM role_template_capabilities WHERE template_id = rt.id) as permissions_count,
                       (SELECT COUNT(*) FROM usuarios WHERE rol = rt.name) as users_count
                FROM role_templates rt 
                WHERE rt.company_id = ? 
                ORDER BY rt.is_system DESC, rt.name
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$this->empresa_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting role templates: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Aplica un template de rol a un usuario
     */
    public function applyRoleTemplate($user_id, $template_name) {
        try {
            // Eliminar permisos actuales del usuario
            $this->db->prepare("DELETE FROM user_capabilities WHERE user_id = ?")->execute([$user_id]);
            
            // Obtener permisos del template
            $template_permissions = $this->getTemplatePermissions($template_name);
            
            // Asignar nuevos permisos
            foreach ($template_permissions as $capability_name) {
                $this->grantCapability($user_id, $capability_name, $this->user_id);
            }
            
            // Actualizar el rol del usuario
            $this->db->prepare("UPDATE usuarios SET rol = ? WHERE id = ?")->execute([$template_name, $user_id]);
            
            return true;
        } catch (Exception $e) {
            error_log("Error applying role template: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Elimina un rol personalizado
     */
    public function deleteRoleTemplate($template_id) {
        try {
            // Verificar que no sea un rol del sistema
            $template = $this->db->prepare("SELECT is_system FROM role_templates WHERE id = ? AND company_id = ?");
            $template->execute([$template_id, $this->empresa_id]);
            $template_data = $template->fetch(PDO::FETCH_ASSOC);
            
            if ($template_data['is_system']) {
                throw new Exception("No se pueden eliminar roles del sistema");
            }
            
            // Eliminar el template (las relaciones se eliminan en cascada)
            $stmt = $this->db->prepare("DELETE FROM role_templates WHERE id = ? AND company_id = ?");
            return $stmt->execute([$template_id, $this->empresa_id]);
        } catch (Exception $e) {
            error_log("Error deleting role template: " . $e->getMessage());
            return false;
        }
    }
}
