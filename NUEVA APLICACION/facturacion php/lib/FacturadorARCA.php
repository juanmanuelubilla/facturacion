<?php
/**
 * Facturador ARCA/AFIP para PHP
 * Versión adaptada desde Python (facturacion_arca.py)
 */
class FacturadorARCA {
    private $empresa_id;
    private $config;
    private $mock_mode;
    private $db;
    
    public function __construct($empresa_id, $db) {
        $this->empresa_id = $empresa_id;
        $this->db = $db;
        $this->config = $this->cargarConfig();
        $this->mock_mode = $this->config['mock'] ?? true;
    }
    
    private function cargarConfig() {
        $stmt = $this->db->prepare("
            SELECT cuit, afip_cert, afip_key, afip_prod, afip_mock 
            FROM nombre_negocio 
            WHERE empresa_id = ? OR id = 1 
            ORDER BY (empresa_id = ?) DESC 
            LIMIT 1
        ");
        $stmt->execute([$this->empresa_id, $this->empresa_id]);
        $res = $stmt->fetch();
        
        if (!$res) {
            return [
                'cuit' => 20123456789,
                'cert' => '',
                'key' => '',
                'produccion' => false,
                'mock' => true
            ];
        }
        
        $cuit_limpio = preg_replace('/[^0-9]/', '', $res['cuit'] ?? '20123456789');
        
        return [
            'cuit' => intval($cuit_limpio) ?: 20123456789,
            'cert' => $res['afip_cert'] ?? '',
            'key' => $res['afip_key'] ?? '',
            'produccion' => boolval($res['afip_prod'] ?? false),
            'mock' => boolval($res['afip_mock'] ?? true)
        ];
    }
    
    public function emitirFacturaC($venta_id, $punto_venta, $dni_cliente, $total) {
        $dni_cliente = strval($dni_cliente ?? "0");
        $dni_int = is_numeric($dni_cliente) ? intval($dni_cliente) : 0;
        
        // Modo mock / manual
        if ($this->mock_mode) {
            $cae = null;
            $nro_cbte = null;
            $res = [];
            
            // Intentar usar función de facturación mock
            try {
                // Aquí podrías integrar con tu sistema de facturación mock
                // Por ahora generamos valores simulados
                $nro_cbte = rand(1, 99999999);
                $cae = strval(rand(10000000000000, 99999999999999));
                $res = ['status' => 'mock', 'nro' => $nro_cbte, 'cae' => $cae];
            } catch (Exception $e) {
                $res = ["error" => $e->getMessage()];
                $cae = strval(rand(10000000000000, 99999999999999));
                $nro_cbte = rand(1, 99999999);
            }
            
            if (!$nro_cbte) {
                $nro_cbte = rand(1, 99999999);
            }
            
            return $this->guardarEnDB(
                $venta_id,
                11, // Tipo C
                $punto_venta,
                $nro_cbte,
                $cae,
                date('Ymd'),
                "APROBADO",
                json_encode($res)
            );
        }
        
        // AFIP Real - Requiere librerías adicionales de AFIP para PHP
        // TODO: Integrar con librería AFIP para PHP (ej: afipsdk/afip.php)
        // Por ahora retornamos error indicando que se necesita integración
        return [
            'status' => 'ERROR',
            'message' => 'Modo AFIP real requiere integración con librería AFIP para PHP'
        ];
    }
    
    private function guardarEnDB($venta_id, $tipo, $punto, $nro, $cae, $vto, $estado, $response) {
        try {
            $fecha_vto = null;
            if ($vto) {
                try {
                    $fecha_vto = date('Y-m-d', strtotime($vto));
                } catch (Exception $e) {
                    // Ignorar error de fecha
                }
            }
            
            $entorno_db = ($this->config['produccion'] ?? false) ? "PROD" : "DEV";
            
            $sql = "
                INSERT INTO comprobante_afip 
                (empresa_id, venta_id, tipo_cbte, punto_vta, nro_cbte, cae, fecha_vto_cae, estado, response_afip, entorno)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $this->empresa_id,
                $venta_id,
                $tipo,
                $punto,
                $nro,
                $cae,
                $fecha_vto,
                $estado,
                $response,
                $entorno_db
            ]);
            
            return [
                'status' => 'OK',
                'cae' => $cae,
                'nro' => $nro
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'ERROR_DB',
                'message' => $e->getMessage()
            ];
        }
    }
}
