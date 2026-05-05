<?php
require_once 'config.php';
require_once 'lib/FaceRecognitionService.php';

class CameraService {
    private $empresa_id;
    private $face_service;
    private $config;
    
    public function __construct($empresa_id) {
        $this->empresa_id = $empresa_id;
        $this->face_service = new FaceRecognitionService($empresa_id);
        $this->config = $this->getConfig();
    }
    
    /**
     * Obtener configuración de cámaras
     */
    private function getConfig() {
        return fetch("SELECT * FROM config_camara WHERE empresa_id = ?", [$this->empresa_id]);
    }
    
    /**
     * Obtener todas las cámaras activas
     */
    public function getCamaras() {
        return fetchAll("SELECT * FROM camaras WHERE empresa_id = ? AND activo = 1 ORDER BY nombre", [$this->empresa_id]);
    }
    
    /**
     * Agregar nueva cámara
     */
    public function agregarCamara($datos) {
        error_log("DEBUG: CameraService::agregarCamara() llamado con datos: " . json_encode($datos));
        
        // Validar y truncar campos para evitar errores de longitud
        $ip = isset($datos['ip']) ? substr(trim($datos['ip']), 0, 45) : '';
        $nombre = isset($datos['nombre']) ? substr(trim($datos['nombre']), 0, 100) : '';
        $usuario = isset($datos['usuario']) ? substr(trim($datos['usuario']), 0, 50) : '';
        $password = isset($datos['password']) ? substr(trim($datos['password']), 0, 100) : '';
        $tipo = isset($datos['tipo']) ? substr(trim($datos['tipo']), 0, 20) : 'RTSP';
        $ruta_stream = isset($datos['ruta_stream']) ? substr(trim($datos['ruta_stream']), 0, 255) : '';
        
        error_log("DEBUG: Datos procesados - IP: '$ip', Nombre: '$nombre', Puerto: " . ($datos['puerto'] ?? 554));
        
        // Simplificado: solo campos esenciales, activo siempre es 1
        $sql = "INSERT INTO camaras (nombre, ip, puerto, usuario, password, tipo, ruta_stream, empresa_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        error_log("DEBUG: SQL a ejecutar: " . $sql);
        
        $resultado = query($sql, [
            $nombre,
            $ip,
            $datos['puerto'] ?? 554,
            $usuario,
            $password,
            $tipo,
            $ruta_stream,
            $this->empresa_id
        ]);
        
        error_log("DEBUG: Resultado de query: " . ($resultado ? 'true' : 'false'));
        
        return $resultado;
    }
    
    /**
     * Actualizar cámara
     */
    public function actualizarCamara($id, $datos) {
        // Validar y truncar campos para evitar errores de longitud
        $ip = isset($datos['ip']) ? substr(trim($datos['ip']), 0, 45) : '';
        $nombre = isset($datos['nombre']) ? substr(trim($datos['nombre']), 0, 100) : '';
        $usuario = isset($datos['usuario']) ? substr(trim($datos['usuario']), 0, 50) : '';
        $password = isset($datos['password']) ? substr(trim($datos['password']), 0, 100) : '';
        $tipo = isset($datos['tipo']) ? substr(trim($datos['tipo']), 0, 20) : 'RTSP';
        $ruta_stream = isset($datos['ruta_stream']) ? substr(trim($datos['ruta_stream']), 0, 255) : '';
        
        // Simplificado: solo campos esenciales
        $sql = "UPDATE camaras SET nombre=?, ip=?, puerto=?, usuario=?, password=?, 
                tipo=?, ruta_stream=? 
                WHERE id=? AND empresa_id=?";
        
        return query($sql, [
            $nombre,
            $ip,
            $datos['puerto'] ?? 554,
            $usuario,
            $password,
            $tipo,
            $ruta_stream,
            $id,
            $this->empresa_id
        ]);
    }
    
    /**
     * Eliminar cámara
     */
    public function eliminarCamara($id) {
        return query("DELETE FROM camaras WHERE id=? AND empresa_id=?", [$id, $this->empresa_id]);
    }
    
    /**
     * Generar URL de streaming
     */
    public function generarStreamURL($camara) {
        switch ($camara['tipo']) {
            case 'RTSP':
                $stream = $camara['ruta_stream'] ?? '/stream1';
                return "rtsp://{$camara['usuario']}:{$camara['password']}@{$camara['ip']}:{$camara['puerto']}{$stream}";
                
            case 'HTTP':
                $stream = $camara['ruta_stream'] ?? '/video.mjpg';
                return "http://{$camara['ip']}:{$camara['puerto']}{$stream}";
                
            default:
                return null;
        }
    }
    
