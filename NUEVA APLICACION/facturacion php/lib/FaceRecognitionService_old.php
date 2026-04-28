<?php
require_once 'config.php';

class FaceRecognitionService {
    private $empresa_id;
    private $config;
    
    public function __construct($empresa_id) {
        $this->empresa_id = $empresa_id;
        $this->config = $this->getConfig();
    }
    
    /**
     * Obtener configuración de alertas
     */
    public function getConfig() {
        $config = fetch("SELECT * FROM config_alertas WHERE empresa_id = ?", [$this->empresa_id]);
        return $config ?: [];
    }
    
    /**
     * Registrar perfil facial de cliente
     */
    public function registrarPerfilFacial($cliente_id, $face_data) {
        $sql = "INSERT INTO perfiles_faciales (cliente_id, face_data, empresa_id) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                face_data = VALUES(face_data), 
                ultima_deteccion = CURRENT_TIMESTAMP";
        
        return query($sql, [$cliente_id, $face_data, $this->empresa_id]);
    }
    
    /**
     * Agregar persona de riesgo
     */
    public function agregarPersonaRiesgo($datos) {
        $sql = "INSERT INTO personas_riesgo 
                (nombre, apellido, alias, foto, tipo_riesgo, nivel_peligro, descripcion, modus_operandi, empresa_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        return query($sql, [
            $datos['nombre'],
            $datos['apellido'] ?? null,
            $datos['alias'] ?? null,
            $datos['foto'] ?? null,
            $datos['tipo_riesgo'] ?? 'MEDIO',
            $datos['nivel_peligro'] ?? 3,
            $datos['descripcion'] ?? null,
            $datos['modus_operandi'] ?? null,
            $this->empresa_id
        ]);
    }
    
    /**
     * Detectar rostro y clasificar
     */
    public function detectarRostro($face_data, $camara_id) {
        $umbral_confianza = $this->config['umbral_confianza'] ?? 0.80;
        
        // 1. Buscar en perfiles de clientes
        $perfil = $this->buscarPerfilCliente($face_data, $umbral_confianza);
        if ($perfil) {
            $this->registrarDeteccion($perfil['id'], null, $camara_id, 'CLIENTE', $perfil['confianza']);
            $this->actualizarUltimaDeteccionPerfil($perfil['id']);
            return [
                'tipo' => 'CLIENTE',
                'datos' => $perfil,
                'mensaje' => 'Cliente detectado'
            ];
        }
        
        // 2. Buscar en personas de riesgo
        $persona_riesgo = $this->buscarPersonaRiesgo($face_data, $umbral_confianza);
        if ($persona_riesgo) {
            $this->registrarDeteccion(null, $persona_riesgo['id'], $camara_id, 'RIESGO', $persona_riesgo['confianza']);
            $this->actualizarUltimaDeteccionRiesgo($persona_riesgo['id']);
            
            // Generar alerta de seguridad
            $this->generarAlertaSeguridad($persona_riesgo['id'], $camara_id, $persona_riesgo['confianza']);
            
            return [
                'tipo' => 'RIESGO',
                'datos' => $persona_riesgo,
                'mensaje' => '¡PERSONA DE RIESGO DETECTADA!',
                'alerta' => true
            ];
        }
        
        // 3. Rostro desconocido
        $this->registrarDeteccion(null, null, $camara_id, 'DESCONOCIDO', 0);
        return [
            'tipo' => 'DESCONOCIDO',
            'datos' => null,
            'mensaje' => 'Rostro no identificado'
        ];
    }
    
    /**
     * Buscar perfil de cliente usando foto existente
     */
    private function buscarPerfilCliente($face_data, $umbral) {
        // Obtener clientes con foto de perfil
        $clientes_con_foto = fetchAll("SELECT id, nombre, apellido, email, foto_perfil 
                                      FROM clientes 
                                      WHERE empresa_id = ? AND foto_perfil IS NOT NULL AND foto_perfil != ''", 
                                      [$this->empresa_id]);
        
        foreach ($clientes_con_foto as $cliente) {
            // Simular comparación facial con foto del cliente
            $confianza = $this->compararRostros($face_data, $cliente['foto_perfil']);
            if ($confianza >= $umbral) {
                $cliente['confianza'] = $confianza;
                
                // Registrar en perfiles_faciales si no existe
                $this->registrarPerfilFacialAutomatico($cliente['id'], $cliente['foto_perfil']);
                
                return $cliente;
            }
        }
        
        return null;
    }
    
    /**
     * Registrar perfil facial automáticamente desde foto de cliente
     */
    private function registrarPerfilFacialAutomatico($cliente_id, $foto_ruta) {
        // Verificar si ya existe
        $existente = fetch("SELECT id FROM perfiles_faciales WHERE cliente_id = ? AND empresa_id = ?", 
                          [$cliente_id, $this->empresa_id]);
        
        if (!$existente) {
            $sql = "INSERT INTO perfiles_faciales (cliente_id, face_data, empresa_id) 
                    VALUES (?, ?, ?)";
            query($sql, [$cliente_id, $foto_ruta, $this->empresa_id]);
        }
    }
    
    /**
     * Obtener clientes con fotos para reconocimiento
     */
    public function getClientesConFotos() {
        return fetchAll("SELECT id, nombre, apellido, email, foto_perfil 
                          FROM clientes 
                          WHERE empresa_id = ? AND foto_perfil IS NOT NULL AND foto_perfil != ''", 
                          [$this->empresa_id]);
    }
    
    /**
     * Sincronizar clientes existentes con fotos al sistema facial
     */
    public function sincronizarClientesConFotos() {
        $clientes = $this->getClientesConFotos();
        $sincronizados = 0;
        
        foreach ($clientes as $cliente) {
            // Verificar si ya tiene perfil facial
            $existente = fetch("SELECT id FROM perfiles_faciales WHERE cliente_id = ? AND empresa_id = ?", 
                              [$cliente['id'], $this->empresa_id]);
            
            if (!$existente) {
                $this->registrarPerfilFacialAutomatico($cliente['id'], $cliente['foto_perfil']);
                $sincronizados++;
            }
        }
        
        return $sincronizados;
    }
    
    /**
     * Buscar persona de riesgo
     */
    private function buscarPersonaRiesgo($face_data, $umbral) {
        // Simulación de comparación facial para personas de riesgo
        $personas = fetchAll("SELECT * FROM personas_riesgo 
                              WHERE empresa_id = ? AND activo = 1 AND foto IS NOT NULL", [$this->empresa_id]);
        
        foreach ($personas as $persona) {
            if ($persona['foto']) {
                // Simulación de comparación con foto de persona de riesgo
                $confianza = $this->compararRostros($face_data, $persona['foto']);
                if ($confianza >= $umbral) {
                    $persona['confianza'] = $confianza;
                    return $persona;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Comparar rostros (simulación)
     */
    private function compararRostros($face_data1, $face_data2) {
        // Simulación simple: si son similares, devuelve alta confianza
        // En producción, usar algoritmos reales como FaceNet, DeepFace, etc.
        if (empty($face_data1) || empty($face_data2)) {
            return 0;
        }
        
        // Simulación basada en similitud de strings
        $similitud = 0;
        $len1 = strlen($face_data1);
        $len2 = strlen($face_data2);
        
        if ($len1 > 0 && $len2 > 0) {
            $comunes = similar_text($face_data1, $face_data2, $porcentaje);
            $similitud = $porcentaje / 100;
        }
        
        return $similitud;
    }
    
    /**
     * Registrar detección facial
     */
    private function registrarDeteccion($perfil_id, $persona_riesgo_id, $camara_id, $tipo, $confianza, $venta_id = null) {
        $sql = "INSERT INTO detecciones_faciales 
                (perfil_id, persona_riesgo_id, camara_id, tipo_deteccion, confianza, venta_id, empresa_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        return query($sql, [$perfil_id, $persona_riesgo_id, $camara_id, $tipo, $confianza, $venta_id, $this->empresa_id]);
    }
    
    /**
     * Actualizar última detección de perfil
     */
    private function actualizarUltimaDeteccionPerfil($perfil_id) {
        return query("UPDATE perfiles_faciales SET ultima_deteccion = CURRENT_TIMESTAMP WHERE id = ?", [$perfil_id]);
    }
    
    /**
     * Actualizar última detección de persona de riesgo
     */
    private function actualizarUltimaDeteccionRiesgo($persona_id) {
        return query("UPDATE personas_riesgo SET ultima_deteccion = CURRENT_TIMESTAMP WHERE id = ?", [$persona_id]);
    }
    
    /**
     * Generar alerta de seguridad
     */
    private function generarAlertaSeguridad($persona_riesgo_id, $camara_id, $confianza) {
        if (!($this->config['alertas_activas'] ?? 1)) {
            return false;
        }
        
        // Crear alerta
        $sql = "INSERT INTO alertas_seguridad 
                (persona_riesgo_id, camara_id, confianza, empresa_id) 
                VALUES (?, ?, ?, ?)";
        
        $alerta_id = query($sql, [$persona_riesgo_id, $camara_id, $confianza, $this->empresa_id]);
        
        // Enviar notificaciones según configuración
        $notificaciones = [];
        
        if ($this->config['notificacion_pantalla'] ?? 1) {
            $this->enviarAlertaPantalla($persona_riesgo_id, $camara_id);
            $notificaciones[] = 'pantalla';
        }
        
        if ($this->config['notificacion_sonido'] ?? 1) {
            $this->enviarAlertaSonido();
            $notificaciones[] = 'sonido';
        }
        
        if ($this->config['email_alerta'] ?? 0) {
            $this->enviarAlertaEmail($persona_riesgo_id, $camara_id);
            $notificaciones[] = 'email';
        }
        
        if ($this->config['whatsapp_alerta'] ?? 0) {
            $this->enviarAlertaWhatsApp($persona_riesgo_id, $camara_id);
            $notificaciones[] = 'whatsapp';
        }
        
        // Actualizar notificaciones enviadas
        query("UPDATE alertas_seguridad SET notificaciones_enviadas = ? WHERE id = ?", 
              [json_encode($notificaciones), $alerta_id]);
        
        return $alerta_id;
    }
    
    /**
     * Enviar alerta a pantalla
     */
    private function enviarAlertaPantalla($persona_riesgo_id, $camara_id) {
        $persona = fetch("SELECT * FROM personas_riesgo WHERE id = ?", [$persona_riesgo_id]);
        $camara = fetch("SELECT nombre FROM camaras WHERE id = ?", [$camara_id]);
        
        if ($persona && $camara) {
            // Guardar alerta en sesión para mostrar en interfaz
            $_SESSION['alerta_seguridad'] = [
                'tipo' => 'RIESGO',
                'persona' => $persona,
                'camara' => $camara,
                'fecha' => date('Y-m-d H:i:s'),
                'mostrada' => false
            ];
        }
    }
    
    /**
     * Enviar alerta de sonido
     */
    private function enviarAlertaSonido() {
        // En producción, activaría una alarma física
        // Por ahora, solo registramos que se debería activar
        error_log("ALERTA DE SONIDO ACTIVADA - Persona de riesgo detectada");
    }
    
    /**
     * Enviar alerta por email
     */
    private function enviarAlertaEmail($persona_riesgo_id, $camara_id) {
        $persona = fetch("SELECT * FROM personas_riesgo WHERE id = ?", [$persona_riesgo_id]);
        $camara = fetch("SELECT nombre FROM camaras WHERE id = ?", [$camara_id]);
        
        if ($persona && $camara) {
            // Aquí iría el envío real de email
            error_log("ALERTA EMAIL: Persona de riesgo {$persona['nombre']} detectada en cámara {$camara['nombre']}");
        }
    }
    
    /**
     * Enviar alerta por WhatsApp
     */
    private function enviarAlertaWhatsApp($persona_riesgo_id, $camara_id) {
        $persona = fetch("SELECT * FROM personas_riesgo WHERE id = ?", [$persona_riesgo_id]);
        $camara = fetch("SELECT nombre FROM camaras WHERE id = ?", [$camara_id]);
        
        if ($persona && $camara) {
            // Aquí iría el envío real por WhatsApp
            error_log("ALERTA WHATSAPP: Persona de riesgo {$persona['nombre']} detectada en cámara {$camara['nombre']}");
        }
    }
    
    /**
     * Obtener personas de riesgo
     */
    public function getPersonasRiesgo() {
        return fetchAll("SELECT * FROM personas_riesgo WHERE empresa_id = ? ORDER BY tipo_riesgo, nivel_peligro DESC", [$this->empresa_id]);
    }
    
    /**
     * Obtener perfiles faciales de clientes
     */
    public function getPerfilesClientes() {
        return fetchAll("SELECT pf.*, c.nombre, c.apellido, c.email, c.foto_perfil
                          FROM perfiles_faciales pf 
                          LEFT JOIN clientes c ON pf.cliente_id = c.id 
                          WHERE pf.empresa_id = ? AND pf.cliente_id IS NOT NULL", [$this->empresa_id]);
    }
    
    /**
     * Obtener detecciones recientes
     */
    public function getDeteccionesRecientes($limite = 50) {
        $sql = "SELECT df.*, 
                       COALESCE(c.nombre, 'N/A') as cliente_nombre,
                       COALESCE(pr.nombre, 'N/A') as riesgo_nombre,
                       cam.nombre as camara_nombre
                FROM detecciones_faciales df
                LEFT JOIN perfiles_faciales pf ON df.perfil_id = pf.id
                LEFT JOIN clientes c ON pf.cliente_id = c.id
                LEFT JOIN personas_riesgo pr ON df.persona_riesgo_id = pr.id
                LEFT JOIN camaras cam ON df.camara_id = cam.id
                WHERE df.empresa_id = ?
                ORDER BY df.fecha DESC
                LIMIT " . intval($limite);
        
        return fetchAll($sql, [$this->empresa_id]);
    }
    
    /**
     * Obtener alertas de seguridad activas
     */
    public function getAlertasActivas() {
        return fetchAll("SELECT al.*, pr.nombre as persona_nombre, pr.tipo_riesgo, pr.descripcion,
                               cam.nombre as camara_nombre
                          FROM alertas_seguridad al
                          LEFT JOIN personas_riesgo pr ON al.persona_riesgo_id = pr.id
                          LEFT JOIN camaras cam ON al.camara_id = cam.id
                          WHERE al.empresa_id = ? AND al.estado = 'ACTIVA'
                          ORDER BY al.fecha DESC", [$this->empresa_id]);
    }
    
    /**
     * Actualizar configuración de alertas
     */
    public function actualizarConfigAlertas($config) {
        $sql = "INSERT INTO config_alertas (empresa_id, alertas_activas, notificacion_sonido, 
                              notificacion_pantalla, email_alerta, whatsapp_alerta, umbral_confianza, tiempo_grabacion_seg) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                alertas_activas = VALUES(alertas_activas), 
                notificacion_sonido = VALUES(notificacion_sonido), 
                notificacion_pantalla = VALUES(notificacion_pantalla), 
                email_alerta = VALUES(email_alerta), 
                whatsapp_alerta = VALUES(whatsapp_alerta), 
                umbral_confianza = VALUES(umbral_confianza), 
                tiempo_grabacion_seg = VALUES(tiempo_grabacion_seg)";
        
        return query($sql, [
            $this->empresa_id,
            $config['alertas_activas'] ?? 1,
            $config['notificacion_sonido'] ?? 1,
            $config['notificacion_pantalla'] ?? 1,
            $config['email_alerta'] ?? 0,
            $config['whatsapp_alerta'] ?? 0,
            $config['umbral_confianza'] ?? 0.80,
            $config['tiempo_grabacion_seg'] ?? 60
        ]);
    }
    
    /**
     * Marcar alerta como atendida
     */
    public function atenderAlerta($alerta_id, $acciones_tomadas) {
        return query("UPDATE alertas_seguridad SET estado = 'ATENDIDA', acciones_tomadas = ? 
                      WHERE id = ? AND empresa_id = ?", [$acciones_tomadas, $alerta_id, $this->empresa_id]);
    }
}
?>
