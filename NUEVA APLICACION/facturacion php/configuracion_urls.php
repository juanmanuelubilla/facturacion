<?php
require_once 'config.php';
requireLogin();

$user = getUser();
if ($user['rol'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$db = getDB();
$url_manager = new URLManager($db);
$config = $url_manager->getConfig();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'actualizar_config') {
        $modo = $_POST['modo'] ?? 'single';
        $dominio_base = trim($_POST['dominio_base'] ?? 'nexuspos.com');
        $permitir_urls_personalizadas = isset($_POST['permitir_urls_personalizadas']);
        $url_personalizada_obligatoria = isset($_POST['url_personalizada_obligatoria']);
        
        $nueva_config = [
            'modo' => $modo,
            'dominio_base' => $dominio_base,
            'permitir_urls_personalizadas' => $permitir_urls_personalizadas,
            'url_personalizada_obligatoria' => $url_personalizada_obligatoria
        ];
        
        if ($url_manager->actualizarConfig($nueva_config)) {
            $success = "✅ Configuración actualizada correctamente";
            $config = $url_manager->getConfig();
        } else {
            $error = "❌ Error al actualizar la configuración";
        }
    }
    
    if ($accion === 'actualizar_url_empresa') {
        $empresa_id = intval($_POST['empresa_id'] ?? 0);
        $url_personalizada = trim($_POST['url_personalizada'] ?? '');
        
        if ($empresa_id > 0) {
            if (empty($url_personalizada)) {
                // Eliminar URL personalizada
                $stmt = $db->prepare("UPDATE empresas SET url_personalizada = NULL WHERE id = ?");
                if ($stmt->execute([$empresa_id])) {
                    $success = "✅ URL personalizada eliminada";
                } else {
                    $error = "❌ Error al eliminar URL personalizada";
                }
            } else {
                // Validar y guardar URL personalizada
                if (!$url_manager->validarURLPersonalizada($url_personalizada)) {
                    $error = "❌ URL inválida. Use solo letras, números y guiones (3-30 caracteres)";
                } elseif (!$url_manager->urlDisponible($url_personalizada, $empresa_id)) {
                    $error = "❌ URL no disponible. Ya está en uso por otra empresa";
                } else {
                    $stmt = $db->prepare("UPDATE empresas SET url_personalizada = ? WHERE id = ?");
                    if ($stmt->execute([$url_personalizada, $empresa_id])) {
                        $success = "✅ URL personalizada actualizada";
                    } else {
                        $error = "❌ Error al actualizar URL personalizada";
                    }
                }
            }
        }
    }
}

