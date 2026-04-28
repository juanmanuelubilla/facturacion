<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

$user = getUser();
$empresa_id = $user['empresa_id'];

$accion = $_GET['accion'] ?? '';

if ($accion === 'generar_codigo_auto') {
    require_once 'lib/PDFGenerator.php';
    
    try {
        $nuevo_codigo = PDFGenerator::generateAutoBarcode($empresa_id);
        echo json_encode(['success' => true, 'codigo' => $nuevo_codigo]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    
    exit;
}

if ($accion === 'generar_ean13') {
    require_once 'lib/PDFGenerator.php';
    
    $prefix = $_GET['prefix'] ?? '';
    
    try {
        $codigo = PDFGenerator::generateEAN13($prefix);
        echo json_encode(['success' => true, 'codigo' => $codigo]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    
    exit;
}

if ($accion === 'asignar_codigo') {
    $producto_id = intval($_POST['producto_id'] ?? 0);
    $codigo_barra = trim($_POST['codigo_barra'] ?? '');
    
    if ($producto_id && $codigo_barra) {
        query("UPDATE productos SET codigo_barra = ? WHERE id = ? AND empresa_id = ?", 
              [$codigo_barra, $producto_id, $empresa_id]);
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    }
    
    exit;
}

if ($accion === 'generar_etiqueta_pdf') {
    $producto_id = intval($_POST['producto_id'] ?? 0);
    $cantidad = intval($_POST['cantidad'] ?? 1);
    $tamano = $_POST['tamano'] ?? '50x30';
    $show_price = isset($_POST['show_price']) ? 1 : 0;
    $show_code = isset($_POST['show_code']) ? 1 : 0;
    $show_name = isset($_POST['show_name']) ? 1 : 0;
    
    require_once 'lib/PDFGenerator.php';
    
    try {
        $config = [
            'width' => (int)explode('x', $tamano)[0],
            'height' => (int)explode('x', $tamano)[1],
            'show_price' => $show_price,
            'show_code' => $show_code,
            'show_name' => $show_name
        ];
        
        $html = PDFGenerator::generateBatchLabels($producto_id, $cantidad, $config);
        
        $filename = 'etiquetas_' . date('YmdHis') . '.html';
        $filepath = '/tmp/' . $filename;
        file_put_contents($filepath, $html);
        
        header('Content-Type: text/html');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        readfile($filepath);
        unlink($filepath);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    
    exit;
}

echo json_encode(['success' => false, 'error' => 'Acción no válida']);
