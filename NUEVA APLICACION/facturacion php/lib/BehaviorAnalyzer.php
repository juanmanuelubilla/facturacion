<?php
require_once 'config.php';

class BehaviorAnalyzer {
    private $empresa_id;
    
    public function __construct($empresa_id) {
        $this->empresa_id = $empresa_id;
    }
    
    /**
     * Analizar frame de video para detectar comportamientos sospechosos
     */
    public function analizarFrame($frame_data, $camara_id) {
        $resultados = [];
        
        // 1. Detectar posturas anómalas
        $posturas = $this->detectarPosturasAnomalas($frame_data);
        if (!empty($posturas)) {
            $resultados = array_merge($resultados, $posturas);
        }
        
        // 2. Detectar movimientos sospechosos
        $movimientos = $this->detectarMovimientosSospechosos($frame_data);
        if (!empty($movimientos)) {
            $resultados = array_merge($resultados, $movimientos);
        }
        
        // 3. Detectar aglomeraciones
        $aglomeraciones = $this->detectarAglomeraciones($frame_data);
        if (!empty($aglomeraciones)) {
            $resultados = array_merge($resultados, $aglomeraciones);
        }
        
        // 4. Detectar objetos ocultos
        $objetos = $this->detectarObjetosOcultos($frame_data);
        if (!empty($objetos)) {
            $resultados = array_merge($resultados, $objetos);
        }
        
        // 5. Detectar comportamiento nervioso
        $nervioso = $this->detectarComportamientoNervioso($frame_data);
        if (!empty($nervioso)) {
            $resultados = array_merge($resultados, $nervioso);
        }
        
        // Registrar eventos y generar alertas
        foreach ($resultados as $evento) {
            $this->registrarEventoComportamiento($evento, $camara_id);
            
            if ($evento['nivel_riesgo'] === 'ALTO' || $evento['nivel_riesgo'] === 'CRITICO') {
                $this->generarAlertaComportamiento($evento, $camara_id);
            }
        }
        
        return $resultados;
    }
    
    /**
     * Detectar posturas anómalas (agachado, mirando alrededor, etc.)
     */
    private function detectarPosturasAnomalas($frame_data) {
        $eventos = [];
        
        // Simulación de detección de posturas con IA
        // En producción, usar modelos como MediaPipe, OpenPose, etc.
        
        $patrones = $this->getPatronesSospechosos('POSTURA_AGACHADA');
        foreach ($patrones as $patron) {
            $confianza = $this->simularDeteccionPostura($frame_data, $patron);
            if ($confianza >= $patron['umbral_confianza']) {
                $eventos[] = [
                    'tipo_evento' => 'POSTURA_ANOMALA',
                    'nivel_riesgo' => $patron['nivel_riesgo'],
                    'descripcion' => 'Persona detectada en postura agachada sospechosa',
                    'coordenadas' => ['x' => rand(100, 400), 'y' => rand(100, 300), 'w' => 80, 'h' => 120],
                    'confianza' => $confianza,
                    'patron_id' => $patron['id']
                ];
            }
        }
        
        $patrones = $this->getPatronesSospechosos('MIRADA_ALREDEDOR');
        foreach ($patrones as $patron) {
            $confianza = $this->simularDeteccionMirada($frame_data, $patron);
            if ($confianza >= $patron['umbral_confianza']) {
                $eventos[] = [
                    'tipo_evento' => 'POSTURA_ANOMALA',
                    'nivel_riesgo' => $patron['nivel_riesgo'],
                    'descripcion' => 'Persona con mirada nerviosa y constante al alrededor',
                    'coordenadas' => ['x' => rand(150, 350), 'y' => rand(80, 200), 'w' => 60, 'h' => 80],
                    'confianza' => $confianza,
                    'patron_id' => $patron['id']
                ];
            }
        }
        
        return $eventos;
    }
    
