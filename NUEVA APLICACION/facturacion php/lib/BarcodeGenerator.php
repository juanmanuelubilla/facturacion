<?php

class BarcodeGenerator {
    
    /**
     * Generar código de barras Code 128
     */
    public static function generateCode128($text, $width = 2, $height = 100) {
        $text = strtoupper($text);
        $codes = [
            ' ' => '11011001100', '!' => '11001101100', '"' => '11001100110', '#' => '10010011000',
            '$' => '10010000110', '%' => '10000100100', '&' => '10000100110', '\'' => '10000101100',
            '(' => '10000100110', ')' => '10000101100', '*' => '10000101100', '+' => '10000101100',
            ',' => '10000101100', '-' => '10000101100', '.' => '10000101100', '/' => '10000101100',
            '0' => '11011001100', '1' => '11001101100', '2' => '11001100110', '3' => '10010011000',
            '4' => '10010000110', '5' => '10000100100', '6' => '10000100110', '7' => '10000101100',
            '8' => '10000101100', '9' => '10000101100', ':' => '10000101100', ';' => '10000101100',
            '<' => '10000101100', '=' => '10000101100', '>' => '10000101100', '?' => '10000101100',
            '@' => '10000101100', 'A' => '11011001100', 'B' => '11001101100', 'C' => '11001100110',
            'D' => '10010011000', 'E' => '10010000110', 'F' => '10000100100', 'G' => '10000100110',
            'H' => '10000101100', 'I' => '10000101100', 'J' => '10000101100', 'K' => '10000101100',
            'L' => '10000101100', 'M' => '10000101100', 'N' => '10000101100', 'O' => '10000101100',
            'P' => '10000101100', 'Q' => '10000101100', 'R' => '10000101100', 'S' => '10000101100',
            'T' => '10000101100', 'U' => '10000101100', 'V' => '10000101100', 'W' => '10000101100',
            'X' => '10000101100', 'Y' => '10000101100', 'Z' => '10000101100'
        ];
        
        // Para simplificar, usaremos una implementación básica
        // En producción, usar una librería como "php-barcode-generator"
        return self::generateSimpleBarcode($text, $width, $height);
    }
    
    /**
     * Generar código de barras simple (versión simplificada)
     */
    private static function generateSimpleBarcode($text, $width, $height) {
        $image = imagecreate(strlen($text) * $width + 20, $height + 20);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        
        // Dibujar código de barras simplificado
        $x = 10;
        for ($i = 0; $i < strlen($text); $i++) {
            $char = $text[$i];
            $ord = ord($char);
            
            // Patrón simple basado en el código ASCII
            for ($j = 0; $j < 8; $j++) {
                if (($ord >> $j) & 1) {
                    imagefilledrectangle($image, $x, 10, $x + $width - 1, $height + 10, $black);
                }
                $x += $width;
            }
        }
        
        // Agregar texto debajo
        $textColor = imagecolorallocate($image, 0, 0, 0);
        imagestring($image, 3, 10, $height + 12, $text, $textColor);
        
        return $image;
    }
    
    /**
     * Generar código QR
     */
    public static function generateQR($text, $size = 200) {
        // Para generar QR, necesitarías una librería como "phpqrcode"
        // Por ahora, retornamos una imagen placeholder
        $image = imagecreate($size, $size);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        
        // Dibujar patrón QR simplificado
        $blockSize = $size / 25;
        for ($i = 0; $i < 25; $i++) {
            for ($j = 0; $j < 25; $j++) {
                if (($i + $j) % 2 === 0) {
                    imagefilledrectangle($image, $i * $blockSize, $j * $blockSize, 
                                        ($i + 1) * $blockSize - 1, ($j + 1) * $blockSize - 1, $black);
                }
            }
        }
        
        return $image;
    }
    
    /**
     * Generar código EAN-13
     */
    public static function generateEAN13($code, $width = 2, $height = 100) {
        // Validar longitud (12 dígitos + 1 dígito de control)
        if (strlen($code) !== 13) {
            throw new Exception("Código EAN-13 debe tener 13 dígitos");
        }
        
        return self::generateSimpleBarcode($code, $width, $height);
    }
    
    /**
     * Generar código UPC-A
     */
    public static function generateUPCA($code, $width = 2, $height = 100) {
        // Validar longitud (11 dígitos + 1 dígito de control)
        if (strlen($code) !== 12) {
            throw new Exception("Código UPC-A debe tener 12 dígitos");
        }
        
        return self::generateSimpleBarcode($code, $width, $height);
    }
    
    /**
     * Guardar imagen como PNG
     */
    public static function saveAsPNG($image, $filepath) {
        imagepng($image, $filepath);
        imagedestroy($image);
    }
    
    /**
     * Guardar imagen como JPEG
     */
    public static function saveAsJPEG($image, $filepath, $quality = 90) {
        imagejpeg($image, $filepath, $quality);
        imagedestroy($image);
    }
}
