<?php
require_once 'config.php';

// Verificar autenticación
requireLogin();

// Obtener empresa del usuario logueado
$user = getUser();
$empresa_id = $user['empresa_id'] ?? 1; // fallback a 1 si no hay empresa_id

/**
 * Mover banner de activos a desactivados (lógica local)
 */
function moverBannerADesactivados($ruta_actual, $empresa_files) {
    $nombre_archivo = basename($ruta_actual);
    
    // RUTA CORRECTA: Desde proyectar/ (DLNA) hacia desactivados/
    $ruta_absoluta_actual = $empresa_files->getBannersPathAbsoluta() . $nombre_archivo;  // banners/proyectar/
    $ruta_absoluta_desactivados = $empresa_files->getBannersDesactivadosPathAbsoluta() . $nombre_archivo;  // banners/desactivados/
    
    // Asegurar que exista la carpeta de desactivados
    if (!is_dir($empresa_files->getBannersDesactivadosPathAbsoluta())) {
        mkdir($empresa_files->getBannersDesactivadosPathAbsoluta(), 0777, true);
    }
    
    // Debug
    error_log("DEBUG: MOVIENDO DE PROYECTAR A DESACTIVADOS");
    error_log("DEBUG: Ruta actual (proyectar): $ruta_absoluta_actual");
    error_log("DEBUG: Ruta desactivados: $ruta_absoluta_desactivados");
    error_log("DEBUG: Existe actual: " . (file_exists($ruta_absoluta_actual) ? 'SI' : 'NO'));
    error_log("DEBUG: Existe desactivados: " . (file_exists($ruta_absoluta_desactivados) ? 'SI' : 'NO'));
    
    // Mover archivo principal de proyectar/ a desactivados/
    if (file_exists($ruta_absoluta_actual) && !file_exists($ruta_absoluta_desactivados)) {
        if (rename($ruta_absoluta_actual, $ruta_absoluta_desactivados)) {
            error_log("DEBUG: ✅ Archivo movido de proyectar/ a desactivados/");
            error_log("DEBUG: ℹ️ Miniatura NO movida - se mantiene en thumbnails/");
            
            return $empresa_files->getBannersDesactivadosPath() . $nombre_archivo;
        } else {
            error_log("DEBUG: Error al mover archivo de proyectar/ a desactivados/");
        }
    } else {
        error_log("DEBUG: No se puede mover - archivo no existe o ya existe en destino");
    }
    
    return $ruta_actual; // Retorna ruta original si no pudo mover
}

/**
 * Mover banner de expirados a activos (lógica local)
 */
function moverBannerAActivos($ruta_actual, $empresa_files) {
    $nombre_archivo = basename($ruta_actual);
    
    // Rutas
    $ruta_absoluta_actual = $empresa_files->getEmpresaPathAbsoluta() . $ruta_actual;
    $ruta_absoluta_activos = $empresa_files->getBannersPathAbsoluta() . $nombre_archivo;
    
    // Asegurar que exista la carpeta de activos
    if (!is_dir($empresa_files->getBannersPathAbsoluta())) {
        mkdir($empresa_files->getBannersPathAbsoluta(), 0755, true);
    }
    
    // Mover archivo
    if (file_exists($ruta_absoluta_actual) && !file_exists($ruta_absoluta_activos)) {
        if (rename($ruta_absoluta_actual, $ruta_absoluta_activos)) {
            error_log("DEBUG: ✅ Archivo movido de desactivados/ a proyectar/");
            error_log("DEBUG: ℹ️ Miniatura NO movida - se mantiene en thumbnails/");
            
            return $empresa_files->getBannersPath() . $nombre_archivo;
        }
    }
    
    return $ruta_actual; // Retorna ruta original si no pudo mover
}