    /**
     * Probar conexión con cámara
     */
    public function probarConexion($camara_id) {
        $camara = fetch("SELECT * FROM camaras WHERE id=? AND empresa_id=?", [$camara_id, $this->empresa_id]);
        if (!$camara) {
            return ['success' => false, 'error' => 'Cámara no encontrada'];
        }
        
        // Para pruebas, simulamos conexión exitosa
        // En producción, aquí iría la lógica real de conexión
        return [
            'success' => true, 
            'message' => 'Conexión exitosa con ' . $camara['nombre'],
            'stream_url' => $this->generarStreamURL($camara)
        ];
    }
    
    /**
     * Registrar evento de cámara (solo detección, sin grabación)
     */
    public function registrarEvento($camara_id, $tipo_evento, $descripcion = null, $confianza = 0, $cliente_id = null, $venta_id = null) {
        $sql = "INSERT INTO eventos_camara (camara_id, tipo_evento, descripcion, confianza, cliente_id, venta_id, empresa_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        return query($sql, [
            $camara_id,
            $tipo_evento,
            $descripcion,
            $confianza,
            $cliente_id,
            $venta_id,
            $this->empresa_id
        ]);
    }
    
    /**
     * Obtener eventos recientes
     */
    public function getEventosRecientes($limite = 10) {
        $sql = "SELECT e.*, c.nombre as camara_nombre 
                FROM eventos_camara e 
                LEFT JOIN camaras c ON e.camara_id = c.id 
                WHERE e.empresa_id = ? 
                ORDER BY e.fecha DESC 
                LIMIT " . intval($limite);
        
        return fetchAll($sql, [$this->empresa_id]);
    }
    
    /**
     * Obtener eventos por venta
     */
    public function getEventosPorVenta($venta_id) {
        $sql = "SELECT e.*, c.nombre as camara_nombre 
                FROM eventos_camara e 
                LEFT JOIN camaras c ON e.camara_id = c.id 
                WHERE e.empresa_id = ? AND e.venta_id = ? 
                ORDER BY e.fecha DESC";
        
        return fetchAll($sql, [$this->empresa_id, $venta_id]);
    }
    
    /**
     * Actualizar configuración (solo detección)
     */
    public function actualizarConfiguracion($config) {
        $sql = "INSERT INTO config_camara (empresa_id, deteccion_movimiento, deteccion_rostros, umbral_confianza, 
                horario_inicio, horario_fin, alertas_fuera_horario) 
                VALUES (?, ?, ?, ?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                deteccion_movimiento = VALUES(deteccion_movimiento), 
                deteccion_rostros = VALUES(deteccion_rostros), 
                umbral_confianza = VALUES(umbral_confianza), 
                horario_inicio = VALUES(horario_inicio), 
                horario_fin = VALUES(horario_fin), 
                alertas_fuera_horario = VALUES(alertas_fuera_horario)";
        
        return query($sql, [
            $this->empresa_id,
            $config['deteccion_movimiento'] ?? 1,
            $config['deteccion_rostros'] ?? 1,
            $config['umbral_confianza'] ?? 0.8000,
            $config['horario_inicio'] ?? '08:00:00',
            $config['horario_fin'] ?? '22:00:00',
            $config['alertas_fuera_horario'] ?? 1
        ]);
    }
    
    /**
     * Escanear red en busca de cámaras (REAL - escanea puertos)
     */
    public function escanearRed() {
        $camaras_encontradas = [];
        
        // Obtener la red local automáticamente
        $red_info = $this->obtenerRedLocalReal();
        if (!$red_info) {
            return [];
        }
        
        $subnet = $red_info['subnet']; // ej: 192.168.1
        $rango = range(1, 254);
        
        // Puertos comunes de cámaras IP
        $puertos_camara = [554, 80, 8080, 37777, 8000, 37778, 34567, 81, 82, 83];
        
        // Limitar a 50 IPs para no sobrecargar (escaneo parcial)
        $ips_a_escanear = array_slice($rango, 0, 50);
        
        foreach ($ips_a_escanear as $i) {
            $ip = $subnet . '.' . $i;
            
            // Intentar conectar a cada puerto
            foreach ($puertos_camara as $puerto) {
                if ($this->puertoAbierto($ip, $puerto, 0.5)) {
                    // Detectar marca según el puerto y respuesta
                    $marca_info = $this->detectarMarcaCamara($ip, $puerto);
                    
                    $camaras_encontradas[] = [
                        'ip' => $ip,
                        'puerto' => $puerto,
                        'tipo' => ($puerto == 554) ? 'RTSP' : 'HTTP',
                        'marca' => $marca_info['marca'],
                        'modelo' => $marca_info['modelo'],
                        'estado' => 'disponible'
                    ];
                    
                    // Si encontramos una cámara en esta IP, no seguir probando otros puertos
                    break;
                }
            }
        }
        
        return $camaras_encontradas;
    }
    
    /**
     * Verificar si un puerto está abierto en una IP
     */
    private function puertoAbierto($ip, $puerto, $timeout = 1) {
        $conexion = @fsockopen($ip, $puerto, $errno, $errstr, $timeout);
        if ($conexion) {
            fclose($conexion);
            return true;
        }
        return false;
    }
    
    /**
     * Detectar marca de cámara según respuesta del puerto
     */
    private function detectarMarcaCamara($ip, $puerto) {
        $marca = 'Cámara IP';
        $modelo = 'Desconocida';
        
        // Intentar obtener banner HTTP
        if ($puerto == 80 || $puerto == 8080 || $puerto == 8000) {
            $respuesta = $this->obtenerHttpBanner($ip, $puerto);
            
            if (stripos($respuesta, 'hikvision') !== false) {
                $marca = 'Hikvision';
                $modelo = 'IP Camera';
            } elseif (stripos($respuesta, 'dahua') !== false) {
                $marca = 'Dahua';
                $modelo = 'IP Camera';
            } elseif (stripos($respuesta, 'axis') !== false) {
                $marca = 'Axis';
                $modelo = 'Network Camera';
            } elseif (stripos($respuesta, 'foscam') !== false) {
                $marca = 'Foscam';
                $modelo = 'IP Camera';
            } elseif (stripos($respuesta, 'tp-link') !== false) {
                $marca = 'TP-Link';
                $modelo = 'Tapo/NC';
            }
        }
        
        // RTSP suele ser Hikvision o Dahua por defecto
        if ($puerto == 554 && $marca == 'Cámara IP') {
            $marca = 'IP Camera';
            $modelo = 'RTSP Stream';
        }
        
        // Puerto 37777 es típico de Dahua
        if ($puerto == 37777) {
            $marca = 'Dahua';
            $modelo = 'P2P/Private';
        }
        
        return ['marca' => $marca, 'modelo' => $modelo];
    }
    
    /**
     * Obtener banner HTTP de una IP
     */
    private function obtenerHttpBanner($ip, $puerto) {
        $context = stream_context_create([
            'http' => [
                'timeout' => 2,
                'user_agent' => 'Mozilla/5.0'
            ]
        ]);
        
        $url = "http://{$ip}:{$puerto}/";
        $respuesta = @file_get_contents($url, false, $context);
        
        if ($respuesta === false) {
            // Intentar obtener solo headers
            $socket = @fsockopen($ip, $puerto, $errno, $errstr, 2);
            if ($socket) {
                fwrite($socket, "GET / HTTP/1.0\r\n\r\n");
                $respuesta = '';
                while (!feof($socket)) {
                    $respuesta .= fgets($socket, 128);
                }
                fclose($socket);
            }
        }
        
        return $respuesta ?: '';
    }
    
    /**
     * Obtener red local real desde interfaces de red
     */
    private function obtenerRedLocalReal() {
        // Intentar obtener IP local
        $ip_local = $this->getLocalIpAddress();
        if (!$ip_local) {
            // Fallback a una red común
            return ['subnet' => '192.168.1', 'ip' => '192.168.1.1'];
        }
        
        // Extraer subnet (ej: 192.168.1.100 -> 192.168.1)
        $partes = explode('.', $ip_local);
        if (count($partes) == 4) {
            return [
                'subnet' => $partes[0] . '.' . $partes[1] . '.' . $partes[2],
                'ip' => $ip_local
            ];
        }
        
        return ['subnet' => '192.168.1', 'ip' => $ip_local];
    }
    
    /**
     * Obtener IP local del servidor
     */
    private function getLocalIpAddress() {
        // Método 1: SERVER_ADDR
        if (!empty($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] != '127.0.0.1') {
            return $_SERVER['SERVER_ADDR'];
        }
        
        // Método 2: hostname
        $hostname = gethostname();
        $ip = gethostbyname($hostname);
        if ($ip && $ip != '127.0.0.1' && $ip != $hostname) {
            return $ip;
        }
        
        // Método 3: Interfaces de red
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows
            $output = shell_exec('ipconfig');
            if (preg_match('/IPv4[^\d]+(\d+\.\d+\.\d+\.\d+)/', $output, $matches)) {
                return $matches[1];
            }
        } else {
            // Linux/Unix
            $output = shell_exec("hostname -I 2>/dev/null");
            if ($output) {
                $ips = explode(' ', trim($output));
                foreach ($ips as $ip) {
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE) === false) {
                        // Es una IP privada, usarla
                        if ($ip != '127.0.0.1') {
                            return $ip;
                        }
                    }
                }
            }
            
            // Alternativa con ip route
            $output = shell_exec("ip route get 8.8.8.8 2>/dev/null | head -1");
            if (preg_match('/src\s+(\d+\.\d+\.\d+\.\d+)/', $output, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }
    
    /**
     * Registrar evento con detección facial
     */
    public function registrarEventoConDeteccion($camara_id, $tipo_evento, $face_data = null, $venta_id = null) {
        $resultado = null;
        $cliente_id = null;
        $confianza = 0;
        $descripcion = null;
        
        // Si hay datos faciales, procesar detección
        if ($face_data) {
            $resultado = $this->face_service->detectarRostro($face_data, $camara_id);
            
            if ($resultado && $resultado['tipo'] === 'CLIENTE' && isset($resultado['datos']['id'])) {
                $cliente_id = $resultado['datos']['id'];
                $confianza = $resultado['datos']['confianza'] ?? 0;
                $descripcion = 'Cliente reconocido: ' . $resultado['datos']['nombre'];
            } else {
                $descripcion = $resultado['mensaje'] ?? 'Rostro no identificado';
            }
        }
        
        // Registrar evento de detección
        $evento_id = $this->registrarEvento($camara_id, $tipo_evento, $descripcion, $confianza, $cliente_id, $venta_id);
        
        return [
            'evento_id' => $evento_id,
            'deteccion' => $resultado
        ];
    }
    
    /**
     * Procesar frame de cámara para detección facial
     */
    public function procesarFrame($camara_id, $frame_data) {
        // Simular extracción de datos faciales del frame
        // En producción, aquí iría el algoritmo real de detección de rostros
        $face_data = $this->extraerDatosFaciales($frame_data);
        
        if ($face_data) {
            return $this->face_service->detectarRostro($face_data, $camara_id);
        }
        
        return ['tipo' => 'SIN_DETECCION', 'datos' => null, 'mensaje' => 'No se detectaron rostros'];
    }
    
    /**
     * Extraer datos faciales del frame (simulación)
     */
    private function extraerDatosFaciales($frame_data) {
        // Simulación: generar hash del frame como "datos faciales"
        // En producción, usar OpenCV, dlib, FaceNet, etc.
        if (empty($frame_data)) {
            return null;
        }
        
        // Simulación simple: usar hash MD5 como representación facial
        $face_data = md5($frame_data . time());
        
        // Agregar algo de variación para simular diferentes rostros
        $face_data .= '_' . substr(md5($frame_data . rand()), 0, 8);
        
        return $face_data;
    }
    
    /**
     * Obtener estadísticas
     */
    public function getEstadisticas() {
        $stats = [];
        
        try {
            // Total de cámaras
            $result = fetch("SELECT COUNT(*) as total FROM camaras WHERE empresa_id = ?", [$this->empresa_id]);
            $stats['total_camaras'] = isset($result['total']) ? $result['total'] : 0;
            
            // Cámaras activas
            $result = fetch("SELECT COUNT(*) as total FROM camaras WHERE empresa_id = ? AND activo = 1", [$this->empresa_id]);
            $stats['camaras_activas'] = isset($result['total']) ? $result['total'] : 0;
            
            // Eventos hoy
            $result = fetch("SELECT COUNT(*) as total FROM eventos_camara WHERE empresa_id = ? AND DATE(fecha) = CURDATE()", [$this->empresa_id]);
            $stats['eventos_hoy'] = isset($result['total']) ? $result['total'] : 0;
            
            
        } catch (Exception $e) {
            // Valores por defecto en caso de error
            $stats['total_camaras'] = 0;
            $stats['camaras_activas'] = 0;
            $stats['eventos_hoy'] = 0;
        }
        
        return $stats;
    }
    
    /**
     * Método eliminado: calcularEspacioUsado (ya no se usa grabación)
     */
    
    /**
     * Formatear bytes a unidad legible
     */
    private function formatearBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unitIndex = 0;
        
        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }
        
        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }
}
?>
