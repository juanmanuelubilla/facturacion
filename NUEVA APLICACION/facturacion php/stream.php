<?php
require_once 'config.php';
session_start();

// Verificar que esté logueado
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Acceso denegado');
}

// Obtener empresa_id del usuario
$empresa_id = $_SESSION['empresa_id'] ?? 1;

// Obtener stream_key de la URL
$stream_key = $_GET['key'] ?? '';
if (empty($stream_key)) {
    http_response_code(400);
    exit('Stream key requerido');
}

// Validar que el stream pertenezca a esta empresa
if (!preg_match('/^stream_' . $empresa_id . '_/', $stream_key)) {
    http_response_code(403);
    exit('Acceso denegado');
}

// Construir ruta del archivo
$file_path = __DIR__ . '/streams/' . $stream_key . '/' . ($_GET['file'] ?? 'output.m3u8');

// Verificar que el archivo exista
if (!file_exists($file_path)) {
    http_response_code(404);
    exit('Stream no encontrado');
}

// Determinar el tipo de contenido
$extension = pathinfo($file_path, PATHINFO_EXTENSION);
$content_types = [
    'm3u8' => 'application/vnd.apple.mpegurl',
    'ts' => 'video/mp2t',
    'mp4' => 'video/mp4'
];

$content_type = $content_types[$extension] ?? 'application/octet-stream';

// Servir el archivo
header('Content-Type: ' . $content_type);
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if ($extension === 'm3u8') {
    // Para archivos M3U8, reemplazar URLs relativas con URLs absolutas
    $content = file_get_contents($file_path);
    $base_url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/facturacion/stream.php?key=' . $stream_key . '&file=';
    $content = preg_replace('/^([^#].*?)$/m', $base_url . '$1', $content);
    echo $content;
} else {
    // Para segmentos de video, servir directamente
    readfile($file_path);
}
?>
