<?php
require_once 'config.php';
requireLogin();

$user = getUser();
$empresa_id = $user['empresa_id'];

require_once 'lib/FaceRecognitionService.php';
$face_service = new FaceRecognitionService($empresa_id);

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'agregar_persona_riesgo') {
        $datos = [
            'nombre' => trim($_POST['nombre'] ?? ''),
            'apellido' => trim($_POST['apellido'] ?? ''),
            'alias' => trim($_POST['alias'] ?? ''),
            'foto' => trim($_POST['foto'] ?? ''),
            'tipo_riesgo' => trim($_POST['tipo_riesgo'] ?? 'MEDIO'),
            'nivel_peligro' => intval($_POST['nivel_peligro'] ?? 3),
            'descripcion' => trim($_POST['descripcion'] ?? ''),
            'modus_operandi' => trim($_POST['modus_operandi'] ?? '')
        ];
        
        $face_service->agregarPersonaRiesgo($datos);
        header('Location: reconocimiento_facial.php');
        exit;
    }
    
    if ($accion === 'registrar_perfil_cliente') {
        $cliente_id = intval($_POST['cliente_id'] ?? 0);
        $face_data = trim($_POST['face_data'] ?? '');
        
        $face_service->registrarPerfilFacial($cliente_id, $face_data);
        header('Location: reconocimiento_facial.php');
        exit;
    }
    
    if ($accion === 'actualizar_config') {
        $config = [
            'alertas_activas' => isset($_POST['alertas_activas']) ? 1 : 0,
            'notificacion_sonido' => isset($_POST['notificacion_sonido']) ? 1 : 0,
            'notificacion_pantalla' => isset($_POST['notificacion_pantalla']) ? 1 : 0,
            'email_alerta' => isset($_POST['email_alerta']) ? 1 : 0,
            'whatsapp_alerta' => isset($_POST['whatsapp_alerta']) ? 1 : 0,
            'umbral_confianza' => floatval($_POST['umbral_confianza'] ?? 0.80),
            'tiempo_grabacion_seg' => intval($_POST['tiempo_grabacion_seg'] ?? 60)
        ];
        
        $face_service->actualizarConfigAlertas($config);
        header('Location: reconocimiento_facial.php');
        exit;
    }
    
    if ($accion === 'atender_alerta') {
        $alerta_id = intval($_POST['alerta_id'] ?? 0);
        $acciones_tomadas = trim($_POST['acciones_tomadas'] ?? '');
        
        $face_service->atenderAlerta($alerta_id, $acciones_tomadas);
        header('Location: reconocimiento_facial.php');
        exit;
    }
    
    if ($accion === 'simular_deteccion') {
        $face_data = trim($_POST['face_data'] ?? '');
        $camara_id = intval($_POST['camara_id'] ?? 1);
        
        $resultado = $face_service->detectarRostro($face_data, $camara_id);
        header('Content-Type: application/json');
        echo json_encode($resultado);
        exit;
    }
}

$personas_riesgo = $face_service->getPersonasRiesgo();
$perfiles_clientes = $face_service->getPerfilesClientes();
$detecciones_recientes = $face_service->getDeteccionesRecientes(20);
$alertas_activas = $face_service->getAlertasActivas();
$config = $face_service->getConfig();

