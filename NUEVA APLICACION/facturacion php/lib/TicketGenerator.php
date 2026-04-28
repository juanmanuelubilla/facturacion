<?php
/**
 * Generador de Tickets para PHP
 * Versión adaptada desde Python (ticket.py)
 */
class TicketGenerator {
    private $db;
    private $empresa_id;
    
    public function __construct($db, $empresa_id) {
        $this->db = $db;
        $this->empresa_id = $empresa_id;
    }
    
    public function generarTicket($items, $total, $venta_id, $metodo_pago, $vuelto = 0) {
        try {
            // Leer configuración de la empresa
            $stmt = $this->db->prepare("SELECT * FROM nombre_negocio WHERE empresa_id = ? OR id = 1 LIMIT 1");
            $stmt->execute([$this->empresa_id]);
            $conf = $stmt->fetch();
            
            // Leer comprobante AFIP si existe
            $cbte = null;
            try {
                $stmt = $this->db->prepare("SELECT * FROM comprobante_afip WHERE venta_id = ? AND empresa_id = ?");
                $stmt->execute([$venta_id, $this->empresa_id]);
                $cbte = $stmt->fetch();
            } catch (Exception $e) {
                // Ignorar error
            }
            
            // Buscar datos del cliente asociado a la venta
            $cliente = null;
            try {
                $stmt = $this->db->prepare("
                    SELECT c.nombre, c.documento, c.condicion_iva 
                    FROM clientes c
                    INNER JOIN ventas v ON v.cliente_id = c.id
                    WHERE v.id = ? AND v.empresa_id = ?
                ");
                $stmt->execute([$venta_id, $this->empresa_id]);
                $cliente = $stmt->fetch();
            } catch (Exception $e) {
                $cliente = null;
            }
            
            $linea = str_repeat('=', 40);
            $sep = str_repeat('-', 40);
            $t = [];
            
            // Cabecera
            $t[] = $linea;
            $t[] = $this->centerText(strtoupper($conf['nombre_negocio'] ?? 'MI NEGOCIO'), 40);
            if (!empty($conf['eslogan'])) {
                $t[] = $this->centerText($conf['eslogan'], 40);
            }
            $t[] = $this->centerText($conf['direccion'] ?? 'Direccion no seteada', 40);
            $t[] = $this->centerText('CUIT: ' . ($conf['cuit'] ?? '00-00000000-0'), 40);
            $t[] = $this->centerText('IVA: ' . ($conf['condicion_iva'] ?? 'Consumidor Final'), 40);
            $t[] = $linea;
            
            // Datos venta
            $tipo = $cbte ? 'FACTURA C' : 'TIQUET NO FISCAL';
            $nro = $cbte && !empty($cbte['nro_cbte']) ? sprintf('%08d', $cbte['nro_cbte']) : sprintf('%08d', $venta_id);
            
            $t[] = $this->centerText($tipo, 40);
            $t[] = "P.V.: 00001 - Nro: $nro";
            $t[] = 'Fecha: ' . date('d/m/Y H:i');
            
            // Datos del cliente
            if ($cliente) {
                $t[] = $sep;
                $t[] = 'CLIENTE: ' . strtoupper(substr($cliente['nombre'], 0, 30));
                $t[] = 'DOC/CUIT: ' . ($cliente['documento'] ?? '');
                $t[] = 'IVA: ' . ($cliente['condicion_iva'] ?? 'Consumidor Final');
            } else {
                $t[] = $sep;
                $t[] = 'CLIENTE: CONSUMIDOR FINAL';
            }
            $t[] = $sep;
            
            // Items
            $mon = $conf['moneda'] ?? '$';
            $t[] = sprintf('%-5s %-23s %10s', 'CANT', 'DETALLE', 'SUBT');
            
            foreach ($items as $item) {
                $nom = substr($item['nombre'] ?? 'Prod', 0, 22);
                $cant = $item['cantidad'] ?? 1;
                
                // Formato de cantidad
                $cant_val = floatval($cant);
                $cant_str = ($cant_val % 1 != 0) ? number_format($cant_val, 3) : intval($cant_val);
                
                $sub = $mon . number_format(floatval($item['subtotal'] ?? 0), 2);
                $t[] = sprintf('%-5s %-23s %10s', $cant_str, $nom, $sub);
            }
            
            $t[] = $sep;
            $t[] = sprintf('%-15s %s %22s', 'TOTAL:', $mon, number_format(floatval($total), 2));
            $t[] = sprintf('%-15s %23s', 'PAGO:', $metodo_pago);
            
            // Vuelto
            $vuelto_val = floatval($vuelto);
            if (stripos($metodo_pago, 'EFECTIVO') !== false && $vuelto_val > 0.01) {
                $t[] = sprintf('%-15s %s %22s', 'VUELTO:', $mon, number_format($vuelto_val, 2));
            }
            
            $t[] = $linea;
            
            // Pie AFIP
            if ($cbte && !empty($cbte['cae'])) {
                $t[] = 'CAE: ' . $cbte['cae'];
                $t[] = 'Vto. CAE: ' . ($cbte['fecha_vto_cae'] ?? 'N/A');
                $cuit_limpio = str_replace('-', '', $conf['cuit'] ?? '');
                $cb_afip = $cuit_limpio . '110001' . $cbte['cae'];
                $t[] = '';
                $t[] = $this->centerText($cb_afip, 40);
            }
            
            $t[] = '';
            $t[] = $this->centerText('GRACIAS POR SU COMPRA', 40);
            $t[] = $linea;
            
            return implode("\n", $t);
            
        } catch (Exception $e) {
            return "ERROR AL GENERAR TICKET: " . $e->getMessage();
        }
    }
    
    public function guardarTicket($texto, $venta_id) {
        try {
            // Buscar ruta de tickets configurada
            $stmt = $this->db->prepare("SELECT ruta_tickets FROM nombre_negocio WHERE empresa_id = ? OR id = 1 LIMIT 1");
            $stmt->execute([$this->empresa_id]);
            $res = $stmt->fetch();
            $ruta_base = $res['ruta_tickets'] ?? 'tickets';
            
            // Validar ruta vacía
            if (empty($ruta_base) || trim($ruta_base) === '' || $ruta_base === 'None') {
                $ruta_base = 'tickets';
            }
            
            // Crear ruta final con subcarpeta de empresa
            $ruta_final = realpath(__DIR__ . '/../') . '/' . $ruta_base . '/empresa_' . $this->empresa_id;
            
            // Crear directorios si no existen
            if (!file_exists($ruta_final)) {
                mkdir($ruta_final, 0755, true);
            }
            
            $archivo_path = $ruta_final . '/Ticket_' . $venta_id . '.txt';
            
            file_put_contents($archivo_path, $texto);
            
            return $archivo_path;
            
        } catch (Exception $e) {
            return null;
        }
    }
    
    private function centerText($text, $width) {
        $text = strval($text);
        $len = strlen($text);
        if ($len >= $width) {
            return substr($text, 0, $width);
        }
        $padding = intval(($width - $len) / 2);
        return str_repeat(' ', $padding) . $text . str_repeat(' ', $width - $len - $padding);
    }
}