// Debug: Log básico al inicio
error_log("=== BANNERS.PHP ACCEDIDO ===");
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data: " . json_encode($_POST));

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'guardar_banner') {
        error_log("=== PROCESANDO GUARDAR BANNER ===");
        error_log("POST data: " . json_encode($_POST));
        error_log("FILES data: " . json_encode($_FILES));
        
        $nombre = trim($_POST['nombre'] ?? '');
        $fecha_caducidad = $_POST['fecha_caducidad'] ?? null;
        $activo = isset($_POST['activo']) ? 1 : 0;
        $ruta_imagen = '';
        
        error_log("Nombre: $nombre, Activo: $activo");
        
        // Procesar imagen desde imagenes.php o upload normal
        if (isset($_POST['imagen_desde_ia']) && !empty($_POST['imagen_desde_ia'])) {
            // Imagen viene desde imagenes.php (base64 o URL)
            require_once 'lib/ImageProcessor.php';
            require_once 'lib/empresa_files.php';
            
            $empresa_files = new EmpresaFiles($empresa_id);
            $upload_dir = $empresa_files->getBannersPathAbsoluta(); // banners/proyectar/
            $thumbnails_dir = $empresa_files->getBannersThumbnailsPathAbsoluta(); // banners/thumbnails/

            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            if (!is_dir($thumbnails_dir)) {
                mkdir($thumbnails_dir, 0755, true);
            }
            
            $imagen_data = $_POST['imagen_desde_ia'];
            
            if (strpos($imagen_data, 'data:') === 0) {
                // Es base64, procesar directamente
                $image_data = substr($imagen_data, strpos($imagen_data, ',') + 1);
                $image_data = base64_decode($image_data);
                
                $processor = new ImageProcessor();
                $filename = $processor->generateUniqueFilename('banner_ia_' . date('Y-m-d_H-i-s') . '.png');
                $filepath = $upload_dir . $filename;
                
                if (file_put_contents($filepath, $image_data)) {
                    // Generar miniatura en carpeta separada
                    $thumbnail_path = $thumbnails_dir . $filename;
                    $result = $processor->processImage($filepath, $thumbnail_path, true);
                    
                    if ($result['success']) {
                        $ruta_imagen = $empresa_files->getBannersPath() . $filename;
                    } else {
                        $ruta_imagen = $empresa_files->getBannersPath() . $filename;
                    }
                    
                    // Copiar vía SFTP al servidor remoto
                    require_once 'lib/SFTPService.php';
                    $sftp_service = new SFTPService($empresa_id);
                    $sftp_result = $sftp_service->copiarArchivo($filepath, $filename);
                    error_log("SFTP: " . ($sftp_result['success'] ? '✅' : '❌') . " " . $sftp_result['message']);
                }
            } else {
                // Puede ser una URL o una ruta local (desde avisos)
                $image_content = '';
                $extension_original = 'jpg'; // default
                
                if (filter_var($imagen_data, FILTER_VALIDATE_URL)) {
                    // Es una URL externa
                    $image_content = file_get_contents($imagen_data);
                    // Extraer extensión de la URL
                    $path_info = pathinfo(parse_url($imagen_data, PHP_URL_PATH));
                    $extension_original = strtolower($path_info['extension'] ?? 'jpg');
                } else {
                    // Es una ruta local, convertir a absoluta
                    if (strpos($imagen_data, '/') === 0) {
                        // Ya es ruta absoluta
                        $ruta_absoluta = $_SERVER['DOCUMENT_ROOT'] . $imagen_data;
                    } else {
                        // Es ruta relativa, convertir a absoluta
                        $ruta_absoluta = dirname(__DIR__) . '/' . $imagen_data;
                    }
                    
                    if (file_exists($ruta_absoluta)) {
                        $image_content = file_get_contents($ruta_absoluta);
                        error_log("Imagen local leída: $ruta_absoluta");
                        // Extraer extensión del archivo local
                        $extension_original = strtolower(pathinfo($ruta_absoluta, PATHINFO_EXTENSION) ?: 'jpg');
                    } else {
                        error_log("No se encontró imagen local: $ruta_absoluta");
                    }
                }
                
                if ($image_content) {
                    // Generar nombre único preservando extensión original
                    $filename = 'banner_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.' . $extension_original;
                    $filepath = $upload_dir . $filename;
                    
                    if (file_put_contents($filepath, $image_content)) {
                        $ruta_imagen = $empresa_files->getBannersPath() . $filename;
                        error_log("Banner guardado desde aviso/ia: $ruta_imagen");
                        
                        // Copiar vía SFTP al servidor remoto
                        require_once 'lib/SFTPService.php';
                        $sftp_service = new SFTPService($empresa_id);
                        $sftp_result = $sftp_service->copiarArchivo($filepath, $filename);
                        error_log("SFTP: " . ($sftp_result['success'] ? '✅' : '❌') . " " . $sftp_result['message']);
                    }
                }
            }
        } elseif (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            // Upload normal de archivo - SOLUCIÓN TEMPORAL SIMPLE
            error_log("=== PROCESANDO UPLOAD NORMAL ===");
            error_log("FILES imagen: " . json_encode($_FILES['imagen']));
            
            // Usar carpeta DLNA correcta con montaje CIFS corregido
            require_once 'lib/empresa_files.php';
            $empresa_files = new EmpresaFiles($empresa_id);
            $upload_dir = $empresa_files->getBannersPathAbsoluta(); // banners/proyectar/
            $thumbnails_dir = $empresa_files->getBannersThumbnailsPathAbsoluta(); // banners/thumbnails/

            error_log("Upload dir: $upload_dir");
            error_log("Thumbnails dir: $thumbnails_dir");

            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
                error_log("Creando upload dir temporal: $upload_dir");
            }
            if (!is_dir($thumbnails_dir)) {
                mkdir($thumbnails_dir, 0777, true);
                error_log("Creando thumbnails dir temporal: $thumbnails_dir");
            }
            
            // Generar nombre único preservando la extensión original
            $original_name = $_FILES['imagen']['name'];
            $extension = pathinfo($original_name, PATHINFO_EXTENSION);
            $extension = strtolower($extension) ?: 'png'; // fallback a png si no hay extensión
            $filename = 'banner_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.' . $extension;
            $filepath = $upload_dir . $filename;
            
            error_log("Filename: $filename");
            error_log("Filepath: $filepath");
            error_log("Temp name: " . $_FILES['imagen']['tmp_name']);
            
            if (move_uploaded_file($_FILES['imagen']['tmp_name'], $filepath)) {
                error_log("Archivo movido exitosamente");
                // Usar ruta relativa correcta para DLNA
                $ruta_imagen = $empresa_files->getBannersPath() . $filename;
                error_log("Banner guardado, ruta: $ruta_imagen");
                
                // Copiar vía SFTP al servidor remoto
                require_once 'lib/SFTPService.php';
                $sftp_service = new SFTPService($empresa_id);
                $sftp_result = $sftp_service->copiarArchivo($filepath, $filename);
                error_log("SFTP: " . ($sftp_result['success'] ? '✅' : '❌') . " " . $sftp_result['message']);
                
                // Intentar generar miniatura simple si existe GD
                if (extension_loaded('gd') && function_exists('imagecreatefromstring')) {
                    try {
                        $image_data = file_get_contents($filepath);
                        $image = imagecreatefromstring($image_data);
                        
                        if ($image) {
                            $width = imagesx($image);
                            $height = imagesy($image);
                            $new_width = 200;
                            $new_height = 200;
                            
                            $thumbnail = imagecreatetruecolor($new_width, $new_height);
                            imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
                            
                            $thumbnail_path = $thumbnails_dir . $filename;
                            imagepng($thumbnail, $thumbnail_path, 9);
                            
                            imagedestroy($image);
                            imagedestroy($thumbnail);
                            
                            error_log("Miniatura generada con GD");
                        }
                    } catch (Exception $e) {
                        error_log("Error generando miniatura: " . $e->getMessage());
                    }
                }
            } else {
                error_log("ERROR al mover archivo");
            }
        } else {
            error_log("=== NO SE DETECTÓ IMAGEN ===");
            error_log("FILES imagen error: " . ($_FILES['imagen']['error'] ?? 'no existe'));
        }

        error_log("Ruta imagen final: " . ($ruta_imagen ?? 'VACIA'));
        
        if (!empty($ruta_imagen)) {
            error_log("=== GUARDANDO EN BASE DE DATOS ===");
            error_log("Empresa ID: $empresa_id, Nombre: $nombre, Ruta: $ruta_imagen");
            
            query("INSERT INTO banners (empresa_id, nombre, ruta_imagen, fecha_caducidad, activo) 
                  VALUES (?, ?, ?, ?, ?)",
                  [$empresa_id, $nombre, $ruta_imagen, $fecha_caducidad, $activo]);
            
            error_log("Banner guardado en base de datos");
            
            error_log("Redirigiendo a banners.php");
            header('Location: banners.php');
            exit;
        } else {
            error_log("=== ERROR: NO HAY RUTA DE IMAGEN ===");
            error_log("No se pudo procesar la imagen, ruta_imagen está vacía");
        }
    }
    
    if ($accion === 'guardar_dlna') {
        $dlna_ip = trim($_POST['dlna_ip'] ?? '');
        $dlna_puerto = trim($_POST['dlna_puerto'] ?? '');
        $dlna_tipo_servidor = trim($_POST['dlna_tipo_servidor'] ?? 'local');
        $dlna_ruta_banners = trim($_POST['dlna_ruta_banners'] ?? '');
        $dlna_ruta_imagenes = trim($_POST['dlna_ruta_imagenes'] ?? '');
        $dlna_ruta_videos = trim($_POST['dlna_ruta_videos'] ?? '');

        // Guardar configuración DLNA en nombre_negocio
        query("UPDATE nombre_negocio SET dlna_ip=?, dlna_puerto=?, dlna_tipo_servidor=?, dlna_ruta_banners=?, dlna_ruta_imagenes=?, dlna_ruta_videos=? WHERE empresa_id=?",
              [$dlna_ip, $dlna_puerto, $dlna_tipo_servidor, $dlna_ruta_banners, $dlna_ruta_imagenes, $dlna_ruta_videos, $empresa_id]);
        header('Location: banners.php');
        exit;
    }
    
    if ($accion === 'eliminar_banner' && !empty($_POST['id'])) {
        $banner_id = $_POST['id'];
        
        // Obtener banner antes de eliminar para borrar archivos
        $banner = fetch("SELECT ruta_imagen FROM banners WHERE id=? AND empresa_id=?", [$banner_id, $empresa_id]);
        
        if ($banner && !empty($banner['ruta_imagen'])) {
            // Eliminar archivos físicos directamente en banners.php
            require_once 'lib/empresa_files.php';
            $empresa_files = new EmpresaFiles($empresa_id);
            
            $nombre_archivo = basename($banner['ruta_imagen']);
            error_log("DEBUG: 🔍 ELIMINANDO BANNER COMPLETO: {$banner['ruta_imagen']}");
            
            // Buscar y eliminar imagen principal en todas las posibles ubicaciones
            $possible_paths = [
                $empresa_files->getBannersPathAbsoluta() . $nombre_archivo,  // proyectar/
                $empresa_files->getBannersDesactivadosPathAbsoluta() . $nombre_archivo,  // desactivados/
                $empresa_files->getBannersExpiradosPathAbsoluta() . $nombre_archivo,  // expirados/
                $empresa_files->getEmpresaPathAbsoluta() . $banner['ruta_imagen']  // ruta original por si acaso
            ];
            
            $imagen_encontrada = false;
            foreach ($possible_paths as $ruta_absoluta) {
                if (file_exists($ruta_absoluta)) {
                    error_log("DEBUG: 📁 Encontrada imagen en: $ruta_absoluta");
                    if (unlink($ruta_absoluta)) {
                        error_log("DEBUG: ✅ Imagen eliminada exitosamente: $ruta_absoluta");
                        $imagen_encontrada = true;
                    } else {
                        error_log("DEBUG: ❌ Error al eliminar imagen: $ruta_absoluta");
                    }
                }
            }
            
            if (!$imagen_encontrada) {
                error_log("DEBUG: ⚠️ No se encontró la imagen principal en ninguna ubicación");
            }
            
            // Eliminar miniatura (solo en thumbnails/)
            $thumbnail_path = $empresa_files->getBannersThumbnailsPathAbsoluta() . $nombre_archivo;  // thumbnails/
            
            if (file_exists($thumbnail_path)) {
                error_log("DEBUG: 🖼️ Encontrada miniatura en: $thumbnail_path");
                if (unlink($thumbnail_path)) {
                    error_log("DEBUG: ✅ Miniatura eliminada: $thumbnail_path");
                    $miniaturas_encontradas = 1;
                } else {
                    error_log("DEBUG: ❌ Error al eliminar miniatura: $thumbnail_path");
                    $miniaturas_encontradas = 0;
                }
            } else {
                error_log("DEBUG: ⚠️ No se encontró miniatura en: $thumbnail_path");
                $miniaturas_encontradas = 0;
            }
            
            if ($miniaturas_encontradas === 0) {
                error_log("DEBUG: ⚠️ No se encontraron miniaturas");
            }
            
            error_log("DEBUG: 🏁 Eliminación completada - Imágenes: $imagen_encontrada, Miniaturas: $miniaturas_encontradas");
            
            // Eliminar archivo vía SFTP en servidor remoto
            require_once 'lib/SFTPService.php';
            $sftp_service = new SFTPService($empresa_id);
            $sftp_result = $sftp_service->eliminarArchivo($nombre_archivo);
            error_log("SFTP: " . ($sftp_result['success'] ? '✅' : '❌') . " " . $sftp_result['message']);
        }
        
        // Eliminar registro de la base de datos
        query("DELETE FROM banners WHERE id=? AND empresa_id=?", [$banner_id, $empresa_id]);
        
        header('Location: banners.php');
        exit;
    }
    
    if ($accion === 'reactivar_banner' && !empty($_POST['banner_id'])) {
        $banner_id = $_POST['banner_id'];
        $nueva_fecha_caducidad = !empty($_POST['nueva_fecha_caducidad']) ? $_POST['nueva_fecha_caducidad'] : null;
        $activo = isset($_POST['activo']) ? 1 : 0;
        
        // Obtener banner actual
        $banner_actual = fetch("SELECT ruta_imagen FROM banners WHERE id=? AND empresa_id=?", [$banner_id, $empresa_id]);
        
        if ($banner_actual) {
            require_once 'lib/empresa_files.php';
            $empresa_files = new EmpresaFiles($empresa_id);
            
            // Mover archivo de expirados a activos (lógica local)
            $nueva_ruta = moverBannerAActivos($banner_actual['ruta_imagen'], $empresa_files);
            
            // Actualizar banner
            query("UPDATE banners SET fecha_caducidad=?, activo=?, ruta_imagen=? WHERE id=? AND empresa_id=?", 
                  [$nueva_fecha_caducidad, $activo, $nueva_ruta, $banner_id, $empresa_id]);
        }
        
        header('Location: banners.php');
        exit;
    }
    
    if ($accion === 'desactivar_banner' && !empty($_POST['id'])) {
        $banner_id = $_POST['id'];
        
        // Obtener banner actual
        $banner_actual = fetch("SELECT ruta_imagen FROM banners WHERE id=? AND empresa_id=?", [$banner_id, $empresa_id]);
        
        if ($banner_actual) {
            require_once 'lib/empresa_files.php';
            $empresa_files = new EmpresaFiles($empresa_id);
            
            // Eliminar archivo del servidor SFTP
            $nombre_archivo = basename($banner_actual['ruta_imagen']);
            require_once 'lib/SFTPService.php';
            $sftp_service = new SFTPService($empresa_id);
            $sftp_result = $sftp_service->eliminarArchivo($nombre_archivo);
            error_log("SFTP: " . ($sftp_result['success'] ? '✅' : '❌') . " " . $sftp_result['message']);
            
            // Mover archivo de activos a desactivados (lógica local)
            $nueva_ruta = moverBannerADesactivados($banner_actual['ruta_imagen'], $empresa_files);
            
            // Actualizar banner a inactivo
            query("UPDATE banners SET activo=0, ruta_imagen=? WHERE id=? AND empresa_id=?",
                  [$nueva_ruta, $banner_id, $empresa_id]);
        }
        
        header('Location: banners.php');
        exit;
    }
}