// Verificar si hay alerta pendiente de mostrar
$alerta_pendiente = $_SESSION['alerta_seguridad'] ?? null;
if ($alerta_pendiente && $alerta_pendiente['mostrada']) {
    unset($_SESSION['alerta_seguridad']);
    $alerta_pendiente = null;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🤖 Reconocimiento Facial - NEXUS POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-900 to-gray-800 text-gray-100">
    <header class="bg-gray-900 shadow-lg">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold text-blue-400">🤖 RECONOCIMIENTO FACIAL</h1>
            <a href="dashboard.php" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded">VOLVER</a>
        </div>
    </header>
    
    <!-- Alerta de Seguridad Crítica -->
    <?php if ($alerta_pendiente): ?>
    <div id="alertaCritica" class="fixed inset-0 bg-red-900 bg-opacity-95 flex items-center justify-center z-50">
        <div class="bg-gray-800 rounded-lg border-4 border-red-600 p-6 max-w-2xl w-full mx-4">
            <div class="text-center mb-4">
                <div class="text-6xl mb-2">🚨</div>
                <h2 class="text-2xl font-bold text-red-400">¡ALERTA DE SEGURIDAD ACTIVADA!</h2>
            </div>
            
            <div class="bg-red-900 rounded p-4 mb-4">
                <h3 class="text-lg font-bold text-white mb-2">🔴 PERSONA DE ALTO RIESGO DETECTADA</h3>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <p class="text-gray-400">Nombre:</p>
                        <p class="text-white font-bold"><?= htmlspecialchars($alerta_pendiente['persona']['nombre']) ?></p>
                    </div>
                    <div>
                        <p class="text-gray-400">Alias:</p>
                        <p class="text-white font-bold"><?= htmlspecialchars($alerta_pendiente['persona']['alias'] ?? 'N/A') ?></p>
                    </div>
                    <div>
                        <p class="text-gray-400">Tipo de Riesgo:</p>
                        <p class="text-red-400 font-bold"><?= htmlspecialchars($alerta_pendiente['persona']['tipo_riesgo']) ?></p>
                    </div>
                    <div>
                        <p class="text-gray-400">Ubicación:</p>
                        <p class="text-white font-bold"><?= htmlspecialchars($alerta_pendiente['camara']['nombre']) ?></p>
                    </div>
                </div>
                <?php if ($alerta_pendiente['persona']['descripcion']): ?>
                    <div class="mt-3">
                        <p class="text-gray-400">Descripción:</p>
                        <p class="text-yellow-300"><?= htmlspecialchars($alerta_pendiente['persona']['descripcion']) ?></p>
                    </div>
                <?php endif; ?>
                <?php if ($alerta_pendiente['persona']['modus_operandi']): ?>
                    <div class="mt-3">
                        <p class="text-gray-400">Modus Operandi:</p>
                        <p class="text-orange-300"><?= htmlspecialchars($alerta_pendiente['persona']['modus_operandi']) ?></p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="flex gap-4">
                <button onclick="marcarAlertaMostrada()" class="flex-1 bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded font-bold">
                    ✅ ENTENDIDO
                </button>
                <button onclick="window.location.href='camaras.php'" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded font-bold">
                    📹 IR A CÁMARAS
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <main class="container mx-auto px-6 py-8">
        <!-- Estadísticas -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Personas de Riesgo</p>
                        <p class="text-2xl font-bold text-red-400"><?= count($personas_riesgo) ?></p>
                    </div>
                    <div class="text-3xl">⚠️</div>
                </div>
            </div>
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Clientes Registrados</p>
                        <p class="text-2xl font-bold text-green-400"><?= count($perfiles_clientes) ?></p>
                    </div>
                    <div class="text-3xl">👥</div>
                </div>
            </div>
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Alertas Activas</p>
                        <p class="text-2xl font-bold text-yellow-400"><?= count($alertas_activas) ?></p>
                    </div>
                    <div class="text-3xl">🚨</div>
                </div>
            </div>
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Detecciones Hoy</p>
                        <p class="text-2xl font-bold text-blue-400"><?= count($detecciones_recientes) ?></p>
                    </div>
                    <div class="text-3xl">📊</div>
                </div>
            </div>
        </div>

        <!-- Tabs de Navegación -->
        <div class="flex gap-2 mb-6 flex-wrap">
            <button onclick="showTab('riesgo')" id="tabRiesgo" class="px-4 py-2 rounded font-bold bg-red-600 text-white">⚠️ PERSONAS DE RIESGO</button>
            <button onclick="showTab('clientes')" id="tabClientes" class="px-4 py-2 rounded font-bold bg-gray-700 text-white">👥 CLIENTES</button>
            <button onclick="showTab('detecciones')" id="tabDetecciones" class="px-4 py-2 rounded font-bold bg-gray-700 text-white">📋 DETECCIONES</button>
            <button onclick="showTab('alertas')" id="tabAlertas" class="px-4 py-2 rounded font-bold bg-gray-700 text-white">🚨 ALERTAS</button>
            <button onclick="showTab('config')" id="tabConfig" class="px-4 py-2 rounded font-bold bg-gray-700 text-white">⚙️ CONFIGURACIÓN</button>
        </div>

        <!-- Panel Personas de Riesgo -->
        <div id="panelRiesgo" class="bg-gray-800 rounded-lg border border-gray-700 p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-white">⚠️ Catálogo de Personas de Riesgo</h2>
                <button onclick="mostrarFormularioRiesgo()" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded text-sm">
                    ➕ AGREGAR PERSONA
                </button>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-700">
                            <th class="text-left py-2 px-3 text-gray-400">Nombre</th>
                            <th class="text-left py-2 px-3 text-gray-400">Alias</th>
                            <th class="text-left py-2 px-3 text-gray-400">Tipo</th>
                            <th class="text-left py-2 px-3 text-gray-400">Nivel</th>
                            <th class="text-left py-2 px-3 text-gray-400">Última Detección</th>
                            <th class="text-left py-2 px-3 text-gray-400">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($personas_riesgo)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-gray-400">
                                    No hay personas de riesgo registradas
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($personas_riesgo as $persona): ?>
                                <tr class="border-b border-gray-700">
                                    <td class="py-2 px-3">
                                        <?= htmlspecialchars($persona['nombre'] . ' ' . ($persona['apellido'] ?? '')) ?>
                                    </td>
                                    <td class="py-2 px-3"><?= htmlspecialchars($persona['alias'] ?? '-') ?></td>
                                    <td class="py-2 px-3">
                                        <span class="px-2 py-1 rounded text-xs 
                                            <?= $persona['tipo_riesgo'] === 'ALTO' ? 'bg-red-900 text-red-300' : 
                                               ($persona['tipo_riesgo'] === 'MEDIO' ? 'bg-yellow-900 text-yellow-300' : 'bg-orange-900 text-orange-300') ?>">
                                            <?= $persona['tipo_riesgo'] ?>
                                        </span>
                                    </td>
                                    <td class="py-2 px-3">
                                        <div class="flex items-center">
                                            <div class="w-16 bg-gray-700 rounded-full h-2 mr-2">
                                                <div class="bg-red-500 h-2 rounded-full" style="width: <?= $persona['nivel_peligro'] * 10 ?>%"></div>
                                            </div>
                                            <span class="text-xs"><?= $persona['nivel_peligro'] ?>/10</span>
                                        </div>
                                    </td>
                                    <td class="py-2 px-3 text-sm">
                                        <?= $persona['ultima_deteccion'] ? date('d/m H:i', strtotime($persona['ultima_deteccion'])) : 'Nunca' ?>
                                    </td>
                                    <td class="py-2 px-3">
                                        <button class="bg-blue-600 hover:bg-blue-700 text-white px-2 py-1 rounded text-xs">
                                            📋 Ver
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Panel Clientes -->
        <div id="panelClientes" class="hidden bg-gray-800 rounded-lg border border-gray-700 p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-white">👥 Perfiles Faciales de Clientes</h2>
                <button onclick="mostrarFormularioCliente()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded text-sm">
                    ➕ REGISTRAR PERFIL
                </button>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-700">
                            <th class="text-left py-2 px-3 text-gray-400">Cliente</th>
                            <th class="text-left py-2 px-3 text-gray-400">Email</th>
                            <th class="text-left py-2 px-3 text-gray-400">Registro</th>
                            <th class="text-left py-2 px-3 text-gray-400">Última Detección</th>
                            <th class="text-left py-2 px-3 text-gray-400">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($perfiles_clientes)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-gray-400">
                                    No hay perfiles faciales registrados
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($perfiles_clientes as $perfil): ?>
                                <tr class="border-b border-gray-700">
                                    <td class="py-2 px-3">
                                        <?= htmlspecialchars($perfil['nombre'] . ' ' . ($perfil['apellido'] ?? '')) ?>
                                    </td>
                                    <td class="py-2 px-3 text-sm"><?= htmlspecialchars($perfil['email'] ?? '-') ?></td>
                                    <td class="py-2 px-3 text-sm"><?= date('d/m/Y', strtotime($perfil['fecha_registro'])) ?></td>
                                    <td class="py-2 px-3 text-sm">
                                        <?= $perfil['ultima_deteccion'] ? date('d/m H:i', strtotime($perfil['ultima_deteccion'])) : 'Nunca' ?>
                                    </td>
                                    <td class="py-2 px-3">
                                        <button class="bg-blue-600 hover:bg-blue-700 text-white px-2 py-1 rounded text-xs">
                                            📋 Ver
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Panel Detecciones -->
        <div id="panelDetecciones" class="hidden bg-gray-800 rounded-lg border border-gray-700 p-6">
            <h2 class="text-xl font-bold text-white mb-4">📋 Detecciones Recientes</h2>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-700">
                            <th class="text-left py-2 px-3 text-gray-400">Fecha/Hora</th>
                            <th class="text-left py-2 px-3 text-gray-400">Tipo</th>
                            <th class="text-left py-2 px-3 text-gray-400">Persona</th>
                            <th class="text-left py-2 px-3 text-gray-400">Cámara</th>
                            <th class="text-left py-2 px-3 text-gray-400">Confianza</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($detecciones_recientes)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-gray-400">
                                    No hay detecciones registradas
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($detecciones_recientes as $deteccion): ?>
                                <tr class="border-b border-gray-700">
                                    <td class="py-2 px-3 text-sm">
                                        <?= date('d/m H:i:s', strtotime($deteccion['fecha'])) ?>
                                    </td>
                                    <td class="py-2 px-3">
                                        <span class="px-2 py-1 rounded text-xs 
                                            <?= $deteccion['tipo_deteccion'] === 'CLIENTE' ? 'bg-green-900 text-green-300' : 
                                               ($deteccion['tipo_deteccion'] === 'RIESGO' ? 'bg-red-900 text-red-300' : 'bg-gray-900 text-gray-300') ?>">
                                            <?= $deteccion['tipo_deteccion'] ?>
                                        </span>
                                    </td>
                                    <td class="py-2 px-3 text-sm">
                                        <?= $deteccion['tipo_deteccion'] === 'CLIENTE' ? htmlspecialchars($deteccion['cliente_nombre']) : 
                                           ($deteccion['tipo_deteccion'] === 'RIESGO' ? htmlspecialchars($deteccion['riesgo_nombre']) : 'Desconocido') ?>
                                    </td>
                                    <td class="py-2 px-3 text-sm"><?= htmlspecialchars($deteccion['camara_nombre']) ?></td>
                                    <td class="py-2 px-3 text-sm">
                                        <?= number_format($deteccion['confianza'] * 100, 1) ?>%
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Panel Alertas -->
        <div id="panelAlertas" class="hidden bg-gray-800 rounded-lg border border-gray-700 p-6">
            <h2 class="text-xl font-bold text-white mb-4">🚨 Alertas de Seguridad</h2>
            
            <div class="space-y-4">
                <?php if (empty($alertas_activas)): ?>
                    <div class="text-center py-8 text-gray-400">
                        <div class="text-4xl mb-2">🛡️</div>
                        <p class="text-xl">No hay alertas activas</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($alertas_activas as $alerta): ?>
                        <div class="bg-red-900 border border-red-700 rounded p-4">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="font-bold text-red-300">
                                        🚨 <?= htmlspecialchars($alerta['persona_nombre']) ?>
                                    </h3>
                                    <p class="text-gray-300 text-sm">
                                        <?= htmlspecialchars($alerta['tipo_riesgo']) ?> - <?= htmlspecialchars($alerta['camara_nombre']) ?>
                                    </p>
                                    <p class="text-gray-400 text-xs">
                                        <?= date('d/m H:i:s', strtotime($alerta['fecha'])) ?> - 
                                        Confianza: <?= number_format($alerta['confianza'] * 100, 1) ?>%
                                    </p>
                                    <?php if ($alerta['descripcion']): ?>
                                        <p class="text-yellow-300 text-sm mt-2"><?= htmlspecialchars($alerta['descripcion']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <form method="POST" class="flex gap-2">
                                    <input type="hidden" name="accion" value="atender_alerta">
                                    <input type="hidden" name="alerta_id" value="<?= $alerta['id'] ?>">
                                    <input type="text" name="acciones_tomadas" placeholder="Acciones tomadas" 
                                           class="bg-gray-700 text-white px-2 py-1 rounded text-sm">
                                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm">
                                        ✅ ATENDER
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Panel Configuración -->
        <div id="panelConfig" class="hidden bg-gray-800 rounded-lg border border-gray-700 p-6">
            <h2 class="text-xl font-bold text-white mb-4">⚙️ Configuración del Sistema</h2>
            <form method="POST">
                <input type="hidden" name="accion" value="actualizar_config">
                <div class="space-y-4">
                    <div class="bg-gray-700 rounded p-4">
                        <label class="flex items-center text-white cursor-pointer">
                            <input type="checkbox" name="alertas_activas" value="1" <?= ($config['alertas_activas'] ?? 1) ? 'checked' : '' ?> class="mr-2">
                            ACTIVAR ALERTAS DE SEGURIDAD
                        </label>
                        <p class="text-gray-400 text-xs mt-1">Genera alertas automáticas al detectar personas de riesgo</p>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-gray-700 rounded p-4">
                            <label class="flex items-center text-white cursor-pointer">
                                <input type="checkbox" name="notificacion_pantalla" value="1" <?= ($config['notificacion_pantalla'] ?? 1) ? 'checked' : '' ?> class="mr-2">
                                📺 Notificación en Pantalla
                            </label>
                        </div>
                        <div class="bg-gray-700 rounded p-4">
                            <label class="flex items-center text-white cursor-pointer">
                                <input type="checkbox" name="notificacion_sonido" value="1" <?= ($config['notificacion_sonido'] ?? 1) ? 'checked' : '' ?> class="mr-2">
                                🔊 Alerta de Sonido
                            </label>
                        </div>
                        <div class="bg-gray-700 rounded p-4">
                            <label class="flex items-center text-white cursor-pointer">
                                <input type="checkbox" name="email_alerta" value="1" <?= ($config['email_alerta'] ?? 0) ? 'checked' : '' ?> class="mr-2">
                                📧 Notificación por Email
                            </label>
                        </div>
                        <div class="bg-gray-700 rounded p-4">
                            <label class="flex items-center text-white cursor-pointer">
                                <input type="checkbox" name="whatsapp_alerta" value="1" <?= ($config['whatsapp_alerta'] ?? 0) ? 'checked' : '' ?> class="mr-2">
                                📱 Notificación por WhatsApp
                            </label>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Umbral de Confianza</label>
                            <input type="number" name="umbral_confianza" value="<?= $config['umbral_confianza'] ?? 0.80 ?>" 
                                   min="0.5" max="1.0" step="0.05"
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                            <p class="text-gray-500 text-xs mt-1">Mínimo 0.5 (50%) - Máximo 1.0 (100%)</p>
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Tiempo de Grabación (segundos)</label>
                            <input type="number" name="tiempo_grabacion_seg" value="<?= $config['tiempo_grabacion_seg'] ?? 60 ?>" 
                                   min="10" max="300"
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                            <p class="text-gray-500 text-xs mt-1">Duración de grabación al detectar riesgo</p>
                        </div>
                    </div>
                </div>
                <button type="submit" class="mt-4 bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded font-bold w-full md:w-auto">
                    💾 GUARDAR CONFIGURACIÓN
                </button>
            </form>
        </div>
    </main>

    <!-- Modal Persona de Riesgo -->
    <div id="modalRiesgo" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-gray-800 rounded-lg border border-gray-700 p-6 w-full max-w-md">
            <h3 class="text-xl font-bold text-white mb-4">Agregar Persona de Riesgo</h3>
            <form method="POST">
                <input type="hidden" name="accion" value="agregar_persona_riesgo">
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Nombre *</label>
                            <input type="text" name="nombre" required
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Apellido</label>
                            <input type="text" name="apellido"
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                        </div>
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Alias</label>
                        <input type="text" name="apellido"
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600"
                               placeholder="Ej: 'El Gordo', 'Manos Rápidas'">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Tipo de Riesgo</label>
                            <select name="tipo_riesgo" class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                                <option value="ALTO">🔴 ALTO</option>
                                <option value="MEDIO">🟡 MEDIO</option>
                                <option value="BAJO">🟠 BAJO</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Nivel de Peligro (1-10)</label>
                            <input type="number" name="nivel_peligro" value="3" min="1" max="10"
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                        </div>
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Descripción del Riesgo</label>
                        <textarea name="descripcion" rows="2"
                                  class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600"
                                  placeholder="Describe el tipo de riesgo que representa"></textarea>
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Modus Operandi</label>
                        <textarea name="modus_operandi" rows="2"
                                  class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600"
                                  placeholder="Método habitual de operación"></textarea>
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Foto (URL o ruta)</label>
                        <input type="text" name="foto"
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600"
                               placeholder="Ruta a la foto para reconocimiento facial">
                    </div>
                </div>
                <div class="flex gap-4 mt-6">
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded font-bold flex-1">
                        💾 GUARDAR
                    </button>
                    <button type="button" onclick="cerrarModalRiesgo()" class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded font-bold flex-1">
                        ❌ CANCELAR
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showTab(tab) {
            const panels = ['panelRiesgo', 'panelClientes', 'panelDetecciones', 'panelAlertas', 'panelConfig'];
            const tabs = ['tabRiesgo', 'tabClientes', 'tabDetecciones', 'tabAlertas', 'tabConfig'];
            const colors = {
                'riesgo': 'bg-red-600',
                'clientes': 'bg-green-600',
                'detecciones': 'bg-blue-600',
                'alertas': 'bg-yellow-600',
                'config': 'bg-purple-600'
            };

            // Ocultar todos los paneles
            panels.forEach(p => {
                const panel = document.getElementById(p);
                if (panel) panel.classList.add('hidden');
            });

            // Resetear todos los botones a gris
            tabs.forEach(t => {
                const tabBtn = document.getElementById(t);
                if (tabBtn) {
                    tabBtn.classList.remove('bg-red-600', 'bg-green-600', 'bg-blue-600', 'bg-yellow-600', 'bg-purple-600');
                    tabBtn.classList.add('bg-gray-700');
                }
            });

            // Mostrar panel y activar botón seleccionado
            const panel = document.getElementById('panel' + tab.charAt(0).toUpperCase() + tab.slice(1));
            const tabBtn = document.getElementById('tab' + tab.charAt(0).toUpperCase() + tab.slice(1));
            if (panel) panel.classList.remove('hidden');
            if (tabBtn) {
                tabBtn.classList.remove('bg-gray-700');
                tabBtn.classList.add(colors[tab]);
            }
        }

        function mostrarFormularioRiesgo() {
            document.getElementById('modalRiesgo').classList.remove('hidden');
        }

        function cerrarModalRiesgo() {
            document.getElementById('modalRiesgo').classList.add('hidden');
        }

        function mostrarFormularioCliente() {
            alert('Formulario de registro de cliente en desarrollo');
        }

        function marcarAlertaMostrada() {
            // Marcar alerta como mostrada
            <?php if ($alerta_pendiente): ?>
            fetch('reconocimiento_facial.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'accion=marcar_alerta_mostrada'
            }).then(() => {
                document.getElementById('alertaCritica').classList.add('hidden');
            });
            <?php endif; ?>
        }

        // Cerrar modales al hacer clic fuera
        document.getElementById('modalRiesgo').addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarModalRiesgo();
            }
        });

        // Auto-refresh alertas cada 30 segundos
        setInterval(() => {
            if (document.getElementById('panelAlertas').classList.contains('hidden') === false) {
                location.reload();
            }
        }, 30000);
    </script>
</body>
</html>
