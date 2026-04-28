<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

$user = getUser();
$empresa_id = $user['empresa_id'];
$cliente_id = $_GET['id'] ?? 0;

try {
    $stmt = getDB()->prepare("
        SELECT
            v.id,
            DATE_FORMAT(v.fecha, '%d/%m/%Y %H:%i') AS fecha,
            v.total,
            GROUP_CONCAT(CONCAT(COALESCE(p.nombre, 'ITEM'), ' x', CAST(vi.cantidad AS CHAR)) SEPARATOR ', ') AS productos
        FROM ventas v
        LEFT JOIN venta_items vi ON vi.venta_id = v.id
        LEFT JOIN productos p ON p.id = vi.producto_id
        WHERE v.empresa_id = ? AND v.cliente_id = ?
        GROUP BY v.id, v.fecha, v.total
        ORDER BY v.fecha DESC
        LIMIT 10
    ");
    $stmt->execute([$empresa_id, $cliente_id]);
    $historial = $stmt->fetchAll();
    echo json_encode($historial);
} catch (Exception $e) {
    echo json_encode([]);
}