// Procesar banners que expiraron hoy - mover archivos automáticamente
$hoy = date('Y-m-d');

// Mover banners expirados por fecha
$banners_expirados_hoy = fetchAll("SELECT * FROM banners WHERE empresa_id = ? AND activo = 1 AND fecha_caducidad < ? AND ruta_imagen NOT LIKE '%expirados%' AND ruta_imagen NOT LIKE '%desactivados%'", [$empresa_id, $hoy]);

if (!empty($banners_expirados_hoy)) {
    require_once 'lib/empresa_files.php';
    $empresa_files = new EmpresaFiles($empresa_id);
    
    foreach ($banners_expirados_hoy as $banner) {
        // Mover archivo de activos a expirados
        $nueva_ruta = $empresa_files->moverBannerAExpirados($banner['ruta_imagen']);
        
        // Actualizar ruta en base de datos
        query("UPDATE banners SET ruta_imagen=? WHERE id=?", [$nueva_ruta, $banner['id']]);
    }
}

// Mover banners desactivados manualmente
$banners_desactivados = fetchAll("SELECT * FROM banners WHERE empresa_id = ? AND activo = 0 AND ruta_imagen NOT LIKE '%desactivados%' AND ruta_imagen NOT LIKE '%expirados%'", [$empresa_id]);

