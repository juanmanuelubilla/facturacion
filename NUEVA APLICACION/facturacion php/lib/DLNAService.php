<?php
/**
 * Servicio de control DLNA
 * Permite controlar el servidor MiniDLNA de forma remota
 */

class DLNAService {
    private $empresa_id;
    private $config;
    
    public function __construct($empresa_id) {
        $this->empresa_id = $empresa_id;
        $this->config = fetch("SELECT dlna_ip_servidor, dlna_puerto_servidor, dlna_tipo_servidor, dlna_activo, dlna_ruta_banners FROM nombre_negocio WHERE empresa_id = ?", [$empresa_id]);
    }
    
    /**
     * Verificar estado del servicio MiniDLNA
     */
    public function verificarEstado() {
        if ($this->config['dlna_tipo_servidor'] === 'local') {
            // Servidor local - ejecutar comandos directamente
            $status = shell_exec('systemctl is-active minidlna 2>/dev/null');
            $enabled = shell_exec('systemctl is-enabled minidlna 2>/dev/null');
            $port_check = shell_exec('netstat -ln | grep :8200 2>/dev/null');
            
            return [
                'success' => true,
                'activo' => trim($status) === 'active',
                'habilitado' => trim($enabled) === 'enabled',
                'puerto_escuchando' => !empty($port_check),
                'detalle' => $this->obtenerDetalleEstadoLocal()
            ];
        } else {
            // Servidor remoto - usar SSH
            $ip = $this->config['dlna_ip_servidor'];
            $status = $this->ejecutarSSH($ip, 'systemctl is-active minidlna 2>/dev/null');
            $enabled = $this->ejecutarSSH($ip, 'systemctl is-enabled minidlna 2>/dev/null');
            $port_check = $this->ejecutarSSH($ip, 'netstat -ln | grep :8200 2>/dev/null');
            
            return [
                'success' => true,
                'activo' => trim($status) === 'active',
                'habilitado' => trim($enabled) === 'enabled',
                'puerto_escuchando' => !empty($port_check),
                'detalle' => $this->obtenerDetalleEstadoRemoto($ip)
            ];
        }
    }
    
    /**
     * Iniciar servicio MiniDLNA
     */
    public function iniciarServicio() {
        if ($this->config['dlna_tipo_servidor'] === 'local') {
            $result = shell_exec('sudo systemctl start minidlna 2>&1');
            $success = $this->verificarComando($result);
            
            return [
                'success' => $success,
                'mensaje' => $success ? 'Servicio MiniDLNA iniciado correctamente' : 'Error al iniciar MiniDLNA: ' . $result,
                'detalle' => $result
            ];
        } else {
            $ip = $this->config['dlna_ip_servidor'];
            $result = $this->ejecutarSSHConCredenciales($ip, 'sudo systemctl start minidlna 2>&1');
            $success = $this->verificarComando($result);
            
            return [
                'success' => $success,
                'mensaje' => $success ? 'Servicio MiniDLNA iniciado en servidor remoto' : 'Error al iniciar MiniDLNA remoto: ' . $result,
                'detalle' => $result
            ];
        }
    }
    
    /**
     * Detener servicio MiniDLNA
     */
    public function detenerServicio() {
        if ($this->config['dlna_tipo_servidor'] === 'local') {
            $result = shell_exec('sudo systemctl stop minidlna 2>&1');
            $success = $this->verificarComando($result);
            
            return [
                'success' => $success,
                'mensaje' => $success ? 'Servicio MiniDLNA detenido correctamente' : 'Error al detener MiniDLNA: ' . $result,
                'detalle' => $result
            ];
        } else {
            $ip = $this->config['dlna_ip_servidor'];
            $result = $this->ejecutarSSH($ip, 'sudo systemctl stop minidlna 2>&1');
            $success = $this->verificarComando($result);
            
            return [
                'success' => $success,
                'mensaje' => $success ? 'Servicio MiniDLNA detenido en servidor remoto' : 'Error al detener MiniDLNA remoto: ' . $result,
                'detalle' => $result
            ];
        }
    }
    
