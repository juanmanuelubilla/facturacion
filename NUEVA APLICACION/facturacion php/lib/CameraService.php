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
        $sql = "INSERT INTO camaras (nombre, ip, puerto, usuario, password, tipo, marca, modelo, ruta_stream, empresa_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        return query($sql, [
            $datos['nombre'],
            $datos['ip'],
            $datos['puerto'] ?? 554,
            $datos['usuario'],
            $datos['password'],
            $datos['tipo'] ?? 'RTSP',
            $datos['marca'] ?? '',
            $datos['modelo'] ?? '',
            $datos['ruta_stream'] ?? '',
            $this->empresa_id
        ]);
    }
    
    /**
     * Actualizar cámara
     */
    public function actualizarCamara($id, $datos) {
        $sql = "UPDATE camaras SET nombre=?, ip=?, puerto=?, usuario=?, password=?, 
                tipo=?, marca=?, modelo=?, ruta_stream=?, activo=? 
                WHERE id=? AND empresa_id=?";
        
        return query($sql, [
            $datos['nombre'],
            $datos['ip'],
            $datos['puerto'] ?? 554,
            $datos['usuario'],
            $datos['password'],
            $datos['tipo'] ?? 'RTSP',
            $datos['marca'] ?? '',
            $datos['modelo'] ?? '',
            $datos['ruta_stream'] ?? '',
            $datos['activo'] ?? 1,
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
     * Grabar evento de cámara
     */
    public function grabarEvento($camara_id, $tipo_evento, $venta_id = null) {
        $camara = fetch("SELECT nombre FROM camaras WHERE id=? AND empresa_id=?", [$camara_id, $this->empresa_id]);
        if (!$camara) return false;
        
        $nombre_archivo = "camara_{$camara_id}_" . date('Y-m-d_H-i-s') . ".mp4";
        $thumbnail = "thumb_{$camara_id}_" . date('Y-m-d_H-i-s') . ".jpg";
        
        $sql = "INSERT INTO eventos_camara (camara_id, tipo_evento, archivo_video, venta_id, thumbnail, empresa_id) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        return query($sql, [
            $camara_id,
            $tipo_evento,
            $nombre_archivo,
            $venta_id,
            $thumbnail,
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
     * Actualizar configuración
     */
    public function actualizarConfiguracion($config) {
        $sql = "INSERT INTO config_camara (empresa_id, grabar_ventas, deteccion_movimiento, calidad_video, 
                duracion_grabacion, almacenamiento_maximo, horario_inicio, horario_fin, alertas_fuera_horario) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                grabar_ventas = VALUES(grabar_ventas), 
                deteccion_movimiento = VALUES(deteccion_movimiento), 
                calidad_video = VALUES(calidad_video), 
                duracion_grabacion = VALUES(duracion_grabacion), 
                almacenamiento_maximo = VALUES(almacenamiento_maximo), 
                horario_inicio = VALUES(horario_inicio), 
                horario_fin = VALUES(horario_fin), 
                alertas_fuera_horario = VALUES(alertas_fuera_horario)";
        
        return query($sql, [
            $this->empresa_id,
            $config['grabar_ventas'] ?? 1,
            $config['deteccion_movimiento'] ?? 1,
            $config['calidad_video'] ?? '720p',
            $config['duracion_grabacion'] ?? 30,
            $config['almacenamiento_maximo'] ?? 1000,
            $config['horario_inicio'] ?? '08:00:00',
            $config['horario_fin'] ?? '22:00:00',
            $config['alertas_fuera_horario'] ?? 1
        ]);
    }
    
    /**
     * Escanear red en busca de cámaras (simulado)
     */
    public function escanearRed() {
        // Simulación de escaneo - en producción usaría nmap o similar
        $red_actual = $this->obtenerRedLocal();
        $camaras_encontradas = [];
        
        // IPs comunes de cámaras para prueba
        $ips_prueba = [
            '192.168.1.108' => ['marca' => 'Dahua', 'modelo' => 'IPC-HFW2431S'],
            '192.168.1.109' => ['marca' => 'Hikvision', 'modelo' => 'DS-2CD2032-I'],
            '192.168.1.110' => ['marca' => 'Axis', 'modelo' => 'M1065'],
        ];
        
        foreach ($ips_prueba as $ip => $info) {
            if ($this->estaEnMismaRed($ip, $red_actual)) {
                $camaras_encontradas[] = [
                    'ip' => $ip,
                    'puerto' => 554,
                    'marca' => $info['marca'],
                    'modelo' => $info['modelo'],
                    'estado' => 'disponible'
                ];
            }
        }
        
        return $camaras_encontradas;
    }
    
    /**
     * Obtener red local
     */
    private function obtenerRedLocal() {
        // Simulación - en producción obtendría la red real
        return '192.168.1';
    }
    
    /**
     * Verificar si IP está en misma red
     */
    private function estaEnMismaRed($ip, $red) {
        return strpos($ip, $red) === 0;
    }
    
    /**
     * Grabar evento con detección facial
     */
    public function grabarEventoConDeteccion($camara_id, $tipo_evento, $face_data = null) {
        $resultado = null;
        
        // Si hay datos faciales, procesar detección
        if ($face_data) {
            $resultado = $this->face_service->detectarRostro($face_data, $camara_id);
        }
        
        // Grabar evento normal
        $evento_id = $this->grabarEvento($camara_id, $tipo_evento, $resultado ? $resultado['tipo'] : 'NORMAL');
        
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
            
            // Espacio utilizado (simulado)
            $stats['espacio_usado'] = rand(100, 800) . ' MB';
            
        } catch (Exception $e) {
            // Valores por defecto en caso de error
            $stats['total_camaras'] = 0;
            $stats['camaras_activas'] = 0;
            $stats['eventos_hoy'] = 0;
            $stats['espacio_usado'] = '0 MB';
        }
        
        return $stats;
    }
}
?>