if (!empty($banners_desactivados)) {
    require_once 'lib/empresa_files.php';
    $empresa_files = new EmpresaFiles($empresa_id);
    
    foreach ($banners_desactivados as $banner) {
        // Mover archivo de activos a desactivados
        $nueva_ruta = $empresa_files->moverBannerADesactivados($banner['ruta_imagen']);
        
        // Actualizar ruta en base de datos
        query("UPDATE banners SET ruta_imagen=? WHERE id=?", [$nueva_ruta, $banner['id']]);
    }
}

// Separar banners activos de inactivos/expirados
$banners_activos = fetchAll("SELECT * FROM banners WHERE empresa_id = ? AND activo = 1 AND (fecha_caducidad IS NULL OR fecha_caducidad >= ?) ORDER BY creado_en DESC", [$empresa_id, $hoy]);
$banners_inactivos = fetchAll("SELECT * FROM banners WHERE empresa_id = ? AND (activo = 0 OR fecha_caducidad < ?) ORDER BY creado_en DESC", [$empresa_id, $hoy]);
$config_dlna = fetch("SELECT dlna_ip_servidor as dlna_ip, dlna_puerto_servidor as dlna_puerto, dlna_tipo_servidor, dlna_ruta_banners, dlna_ruta_imagenes, dlna_ruta_videos FROM nombre_negocio WHERE empresa_id = ?", [$empresa_id]);