    /**
     * Reiniciar servicio MiniDLNA
     */
    public function reiniciarServicio() {
        if ($this->config['dlna_tipo_servidor'] === 'local') {
            $result = shell_exec('sudo systemctl restart minidlna 2>&1');
            $success = $this->verificarComando($result);
            
            return [
                'success' => $success,
                'mensaje' => $success ? 'Servicio MiniDLNA reiniciado correctamente' : 'Error al reiniciar MiniDLNA: ' . $result,
                'detalle' => $result
            ];
        } else {
            $ip = $this->config['dlna_ip_servidor'];
            $result = $this->ejecutarSSH($ip, 'sudo systemctl restart minidlna 2>&1');
            $success = $this->verificarComando($result);
            
            return [
                'success' => $success,
                'mensaje' => $success ? 'Servicio MiniDLNA reiniciado en servidor remoto' : 'Error al reiniciar MiniDLNA remoto: ' . $result,
                'detalle' => $result
            ];
        }
    }
    
    /**
     * Verificar configuración de carpetas
     */
    public function verificarConfiguracionCarpetas() {
        $ruta_banners = $this->config['dlna_ruta_banners'];
        
        if ($this->config['dlna_tipo_servidor'] === 'local') {
            // Verificar localmente
            $existe = is_dir($ruta_banners);
            $archivos = $existe ? count(glob($ruta_banners . '*/*.{jpg,jpeg,png,mp4,avi,mkv}', GLOB_BRACE)) : 0;
            $estructura = $this->analizarEstructuraCarpetas($ruta_banners);
            
            return [
                'success' => true,
                'ruta_banners' => $ruta_banners,
                'carpeta_existe' => $existe,
                'total_archivos' => $archivos,
                'estructura' => $estructura,
                'detalles' => $this->obtenerDetallesCarpetas($ruta_banners)
            ];
        } else {
            // Verificar remotamente
            $ip = $this->config['dlna_ip_servidor'];
            $existe = trim($this->ejecutarSSH($ip, "test -d '$ruta_banners' && echo 'existe' || echo 'no_existe'"));
            $archivos = $existe === 'existe' ? intval($this->ejecutarSSH($ip, "find '$ruta_banners' -type f | wc -l")) : 0;
            $estructura = $this->analizarEstructuraCarpetasRemotas($ip, $ruta_banners);
            
            return [
                'success' => true,
                'ruta_banners' => $ruta_banners,
                'carpeta_existe' => $existe === 'existe',
                'total_archivos' => $archivos,
                'estructura' => $estructura,
                'detalles' => $this->obtenerDetallesCarpetasRemotas($ip, $ruta_banners)
            ];
        }
    }
    
    /**
     * Probar conexión al servidor DLNA
     */
    public function probarConexion() {
        $ip = $this->config['dlna_ip_servidor'];
        $puerto = $this->config['dlna_puerto_servidor'];
        
        // Probar ping
        $ping_result = shell_exec("ping -c 1 -W 2 $ip 2>/dev/null");
        $ping_success = strpos($ping_result, '1 received') !== false;
        
        // Probar puerto DLNA
        $port_result = @fsockopen($ip, $puerto, $errno, $errstr, 2);
        $port_success = is_resource($port_result);
        if ($port_success) {
            fclose($port_result);
        }
        
        // Probar puerto SSH
        $ssh_result = @fsockopen($ip, 22, $ssh_errno, $ssh_errstr, 2);
        $ssh_success = is_resource($ssh_result);
        if ($ssh_result) {
            fclose($ssh_result);
        }
        
        // Probar acceso SSH real
        $ssh_test = '';
        if ($this->config['dlna_tipo_servidor'] === 'remoto') {
            $ssh_test = $this->ejecutarSSH($ip, 'echo "SSH_OK" 2>&1');
            $ssh_working = strpos($ssh_test, 'SSH_OK') !== false;
        } else {
            $ssh_working = true; // Local no necesita SSH
        }
        
        return [
            'success' => true,
            'ip' => $ip,
            'puerto' => $puerto,
            'ping_ok' => $ping_success,
            'puerto_ok' => $port_success,
            'ssh_puerto_ok' => $ssh_success,
            'ssh_acceso_ok' => $ssh_working,
            'mensaje' => ($ping_success && $port_success && $ssh_success && $ssh_working) ? 'Conexión completa al servidor DLNA' : 'Problemas de conexión al servidor DLNA',
            'detalles' => [
                'ping' => $ping_success ? 'OK' : 'FAIL',
                'puerto_dlna' => $port_success ? 'OK' : 'FAIL',
                'puerto_ssh' => $ssh_success ? 'OK' : 'FAIL',
                'acceso_ssh' => $ssh_working ? 'OK' : 'FAIL',
                'tipo_servidor' => $this->config['dlna_tipo_servidor']
            ],
            'requisitos' => $this->verificarRequisitosSSH(),
            'diagnostico' => $this->obtenerDiagnosticoCompleto()
        ];
    }
    
