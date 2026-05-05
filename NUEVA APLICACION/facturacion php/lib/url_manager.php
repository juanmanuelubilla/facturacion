<?php

/**
 * NEXUS POS - Gestor de URLs Multiempresa
 * 
 * Permite configurar URLs personalizadas por empresa de forma opcional
 * Modos: Single, Multiempresa (subdominio, prefijo, parámetro)
 */

class URLManager {
    private $db;
    private $config;
    
    public function __construct($db) {
        $this->db = $db;
        $this->config = $this->loadConfig();
    }
    
    /**
     * Cargar configuración del sistema
     */
    private function loadConfig() {
        // Configuración por defecto
        $config = [
            'modo' => 'single', // single, multi_subdominio, multi_prefijo, multi_parametro
            'dominio_base' => 'nexuspos.com',
            'permitir_urls_personalizadas' => false,
            'url_personalizada_obligatoria' => false
        ];
        
        // Intentar cargar desde base de datos si existe tabla de configuración
        try {
            $stmt = $this->db->prepare("SELECT modo, dominio_base, permitir_urls_personalizadas, url_personalizada_obligatoria FROM sistema_config WHERE id = 1");
            $stmt->execute();
            $db_config = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($db_config) {
                $config = array_merge($config, $db_config);
            }
        } catch (Exception $e) {
            // Si no existe la tabla, usar configuración por defecto
        }
        
        return $config;
    }
    
    /**
     * Detectar empresa actual basado en URL
     */
    public function detectarEmpresa() {
        $empresa_id = null;
        
        switch ($this->config['modo']) {
            case 'multi_subdominio':
                $empresa_id = $this->detectarPorSubdominio();
                break;
                
            case 'multi_prefijo':
                $empresa_id = $this->detectarPorPrefijo();
                break;
                
            case 'multi_parametro':
                $empresa_id = $this->detectarPorParametro();
                break;
                
            case 'single':
            default:
                $empresa_id = $this->detectarSingleMode();
                break;
        }
        
        return $empresa_id;
    }
    
    /**
     * Detectar empresa por subdominio (ej: miempresa.nexuspos.com)
     */
    private function detectarPorSubdominio() {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $dominio_base = $this->config['dominio_base'];
        
        // Extraer subdominio
        if (strpos($host, '.') !== false && $host !== $dominio_base) {
            $partes = explode('.', $host);
            if (count($partes) >= 3) {
                $subdominio = $partes[0];
                
                // Buscar empresa por subdominio personalizado
                $stmt = $this->db->prepare("SELECT id FROM empresas WHERE url_personalizada = ? AND activo = 1");
                $stmt->execute([$subdominio]);
                $empresa = $stmt->fetch();
                
                if ($empresa) {
                    return $empresa['id'];
                }
            }
        }
        
        return null;
    }
    
    /**
     * Detectar empresa por prefijo en URL (ej: nexuspos.com/miempresa)
     */
    private function detectarPorPrefijo() {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $partes = explode('/', trim($request_uri, '/'));
        
        if (count($partes) >= 1 && !empty($partes[0])) {
            $prefijo = $partes[0];
            
            // Buscar empresa por URL personalizada
            $stmt = $this->db->prepare("SELECT id FROM empresas WHERE url_personalizada = ? AND activo = 1");
            $stmt->execute([$prefijo]);
            $empresa = $stmt->fetch();
            
            if ($empresa) {
                // Remover el prefijo de la URL para que el routing funcione normal
                $_SERVER['REQUEST_URI'] = str_replace('/' . $prefijo, '', $request_uri) ?: '/';
                return $empresa['id'];
            }
        }
        
        return null;
    }
    
    /**
     * Detectar empresa por parámetro (ej: ?empresa=miempresa)
     */
    private function detectarPorParametro() {
        $empresa_param = $_GET['empresa'] ?? $_POST['empresa'] ?? null;
        
        if ($empresa_param) {
            // Buscar por URL personalizada o nombre
            $stmt = $this->db->prepare("SELECT id FROM empresas WHERE (url_personalizada = ? OR nombre = ?) AND activo = 1");
            $stmt->execute([$empresa_param, $empresa_param]);
            $empresa = $stmt->fetch();
            
            if ($empresa) {
                return $empresa['id'];
            }
        }
        
        return null;
    }
    
