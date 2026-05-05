<?php
require_once 'config.php';
requireLogin();

$user = getUser();
$empresa_id = $user['empresa_id'];

// Validar rol para acciones de cámaras
function esAdministrador() {
    global $user;
    return isset($user['rol']) && $user['rol'] === 'administrador';
}

require_once 'lib/CameraService.php';
$camera_service = new CameraService($empresa_id);

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'agregar_camara') {
        if (!esAdministrador()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Acceso denegado. Solo administradores pueden agregar cámaras.']);
            exit;
        }
        
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
        if (!esAdministrador()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Acceso denegado. Solo administradores pueden modificar cámaras.']);
            exit;
        }
        
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
        if (!esAdministrador()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Acceso denegado. Solo administradores pueden eliminar cámaras.']);
            exit;
        }
        
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
    
    if ($accion === 'iniciar_stream') {
        $camara_id = intval($_POST['camara_id'] ?? 0);
        
        // Obtener datos de la cámara
        $camara = fetch("SELECT * FROM camaras WHERE id = ? AND empresa_id = ?", [$camara_id, $empresa_id]);
        if (!$camara) {
            echo json_encode(['success' => false, 'error' => 'Cámara no encontrada']);
            exit;
        }
        
        // Iniciar stream HLS
        require_once 'lib/RTSPStreamService.php';
        $stream_service = new RTSPStreamService($empresa_id);
        
        try {
            $stream_info = $stream_service->generateWebRTCStream($camara);
            echo json_encode(['success' => true, 'stream_info' => $stream_info]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($accion === 'obtener_camara') {
        $camara_id = intval($_POST['id'] ?? 0);
        
        // Obtener datos de la cámara
        $camara = fetch("SELECT * FROM camaras WHERE id = ? AND empresa_id = ?", [$camara_id, $empresa_id]);
        if (!$camara) {
            echo json_encode(['success' => false, 'error' => 'Cámara no encontrada']);
            exit;
        }
        
        // Construir URL completa para mostrar
        $url = '';
        if ($camara['tipo'] === 'RTSP') {
            $url = 'rtsp://';
            if (!empty($camara['usuario'])) {
                $url .= $camara['usuario'];
                if (!empty($camara['password'])) {
                    $url .= ':' . $camara['password'];
                }
                $url .= '@';
            }
            $url .= $camara['ip'] . ':' . $camara['puerto'] . $camara['ruta_stream'];
        } else {
            $url = 'http://' . $camara['ip'] . ':' . $camara['puerto'] . $camara['ruta_stream'];
        }
        
        $camara['url_completa'] = $url;
        
        echo json_encode(['success' => true, 'camara' => $camara]);
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
    <title>📹 Cámaras de Vigilancia - WARP POS</title>
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
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
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
        </div>

        <!-- Vista de Cámaras -->
        <div class="bg-gray-800 rounded-lg border border-gray-700 p-6 mb-8">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-white">📹 Vista de Cámaras</h2>
                <?php if (esAdministrador()): ?>
                    <div class="flex gap-2">
                        <button onclick="escanearRed()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm">
                            🔍 ESCANEAR RED
                        </button>
                        <button onclick="mostrarFormularioCamara()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded text-sm">
                            ➕ AGREGAR CÁMARA
                        </button>
                    </div>
                <?php endif; ?>
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
                                <!-- Video en vivo -->
                                <?php if ($camara['activo']): ?>
                                    <div class="relative w-full h-full">
                                        <!-- Reproductor HLS para RTSP y HTTP -->
                                        <video id="video_<?= $camara['id'] ?>" class="w-full h-full object-cover" controls autoplay muted playsinline>
                                            <source src="stream.php?key=<?= 'stream_' . $_SESSION['empresa_id'] . '_' . $camara['id'] ?>" type="application/vnd.apple.mpegurl">
                                            Tu navegador no soporta video HLS.
                                        </video>
                                        
                                        <!-- Botón de iniciar stream -->
                                        <div id="loading_<?= $camara['id'] ?>" class="absolute inset-0 flex items-center justify-center bg-gray-900">
                                            <div class="text-center">
                                                <div class="text-4xl mb-2">📹</div>
                                                <p class="text-white font-bold"><?= htmlspecialchars($camara['nombre']) ?></p>
                                                <p class="text-gray-400 text-sm"><?= htmlspecialchars($camara['ip']) ?></p>
                                                <button onclick="iniciarStream(<?= $camara['id'] ?>)" 
                                                        class="mt-2 bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-xs">
                                                    ▶️ Iniciar Stream
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <!-- Estado del stream -->
                                        <div id="status_<?= $camara['id'] ?>" class="absolute top-8 left-2 hidden">
                                            <span class="bg-green-600 text-white text-xs px-2 py-1 rounded">
                                                🟢 Streaming
                                            </span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <!-- Cámara inactiva -->
                                    <div class="absolute inset-0 flex items-center justify-center bg-gray-900">
                                        <div class="text-center">
                                            <div class="text-4xl mb-2">📹</div>
                                            <p class="text-white font-bold"><?= htmlspecialchars($camara['nombre']) ?></p>
                                            <p class="text-gray-400 text-sm">Cámara Inactiva</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Controles de visualización -->
                                <div class="absolute top-2 right-2 flex gap-1">
                                    <button onclick="probarConexion(<?= $camara['id'] ?>)" class="bg-green-600 hover:bg-green-700 text-white p-1 rounded text-xs">
                                        🔗
                                    </button>
                                    <button onclick="grabarManual(<?= $camara['id'] ?>)" class="bg-red-600 hover:bg-red-700 text-white p-1 rounded text-xs">
                                        🔴
                                    </button>
                                </div>
                                <!-- Estado -->
                                <div class="absolute top-2 left-2">
                                    <?php if ($camara['activo']): ?>
                                    <span class="bg-green-600 text-white text-xs px-2 py-1 rounded">
                                        🟢 Activa
                                    </span>
                                    <?php else: ?>
                                    <span class="bg-red-600 text-white text-xs px-2 py-1 rounded">
                                        🔴 Inactiva
                                    </span>
                                    <?php endif; ?>
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
            <form id="camaraForm" method="POST" onsubmit="return procesarFormularioCamara(event)">
                <input type="hidden" name="accion" id="formAccion" value="agregar_camara">
                <input type="hidden" name="id" id="camaraId" value="">
                
                <!-- Campos ocultos que se llenan automáticamente desde la URL -->
                <input type="hidden" name="ip" id="ip">
                <input type="hidden" name="puerto" id="puerto">
                <input type="hidden" name="tipo" id="tipo">
                <input type="hidden" name="usuario" id="usuario">
                <input type="hidden" name="password" id="password">
                <input type="hidden" name="ruta_stream" id="ruta_stream">
                
                <div class="space-y-4">
                    <!-- URL Completa - El único campo obligatorio -->
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">URL de la Cámara *</label>
                        <input type="text" id="url_camara" required
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600"
                               placeholder="rtsp://admin:password@192.168.1.100:554/stream1"
                               onchange="parsearUrlCamara()">
                        <p class="text-gray-500 text-xs mt-1">
                            Ej: rtsp://user:pass@ip:puerto/ruta o http://ip:puerto/video
                        </p>
                    </div>
                    
                    <!-- Vista previa de datos parseados -->
                    <div id="previewDatos" class="hidden bg-gray-900 rounded p-3">
                        <p class="text-gray-400 text-xs mb-2">Datos detectados:</p>
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <span class="text-gray-500">IP:</span> <span class="text-green-400" id="previewIp"></span>
                            <span class="text-gray-500">Puerto:</span> <span class="text-green-400" id="previewPuerto"></span>
                            <span class="text-gray-500">Tipo:</span> <span class="text-green-400" id="previewTipo"></span>
                            <span class="text-gray-500">Usuario:</span> <span class="text-green-400" id="previewUsuario"></span>
                            <span class="text-gray-500">Ruta:</span> <span class="text-green-400" id="previewRuta"></span>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Nombre (opcional)</label>
                        <input type="text" name="nombre" id="nombre"
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600"
                               placeholder="Ej: Cámara Entrada Principal">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Marca (opcional)</label>
                            <input type="text" name="marca" id="marca"
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600"
                                   placeholder="Hikvision">
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Modelo (opcional)</label>
                            <input type="text" name="modelo" id="modelo"
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600"
                                   placeholder="DS-2CD2043G0">
                        </div>
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
            document.getElementById('previewDatos').classList.add('hidden');
            document.getElementById('modalCamara').classList.remove('hidden');
        }
        
        function parsearUrlCamara() {
            const urlInput = document.getElementById('url_camara').value.trim();
            if (!urlInput) return;
            
            try {
                let url = urlInput;
                // Si no tiene protocolo, agregar rtsp:// por defecto
                if (!url.match(/^https?:\/\//) && !url.match(/^rtsp:\/\//)) {
                    url = 'rtsp://' + url;
                }
                
                const urlObj = new URL(url);
                const protocolo = urlObj.protocol.replace(':', '').toUpperCase();
                
                // Extraer componentes
                const tipo = protocolo === 'RTSP' ? 'RTSP' : 'HTTP';
                const ip = urlObj.hostname;
                const puerto = urlObj.port || (protocolo === 'RTSP' ? 554 : 80);
                const usuario = urlObj.username || '';
                const password = urlObj.password || '';
                const ruta = urlObj.pathname + urlObj.search || '/stream1';
                
                // Llenar campos ocultos
                document.getElementById('ip').value = ip;
                document.getElementById('puerto').value = puerto;
                document.getElementById('tipo').value = tipo;
                document.getElementById('usuario').value = usuario;
                document.getElementById('password').value = password;
                document.getElementById('ruta_stream').value = ruta;
                
                // Mostrar preview
                document.getElementById('previewIp').textContent = ip;
                document.getElementById('previewPuerto').textContent = puerto;
                document.getElementById('previewTipo').textContent = tipo;
                document.getElementById('previewUsuario').textContent = usuario || '(sin usuario)';
                document.getElementById('previewRuta').textContent = ruta;
                document.getElementById('previewDatos').classList.remove('hidden');
                
                // Auto-llenar nombre si está vacío
                const nombreInput = document.getElementById('nombre');
                if (!nombreInput.value) {
                    nombreInput.value = `Cámara ${ip}:${puerto}`;
                }
                
            } catch (e) {
                console.error('Error parseando URL:', e);
                document.getElementById('previewDatos').classList.add('hidden');
            }
        }
        
        function procesarFormularioCamara(event) {
            // Validar que se haya parseado la URL
            if (!document.getElementById('ip').value) {
                alert('❌ Ingresá una URL válida de cámara');
                event.preventDefault();
                return false;
            }
            return true;
        }
        
        function cerrarModal() {
            document.getElementById('modalCamara').classList.add('hidden');
        }
        
        function eliminarCamara(id, nombre) {
            if (confirm(`¿Estás seguro que querés eliminar la cámara "${nombre}"?\n\nEsta acción no se puede deshacer.`)) {
                fetch('camaras.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'accion=eliminar_camara&id=' + id
                })
                .then(response => {
                    if (response.ok) {
                        window.location.reload();
                    } else {
                        alert('❌ Error al eliminar la cámara');
                    }
                })
                .catch(error => {
                    alert('❌ Error: ' + error.message);
                });
            }
        }
        
        function iniciarStream(camara_id) {
            const loadingDiv = document.getElementById(`loading_${camara_id}`);
            const video = document.getElementById(`video_${camara_id}`);
            const statusDiv = document.getElementById(`status_${camara_id}`);
            
            // Mostrar estado de carga
            loadingDiv.innerHTML = `
                <div class="text-center">
                    <div class="animate-spin text-4xl mb-2">📹</div>
                    <p class="text-white font-bold">Iniciando Stream...</p>
                    <p class="text-gray-400 text-sm">Esto puede tardar unos segundos</p>
                </div>
            `;
            
            // Iniciar el stream en el servidor
            fetch('camaras.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `accion=iniciar_stream&camara_id=${camara_id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Esperar un momento y luego mostrar el video
                    setTimeout(() => {
                        loadingDiv.classList.add('hidden');
                        video.classList.remove('hidden');
                        statusDiv.classList.remove('hidden');
                        
                        // Intentar cargar el video
                        video.load();
                        
                        // Manejar errores de carga
                        video.onerror = function() {
                            console.error('Error cargando video HLS');
                            loadingDiv.classList.remove('hidden');
                            loadingDiv.innerHTML = `
                                <div class="text-center">
                                    <div class="text-4xl mb-2">❌</div>
                                    <p class="text-white font-bold">Error de Stream</p>
                                    <p class="text-gray-400 text-sm">Reintentando...</p>
                                    <button onclick="iniciarStream(${camara_id})" 
                                            class="mt-2 bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-xs">
                                        🔄 Reintentar
                                    </button>
                                </div>
                            `;
                            statusDiv.classList.add('hidden');
                        };
                        
                        video.oncanplay = function() {
                            console.log('Stream cargado exitosamente');
                        };
                    }, 3000); // Esperar 3 segundos a que FFmpeg genere los segmentos
                } else {
                    throw new Error(data.error || 'No se pudo iniciar el stream');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                loadingDiv.innerHTML = `
                    <div class="text-center">
                        <div class="text-4xl mb-2">❌</div>
                        <p class="text-white font-bold">Error al Iniciar Stream</p>
                        <p class="text-gray-400 text-sm">${error.message}</p>
                        <button onclick="iniciarStream(${camara_id})" 
                                class="mt-2 bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-xs">
                            🔄 Reintentar
                        </button>
                    </div>
                `;
            });
        }
        
        function editarCamara(id) {
            // Cargar datos reales de la cámara
            fetch('camaras.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'accion=obtener_camara&id=' + id
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const camara = data.camara;
                    
                    // Configurar el modal para edición
                    document.getElementById('modalTitle').textContent = 'Editar Cámara';
                    document.getElementById('formAccion').value = 'actualizar_camara';
                    document.getElementById('camaraId').value = camara.id;
                    
                    // Llenar el formulario con datos reales
                    document.getElementById('url_camara').value = camara.url_completa;
                    document.getElementById('nombre').value = camara.nombre || '';
                    document.getElementById('marca').value = camara.marca || '';
                    document.getElementById('modelo').value = camara.modelo || '';
                    document.getElementById('activo').checked = camara.activo == 1;
                    
                    // Parsear la URL para llenar campos ocultos
                    parsearUrlCamara();
                    
                    // Mostrar el modal
                    document.getElementById('modalCamara').classList.remove('hidden');
                } else {
                    alert('❌ Error: ' + (data.error || 'No se pudo cargar la cámara'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('❌ Error al cargar los datos de la cámara');
            });
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