    /**
     * Verificar requisitos para control remoto
     */
    private function verificarRequisitosSSH() {
        $requisitos = [];
        
        // Verificar si el servidor es remoto
        if ($this->config['dlna_tipo_servidor'] === 'remoto') {
            // Verificar si hay archivo de clave SSH
            $home_dir = exec('echo $HOME');
            $ssh_key_exists = file_exists($home_dir . '/.ssh/id_rsa.pub');
            
            // Verificar si el usuario puede ejecutar comandos SSH
            $ssh_test = shell_exec('which ssh 2>/dev/null');
            $ssh_available = !empty($ssh_test);
            
            $requisitos[] = [
                'clave_ssh_configurada' => $ssh_key_exists,
                'ssh_disponible' => $ssh_available,
                'acceso_root' => true, // Requiere acceso root
                'firewall_ssh' => true // Requiere puerto 22 abierto
            ];
        } else {
            $requisitos[] = [
                'servidor_local' => true,
                'acceso_directo' => true,
                'sudo_disponible' => true
            ];
        }
        
        return $requisitos;
    }
    
    /**
     * Obtener diagnóstico completo del sistema
     */
    private function obtenerDiagnosticoCompleto() {
        $diagnostico = [];
        
        if ($this->config['dlna_tipo_servidor'] === 'remoto') {
            // Diagnóstico para servidor remoto
            $ip = $this->config['dlna_ip_servidor'];
            
            // Verificar conectividad básica
            $ping = shell_exec("ping -c 1 -W 2 $ip 2>/dev/null");
            $ping_ok = strpos($ping, '1 received') !== false;
            
            // Verificar puertos
            $ssh_port = @fsockopen($ip, 22, $errno, $errstr, 2);
            $ssh_port_ok = is_resource($ssh_port);
            if ($ssh_port) fclose($ssh_port);
            
            $dlna_port = @fsockopen($ip, 8200, $errno, $errstr, 2);
            $dlna_port_ok = is_resource($dlna_port);
            if ($dlna_port) fclose($dlna_port);
            
            // Verificar acceso SSH
            $ssh_test = '';
            $ssh_working = false;
            if ($ssh_port_ok) {
                $ssh_test = $this->ejecutarSSH($ip, 'echo "SSH_ACCESS_OK" 2>&1');
                $ssh_working = strpos($ssh_test, 'SSH_ACCESS_OK') !== false;
            }
            
            // Verificar servicios en servidor remoto
            $ssh_service = '';
            $minidlna_service = '';
            if ($ssh_working) {
                $ssh_service = $this->ejecutarSSH($ip, 'systemctl is-active ssh 2>/dev/null');
                $minidlna_service = $this->ejecutarSSH($ip, 'systemctl is-active minidlna 2>/dev/null');
            }
            
            $diagnostico = [
                'conectividad' => [
                    'ping_ok' => $ping_ok,
                    'ssh_puerto_ok' => $ssh_port_ok,
                    'dlna_puerto_ok' => $dlna_port_ok,
                    'ssh_acceso_ok' => $ssh_working
                ],
                'servicios' => [
                    'ssh_activo' => trim($ssh_service) === 'active',
                    'minidlna_activo' => trim($minidlna_service) === 'active'
                ],
                'recomendaciones' => $this->generarRecomendacionesDiagnostico($ping_ok, $ssh_port_ok, $dlna_port_ok, $ssh_working)
            ];
        } else {
            // Diagnóstico para servidor local
            $ping = shell_exec("ping -c 1 -W 2 127.0.0.1 2>/dev/null");
            $ping_ok = strpos($ping, '1 received') !== false;
            
            $ssh_service = shell_exec('systemctl is-active ssh 2>/dev/null');
            $minidlna_service = shell_exec('systemctl is-active minidlna 2>/dev/null');
            
            $diagnostico = [
                'conectividad' => [
                    'localhost_ok' => $ping_ok
                ],
                'servicios' => [
                    'ssh_activo' => trim($ssh_service) === 'active',
                    'minidlna_activo' => trim($minidlna_service) === 'active'
                ],
                'recomendaciones' => $this->generarRecomendacionesDiagnostico($ping_ok, true, true, true)
            ];
        }
        
        return $diagnostico;
    }
    
