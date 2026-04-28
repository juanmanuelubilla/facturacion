<?php
require_once 'config.php';
requireLogin();

$user = getUser();
$empresa_id = $user['empresa_id'];

require_once 'lib/CameraService.php';
$camera_service = new CameraService($empresa_id);

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'agregar_camara') {
        $datos = [
            'nombre' => trim($_POST['nombre'] ?? ''),
            'ip' => trim($_POST['ip'] ?? ''),
            'puerto' => intval($_POST['puerto'] ?? 554),
            'usuario' => trim($_POST['usuario'] ?? ''),
            'password' => trim($_POST['password'] ?? ''),
            'tipo' => trim($_POST['tipo'] ?? 'RTSP'),
            'marca' => trim($_POST['marca'] ?? ''),
            'modelo' => trim($_POST['modelo'] ?? ''),
            'ruta_stream' => trim($_POST['ruta_stream'] ?? '')
        ];
        
        $camera_service->agregarCamara($datos);
        header('Location: camaras.php');
        exit;
    }
    
    if ($accion === 'actualizar_camara') {
        $id = intval($_POST['id'] ?? 0);
        $datos = [
            'nombre' => trim($_POST['nombre'] ?? ''),
            'ip' => trim($_POST['ip'] ?? ''),
            'puerto' => intval($_POST['puerto'] ?? 554),
            'usuario' => trim($_POST['usuario'] ?? ''),
            'password' => trim($_POST['password'] ?? ''),
            'tipo' => trim($_POST['tipo'] ?? 'RTSP'),
            'marca' => trim($_POST['marca'] ?? ''),
            'modelo' => trim($_POST['modelo'] ?? ''),
            'ruta_stream' => trim($_POST['ruta_stream'] ?? ''),
            'activo' => isset($_POST['activo']) ? 1 : 0
        ];
        
        $camera_service->actualizarCamara($id, $datos);
        header('Location: camaras.php');
        exit;
    }
    
    if ($accion === 'eliminar_camara') {
        $id = intval($_POST['id'] ?? 0);
        $camera_service->eliminarCamara($id);
        header('Location: camaras.php');
        exit;
    }
    
    if ($accion === 'probar_conexion') {
        $id = intval($_POST['id'] ?? 0);
        $resultado = $camera_service->probarConexion($id);
        header('Content-Type: application/json');
        echo json_encode($resultado);
        exit;
    }
    
    if ($accion === 'escanear_red') {
        $resultado = $camera_service->escanearRed();
        header('Content-Type: application/json');
        echo json_encode($resultado);
        exit;
    }
    
    if ($accion === 'grabar_evento') {
        $camara_id = intval($_POST['camara_id'] ?? 0);
        $tipo_evento = trim($_POST['tipo_evento'] ?? 'manual');
        $venta_id = intval($_POST['venta_id'] ?? null);
        
        $resultado = $camera_service->grabarEvento($camara_id, $tipo_evento, $venta_id);
        header('Content-Type: application/json');
        echo json_encode(['success' => $resultado]);
        exit;
    }
}

