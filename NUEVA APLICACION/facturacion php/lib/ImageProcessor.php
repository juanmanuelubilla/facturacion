<?php
/**
 * Procesador de Imágenes
 * Maneja crop, resize, miniaturas, optimización y conversión de formatos
 */

class ImageProcessor {
    private $maxWidth = 800;
    private $maxHeight = 800;
    private $thumbnailWidth = 200;
    private $thumbnailHeight = 200;
    private $quality = 85;
    
    /**
     * Generar nombre único con fecha/hora
     */
    public function generateUniqueFilename($originalName) {
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        $timestamp = date('Ymd_His');
        $random = substr(md5(uniqid()), 0, 8);
        return $timestamp . '_' . $random . '.' . $ext;
    }
    
    /**
     * Generar ruta tipo WordPress (año/mes)
     */
    public function generateWordPressPath($basePath) {
        $year = date('Y');
        $month = date('m');
        return $basePath . $year . '/' . $month . '/';
    }
    
    /**
     * Procesar imagen completa
     */
    public function processImage($sourcePath, $destinationPath, $generateThumbnail = true) {
        // Obtener información de la imagen
        $imageInfo = $this->getImageInfo($sourcePath);
        if (!$imageInfo) {
            return ['success' => false, 'error' => 'No se pudo leer la imagen'];
        }
        
        // Crear imagen desde archivo
        $image = $this->createImageFromFile($sourcePath, $imageInfo);
        if (!$image) {
            return ['success' => false, 'error' => 'No se pudo crear la imagen'];
        }
        
        // Redimensionar imagen principal
        $resizedImage = $this->resizeImage($image, $this->maxWidth, $this->maxHeight);
        if (!$resizedImage) {
            return ['success' => false, 'error' => 'Error al redimensionar'];
        }
        
        // Guardar imagen principal optimizada
        $mainSaved = $this->saveImage($resizedImage, $destinationPath, $imageInfo['mime'], $this->quality);
        if (!$mainSaved) {
            return ['success' => false, 'error' => 'Error al guardar imagen principal'];
        }
        
        // Generar miniatura si se solicita
        $thumbnailPath = null;
        if ($generateThumbnail) {
            $thumbnailPath = $this->generateThumbnail($image, $destinationPath);
        }
        
        // Liberar memoria
        imagedestroy($image);
        imagedestroy($resizedImage);
        
        return [
            'success' => true,
            'main_path' => $destinationPath,
            'thumbnail_path' => $thumbnailPath
        ];
    }
    
    /**
     * Obtener información de la imagen
     */
    private function getImageInfo($path) {
        $info = getimagesize($path);
        if (!$info) {
            return null;
        }
        
        return [
            'width' => $info[0],
            'height' => $info[1],
            'mime' => $info['mime'],
            'type' => $info[2]
        ];
    }
    
    /**
     * Crear imagen desde archivo según tipo
     */
    private function createImageFromFile($path, $imageInfo) {
        switch ($imageInfo['mime']) {
            case 'image/jpeg':
                return imagecreatefromjpeg($path);
            case 'image/png':
                return imagecreatefrompng($path);
            case 'image/gif':
                return imagecreatefromgif($path);
            case 'image/webp':
                return imagecreatefromwebp($path);
            default:
                return null;
        }
    }
    
    /**
     * Redimensionar imagen manteniendo aspect ratio
     */
    private function resizeImage($image, $maxWidth, $maxHeight) {
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Calcular nuevas dimensiones manteniendo aspect ratio
        $ratio = min($maxWidth / $width, $maxHeight / $height);
        $newWidth = (int)($width * $ratio);
        $newHeight = (int)($height * $ratio);
        
        // Crear nueva imagen
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // Habilitar transparencia para PNG
        if ($this->isTransparentImage($image)) {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
            imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
        }
        
        // Redimensionar
        imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        
        return $newImage;
    }
    
    /**
     * Generar miniatura cuadrada con crop
     */
    private function generateThumbnail($image, $originalPath) {
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Calcular crop para obtener cuadrado central
        $size = min($width, $height);
        $x = (int)(($width - $size) / 2);
        $y = (int)(($height - $size) / 2);
        
        // Crear miniatura
        $thumbnail = imagecreatetruecolor($this->thumbnailWidth, $this->thumbnailHeight);
        
        // Habilitar transparencia
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
        $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
        imagefilledrectangle($thumbnail, 0, 0, $this->thumbnailWidth, $this->thumbnailHeight, $transparent);
        
        // Crop y resize
        imagecopyresampled($thumbnail, $image, 0, 0, $x, $y, $this->thumbnailWidth, $this->thumbnailHeight, $size, $size);
        
        // Generar nombre de miniatura
        $pathInfo = pathinfo($originalPath);
        $thumbnailPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_thumb.' . $pathInfo['extension'];
        
        // Guardar miniatura
        $this->saveImage($thumbnail, $thumbnailPath, 'image/jpeg', 75);
        
        imagedestroy($thumbnail);
        
        return $thumbnailPath;
    }
    
    /**
     * Guardar imagen optimizada
     */
    private function saveImage($image, $path, $mime, $quality) {
        // Crear directorio si no existe
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // Convertir a JPEG para mejor optimización (excepto si es GIF animado)
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
        switch ($extension) {
            case 'png':
                imagepng($image, $path, (int)(9 * ($quality / 100)));
                break;
            case 'gif':
                imagegif($image, $path);
                break;
            case 'webp':
                imagewebp($image, $path, $quality);
                break;
            default:
                imagejpeg($image, $path, $quality);
        }
        
        return file_exists($path);
    }
    
    /**
     * Verificar si la imagen tiene transparencia
     */
    private function isTransparentImage($image) {
        $width = imagesx($image);
        $height = imagesy($image);
        
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $color = imagecolorat($image, $x, $y);
                $alpha = ($color >> 24) & 0x7F;
                if ($alpha > 0) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Optimizar imagen existente
     */
    public function optimizeImage($path) {
        $imageInfo = $this->getImageInfo($path);
        if (!$imageInfo) {
            return ['success' => false, 'error' => 'No se pudo leer la imagen'];
        }
        
        $image = $this->createImageFromFile($path, $imageInfo);
        if (!$image) {
            return ['success' => false, 'error' => 'No se pudo crear la imagen'];
        }
        
        $optimized = $this->saveImage($image, $path, $imageInfo['mime'], $this->quality);
        imagedestroy($image);
        
        return ['success' => $optimized];
    }
    
    /**
     * Convertir imagen a WebP
     */
    public function convertToWebP($sourcePath, $destinationPath) {
        $imageInfo = $this->getImageInfo($sourcePath);
        if (!$imageInfo) {
            return ['success' => false, 'error' => 'No se pudo leer la imagen'];
        }
        
        $image = $this->createImageFromFile($sourcePath, $imageInfo);
        if (!$image) {
            return ['success' => false, 'error' => 'No se pudo crear la imagen'];
        }
        
        $converted = imagewebp($image, $destinationPath, $this->quality);
        imagedestroy($image);
        
        return ['success' => $converted];
    }
}
?>
