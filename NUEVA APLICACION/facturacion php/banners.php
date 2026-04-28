<?php
require_once 'config.php';
requireLogin();

$user = getUser();
$empresa_id = $user['empresa_id'];

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'guardar_banner') {
        $nombre = trim($_POST['nombre'] ?? '');
        $fecha_caducidad = $_POST['fecha_caducidad'] ?? null;
        $tiempo_visualizacion = intval($_POST['tiempo_visualizacion'] ?? 10);
        $activo = isset($_POST['activo']) ? 1 : 0;
        $ruta_imagen = '';
        
        // Procesar imagen desde imagenes.php o upload normal
        if (isset($_POST['imagen_desde_ia']) && !empty($_POST['imagen_desde_ia'])) {
            // Imagen viene desde imagenes.php (base64 o URL)
            require_once 'lib/ImageProcessor.php';
            require_once 'lib/empresa_files.php';
            
            $empresa_files = new EmpresaFiles($empresa_id);
            $upload_dir = $empresa_files->getBannersPathAbsoluta();
            
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
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
                    $result = $processor->processImage($filepath, $filepath, true);
                    
                    if ($result['success']) {
                        $ruta_imagen = $empresa_files->getBannersPath() . $filename;
                    } else {
                        $ruta_imagen = $empresa_files->getBannersPath() . $filename;
                    }
                }
            } else {
                // Es una URL, copiar la imagen
                $image_content = file_get_contents($imagen_data);
                if ($image_content) {
                    $processor = new ImageProcessor();
                    $filename = $processor->generateUniqueFilename('banner_ia_' . date('Y-m-d_H-i-s') . '.png');
                    $filepath = $upload_dir . $filename;
                    
                    if (file_put_contents($filepath, $image_content)) {
                        $result = $processor->processImage($filepath, $filepath, true);
                        
                        if ($result['success']) {
                            $ruta_imagen = $empresa_files->getBannersPath() . $filename;
                        } else {
                            $ruta_imagen = $empresa_files->getBannersPath() . $filename;
                        }
                    }
                }
            }
        } elseif (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            // Upload normal de archivo
            require_once 'lib/ImageProcessor.php';
            require_once 'lib/empresa_files.php';
            
            $empresa_files = new EmpresaFiles($empresa_id);
        }

        if (!empty($ruta_imagen)) {
            query("INSERT INTO banners (empresa_id, nombre, ruta_imagen, fecha_caducidad, tiempo_visualizacion, activo) 
                  VALUES (?, ?, ?, ?, ?, ?)",
                  [$empresa_id, $nombre, $ruta_imagen, $fecha_caducidad, $tiempo_visualizacion, $activo]);
            
            // Si viene de avisos.php, marcar el aviso como enviado
            if ($from_avisos && !empty($aviso_id)) {
                query("UPDATE avisos SET enviado_banner = 1 WHERE id = ? AND empresa_id = ?", [$aviso_id, $empresa_id]);
            }
        }
        header('Location: banners.php');
        exit;
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
        query("DELETE FROM banners WHERE id=? AND empresa_id=?", [$_POST['id'], $empresa_id]);
        header('Location: banners.php');
        exit;
    }
}

$banners = fetchAll("SELECT * FROM banners WHERE empresa_id = ? ORDER BY creado_en DESC", [$empresa_id]);
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
    } else {
        // Es una URL, usarla directamente
        $imagen_preview = $imagen_data;
    }
}

// Pre-llenar el nombre del banner según el origen
$nombre_banner_default = '';
if ($from_imagenes && $producto_nombre) {
    $nombre_banner_default = "Promo: $producto_nombre";
} elseif ($from_avisos && $aviso_titulo) {
    $nombre_banner_default = "Aviso: $aviso_titulo";
}

