<?php

class PDFGenerator {
    
    /**
     * Generar PDF de etiquetas
     */
    public static function generateLabelsPDF($labels, $config = []) {
        $defaults = [
            'width' => 50,      // mm
            'height' => 30,     // mm
            'columns' => 2,
            'rows' => 5,
            'margin' => 5,      // mm
            'show_price' => true,
            'show_code' => true,
            'show_name' => true
        ];
        
        $config = array_merge($defaults, $config);
        
        // Crear HTML para el PDF
        $html = self::generateLabelsHTML($labels, $config);
        
        // Usar una librería de PDF real en producción (TCPDF, FPDF, etc.)
        // Por ahora, guardamos como HTML que puede imprimirse
        return $html;
    }
    
    /**
     * Generar HTML de etiquetas
     */
    private static function generateLabelsHTML($labels, $config) {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Etiquetas</title>
    <style>
        @page {
            size: A4;
            margin: 10mm;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
        }
        .label {
            width: ' . $config['width'] . 'mm;
            height: ' . $config['height'] . 'mm;
            border: 1px solid #000;
            display: inline-block;
            margin: ' . $config['margin'] . 'mm;
            padding: 2mm;
            text-align: center;
            page-break-inside: avoid;
        }
        .label-name {
            font-weight: bold;
            font-size: 8px;
            margin-bottom: 2px;
            word-wrap: break-word;
        }
        .label-price {
            font-size: 10px;
            font-weight: bold;
            color: #000;
        }
        .label-code {
            font-size: 7px;
            margin-top: 2px;
        }
        .barcode-placeholder {
            width: 100%;
            height: 15px;
            background: repeating-linear-gradient(
                90deg,
                #000,
                #000 1px,
                #fff 1px,
                #fff 2px
            );
            margin: 2px 0;
        }
    </style>
</head>
<body>';
        
        foreach ($labels as $label) {
            $html .= '<div class="label">';
            
            if ($config['show_name']) {
                $html .= '<div class="label-name">' . htmlspecialchars($label['nombre']) . '</div>';
            }
            
            if ($config['show_price']) {
                $html .= '<div class="label-price">$' . number_format($label['precio'], 2) . '</div>';
            }
            
            $html .= '<div class="barcode-placeholder"></div>';
            
            if ($config['show_code']) {
                $html .= '<div class="label-code">' . htmlspecialchars($label['codigo_barra']) . '</div>';
            }
            
            $html .= '</div>';
        }
        
        $html .= '</body></html>';
        
        return $html;
    }
    
    /**
     * Generar etiqueta individual
     */
    public static function generateSingleLabel($label, $config = []) {
        $defaults = [
            'width' => 50,
            'height' => 30,
            'show_price' => true,
            'show_code' => true,
            'show_name' => true
        ];
        
        $config = array_merge($defaults, $config);
        
        return self::generateLabelsHTML([$label], $config);
    }
    
    /**
     * Generar etiquetas en lote
     */
    public static function generateBatchLabels($producto_id, $cantidad, $config = []) {
        // No hacer require_once de config.php, asumir que ya está incluido
        
        $producto = fetch("SELECT * FROM productos WHERE id = ?", [$producto_id]);
        
        if (!$producto) {
            throw new Exception("Producto no encontrado");
        }
        
        $labels = [];
        for ($i = 0; $i < $cantidad; $i++) {
            $labels[] = [
                'nombre' => $producto['nombre'],
                'precio' => $producto['precio'],
                'codigo_barra' => $producto['codigo_barra'] ?: $producto['codigo']
            ];
        }
        
        return self::generateLabelsPDF($labels, $config);
    }
    
    /**
     * Generar código de barras automático
     */
    public static function generateAutoBarcode($empresa_id) {
        // Formato: [EMPRESA][AÑO][SECUENCIAL6] + [CHECKSUM]
        // Ejemplo: 0012400012345 (13 dígitos EAN-13)
        
        $año = date('y'); // 24 para 2024
        $empresa_prefijo = str_pad($empresa_id, 2, '0', STR_PAD_LEFT); // 01, 02, etc.
        
        // Obtener el último código de esta empresa y año
        $prefijo_busqueda = $empresa_prefijo . $año;
        $ultimo = fetch("SELECT codigo_barra FROM productos WHERE empresa_id = ? AND codigo_barra IS NOT NULL AND codigo_barra != '' AND codigo_barra LIKE ? ORDER BY id DESC LIMIT 1", [$empresa_id, $prefijo_busqueda . '%']);
        
        if ($ultimo && $ultimo['codigo_barra']) {
            // Extraer el secuencial (últimos 6 dígitos antes del checksum)
            $base = substr($ultimo['codigo_barra'], 0, 12);
            $secuencial = intval(substr($base, -6)) + 1;
        } else {
            // Primer código del año: 000001
            $secuencial = 1;
        }
        
        // Construir código base (12 dígitos)
        $secuencial_str = str_pad($secuencial, 6, '0', STR_PAD_LEFT);
        $base_code = $empresa_prefijo . $año . $secuencial_str;
        
        // Calcular y agregar checksum
        $checksum = self::calculateEAN13Checksum($base_code);
        
        return $base_code . $checksum;
    }
    
    /**
     * Calcular dígito de verificación EAN-13
     */
    public static function calculateEAN13Checksum($code) {
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $digit = intval($code[$i]);
            $sum += ($i % 2 === 0) ? $digit : $digit * 3;
        }
        $checksum = (10 - ($sum % 10)) % 10;
        return $checksum;
    }
    
    /**
     * Generar código EAN-13 completo
     */
    public static function generateEAN13($prefix = '') {
        // Prefix puede ser el código de país (ej: 779 para Argentina)
        $base = $prefix ?: '779'; // Código de país Argentina por defecto
        
        // Generar 9 dígitos aleatorios
        for ($i = 0; $i < 9; $i++) {
            $base .= rand(0, 9);
        }
        
        // Calcular dígito de verificación
        $checksum = self::calculateEAN13Checksum($base);
        
        return $base . $checksum;
    }
}
