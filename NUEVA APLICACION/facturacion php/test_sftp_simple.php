<?php
// Script simple para probar SFTP
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

try {
    // Cargar configuración
    require_once 'config.php';
    
    // Probar SFTP
    require_once 'lib/SFTPService.php';
    $sftp_service = new SFTPService(1); // empresa_id = 1
    
    $resultado = $sftp_service->probarConexion();
    
    echo json_encode($resultado);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Exception: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
} catch (Error $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>