// Calcular estado de banners
$hoy = date('Y-m-d');
foreach ($banners as &$b) {
    if (!$b['activo']) {
        $b['estado'] = 'Inactivo';
        $b['estado_color'] = 'red';
    } elseif ($b['fecha_caducidad'] < $hoy) {
        $b['estado'] = 'Expirado';
        $b['estado_color'] = 'orange';
    } elseif ($b['fecha_caducidad'] == $hoy) {
        $b['estado'] = 'Expira hoy';
        $b['estado_color'] = 'yellow';
    } else {
        $b['estado'] = 'Activo';
        $b['estado_color'] = 'green';
    }
}
unset($b);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banners - NEXUS POS</title>
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
            <!-- Lista de Banners -->
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
                <h3 class="text-xl font-bold text-white mb-4">🖼️ Banners Activos</h3>
                <div class="space-y-3">
                    <?php if (empty($banners)): ?>
                        <p class="text-gray-400 text-center py-8">No hay banners configurados</p>
                    <?php else: ?>
                        <?php foreach ($banners as $b): ?>
                            <div class="bg-gray-700 rounded p-4 border border-gray-600">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <h4 class="font-bold text-white"><?= htmlspecialchars($b['nombre']) ?></h4>
                                        <p class="text-sm text-gray-400"><?= htmlspecialchars($b['descripcion'] ?? '') ?></p>
                                    </div>
                                    <span class="px-2 py-1 text-xs rounded <?= $b['estado_color'] ?>"><?= $b['estado'] ?></span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-xs text-gray-400">Creado: <?= date('d/m/Y H:i', strtotime($b['creado_en'])) ?></span>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="accion" value="eliminar_banner">
                                        <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                        <button type="submit" onclick="return confirm('¿Eliminar este banner?')" 
                                                class="text-red-400 hover:text-red-300 text-sm">🗑️ Eliminar</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-700">
                    <p class="text-sm text-gray-400">
                        💡 <strong>Configuración:</strong> Para modificar la configuración DLNA, ve a 
                        <a href="configurar.php?tab=dlna" class="text-purple-400 hover:text-purple-300 underline">Configuración → DLNA</a>
                    </p>
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
                <form method="POST" enctype="multipart/form-data">
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
                            <label class="block text-gray-400 text-sm mb-2">Fecha de Caducidad</label>
                            <input type="date" name="fecha_caducidad"
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Tiempo Visualización (seg)</label>
                            <input type="number" name="tiempo_visualizacion" value="10" min="1"
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" name="activo" checked class="w-4 h-4">
                            <label class="text-gray-400 text-sm">Activo</label>
                        </div>
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded font-bold">
                            � SUBIR BANNER
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Lista de Banners -->
        <div class="bg-gray-800 rounded-lg border border-gray-700 p-6 mt-6">
            <h3 class="text-xl font-bold text-white mb-4">Banners Activos</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($banners as $b): ?>
                <div class="bg-gray-700 rounded-lg p-4">
                    <?php if ($b['ruta_imagen']): ?>
                    <img src="<?= htmlspecialchars($b['ruta_imagen']) ?>" alt="<?= htmlspecialchars($b['nombre']) ?>"
                         class="w-full h-32 object-cover rounded mb-3"
                         onerror="this.style.display='none'">
                    <?php endif; ?>
                    <h4 class="text-white font-bold"><?= htmlspecialchars($b['nombre']) ?></h4>
                    <p class="text-gray-400 text-sm">Tiempo: <?= $b['tiempo_visualizacion'] ?>s</p>
                    <?php if ($b['fecha_caducidad']): ?>
                    <p class="text-gray-400 text-sm">Vence: <?= $b['fecha_caducidad'] ?></p>
                    <?php endif; ?>
                    <div class="flex justify-between items-center mt-3">
                        <span class="bg-<?= $b['estado_color'] ?>-600 px-2 py-1 rounded text-xs">
                            <?= $b['estado'] ?>
                        </span>
                        <button onclick="eliminarBanner(<?= $b['id'] ?>)" class="text-red-400 hover:text-red-300 text-sm">🗑️ Eliminar</button>
                    </div>
                </div>
                <?php endforeach; ?>
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
    </script>
</body>
</html>
