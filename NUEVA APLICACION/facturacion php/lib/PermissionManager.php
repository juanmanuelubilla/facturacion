<?php
/**
 * PermissionManager - Sistema de Gestión de Permisos por Capabilities
 * Reemplaza el sistema simple de roles por un sistema granular de permisos
 */

require_once 'config.php';

class PermissionManager {
    private $user_id;
    private $empresa_id;
    private $user_capabilities = null;
    private $group_capabilities = null;
    
    public function __construct($user_id = null, $empresa_id = null) {
        $this->user_id = $user_id ?? ($_SESSION['user_id'] ?? null);
        $this->empresa_id = $empresa_id ?? ($_SESSION['empresa_id'] ?? null);
    }
    
    /**
     * Verifica si un usuario tiene un permiso específico
     */
    public function hasCapability($capability_name) {
        if (!$this->user_id) {
            return false;
        }
        
        // Cargar permisos del usuario si no están cargados
        if ($this->user_capabilities === null) {
            $this->loadUserCapabilities();
        }
        
        return in_array($capability_name, $this->user_capabilities);
    }
    
    /**
     * Verifica si un usuario tiene alguno de los permisos especificados
     */
    public function hasAnyCapability(array $capabilities) {
        foreach ($capabilities as $capability) {
            if ($this->hasCapability($capability)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Verifica si un usuario tiene todos los permisos especificados
     */
    public function hasAllCapabilities(array $capabilities) {
        foreach ($capabilities as $capability) {
            if (!$this->hasCapability($capability)) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Carga los permisos del usuario desde la base de datos
     */
    private function loadUserCapabilities() {
        $this->user_capabilities = [];
        
        try {
            // Obtener permisos directos del usuario
            $direct_caps = fetchAll("
                SELECT c.name 
                FROM user_capabilities uc
                JOIN capabilities c ON uc.capability_id = c.id
                WHERE uc.user_id = ? 
                AND (uc.expires_at IS NULL OR uc.expires_at > NOW())
            ", [$this->user_id]);
            
            foreach ($direct_caps as $cap) {
                $this->user_capabilities[] = $cap['name'];
            }
            
            // Obtener permisos de grupos del usuario
            $group_caps = fetchAll("
                SELECT DISTINCT c.name 
                FROM user_groups ug
                JOIN group_capabilities gc ON ug.group_id = gc.group_id
                JOIN capabilities c ON gc.capability_id = c.id
                WHERE ug.user_id = ?
            ", [$this->user_id]);
            
            foreach ($group_caps as $cap) {
                if (!in_array($cap['name'], $this->user_capabilities)) {
                    $this->user_capabilities[] = $cap['name'];
                }
            }
            
        } catch (Exception $e) {
            error_log("Error loading user capabilities: " . $e->getMessage());
        }
    }
    
    /**
     * Otorga un permiso específico a un usuario
     */
    public function grantCapability($user_id, $capability_name, $granted_by = null, $expires_at = null) {
        try {
            // Obtener ID del capability
            $capability = fetch("SELECT id FROM capabilities WHERE name = ?", [$capability_name]);
            if (!$capability) {
                throw new Exception("Capability '$capability_name' not found");
            }
            
            // Insertar o actualizar el permiso
            query("
                INSERT INTO user_capabilities (user_id, capability_id, granted_by, expires_at)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                granted_by = VALUES(granted_by),
                expires_at = VALUES(expires_at),
                granted_at = CURRENT_TIMESTAMP
            ", [$user_id, $capability['id'], $granted_by, $expires_at]);
            
            // Limpiar cache si es el usuario actual
            if ($user_id == $this->user_id) {
                $this->user_capabilities = null;
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Error granting capability: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Revoca un permiso específico de un usuario
     */
    public function revokeCapability($user_id, $capability_name) {
        try {
            $capability = fetch("SELECT id FROM capabilities WHERE name = ?", [$capability_name]);
            if (!$capability) {
                throw new Exception("Capability '$capability_name' not found");
            }
            
            query("
                DELETE FROM user_capabilities 
                WHERE user_id = ? AND capability_id = ?
            ", [$user_id, $capability['id']]);
            
            // Limpiar cache si es el usuario actual
            if ($user_id == $this->user_id) {
                $this->user_capabilities = null;
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Error revoking capability: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Asigna un usuario a un grupo de permisos
     */
    public function assignToGroup($user_id, $group_name, $assigned_by = null) {
        try {
            $group = fetch("SELECT id FROM capability_groups WHERE name = ?", [$group_name]);
            if (!$group) {
                throw new Exception("Group '$group_name' not found");
            }
            
            query("
                INSERT INTO user_groups (user_id, group_id, assigned_by)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                assigned_by = VALUES(assigned_by),
                assigned_at = CURRENT_TIMESTAMP
            ", [$user_id, $group['id'], $assigned_by]);
            
            // Limpiar cache si es el usuario actual
            if ($user_id == $this->user_id) {
                $this->user_capabilities = null;
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Error assigning to group: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Remueve un usuario de un grupo de permisos
     */
    public function removeFromGroup($user_id, $group_name) {
        try {
            $group = fetch("SELECT id FROM capability_groups WHERE name = ?", [$group_name]);
            if (!$group) {
                throw new Exception("Group '$group_name' not found");
            }
            
            query("
                DELETE FROM user_groups 
                WHERE user_id = ? AND group_id = ?
            ", [$user_id, $group['id']]);
            
            // Limpiar cache si es el usuario actual
            if ($user_id == $this->user_id) {
                $this->user_capabilities = null;
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Error removing from group: " . $e->getMessage());
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
            $capabilities = fetchAll("
                SELECT DISTINCT c.name, c.description, c.category, c.module
                FROM capabilities c
                LEFT JOIN user_capabilities uc ON c.id = uc.capability_id AND uc.user_id = ? 
                    AND (uc.expires_at IS NULL OR uc.expires_at > NOW())
                LEFT JOIN user_groups ug ON ug.user_id = ?
                LEFT JOIN group_capabilities gc ON gc.group_id = ug.group_id AND gc.capability_id = c.id
                WHERE (uc.user_id IS NOT NULL OR ug.user_id IS NOT NULL)
                ORDER BY c.category, c.module, c.name
            ", [$user_id, $user_id]);
            
            return $capabilities;
        } catch (Exception $e) {
            error_log("Error getting user capabilities: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtiene todos los grupos disponibles
     */
    public function getAvailableGroups() {
        try {
            return fetchAll("
                SELECT * FROM capability_groups 
                ORDER BY is_system DESC, name
            ");
        } catch (Exception $e) {
            error_log("Error getting groups: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtiene todos los capabilities disponibles
     */
    public function getAvailableCapabilities($category = null) {
        try {
            $sql = "SELECT * FROM capabilities";
            $params = [];
            
            if ($category) {
                $sql .= " WHERE category = ?";
                $params[] = $category;
            }
            
            $sql .= " ORDER BY category, module, name";
            
            return fetchAll($sql, $params);
        } catch (Exception $e) {
            error_log("Error getting capabilities: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtiene los grupos de un usuario
     */
    public function getUserGroups($user_id = null) {
        $user_id = $user_id ?? $this->user_id;
        if (!$user_id) {
            return [];
        }
        
        try {
            return fetchAll("
                SELECT cg.*, ug.assigned_at, u.nombre as assigned_by_name
                FROM user_groups ug
                JOIN capability_groups cg ON ug.group_id = cg.id
                LEFT JOIN usuarios u ON ug.assigned_by = u.id
                WHERE ug.user_id = ?
                ORDER BY cg.name
            ", [$user_id]);
        } catch (Exception $e) {
            error_log("Error getting user groups: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Verifica si un usuario puede acceder a una página específica
     */
    public function canAccessPage($page) {
        $page_capabilities = [
            'dashboard.php' => ['dashboard.view'],
            'empresas.php' => ['empresas.view'],
            'productos.php' => ['productos.view'],
            'inventario.php' => ['inventario.view'],
            'ventas.php' => ['ventas.view'],
            'caja.php' => ['caja.view'],
            'clientes.php' => ['clientes.view'],
            'presupuestos.php' => ['presupuestos.view'],
            'reportes.php' => ['reportes.view'],
            'camaras.php' => ['camaras.view'],
            'usuarios.php' => ['usuarios.view'],
            'configurar.php' => ['config.view']
        ];
        
        $required_caps = $page_capabilities[$page] ?? [];
        
        if (empty($required_caps)) {
            return true; // Páginas sin restricción específica
        }
        
        return $this->hasAnyCapability($required_caps);
    }
    
    /**
     * Función helper para usar en templates
     */
    public static function check($capability) {
        $pm = new self();
        return $pm->hasCapability($capability);
    }
    
    /**
     * Función helper para verificar múltiples permisos
     */
    public static function checkAny(array $capabilities) {
        $pm = new self();
        return $pm->hasAnyCapability($capabilities);
    }
    
    /**
     * Función helper para verificar todos los permisos
     */
    public static function checkAll(array $capabilities) {
        $pm = new self();
        return $pm->hasAllCapabilities($capabilities);
    }
}

// Funciones globales para compatibilidad y facilidad de uso
function hasCapability($capability) {
    return PermissionManager::check($capability);
}

function canAccess($page) {
    $pm = new PermissionManager();
    return $pm->canAccessPage($page);
}

function requireCapability($capability) {
    if (!hasCapability($capability)) {
        header('HTTP/1.0 403 Forbidden');
        header('Location: dashboard.php?error=permission_denied');
        exit;
    }
}

function requireAnyCapability(array $capabilities) {
    if (!PermissionManager::checkAny($capabilities)) {
        header('HTTP/1.0 403 Forbidden');
        header('Location: dashboard.php?error=permission_denied');
        exit;
    }
}
?>