    /**
     * Generar recomendaciones basadas en diagnóstico
     */
    private function generarRecomendacionesDiagnostico($ping_ok, $ssh_port_ok, $dlna_port_ok, $ssh_working) {
        $recomendaciones = [];
        
        if (!$ping_ok) {
            $recomendaciones[] = "❌ El servidor DLNA no responde a ping. Verifique la IP y conectividad de red.";
        }
        
        if (!$ssh_port_ok) {
            $recomendaciones[] = "❌ El puerto SSH (22) está cerrado en el servidor DLNA. Configure el firewall.";
        }
        
        if (!$dlna_port_ok) {
            $recomendaciones[] = "❌ El puerto DLNA (8200) está cerrado. Verifique que MiniDLNA esté corriendo.";
        }
        
        if (!$ssh_working) {
            $recomendaciones[] = "❌ No se puede acceder por SSH al servidor DLNA. Configure las claves SSH.";
            $recomendaciones[] = "🔑 Ejecute en el servidor web: ssh-keygen -t rsa -b 2048";
            $recomendaciones[] = "📤 Copie la clave: ssh-copy-id root@IP_DLNA";
            $recomendaciones[] = "🔐 Pruebe la conexión: ssh root@IP_DLNA 'echo OK'";
        }
        
        if ($ping_ok && $ssh_port_ok && $dlna_port_ok && $ssh_working) {
            $recomendaciones[] = "✅ Todos los requisitos están configurados correctamente.";
        }
        
        return $recomendaciones;
    }
    
    // Métodos privados
    private function ejecutarSSH($ip, $comando) {
        // Intentar ejecutar comando SSH (requiere claves configuradas)
        $ssh_cmd = "ssh -o ConnectTimeout=5 -o BatchMode=yes -o StrictHostKeyChecking=no root@$ip '$comando' 2>&1";
        $result = shell_exec($ssh_cmd);
        
        // Log para diagnóstico
        $log_entry = date('Y-m-d H:i:s') . " - SSH to $ip: $comando\n";
        file_put_contents('/tmp/dlna_ssh.log', $log_entry, FILE_APPEND);
        file_put_contents('/tmp/dlna_ssh.log', "Result: $result\n", FILE_APPEND);
        
        return $result;
    }
    
    /**
     * Ejutar comando SSH con credenciales configuradas
     */
    private function ejecutarSSHConCredenciales($ip, $comando) {
        // Usar credenciales configuradas en lugar de root directo
        $user = $this->config['dlna_ssh_user'] ?? 'root';
        $password = $this->config['dlna_ssh_password'] ?? '';
        
        if (!empty($password)) {
            // Usar usuario/contraseña
            $ssh_cmd = "ssh -o ConnectTimeout=5 -o BatchMode=yes -o StrictHostKeyChecking=no $user@$ip '$comando' 2>&1";
            $ssh_cmd = "echo '$password' | " . $ssh_cmd;
        } else {
            // Usar claves SSH (método preferido)
            $ssh_cmd = "ssh -o ConnectTimeout=5 -o BatchMode=yes -o StrictHostKeyChecking=no root@$ip '$comando' 2>&1";
        }
        
        $result = shell_exec($ssh_cmd);
        
        // Log para diagnóstico
        $log_entry = date('Y-m-d H:i:s') . " - SSH to $ip (user: $user): $comando\n";
        file_put_contents('/tmp/dlna_ssh.log', $log_entry, FILE_APPEND);
        file_put_contents('/tmp/dlna_ssh.log', "Result: $result\n", FILE_APPEND);
        
        return $result;
    }
    
    private function verificarComando($result) {
        return empty($result) || strpos($result, 'Failed') === false;
    }
    
    private function obtenerDetalleEstadoLocal() {
        $status = shell_exec('systemctl status minidlna --no-pager --lines=10 2>/dev/null');
        $uptime = shell_exec('ps -o etime= -p minidlna 2>/dev/null | tr -d " "');
        
        return [
            'status_output' => $status,
            'uptime' => $uptime,
            'memoria' => $this->obtenerUsoMemoria(),
            'disco' => $this->obtenerUsoDisco()
        ];
    }
    
    private function obtenerDetalleEstadoRemoto($ip) {
        $status = $this->ejecutarSSH($ip, 'systemctl status minidlna --no-pager --lines=10 2>/dev/null');
        $uptime = $this->ejecutarSSH($ip, 'ps -o etime= -p minidlna 2>/dev/null | tr -d " "');
        
        return [
            'status_output' => $status,
            'uptime' => $uptime,
            'memoria' => $this->obtenerUsoMemoriaRemota($ip),
            'disco' => $this->obtenerUsoDiscoRemoto($ip)
        ];
    }
    