$camaras = $camera_service->getCamaras();
$estadisticas = $camera_service->getEstadisticas();
$eventos_recientes = $camera_service->getEventosRecientes(10);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📹 Cámaras de Vigilancia - NEXUS POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://vjs.zencdn.net/8.6.1/video-js.css" rel="stylesheet">
    <script src="https://vjs.zencdn.net/8.6.1/video.min.js"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-900 to-gray-800 text-gray-100">
    <header class="bg-gray-900 shadow-lg">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold text-blue-400">📹 CÁMARAS DE VIGILANCIA</h1>
            <a href="dashboard.php" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded">VOLVER</a>
        </div>
    </header>
    
    <main class="container mx-auto px-6 py-8">
        <!-- Estadísticas -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Total Cámaras</p>
                        <p class="text-2xl font-bold text-white"><?= $estadisticas['total_camaras'] ?></p>
                    </div>
                    <div class="text-3xl">📹</div>
                </div>
            </div>
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Activas</p>
                        <p class="text-2xl font-bold text-green-400"><?= $estadisticas['camaras_activas'] ?></p>
                    </div>
                    <div class="text-3xl">🟢</div>
                </div>
            </div>
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Eventos Hoy</p>
                        <p class="text-2xl font-bold text-yellow-400"><?= $estadisticas['eventos_hoy'] ?></p>
                    </div>
                    <div class="text-3xl">📋</div>
                </div>
            </div>
            <div class="bg-gray-800 rounded-lg border border-gray-700 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Almacenamiento</p>
                        <p class="text-2xl font-bold text-blue-400"><?= $estadisticas['espacio_usado'] ?></p>
                    </div>
                    <div class="text-3xl">💾</div>
                </div>
            </div>
        </div>

        <!-- Vista de Cámaras -->
        <div class="bg-gray-800 rounded-lg border border-gray-700 p-6 mb-8">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-white">📹 Vista de Cámaras</h2>
                <div class="flex gap-2">
                    <button onclick="escanearRed()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm">
                        🔍 ESCANEAR RED
                    </button>
                    <button onclick="mostrarFormularioCamara()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded text-sm">
                        ➕ AGREGAR CÁMARA
                    </button>
                </div>
            </div>
            
            <!-- Grid de Cámaras -->
            <div id="cameraGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <?php if (empty($camaras)): ?>
                    <div class="col-span-full text-center py-12 text-gray-400">
                        <div class="text-6xl mb-4">📹</div>
                        <p class="text-xl">No hay cámaras configuradas</p>
                        <p class="text-sm mt-2">Agrega tu primera cámara para comenzar</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($camaras as $camara): ?>
                        <div class="bg-gray-700 rounded-lg border border-gray-600 overflow-hidden">
                            <div class="relative aspect-video bg-black">
                                <!-- Placeholder de video -->
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <div class="text-center">
                                        <div class="text-4xl mb-2">📹</div>
                                        <p class="text-white font-bold"><?= htmlspecialchars($camara['nombre']) ?></p>
                                        <p class="text-gray-400 text-sm"><?= htmlspecialchars($camara['ip']) ?></p>
                                    </div>
                                </div>
                                <!-- Controles -->
                                <div class="absolute top-2 right-2 flex gap-1">
                                    <button onclick="probarConexion(<?= $camara['id'] ?>)" class="bg-green-600 hover:bg-green-700 text-white p-1 rounded text-xs">
                                        🔗
                                    </button>
                                    <button onclick="grabarManual(<?= $camara['id'] ?>)" class="bg-red-600 hover:bg-red-700 text-white p-1 rounded text-xs">
                                        🔴
                                    </button>
                                    <button onclick="editarCamara(<?= $camara['id'] ?>)" class="bg-blue-600 hover:bg-blue-700 text-white p-1 rounded text-xs">
                                        ✏️
                                    </button>
                                </div>
                                <!-- Estado -->
                                <div class="absolute top-2 left-2">
                                    <span class="bg-green-600 text-white text-xs px-2 py-1 rounded">
                                        🟢 Activa
                                    </span>
                                </div>
                            </div>
                            <div class="p-3">
                                <h3 class="font-bold text-white"><?= htmlspecialchars($camara['nombre']) ?></h3>
                                <p class="text-gray-400 text-sm"><?= htmlspecialchars($camara['marca'] . ' ' . $camara['modelo']) ?></p>
                                <p class="text-gray-500 text-xs"><?= htmlspecialchars($camara['ip']) ?>:<?= $camara['puerto'] ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Eventos Recientes -->
        <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
            <h2 class="text-xl font-bold text-white mb-4">📋 Eventos Recientes</h2>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-700">
                            <th class="text-left py-2 px-3 text-gray-400">Fecha/Hora</th>
                            <th class="text-left py-2 px-3 text-gray-400">Cámara</th>
                            <th class="text-left py-2 px-3 text-gray-400">Tipo</th>
                            <th class="text-left py-2 px-3 text-gray-400">Venta</th>
                            <th class="text-left py-2 px-3 text-gray-400">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($eventos_recientes)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-gray-400">
                                    No hay eventos registrados
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($eventos_recientes as $evento): ?>
                                <tr class="border-b border-gray-700">
                                    <td class="py-2 px-3 text-sm">
                                        <?= date('d/m H:i', strtotime($evento['fecha'])) ?>
                                    </td>
                                    <td class="py-2 px-3 text-sm">
                                        <?= htmlspecialchars($evento['camara_nombre']) ?>
                                    </td>
                                    <td class="py-2 px-3 text-sm">
                                        <span class="px-2 py-1 rounded text-xs 
                                            <?= $evento['tipo_evento'] === 'venta' ? 'bg-green-900 text-green-300' : 
                                               ($evento['tipo_evento'] === 'movimiento' ? 'bg-yellow-900 text-yellow-300' : 'bg-blue-900 text-blue-300') ?>">
                                            <?= ucfirst($evento['tipo_evento']) ?>
                                        </span>
                                    </td>
                                    <td class="py-2 px-3 text-sm">
                                        <?= $evento['venta_id'] ? '#' . $evento['venta_id'] : '-' ?>
                                    </td>
                                    <td class="py-2 px-3 text-sm">
                                        <button class="bg-blue-600 hover:bg-blue-700 text-white px-2 py-1 rounded text-xs">
                                            � Conectar
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal Formulario Cámara -->
    <div id="modalCamara" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-gray-800 rounded-lg border border-gray-700 p-6 w-full max-w-md">
            <h3 class="text-xl font-bold text-white mb-4" id="modalTitle">Nueva Cámara</h3>
            <form id="camaraForm" method="POST">
                <input type="hidden" name="accion" id="formAccion" value="agregar_camara">
                <input type="hidden" name="id" id="camaraId" value="">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Nombre</label>
                        <input type="text" name="nombre" id="nombre" required
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600"
                               placeholder="Ej: Caja Principal">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Dirección IP</label>
                        <input type="text" name="ip" id="ip" required
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600"
                               placeholder="192.168.1.100">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Puerto</label>
                            <input type="number" name="puerto" id="puerto" value="554"
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Tipo</label>
                            <select name="tipo" id="tipo" class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                                <option value="RTSP">RTSP</option>
                                <option value="HTTP">HTTP</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Usuario</label>
                            <input type="text" name="usuario" id="usuario"
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Contraseña</label>
                            <input type="password" name="password" id="password"
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Marca</label>
                            <input type="text" name="marca" id="marca"
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600"
                                   placeholder="Dahua, Hikvision...">
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Modelo</label>
                            <input type="text" name="modelo" id="modelo"
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                        </div>
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Ruta Stream (opcional)</label>
                        <input type="text" name="ruta_stream" id="ruta_stream"
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600"
                               placeholder="/stream1">
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" name="activo" id="activo" checked class="mr-2">
                        <label for="activo" class="text-white">Cámara activa</label>
                    </div>
                </div>
                
                <div class="flex gap-4 mt-6">
                    <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded font-bold flex-1">
                        💾 GUARDAR
                    </button>
                    <button type="button" onclick="cerrarModal()" class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded font-bold flex-1">
                        ❌ CANCELAR
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function mostrarFormularioCamara() {
            document.getElementById('modalTitle').textContent = 'Nueva Cámara';
            document.getElementById('formAccion').value = 'agregar_camara';
            document.getElementById('camaraForm').reset();
            document.getElementById('modalCamara').classList.remove('hidden');
        }
        
        function cerrarModal() {
            document.getElementById('modalCamara').classList.add('hidden');
        }
        
        function editarCamara(id) {
            // Cargar datos de la cámara (simulado)
            document.getElementById('modalTitle').textContent = 'Editar Cámara';
            document.getElementById('formAccion').value = 'actualizar_camara';
            document.getElementById('camaraId').value = id;
            
            // Aquí cargarías los datos reales de la cámara
            document.getElementById('nombre').value = 'Cámara ' + id;
            document.getElementById('ip').value = '192.168.1.' + (100 + id);
            document.getElementById('modalCamara').classList.remove('hidden');
        }
        
        function probarConexion(camara_id) {
            fetch('camaras.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'accion=probar_conexion&id=' + camara_id
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.message);
                } else {
                    alert('❌ ' + data.error);
                }
            })
            .catch(error => {
                alert('❌ Error: ' + error.message);
            });
        }
        
        function grabarManual(camara_id) {
            if (confirm('¿Iniciar grabación manual por 30 segundos?')) {
                fetch('camaras.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'accion=grabar_evento&camara_id=' + camara_id + '&tipo_evento=manual'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('📹 Grabación iniciada');
                    } else {
                        alert('❌ Error al iniciar grabación');
                    }
                });
            }
        }
        
        function escanearRed() {
            const btn = event.target;
            btn.disabled = true;
            btn.textContent = '🔍 Escaneando...';
            
            fetch('camaras.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'accion=escanear_red'
            })
            .then(response => response.json())
            .then(data => {
                if (data.length > 0) {
                    alert('🎯 Se encontraron ' + data.length + ' cámaras:\n' + 
                          data.map(c => '• ' + c.ip + ' - ' + c.marca + ' ' + c.modelo).join('\n'));
                } else {
                    alert('🔍 No se encontraron cámaras en la red');
                }
            })
            .catch(error => {
                alert('❌ Error: ' + error.message);
            })
            .finally(() => {
                btn.disabled = false;
                btn.textContent = '🔍 Escanear Red';
            });
        }
        
        // Cerrar modal al hacer clic fuera
        document.getElementById('modalCamara').addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarModal();
            }
        });
    </script>
</body>
</html>
