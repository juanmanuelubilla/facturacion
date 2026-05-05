<?php
require_once 'config.php';
requireLogin();

$user = getUser();
$empresa_id = $user['empresa_id'];

error_log("=== TEST BANNER SIMPLE ACCEDIDO ===");
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data: " . json_encode($_POST));
error_log("FILES data: " . json_encode($_FILES));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'guardar_banner') {
    error_log("=== PROCESANDO GUARDAR BANNER SIMPLE ===");
    
    $nombre = trim($_POST['nombre'] ?? '');
    $ruta_imagen = '';
    
    error_log("Nombre recibido: $nombre");
    
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        error_log("=== PROCESANDO UPLOAD ===");
        
        // Usar carpeta temporal local mientras resolvemos permisos
        $upload_dir = './temp_banners/';
        
        error_log("Upload dir: $upload_dir");
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
            error_log("Creando upload dir temporal");
        }
        
        $filename = 'test_banner_' . date('Y-m-d_H-i-s') . '.png';
        $filepath = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['imagen']['tmp_name'], $filepath)) {
            error_log("Archivo movido exitosamente a: $filepath");
            $ruta_imagen = $upload_dir . $filename;
            error_log("Ruta relativa: $ruta_imagen");
        } else {
            error_log("ERROR al mover archivo");
        }
    } else {
        error_log("ERROR: No hay imagen o hay error de upload");
        error_log("FILES imagen: " . json_encode($_FILES['imagen'] ?? 'no existe'));
    }
    
    if (!empty($ruta_imagen)) {
        error_log("=== GUARDANDO EN BASE DE DATOS ===");
        query("INSERT INTO banners (empresa_id, nombre, ruta_imagen, activo) VALUES (?, ?, ?, 1)", 
              [$empresa_id, $nombre, $ruta_imagen]);
        error_log("Banner guardado exitosamente");
        
        header('Location: banners.php');
        exit;
    } else {
        error_log("ERROR: No hay ruta de imagen");
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Test Banner Simple</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white p-8">
    <h1 class="text-2xl font-bold mb-6">🧪 Test Banner Simple</h1>
    
    <form method="POST" enctype="multipart/form-data" class="space-y-4 max-w-md">
        <input type="hidden" name="accion" value="guardar_banner">
        
        <div>
            <label class="block text-gray-400 text-sm mb-2">Nombre</label>
            <input type="text" name="nombre" required 
                   class="w-full bg-gray-700 text-white p-3 rounded">
        </div>
        
        <div>
            <label class="block text-gray-400 text-sm mb-2">Imagen</label>
            <input type="file" name="imagen" accept="image/*" required
                   class="w-full bg-gray-700 text-white p-3 rounded">
        </div>
        
        <button type="submit" 
                class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded font-bold">
            📤 SUBIR BANNER TEST
        </button>
    </form>
    
    <div class="mt-6">
        <a href="banners.php" class="text-blue-400 hover:text-blue-300">← Volver a Banners</a>
    </div>
    
    <script>
        document.querySelector('form').addEventListener('submit', function(e) {
            console.log('Formulario submit detectado');
            console.log('FormData:', new FormData(this));
        });
    </script>
</body>
</html>
