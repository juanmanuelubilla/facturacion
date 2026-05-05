<?php
/**
 * Camera Events API - Versión Simplificada
 * Compatible con la configuración actual del sistema
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config.php';

class CameraEventsAPI {
    private $empresa_id;
    
    public function __construct() {
        // session_start() ya fue llamado en config.php
        $this->empresa_id = isset($_SESSION['empresa_id']) ? $_SESSION['empresa_id'] : 1;
    }
    
    public function handleRequest() {
        $action = $_GET['action'] ?? '';
        
        switch ($action) {
            case 'status':
                return $this->getCameraStatus();
            case 'analytics':
                return $this->getAnalytics();
            case 'recent_activity':
                return $this->getRecentActivity();
            default:
                return ['error' => 'Acción no válida'];
        }
    }
    
    private function getCameraStatus() {
        try {
            // Obtener cámaras activas
            $cameras = fetchAll("SELECT id, nombre, ip, ruta_stream, activo FROM camaras WHERE empresa_id = ? AND activo = 1", [$this->empresa_id]);
            
            $camera_status = [];
            foreach ($cameras as $camera) {
                $status = [
                    'id' => $camera['id'],
                    'nombre' => $camera['nombre'],
                    'ip' => $camera['ip'],
                    'estado' => 'desconectado', // Por defecto
                    'ultima_grabacion' => null,
                    'alertas_activas' => 0
                ];
                
                // Para sistema sin grabación, considerar conectada si está activa
                // y tiene configuración de stream
                if ($camera['ruta_stream'] && !empty($camera['ip'])) {
                    $status['estado'] = 'conectado';
                    
                    // Verificar si hay eventos recientes para más precisión
                    $last_event = fetch("SELECT fecha FROM eventos_camara WHERE camara_id = ? AND empresa_id = ? ORDER BY fecha DESC LIMIT 1", [$camera['id'], $this->empresa_id]);
                    if ($last_event) {
                        $status['ultimo_evento'] = $last_event['fecha'];
                    }
                }
                
                // Contar alertas recientes
                $alert_count = fetch("SELECT COUNT(*) as total FROM security_alerts WHERE camera_id = ? AND DATE(timestamp) = CURDATE()", [$camera['id']]);
                $status['alertas_activas'] = $alert_count['total'] ?? 0;
                
                $camera_status[] = $status;
            }
            
            return [
                'success' => true,
                'camera_status' => $camera_status,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function getAnalytics() {
        try {
            $period = $_GET['period'] ?? '1h';
            $time_condition = $this->getTimeCondition($period);
            
            // Estadísticas básicas
            $stats = [
                'total_camaras' => fetch("SELECT COUNT(*) as total FROM camaras WHERE empresa_id = ?", [$this->empresa_id])['total'] ?? 0,
                'camaras_activas' => fetch("SELECT COUNT(*) as total FROM camaras WHERE empresa_id = ? AND activo = 1", [$this->empresa_id])['total'] ?? 0,
                'grabaciones_periodo' => fetch("SELECT COUNT(*) as total FROM camera_recording_events WHERE camera_id IN (SELECT id FROM camaras WHERE empresa_id = ?) AND $time_condition", [$this->empresa_id])['total'] ?? 0,
                'alertas_periodo' => fetch("SELECT COUNT(*) as total FROM security_alerts WHERE camera_id IN (SELECT id FROM camaras WHERE empresa_id = ?) AND $time_condition", [$this->empresa_id])['total'] ?? 0,
                'eventos_camara' => fetch("SELECT COUNT(*) as total FROM eventos_camara WHERE empresa_id = ? AND $time_condition", [$this->empresa_id])['total'] ?? 0
            ];
            
            return [
                'success' => true,
                'analytics' => $stats,
                'period' => $period,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function getRecentActivity() {
        try {
            // Eventos recientes de cámaras (reconocimiento facial)
            $camera_events = fetchAll("
                SELECT ce.*, c.nombre as camera_nombre 
                FROM eventos_camara ce 
                JOIN camaras c ON ce.camara_id = c.id 
                WHERE ce.empresa_id = ? 
                ORDER BY ce.fecha DESC 
                LIMIT 10
            ", [$this->empresa_id]);
            
            // Grabaciones recientes (vacío - sistema sin grabación)
            $recordings = [];
            
            // Alertas recientes
            $alerts = fetchAll("
                SELECT sa.*, c.nombre as camera_nombre 
                FROM security_alerts sa 
                JOIN camaras c ON sa.camera_id = c.id 
                WHERE c.empresa_id = ? 
                ORDER BY sa.timestamp DESC 
                LIMIT 5
            ", [$this->empresa_id]);
            
            return [
                'success' => true,
                'camera_events' => $camera_events,
                'recordings' => $recordings,
                'alerts' => $alerts,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function getTimeCondition($period) {
        switch ($period) {
            case '1h':
                return "timestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR)";
            case '24h':
                return "timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
            case '7d':
                return "timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            default:
                return "timestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR)";
        }
    }
}

// Procesar la solicitud
$api = new CameraEventsAPI();
echo json_encode($api->handleRequest());
?>
