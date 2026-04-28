<?php
require_once 'config.php';
requireLogin();

$user = getUser();
$empresa_id = $user['empresa_id'];

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'guardar_config') {
        $api_key = trim($_POST['api_key'] ?? '');
        $api_secret = trim($_POST['api_secret'] ?? '');
        $phone_number = trim($_POST['phone_number'] ?? '');
        $sid = trim($_POST['sid'] ?? '');
        
        if ($api_key && $api_secret && $phone_number && $sid) {
            // Verificar si ya existe configuración
            $existente = fetch("SELECT id FROM whatsapp_config WHERE empresa_id = ?", [$empresa_id]);
            
            if ($existente) {
                query("UPDATE whatsapp_config SET api_key=?, api_secret=?, phone_number=?, sid=?, activo=1 WHERE empresa_id=?",
                      [$api_key, $api_secret, $phone_number, $sid, $empresa_id]);
            } else {
                query("INSERT INTO whatsapp_config (empresa_id, api_key, api_secret, phone_number, sid) VALUES (?, ?, ?, ?, ?)",
                      [$empresa_id, $api_key, $api_secret, $phone_number, $sid]);
            }
            
            header('Location: whatsapp.php');
            exit;
        }
    }
    
    if ($accion === 'guardar_campana') {
        $nombre = trim($_POST['nombre'] ?? '');
        $mensaje = trim($_POST['mensaje'] ?? '');
        $segmento = $_POST['segmento'] ?? 'todos';
        $fecha_programada = $_POST['fecha_programada'] ?? '';
        
        if ($nombre && $mensaje && $fecha_programada) {
            query("INSERT INTO whatsapp_campaigns (empresa_id, nombre, mensaje, segmento, fecha_programada, creado_por) VALUES (?, ?, ?, ?, ?, ?)",
                  [$empresa_id, $nombre, $mensaje, $segmento, $fecha_programada, $user['id']]);
            
            header('Location: whatsapp.php');
            exit;
        }
    }
    
    if ($accion === 'guardar_plantilla') {
        $nombre = trim($_POST['nombre'] ?? '');
        $asunto = trim($_POST['asunto'] ?? '');
        $mensaje = trim($_POST['mensaje'] ?? '');
        
        if ($nombre && $mensaje) {
            query("INSERT INTO whatsapp_templates (empresa_id, nombre, asunto, mensaje, creado_por) VALUES (?, ?, ?, ?, ?)",
                  [$empresa_id, $nombre, $asunto, $mensaje, $user['id']]);
            
            header('Location: whatsapp.php');
            exit;
        }
    }
}