// Obtener empresas con sus URLs personalizadas
$empresas = fetchAll("SELECT id, nombre, url_personalizada FROM empresas WHERE activo = 1 ORDER BY nombre");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración de URLs - WARP POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-900 to-gray-800 text-gray-100">
    <header class="bg-gray-900 shadow-lg">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold text-blue-400">🌐 CONFIGURACIÓN DE URLs</h1>
            <a href="dashboard.php" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded">VOLVER</a>
        </div>
    </header>
    
    <main class="container mx-auto px-6 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <!-- Configuración del Sistema -->
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
                <h2 class="text-xl font-bold text-white mb-4">⚙️ Configuración del Sistema</h2>
                
                <?php if ($error): ?>
                    <div class="bg-red-500 text-white p-3 rounded mb-4"><?= $error ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="bg-green-500 text-white p-3 rounded mb-4"><?= $success ?></div>
                <?php endif; ?>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="accion" value="actualizar_config">
                    
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Modo del Sistema</label>
                        <select name="modo" class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                            <option value="single" <?= $config['modo'] === 'single' ? 'selected' : '' ?>>🏢 Single Empresa</option>
                            <option value="multi_subdominio" <?= $config['modo'] === 'multi_subdominio' ? 'selected' : '' ?>>🌐 Multiempresa (Subdominios)</option>
                            <option value="multi_prefijo" <?= $config['modo'] === 'multi_prefijo' ? 'selected' : '' ?>>📂 Multiempresa (Prefijos)</option>
                            <option value="multi_parametro" <?= $config['modo'] === 'multi_parametro' ? 'selected' : '' ?>>🔗 Multiempresa (Parámetros)</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Dominio Base</label>
                        <input type="text" name="dominio_base" value="<?= htmlspecialchars($config['dominio_base']) ?>"
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600"
                               placeholder="nexuspos.com">
                    </div>
                    
                    <div class="space-y-2">
                        <label class="flex items-center">
                            <input type="checkbox" name="permitir_urls_personalizadas" 
                                   <?= $config['permitir_urls_personalizadas'] ? 'checked' : '' ?>
                                   class="mr-2">
                            <span class="text-gray-300">Permitir URLs personalizadas por empresa</span>
                        </label>
                        
                        <label class="flex items-center">
                            <input type="checkbox" name="url_personalizada_obligatoria" 
                                   <?= $config['url_personalizada_obligatoria'] ? 'checked' : '' ?>
                                   class="mr-2">
                            <span class="text-gray-300">URL personalizada obligatoria (modo multiempresa)</span>
                        </label>
                    </div>
                    
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded">
                        💾 ACTUALIZAR CONFIGURACIÓN
                    </button>
                </form>
                
                <!-- Ejemplos de URLs -->
                <div class="mt-6 bg-gray-700 p-4 rounded">
                    <h3 class="text-white font-bold mb-2">📋 Ejemplos de URLs</h3>
                    <div class="text-sm text-gray-300 space-y-1">
                        <div><strong>Single:</strong> <?= $config['dominio_base'] ?>/dashboard.php</div>
                        <div><strong>Subdominio:</strong> miempresa.<?= $config['dominio_base'] ?>/dashboard.php</div>
                        <div><strong>Prefijo:</strong> <?= $config['dominio_base'] ?>/miempresa/dashboard.php</div>
                        <div><strong>Parámetro:</strong> <?= $config['dominio_base'] ?>/dashboard.php?empresa=miempresa</div>
                    </div>
                </div>
            </div>
            
            <!-- URLs Personalizadas por Empresa -->
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
                <h2 class="text-xl font-bold text-white mb-4">🏷️ URLs Personalizadas por Empresa</h2>
                
                <div class="space-y-3 max-h-96 overflow-y-auto">
                    <?php foreach ($empresas as $empresa): ?>
                        <form method="POST" class="bg-gray-700 p-3 rounded">
                            <input type="hidden" name="accion" value="actualizar_url_empresa">
                            <input type="hidden" name="empresa_id" value="<?= $empresa['id'] ?>">
                            
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="text-white font-bold"><?= htmlspecialchars($empresa['nombre']) ?></div>
                                    <div class="text-sm text-gray-400">
                                        URL: 
                                        <?php if ($empresa['url_personalizada']): ?>
                                            <span class="text-green-400"><?= htmlspecialchars($empresa['url_personalizada']) ?></span>
                                        <?php else: ?>
                                            <span class="text-gray-500">Sin configurar</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="flex items-center space-x-2">
                                    <input type="text" name="url_personalizada" 
                                           value="<?= htmlspecialchars($empresa['url_personalizada'] ?? '') ?>"
                                           placeholder="mi-empresa"
                                           class="bg-gray-600 text-white px-2 py-1 rounded text-sm w-32">
                                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm">
                                        💾
                                    </button>
                                </div>
                            </div>
                        </form>
                    <?php endforeach; ?>
                </div>
                
                <div class="mt-4 bg-gray-700 p-3 rounded">
                    <h3 class="text-white font-bold mb-2">ℹ️ Información</h3>
                    <ul class="text-sm text-gray-300 space-y-1">
                        <li>• Las URLs personalizadas solo aceptan letras, números y guiones</li>
                        <li>• Longitud: 3 a 30 caracteres</li>
                        <li>• No pueden repetirse entre empresas</li>
                        <li>• Dejar vacío para eliminar la URL personalizada</li>
                    </ul>
                </div>
            </div>
            
        </div>
        
        <!-- Estado Actual -->
        <div class="mt-6 bg-gray-800 rounded-lg border border-gray-700 p-6">
            <h2 class="text-xl font-bold text-white mb-4">📊 Estado Actual del Sistema</h2>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-gray-700 p-4 rounded text-center">
                    <div class="text-2xl font-bold text-blue-400"><?= count($empresas) ?></div>
                    <div class="text-gray-400 text-sm">Empresas Activas</div>
                </div>
                <div class="bg-gray-700 p-4 rounded text-center">
                    <div class="text-2xl font-bold text-green-400"><?= count(array_filter($empresas, fn($e) => $e['url_personalizada'])) ?></div>
                    <div class="text-gray-400 text-sm">Con URL Personalizada</div>
                </div>
                <div class="bg-gray-700 p-4 rounded text-center">
                    <div class="text-2xl font-bold text-yellow-400"><?= $config['modo'] ?></div>
                    <div class="text-gray-400 text-sm">Modo Actual</div>
                </div>
                <div class="bg-gray-700 p-4 rounded text-center">
                    <div class="text-2xl font-bold text-purple-400"><?= $config['dominio_base'] ?></div>
                    <div class="text-gray-400 text-sm">Dominio Base</div>
                </div>
            </div>
        </div>
        
    </main>
</body>
</html>
