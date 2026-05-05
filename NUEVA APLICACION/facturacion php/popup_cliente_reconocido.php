<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

$user = getUser();
$empresa_id = $user['empresa_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$cliente_id = $_GET['cliente_id'] ?? null;
if (!$cliente_id) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de cliente requerido']);
    exit;
}

// Obtener datos del cliente
$cliente = fetch("SELECT id, nombre, apellido, documento, telefono, email, foto_cliente, foto_opcional, 
                         acepta_whatsapp, comentarios, condicion_iva
                  FROM clientes 
                  WHERE id = ? AND empresa_id = ?", [$cliente_id, $empresa_id]);

if (!$cliente) {
    http_response_code(404);
    echo json_encode(['error' => 'Cliente no encontrado']);
    exit;
}

// Obtener últimas compras del cliente
$ultimas_compras = fetchAll("
    SELECT v.id, v.total, v.fecha, v.estado, v.metodo_pago,
           COUNT(vi.id) as cantidad_items,
           GROUP_CONCAT(CONCAT(p.nombre, ' (', vi.cantidad, ')') SEPARATOR ', ') as productos
    FROM ventas v
    LEFT JOIN venta_items vi ON v.id = vi.venta_id
    LEFT JOIN productos p ON vi.producto_id = p.id
    WHERE v.cliente_id = ? AND v.empresa_id = ?
    GROUP BY v.id
    ORDER BY v.fecha DESC
    LIMIT 5
", [$cliente_id, $empresa_id]);

// Estadísticas del cliente
$stats = fetch("
    SELECT 
        COUNT(*) as total_compras,
        COALESCE(SUM(total), 0) as total_gastado,
        COALESCE(AVG(total), 0) as ticket_promedio,
        MAX(fecha) as ultima_compra
    FROM ventas 
    WHERE cliente_id = ? AND empresa_id = ?
", [$cliente_id, $empresa_id]);

// Formatear datos para respuesta
$cliente['nombre_completo'] = trim($cliente['nombre'] . ' ' . $cliente['apellido']);
$cliente['foto_principal'] = $cliente['foto_cliente'] ?: $cliente['foto_opcional'];

foreach ($ultimas_compras as &$compra) {
    $compra['fecha_formateada'] = date('d/m/Y H:i', strtotime($compra['fecha']));
    $compra['total_formateado'] = '$' . number_format($compra['total'], 2, ',', '.');
}

$stats_response = [
    'total_compras' => (int)$stats['total_compras'],
    'total_gastado' => '$' . number_format($stats['total_gastado'], 2, ',', '.'),
    'ticket_promedio' => '$' . number_format($stats['ticket_promedio'], 2, ',', '.'),
    'ultima_compra' => $stats['ultima_compra'] ? date('d/m/Y H:i', strtotime($stats['ultima_compra'])) : 'Sin compras'
];

echo json_encode([
    'success' => true,
    'cliente' => $cliente,
    'ultimas_compras' => $ultimas_compras,
    'estadisticas' => $stats_response,
    'timestamp' => date('Y-m-d H:i:s')
]);
?>