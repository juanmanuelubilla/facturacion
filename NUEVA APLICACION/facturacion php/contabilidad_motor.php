<?php
require_once 'config.php';

/**
 * Motor de Asientos Contables Automáticos
 * Genera asientos contables a partir de las operaciones del sistema
 */

class MotorContable {
    private $db;
    private $empresa_id;
    
    public function __construct($empresa_id, $db = null) {
        $this->empresa_id = $empresa_id;
        $this->db = $db ?: getDB();
    }
    
    /**
     * Generar asiento automático para una venta
     */
    public function generarAsientoVenta($venta_id) {
        try {
            $this->db->beginTransaction();
            
            // Obtener datos de la venta y método de pago
            $stmt = $this->db->prepare("
                SELECT v.*, c.nombre as cliente_nombre, c.documento as cliente_documento
                FROM ventas v 
                LEFT JOIN clientes c ON v.cliente_id = c.id 
                WHERE v.id = ? AND v.empresa_id = ?
            ");
            $stmt->execute([$venta_id, $this->empresa_id]);
            $venta = $stmt->fetch();
            
            // Obtener método de pago desde la tabla pagos
            $stmt_pago = $this->db->prepare("
                SELECT metodo FROM pagos 
                WHERE venta_id = ? AND empresa_id = ?
                LIMIT 1
            ");
            $stmt_pago->execute([$venta_id, $this->empresa_id]);
            $pago = $stmt_pago->fetch();
            $metodo_pago = $pago['metodo'] ?? 'EFECTIVO';
            
            if (!$venta) {
                throw new Exception("Venta no encontrada");
            }
            
            // Obtener items de la venta
            $stmt = $this->db->prepare("
                SELECT vi.*, p.nombre as producto_nombre, p.costo as producto_costo
                FROM venta_items vi
                LEFT JOIN productos p ON vi.producto_id = p.id
                WHERE vi.venta_id = ?
            ");
            $stmt->execute([$venta_id]);
            $items = $stmt->fetchAll();
            
            // Calcular totales
            $total_venta = 0;
            $total_iva = 0;
            $total_gravado = 0;
            $total_exento = 0;
            
            foreach ($items as $item) {
                $subtotal_con_iva = $item['subtotal'];
                $total_venta += $subtotal_con_iva;
                
                // Calcular IVA (21% por defecto, configurable)
                $iva_tasa = $this->getIVATasa($item['producto_id']);
                
                if ($iva_tasa > 0) {
                    // Calcular base imponible y monto de IVA
                    $base_imponible = $subtotal_con_iva / (1 + $iva_tasa / 100);
                    $iva_monto = $base_imponible * ($iva_tasa / 100);
                    $total_iva += $iva_monto;
                    $total_gravado += $base_imponible;
                } else {
                    // Producto exento
                    $total_exento += $subtotal_con_iva;
                }
            }
            
            // Generar número de asiento
            $numero_asiento = $this->getProximoNumeroAsiento();
            
            // Crear asiento principal
            $stmt = $this->db->prepare("
                INSERT INTO asientos_contables 
                (empresa_id, numero, fecha, descripcion, tipo_comprobante, nro_comprobante, total_debe, total_haber, usuario_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $descripcion = "Venta #{$venta_id} - {$venta['cliente_nombre']}";
            $stmt->execute([
                $this->empresa_id,
                $numero_asiento,
                $venta['fecha'],
                $descripcion,
                'Factura', // Tipo configurable según factura A/B/C
                $venta_id,
                $total_venta,  // Total ya incluye IVA
                $total_venta,  // Total ya incluye IVA
                $venta['usuario_id']
            ]);
            
            $asiento_id = $this->db->lastInsertId();
            
            // Agregar detalles del asiento para venta de contado
            
            // 1. DEBE: Caja (venta de contado)
            $cuenta_caja = $this->getCuentaPorMetodoPago($metodo_pago);
            if ($cuenta_caja) {
                $this->agregarDetalleAsiento($asiento_id, $cuenta_caja['id'], $total_venta, 0, 'Venta de contado');
            }
            
            // 2. HABER: Ventas (base imponible sin IVA)
            $cuenta_ventas = $this->getCuentaPorCodigo('5.01.01'); // Ventas
            if ($cuenta_ventas) {
                $this->agregarDetalleAsiento($asiento_id, $cuenta_ventas['id'], 0, $total_gravado + $total_exento, 'Ventas del período');
            }
            
            // 3. DEBE: Costo de Mercaderías Vendidas
            $costo_total = 0;
            foreach ($items as $item) {
                // Usar el costo guardado en venta_items, si no existe usar costo del producto
                $costo_unitario = $item['costo_unitario'] ?? $item['producto_costo'] ?? 0;
                $cantidad = $item['cantidad'];
                $costo_total += $costo_unitario * $cantidad;
            }
            
            if ($costo_total > 0) {
                $cuenta_costo_venta = $this->getCuentaPorCodigo('6.01.01'); // Costo Mercaderías Vendidas
                if ($cuenta_costo_venta) {
                    $this->agregarDetalleAsiento($asiento_id, $cuenta_costo_venta['id'], $costo_total, 0, 'Costo de mercaderías vendidas');
                }
            }
            
            // 4. HABER: IVA Débito Fiscal
            if ($total_iva > 0) {
                $cuenta_iva_debito = $this->getCuentaPorCodigo('3.02.01'); // IVA Débito Fiscal
                if ($cuenta_iva_debito) {
                    $this->agregarDetalleAsiento($asiento_id, $cuenta_iva_debito['id'], 0, $total_iva, 'IVA 21% ventas');
                }
            }
            
            // Actualizar cuenta corriente del cliente SOLO si es venta a crédito
            // Por ahora, asumimos que todas las ventas son de contado (no hay fiado)
            // Si en el futuro se agrega opción de crédito, descomentar esto:
            /*
            if ($venta['cliente_id'] && $venta['cliente_id'] > 0 && $venta['tipo_venta'] === 'CREDITO') {
                $this->actualizarCtaCteCliente($venta['cliente_id'], $venta_id, $total_venta, $venta['fecha'], $asiento_id);
            }
            */
            
            $this->db->commit();
            
            return [
                'success' => true,
                'asiento_id' => $asiento_id,
                'numero' => $numero_asiento,
                'mensaje' => 'Asiento contable generado correctamente'
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Generar asiento para una compra/proveedor
     */
    public function generarAsientoCompra($compra_data) {
        try {
            $this->db->beginTransaction();
            
            // Lógica similar a venta pero para compras
            // DEBE: Mercaderías, IVA Crédito Fiscal
            // HABER: Proveedores
            
            $this->db->commit();
            
            return ['success' => true, 'mensaje' => 'Asiento de compra generado'];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Generar asiento para pago de cliente
     */
    public function generarAsientoPagoCliente($pago_data) {
        try {
            $this->db->beginTransaction();
            
            // DEBE: Caja/Banco
            // HABER: Clientes
            
            $this->db->commit();
            
            return ['success' => true, 'mensaje' => 'Asiento de pago generado'];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Generar asiento para gasto
     */
    public function generarAsientoGasto($gasto_data) {
        try {
            $this->db->beginTransaction();
            
            $monto = $gasto_data['monto'];
            $descripcion = $gasto_data['descripcion'];
            $categoria = $gasto_data['categoria'];
            $metodo_pago = $gasto_data['metodo_pago'] ?? 'EFECTIVO';
            
            // Generar número de asiento
            $numero_asiento = $this->getProximoNumeroAsiento();
            
            // Crear asiento principal
            $stmt = $this->db->prepare("
                INSERT INTO asientos_contables 
                (empresa_id, numero, fecha, descripcion, tipo_comprobante, nro_comprobante, total_debe, total_haber, usuario_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $this->empresa_id,
                $numero_asiento,
                date('Y-m-d'),
                $descripcion,
                'Gasto',
                $gasto_data['id'] ?? 0,
                $monto,
                $monto,
                $_SESSION['user_id'] ?? 1
            ]);
            
            $asiento_id = $this->db->lastInsertId();
            
            // DEBE: Cuenta de gasto según categoría
            $cuenta_gasto = $this->getCuentaPorCategoria($categoria);
            if ($cuenta_gasto) {
                $this->agregarDetalleAsiento($asiento_id, $cuenta_gasto['id'], $monto, 0, $descripcion);
            }
            
            // HABER: Cuenta de pago según método
            $cuenta_pago = $this->getCuentaPorMetodoPago($metodo_pago);
            if ($cuenta_pago) {
                $this->agregarDetalleAsiento($asiento_id, $cuenta_pago['id'], 0, $monto, 'Pago en ' . $metodo_pago);
            }
            
            $this->db->commit();
            
            return [
                'success' => true, 
                'asiento_id' => $asiento_id,
                'numero' => $numero_asiento,
                'mensaje' => 'Asiento de gasto generado correctamente'
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Generar asiento para movimiento de finanzas
     */
    public function generarAsientoFinanzas($movimiento_data) {
        try {
            $this->db->beginTransaction();
            
            $monto = $movimiento_data['monto'];
            $descripcion = $movimiento_data['descripcion'];
            $tipo = $movimiento_data['tipo']; // 'INGRESO' o 'GASTO'
            $categoria = $movimiento_data['categoria'];
            $metodo_pago = $movimiento_data['metodo_pago'] ?? 'EFECTIVO';
            
            // Generar número de asiento
            $numero_asiento = $this->getProximoNumeroAsiento();
            
            // Crear asiento principal
            $stmt = $this->db->prepare("
                INSERT INTO asientos_contables 
                (empresa_id, numero, fecha, descripcion, tipo_comprobante, nro_comprobante, total_debe, total_haber, usuario_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $this->empresa_id,
                $numero_asiento,
                date('Y-m-d'),
                $descripcion,
                'Movimiento',
                $movimiento_data['id'] ?? 0,
                $monto,
                $monto,
                $_SESSION['user_id'] ?? 1
            ]);
            
            $asiento_id = $this->db->lastInsertId();
            
            if ($tipo === 'INGRESO') {
                // DEBE: Cuenta de caja/banco
                $cuenta_caja = $this->getCuentaPorMetodoPago($metodo_pago);
                if ($cuenta_caja) {
                    $this->agregarDetalleAsiento($asiento_id, $cuenta_caja['id'], $monto, 0, $descripcion);
                }
                
                // HABER: Cuenta de ingresos
                $cuenta_ingreso = $this->getCuentaPorCategoria($categoria);
                if ($cuenta_ingreso) {
                    $this->agregarDetalleAsiento($asiento_id, $cuenta_ingreso['id'], 0, $monto, $descripcion);
                }
            } else {
                // GASTO
                // DEBE: Cuenta de gasto
                $cuenta_gasto = $this->getCuentaPorCategoria($categoria);
                if ($cuenta_gasto) {
                    $this->agregarDetalleAsiento($asiento_id, $cuenta_gasto['id'], $monto, 0, $descripcion);
                }
                
                // HABER: Cuenta de caja/banco
                $cuenta_caja = $this->getCuentaPorMetodoPago($metodo_pago);
                if ($cuenta_caja) {
                    $this->agregarDetalleAsiento($asiento_id, $cuenta_caja['id'], 0, $monto, 'Pago en ' . $metodo_pago);
                }
            }
            
            $this->db->commit();
            
            return [
                'success' => true, 
                'asiento_id' => $asiento_id,
                'numero' => $numero_asiento,
                'mensaje' => 'Asiento de finanzas generado correctamente'
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // Métodos auxiliares privados
    
    private function getProximoNumeroAsiento() {
        $stmt = $this->db->prepare("SELECT COALESCE(MAX(numero), 0) + 1 as proximo FROM asientos_contables WHERE empresa_id = ?");
        $stmt->execute([$this->empresa_id]);
        $result = $stmt->fetch();
        return $result['proximo'];
    }
    
    private function getCuentaPorCodigo($codigo) {
        $stmt = $this->db->prepare("SELECT * FROM plan_cuentas WHERE empresa_id = ? AND codigo = ? AND imputable = 1");
        $stmt->execute([$this->empresa_id, $codigo]);
        return $stmt->fetch();
    }
    
    private function getIVATasa($producto_id) {
        // Por defecto 21%, configurable por producto
        return 21.00;
    }
    
    private function agregarDetalleAsiento($asiento_id, $cuenta_id, $debe, $haber, $descripcion) {
        $stmt = $this->db->prepare("
            INSERT INTO asiento_detalles (asiento_id, cuenta_id, debe, haber, descripcion)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$asiento_id, $cuenta_id, $debe, $haber, $descripcion]);
    }
    
    private function actualizarCtaCteCliente($cliente_id, $venta_id, $importe, $fecha, $asiento_id) {
        // Solo crear cuenta corriente si el cliente_id es válido
        if (!$cliente_id || $cliente_id <= 0) {
            return;
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO ctacte_clientes 
            (empresa_id, cliente_id, tipo_movimiento, comprobante_tipo, comprobante_nro, importe, saldo, fecha, asiento_id)
            VALUES (?, ?, 'DEUDA', 'Factura', ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$this->empresa_id, $cliente_id, $venta_id, $importe, $importe, $fecha, $asiento_id]);
    }
    
    private function getCuentaPorCategoria($categoria) {
        // Mapeo de categorías de finanzas a cuentas contables
        $mapeo = [
            'Ventas' => '5.01.01', // Ventas
            'Servicios' => '5.03.01', // Otros Ingresos
            'Alquiler' => '6.03.01', // Alquiler Local Comercial
            'Servicios' => '6.04.01', // Servicios (Luz, Agua, etc.)
            'Sueldos' => '6.02.01', // Sueldos y Jornales
            'Publicidad' => '6.05.01', // Publicidad y Marketing
            'Impuestos' => '6.08.01', // Impuestos y Tasas
            'Honorarios' => '6.10.01', // Honorarios Profesionales
            'Mantenimiento' => '6.09.01', // Mantenimiento y Reparaciones
        ];
        
        $codigo = $mapeo[$categoria] ?? null;
        if ($codigo) {
            return $this->getCuentaPorCodigo($codigo);
        }
        
        // Si no encuentra mapeo, buscar cuenta por nombre
        $stmt = $this->db->prepare("SELECT * FROM plan_cuentas WHERE empresa_id = ? AND nombre LIKE ? AND imputable = 1 LIMIT 1");
        $stmt->execute([$this->empresa_id, "%{$categoria}%"]);
        return $stmt->fetch();
    }
    
    private function getCuentaPorMetodoPago($metodo_pago) {
        // Mapeo de métodos de pago a cuentas contables
        $mapeo = [
            'EFECTIVO' => '1.01.01', // Caja
            'BANCARIO' => '1.01.02', // Banco Cuenta Corriente
            'TRANSFERENCIA' => '1.01.02', // Banco Cuenta Corriente
            'CHEQUE' => '1.01.02', // Banco Cuenta Corriente
            'TARJETA' => '1.01.02', // Banco Cuenta Corriente
        ];
        
        $codigo = $mapeo[strtoupper($metodo_pago)] ?? '1.01.01'; // Default: Caja
        return $this->getCuentaPorCodigo($codigo);
    }
    
    /**
     * Obtener resumen de cuentas para balance
     */
    public function getResumenCuentas($fecha_hasta = null) {
        $fecha_hasta = $fecha_hasta ?: date('Y-m-d');
        
        $stmt = $this->db->prepare("
            SELECT 
                pc.codigo,
                pc.nombre,
                pc.tipo,
                pc.subtipo,
                COALESCE(SUM(ad.debe), 0) as total_debe,
                COALESCE(SUM(ad.haber), 0) as total_haber,
                CASE 
                    WHEN pc.tipo IN ('ACTIVO', 'GASTO') 
                    THEN COALESCE(SUM(ad.debe) - SUM(ad.haber), 0)
                    WHEN pc.tipo IN ('PASIVO', 'PATRIMONIO_NETO', 'INGRESO') 
                    THEN COALESCE(SUM(ad.haber) - SUM(ad.debe), 0)
                    ELSE 0
                END as saldo
            FROM plan_cuentas pc
            LEFT JOIN asiento_detalles ad ON pc.id = ad.cuenta_id
            LEFT JOIN asientos_contables ac ON ad.asiento_id = ac.id
            WHERE pc.empresa_id = ? AND pc.imputable = 1
            AND (ac.fecha IS NULL OR ac.fecha <= ?)
            GROUP BY pc.id, pc.codigo, pc.nombre, pc.tipo, pc.subtipo
            ORDER BY pc.codigo
        ");
        
        $stmt->execute([$this->empresa_id, $fecha_hasta]);
        return $stmt->fetchAll();
    }
    
    /**
     * Generar libro diario
     */
    public function getLibroDiario($fecha_desde, $fecha_hasta) {
        $stmt = $this->db->prepare("
            SELECT 
                ac.numero,
                ac.fecha,
                ac.descripcion,
                ac.tipo_comprobante,
                ac.nro_comprobante,
                pc.codigo as cuenta_codigo,
                pc.nombre as cuenta_nombre,
                ad.debe,
                ad.haber,
                ad.descripcion as detalle_descripcion
            FROM asientos_contables ac
            JOIN asiento_detalles ad ON ac.id = ad.asiento_id
            JOIN plan_cuentas pc ON ad.cuenta_id = pc.id
            WHERE ac.empresa_id = ? AND ac.fecha BETWEEN ? AND ?
            ORDER BY ac.numero, pc.codigo
        ");
        
        $stmt->execute([$this->empresa_id, $fecha_desde, $fecha_hasta]);
        return $stmt->fetchAll();
    }
}

// API endpoint para generar asientos automáticamente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];
    $empresa_id = $_SESSION['empresa_id'] ?? 0;
    
    $motor = new MotorContable($empresa_id);
    
    header('Content-Type: application/json');
    
    switch ($accion) {
        case 'generar_asiento_venta':
            $venta_id = intval($_POST['venta_id'] ?? 0);
            $resultado = $motor->generarAsientoVenta($venta_id);
            echo json_encode($resultado);
            break;
            
        case 'generar_asiento_gasto':
            $gasto_data = json_decode($_POST['gasto_data'] ?? '{}', true);
            $resultado = $motor->generarAsientoGasto($gasto_data);
            echo json_encode($resultado);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    }
}
?>
