<?php
/**
 * Camera Events API
 * Integrates the camera daemon with the sales system
 * Handles real-time events for automatic recording and analysis
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../lib/Database.php';
require_once '../lib/CameraService.php';
require_once '../lib/EmpresaFiles.php';

class CameraEventsAPI {
    private $db;
    private $camera_service;
    private $redis;
    private $logger;
    
    public function __construct() {
        $this->db = new Database();
        $this->camera_service = new CameraService($this->getEmpresaId());
        $this->redis = $this->connectRedis();
        $this->logger = $this->createLogger();
    }
    
    private function getEmpresaId() {
        // Get empresa_id from session or default to 1
        return isset($_SESSION['empresa_id']) ? $_SESSION['empresa_id'] : 1;
    }
    
    private function connectRedis() {
        try {
            $redis = new Redis();
            $redis->connect('127.0.0.1', 6379);
            return $redis;
        } catch (Exception $e) {
            $this->log('ERROR', 'Redis connection failed: ' . $e->getMessage());
            return null;
        }
    }
    
    private function createLogger() {
        $log_file = __DIR__ . '/../camera_daemon/logs/api.log';
        $log_dir = dirname($log_file);
        
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        return [
            'log' => function($level, $message) use ($log_file) {
                $timestamp = date('Y-m-d H:i:s');
                $log_entry = "[$timestamp] [$level] $message" . PHP_EOL;
                file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
            }
        ];
    }
    
    private function log($level, $message) {
        if ($this->logger) {
            $this->logger['log']($level, $message);
        }
    }
    
    /**
     * Handle API requests
     */
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $action = $_GET['action'] ?? '';
        
        try {
            switch ($method) {
                case 'POST':
                    $this->handlePostRequest($action);
                    break;
                case 'GET':
                    $this->handleGetRequest($action);
                    break;
                default:
                    $this->sendError('Method not allowed', 405);
            }
        } catch (Exception $e) {
            $this->log('ERROR', 'API Error: ' . $e->getMessage());
            $this->sendError('Internal server error', 500);
        }
    }
    
    /**
     * Handle POST requests
     */
    private function handlePostRequest($action) {
        switch ($action) {
            case 'sale_completed':
                $this->handleSaleCompleted();
                break;
            case 'manual_recording':
                $this->handleManualRecording();
                break;
            case 'face_registration':
                $this->handleFaceRegistration();
                break;
            case 'face_recognition':
                $this->handleFaceRecognition();
                break;
            case 'alert_acknowledged':
                $this->handleAlertAcknowledged();
                break;
            default:
                $this->sendError('Invalid action', 400);
        }
    }
    
    /**
     * Handle GET requests
     */
    private function handleGetRequest($action) {
        switch ($action) {
            case 'status':
                $this->getSystemStatus();
                break;
            case 'alerts':
                $this->getAlerts();
                break;
            case 'analytics':
                $this->getAnalytics();
                break;
            case 'recordings':
                $this->getRecordings();
                break;
            default:
                $this->sendError('Invalid action', 400);
        }
    }
    
    /**
     * Handle sale completed event
     */
    private function handleSaleCompleted() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            $this->sendError('Invalid JSON input', 400);
        }
        
        // Validate required fields
        $required_fields = ['sale_id', 'total_amount', 'customer_info'];
        foreach ($required_fields as $field) {
            if (!isset($input[$field])) {
                $this->sendError("Missing required field: $field", 400);
            }
        }
        
        $sale_data = [
            'sale_id' => $input['sale_id'],
            'total_amount' => floatval($input['total_amount']),
            'customer_info' => $input['customer_info'],
            'timestamp' => date('Y-m-d H:i:s'),
            'empresa_id' => $this->getEmpresaId(),
            'user_id' => $_SESSION['user_id'] ?? null
        ];
        
        // Store sale event in database
        $this->storeSaleEvent($sale_data);
        
        // Send to Redis queue for daemon (solo detección, sin grabación)
        $this->queueSaleEvent($sale_data);
        
        $this->sendResponse([
            'success' => true,
            'message' => 'Sale event processed successfully',
            'sale_id' => $sale_data['sale_id']
        ]);
    }
    
    /**
     * Store sale event in database
     */
    private function storeSaleEvent($sale_data) {
        try {
            $sql = "INSERT INTO camera_sale_events 
                    (sale_id, empresa_id, total_amount, customer_info, 
                     timestamp, user_id, processed) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $sale_data['sale_id'],
                $sale_data['empresa_id'],
                $sale_data['total_amount'],
                json_encode($sale_data['customer_info']),
                $sale_data['timestamp'],
                $sale_data['user_id'],
                1  // processed
            ];
            
            query($sql, $params);
            $this->log('INFO', "Sale event stored: {$sale_data['sale_id']}");
            
        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to store sale event: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Handle face recognition event
     */
    private function handleFaceRecognition() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            $this->sendError('Invalid JSON data');
        }
        
        $camera_id = $input['camera_id'] ?? null;
        $face_data = $input['face_data'] ?? null;
        $confidence = $input['confidence'] ?? 0;
        $timestamp = $input['timestamp'] ?? date('Y-m-d H:i:s');
        
        if (!$camera_id || !$face_data) {
            $this->sendError('Missing required fields: camera_id, face_data');
        }
        
        try {
            // Usar el servicio de reconocimiento facial existente
            require_once '../lib/FaceRecognitionService.php';
            $face_service = new FaceRecognitionService($this->getEmpresaId());
            
            // Detectar rostro
            $result = $face_service->detectarRostro($face_data, $camera_id);
            
            if ($result['tipo'] === 'CLIENTE' && isset($result['datos']['id'])) {
                // Enviar evento al frontend via WebSocket/SSE
                $this->sendFaceRecognitionEvent($result['datos']['id'], $camera_id, $confidence, $timestamp);
                
                $this->sendResponse([
                    'success' => true,
                    'type' => 'client_recognized',
                    'cliente_id' => $result['datos']['id'],
                    'cliente_nombre' => $result['datos']['nombre'],
                    'confidence' => $confidence,
                    'timestamp' => $timestamp
                ]);
            } else {
                $this->sendResponse([
                    'success' => true,
                    'type' => 'face_detected',
                    'recognition_type' => $result['tipo'],
                    'confidence' => $confidence,
                    'timestamp' => $timestamp
                ]);
            }
            
        } catch (Exception $e) {
            $this->log('ERROR', 'Face recognition failed: ' . $e->getMessage());
            $this->sendError('Face recognition failed', 500);
        }
    }
    
    /**
     * Send face recognition event to frontend
     */
    private function sendFaceRecognitionEvent($cliente_id, $camera_id, $confidence, $timestamp) {
        // Publicar evento en Redis para WebSocket/SSE
        if ($this->redis) {
            $event_data = [
                'type' => 'face_recognition',
                'cliente_id' => $cliente_id,
                'camera_id' => $camera_id,
                'confidence' => $confidence,
                'timestamp' => $timestamp,
                'empresa_id' => $this->getEmpresaId()
            ];
            
            $this->redis->publish('camera_events', json_encode($event_data));
            $this->redis->setex("last_face_recognition:{$this->getEmpresaId()}", 300, json_encode($event_data));
        }
        
        $this->log('INFO', "Face recognition event: Cliente {$cliente_id} detected on camera {$camera_id}");
    }
    
    /**
     * Método eliminado: triggerCameraRecording (ya no se usa grabación)
     */
    
    /**
     * Store recording event
     */
    private function storeRecordingEvent($event_data) {
        try {
            $sql = "INSERT INTO camera_recording_events 
                    (camera_id, event_type, trigger_data, duration, timestamp) 
                    VALUES (?, ?, ?, ?, ?)";
            
            $params = [
                $event_data['camera_id'],
                $event_data['event_type'],
                json_encode($event_data['trigger_data']),
                $event_data['duration'],
                $event_data['timestamp']
            ];
            
            query($sql, $params);
            
        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to store recording event: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Queue sale event for daemon
     */
    private function queueSaleEvent($sale_data) {
        if (!$this->redis) {
            $this->log('WARNING', 'Redis not available, skipping queue');
            return;
        }
        
        try {
            $event = [
                'type' => 'sale_completed',
                'data' => $sale_data,
                'timestamp' => time()
            ];
            
            $this->redis->lpush('camera_events:sales', json_encode($event));
            $this->log('INFO', "Sale event queued: {$sale_data['sale_id']}");
            
        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to queue sale event: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle manual recording request
     */
    private function handleManualRecording() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            $this->sendError('Invalid JSON input', 400);
        }
        
        $camera_id = $input['camera_id'] ?? null;
        $action = $input['action'] ?? null; // 'start' or 'stop'
        $duration = $input['duration'] ?? 60; // seconds
        
        if (!$camera_id || !$action) {
            $this->sendError('Missing camera_id or action', 400);
        }
        
        // Validate camera exists and is active
        $camera = $this->camera_service->obtenerCamara($camera_id);
        if (!$camera || !$camera['activo']) {
            $this->sendError('Camera not found or inactive', 404);
        }
        
        $recording_event = [
            'camera_id' => $camera_id,
            'action' => $action,
            'duration' => $duration,
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => $_SESSION['user_id'] ?? null,
            'manual' => true
        ];
        
        // Store manual recording event
        $this->storeRecordingEvent($recording_event);
        
        // Queue for daemon
        if ($this->redis) {
            $this->redis->lpush('camera_events:recording', json_encode($recording_event));
        }
        
        $this->sendResponse([
            'success' => true,
            'message' => "Manual recording $action initiated",
            'camera_id' => $camera_id,
            'action' => $action,
            'duration' => $duration
        ]);
    }
    
    /**
     * Handle face registration
     */
    private function handleFaceRegistration() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            $this->sendError('Invalid JSON input', 400);
        }
        
        $name = $input['name'] ?? null;
        $face_data = $input['face_data'] ?? null; // Base64 encoded image or face encoding
        
        if (!$name || !$face_data) {
            $this->sendError('Missing name or face_data', 400);
        }
        
        $face_id = $this->registerFace($name, $face_data);
        
        if ($face_id) {
            $this->sendResponse([
                'success' => true,
                'message' => 'Face registered successfully',
                'face_id' => $face_id,
                'name' => $name
            ]);
        } else {
            $this->sendError('Failed to register face', 500);
        }
    }
    
    /**
     * Register new face
     */
    private function registerFace($name, $face_data) {
        try {
            $face_id = 'face_' . uniqid();
            
            $sql = "INSERT INTO camera_faces 
                    (face_id, name, face_data, registered_at, empresa_id) 
                    VALUES (?, ?, ?, NOW(), ?)";
            
            $params = [
                $face_id,
                $name,
                $face_data,
                $this->getEmpresaId()
            ];
            
            query($sql, $params);
            
            // Queue for daemon to update face recognition database
            if ($this->redis) {
                $event = [
                    'type' => 'face_registration',
                    'face_id' => $face_id,
                    'name' => $name,
                    'face_data' => $face_data
                ];
                
                $this->redis->lpush('camera_events:faces', json_encode($event));
            }
            
            $this->log('INFO', "Face registered: $face_id - $name");
            return $face_id;
            
        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to register face: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Handle alert acknowledgment
     */
    private function handleAlertAcknowledged() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            $this->sendError('Invalid JSON input', 400);
        }
        
        $alert_id = $input['alert_id'] ?? null;
        $acknowledged_by = $input['acknowledged_by'] ?? $_SESSION['username'] ?? 'system';
        
        if (!$alert_id) {
            $this->sendError('Missing alert_id', 400);
        }
        
        // Update alert in database
        $sql = "UPDATE security_alerts 
                SET acknowledged = TRUE, acknowledged_by = ?, acknowledged_at = NOW() 
                WHERE id = ?";
        
        $result = query($sql, [$acknowledged_by, $alert_id]);
        
        if ($result) {
            // Queue for daemon
            if ($this->redis) {
                $event = [
                    'type' => 'alert_acknowledged',
                    'alert_id' => $alert_id,
                    'acknowledged_by' => $acknowledged_by
                ];
                
                $this->redis->lpush('camera_events:alerts', json_encode($event));
            }
            
            $this->sendResponse([
                'success' => true,
                'message' => 'Alert acknowledged successfully',
                'alert_id' => $alert_id
            ]);
        } else {
            $this->sendError('Failed to acknowledge alert', 500);
        }
    }
    
    /**
     * Get system status
     */
    private function getSystemStatus() {
        try {
            $status = [
                'daemon_status' => $this->getDaemonStatus(),
                'camera_status' => $this->getCameraStatus(),
                'recording_status' => $this->getRecordingStatus(),
                'storage_status' => $this->getStorageStatus(),
                'recent_events' => $this->getRecentEvents()
            ];
            
            $this->sendResponse($status);
            
        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to get system status: ' . $e->getMessage());
            $this->sendError('Failed to get system status', 500);
        }
    }
    
    /**
     * Get daemon status from Redis
     */
    private function getDaemonStatus() {
        if (!$this->redis) {
            return ['status' => 'unknown', 'message' => 'Redis not available'];
        }
        
        try {
            $daemon_info = $this->redis->get('daemon:status');
            if ($daemon_info) {
                return json_decode($daemon_info, true);
            }
            
            return ['status' => 'offline', 'message' => 'Daemon not responding'];
            
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Get camera status
     */
    private function getCameraStatus() {
        try {
            $cameras = $this->camera_service->obtenerCamarasActivas();
            $camera_status = [];
            
            foreach ($cameras as $camera) {
                $camera_status[] = [
                    'id' => $camera['id'],
                    'name' => $camera['nombre'],
                    'ip' => $camera['ip'],
                    'status' => 'online', // Would be checked via daemon
                    'last_seen' => date('Y-m-d H:i:s')
                ];
            }
            
            return $camera_status;
            
        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to get camera status: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get recording status
     */
    private function getRecordingStatus() {
        try {
            if ($this->redis) {
                $recording_info = $this->redis->get('recording:status');
                if ($recording_info) {
                    return json_decode($recording_info, true);
                }
            }
            
            return [
                'active_recordings' => 0,
                'storage_used' => 0,
                'storage_limit' => 100
            ];
            
        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to get recording status: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get storage status
     */
    private function getStorageStatus() {
        try {
            $videos_dir = __DIR__ . '/../videos';
            $total_size = 0;
            $file_count = 0;
            
            if (is_dir($videos_dir)) {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($videos_dir)
                );
                
                foreach ($iterator as $file) {
                    if ($file->isFile() && $file->getExtension() === 'mp4') {
                        $total_size += $file->getSize();
                        $file_count++;
                    }
                }
            }
            
            return [
                'total_files' => $file_count,
                'storage_used_gb' => round($total_size / (1024 * 1024 * 1024), 2),
                'storage_available_gb' => round(100 - ($total_size / (1024 * 1024 * 1024)), 2)
            ];
            
        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to get storage status: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get recent events
     */
    private function getRecentEvents() {
        try {
            $sql = "SELECT * FROM camera_sale_events 
                    ORDER BY timestamp DESC 
                    LIMIT 10";
            
            $events = query($sql);
            
            $recent_events = [];
            foreach ($events as $event) {
                $recent_events[] = [
                    'id' => $event['id'],
                    'type' => 'sale',
                    'sale_id' => $event['sale_id'],
                    'timestamp' => $event['timestamp'],
                    'total_amount' => $event['total_amount']
                ];
            }
            
            return $recent_events;
            
        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to get recent events: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get alerts
     */
    private function getAlerts() {
        try {
            $camera_id = $_GET['camera_id'] ?? null;
            $limit = intval($_GET['limit'] ?? 50);
            
            $sql = "SELECT * FROM security_alerts";
            $params = [];
            
            if ($camera_id) {
                $sql .= " WHERE camera_id = ?";
                $params[] = $camera_id;
            }
            
            $sql .= " ORDER BY timestamp DESC LIMIT ?";
            $params[] = $limit;
            
            $alerts = query($sql, $params);
            
            $alert_list = [];
            foreach ($alerts as $alert) {
                $alert_list[] = [
                    'id' => $alert['id'],
                    'camera_id' => $alert['camera_id'],
                    'alert_type' => $alert['alert_type'],
                    'severity' => $alert['severity'],
                    'title' => $alert['description'],
                    'timestamp' => $alert['timestamp'],
                    'acknowledged' => $alert['acknowledged'],
                    'details' => json_decode($alert['details_json'], true)
                ];
            }
            
            $this->sendResponse($alert_list);
            
        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to get alerts: ' . $e->getMessage());
            $this->sendError('Failed to get alerts', 500);
        }
    }
    
    /**
     * Get analytics data
     */
    private function getAnalytics() {
        try {
            $period = $_GET['period'] ?? '24h'; // 1h, 24h, 7d, 30d
            $camera_id = $_GET['camera_id'] ?? null;
            
            // Convert period to hours
            $hours_map = [
                '1h' => 1,
                '24h' => 24,
                '7d' => 168,
                '30d' => 720
            ];
            
            $hours = $hours_map[$period] ?? 24;
            
            $analytics = [
                'people_count' => $this->getPeopleCountAnalytics($camera_id, $hours),
                'face_detections' => $this->getFaceDetectionAnalytics($camera_id, $hours),
                'alerts' => $this->getAlertAnalytics($camera_id, $hours),
                'recordings' => $this->getRecordingAnalytics($camera_id, $hours)
            ];
            
            $this->sendResponse($analytics);
            
        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to get analytics: ' . $e->getMessage());
            $this->sendError('Failed to get analytics', 500);
        }
    }
    
    /**
     * Get people count analytics
     */
    private function getPeopleCountAnalytics($camera_id, $hours) {
        try {
            $sql = "SELECT DATE_FORMAT(timestamp, '%Y-%m-%d %H:00:00') as hour_bucket,
                           AVG(CASE WHEN class_name = 'person' THEN 1 ELSE 0 END) as avg_people,
                           COUNT(CASE WHEN class_name = 'person' THEN 1 END) as total_detections
                    FROM object_detections 
                    WHERE timestamp >= DATE_SUB(NOW(), INTERVAL ? HOUR)";
            
            $params = [$hours];
            
            if ($camera_id) {
                $sql .= " AND camera_id = ?";
                $params[] = $camera_id;
            }
            
            $sql .= " GROUP BY hour_bucket ORDER BY hour_bucket";
            
            $results = query($sql, $params);
            
            $analytics = [];
            foreach ($results as $row) {
                $analytics[] = [
                    'timestamp' => $row['hour_bucket'],
                    'avg_people' => floatval($row['avg_people']),
                    'total_detections' => intval($row['total_detections'])
                ];
            }
            
            return $analytics;
            
        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to get people count analytics: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get face detection analytics
     */
    private function getFaceDetectionAnalytics($camera_id, $hours) {
        try {
            $sql = "SELECT COUNT(*) as total_faces,
                           COUNT(DISTINCT name) as unique_faces,
                           AVG(confidence) as avg_confidence
                    FROM face_detections 
                    WHERE timestamp >= DATE_SUB(NOW(), INTERVAL ? HOUR)";
            
            $params = [$hours];
            
            if ($camera_id) {
                $sql .= " AND camera_id = ?";
                $params[] = $camera_id;
            }
            
            $result = query($sql, $params)[0] ?? null;
            
            return [
                'total_faces' => intval($result['total_faces'] ?? 0),
                'unique_faces' => intval($result['unique_faces'] ?? 0),
                'avg_confidence' => floatval($result['avg_confidence'] ?? 0)
            ];
            
        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to get face detection analytics: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get alert analytics
     */
    private function getAlertAnalytics($camera_id, $hours) {
        try {
            $sql = "SELECT alert_type, COUNT(*) as count,
                           SUM(CASE WHEN acknowledged THEN 1 ELSE 0 END) as acknowledged
                    FROM security_alerts 
                    WHERE timestamp >= DATE_SUB(NOW(), INTERVAL ? HOUR)";
            
            $params = [$hours];
            
            if ($camera_id) {
                $sql .= " AND camera_id = ?";
                $params[] = $camera_id;
            }
            
            $sql .= " GROUP BY alert_type";
            
            $results = query($sql, $params);
            
            $analytics = [];
            foreach ($results as $row) {
                $analytics[] = [
                    'alert_type' => $row['alert_type'],
                    'count' => intval($row['count']),
                    'acknowledged' => intval($row['acknowledged'])
                ];
            }
            
            return $analytics;
            
        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to get alert analytics: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get recording analytics
     */
    private function getRecordingAnalytics($camera_id, $hours) {
        try {
            $sql = "SELECT COUNT(*) as total_recordings,
                           AVG(duration) as avg_duration
                    FROM camera_recording_events 
                    WHERE timestamp >= DATE_SUB(NOW(), INTERVAL ? HOUR)";
            
            $params = [$hours];
            
            if ($camera_id) {
                $sql .= " AND camera_id = ?";
                $params[] = $camera_id;
            }
            
            $result = query($sql, $params)[0] ?? null;
            
            return [
                'total_recordings' => intval($result['total_recordings'] ?? 0),
                'avg_duration' => floatval($result['avg_duration'] ?? 0)
            ];
            
        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to get recording analytics: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get recordings list
     */
    private function getRecordings() {
        try {
            $camera_id = $_GET['camera_id'] ?? null;
            $start_date = $_GET['start_date'] ?? null;
            $end_date = $_GET['end_date'] ?? null;
            $limit = intval($_GET['limit'] ?? 100);
            
            $sql = "SELECT * FROM camera_recording_events";
            $params = [];
            
            $where_conditions = [];
            
            if ($camera_id) {
                $where_conditions[] = "camera_id = ?";
                $params[] = $camera_id;
            }
            
            if ($start_date) {
                $where_conditions[] = "timestamp >= ?";
                $params[] = $start_date;
            }
            
            if ($end_date) {
                $where_conditions[] = "timestamp <= ?";
                $params[] = $end_date;
            }
            
            if (!empty($where_conditions)) {
                $sql .= " WHERE " . implode(' AND ', $where_conditions);
            }
            
            $sql .= " ORDER BY timestamp DESC LIMIT ?";
            $params[] = $limit;
            
            $recordings = query($sql, $params);
            
            $recording_list = [];
            foreach ($recordings as $recording) {
                $recording_list[] = [
                    'id' => $recording['id'],
                    'camera_id' => $recording['camera_id'],
                    'event_type' => $recording['event_type'],
                    'duration' => $recording['duration'],
                    'timestamp' => $recording['timestamp'],
                    'trigger_data' => json_decode($recording['trigger_data'], true)
                ];
            }
            
            $this->sendResponse($recording_list);
            
        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to get recordings: ' . $e->getMessage());
            $this->sendError('Failed to get recordings', 500);
        }
    }
    
    /**
     * Get camera configuration
     */
    private function getCameraConfig() {
        try {
            $sql = "SELECT grabar_ventas, deteccion_movimiento, calidad_video, 
                           duracion_grabacion, almacenamiento_maximo, 
                           horario_inicio, horario_fin, alertas_fuera_horario
                    FROM config_camara 
                    WHERE empresa_id = ?
                    ORDER BY id DESC 
                    LIMIT 1";
            
            $result = query($sql, [$this->getEmpresaId()]);
            
            return $result[0] ?? [
                'grabar_ventas' => 0,
                'deteccion_movimiento' => 0,
                'duracion_grabacion' => 30
            ];
            
        } catch (Exception $e) {
            $this->log('ERROR', 'Failed to get camera config: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Send JSON response
     */
    private function sendResponse($data) {
        echo json_encode($data);
        exit;
    }
    
    /**
     * Send error response
     */
    private function sendError($message, $code = 400) {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error' => $message
        ]);
        exit;
    }
}

// Handle the request
$api = new CameraEventsAPI();
$api->handleRequest();
?>