// Recibir datos desde imagenes.php o avisos.php
$from_imagenes = ($_GET['from'] ?? '') === 'imagenes';
$from_avisos = ($_GET['from'] ?? '') === 'avisos';
$producto_id = $_GET['producto_id'] ?? '';
$producto_nombre = $_GET['producto_nombre'] ?? '';
$prompt = $_GET['prompt'] ?? '';
$imagen_data = $_GET['imagen'] ?? '';

// Datos desde avisos.php
$aviso_id = $_GET['aviso_id'] ?? '';
$aviso_titulo = $_GET['aviso_titulo'] ?? '';
$aviso_descripcion = $_GET['aviso_descripcion'] ?? '';
$aviso_tipo = $_GET['aviso_tipo'] ?? '';

// Si viene de imagenes.php o avisos.php, procesar la imagen
$imagen_preview = '';
if (($from_imagenes || $from_avisos) && $imagen_data) {
    if (strpos($imagen_data, 'data:') === 0) {
        // Es una imagen en base64, procesarla directamente
        $imagen_preview = $imagen_data;
    } elseif (filter_var($imagen_data, FILTER_VALIDATE_URL)) {
        // Es una URL externa, usarla directamente
        $imagen_preview = $imagen_data;
    } else {
        // Es una ruta local (desde avisos), convertir a URL absoluta
        if (strpos($imagen_data, '/') === 0) {
            // Ya comienza con /, usar directamente
            $imagen_preview = $imagen_data;
        } else {
            // Es ruta relativa, agregar / al inicio
            $imagen_preview = '/' . $imagen_data;
        }
    }
}