// Obtener datos
$config = fetch("SELECT whatsapp_sid, whatsapp_api_key, whatsapp_phone FROM nombre_negocio WHERE empresa_id = ?", [$empresa_id]);
$campaigns = fetchAll("SELECT c.*, u.nombre as creador FROM whatsapp_campaigns c LEFT JOIN usuarios u ON c.creado_por = u.id WHERE c.empresa_id = ? ORDER BY c.fecha_creacion DESC", [$empresa_id]);
$templates = fetchAll("SELECT * FROM whatsapp_templates WHERE empresa_id = ? ORDER BY nombre", [$empresa_id]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>💬 WhatsApp Marketing - NEXUS POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-900 to-gray-800 text-gray-100">
    <header class="bg-gray-900 shadow-lg">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold text-green-400">💬 WHATSAPP MARKETING</h1>
            <a href="dashboard.php" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded">VOLVER</a>
        </div>
    </header>
    <main class="container mx-auto px-6 py-8">
        <!-- Tabs -->
        <div class="flex gap-2 mb-6">
            <button onclick="showTab('config')" id="tabConfig" class="px-6 py-3 rounded font-bold bg-green-600 text-white">⚙️ CONFIGURACIÓN</button>
            <button onclick="showTab('campanas')" id="tabCampanas" class="px-6 py-3 rounded font-bold bg-gray-700 text-white">📢 CAMPAÑAS</button>
            <button onclick="showTab('plantillas')" id="tabPlantillas" class="px-6 py-3 rounded font-bold bg-gray-700 text-white">📝 PLANTILLAS</button>
        </div>
        
        <!-- Tab Configuración -->
        <div id="panelConfig" class="bg-gray-800 rounded-lg border border-gray-700 p-6">
            <h3 class="text-xl font-bold text-white mb-4">📋 Estado de Configuración</h3>
            <div class="space-y-4">
                <div class="bg-gray-700 rounded p-4 border border-gray-600">
                    <h4 class="font-bold text-white mb-2">API Twilio</h4>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-gray-400">Account SID:</span>
                            <span class="<?= $config['whatsapp_sid'] ? 'text-green-400' : 'text-red-400' ?>">
                                <?= $config['whatsapp_sid'] ? '✅ Configurado' : '❌ No configurado' ?>
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">API Key:</span>
                            <span class="<?= $config['whatsapp_api_key'] ? 'text-green-400' : 'text-red-400' ?>">
                                <?= $config['whatsapp_api_key'] ? '✅ Configurado' : '❌ No configurado' ?>
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Número WhatsApp:</span>
                            <span class="<?= $config['whatsapp_phone'] ? 'text-green-400' : 'text-red-400' ?>">
                                <?= $config['whatsapp_phone'] ? '✅ ' . htmlspecialchars($config['whatsapp_phone']) : '❌ No configurado' ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="bg-blue-900 border border-blue-600 text-blue-100 px-4 py-3 rounded">
                    <p class="font-bold">💡 Configuración Centralizada</p>
                    <p class="text-sm">Para modificar la configuración de WhatsApp, ve a 
                    <a href="configurar.php?tab=whatsapp" class="text-blue-200 hover:text-white underline">Configuración → WhatsApp</a></p>
                </div>
            </div>
        </div>
        
        <!-- Tab Campañas -->
        <div id="panelCampanas" class="hidden bg-gray-800 rounded-lg border border-gray-700 p-6">
            <h3 class="text-xl font-bold text-white mb-4">Nueva Campaña</h3>
            <form method="POST">
                <input type="hidden" name="accion" value="guardar_campana">
                <div class="space-y-4">
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Nombre de Campaña</label>
                        <input type="text" name="nombre" required
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Segmento</label>
                        <select name="segmento" class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                            <option value="todos">Todos los clientes</option>
                            <option value="activos">Clientes activos (últimos 30 días)</option>
                            <option value="inactivos">Clientes inactivos (+90 días)</option>
                            <option value="premium">Clientes premium (compras > $100.000)</option>
                            <option value="con_whatsapp">Clientes con WhatsApp</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Fecha y Hora de Envío</label>
                        <input type="datetime-local" name="fecha_programada" required
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Mensaje</label>
                        <textarea name="mensaje" rows="4" required
                                  class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600"
                                  placeholder="Escribe tu mensaje aquí..."></textarea>
                    </div>
                    <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white py-3 rounded font-bold">
                        📨 GUARDAR CAMPAÑA
                    </button>
                </div>
            </form>
            
            <h3 class="text-xl font-bold text-white mt-8 mb-4">Campañas Programadas</h3>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-700">
                            <th class="text-left text-gray-400 py-2">Nombre</th>
                            <th class="text-left text-gray-400 py-2">Segmento</th>
                            <th class="text-left text-gray-400 py-2">Fecha Programada</th>
                            <th class="text-left text-gray-400 py-2">Estado</th>
                            <th class="text-left text-gray-400 py-2">Enviados</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($campaigns as $campaign): ?>
                        <tr class="border-b border-gray-700">
                            <td class="py-2"><?= htmlspecialchars($campaign['nombre']) ?></td>
                            <td class="py-2"><?= htmlspecialchars($campaign['segmento']) ?></td>
                            <td class="py-2"><?= date('d/m/Y H:i', strtotime($campaign['fecha_programada'])) ?></td>
                            <td class="py-2">
                                <span class="px-2 py-1 rounded text-xs font-bold
                                    <?= $campaign['estado'] === 'completado' ? 'bg-green-600' : 
                                       ($campaign['estado'] === 'pendiente' ? 'bg-yellow-600' : 'bg-red-600') ?>">
                                    <?= strtoupper($campaign['estado']) ?>
                                </span>
                            </td>
                            <td class="py-2"><?= $campaign['mensajes_enviados'] ?> / <?= $campaign['total_clientes'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Tab Plantillas -->
        <div id="panelPlantillas" class="hidden bg-gray-800 rounded-lg border border-gray-700 p-6">
            <h3 class="text-xl font-bold text-white mb-4">Nueva Plantilla</h3>
            <form method="POST">
                <input type="hidden" name="accion" value="guardar_plantilla">
                <div class="space-y-4">
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Nombre</label>
                        <input type="text" name="nombre" required
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Asunto (opcional)</label>
                        <input type="text" name="asunto"
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Mensaje</label>
                        <textarea name="mensaje" rows="4" required
                                  class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600"
                                  placeholder="Usa {nombre} para variables..."></textarea>
                    </div>
                    <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white py-3 rounded font-bold">
                        📨 GUARDAR PLANTILLA
                    </button>
                </div>
            </form>
            
            <h3 class="text-xl font-bold text-white mt-8 mb-4">Plantillas Disponibles</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($templates as $template): ?>
                <div class="bg-gray-700 p-4 rounded border border-gray-600">
                    <h4 class="font-bold text-white"><?= htmlspecialchars($template['nombre']) ?></h4>
                    <?php if ($template['asunto']): ?>
                    <p class="text-gray-400 text-sm mb-2"><?= htmlspecialchars($template['asunto']) ?></p>
                    <?php endif; ?>
                    <p class="text-gray-300 text-sm"><?= htmlspecialchars(substr($template['mensaje'], 0, 100)) ?>...</p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
    
    <script>
        function showTab(tab) {
            document.getElementById('panelConfig').classList.add('hidden');
            document.getElementById('panelCampanas').classList.add('hidden');
            document.getElementById('panelPlantillas').classList.add('hidden');
            
            document.getElementById('tabConfig').classList.remove('bg-green-600');
            document.getElementById('tabConfig').classList.add('bg-gray-700');
            document.getElementById('tabCampanas').classList.remove('bg-green-600');
            document.getElementById('tabCampanas').classList.add('bg-gray-700');
            document.getElementById('tabPlantillas').classList.remove('bg-green-600');
            document.getElementById('tabPlantillas').classList.add('bg-gray-700');
            
            document.getElementById('panel' + tab.charAt(0).toUpperCase() + tab.slice(1)).classList.remove('hidden');
            document.getElementById('tab' + tab.charAt(0).toUpperCase() + tab.slice(1)).classList.remove('bg-gray-700');
            document.getElementById('tab' + tab.charAt(0).toUpperCase() + tab.slice(1)).classList.add('bg-green-600');
        }
    </script>
</body>
</html>
