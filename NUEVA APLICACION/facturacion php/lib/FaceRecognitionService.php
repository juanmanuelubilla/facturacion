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
     * Detectar rostro y clasificar
     */
    public function detectarRostro($face_data, $camara_id = null) {
        $config = $this->getConfig();
        $umbral = $config['umbral_confianza'] ?? 0.80;
        
        // 1. Buscar en perfiles de clientes
        $cliente = $this->buscarPerfilCliente($face_data, $umbral);
        if ($cliente) {
            $this->registrarDeteccion($cliente['id'], null, $camara_id, 'CLIENTE', $cliente['confianza']);
            return [
                'tipo' => 'CLIENTE',
                'datos' => $cliente,
                'mensaje' => 'Cliente identificado: ' . $cliente['nombre']
            ];
        }
        
        // 2. Buscar en personas de riesgo
        $riesgo = $this->buscarPersonaRiesgo($face_data, $umbral);
        if ($riesgo) {
            $this->registrarDeteccion(null, $riesgo['id'], $camara_id, 'RIESGO', $riesgo['confianza']);
            $this->generarAlerta($riesgo, $camara_id);
            return [
                'tipo' => 'RIESGO',
                'datos' => $riesgo,
                'mensaje' => '¡PERSONA DE RIESGO DETECTADA! ' . $riesgo['nombre']
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
     * Buscar persona de riesgo
     */
    private function buscarPersonaRiesgo($face_data, $umbral) {
        $personas = fetchAll("SELECT * FROM personas_riesgo 
                              WHERE empresa_id = ? AND activo = 1 AND foto IS NOT NULL", [$this->empresa_id]);
        
        foreach ($personas as $persona) {
            $confianza = $this->compararRostros($face_data, $persona['foto']);
            if ($confianza >= $umbral) {
                $persona['confianza'] = $confianza;
                return $persona;
            }
        }
        
        return null;
    }
    
    /**
     * Comparar rostros (simulación)
     */
    private function compararRostros($face_data1, $face_data2) {
        // Simulación de comparación facial
        // En producción, usar algoritmos reales como FaceNet, OpenFace, etc.
        
        if (empty($face_data1) || empty($face_data2)) {
            return 0;
        }
        
        // Simulación simple: hash comparison
        $hash1 = md5($face_data1);
        $hash2 = md5($face_data2);
        
        // Calcular similitud (0-1)
        $similitud = 0;
        for ($i = 0; $i < min(strlen($hash1), strlen($hash2)); $i++) {
            if ($hash1[$i] === $hash2[$i]) {
                $similitud += 1 / 32; // 32 caracteres en MD5
            }
        }
        
        // Agregar variación aleatoria para simular detección real
        $similitud += (rand(0, 20) - 10) / 100;
        $similitud = max(0, min(1, $similitud));
        
        return $similitud;
    }
    
    /**
     * Generar alerta de seguridad
     */
    private function generarAlerta($persona_riesgo, $camara_id) {
        $sql = "INSERT INTO alertas_seguridad 
                (persona_riesgo_id, camara_id, tipo_alerta, mensaje, empresa_id) 
                VALUES (?, ?, ?, ?, ?)";
        
        $mensaje = "PERSONA DE RIESGO DETECTADA: " . $persona_riesgo['nombre'] . 
                  " - Nivel: " . $persona_riesgo['nivel_peligro'] . 
                  " - Motivo: " . $persona_riesgo['motivo_catalogacion'];
        
        query($sql, [$persona_riesgo['id'], $camara_id, 'RIESGO_DETECTADO', $mensaje, $this->empresa_id]);
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
}
?>