    /**
     * Modo single: primera empresa activa
     */
    private function detectarSingleMode() {
        $stmt = $this->db->prepare("SELECT id FROM empresas WHERE activo = 1 ORDER BY id LIMIT 1");
        $stmt->execute();
        $empresa = $stmt->fetch();
        
        return $empresa ? $empresa['id'] : null;
    }
    
    /**
     * Generar URL para una empresa específica
     */
    public function generarURL($empresa_id, $pagina = '') {
        $stmt = $this->db->prepare("SELECT url_personalizada, nombre FROM empresas WHERE id = ?");
        $stmt->execute([$empresa_id]);
        $empresa = $stmt->fetch();
        
        if (!$empresa) {
            return '';
        }
        
        $base_url = $this->getBaseURL();
        $url_personalizada = $empresa['url_personalizada'];
        
        switch ($this->config['modo']) {
            case 'multi_subdominio':
                if ($url_personalizada) {
                    return "http://{$url_personalizada}.{$this->config['dominio_base']}/{$pagina}";
                }
                break;
                
            case 'multi_prefijo':
                if ($url_personalizada) {
                    return "{$base_url}/{$url_personalizada}/{$pagina}";
                }
                break;
                
            case 'multi_parametro':
                if ($url_personalizada) {
                    return "{$base_url}/{$pagina}?empresa={$url_personalizada}";
                }
                break;
        }
        
        // URL estándar por defecto
        return "{$base_url}/{$pagina}";
    }
    
    /**
     * Obtener URL base del sistema
     */
    private function getBaseURL() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        return "{$protocol}://{$host}";
    }
    
    /**
     * Verificar si una URL personalizada está disponible
     */
    public function urlDisponible($url, $empresa_id = null) {
        $sql = "SELECT id FROM empresas WHERE url_personalizada = ? AND activo = 1";
        $params = [$url];
        
        if ($empresa_id) {
            $sql .= " AND id != ?";
            $params[] = $empresa_id;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetch() === false;
    }
    
    /**
     * Validar formato de URL personalizada
     */
    public function validarURLPersonalizada($url) {
        // Solo letras, números y guiones
        // Mínimo 3 caracteres, máximo 30
        return preg_match('/^[a-zA-Z0-9_-]{3,30}$/', $url);
    }
    
    /**
     * Obtener configuración actual
     */
    public function getConfig() {
        return $this->config;
    }
    
    /**
     * Actualizar configuración del sistema
     */
    public function actualizarConfig($nueva_config) {
        try {
            // Crear tabla de configuración si no existe
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS sistema_config (
                    id INT PRIMARY KEY,
                    modo VARCHAR(50) DEFAULT 'single',
                    dominio_base VARCHAR(255) DEFAULT 'nexuspos.com',
                    permitir_urls_personalizadas BOOLEAN DEFAULT FALSE,
                    url_personalizada_obligatoria BOOLEAN DEFAULT FALSE,
                    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ");
            
            // Actualizar o insertar configuración
            $stmt = $this->db->prepare("
                INSERT INTO sistema_config (id, modo, dominio_base, permitir_urls_personalizadas, url_personalizada_obligatoria)
                VALUES (1, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                modo = VALUES(modo),
                dominio_base = VALUES(dominio_base),
                permitir_urls_personalizadas = VALUES(permitir_urls_personalizadas),
                url_personalizada_obligatoria = VALUES(url_personalizada_obligatoria)
            ");
            
            return $stmt->execute([
                $nueva_config['modo'],
                $nueva_config['dominio_base'],
                $nueva_config['permitir_urls_personalizadas'],
                $nueva_config['url_personalizada_obligatoria']
            ]);
            
        } catch (Exception $e) {
            error_log("Error al actualizar configuración: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Función global para obtener empresa actual
 */
function getEmpresaActual() {
    static $empresa_actual = null;
    
    if ($empresa_actual === null) {
        $db = getDB();
        $url_manager = new URLManager($db);
        $empresa_actual = $url_manager->detectarEmpresa();
    }
    
    return $empresa_actual;
}
