<?php
// Versión simplificada para diagnóstico
file_put_contents('/tmp/api_venta_simple.log', date('Y-m-d H:i:s') . " - Inicio\n", FILE_APPEND);

header('Content-Type: application/json');
file_put_contents('/tmp/api_venta_simple.log', date('Y-m-d H:i:s') . " - Después de header\n", FILE_APPEND);

echo json_encode(['success' => true, 'test' => 'API funciona', 'time' => date('Y-m-d H:i:s')]);
file_put_contents('/tmp/api_venta_simple.log', date('Y-m-d H:i:s') . " - Después de echo\n", FILE_APPEND);