// Pre-llenar el nombre del banner según el origen
$nombre_banner_default = '';
if ($from_imagenes && $producto_nombre) {
    $nombre_banner_default = "Promo: $producto_nombre";
} elseif ($from_avisos && $aviso_titulo) {
    $nombre_banner_default = "Aviso: $aviso_titulo";
}

// Calcular estado de banners activos
foreach ($banners_activos as &$b) {
    if ($b['fecha_caducidad'] == $hoy) {
        $b['estado'] = 'Expira hoy';
        $b['estado_color'] = 'yellow';
    } else {
        $b['estado'] = 'Activo';
        $b['estado_color'] = 'green';
    }
}
unset($b);

// Calcular estado de banners inactivos
foreach ($banners_inactivos as &$b) {
    if (!$b['activo']) {
        $b['estado'] = 'Inactivo';
        $b['estado_color'] = 'red';
    } elseif ($b['fecha_caducidad'] < $hoy) {
        $b['estado'] = 'Expirado';
        $b['estado_color'] = 'orange';
    } else {
        $b['estado'] = 'Desactivado';
        $b['estado_color'] = 'gray';
    }
}
unset($b);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banners - WARP POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-900 to-gray-800 text-gray-100">
    <header class="bg-gray-900 shadow-lg">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold text-purple-400">🖼️ BANNERS DLNA TV</h1>
            <a href="dashboard.php" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded">VOLVER</a>
        </div>
    </header>
    <main class="container mx-auto px-6 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Lista de Banners Activos -->
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
                <h3 class="text-xl font-bold text-white mb-4">🖼️ Banners Activos</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php if (empty($banners_activos)): ?>
                        <div class="col-span-2">
                            <p class="text-gray-400 text-center py-8">No hay banners activos</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($banners_activos as $b): ?>
                            <div class="bg-gray-700 rounded-lg p-4 border border-gray-600">
                                <!-- Imagen del banner -->
                                <?php if ($b['ruta_imagen']): ?>
                                <div class="mb-3">
                                    <img src="<?= htmlspecialchars($b['ruta_imagen']) ?>" alt="<?= htmlspecialchars($b['nombre']) ?>"
                                         class="w-full h-32 object-cover rounded"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                    <div class="w-full h-32 bg-gray-600 rounded flex items-center justify-center text-gray-400" style="display:none;">
                                        📦 Sin imagen
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="w-full h-32 bg-gray-600 rounded flex items-center justify-center text-gray-400 mb-3">
                                    📦 Sin imagen
                                </div>
                                <?php endif; ?>
                                
                                <!-- Información del banner -->
                                <div class="space-y-2">
                                    <h4 class="font-bold text-white"><?= htmlspecialchars($b['nombre']) ?></h4>
                                    
                                    <div class="flex justify-between items-center">
                                        <span class="px-2 py-1 text-xs rounded bg-<?= $b['estado_color'] ?>-600">
                                            <?= $b['estado'] ?>
                                        </span>
                                        <span class="text-xs text-gray-400">
                                            <?= date('d/m/Y', strtotime($b['creado_en'])) ?>
                                        </span>
                                    </div>
                                    
                                    <div class="text-xs text-gray-400 space-y-1">
                                        <?php if ($b['fecha_caducidad']): ?>
                                        <p>📅 Vence: <?= $b['fecha_caducidad'] ?></p>
                                        <?php endif; ?>
                                        <p>📁 Ruta: <?= htmlspecialchars($b['ruta_imagen']) ?></p>
                                    </div>
                                    
                                    <div class="flex justify-between items-center pt-2 border-t border-gray-600">
                                        <div class="flex items-center space-x-2">
                                            <span class="flex items-center text-xs">
                                                <span class="w-2 h-2 rounded-full mr-1 <?= $b['activo'] ? 'bg-green-500' : 'bg-red-500' ?>"></span>
                                                <?= $b['activo'] ? 'Activo' : 'Inactivo' ?>
                                            </span>
                                            <?php if ($b['activo']): ?>
                                            <button onclick="desactivarBanner(<?= $b['id'] ?>)" 
                                                    class="text-orange-400 hover:text-orange-300 text-xs">🔌 Desactivar</button>
                                            <?php endif; ?>
                                        </div>
                                        <button onclick="eliminarBanner(<?= $b['id'] ?>)" 
                                                class="text-red-400 hover:text-red-300 text-xs">🗑️ Eliminar</button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="mt-4 pt-4 border-t border-gray-700">
                    <div class="flex justify-between items-center">
                        <p class="text-sm text-gray-400">
                            📊 Activos: <?= count($banners_activos) ?> banners
                        </p>
                        <p class="text-sm text-gray-400">
                            💡 <strong>Configuración:</strong> 
                            <a href="configurar.php?tab=dlna" class="text-purple-400 hover:text-purple-300 underline">Configurar DLNA</a>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Nuevo Banner -->
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
                <h3 class="text-xl font-bold text-white mb-4">Nuevo Banner</h3>
                <?php if ($from_imagenes): ?>
                <div class="bg-blue-900 border border-blue-600 text-blue-100 px-4 py-3 rounded mb-4">
                    <p class="font-bold">📸 Enviado desde Generador de Imágenes</p>
                    <p class="text-sm">Producto: <?= htmlspecialchars($producto_nombre) ?></p>
                    <?php if ($prompt): ?>
                    <p class="text-sm text-gray-300 mt-1">Prompt: <?= htmlspecialchars(substr($prompt, 0, 100)) ?>...</p>
                    <?php endif; ?>
                </div>
                <?php elseif ($from_avisos): ?>
                <div class="bg-green-900 border border-green-600 text-green-100 px-4 py-3 rounded mb-4">
                    <p class="font-bold">📢 Enviado desde Avisos</p>
                    <p class="text-sm">Título: <?= htmlspecialchars($aviso_titulo) ?></p>
                    <p class="text-sm">Tipo: <?= htmlspecialchars($aviso_tipo) ?></p>
                    <?php if ($aviso_descripcion): ?>
                    <p class="text-sm text-gray-300 mt-1">Descripción: <?= htmlspecialchars(substr($aviso_descripcion, 0, 100)) ?>...</p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <form method="POST" enctype="multipart/form-data" onsubmit="console.log('Formulario enviado'); return true;">
                    <input type="hidden" name="accion" value="guardar_banner">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Nombre</label>
                            <input type="text" name="nombre" required
                                   value="<?= htmlspecialchars($nombre_banner_default) ?>"
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Imagen del Banner</label>
                            <?php if (($from_imagenes || $from_avisos) && $imagen_preview): ?>
                                <!-- Vista previa de imagen desde imagenes.php o avisos.php -->
                                <div class="mb-3 p-3 bg-gray-700 rounded border border-gray-600">
                                    <img src="<?= htmlspecialchars($imagen_preview) ?>" alt="Vista previa" class="max-w-full max-h-32 object-contain">
                                    <?php if ($from_imagenes): ?>
                                        <p class="text-xs text-gray-400 mt-2">Imagen desde IA: <?= htmlspecialchars($producto_nombre) ?></p>
                                    <?php elseif ($from_avisos): ?>
                                        <p class="text-xs text-gray-400 mt-2">Imagen desde aviso: <?= htmlspecialchars($aviso_titulo) ?></p>
                                    <?php endif; ?>
                                </div>
                                <input type="hidden" name="imagen_desde_ia" value="<?= htmlspecialchars($imagen_preview) ?>">
                            <?php else: ?>
                                <input type="file" name="imagen" accept="image/*" required
                                       class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                            <?php endif; ?>
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Fecha de Caducidad <span class="text-red-400">*</span></label>
                            <input type="date" name="fecha_caducidad" required
                                   min="<?= date('Y-m-d') ?>"
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                            <p class="text-xs text-gray-400 mt-1">La fecha de caducidad es obligatoria</p>
                        </div>
                                                <div class="flex items-center gap-2">
                            <input type="checkbox" name="activo" checked class="w-4 h-4">
                            <label class="text-gray-400 text-sm">Activo</label>
                        </div>
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded font-bold">
                            📤 SUBIR BANNER
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Lista de Banners Inactivos/Expirados -->
        <div class="bg-gray-800 rounded-lg border border-gray-700 p-6 mt-6">
            <h3 class="text-xl font-bold text-white mb-4">🚫 Banners Inactivos/Expirados</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php if (empty($banners_inactivos)): ?>
                    <div class="col-span-full">
                        <p class="text-gray-400 text-center py-8">No hay banners inactivos o expirados</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($banners_inactivos as $b): ?>
                        <div class="bg-gray-700 rounded-lg p-4 border border-gray-600">
                            <!-- Imagen del banner -->
                            <?php if ($b['ruta_imagen']): ?>
                            <div class="mb-3 opacity-50">
                                <img src="<?= htmlspecialchars($b['ruta_imagen']) ?>" alt="<?= htmlspecialchars($b['nombre']) ?>"
                                     class="w-full h-24 object-cover rounded"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                <div class="w-full h-24 bg-gray-600 rounded flex items-center justify-center text-gray-400" style="display:none;">
                                    📦 Sin imagen
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="w-full h-24 bg-gray-600 rounded flex items-center justify-center text-gray-400 mb-3 opacity-50">
                                📦 Sin imagen
                            </div>
                            <?php endif; ?>
                            
                            <!-- Información del banner -->
                            <div class="space-y-2">
                                <h4 class="font-bold text-white text-sm"><?= htmlspecialchars($b['nombre']) ?></h4>
                                
                                <div class="flex justify-between items-center">
                                    <span class="px-2 py-1 text-xs rounded bg-<?= $b['estado_color'] ?>-600">
                                        <?= $b['estado'] ?>
                                    </span>
                                    <span class="text-xs text-gray-400">
                                        <?= date('d/m/Y', strtotime($b['creado_en'])) ?>
                                    </span>
                                </div>
                                
                                <div class="text-xs text-gray-400 space-y-1">
                                    <?php if ($b['fecha_caducidad']): ?>
                                    <p>📅 Venció: <?= $b['fecha_caducidad'] ?></p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="flex gap-2 pt-2">
                                    <button onclick="reactivarBanner(<?= $b['id'] ?>)" 
                                            class="flex-1 bg-green-600 hover:bg-green-700 text-white px-2 py-1 rounded text-xs">
                                        🔄 Reactivar
                                    </button>
                                    <button onclick="eliminarBanner(<?= $b['id'] ?>)" 
                                            class="flex-1 bg-red-600 hover:bg-red-700 text-white px-2 py-1 rounded text-xs">
                                        🗑️ Eliminar
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="mt-4 pt-4 border-t border-gray-700">
                <div class="flex justify-between items-center">
                    <p class="text-sm text-gray-400">
                        📊 Inactivos: <?= count($banners_inactivos) ?> banners
                    </p>
                    <p class="text-sm text-gray-400">
                        💡 <strong>Tip:</strong> Los banners expirados pueden ser reactivados con nueva fecha
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Modal para Reactivar Banner -->
        <div id="modalReactivar" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
            <div class="bg-gray-800 rounded-lg p-6 w-full max-w-md">
                <h3 class="text-xl font-bold text-white mb-4">🔄 Reactivar Banner</h3>
                <form id="formReactivar" method="POST">
                    <input type="hidden" name="accion" value="reactivar_banner">
                    <input type="hidden" id="reactivar_banner_id" name="banner_id">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Nombre del Banner</label>
                            <input type="text" id="reactivar_nombre" readonly
                                   class="w-full bg-gray-600 text-gray-300 p-3 rounded border border-gray-500">
                        </div>
                        
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Nueva Fecha de Caducidad</label>
                            <input type="date" name="nueva_fecha_caducidad" required
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                            <p class="text-xs text-gray-400 mt-1">Deja vacío si no quieres fecha de caducidad</p>
                        </div>
                        
                                                
                        <div class="flex items-center gap-2">
                            <input type="checkbox" name="activo" checked class="w-4 h-4">
                            <label class="text-gray-400 text-sm">Activar banner</label>
                        </div>
                    </div>
                    
                    <div class="flex gap-3 mt-6">
                        <button type="submit" class="flex-1 bg-green-600 hover:bg-green-700 text-white py-3 rounded font-bold">
                            ✅ Reactivar Banner
                        </button>
                        <button type="button" onclick="cerrarModalReactivar()" 
                                class="flex-1 bg-gray-600 hover:bg-gray-700 text-white py-3 rounded font-bold">
                            ❌ Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
    </main>

    <script>
        function eliminarBanner(id) {
            if (confirm('¿Eliminar este banner?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="accion" value="eliminar_banner"><input type="hidden" name="id" value="' + id + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function desactivarBanner(id) {
            if (confirm('¿Desactivar este banner? Se moverá la imagen de la carpeta DLNA.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="accion" value="desactivar_banner"><input type="hidden" name="id" value="' + id + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function reactivarBanner(id) {
            // Obtener datos del banner desde el DOM
            const bannerElement = event.target.closest('.bg-gray-700');
            const nombre = bannerElement.querySelector('h4').textContent;
            
            // Llenar el formulario del modal
            document.getElementById('reactivar_banner_id').value = id;
            document.getElementById('reactivar_nombre').value = nombre;
            
            // Establecer fecha mínima como hoy
            const hoy = new Date().toISOString().split('T')[0];
            document.querySelector('input[name="nueva_fecha_caducidad"]').min = hoy;
            document.querySelector('input[name="nueva_fecha_caducidad"]').value = hoy;
            
            // Mostrar modal
            document.getElementById('modalReactivar').classList.remove('hidden');
        }
        
        function cerrarModalReactivar() {
            document.getElementById('modalReactivar').classList.add('hidden');
        }
        
        // Cerrar modal al hacer clic fuera
        document.getElementById('modalReactivar').addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarModalReactivar();
            }
        });
    </script>
</body>
</html>