    /**
     * Detectar movimientos sospechosos (rápidos, erráticos, etc.)
     */
    private function detectarMovimientosSospechosos($frame_data) {
        $eventos = [];
        
        $patrones = $this->getPatronesSospechosos('MOVIMIENTO_RAPIDO');
        foreach ($patrones as $patron) {
            $confianza = $this->simularDeteccionMovimiento($frame_data, $patron);
            if ($confianza >= $patron['umbral_confianza']) {
                $eventos[] = [
                    'tipo_evento' => 'MOVIMIENTO_SUSPICHOZO',
                    'nivel_riesgo' => $patron['nivel_riesgo'],
                    'descripcion' => 'Movimiento rápido y errático detectado',
                    'coordenadas' => ['x' => rand(200, 500), 'y' => rand(150, 350), 'w' => 100, 'h' => 150],
                    'confianza' => $confianza,
                    'patron_id' => $patron['id']
                ];
            }
        }
        
        return $eventos;
    }
    
    /**
     * Detectar aglomeraciones inusuales
     */
    private function detectarAglomeraciones($frame_data) {
        $eventos = [];
        
        // Simulación de detección de grupos
        $personas_detectadas = rand(2, 8);
        
        if ($personas_detectadas >= 4) {
            $eventos[] = [
                'tipo_evento' => 'AGLOMERACION',
                'nivel_riesgo' => $personas_detectadas >= 6 ? 'ALTO' : 'MEDIO',
                'descripcion' => "Aglomeración de {$personas_detectadas} personas detectada",
                'coordenadas' => ['x' => rand(100, 400), 'y' => rand(100, 300), 'w' => 200, 'h' => 200],
                'confianza' => 0.85 + (rand(0, 10) / 100),
                'personas_count' => $personas_detectadas
            ];
        }
        
        return $eventos;
    }
    
    /**
     * Detectar objetos ocultos o sospechosos
     */
    private function detectarObjetosOcultos($frame_data) {
        $eventos = [];
        
        // Simulación de detección de objetos
        $objetos_sospechosos = ['bolsillo grande', 'objeto oculto', 'paquete sospechoso'];
        
        if (rand(1, 10) <= 3) { // 30% de probabilidad
            $objeto = $objetos_sospechosos[array_rand($objetos_sospechosos)];
            $eventos[] = [
                'tipo_evento' => 'OBJETO_OCULTO',
                'nivel_riesgo' => 'MEDIO',
                'descripcion' => "Objeto sospechoso detectado: {$objeto}",
                'coordenadas' => ['x' => rand(180, 420), 'y' => rand(200, 400), 'w' => 40, 'h' => 60],
                'confianza' => 0.70 + (rand(0, 20) / 100),
                'objeto_tipo' => $objeto
            ];
        }
        
        return $eventos;
    }
    
    /**
     * Detectar comportamiento nervioso
     */
    private function detectarComportamientoNervioso($frame_data) {
        $eventos = [];
        
        $patrones = $this->getPatronesSospechosos('MANOS_EN_BOLSILLOS');
        foreach ($patrones as $patron) {
            $confianza = $this->simularDeteccionManos($frame_data, $patron);
            if ($confianza >= $patron['umbral_confianza']) {
                $eventos[] = [
                    'tipo_evento' => 'COMPORTAMIENTO_NERVIOSO',
                    'nivel_riesgo' => $patron['nivel_riesgo'],
                    'descripcion' => 'Persona con manos constantemente en bolsillos (comportamiento nervioso)',
                    'coordenadas' => ['x' => rand(150, 350), 'y' => rand(200, 350), 'w' => 70, 'h' => 100],
                    'confianza' => $confianza,
                    'patron_id' => $patron['id']
                ];
            }
        }
        
        return $eventos;
    }
    
    /**
     * Obtener patrones sospechosos configurados
     */
    private function getPatronesSospechosos($tipo_patron = null) {
        $sql = "SELECT * FROM patrones_sospechosos 
                WHERE empresa_id = ? AND activo = 1";
        $params = [$this->empresa_id];
        
        if ($tipo_patron) {
            $sql .= " AND tipo_patron = ?";
            $params[] = $tipo_patron;
        }
        
        return fetchAll($sql, $params);
    }
    
