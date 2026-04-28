<?php
require_once 'config.php';
requireLogin();

$user = getUser();
$empresa_id = $user['empresa_id'];

require_once 'lib/FaceRecognitionService.php';
require_once 'lib/CustomerProfiler.php';

$face_service = new FaceRecognitionService($empresa_id);
$profiler = new CustomerProfiler($empresa_id);

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'sincronizar_fotos') {
        $sincronizados = $face_service->sincronizarClientesConFotos();
        $mensaje = "✅ Se sincronizaron {$sincronizados} clientes con fotos al sistema facial";
    }
    
    if ($accion === 'simular_deteccion') {
        $face_data_demo = "rostro_simulado_" . time();
        $camara_id = intval($_POST['camara_id'] ?? 1);
        
        $resultado = $face_service->detectarRostro($face_data_demo, $camara_id);
        
        if ($resultado['tipo'] === 'CLIENTE') {
            $perfil_completo = $profiler->getPerfilCompleto($resultado['datos']['id']);
        }
    }
}

$clientes_con_fotos = $face_service->getClientesConFotos();
$perfiles_faciales = $face_service->getPerfilesClientes();
$personas_riesgo = $face_service->getPersonasRiesgo();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🤖 Demo Reconocimiento con Fotos - NEXUS POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-900 to-gray-800 text-gray-100 p-8">
    <div class="max-w-6xl mx-auto">
        <div class="bg-gray-800 rounded-lg border border-gray-700 p-6 mb-6">
            <h1 class="text-2xl font-bold text-blue-400 mb-4">🤖 RECONOCIMIENTO FACIAL CON FOTOS EXISTENTES</h1>
            
            <?php if (isset($mensaje)): ?>
                <div class="bg-green-900 border border-green-700 rounded p-3 mb-4">
                    <p class="text-green-300"><?= $mensaje ?></p>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="bg-gray-700 rounded p-4">
                    <h3 class="text-lg font-bold text-white mb-2">👥 Clientes con Fotos</h3>
                    <p class="text-3xl font-bold text-green-400"><?= count($clientes_con_fotos) ?></p>
                    <p class="text-gray-400 text-sm">Listos para reconocimiento</p>
                </div>
                <div class="bg-gray-700 rounded p-4">
                    <h3 class="text-lg font-bold text-white mb-2">🎯 Perfiles Faciales</h3>
                    <p class="text-3xl font-bold text-blue-400"><?= count($perfiles_faciales) ?></p>
                    <p class="text-gray-400 text-sm">Sincronizados</p>
                </div>
                <div class="bg-gray-700 rounded p-4">
                    <h3 class="text-lg font-bold text-white mb-2">⚠️ Personas de Riesgo</h3>
                    <p class="text-3xl font-bold text-red-400"><?= count($personas_riesgo) ?></p>
                    <p class="text-gray-400 text-sm">Catalogadas</p>
                </div>
            </div>

            <div class="bg-blue-900 border border-blue-700 rounded p-4 mb-6">
                <h2 class="text-lg font-bold text-white mb-3">📋 ¿CÓMO FUNCIONA?</h2>
                <ol class="text-sm space-y-2">
                    <li><strong>1.</strong> Los clientes suben su foto desde el módulo de clientes</li>
                    <li><strong>2.</strong> El sistema sincroniza automáticamente las fotos existentes</li>
                    <li><strong>3.</strong> Las cámaras detectan rostros y comparan con las fotos</li>
                    <li><strong>4.</strong> Si hay coincidencia, muestra perfil y compras frecuentes</li>
                </ol>
            </div>

            <form method="POST" class="mb-6">
                <input type="hidden" name="accion" value="sincronizar_fotos">
                <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded font-bold">
                    🔄 SINCRONIZAR FOTOS EXISTENTES
                </button>
            </form>

            <?php if (!empty($clientes_con_fotos)): ?>
                <div class="bg-gray-700 rounded p-4 mb-6">
                    <h2 class="text-lg font-bold text-white mb-3">👥 Clientes con Fotos de Perfil</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach ($clientes_con_fotos as $cliente): ?>
                            <div class="bg-gray-800 rounded p-3 border border-gray-600">
                                <div class="flex items-center space-x-3">
                                    <?php if ($cliente['foto_perfil']): ?>
                                        <img src="<?= htmlspecialchars($cliente['foto_perfil']) ?>" 
                                             alt="<?= htmlspecialchars($cliente['nombre']) ?>" 
                                             class="w-16 h-16 rounded object-cover border border-gray-500">
                                    <?php else: ?>
                                        <div class="w-16 h-16 bg-gray-600 rounded flex items-center justify-center">
                                            <span class="text-2xl">👤</span>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <p class="text-white font-bold"><?= htmlspecialchars($cliente['nombre'] . ' ' . ($cliente['apellido'] ?? '')) ?></p>
                                        <p class="text-gray-400 text-sm"><?= htmlspecialchars($cliente['email'] ?? '') ?></p>
                                        <span class="inline-block px-2 py-1 bg-green-900 text-green-300 text-xs rounded mt-1">
                                            ✅ Listo para reconocimiento
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="bg-yellow-900 border border-yellow-700 rounded p-4 mb-6">
                <h2 class="text-lg font-bold text-white mb-3">🎯 SIMULAR DETECCIÓN FACIAL</h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="accion" value="simular_deteccion">
                    <div>
                        <label class="block text-gray-300 text-sm mb-2">Cámara de simulación:</label>
                        <select name="camara_id" class="w-full bg-gray-700 text-white p-2 rounded border border-gray-600">
                            <option value="1">📹 ENTRADA PRINCIPAL</option>
                            <option value="2">📹 CAJA</option>
                            <option value="3">📹 DEPÓSITO</option>
                        </select>
                    </div>
                    <button type="submit" class="bg-yellow-600 hover:bg-yellow-700 text-white px-6 py-3 rounded font-bold">
                        🎭 SIMULAR DETECCIÓN DE ROSTRO
                    </button>
                </form>
            </div>

            <?php if (isset($resultado)): ?>
                <div class="bg-gray-700 rounded p-4 mb-6">
                    <h2 class="text-lg font-bold text-white mb-3">🎯 RESULTADO DE DETECCIÓN</h2>
                    <div class="space-y-3">
                        <div>
                            <p class="text-gray-400">Tipo:</p>
                            <span class="inline-block px-3 py-1 rounded text-sm font-bold
                                <?= $resultado['tipo'] === 'CLIENTE' ? 'bg-green-900 text-green-300' : 
                                   ($resultado['tipo'] === 'RIESGO' ? 'bg-red-900 text-red-300' : 'bg-gray-900 text-gray-300') ?>">
                                <?= $resultado['tipo'] ?>
                            </span>
                        </div>
                        <div>
                            <p class="text-gray-400">Mensaje:</p>
                            <p class="text-white"><?= htmlspecialchars($resultado['mensaje']) ?></p>
                        </div>
                        
                        <?php if ($resultado['tipo'] === 'CLIENTE' && isset($perfil_completo)): ?>
                            <div class="bg-green-900 border border-green-700 rounded p-3">
                                <h3 class="text-white font-bold mb-2">👤 Cliente Identificado</h3>
                                <div class="grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <p class="text-gray-300">Nombre:</p>
                                        <p class="text-white"><?= htmlspecialchars($perfil_completo['cliente']['nombre'] . ' ' . ($perfil_completo['cliente']['apellido'] ?? '')) ?></p>
                                    </div>
                                    <div>
                                        <p class="text-gray-300">Email:</p>
                                        <p class="text-white"><?= htmlspecialchars($perfil_completo['cliente']['email'] ?? '') ?></p>
                                    </div>
                                </div>
                                
                                <?php if (!empty($perfil_completo['recomendaciones']['producto_sugerido'])): ?>
                                    <div class="mt-3">
                                        <p class="text-gray-300 mb-1">🎯 Producto sugerido:</p>
                                        <p class="text-purple-300 font-bold">
                                            <?= htmlspecialchars($perfil_completo['recomendaciones']['producto_sugerido']['nombre']) ?>
                                            <span class="text-gray-400 text-sm ml-2">
                                                (<?= $perfil_completo['recomendaciones']['producto_sugerido']['motivo'] ?>)
                                            </span>
                                        </p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($perfil_completo['recomendaciones']['frases_empatia'])): ?>
                                    <div class="mt-3">
                                        <p class="text-gray-300 mb-1">💬 Frases sugeridas:</p>
                                        <div class="space-y-1">
                                            <?php foreach (array_slice($perfil_completo['recomendaciones']['frases_empatia'], 0, 2) as $frase): ?>
                                                <div class="bg-gray-800 rounded p-2 text-sm">
                                                    "<?= htmlspecialchars($frase) ?>"
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="bg-gray-700 rounded p-4">
                <h2 class="text-lg font-bold text-white mb-3">🚀 PRÓXIMOS PASOS</h2>
                <ol class="text-sm space-y-2">
                    <li><strong>1.</strong> Agrega campo de foto en <code>clientes.php</code> (ya implementado)</li>
                    <li><strong>2.</strong> Sube fotos de tus clientes habituales</li>
                    <li><strong>3.</strong> Sincroniza con el botón de arriba</li>
                    <li><strong>4.</strong> Prueba las detecciones simuladas</li>
                    <li><strong>5.</strong> Integra con cámaras reales cuando estén disponibles</li>
                </ol>
            </div>

            <div class="flex gap-4 mt-6">
                <a href="reconocimiento_facial.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded font-bold flex-1 text-center">
                    🤖 IR AL SISTEMA COMPLETO
                </a>
                <a href="clientes.php" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded font-bold flex-1 text-center">
                    👥 GESTIONAR CLIENTES
                </a>
                <a href="dashboard.php" class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded font-bold flex-1 text-center">
                    🏠 VOLVER AL DASHBOARD
                </a>
            </div>
        </div>
    </div>
</body>
</html>
