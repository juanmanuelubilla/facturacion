<?php
/**
 * Gestión de archivos por empresa
 * Configura automáticamente la estructura de carpetas para cada empresa
 */

class EmpresaFiles {
    private $base_path;
    private $empresa_id;
    
    public function __construct($empresa_id, $base_path = null) {
        $this->empresa_id = $empresa_id;
        // Usar ruta absoluta del servidor web
        $this->base_path = $base_path ?: '/mnt/R2/SD64GB/www/facturacion/html/files/';
    }
    
    /**
     * Crear estructura de carpetas para una empresa
     */
    public function crearEstructura() {
        // Usar ruta absoluta para crear carpetas físicas
        $empresa_path_absoluta = $this->getEmpresaPathAbsoluta();
        
        // Crear carpeta principal de la empresa
        if (!file_exists($empresa_path_absoluta)) {
            mkdir($empresa_path_absoluta, 0755, true);
        }
        
        // Crear subcarpetas
        $subcarpetas = [
            'productos',
            'banners',
            'banners/proyectar',  // Carpeta DLNA para proyectar (activos)
            'banners/thumbnails',  // Miniaturas separadas del contenido DLNA
            'banners/expirados',  // Banners expirados por fecha (no DLNA)
            'banners/desactivados', // Banners desactivados manualmente (no DLNA)
            'avisos',  // Imágenes de avisos
            'ia',
            'tickets',
            'logos',
            'imagenes',
            'videos',
            'avatars',
            'clientes'
        ];
        
        foreach ($subcarpetas as $subcarpeta) {
            $path = $empresa_path_absoluta . $subcarpeta . '/';
            if (!file_exists($path)) {
                mkdir($path, 0755, true);
            }
        }
        
        return true;
    }
    
    /**
     * Obtener path base de la empresa (relativo para DB)
     */
    public function getEmpresaPath() {
        return $this->base_path . 'empresa_' . $this->empresa_id . '/';
    }
    
    /**
     * Obtener path absoluto de la empresa (para crear carpetas físicas)
     */
    public function getEmpresaPathAbsoluta() {
        return dirname(__DIR__) . '/' . $this->getEmpresaPath();
    }
    
    /**
     * Obtener todas las rutas configuradas para la empresa
     */
    public function getRutasConfiguradas() {
        $empresa_path = $this->getEmpresaPath();
        
        return [
            'ruta_tickets' => $empresa_path . 'tickets/',
            'ruta_imagenes' => $empresa_path . 'productos/',
            'ia_ruta_imagenes' => $empresa_path . 'ia/',
            'dlna_ruta_banners' => $empresa_path . 'banners/',
            'dlna_ruta_imagenes' => $empresa_path . 'imagenes/',
            'dlna_ruta_videos' => $empresa_path . 'videos/'
        ];
    }
    
    /**
     * Obtener ruta de banners (estructura plana, sin año/mes)
     */
    public function getBannersPath() {
        $empresa_path = $this->getEmpresaPath();
        return $empresa_path . 'banners/proyectar/';
    }
    
    /**
     * Obtener ruta absoluta de banners (estructura plana)
     */
    public function getBannersPathAbsoluta() {
        $empresa_path_absoluta = $this->getEmpresaPathAbsoluta();
        return $empresa_path_absoluta . 'banners/proyectar/';
    }
    
    /**
     * Obtener ruta de miniaturas de banners (separada del contenido DLNA)
     */
    public function getBannersThumbnailsPath() {
        $empresa_path = $this->getEmpresaPath();
        return $empresa_path . 'banners/thumbnails/';
    }
    
    /**
     * Obtener ruta absoluta de miniaturas de banners
     */
    public function getBannersThumbnailsPathAbsoluta() {
        $empresa_path_absoluta = $this->getEmpresaPathAbsoluta();
        return $empresa_path_absoluta . 'banners/thumbnails/';
    }
    
    /**
     * Obtener ruta de banners expirados (no DLNA)
     */
    public function getBannersExpiradosPath() {
        $empresa_path = $this->getEmpresaPath();
        return $empresa_path . 'banners/expirados/';
    }
    
    /**
     * Obtener ruta absoluta de banners expirados
     */
    public function getBannersExpiradosPathAbsoluta() {
        $empresa_path_absoluta = $this->getEmpresaPathAbsoluta();
        return $empresa_path_absoluta . 'banners/expirados/';
    }
    
    /**
     * Obtener ruta de banners desactivados (no DLNA)
     */
    public function getBannersDesactivadosPath() {
        $empresa_path = $this->getEmpresaPath();
        return $empresa_path . 'banners/desactivados/';
    }
    
    /**
     * Obtener ruta absoluta de banners desactivados
     */
    public function getBannersDesactivadosPathAbsoluta() {
        $empresa_path_absoluta = $this->getEmpresaPathAbsoluta();
        return $empresa_path_absoluta . 'banners/desactivados/';
    }
    
        
    /**
     * Obtener ruta de imágenes generadas por IA
     */
    public function getImagenesGeneradasPath() {
        $empresa_path = $this->getEmpresaPath();
        return $empresa_path . 'imagenes_generadas/';
    }
    