    /**
     * Simular detección de postura (reemplazar con IA real)
     */
    private function simularDeteccionPostura($frame_data, $patron) {
        // Simulación básica - en producción usar MediaPipe/OpenPose
        $base_confianza = 0.60;
        $variacion = (rand(0, 40) / 100);
        return min(0.95, $base_confianza + $variacion);
    }
    
    /**
     * Simular detección de mirada (reemplazar con IA real)
     */
    private function simularDeteccionMirada($frame_data, $patron) {
        // Simulación básica - en producción usar modelos de detección de mirada
        $base_confianza = 0.55;
        $variacion = (rand(0, 35) / 100);
        return min(0.90, $base_confianza + $variacion);
    }
    
    /**
     * Simular detección de movimiento (reemplazar con IA real)
     */
    private function simularDeteccionMovimiento($frame_data, $patron) {
        // Simulación básica - en producción usar análisis óptico de flujo
        $base_confianza = 0.65;
        $variacion = (rand(0, 30) / 100);
        return min(0.95, $base_confianza + $variacion);
    }
    
    /**
     * Simular detección de manos (reemplazar con IA real)
     */
    private function simularDeteccionManos($frame_data, $patron) {
        // Simulación básica - en producción usar detección de manos
        $base_confianza = 0.70;
        $variacion = (rand(0, 25) / 100);
        return min(0.92, $base_confianza + $variacion);
    }
    
