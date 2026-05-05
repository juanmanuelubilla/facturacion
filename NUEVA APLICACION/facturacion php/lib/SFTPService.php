<?php
require_once __DIR__ . '/../config.php';

// Autoloader simple para phpseclib3
spl_autoload_register(function ($class) {
    $prefix = 'phpseclib3\\';
    $base_dir = __DIR__ . '/phpseclib/phpseclib/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require_once $file;
    }
});

use phpseclib3\Net\SFTP;

class SFTPService {
    private $empresa_id;
    private $config;
    private $sftp;
    
    public function __construct($empresa_id) {
        $this->empresa_id = $empresa_id;
        $this->config = $this->cargarConfiguracion();
    }
    
    /**
     * Cargar configuración SFTP desde la base de datos
     */
    private function cargarConfiguracion() {
        $config = fetch("SELECT sftp_host, sftp_port, sftp_user, sftp_password, sftp_remote_path, sftp_enabled 
                        FROM nombre_negocio WHERE empresa_id=?", [$this->empresa_id]);
        
        if (!$config) {
            return [
                'sftp_host' => defined('SFTP_HOST') ? SFTP_HOST : '192.168.31.102',
                'sftp_port' => defined('SFTP_PORT') ? SFTP_PORT : '22',
                'sftp_user' => defined('SFTP_USER') ? SFTP_USER : 'pi',
                'sftp_password' => defined('SFTP_PASSWORD') ? SFTP_PASSWORD : 'juanmanuel',
                'sftp_remote_path' => defined('SFTP_REMOTE_PATH') ? SFTP_REMOTE_PATH : '/mnt/R2/SD64GB/www/facturacion/html/banners/',
                'sftp_enabled' => defined('SFTP_ENABLED') ? SFTP_ENABLED : 0
            ];
        }
        
        return $config;
    }
    
    /**
     * Conectar al servidor SFTP
     */
    private function conectar() {
        try {
            $this->sftp = new SFTP($this->config['sftp_host'], $this->config['sftp_port']);
            
            if (!$this->sftp->login($this->config['sftp_user'], $this->config['sftp_password'])) {
                error_log("SFTP: Error de autenticación en {$this->config['sftp_host']}");
                return false;
            }
            
            error_log("SFTP: Conexión exitosa a {$this->config['sftp_host']}");
            return true;
            
        } catch (Exception $e) {
            error_log("SFTP: Error al conectar - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Desconectar del servidor SFTP
     */
    private function desconectar() {
        if ($this->sftp) {
            $this->sftp->disconnect();
            $this->sftp = null;
        }
    }
    
    /**
     * Probar conexión SFTP
     */
    public function probarConexion() {
        if (!$this->config['sftp_enabled']) {
            return [
                'success' => false,
                'message' => 'La copia SFTP no está habilitada'
            ];
        }
        
        if ($this->conectar()) {
            // Verificar que el directorio remoto exista
            $remote_path = $this->config['sftp_remote_path'];
            
            if (!$this->sftp->file_exists($remote_path)) {
                // Intentar crear el directorio
                if (!$this->sftp->mkdir($remote_path, 0777, true)) {
                    $this->desconectar();
                    return [
                        'success' => false,
                        'message' => "No se pudo crear el directorio remoto: $remote_path"
                    ];
                }
            }
            
            $this->desconectar();
            return [
                'success' => true,
                'message' => 'Conexión SFTP exitosa'
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Error al conectar al servidor SFTP'
        ];
    }
    
    /**
     * Copiar archivo vía SFTP
     * 
     * @param string $local_path Ruta local del archivo
     * @param string $remote_filename Nombre del archivo en el servidor remoto
     * @return array Resultado de la operación
     */
    public function copiarArchivo($local_path, $remote_filename) {
        // Verificar si SFTP está habilitado
        if (!$this->config['sftp_enabled']) {
            error_log("SFTP: Copia SFTP no está habilitada");
            return [
                'success' => false,
                'message' => 'Copia SFTP no habilitada'
            ];
        }
        
        // Verificar que el archivo local exista
        if (!file_exists($local_path)) {
            error_log("SFTP: Archivo local no existe: $local_path");
            return [
                'success' => false,
                'message' => "Archivo local no existe: $local_path"
            ];
        }
        
        // Conectar al servidor SFTP
        if (!$this->conectar()) {
            return [
                'success' => false,
                'message' => 'Error al conectar al servidor SFTP'
            ];
        }
        
        // Construir ruta remota completa
        $remote_path = rtrim($this->config['sftp_remote_path'], '/') . '/' . $remote_filename;
        
        try {
            // Verificar que el directorio remoto exista
            $remote_dir = dirname($remote_path);
            if (!$this->sftp->file_exists($remote_dir)) {
                if (!$this->sftp->mkdir($remote_dir, 0777, true)) {
                    $this->desconectar();
                    error_log("SFTP: No se pudo crear directorio remoto: $remote_dir");
                    return [
                        'success' => false,
                        'message' => "No se pudo crear directorio remoto: $remote_dir"
                    ];
                }
            }
            
            // Copiar el archivo
            if ($this->sftp->put($remote_path, $local_path, SFTP::SOURCE_LOCAL_FILE)) {
                // Establecer permisos para que DLNA pueda leer el archivo (644 = rw-r--r--)
                $this->sftp->chmod($remote_path, 0644);
                error_log("SFTP: Archivo copiado exitosamente: $local_path -> $remote_path (permisos: 644)");
                $this->desconectar();
                return [
                    'success' => true,
                    'message' => "Archivo copiado exitosamente a $remote_path (permisos: 644)"
                ];
            } else {
                error_log("SFTP: Error al copiar archivo: $local_path -> $remote_path");
                $this->desconectar();
                return [
                    'success' => false,
                    'message' => 'Error al copiar archivo al servidor SFTP'
                ];
            }
            
        } catch (Exception $e) {
            error_log("SFTP: Excepción al copiar archivo - " . $e->getMessage());
            $this->desconectar();
            return [
                'success' => false,
                'message' => 'Excepción: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Eliminar archivo vía SFTP
     * 
     * @param string $remote_filename Nombre del archivo en el servidor remoto
     * @return array Resultado de la operación
     */
    public function eliminarArchivo($remote_filename) {
        // Verificar si SFTP está habilitado
        if (!$this->config['sftp_enabled']) {
            return [
                'success' => false,
                'message' => 'Copia SFTP no habilitada'
            ];
        }
        
        // Conectar al servidor SFTP
        if (!$this->conectar()) {
            return [
                'success' => false,
                'message' => 'Error al conectar al servidor SFTP'
            ];
        }
        
        // Construir ruta remota completa
        $remote_path = rtrim($this->config['sftp_remote_path'], '/') . '/' . $remote_filename;
        
        try {
            if ($this->sftp->file_exists($remote_path)) {
                if ($this->sftp->delete($remote_path)) {
                    error_log("SFTP: Archivo eliminado exitosamente: $remote_path");
                    $this->desconectar();
                    return [
                        'success' => true,
                        'message' => "Archivo eliminado exitosamente"
                    ];
                } else {
                    error_log("SFTP: Error al eliminar archivo: $remote_path");
                    $this->desconectar();
                    return [
                        'success' => false,
                        'message' => 'Error al eliminar archivo del servidor SFTP'
                    ];
                }
            } else {
                error_log("SFTP: Archivo remoto no existe: $remote_path");
                $this->desconectar();
                return [
                    'success' => true,
                    'message' => 'Archivo remoto no existe (ya eliminado)'
                ];
            }
            
        } catch (Exception $e) {
            error_log("SFTP: Excepción al eliminar archivo - " . $e->getMessage());
            $this->desconectar();
            return [
                'success' => false,
                'message' => 'Excepción: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Mover archivo vía SFTP (renombrar)
     * 
     * @param string $old_remote_filename Nombre actual del archivo
     * @param string $new_remote_filename Nuevo nombre del archivo
     * @return array Resultado de la operación
     */
    public function moverArchivo($old_remote_filename, $new_remote_filename) {
        // Verificar si SFTP está habilitado
        if (!$this->config['sftp_enabled']) {
            return [
                'success' => false,
                'message' => 'Copia SFTP no habilitada'
            ];
        }
        
        // Conectar al servidor SFTP
        if (!$this->conectar()) {
            return [
                'success' => false,
                'message' => 'Error al conectar al servidor SFTP'
            ];
        }
        
        // Construir rutas remotas
        $old_remote_path = rtrim($this->config['sftp_remote_path'], '/') . '/' . $old_remote_filename;
        $new_remote_path = rtrim($this->config['sftp_remote_path'], '/') . '/' . $new_remote_filename;
        
        try {
            if ($this->sftp->file_exists($old_remote_path)) {
                if ($this->sftp->rename($old_remote_path, $new_remote_path)) {
                    error_log("SFTP: Archivo movido exitosamente: $old_remote_path -> $new_remote_path");
                    $this->desconectar();
                    return [
                        'success' => true,
                        'message' => "Archivo movido exitosamente"
                    ];
                } else {
                    error_log("SFTP: Error al mover archivo: $old_remote_path -> $new_remote_path");
                    $this->desconectar();
                    return [
                        'success' => false,
                        'message' => 'Error al mover archivo en el servidor SFTP'
                    ];
                }
            } else {
                error_log("SFTP: Archivo remoto no existe: $old_remote_path");
                $this->desconectar();
                return [
                    'success' => false,
                    'message' => 'Archivo remoto no existe'
                ];
            }
            
        } catch (Exception $e) {
            error_log("SFTP: Excepción al mover archivo - " . $e->getMessage());
            $this->desconectar();
            return [
                'success' => false,
                'message' => 'Excepción: ' . $e->getMessage()
            ];
        }
    }
}