    /**
     * Obtener ruta absoluta de imágenes generadas por IA
     */
    public function getImagenesGeneradasPathAbsoluta() {
        $empresa_path_absoluta = $this->getEmpresaPathAbsoluta();
        return $empresa_path_absoluta . 'imagenes_generadas/';
    }
    
    /**
     * Configurar rutas en la base de datos para la empresa
     */
    public function configurarRutasEnDB($db) {
        $rutas = $this->getRutasConfiguradas();
        
        $stmt = $db->prepare("
            UPDATE nombre_negocio 
            SET ruta_tickets = ?,
                ruta_imagenes = ?,
                ia_ruta_imagenes = ?,
                dlna_ruta_banners = ?,
                dlna_ruta_imagenes = ?,
                dlna_ruta_videos = ?
            WHERE empresa_id = ?
        ");
        
        $stmt->execute([
            $rutas['ruta_tickets'],
            $rutas['ruta_imagenes'],
            $rutas['ia_ruta_imagenes'],
            $rutas['dlna_ruta_banners'],
            $rutas['dlna_ruta_imagenes'],
            $rutas['dlna_ruta_videos'],
            $this->empresa_id
        ]);
        
        return true;
    }
    
    /**
     * Migrar archivos desde rutas antiguas a la nueva estructura
     */
    public function migrarArchivos($db) {
        // Obtener rutas actuales
        $config = $db->prepare("SELECT ruta_imagenes, ruta_tickets FROM nombre_negocio WHERE empresa_id = ?");
        $config->execute([$this->empresa_id]);
        $rutas_actuales = $config->fetch();
        
        $rutas_nuevas = $this->getRutasConfiguradas();
        $rutas_nuevas_absolutas = $this->getRutasAbsolutas();
        
        // Migrar imágenes de productos
        if (!empty($rutas_actuales['ruta_imagenes']) && $rutas_actuales['ruta_imagenes'] !== $rutas_nuevas['ruta_imagenes']) {
            $origen = $this->convertirRutaRelativaAAbsoluta($rutas_actuales['ruta_imagenes']);
            $destino = $rutas_nuevas_absolutas['ruta_imagenes'];
            $this->migrarDirectorio($origen, $destino);
        }
        
        // Migrar tickets
        if (!empty($rutas_actuales['ruta_tickets']) && $rutas_actuales['ruta_tickets'] !== $rutas_nuevas['ruta_tickets']) {
            $origen = $this->convertirRutaRelativaAAbsoluta($rutas_actuales['ruta_tickets']);
            $destino = $rutas_nuevas_absolutas['ruta_tickets'];
            $this->migrarDirectorio($origen, $destino);
        }
        
        return true;
    }
    
    /**
     * Obtener rutas absolutas para operaciones de archivos
     */
    public function getRutasAbsolutas() {
        $empresa_path_absoluta = $this->getEmpresaPathAbsoluta();
        
        return [
            'ruta_tickets' => $empresa_path_absoluta . 'tickets/',
            'ruta_imagenes' => $empresa_path_absoluta . 'productos/',
            'ia_ruta_imagenes' => $empresa_path_absoluta . 'ia/',
            'dlna_ruta_banners' => $empresa_path_absoluta . 'banners/',
            'dlna_ruta_imagenes' => $empresa_path_absoluta . 'imagenes/',
            'dlna_ruta_videos' => $empresa_path_absoluta . 'videos/'
        ];
    }
    
    /**
     * Convertir ruta relativa a absoluta
     */
    private function convertirRutaRelativaAAbsoluta($ruta) {
        // Si ya es absoluta, retornarla
        if (strpos($ruta, '/') === 0) {
            return $ruta;
        }
        // Si es relativa, convertirla a absoluta
        return dirname(__DIR__) . '/' . $ruta;
    }
    
    /**
     * Migrar archivos de un directorio a otro
     */
    private function migrarDirectorio($origen, $destino) {
        if (!file_exists($origen)) {
            return false;
        }
        
        $archivos = glob($origen . '*');
        
        foreach ($archivos as $archivo) {
            if (is_file($archivo)) {
                $nombre_archivo = basename($archivo);
                copy($archivo, $destino . $nombre_archivo);
            }
        }
        
        return true;
    }
    
    /**
     * Obtener ruta de avisos (imágenes de avisos)
     */
    public function getAvisosPath() {
        $empresa_path = $this->getEmpresaPath();
        return $empresa_path . 'avisos/';
    }
    
    /**
     * Obtener ruta absoluta de avisos
     */
    public function getAvisosPathAbsoluta() {
        $empresa_path_absoluta = $this->getEmpresaPathAbsoluta();
        return $empresa_path_absoluta . 'avisos/';
    }
    
    /**
     * Obtener ruta de videos (grabaciones de cámaras)
     */
    public function getVideosPath() {
        $empresa_path = $this->getEmpresaPath();
        return $empresa_path . 'videos/';
    }
    
    /**
     * Obtener ruta absoluta de videos (grabaciones de cámaras)
     */
    public function getVideosPathAbsoluta() {
        $empresa_path_absoluta = $this->getEmpresaPathAbsoluta();
        return $empresa_path_absoluta . 'videos/';
    }
}
?>