    /**
     * Registrar evento de comportamiento
     */
    private function registrarEventoComportamiento($evento, $camara_id) {
        $sql = "INSERT INTO eventos_comportamiento 
                (camara_id, tipo_evento, nivel_riesgo, descripcion, coordenadas, confianza, empresa_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $coordenadas_json = json_encode($evento['coordenadas']);
        
        query($sql, [
            $camara_id,
            $evento['tipo_evento'],
            $evento['nivel_riesgo'],
            $evento['descripcion'],
            $coordenadas_json,
            $evento['confianza'],
            $this->empresa_id
        ]);
        
        return query("SELECT LAST_INSERT_ID() as id")[0]['id'];
    }
    
    /**
     * Generar alerta de comportamiento
     */
    private function generarAlertaComportamiento($evento, $camara_id) {
        $evento_id = $this->registrarEventoComportamiento($evento, $camara_id);
        
        $sql = "INSERT INTO alertas_comportamiento 
                (evento_id, tipo_alerta, mensaje, empresa_id) 
                VALUES (?, ?, ?, ?)";
        
        $mensaje_alerta = "⚠️ ALERTA DE COMPORTAMIENTO SOSPECHOSO\n" .
                        "Tipo: {$evento['tipo_evento']}\n" .
                        "Nivel: {$evento['nivel_riesgo']}\n" .
                        "Descripción: {$evento['descripcion']}\n" .
                        "Confianza: " . number_format($evento['confianza'] * 100, 1) . "%";
        
        query($sql, [
            $evento_id,
            'COMPORTAMIENTO_SUSPICHOZO',
            $mensaje_alerta,
            $this->empresa_id
        ]);
        
        return $evento_id;
    }
    
    /**
     * Obtener eventos recientes
     */
    public function getEventosRecientes($limite = 20) {
        $sql = "SELECT ec.*, c.nombre as camara_nombre 
                FROM eventos_comportamiento ec
                LEFT JOIN camaras c ON ec.camara_id = c.id
                WHERE ec.empresa_id = ? AND ec.activa = 1
                ORDER BY ec.fecha DESC
                LIMIT " . intval($limite);
        
        return fetchAll($sql, [$this->empresa_id]);
    }
    
    /**
     * Obtener alertas activas
     */
    public function getAlertasActivas() {
        return fetchAll("
            SELECT ac.*, ec.tipo_evento, ec.nivel_riesgo, ec.descripcion,
                   c.nombre as camara_nombre
            FROM alertas_comportamiento ac
            LEFT JOIN eventos_comportamiento ec ON ac.evento_id = ec.id
            LEFT JOIN camaras c ON ec.camara_id = c.id
            WHERE ac.empresa_id = ? AND ac.notificada = 0
            ORDER BY ac.fecha DESC
        ", [$this->empresa_id]);
    }
    
    /**
     * Configurar patrones sospechosos por defecto
     */
    public function configurarPatronesDefecto() {
        $patrones = [
            [
                'nombre' => 'Postura Agachada Sospechosa',
                'descripcion' => 'Persona agachada de forma sospechosa, posible ocultamiento',
                'tipo_patron' => 'POSTURA_AGACHADA',
                'nivel_riesgo' => 'ALTO',
                'umbral_confianza' => 0.75
            ],
            [
                'nombre' => 'Mirada Nerviosa Constante',
                'descripcion' => 'Persona mirando constantemente al alrededor de forma nerviosa',
                'tipo_patron' => 'MIRADA_ALREDEDOR',
                'nivel_riesgo' => 'MEDIO',
                'umbral_confianza' => 0.70
            ],
            [
                'nombre' => 'Movimientos Rápidos Erráticos',
                'descripcion' => 'Movimientos rápidos y erráticos sin propósito claro',
                'tipo_patron' => 'MOVIMIENTO_RAPIDO',
                'nivel_riesgo' => 'MEDIO',
                'umbral_confianza' => 0.80
            ],
            [
                'nombre' => 'Manos en Bolsillos',
                'descripcion' => 'Persona con manos constantemente en bolsillos (comportamiento nervioso)',
                'tipo_patron' => 'MANOS_EN_BOLSILLOS',
                'nivel_riesgo' => 'BAJO',
                'umbral_confianza' => 0.75
            ]
        ];
        
        $configurados = 0;
        foreach ($patrones as $patron) {
            // Verificar si ya existe
            $existente = fetch("SELECT id FROM patrones_sospechosos 
                                WHERE empresa_id = ? AND tipo_patron = ? AND nombre = ?", 
                                [$this->empresa_id, $patron['tipo_patron'], $patron['nombre']]);
            
            if (!$existente) {
                $sql = "INSERT INTO patrones_sospechosos 
                        (nombre, descripcion, tipo_patron, nivel_riesgo, activo, empresa_id, umbral_confianza) 
                        VALUES (?, ?, ?, ?, 1, ?, ?)";
                
                query($sql, [
                    $patron['nombre'],
                    $patron['descripcion'],
                    $patron['tipo_patron'],
                    $patron['nivel_riesgo'],
                    $this->empresa_id,
                    $patron['umbral_confianza']
                ]);
                
                $configurados++;
            }
        }
        
        return $configurados;
    }
    
    /**
     * Obtener estadísticas de comportamiento
     */
    public function getEstadisticas() {
        $stats = [];
        
        // Eventos por tipo
        $stats['eventos_por_tipo'] = fetchAll("
            SELECT tipo_evento, COUNT(*) as total, AVG(confianza) as confianza_promedio
            FROM eventos_comportamiento 
            WHERE empresa_id = ? AND fecha >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY tipo_evento
            ORDER BY total DESC
        ", [$this->empresa_id]);
        
        // Eventos por nivel de riesgo
        $stats['eventos_por_riesgo'] = fetchAll("
            SELECT nivel_riesgo, COUNT(*) as total
            FROM eventos_comportamiento 
            WHERE empresa_id = ? AND fecha >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY nivel_riesgo
            ORDER BY 
                CASE nivel_riesgo 
                    WHEN 'CRITICO' THEN 1 
                    WHEN 'ALTO' THEN 2 
                    WHEN 'MEDIO' THEN 3 
                    WHEN 'BAJO' THEN 4 
                END
        ", [$this->empresa_id]);
        
        // Alertas no notificadas
        $stats['alertas_pendientes'] = fetch("SELECT COUNT(*) as total 
                                              FROM alertas_comportamiento 
                                              WHERE empresa_id = ? AND notificada = 0", 
                                              [$this->empresa_id])['total'] ?? 0;
        
        return $stats;
    }
}
?>
