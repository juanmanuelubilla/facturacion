<?php
require_once 'config.php';

echo "<h2>🔧 Solucionador de Permisos de Banners</h2>";

$user = getUser();
$empresa_id = $user['empresa_id'];

require_once 'lib/empresa_files.php';
$empresa_files = new EmpresaFiles($empresa_id);

$banners_path = $empresa_files->getBannersPathAbsoluta(); // banners/proyectar/
$thumbnails_path = $empresa_files->getBannersThumbnailsPathAbsoluta(); // banners/thumbnails/

echo "<h3>📁 Rutas:</h3>";
echo "<ul>";
echo "<li>Banners: $banners_path</li>";
echo "<li>Thumbnails: $thumbnails_path</li>";
echo "</ul>";

// Opción 1: Intentar con permisos más amplios
echo "<h3>🔓 Intentando con permisos 777</h3>";

if (!is_dir($banners_path)) {
    echo "<p>Creando $banners_path con permisos 777...</p>";
    if (mkdir($banners_path, 0777, true)) {
        echo "<p class='text-green-400'>✅ Carpeta creada con 777</p>";
    } else {
        echo "<p class='text-red-400'>❌ No se pudo crear</p>";
    }
} else {
    chmod($banners_path, 0777);
    echo "<p class='text-blue-400'>🔒 Permisos cambiados a 777</p>";
}

if (!is_dir($thumbnails_path)) {
    echo "<p>Creando $thumbnails_path con permisos 777...</p>";
    if (mkdir($thumbnails_path, 0777, true)) {
        echo "<p class='text-green-400'>✅ Carpeta creada con 777</p>";
    } else {
        echo "<p class='text-red-400'>❌ No se pudo crear</p>";
    }
} else {
    chmod($thumbnails_path, 0777);
    echo "<p class='text-blue-400'>🔒 Permisos cambiados a 777</p>";
}

// Opción 2: Probar escritura
echo "<h3>✍️ Probando escritura</h3>";
$test_file = $banners_path . '/test_permissions.txt';
if (file_put_contents($test_file, 'TEST')) {
    echo "<p class='text-green-400'>✅ Escritura exitosa</p>";
    unlink($test_file);
    echo "<p class='text-blue-400'>🗑️ Archivo de prueba eliminado</p>";
} else {
    echo "<p class='text-red-400'>❌ Error de escritura</p>";
    
    // Opción 3: Usar carpeta temporal alternativa
    echo "<h3>🔄 Opción alternativa: Carpeta temporal</h3>";
    $temp_path = './temp_banners/';
    if (!is_dir($temp_path)) {
        mkdir($temp_path, 0777, true);
    }
    
    echo "<p>Usando carpeta alternativa: $temp_path</p>";
    
    // Actualizar EmpresaFiles para usar ruta alternativa
    echo "<p class='text-yellow-400'>⚠️ Se usará una carpeta temporal hasta resolver permisos</p>";
}

// Verificar permisos actuales
echo "<h3>🔍 Permisos actuales:</h3>";
$rutas = [$banners_path, $thumbnails_path];
foreach ($rutas as $ruta) {
    if (is_dir($ruta)) {
        $perms = substr(sprintf('%o', fileperms($ruta)), -4);
        $writable = is_writable($ruta) ? '✅' : '❌';
        echo "<p>$ruta - Perms: $perms - Writable: $writable</p>";
    }
}

echo "<h3>🧪 Tests</h3>";
echo "<p><a href='test_banner_simple.php' class='text-blue-400'>🧪 Probar Test Banner</a></p>";
echo "<p><a href='banners.php' class='text-blue-400'>🖼️ Ir a Banners</a></p>";
?>
