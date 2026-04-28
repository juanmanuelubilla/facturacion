<?php
require_once 'config.php';
requireLogin();

$user = getUser();
$empresa_id = $user['empresa_id'];

require_once 'lib/FaceRecognitionService.php';
require_once 'lib/CustomerProfiler.php';

$face_service = new FaceRecognitionService($empresa_id);
$profiler = new CustomerProfiler($empresa_id);

// Obtener primer cliente existente
$cliente = fetch("SELECT * FROM clientes WHERE empresa_id = ? LIMIT 1", [$empresa_id]);

if (!$cliente) {
    echo "❌ No hay clientes registrados. Primero crea un cliente en clientes.php<br>";
    echo "<a href='clientes.php'>👥 Ir a Clientes</a>";
    exit;
}

// Registrar perfil facial de demo
$face_data_demo = "perfil_demo_" . $cliente['id'] . "_" . time();
$face_service->registrarPerfilFacial($cliente['id'], $face_data_demo);

// Generar análisis de compras
$perfil_completo = $profiler->getPerfilCompleto($cliente['id']);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎯 Demo Perfil Cliente - NEXUS POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-900 to-gray-800 text-gray-100 p-8">
    <div class="max-w-4xl mx-auto">
        <div class="bg-gray-800 rounded-lg border border-gray-700 p-6 mb-6">
            <h1 class="text-2xl font-bold text-green-400 mb-4">✅ PERFIL DE CLIENTE REGISTRADO</h1>
            
            <div class="bg-green-900 border border-green-700 rounded p-4 mb-6">
                <h2 class="text-lg font-bold text-white mb-2">👤 Datos del Cliente</h2>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-gray-400">Nombre:</p>
                        <p class="text-white font-bold"><?= htmlspecialchars($cliente['nombre'] . ' ' . ($cliente['apellido'] ?? '')) ?></p>
                    </div>
                    <div>
                        <p class="text-gray-400">Email:</p>
                        <p class="text-white"><?= htmlspecialchars($cliente['email'] ?? 'No registrado') ?></p>
                    </div>
                    <div>
                        <p class="text-gray-400">Teléfono:</p>
                        <p class="text-white"><?= htmlspecialchars($cliente['telefono'] ?? 'No registrado') ?></p>
                    </div>
                    <div>
                        <p class="text-gray-400">Perfil Facial:</p>
                        <p class="text-green-300 font-bold">✅ Registrado</p>
                    </div>
                </div>
            </div>

            <?php if ($perfil_completo['analisis']): ?>
                <div class="bg-blue-900 border border-blue-700 rounded p-4 mb-6">
                    <h2 class="text-lg font-bold text-white mb-2">📊 Análisis de Compras</h2>
                    
                    <?php if (!empty($perfil_completo['analisis']['productos_frecuentes'])): ?>
                        <div class="mb-4">
                            <p class="text-gray-300 mb-2">🛒 Productos más comprados:</p>
                            <div class="space-y-1">
                                <?php foreach ($perfil_completo['analisis']['productos_frecuentes'] as $producto): ?>
                                    <div class="flex justify-between text-sm">
                                        <span><?= htmlspecialchars($producto['nombre']) ?></span>
                                        <span class="text-blue-300"><?= $producto['frecuencia'] ?> veces</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($perfil_completo['analisis']['ticket_promedio']['total_ventas'] > 0): ?>
                        <div class="mb-4">
                            <p class="text-gray-300 mb-2">💰 Ticket promedio:</p>
                            <p class="text-green-300 font-bold">$<?= number_format($perfil_completo['analisis']['ticket_promedio']['promedio'], 2) ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($perfil_completo['analisis']['ultimas_compras'])): ?>
                        <div>
                            <p class="text-gray-300 mb-2">📅 Últimas compras:</p>
                            <div class="space-y-1">
                                <?php foreach ($perfil_completo['analisis']['ultimas_compras'] as $compra): ?>
                                    <div class="text-sm">
                                        <span class="text-gray-400"><?= date('d/m H:i', strtotime($compra['fecha'])) ?></span>
                                        <span class="text-green-300 ml-2">$<?= number_format($compra['total'], 2) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($perfil_completo['recomendaciones']): ?>
                <div class="bg-purple-900 border border-purple-700 rounded p-4 mb-6">
                    <h2 class="text-lg font-bold text-white mb-2">🎯 Recomendaciones Personalizadas</h2>
                    
                    <?php if ($perfil_completo['recomendaciones']['producto_sugerido']): ?>
                        <div class="mb-4">
                            <p class="text-gray-300 mb-2">🌟 Producto sugerido:</p>
                            <p class="text-purple-300 font-bold">
                                <?= htmlspecialchars($perfil_completo['recomendaciones']['producto_sugerido']['nombre']) ?>
                                <span class="text-gray-400 text-sm ml-2">
                                    (<?= $perfil_completo['recomendaciones']['producto_sugerido']['motivo'] ?>)
                                </span>
                            </p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($perfil_completo['recomendaciones']['frases_empatia'])): ?>
                        <div>
                            <p class="text-gray-300 mb-2">💬 Frases de empatía:</p>
                            <div class="space-y-1">
                                <?php foreach (array_slice($perfil_completo['recomendaciones']['frases_empatia'], 0, 3) as $frase): ?>
                                    <div class="bg-gray-700 rounded p-2 text-sm">
                                        "<?= htmlspecialchars($frase) ?>"
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="bg-yellow-900 border border-yellow-700 rounded p-4 mb-6">
                <h2 class="text-lg font-bold text-white mb-2">🚀 Cómo Probar el Sistema</h2>
                <ol class="text-sm space-y-2">
                    <li>1. Ve a <a href="reconocimiento_facial.php" class="text-blue-300 underline">🤖 Reconocimiento Facial</a></li>
                    <li>2. Click en tab "👥 CLIENTES"</li>
                    <li>3. Verás este cliente con perfil facial registrado</li>
                    <li>4. Simula una detección para ver las recomendaciones</li>
                    <li>5. Cuando entre este cliente, el sistema mostrará sus compras frecuentes</li>
                </ol>
            </div>

            <div class="flex gap-4">
                <a href="reconocimiento_facial.php" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded font-bold flex-1 text-center">
                    🤖 IR AL SISTEMA DE RECONOCIMIENTO
                </a>
                <a href="dashboard.php" class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded font-bold flex-1 text-center">
                    🏠 VOLVER AL DASHBOARD
                </a>
            </div>
        </div>
    </div>
</body>
</html>
