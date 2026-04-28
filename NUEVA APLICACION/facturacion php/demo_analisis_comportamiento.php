<?php
require_once 'config.php';
requireLogin();

$user = getUser();
$empresa_id = $user['empresa_id'];

require_once 'lib/BehaviorAnalyzer.php';

$analyzer = new BehaviorAnalyzer($empresa_id);

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'configurar_patrones') {
        $configurados = $analyzer->configurarPatronesDefecto();
        $mensaje = "✅ Se configuraron {$configurados} patrones de comportamiento por defecto";
    }
    
    if ($accion === 'simular_analisis') {
        $camara_id = intval($_POST['camara_id'] ?? 1);
        $frame_data_demo = "frame_simulado_" . time();
        
        $resultados = $analyzer->analizarFrame($frame_data_demo, $camara_id);
        
        if (!empty($resultados)) {
            $mensaje = "⚠️ Se detectaron " . count($resultados) . " comportamientos sospechosos";
        } else {
            $mensaje = "✅ No se detectaron comportamientos anómalos";
        }
    }
}

$eventos_recientes = $analyzer->getEventosRecientes(10);
$alertas_activas = $analyzer->getAlertasActivas();
$estadisticas = $analyzer->getEstadisticas();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎯 Demo Análisis de Comportamiento - NEXUS POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes pulse-red {
            0%, 100% { background-color: rgb(239 68 68); }
            50% { background-color: rgb(220 38 38); }
        }
        .alerta-critica {
            animation: pulse-red 2s infinite;
        }
        .coordenadas-box {
            border: 2px dashed #ef4444;
            background-color: rgba(239, 68, 68, 0.1);
        }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-900 to-gray-800 text-gray-100 p-8">
    <div class="max-w-6xl mx-auto">
        <div class="bg-gray-800 rounded-lg border border-gray-700 p-6 mb-6">
            <h1 class="text-2xl font-bold text-red-400 mb-4">🎯 ANÁLISIS DE COMPORTAMIENTO SOSPECHOSO</h1>
            
            <?php if (isset($mensaje)): ?>
                <div class="bg-yellow-900 border border-yellow-700 rounded p-3 mb-4">
                    <p class="text-yellow-300"><?= $mensaje ?></p>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-gray-700 rounded p-4">
                    <h3 class="text-lg font-bold text-white mb-2">📊 Eventos Hoy</h3>
                    <p class="text-3xl font-bold text-blue-400">
                        <?= array_sum(array_column($estadisticas['eventos_por_tipo'] ?? [], 'total')) ?>
                    </p>
                    <p class="text-gray-400 text-sm">Comportamientos detectados</p>
                </div>
                <div class="bg-gray-700 rounded p-4">
                    <h3 class="text-lg font-bold text-white mb-2">⚠️ Alertas Altas</h3>
                    <p class="text-3xl font-bold text-orange-400">
                        <?= ($estadisticas['eventos_por_riesgo'][0]['total'] ?? 0) + ($estadisticas['eventos_por_riesgo'][1]['total'] ?? 0) ?>
                    </p>
                    <p class="text-gray-400 text-sm">Alto + Crítico</p>
                </div>
                <div class="bg-gray-700 rounded p-4">
                    <h3 class="text-lg font-bold text-white mb-2">🔔 Pendientes</h3>
                    <p class="text-3xl font-bold text-red-400"><?= $estadisticas['alertas_pendientes'] ?></p>
                    <p class="text-gray-400 text-sm">Sin notificar</p>
                </div>
                <div class="bg-gray-700 rounded p-4">
                    <h3 class="text-lg font-bold text-white mb-2">📹 Cámaras</h3>
                    <p class="text-3xl font-bold text-green-400">3</p>
                    <p class="text-gray-400 text-sm">Monitoreando</p>
                </div>
            </div>

            <div class="bg-purple-900 border border-purple-700 rounded p-4 mb-6">
                <h2 class="text-lg font-bold text-white mb-3">🎭 ¿QUÉ DETECTA EL SISTEMA?</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <h3 class="text-purple-300 font-bold mb-2">🚶‍♂️ Posturas Anómalas</h3>
                        <ul class="text-gray-300 space-y-1">
                            <li>• Personas agachadas sospechosamente</li>
                            <li>• Mirada nerviosa constante</li>
                            <li>• Posturas de ocultamiento</li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-purple-300 font-bold mb-2">🏃‍♂️ Movimientos Sospechosos</h3>
                        <ul class="text-gray-300 space-y-1">
                            <li>• Movimientos rápidos y erráticos</li>
                            <li>• Comportamiento nervioso</li>
                            <li>• Manos en bolsillos constantes</li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-purple-300 font-bold mb-2">👥 Aglomeraciones</h3>
                        <ul class="text-gray-300 space-y-1">
                            <li>• Grupos inusuales de personas</li>
                            <li>• Concentraciones sospechosas</li>
                            <li>• Patrones de agrupamiento</li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-purple-300 font-bold mb-2">🎒 Objetos Ocultos</h3>
                        <ul class="text-gray-300 space-y-1">
                            <li>• Bolsillos grandes sospechosos</li>
                            <li>• Objetos ocultos en ropa</li>
                            <li>• Paquetes no identificados</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <form method="POST" class="bg-blue-900 border border-blue-700 rounded p-4">
                    <input type="hidden" name="accion" value="configurar_patrones">
                    <h3 class="text-lg font-bold text-white mb-3">⚙️ Configurar Sistema</h3>
                    <p class="text-gray-300 text-sm mb-4">Configura los patrones de comportamiento por defecto</p>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded font-bold w-full">
                        🔄 CONFIGURAR PATRONES
                    </button>
                </form>

                <form method="POST" class="bg-yellow-900 border border-yellow-700 rounded p-4">
                    <input type="hidden" name="accion" value="simular_analisis">
                    <h3 class="text-lg font-bold text-white mb-3">🎭 Simular Análisis</h3>
                    <div class="mb-4">
                        <label class="block text-gray-300 text-sm mb-2">Cámara de simulación:</label>
                        <select name="camara_id" class="w-full bg-gray-700 text-white p-2 rounded border border-gray-600">
                            <option value="1">📹 ENTRADA PRINCIPAL</option>
                            <option value="2">📹 CAJA</option>
                            <option value="3">📹 DEPÓSITO</option>
                        </select>
                    </div>
                    <button type="submit" class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded font-bold w-full">
                        🎬 SIMULAR DETECCIÓN
                    </button>
                </form>
            </div>

            <?php if (!empty($eventos_recientes)): ?>
                <div class="bg-gray-700 rounded p-4 mb-6">
                    <h2 class="text-lg font-bold text-white mb-3">📊 Eventos Recientes</h2>
                    <div class="space-y-3">
                        <?php foreach ($eventos_recientes as $evento): ?>
                            <div class="bg-gray-800 rounded p-3 border-l-4 
                                <?= $evento['nivel_riesgo'] === 'CRITICO' ? 'border-red-500' : 
                                   ($evento['nivel_riesgo'] === 'ALTO' ? 'border-orange-500' : 
                                   ($evento['nivel_riesgo'] === 'MEDIO' ? 'border-yellow-500' : 'border-green-500')) ?>">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="px-2 py-1 rounded text-xs font-bold
                                                <?= $evento['nivel_riesgo'] === 'CRITICO' ? 'bg-red-900 text-red-300' : 
                                                   ($evento['nivel_riesgo'] === 'ALTO' ? 'bg-orange-900 text-orange-300' : 
                                                   ($evento['nivel_riesgo'] === 'MEDIO' ? 'bg-yellow-900 text-yellow-300' : 'bg-green-900 text-green-300')) ?>">
                                                <?= $evento['nivel_riesgo'] ?>
                                            </span>
                                            <span class="text-gray-400 text-sm"><?= $evento['tipo_evento'] ?></span>
                                        </div>
                                        <p class="text-white text-sm"><?= htmlspecialchars($evento['descripcion']) ?></p>
                                        <div class="flex items-center gap-4 mt-2 text-xs text-gray-400">
                                            <span>📹 <?= htmlspecialchars($evento['camara_nombre'] ?? 'Cámara') ?></span>
                                            <span>🕐 <?= date('H:i:s', strtotime($evento['fecha'])) ?></span>
                                            <span>🎯 <?= number_format($evento['confianza'] * 100, 1) ?>% confianza</span>
                                        </div>
                                    </div>
                                    <?php if ($evento['coordenadas']): ?>
                                        <div class="ml-4">
                                            <div class="coordenadas-box rounded p-2 text-xs">
                                                <p class="text-red-300">Área detectada</p>
                                                <p class="text-gray-300">X:<?= json_decode($evento['coordenadas'])->x ?> 
                                                   Y:<?= json_decode($evento['coordenadas'])->y ?></p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($alertas_activas)): ?>
                <div class="bg-red-900 border border-red-700 rounded p-4 mb-6 alerta-critica">
                    <h2 class="text-lg font-bold text-white mb-3">🚨 ALERTAS ACTIVAS</h2>
                    <div class="space-y-3">
                        <?php foreach ($alertas_activas as $alerta): ?>
                            <div class="bg-red-800 rounded p-3">
                                <div class="flex items-start gap-3">
                                    <div class="text-2xl">⚠️</div>
                                    <div class="flex-1">
                                        <p class="text-white font-bold mb-1">ALERTA DE COMPORTAMIENTO SOSPECHOSO</p>
                                        <p class="text-gray-300 text-sm whitespace-pre-line"><?= htmlspecialchars($alerta['mensaje']) ?></p>
                                        <div class="flex items-center gap-4 mt-2 text-xs text-gray-400">
                                            <span>📹 <?= htmlspecialchars($alerta['camara_nombre'] ?? 'Cámara') ?></span>
                                            <span>🕐 <?= date('H:i:s', strtotime($alerta['fecha'])) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="bg-gray-700 rounded p-4">
                <h2 class="text-lg font-bold text-white mb-3">🚀 CÓMO FUNCIONA EN PRODUCCIÓN</h2>
                <ol class="text-sm space-y-2">
                    <li><strong>1.</strong> Las cámaras capturan video en tiempo real</li>
                    <li><strong>2.</strong> La IA analiza cada frame buscando patrones</li>
                    <li><strong>3.</strong> Detecta posturas, movimientos y comportamientos</li>
                    <li><strong>4.</strong> Clasifica el nivel de riesgo (Bajo/Medio/Alto/Crítico)</li>
                    <li><strong>5.</strong> Genera alertas automáticas para niveles altos</li>
                    <li><strong>6.</strong> Registra coordenadas para revisión visual</li>
                </ol>
                
                <div class="mt-4 p-3 bg-gray-800 rounded">
                    <h3 class="text-white font-bold mb-2">🔧 Tecnologías IA Sugeridas</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                        <div>
                            <p class="text-blue-300 font-bold">MediaPipe</p>
                            <p class="text-gray-400">Detección de posturas y puntos clave</p>
                        </div>
                        <div>
                            <p class="text-green-300 font-bold">OpenPose</p>
                            <p class="text-gray-400">Análisis de pose humana</p>
                        </div>
                        <div>
                            <p class="text-purple-300 font-bold">YOLO</p>
                            <p class="text-gray-400">Detección de objetos y personas</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex gap-4 mt-6">
                <a href="reconocimiento_facial.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded font-bold flex-1 text-center">
                    🤖 IR A RECONOCIMIENTO FACIAL
                </a>
                <a href="camaras.php" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded font-bold flex-1 text-center">
                    📹 IR A CÁMARAS
                </a>
                <a href="dashboard.php" class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded font-bold flex-1 text-center">
                    🏠 VOLVER AL DASHBOARD
                </a>
            </div>
        </div>
    </div>
</body>
</html>
