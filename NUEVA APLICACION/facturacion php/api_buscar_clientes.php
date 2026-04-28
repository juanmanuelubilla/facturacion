<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

$user = getUser();
$empresa_id = $user['empresa_id'];
$termino = $_GET['termino'] ?? '';

if (strlen($termino) < 2) {
    echo json_encode([]);
    exit;
}

try {
    $clientes = fetchAll("
        SELECT id, nombre, apellido, documento 
        FROM clientes 
        WHERE empresa_id = ? 
        AND (nombre LIKE ? OR apellido LIKE ? OR documento LIKE ?) 
        ORDER BY nombre, apellido 
        LIMIT 10
    ", [$empresa_id, "%$termino%", "%$termino%", "%$termino%"]);
    
    echo json_encode($clientes);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
