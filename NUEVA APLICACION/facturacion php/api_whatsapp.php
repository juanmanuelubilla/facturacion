<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

$user = getUser();
$empresa_id = $user['empresa_id'];

$accion = $_GET['accion'] ?? '';

if ($accion === 'enviar_campana') {
    $campaign_id = intval($_GET['campaign_id'] ?? 0);
    
    $campaign = fetch("SELECT * FROM whatsapp_campaigns WHERE id = ? AND empresa_id = ?", [$campaign_id, $empresa_id]);
    
    if (!$campaign) {
        echo json_encode(['success' => false, 'error' => 'Campaña no encontrada']);
        exit;
    }
    
    require_once 'lib/WhatsAppService.php';
    
    try {
        $whatsapp = new WhatsAppService($empresa_id);
        $clientes = $whatsapp->getClientesBySegmento($campaign['segmento']);
        
        query("UPDATE whatsapp_campaigns SET estado = 'enviando', total_clientes = ? WHERE id = ?", [count($clientes), $campaign_id]);
        
        $enviados = 0;
        $fallidos = 0;
        
        foreach ($clientes as $cliente) {
            if ($cliente['whatsapp']) {
                try {
                    $numero = $whatsapp->validarNumero($cliente['whatsapp']);
                    $mensaje = str_replace('{nombre}', $cliente['nombre'], $campaign['mensaje']);
                    
                    $result = $whatsapp->sendMessage($numero, $mensaje);
                    
                    query("INSERT INTO whatsapp_messages (campaign_id, cliente_id, telefono, mensaje, estado, mensaje_sid, fecha_envio) VALUES (?, ?, ?, ?, 'enviado', ?, NOW())",
                          [$campaign_id, $cliente['id'], $numero, $mensaje, $result['sid'] ?? null]);
                    
                    $enviados++;
                } catch (Exception $e) {
                    query("INSERT INTO whatsapp_messages (campaign_id, cliente_id, telefono, mensaje, estado, error_mensaje) VALUES (?, ?, ?, ?, 'fallido', ?)",
                          [$campaign_id, $cliente['id'], $cliente['whatsapp'], $campaign['mensaje'], $e->getMessage()]);
                    $fallidos++;
                }
            }
        }
        
        query("UPDATE whatsapp_campaigns SET estado = 'completado', mensajes_enviados = ?, mensajes_fallidos = ?, fecha_envio = NOW() WHERE id = ?",
              [$enviados, $fallidos, $campaign_id]);
        
        echo json_encode(['success' => true, 'enviados' => $enviados, 'fallidos' => $fallidos]);
        
    } catch (Exception $e) {
        query("UPDATE whatsapp_campaigns SET estado = 'fallido' WHERE id = ?", [$campaign_id]);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    
    exit;
}

if ($accion === 'eliminar_campana') {
    $campaign_id = intval($_GET['campaign_id'] ?? 0);
    
    query("DELETE FROM whatsapp_campaigns WHERE id = ? AND empresa_id = ?", [$campaign_id, $empresa_id]);
    
    echo json_encode(['success' => true]);
    exit;
}

if ($accion === 'eliminar_plantilla') {
    $template_id = intval($_GET['template_id'] ?? 0);
    
    query("DELETE FROM whatsapp_templates WHERE id = ? AND empresa_id = ?", [$template_id, $empresa_id]);
    
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Acción no válida']);