    private function analizarEstructuraCarpetas($ruta) {
        if (!is_dir($ruta)) return [];
        
        $estructura = [];
        $dirs = glob($ruta . '/*', GLOB_ONLYDIR);
        
        foreach ($dirs as $dir) {
            $nombre = basename($dir);
            $archivos = count(glob($dir . '/*.{jpg,jpeg,png,mp4,avi,mkv}', GLOB_BRACE));
            $estructura[$nombre] = [
                'archivos' => $archivos,
                'tamano' => $this->obtenerTamanoDirectorio($dir)
            ];
        }
        
        return $estructura;
    }
    
    private function analizarEstructuraCarpetasRemotas($ip, $ruta) {
        $estructura = [];
        $dirs_output = $this->ejecutarSSH($ip, "find '$ruta' -maxdepth 1 -type d 2>/dev/null");
        $dirs = array_filter(explode("\n", $dirs_output));
        
        foreach ($dirs as $dir) {
            $nombre = basename($dir);
            $archivos = intval($this->ejecutarSSH($ip, "find '$dir' -maxdepth 1 -type f | wc -l"));
            $tamano = trim($this->ejecutarSSH($ip, "du -sh '$dir' 2>/dev/null | cut -f1"));
            
            $estructura[$nombre] = [
                'archivos' => $archivos,
                'tamano' => $tamano
            ];
        }
        
        return $estructura;
    }
    
    private function obtenerDetallesCarpetas($ruta) {
        $recientes = [];
        $archivos = glob($ruta . '*/*.{jpg,jpeg,png,mp4,avi,mkv}', GLOB_BRACE);
        
        usort($archivos, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        $recientes = array_slice($archivos, 0, 10);
        
        return [
            'total_espacio' => disk_total_space(dirname($ruta)),
            'espacio_libre' => disk_free_space(dirname($ruta)),
            'archivos_recientes' => array_map(function($file) {
                return [
                    'nombre' => basename($file),
                    'tamano' => filesize($file),
                    'fecha' => filemtime($file),
                    'tipo' => $this->obtenerTipoArchivo($file)
                ];
            }, $recientes)
        ];
    }
    
    private function obtenerDetallesCarpetasRemotas($ip, $ruta) {
        $recientes_output = $this->ejecutarSSH($ip, "find '$ruta' -type f -printf '%T@ %p\n' 2>/dev/null | sort -nr | head -10");
        $recientes = array_filter(explode("\n", $recientes_output));
        
        $archivos_recientes = [];
        foreach ($recientes as $line) {
            if (empty($line)) continue;
            $parts = explode(' ', $line, 2);
            if (count($parts) === 2) {
                $timestamp = $parts[0];
                $filepath = $parts[1];
                $archivos_recientes[] = [
                    'nombre' => basename($filepath),
                    'fecha' => $timestamp,
                    'tipo' => $this->obtenerTipoArchivo($filepath)
                ];
            }
        }
        
        return [
            'total_espacio' => trim($this->ejecutarSSH($ip, "df -h '$ruta' | tail -1 | awk '{print $2}'")),
            'espacio_libre' => trim($this->ejecutarSSH($ip, "df -h '$ruta' | tail -1 | awk '{print $4}'")),
            'archivos_recientes' => $archivos_recientes
        ];
    }
    
    private function obtenerTipoArchivo($archivo) {
        $extension = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
        if (in_array($extension, ['jpg', 'jpeg', 'png'])) return 'imagen';
        if (in_array($extension, ['mp4', 'avi', 'mkv'])) return 'video';
        return 'desconocido';
    }
    
    private function obtenerTamanoDirectorio($dir) {
        $bytes = 0;
        foreach (glob($dir . '/*') as $file) {
            $bytes += is_file($file) ? filesize($file) : 0;
        }
        return $this->formatearBytes($bytes);
    }
    
    private function obtenerUsoMemoria() {
        $meminfo = shell_exec('free -h 2>/dev/null | grep Mem');
        return trim($meminfo);
    }
    
    private function obtenerUsoMemoriaRemota($ip) {
        return trim($this->ejecutarSSH($ip, 'free -h 2>/dev/null | grep Mem'));
    }
    
    private function obtenerUsoDisco() {
        $ruta = $this->config['dlna_ruta_banners'];
        $diskinfo = shell_exec("df -h '$ruta' 2>/dev/null | tail -1");
        return trim($diskinfo);
    }
    
    private function obtenerUsoDiscoRemoto($ip) {
        $ruta = $this->config['dlna_ruta_banners'];
        return trim($this->ejecutarSSH($ip, "df -h '$ruta' 2>/dev/null | tail -1"));
    }
    
    private function formatearBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
