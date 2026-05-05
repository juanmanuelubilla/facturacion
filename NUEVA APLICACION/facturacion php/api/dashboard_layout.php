<?php
require_once '../config.php';
requireLogin();
header('Content-Type: application/json');

$user = getUser();
$user_id = $user['id'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'load':
            // Cargar layout guardado del usuario
            $layout = fetchAll("
                SELECT module_key, module_order, is_visible 
                FROM user_dashboard_layout 
                WHERE user_id = ? 
                ORDER BY module_order
            ", [$user_id]);
            
            echo json_encode([
                'success' => true,
                'layout' => $layout
            ]);
            break;
            
        case 'save':
            // Guardar layout del usuario
            $input = json_decode(file_get_contents('php://input'), true);
            $layout = $input['layout'] ?? [];
            
            // Eliminar layout actual
            query("DELETE FROM user_dashboard_layout WHERE user_id = ?", [$user_id]);
            
            // Insertar nuevo layout
            foreach ($layout as $item) {
                query("
                    INSERT INTO user_dashboard_layout 
                    (user_id, module_key, module_order, is_visible) 
                    VALUES (?, ?, ?, ?)
                ", [
                    $user_id,
                    $item['module_key'],
                    $item['module_order'],
                    $item['is_visible'] ?? true
                ]);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Layout guardado correctamente'
            ]);
            break;
            
        case 'reset':
            // Restablecer layout por defecto
            query("DELETE FROM user_dashboard_layout WHERE user_id = ?", [$user_id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Layout restablecido por defecto'
            ]);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Acción no válida'
            ]);
            break;
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
