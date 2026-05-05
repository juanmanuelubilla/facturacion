<?php
require_once 'config.php';

echo "<h2>🔧 Creando Carpetas de Banners</h2>";

$user = getUser();
$empresa_id = $user['empresa_id'];

require_once 'lib/empresa_files.php';
$empresa_files = new EmpresaFiles($empresa_id);

// Obtener rutas absolutas
$banners_path = $empresa_files->getBannersPathAbsoluta(); // banners/proyectar/
$thumbnails_path = $empresa_files->getBannersThumbnailsPathAbsoluta(); // banners/thumbnails/
$desactivados_path = $empresa_files->getBannersDesactivadosPathAbsoluta(); // banners/desactivados/
$empresa_path = dirname($banners_path); // empresa_1/

echo "<h3>📁 Rutas a crear:</h3>";
echo "<ul>";
echo "<li>Empresa: $empresa_path</li>";
echo "<li>Banners (proyectar): $banners_path</li>";
echo "<li>Thumbnails: $thumbnails_path</li>";
echo "<li>Desactivados: $desactivados_path</li>";
echo "</ul>";

// Crear carpetas con permisos correctos
$carpetas = [
    $empresa_path,
    $banners_path,
    $thumbnails_path,
    $desactivados_path
];

foreach ($carpetas as $carpeta) {
    if (!is_dir($carpeta)) {
        echo "<p>📁 Creando carpeta: $carpeta</p>";
        if (mkdir($carpeta, 0755, true)) {
            echo "<p class='text-green-400'>✅ Carpeta creada exitosamente</p>";
            
            // Establecer permisos
            chmod($carpeta, 0755);
            echo "<p class='text-blue-400'>🔒 Permisos establecidos: 755</p>";
        } else {
            echo "<p class='text-red-400'>❌ Error al crear carpeta</p>";
        }
    } else {
        echo "<p class='text-yellow-400'>ℹ️ La carpeta ya existe: $carpeta</p>";
        
        // Verificar y corregir permisos si es necesario
        $perms = fileperms($carpeta);
        if (($perms & 0x1FF) != 0755) {
            chmod($carpeta, 0755);
            echo "<p class='text-blue-400'>🔒 Permisos corregidos: 755</p>";
        }
    }
}

// Verificar propietario
echo "<h3>👤 Verificando propietario:</h3>";
foreach ($carpetas as $carpeta) {
    if (is_dir($carpeta)) {
        $owner = fileowner($carpeta);
        $group = filegroup($carpeta);
        $perms = substr(sprintf('%o', fileperms($carpeta)), -4);
        echo "<p>$carpeta - Owner: $owner, Group: $group, Perms: $perms</p>";
    }
}

echo "<h3>✅ Proceso completado</h3>";
echo "<p><a href='banners.php'>🔙 Volver a Banners</a></p>";
echo "<p><a href='test_banner_simple.php'>🧪 Probar Test Banner</a></p>";
?>
