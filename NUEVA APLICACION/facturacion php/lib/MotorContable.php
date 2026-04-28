<?php
/**
 * Motor Contable - Generación automática de asientos contables
 */

class MotorContable {
    private $db;
    private $empresa_id;
    
    public function __construct($db, $empresa_id) {
        $this->db = $db;
        $this->empresa_id = $empresa_id;
    }
    
    /**
     * Generar asiento contable para una venta
     */
    public function generarAsientoVenta($venta_id) {
        try {
            // Obtener datos de la venta
            $stmt = $this->db->prepare("SELECT v.*, c.nombre as cliente_nombre FROM ventas v LEFT JOIN clientes c ON v.cliente_id = c.id WHERE v.id = ? AND v.empresa_id = ?");
            $stmt->execute([$venta_id, $this->empresa_id]);
            $venta = $stmt->fetch();
            
            if (!$venta) {
                throw new Exception("Venta no encontrada");
            }
            
            // Obtener items de la venta
            $stmt = $this->db->prepare("SELECT vi.*, p.nombre as producto_nombre FROM venta_items vi LEFT JOIN productos p ON vi.producto_id = p.id WHERE vi.venta_id = ?");
            $stmt->execute([$venta_id]);
            $items = $stmt->fetchAll();
            
            // Obtener cuentas contables
            $cuentas = $this->obtenerCuentasPorDefecto();
            
            // Crear asiento
            $asiento_numero = $this->generarNumeroAsiento();
            $fecha_asiento = date('Y-m-d H:i:s');
            
            // Insertar asiento cabecera
            $stmt = $this->db->prepare("INSERT INTO asientos_contables (numero, fecha, descripcion, empresa_id, tipo_comprobante, nro_comprobante, total_debe, total_haber, usuario_id) VALUES (?, ?, ?, ?, 'FACTURA', ?, 0, 0, ?)");
            $stmt->execute([$asiento_numero, $fecha_asiento, "Venta #$venta_id - {$venta['cliente_nombre']}", $this->empresa_id, $venta_id, 1]);
            $asiento_id = $this->db->lastInsertId();
            
            // Insertar movimientos
            $total_debe = 0;
            $total_haber = 0;
            
            // 1. Venta a crédito (Debe)
            $monto_venta = $venta['total'];
            $stmt = $this->db->prepare("INSERT INTO asientos_movimientos (asiento_id, cuenta_id, debe, haber, descripcion) VALUES (?, ?, ?, ?, 'Venta #$venta_id')");
            $stmt->execute([$asiento_id, $cuentas['cuentas_por_cobrar'], $monto_venta, 0]);
            $total_debe += $monto_venta;
            
            // 2. IVA débito fiscal (Debe) - 21% de la venta
            $iva_debito = $monto_venta * 0.21;
            $stmt = $this->db->prepare("INSERT INTO asientos_movimientos (asiento_id, cuenta_id, debe, haber, descripcion) VALUES (?, ?, ?, ?, 'IVA Débito Fiscal #$venta_id')");
            $stmt->execute([$asiento_id, $cuentas['iva_debito_fiscal'], $iva_debito, 0]);
            $total_debe += $iva_debito;
            
            // 3. Ventas (Haber)
            $stmt = $this->db->prepare("INSERT INTO asientos_movimientos (asiento_id, cuenta_id, debe, haber, descripcion) VALUES (?, ?, ?, ?, 'Ingreso por Venta #$venta_id')");
            $stmt->execute([$asiento_id, $cuentas['ventas'], 0, $monto_venta]);
            $total_haber += $monto_venta;
            
            // 4. Costo de ventas (Debe)
            $costo_total = 0;
            foreach ($items as $item) {
                $costo_total += ($item['costo_unitario'] * $item['cantidad']);
            }
            $stmt = $this->db->prepare("INSERT INTO asientos_movimientos (asiento_id, cuenta_id, debe, haber, descripcion) VALUES (?, ?, ?, ?, 'Costo Venta #$venta_id')");
            $stmt->execute([$asiento_id, $cuentas['costo_ventas'], $costo_total, 0]);
            $total_debe += $costo_total;
            
            // 5. Inventario (Haber)
            $stmt = $this->db->prepare("INSERT INTO asientos_movimientos (asiento_id, cuenta_id, debe, haber, descripcion) VALUES (?, ?, ?, ?, 'Salida Inventario #$venta_id')");
            $stmt->execute([$asiento_id, $cuentas['inventario'], 0, $costo_total]);
            $total_haber += $costo_total;
            
            // Actualizar totales del asiento
            $stmt = $this->db->prepare("UPDATE asientos_contables SET total_debe = ?, total_haber = ? WHERE id = ?");
            $stmt->execute([$total_debe, $total_haber, $asiento_id]);
            
            return [
                'success' => true,
                'asiento_id' => $asiento_id,
                'numero' => $asiento_numero,
                'total_debe' => $total_debe,
                'total_haber' => $total_haber
            ];
            
        } catch (Exception $e) {
            throw new Exception("Error generando asiento contable: " . $e->getMessage());
        }
    }
    
    /**
     * Obtener cuentas por defecto
     */
    private function obtenerCuentasPorDefecto() {
        // Intentar obtener desde plan de cuentas, si no existe usar valores por defecto
        $cuentas_por_defecto = [
            'cuentas_por_cobrar' => 1,
            'ventas' => 4,
            'iva_debito_fiscal' => 5,
            'costo_ventas' => 6,
            'inventario' => 3
        ];
        
        try {
            $stmt = $this->db->prepare("SELECT id, codigo FROM plan_cuentas WHERE empresa_id = ? AND codigo IN (?, ?, ?, ?, ?)");
            $codigos = ['1.01.001', '4.01.001', '5.01.001', '6.01.001', '3.01.001'];
            $stmt->execute([$this->empresa_id, ...$codigos]);
            $cuentas_db = $stmt->fetchAll();
            
            foreach ($cuentas_db as $cuenta) {
                switch ($cuenta['codigo']) {
                    case '1.01.001': $cuentas_por_defecto['cuentas_por_cobrar'] = $cuenta['id']; break;
                    case '4.01.001': $cuentas_por_defecto['ventas'] = $cuenta['id']; break;
                    case '5.01.001': $cuentas_por_defecto['iva_debito_fiscal'] = $cuenta['id']; break;
                    case '6.01.001': $cuentas_por_defecto['costo_ventas'] = $cuenta['id']; break;
                    case '3.01.001': $cuentas_por_defecto['inventario'] = $cuenta['id']; break;
                }
            }
        } catch (Exception $e) {
            // Si falla, usar valores por defecto
        }
        
        return $cuentas_por_defecto;
    }
    
    /**
     * Generar número de asiento
     */
    private function generarNumeroAsiento() {
        $stmt = $this->db->prepare("SELECT COALESCE(MAX(numero), 0) + 1 as siguiente FROM asientos_contables WHERE empresa_id = ?");
        $stmt->execute([$this->empresa_id]);
        $resultado = $stmt->fetch();
        return $resultado['siguiente'];
    }
}
?>
