<?php
require_once 'config.php';

class PresupuestoService {
    private $db;
    private $empresa_id;
    private $usuario_id;
    
    public function __construct($empresa_id, $usuario_id) {
        $this->db = getDB();
        $this->empresa_id = $empresa_id;
        $this->usuario_id = $usuario_id;
    }
    
    /**
     * Crear un nuevo presupuesto
     */
    public function crearPresupuesto($datos) {
        try {
            $this->db->beginTransaction();
            
            // Generar número de presupuesto único
            $numero_presupuesto = $this->generarNumeroPresupuesto();
            
            // Insertar presupuesto principal
            $stmt = $this->db->prepare("
                INSERT INTO presupuestos (
                    empresa_id, cliente_id, numero_presupuesto, titulo, descripcion,
                    subtotal, iva_porcentaje, iva_total, total, estado,
                    validez_dias, fecha_vencimiento, creado_por, observaciones
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $fecha_vencimiento = !empty($datos['validez_dias']) 
                ? date('Y-m-d H:i:s', strtotime("+{$datos['validez_dias']} days"))
                : null;
            
            $stmt->execute([
                $this->empresa_id,
                $datos['cliente_id'],
                $numero_presupuesto,
                $datos['titulo'],
                $datos['descripcion'] ?? '',
                $datos['subtotal'] ?? 0,
                $datos['iva_porcentaje'] ?? 21.00,
                $datos['iva_total'] ?? 0,
                $datos['total'] ?? 0,
                $datos['estado'] ?? 'pendiente',
                $datos['validez_dias'] ?? 30,
                $fecha_vencimiento,
                $this->usuario_id,
                $datos['observaciones'] ?? ''
            ]);
            
            $presupuesto_id = $this->db->lastInsertId();
            
            // Insertar detalles del presupuesto
            if (!empty($datos['detalles'])) {
                foreach ($datos['detalles'] as $detalle) {
                    $subtotal = $detalle['cantidad'] * $detalle['precio_unitario'];
                    $iva_total = $subtotal * ($detalle['iva_porcentaje'] / 100);
                    $total = $subtotal + $iva_total;
                    
                    $stmt_detalle = $this->db->prepare("
                        INSERT INTO presupuesto_detalles (
                            presupuesto_id, producto_id, producto_nombre, cantidad, precio_unitario,
                            subtotal, iva_porcentaje, iva_total, total
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $stmt_detalle->execute([
                        $presupuesto_id,
                        $detalle['producto_id'],
                        $detalle['producto_nombre'],
                        $detalle['cantidad'],
                        $detalle['precio_unitario'],
                        $subtotal,
                        $detalle['iva_porcentaje'] ?? 21.00,
                        $iva_total,
                        $total
                    ]);
                }
            }
            
            // Agregar seguimiento inicial
            $this->agregarSeguimiento($presupuesto_id, 'creado', 'Presupuesto creado exitosamente');
            
            $this->db->commit();
            
            return [
                'success' => true,
                'presupuesto_id' => $presupuesto_id,
                'numero_presupuesto' => $numero_presupuesto
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Generar número de presupuesto único
     */
    private function generarNumeroPresupuesto() {
        $año = date('Y');
        $secuencia = 1;
        
        // Obtener última secuencia del año
        $stmt = $this->db->prepare("
            SELECT MAX(CAST(SUBSTRING(numero_presupuesto, 6) AS UNSIGNED)) as max_secuencia
            FROM presupuestos 
            WHERE empresa_id = ? AND numero_presupuesto LIKE ?
        ");
        $stmt->execute([$this->empresa_id, "PRES-{$año}-%"]);
        $resultado = $stmt->fetch();
        
        if ($resultado && $resultado['max_secuencia']) {
            $secuencia = $resultado['max_secuencia'] + 1;
        }
        
        return "PRES-{$año}-" . str_pad($secuencia, 4, '0', STR_PAD_LEFT);
    }
    
    /**
     * Obtener lista de presupuestos
     */
    public function obtenerPresupuestos($filtros = []) {
        $sql = "
            SELECT p.*, c.nombre as cliente_nombre, c.apellido as cliente_apellido,
                   u.nombre as creado_por_nombre
            FROM presupuestos p
            LEFT JOIN clientes c ON p.cliente_id = c.id
            LEFT JOIN usuarios u ON p.creado_por = u.id
            WHERE p.empresa_id = ?
        ";
        
        $params = [$this->empresa_id];
        
        // Aplicar filtros
        if (!empty($filtros['estado'])) {
            $sql .= " AND p.estado = ?";
            $params[] = $filtros['estado'];
        }
        
        if (!empty($filtros['cliente_id'])) {
            $sql .= " AND p.cliente_id = ?";
            $params[] = $filtros['cliente_id'];
        }
        
        if (!empty($filtros['fecha_desde'])) {
            $sql .= " AND p.fecha_creacion >= ?";
            $params[] = $filtros['fecha_desde'];
        }
        
        if (!empty($filtros['fecha_hasta'])) {
            $sql .= " AND p.fecha_creacion <= ?";
            $params[] = $filtros['fecha_hasta'];
        }
        
        $sql .= " ORDER BY p.fecha_creacion DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Obtener detalles de un presupuesto
     */
    public function obtenerDetallesPresupuesto($presupuesto_id) {
        $stmt = $this->db->prepare("
            SELECT pd.*, p.nombre as producto_nombre, p.codigo_barra
            FROM presupuesto_detalles pd
            LEFT JOIN productos p ON pd.producto_id = p.id
            WHERE pd.presupuesto_id = ?
            ORDER BY pd.id
        ");
        $stmt->execute([$presupuesto_id]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Obtener presupuesto completo con detalles
     */
    public function obtenerPresupuestoCompleto($presupuesto_id) {
        // Obtener presupuesto principal
        $stmt = $this->db->prepare("
            SELECT p.*, c.nombre as cliente_nombre, c.apellido as cliente_apellido,
                   c.telefono as cliente_telefono, c.whatsapp as cliente_whatsapp,
                   u.nombre as creado_por_nombre
            FROM presupuestos p
            LEFT JOIN clientes c ON p.cliente_id = c.id
            LEFT JOIN usuarios u ON p.creado_por = u.id
            WHERE p.id = ? AND p.empresa_id = ?
        ");
        $stmt->execute([$presupuesto_id, $this->empresa_id]);
        $presupuesto = $stmt->fetch();
        
        if ($presupuesto) {
            // Obtener detalles
            $presupuesto['detalles'] = $this->obtenerDetallesPresupuesto($presupuesto_id);
            
            // Obtener seguimiento
            $presupuesto['seguimiento'] = $this->obtenerSeguimiento($presupuesto_id);
        }
        
        return $presupuesto;
    }
    
    /**
     * Actualizar estado de presupuesto
     */
    public function actualizarEstado($presupuesto_id, $estado, $observaciones = '') {
        try {
            $stmt = $this->db->prepare("
                UPDATE presupuestos 
                SET estado = ?, 
                    fecha_aprobacion = CASE WHEN ? IN ('aprobado', 'convertido') THEN NOW() ELSE fecha_aprobacion END,
                    fecha_conversion = CASE WHEN ? = 'convertido' THEN NOW() ELSE fecha_conversion END,
                    aprobado_por = CASE WHEN ? IN ('aprobado', 'convertido') THEN ? ELSE aprobado_por END,
                    observaciones = ?
                WHERE id = ? AND empresa_id = ?
            ");
            
            $stmt->execute([
                $estado,
                $estado,
                $estado,
                $this->usuario_id,
                $observaciones,
                $presupuesto_id,
                $this->empresa_id
            ]);
            
            // Agregar seguimiento
            $this->agregarSeguimiento($presupuesto_id, $estado, $observaciones);
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Agregar seguimiento al presupuesto
     */
    public function agregarSeguimiento($presupuesto_id, $tipo_accion, $descripcion) {
        $stmt = $this->db->prepare("
            INSERT INTO presupuesto_seguimiento (
                presupuesto_id, tipo_accion, descripcion, creado_por
            ) VALUES (?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $presupuesto_id,
            $tipo_accion,
            $descripcion,
            $this->usuario_id
        ]);
    }
    
    /**
     * Obtener seguimiento de un presupuesto
     */
    public function obtenerSeguimiento($presupuesto_id) {
        $stmt = $this->db->prepare("
            SELECT ps.*, u.nombre as usuario_nombre, u.apellido as usuario_apellido
            FROM presupuesto_seguimiento ps
            LEFT JOIN usuarios u ON ps.creado_por = u.id
            WHERE ps.presupuesto_id = ?
            ORDER BY ps.fecha_accion DESC
        ");
        $stmt->execute([$presupuesto_id]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Eliminar presupuesto
     */
    public function eliminarPresupuesto($presupuesto_id) {
        try {
            $this->db->beginTransaction();
            
            // Eliminar detalles y seguimiento (cascading)
            $stmt = $this->db->prepare("
                DELETE FROM presupuestos 
                WHERE id = ? AND empresa_id = ?
            ");
            $stmt->execute([$presupuesto_id, $this->empresa_id]);
            
            $this->db->commit();
            
            return ['success' => true];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Convertir presupuesto en venta
     */
    public function convertirEnVenta($presupuesto_id) {
        try {
            $this->db->beginTransaction();
            
            // Obtener detalles del presupuesto
            $detalles = $this->obtenerDetallesPresupuesto($presupuesto_id);
            
            // Crear asiento contable por la conversión
            require_once 'lib/MotorContable.php';
            $motor = new MotorContable($this->empresa_id);
            
            foreach ($detalles as $detalle) {
                $asiento_data = [
                    'fecha' => date('Y-m-d'),
                    'concepto' => "Venta por conversión de presupuesto",
                    'debe' => [
                        'cuenta' => 'Clientes',
                        'monto' => $detalle['total']
                    ],
                    'haber' => [
                        'cuenta' => 'Ventas',
                        'monto' => $detalle['subtotal']
                    ],
                    'iva' => [
                        'cuenta' => 'IVA Crédito Fiscal',
                        'monto' => $detalle['iva_total']
                    ]
                ];
                
                $motor->crearAsiento($asiento_data);
            }
            
            // Actualizar estado del presupuesto
            $this->actualizarEstado($presupuesto_id, 'convertido', 'Convertido en venta automáticamente');
            
            $this->db->commit();
            
            return ['success' => true];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Generar PDF del presupuesto
     */
    public function generarPDF($presupuesto_id) {
        $presupuesto = $this->obtenerPresupuestoCompleto($presupuesto_id);
        
        if (!$presupuesto) {
            return ['success' => false, 'error' => 'Presupuesto no encontrado'];
        }
        
        require_once 'lib/PDFGenerator.php';
        $pdf = new PDFGenerator();
        
        $html = $this->generarHTMLPresupuesto($presupuesto);
        
        return $pdf->generarDesdeHTML($html, "presupuesto_{$presupuesto['numero_presupuesto']}.pdf");
    }
    
    /**
     * Generar HTML del presupuesto
     */
    private function generarHTMLPresupuesto($presupuesto) {
        $html = "
        <html>
        <head>
            <title>Presupuesto {$presupuesto['numero_presupuesto']}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .info { margin-bottom: 20px; }
                .detalles { width: 100%; border-collapse: collapse; }
                .detalles th, .detalles td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                .detalles th { background-color: #f2f2f2; }
                .totales { margin-top: 20px; text-align: right; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>PRESUPUESTO</h1>
                <h2>N°: {$presupuesto['numero_presupuesto']}</h2>
                <p>Fecha: " . date('d/m/Y H:i', strtotime($presupuesto['fecha_creacion'])) . "</p>
                <p>Válido por: {$presupuesto['validez_dias']} días</p>
            </div>
            
            <div class='info'>
                <h3>Datos del Cliente</h3>
                <p><strong>Nombre:</strong> {$presupuesto['cliente_nombre']} {$presupuesto['cliente_apellido']}</p>
                <p><strong>Teléfono:</strong> {$presupuesto['cliente_telefono']}</p>
                <p><strong>Email:</strong> {$presupuesto['cliente_email']}</p>
            </div>
            
            <h3>Descripción</h3>
            <p>" . nl2br($presupuesto['descripcion']) . "</p>
            
            <table class='detalles'>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Precio Unitario</th>
                        <th>Subtotal</th>
                        <th>IVA</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>";
        
        foreach ($presupuesto['detalles'] as $detalle) {
            $html .= "
                    <tr>
                        <td>{$detalle['producto_nombre']}</td>
                        <td>" . number_format($detalle['cantidad'], 2) . "</td>
                        <td>$" . number_format($detalle['precio_unitario'], 2) . "</td>
                        <td>$" . number_format($detalle['subtotal'], 2) . "</td>
                        <td>" . number_format($detalle['iva_porcentaje'], 2) . "%</td>
                        <td>$" . number_format($detalle['total'], 2) . "</td>
                    </tr>";
        }
        
        $html .= "
                </tbody>
            </table>
            
            <div class='totales'>
                <p><strong>Subtotal:</strong> $ " . number_format($presupuesto['subtotal'], 2) . "</p>
                <p><strong>IVA ({$presupuesto['iva_porcentaje']}%):</strong> $ " . number_format($presupuesto['iva_total'], 2) . "</p>
                <p><strong>TOTAL:</strong> $ " . number_format($presupuesto['total'], 2) . "</p>
            </div>
            
            <div class='info'>
                <h3>Observaciones</h3>
                <p>" . nl2br($presupuesto['observaciones']) . "</p>
            </div>
            
            <div class='info'>
                <p><strong>Estado:</strong> " . ucfirst($presupuesto['estado']) . "</p>
                <p><strong>Creado por:</strong> {$presupuesto['creado_por_nombre']} {$presupuesto['creado_por_apellido']}</p>
            </div>
        </body>
        </html>";
        
        return $html;
    }
}
