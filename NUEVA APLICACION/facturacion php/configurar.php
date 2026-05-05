<?php
require_once 'config.php';
requireLogin();

$user = getUser();
$empresa_id = $user['empresa_id'];

// API endpoint para probar conexión SFTP (GET)
if (isset($_GET['accion_dlna']) && $_GET['accion_dlna'] === 'probar_sftp') {
    error_log("DEBUG: Endpoint SFTP llamado - empresa_id: $empresa_id");
    try {
        require_once 'lib/SFTPService.php';
        $sftp_service = new SFTPService($empresa_id);
        $resultado = $sftp_service->probarConexion();
        error_log("DEBUG: Resultado SFTP: " . json_encode($resultado));
        header('Content-Type: application/json');
        echo json_encode($resultado);
        exit;
    } catch (Exception $e) {
        error_log("DEBUG: Error en endpoint SFTP: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// API endpoint para escanear red de cámaras (GET)
if (isset($_GET['accion']) && $_GET['accion'] === 'camara_escanear_red') {
    require_once 'lib/CameraService.php';
    $camera_service = new CameraService($empresa_id);
    $resultado = $camera_service->escanearRed();
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'camaras' => $resultado]);
    exit;
}

// API endpoint para agregar cámara (POST JSON)
if (isset($_GET['accion']) && $_GET['accion'] === 'camara_agregar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("DEBUG: camara_agagar endpoint llamado");
    
    $json = file_get_contents('php://input');
    error_log("DEBUG: JSON recibido: " . $json);
    
    $datos = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("DEBUG: Error JSON: " . json_last_error_msg());
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'JSON inválido: ' . json_last_error_msg()]);
        exit;
    }
    
    error_log("DEBUG: datos parseados: " . json_encode($datos));
    
    require_once 'lib/CameraService.php';
    $camera_service = new CameraService($empresa_id);
    
    $resultado = $camera_service->agregarCamara($datos);
    error_log("DEBUG: resultado de agregar: " . json_encode($resultado));
    
    // Asegurar que la respuesta sea correcta
    $response = $resultado ? ['success' => true] : ['success' => false, 'error' => 'No se pudo agregar la cámara'];
    
    error_log("DEBUG: respuesta final: " . json_encode($response));
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// API endpoint para listar cámaras (GET)
if (isset($_GET['accion']) && $_GET['accion'] === 'camara_listar') {
    // Listar cámaras es público (solo lectura)
    require_once 'lib/CameraService.php';
    $camera_service = new CameraService($empresa_id);
    $camaras = $camera_service->getCamaras();
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'camaras' => $camaras]);
    exit;
}

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("DEBUG: POST request received - Data: " . json_encode($_POST));
    $tab = $_POST['tab'] ?? '';
    
    // Si es una prueba de conexión de email
    if (isset($_POST['accion']) && $_POST['accion'] === 'probar_email') {
        require_once 'lib/EmailService.php';
        $email_service = new EmailService($empresa_id);
        $resultado = $email_service->probarConexion();
        header('Content-Type: application/json');
        echo json_encode($resultado);
        exit;
    }
    
    // Endpoint de prueba para debug
    if (isset($_POST['accion']) && $_POST['accion'] === 'test_post') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'POST test successful',
            'post_data' => $_POST,
            'empresa_id' => $empresa_id
        ]);
        exit;
    }
    
    // API endpoints para cámaras
    if (isset($_POST['accion']) && $_POST['accion'] === 'obtener_camara') {
        error_log("DEBUG: obtener_camara endpoint llamado - ID: " . ($_POST['id'] ?? 'null') . " - Empresa: $empresa_id");
        
        $camara_id = intval($_POST['id'] ?? 0);
        
        if ($camara_id === 0) {
            error_log("DEBUG: ID de cámara inválido: $camara_id");
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'ID de cámara inválido']);
            exit;
        }
        
        // Obtener datos de la cámara
        $camara = fetch("SELECT * FROM camaras WHERE id = ? AND empresa_id = ?", [$camara_id, $empresa_id]);
        if (!$camara) {
            error_log("DEBUG: Cámara no encontrada - ID: $camara_id, Empresa: $empresa_id");
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Cámara no encontrada']);
            exit;
        }
        
        error_log("DEBUG: Cámara encontrada: " . json_encode($camara));
        
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
        
        error_log("DEBUG: Enviando respuesta - URL: $url");
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'camara' => $camara]);
        exit;
    }
    
    if (isset($_POST['accion']) && $_POST['accion'] === 'eliminar_camara') {
        $camara_id = intval($_POST['id'] ?? 0);
        
        // Verificar que la cámara exista y pertenezca a la empresa
        $camara = fetch("SELECT id FROM camaras WHERE id = ? AND empresa_id = ?", [$camara_id, $empresa_id]);
        if (!$camara) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Cámara no encontrada']);
            exit;
        }
        
        // Eliminar la cámara
        require_once 'lib/CameraService.php';
        $camera_service = new CameraService($empresa_id);
        $resultado = $camera_service->eliminarCamara($camara_id);
        
        header('Content-Type: application/json');
        if ($resultado) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No se pudo eliminar la cámara']);
        }
        exit;
    }
    
    if (isset($_POST['accion']) && $_POST['accion'] === 'actualizar_camara') {
        error_log("DEBUG: actualizar_camara endpoint llamado - POST data: " . json_encode($_POST));
        
        $camara_id = intval($_POST['id'] ?? 0);
        error_log("DEBUG: cámara ID a actualizar: $camara_id");
        
        // Verificar que la cámara exista y pertenezca a la empresa
        $camara_existente = fetch("SELECT id FROM camaras WHERE id = ? AND empresa_id = ?", [$camara_id, $empresa_id]);
        if (!$camara_existente) {
            error_log("DEBUG: cámara no encontrada - ID: $camara_id, Empresa: $empresa_id");
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Cámara no encontrada']);
            exit;
        }
        
        error_log("DEBUG: cámara encontrada, procesando datos");
        
        // Procesar datos del formulario
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
        
        error_log("DEBUG: datos procesados: " . json_encode($datos));
        
        // Actualizar la cámara
        require_once 'lib/CameraService.php';
        $camera_service = new CameraService($empresa_id);
        $resultado = $camera_service->actualizarCamara($camara_id, $datos);
        
        error_log("DEBUG: resultado de actualización: " . ($resultado ? 'true' : 'false'));
        
        header('Content-Type: application/json');
        if ($resultado) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No se pudo actualizar la cámara']);
        }
        exit;
    }
    
    if ($tab === 'empresa') {
        $nombre_negocio = trim($_POST['nombre_negocio'] ?? '');
        $eslogan = trim($_POST['eslogan'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $condicion_iva = trim($_POST['condicion_iva'] ?? '');
        $impuesto = floatval($_POST['impuesto'] ?? 21);
        $ingresos_brutos = floatval($_POST['ingresos_brutos'] ?? 0);
        $ganancia_sugerida = floatval($_POST['ganancia_sugerida'] ?? 0);

        query("UPDATE nombre_negocio SET nombre_negocio=?, eslogan=?, direccion=?, condicion_iva=?, impuesto=?, ingresos_brutos=?, ganancia_sugerida=? WHERE empresa_id=?",
              [$nombre_negocio, $eslogan, $direccion, $condicion_iva, $impuesto, $ingresos_brutos, $ganancia_sugerida, $empresa_id]);
    }
    
    if ($tab === 'afip') {
        $afip_cuit = trim($_POST['afip_cuit'] ?? '');
        $afip_punto_venta = intval($_POST['afip_punto_venta'] ?? 0);
        $afip_certificado = trim($_POST['afip_certificado'] ?? '');
        $afip_clave = trim($_POST['afip_clave'] ?? '');
        $siempre_fiscal = isset($_POST['siempre_fiscal']) ? 1 : 0;
        $afip_prod = isset($_POST['afip_prod']) ? 1 : 0;
        $afip_mock = isset($_POST['afip_mock']) ? 1 : 0;

        query("UPDATE nombre_negocio SET afip_cuit=?, afip_punto_venta=?, afip_certificado=?, afip_clave=?, siempre_fiscal=?, afip_prod=?, afip_mock=? WHERE empresa_id=?",
              [$afip_cuit, $afip_punto_venta, $afip_certificado, $afip_clave, $siempre_fiscal, $afip_prod, $afip_mock, $empresa_id]);
    }
    
    if ($tab === 'ventas') {
        $moneda = trim($_POST['moneda'] ?? '$');
        $permitir_fraccion = isset($_POST['permitir_fraccion']) ? 1 : 0;

        query("UPDATE nombre_negocio SET moneda=?, permitir_fraccion=? WHERE empresa_id=?",
              [$moneda, $permitir_fraccion, $empresa_id]);
    }
    
    if ($tab === 'rutas') {
        // Las rutas se configuran automáticamente por empresa
        // No se permite edición manual
        // Si necesitas regenerar rutas, usa el botón de regenerar
        if (isset($_POST['regenerar_rutas'])) {
            $db = getDB();
            require_once 'lib/empresa_files.php';
            $empresa_files = new EmpresaFiles($empresa_id);
            $empresa_files->crearEstructura();
            $empresa_files->configurarRutasEnDB($db);
        }
    }
    
    if ($tab === 'pagos') {
        $mp_access_token = trim($_POST['mp_access_token'] ?? '');
        $mp_user_id = trim($_POST['mp_user_id'] ?? '');
        $mp_external_id = trim($_POST['mp_external_id'] ?? 'CAJA_01');
        $modo_api_key = trim($_POST['modo_api_key'] ?? '');
        $modo_sandbox = isset($_POST['modo_sandbox']) ? 1 : 0;
        $pw_api_key = trim($_POST['pw_api_key'] ?? '');
        $pw_merchant_id = trim($_POST['pw_merchant_id'] ?? '');

        query("UPDATE config_pagos SET mp_access_token=?, mp_user_id=?, mp_external_id=?, modo_api_key=?, modo_sandbox=?, pw_api_key=?, pw_merchant_id=? WHERE empresa_id=? OR id=1",
              [$mp_access_token, $mp_user_id, $mp_external_id, $modo_api_key, $modo_sandbox, $pw_api_key, $pw_merchant_id, $empresa_id]);
    }

    if ($tab === 'ia') {
        $ia_proveedor = trim($_POST['ia_proveedor'] ?? '');
        $ia_url = trim($_POST['ia_url'] ?? '');
        $ia_api_key = trim($_POST['ia_api_key'] ?? '');
        // ia_ruta_imagenes se configura automáticamente

        query("UPDATE nombre_negocio SET ia_proveedor=?, ia_url=?, ia_api_key=? WHERE empresa_id=?",
              [$ia_proveedor, $ia_url, $ia_api_key, $empresa_id]);
    }

    if ($tab === 'dlna') {
        // Procesar configuración DLNA
        if (isset($_POST['accion']) && $_POST['accion'] === 'guardar_dlna') {
            $dlna_tipo_servidor = trim($_POST['dlna_tipo_servidor'] ?? 'local');
            $dlna_ip_servidor = trim($_POST['dlna_ip_servidor'] ?? '192.168.1.100');
            $dlna_puerto_servidor = trim($_POST['dlna_puerto_servidor'] ?? '8200');
            // Las rutas DLNA se configuran automáticamente
            $dlna_activo = isset($_POST['dlna_activo']) ? 1 : 0;
            $dlna_auto_start = isset($_POST['dlna_auto_start']) ? 1 : 0;
            // Guardar credenciales SSH para control remoto
            $dlna_ssh_user = trim($_POST['dlna_ssh_user'] ?? 'root');
            $dlna_ssh_password = trim($_POST['dlna_ssh_password'] ?? '');
            
            // Guardar configuración SFTP
            $sftp_host = trim($_POST['sftp_host'] ?? (defined('SFTP_HOST') ? SFTP_HOST : '192.168.31.101'));
            $sftp_port = trim($_POST['sftp_port'] ?? (defined('SFTP_PORT') ? SFTP_PORT : '22'));
            $sftp_user = trim($_POST['sftp_user'] ?? (defined('SFTP_USER') ? SFTP_USER : 'pi'));
            $sftp_password = trim($_POST['sftp_password'] ?? (defined('SFTP_PASSWORD') ? SFTP_PASSWORD : 'juanmanuel'));
            $sftp_remote_path = trim($_POST['sftp_remote_path'] ?? (defined('SFTP_REMOTE_PATH') ? SFTP_REMOTE_PATH : '/mnt/R2/SD64GB/www/facturacion/html/banners/'));
            $sftp_enabled = isset($_POST['sftp_enabled']) ? 1 : 0;

            query("UPDATE nombre_negocio SET dlna_tipo_servidor=?, dlna_ip_servidor=?, dlna_puerto_servidor=?, dlna_activo=?, dlna_auto_start=?, dlna_ssh_user=?, dlna_ssh_password=?, sftp_host=?, sftp_port=?, sftp_user=?, sftp_password=?, sftp_remote_path=?, sftp_enabled=? WHERE empresa_id=?",
                  [$dlna_tipo_servidor, $dlna_ip_servidor, $dlna_puerto_servidor, $dlna_activo, $dlna_auto_start, $dlna_ssh_user, $dlna_ssh_password, $sftp_host, $sftp_port, $sftp_user, $sftp_password, $sftp_remote_path, $sftp_enabled, $empresa_id]);
        }
        
        // API endpoints para control DLNA
        if (isset($_GET['accion_dlna'])) {
            require_once 'lib/DLNAService.php';
            $dlna_service = new DLNAService($empresa_id);
            
            $accion = $_GET['accion_dlna'];
            $resultado = [];
            
            switch ($accion) {
                case 'estado':
                    $resultado = $dlna_service->verificarEstado();
                    break;
                    
                case 'iniciar':
                    $resultado = $dlna_service->iniciarServicio();
                    break;
                    
                case 'detener':
                    $resultado = $dlna_service->detenerServicio();
                    break;
                    
                case 'reiniciar':
                    $resultado = $dlna_service->reiniciarServicio();
                    break;
                    
                case 'verificar_carpetas':
                    $resultado = $dlna_service->verificarConfiguracionCarpetas();
                    break;
                    
                case 'probar_conexion':
                    $resultado = $dlna_service->probarConexion();
                    break;
            }
            
            header('Content-Type: application/json');
            echo json_encode($resultado);
            exit;
        }
    }

    if ($tab === 'whatsapp') {
        $whatsapp_sid = trim($_POST['whatsapp_sid'] ?? '');
        $whatsapp_api_key = trim($_POST['whatsapp_api_key'] ?? '');
        $whatsapp_api_secret = trim($_POST['whatsapp_api_secret'] ?? '');
        $whatsapp_phone = trim($_POST['whatsapp_phone'] ?? '');

        query("UPDATE nombre_negocio SET whatsapp_sid=?, whatsapp_api_key=?, whatsapp_api_secret=?, whatsapp_phone=? WHERE empresa_id=?",
              [$whatsapp_sid, $whatsapp_api_key, $whatsapp_api_secret, $whatsapp_phone, $empresa_id]);
    }

    // Guardar configuración de Tickets
    if ($tab === 'tickets') {
        $impresora_auto = isset($_POST['impresora_auto']) ? 1 : 0;
        $tipo_impresora = trim($_POST['tipo_impresora'] ?? 'auto');
        $impresora_ticket = trim($_POST['impresora_ticket'] ?? '');
        $impresora_ip = trim($_POST['impresora_ip'] ?? '');

        query("UPDATE nombre_negocio SET impresora_auto=?, tipo_impresora=?, impresora_ticket=?, impresora_ip=? WHERE empresa_id=?",
              [$impresora_auto, $tipo_impresora, $impresora_ticket, $impresora_ip, $empresa_id]);
    }

    // Guardar configuración de Email
    if ($tab === 'email') {
        $email_host = trim($_POST['email_host'] ?? '');
        $email_port = intval($_POST['email_port'] ?? 587);
        $email_username = trim($_POST['email_username'] ?? '');
        $email_password = trim($_POST['email_password'] ?? '');
        $email_encryption = trim($_POST['email_encryption'] ?? 'tls');
        $email_from_name = trim($_POST['email_from_name'] ?? '');
        $email_from_email = trim($_POST['email_from_email'] ?? '');

        query("UPDATE nombre_negocio SET email_host=?, email_port=?, email_username=?, email_password=?, 
                              email_encryption=?, email_from_name=?, email_from_email=? WHERE empresa_id=?",
              [$email_host, $email_port, $email_username, $email_password, $email_encryption, $email_from_name, $email_from_email, $empresa_id]);
    }

    // Guardar configuración de Inventario
    if ($tab === 'inventario') {
        $stock_bajo_entero = intval($_POST['stock_bajo_entero'] ?? 5);
        $stock_bajo_fraccion = floatval($_POST['stock_bajo_fraccion'] ?? 1.000);

        query("UPDATE nombre_negocio SET stock_bajo_entero=?, stock_bajo_fraccion=? WHERE empresa_id=?",
              [$stock_bajo_entero, $stock_bajo_fraccion, $empresa_id]);
    }

    // Guardar configuración de Cámaras
    if ($tab === 'camaras') {
        $deteccion_rostros = isset($_POST['deteccion_rostros']) ? 1 : 0;
        $deteccion_movimiento = isset($_POST['deteccion_movimiento']) ? 1 : 0;
        $umbral_confianza = floatval($_POST['umbral_confianza'] ?? 0.8000);
        $horario_inicio = trim($_POST['horario_inicio'] ?? '08:00:00');
        $horario_fin = trim($_POST['horario_fin'] ?? '22:00:00');
        $alertas_fuera_horario = isset($_POST['alertas_fuera_horario']) ? 1 : 0;

        query("INSERT INTO config_camara (empresa_id, deteccion_rostros, deteccion_movimiento, umbral_confianza, 
                              horario_inicio, horario_fin, alertas_fuera_horario) 
                VALUES (?, ?, ?, ?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                deteccion_rostros = VALUES(deteccion_rostros), 
                deteccion_movimiento = VALUES(deteccion_movimiento), 
                umbral_confianza = VALUES(umbral_confianza), 
                horario_inicio = VALUES(horario_inicio), 
                horario_fin = VALUES(horario_fin), 
                alertas_fuera_horario = VALUES(alertas_fuera_horario)",
              [$empresa_id, $deteccion_rostros, $deteccion_movimiento, $umbral_confianza, 
               $horario_inicio, $horario_fin, $alertas_fuera_horario]);
    }

    // Guardar configuración de Reconocimiento Facial
    if ($tab === 'reconocimiento') {
        $alertas_activas = isset($_POST['alertas_activas']) ? 1 : 0;
        $notificacion_sonido = isset($_POST['notificacion_sonido']) ? 1 : 0;
        $notificacion_pantalla = isset($_POST['notificacion_pantalla']) ? 1 : 0;
        $email_alerta = isset($_POST['email_alerta']) ? 1 : 0;
        $whatsapp_alerta = isset($_POST['whatsapp_alerta']) ? 1 : 0;
        $umbral_confianza = floatval($_POST['umbral_confianza'] ?? 0.80);
        $tiempo_grabacion_seg = intval($_POST['tiempo_grabacion_seg'] ?? 60);

        query("INSERT INTO config_alertas (empresa_id, alertas_activas, notificacion_sonido, notificacion_pantalla, 
                              email_alerta, whatsapp_alerta, umbral_confianza, tiempo_grabacion_seg) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                alertas_activas = VALUES(alertas_activas), 
                notificacion_sonido = VALUES(notificacion_sonido), 
                notificacion_pantalla = VALUES(notificacion_pantalla), 
                email_alerta = VALUES(email_alerta), 
                whatsapp_alerta = VALUES(whatsapp_alerta), 
                umbral_confianza = VALUES(umbral_confianza), 
                tiempo_grabacion_seg = VALUES(tiempo_grabacion_seg)",
              [$empresa_id, $alertas_activas, $notificacion_sonido, $notificacion_pantalla, 
               $email_alerta, $whatsapp_alerta, $umbral_confianza, $tiempo_grabacion_seg]);
    }

    header('Location: configurar.php');
    exit;
}

$config = fetch("SELECT * FROM nombre_negocio WHERE empresa_id = ?", [$empresa_id]);

// Cargar configuración de cámaras y mergearla con la configuración general
$config_camara = fetch("SELECT * FROM config_camara WHERE empresa_id = ?", [$empresa_id]);
if ($config_camara) {
    $config = array_merge($config, $config_camara);
}

$proveedores_ia = fetchAll("SELECT nombre FROM proveedores_ia WHERE (empresa_id = ? OR empresa_id IS NULL) AND activo = 1", [$empresa_id]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar - WARP POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-900 to-gray-800 text-gray-100">
    <header class="bg-gray-900 shadow-lg">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold text-purple-400">⚙️ PANEL DE CONTROL</h1>
            <a href="dashboard.php" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded">VOLVER</a>
        </div>
    </header>
    <main class="container mx-auto px-6 py-8">
        <!-- Tabs -->
        <div class="flex gap-2 mb-6 flex-wrap">
            <button onclick="showTab('empresa')" id="tabEmpresa" class="px-4 py-2 rounded font-bold bg-purple-600 text-white">🏢 EMPRESA</button>
            <button onclick="showTab('afip')" id="tabAfip" class="px-4 py-2 rounded font-bold bg-gray-700 text-white">📋 AFIP/ARCA</button>
            <button onclick="showTab('ventas')" id="tabVentas" class="px-4 py-2 rounded font-bold bg-gray-700 text-white">💰 VENTAS</button>
            <button onclick="showTab('rutas')" id="tabRutas" class="px-4 py-2 rounded font-bold bg-gray-700 text-white">📁 RUTAS</button>
            <button onclick="showTab('pagos')" id="tabPagos" class="px-4 py-2 rounded font-bold bg-gray-700 text-white">💳 PAGOS</button>
            <button onclick="showTab('inventario')" id="tabInventario" class="px-4 py-2 rounded font-bold bg-gray-700 text-white">📦 INVENTARIO</button>
            <button onclick="showTab('email')" id="tabEmail" class="px-4 py-2 rounded font-bold bg-gray-700 text-white">📧 EMAIL</button>
            <button onclick="showTab('camaras')" id="tabCamaras" class="px-4 py-2 rounded font-bold bg-gray-700 text-white">📹 CÁMARAS</button>
            <button onclick="showTab('camaras_red')" id="tabCamarasRed" class="px-4 py-2 rounded font-bold bg-gray-700 text-white">🔍 RED</button>
            <button onclick="showTab('whatsapp')" id="tabWhatsapp" class="px-4 py-2 rounded font-bold bg-gray-700 text-white">📱 WHATSAPP</button>
            <button onclick="showTab('ia')" id="tabIa" class="px-4 py-2 rounded font-bold bg-gray-700 text-white">🤖 IA</button>
            <button onclick="showTab('dlna')" id="tabDlna" class="px-4 py-2 rounded font-bold bg-gray-700 text-white">📺 DLNA</button>
            <button onclick="showTab('tickets')" id="tabTickets" class="px-4 py-2 rounded font-bold bg-gray-700 text-white">🖨️ TICKETS</button>
            <button onclick="showTab('reconocimiento')" id="tabReconocimiento" class="px-4 py-2 rounded font-bold bg-gray-700 text-white">🤖 RECONOCIMIENTO</button>
        </div>
        
        <!-- Tab Empresa -->
        <div id="panelEmpresa" class="bg-gray-800 rounded-lg border border-gray-700 p-6">
            <h3 class="text-xl font-bold text-white mb-4">Datos de la Empresa</h3>
            <form method="POST">
                <input type="hidden" name="tab" value="empresa">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Nombre del Negocio</label>
                        <input type="text" name="nombre_negocio" value="<?= htmlspecialchars($config['nombre_negocio'] ?? '') ?>"
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Eslogan / Subtítulo</label>
                        <input type="text" name="eslogan" value="<?= htmlspecialchars($config['eslogan'] ?? '') ?>"
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Dirección Comercial</label>
                        <input type="text" name="direccion" value="<?= htmlspecialchars($config['direccion'] ?? '') ?>"
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Condición IVA</label>
                        <input type="text" name="condicion_iva" value="<?= htmlspecialchars($config['condicion_iva'] ?? '') ?>"
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">IVA (%)</label>
                        <input type="number" step="0.01" name="impuesto" value="<?= $config['impuesto'] ?? 21 ?>"
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">IIBB (%)</label>
                        <input type="number" step="0.01" name="ingresos_brutos" value="<?= $config['ingresos_brutos'] ?? 0 ?>"
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Margen (%)</label>
                        <input type="number" step="0.01" name="ganancia_sugerida" value="<?= $config['ganancia_sugerida'] ?? 0 ?>"
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                    </div>
                </div>
                <button type="submit" class="mt-4 bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded font-bold w-full md:w-auto">
                    💾 GUARDAR CONFIGURACIÓN
                </button>
            </form>
        </div>
        
        <!-- Tab AFIP -->
        <div id="panelAfip" class="hidden bg-gray-800 rounded-lg border border-gray-700 p-6">
            <h3 class="text-xl font-bold text-white mb-4">Configuración AFIP/ARCA</h3>
            <form method="POST">
                <input type="hidden" name="tab" value="afip">
                <div class="bg-gray-700 rounded p-4 mb-4">
                    <label class="flex items-center text-white cursor-pointer">
                        <input type="checkbox" name="siempre_fiscal" value="1" <?= ($config['siempre_fiscal'] ?? 0) ? 'checked' : '' ?> class="mr-2">
                        SOLICITAR FACTURA ARCA POR DEFECTO
                    </label>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">CUIT (solo números)</label>
                        <input type="text" name="afip_cuit" value="<?= htmlspecialchars($config['afip_cuit'] ?? '') ?>"
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Punto de Venta</label>
                        <input type="number" name="afip_punto_venta" value="<?= $config['afip_punto_venta'] ?? 1 ?>"
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Certificado AFIP (.crt)</label>
                        <input type="text" name="afip_certificado" value="<?= htmlspecialchars($config['afip_certificado'] ?? '') ?>"
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600"
                               placeholder="/ruta/certificado.crt">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Llave Privada (.key)</label>
                        <input type="text" name="afip_clave" value="<?= htmlspecialchars($config['afip_clave'] ?? '') ?>"
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600"
                               placeholder="/ruta/clave.key">
                    </div>
                </div>
                <div class="flex gap-4 mt-4">
                    <label class="flex items-center text-white cursor-pointer">
                        <input type="checkbox" name="afip_prod" value="1" <?= ($config['afip_prod'] ?? 0) ? 'checked' : '' ?> class="mr-2">
                        MODO PRODUCCIÓN
                    </label>
                    <label class="flex items-center text-white cursor-pointer">
                        <input type="checkbox" name="afip_mock" value="1" <?= ($config['afip_mock'] ?? 0) ? 'checked' : '' ?> class="mr-2">
                        USAR MOCK (SIMULADO)
                    </label>
                </div>
                <button type="submit" class="mt-4 bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded font-bold w-full md:w-auto">
                    💾 GUARDAR CONFIGURACIÓN
                </button>
            </form>
        </div>

        <!-- Tab Inventario -->
        <div id="panelInventario" class="hidden bg-gray-800 rounded-lg border border-gray-700 p-6">
            <h3 class="text-xl font-bold text-white mb-4">Configuración de Inventario</h3>
            <form method="POST">
                <input type="hidden" name="tab" value="inventario">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Stock Bajo (Productos Enteros)</label>
                        <input type="number" name="stock_bajo_entero" value="<?= $config['stock_bajo_entero'] ?? 5 ?>"
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600"
                               placeholder="5">
                        <p class="text-xs text-gray-500 mt-1">Umbral para alertar stock bajo en productos unitarios</p>
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Stock Bajo (Productos por Peso/Fracción)</label>
                        <input type="number" name="stock_bajo_fraccion" step="0.001" value="<?= $config['stock_bajo_fraccion'] ?? 1.000 ?>"
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600"
                               placeholder="1.000">
                        <p class="text-xs text-gray-500 mt-1">Umbral para alertar stock bajo en productos por peso (kg)</p>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded">
                        💾 Guardar Configuración
                    </button>
                </div>
            </form>
        </div>

        <!-- Tab Email -->
        <div id="panelEmail" class="hidden bg-gray-800 rounded-lg border border-gray-700 p-6">
            <h3 class="text-xl font-bold text-white mb-4">Configuración de Email</h3>
            <form method="POST">
                <input type="hidden" name="tab" value="email">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Servidor SMTP (Host)</label>
                        <input type="text" name="email_host" value="<?= htmlspecialchars($config['email_host'] ?? '') ?>"
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600"
                               placeholder="smtp.gmail.com">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Puerto</label>
                        <input type="number" name="email_port" value="<?= $config['email_port'] ?? 587 ?>"
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600"
                               placeholder="587">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Usuario Email</label>
                        <input type="email" name="email_username" value="<?= htmlspecialchars($config['email_username'] ?? '') ?>"
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600"
                               placeholder="tuemail@dominio.com">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Contraseña</label>
                        <input type="password" name="email_password" value="<?= htmlspecialchars($config['email_password'] ?? '') ?>"
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600"
                               placeholder="Contraseña o App Password">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Encriptación</label>
                        <select name="email_encryption" class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                            <option value="tls" <?= ($config['email_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS</option>
                            <option value="ssl" <?= ($config['email_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                            <option value="none" <?= ($config['email_encryption'] ?? '') === 'none' ? 'selected' : '' ?>>Sin encriptación</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Nombre Remitente</label>
                        <input type="text" name="email_from_name" value="<?= htmlspecialchars($config['email_from_name'] ?? $config['nombre_negocio'] ?? '') ?>"
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600"
                               placeholder="Nombre del Negocio">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-gray-400 text-sm mb-2">Email Remitente</label>
                        <input type="email" name="email_from_email" value="<?= htmlspecialchars($config['email_from_email'] ?? '') ?>"
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600"
                               placeholder="noreply@dominio.com">
                    </div>
                </div>
                
                <div class="bg-blue-900 p-4 rounded mt-4">
                    <h4 class="text-blue-300 font-bold mb-2">📧 Configuraciones Comunes:</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div>
                            <strong>Gmail:</strong><br>
                            Host: smtp.gmail.com<br>
                            Puerto: 587<br>
                            Encriptación: TLS<br>
                            <span class="text-xs text-blue-400">Usar App Password (no contraseña normal)</span>
                        </div>
                        <div>
                            <strong>Outlook/Hotmail:</strong><br>
                            Host: smtp-mail.outlook.com<br>
                            Puerto: 587<br>
                            Encriptación: TLS
                        </div>
                        <div>
                            <strong>Yahoo:</strong><br>
                            Host: smtp.mail.yahoo.com<br>
                            Puerto: 587<br>
                            Encriptación: TLS<br>
                            <span class="text-xs text-blue-400">Usar App Password</span>
                        </div>
                        <div>
                            <strong>Custom:</strong><br>
                            Verificar con tu proveedor<br>
                            los datos exactos del servidor
                        </div>
                    </div>
                </div>
                
                <div class="flex gap-4 mt-4">
                    <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded font-bold w-full md:w-auto">
                        💾 GUARDAR
                    </button>
                    <button type="button" onclick="probarEmail()" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded font-bold w-full md:w-auto">
                        📧 PROBAR EMAIL
                    </button>
                </div>
                
                <div id="emailTestResult" class="hidden mt-4 p-4 rounded"></div>
            </form>
        </div>

        <!-- Tab Ventas -->
        <div id="panelVentas" class="hidden bg-gray-800 rounded-lg border border-gray-700 p-6">
            <h3 class="text-xl font-bold text-white mb-4">Configuración de Ventas</h3>
            <form method="POST">
                <input type="hidden" name="tab" value="ventas">
                <div class="space-y-4">
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Moneda ($)</label>
                        <input type="text" name="moneda" value="<?= htmlspecialchars($config['moneda'] ?? '$') ?>"
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                    </div>
                    <div class="bg-gray-700 rounded p-4">
                        <label class="flex items-center text-white cursor-pointer">
                            <input type="checkbox" name="permitir_fraccion" value="1" <?= ($config['permitir_fraccion'] ?? 0) ? 'checked' : '' ?> class="mr-2">
                            HABILITAR VENTAS POR PESO
                        </label>
                    </div>
                </div>
                <button type="submit" class="mt-4 bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded font-bold w-full md:w-auto">
                    💾 GUARDAR CONFIGURACIÓN
                </button>
            </form>
        </div>

        <!-- Tab Rutas -->
        <div id="panelRutas" class="hidden bg-gray-800 rounded-lg border border-gray-700 p-6">
            <h3 class="text-xl font-bold text-white mb-4">Configuración de Rutas (Automática)</h3>
            <p class="text-gray-400 mb-4">Las rutas se configuran automáticamente por empresa. Si necesitas regenerar la estructura de carpetas, usa el botón de regenerar.</p>
            
            <form method="POST">
                <input type="hidden" name="tab" value="rutas">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Ruta Tickets PDF</label>
                        <input type="text" value="<?= htmlspecialchars($config['ruta_tickets'] ?? 'No configurado') ?>"
                               class="w-full bg-gray-600 text-gray-300 p-3 rounded border border-gray-500" readonly>
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Ruta Imágenes Productos</label>
                        <input type="text" value="<?= htmlspecialchars($config['ruta_imagenes'] ?? 'No configurado') ?>"
                        <label class="block text-gray-400 text-sm mb-2">Ruta Imágenes IA</label>
                        <input type="text" value="<?= htmlspecialchars($config['ia_ruta_imagenes'] ?? 'No configurado') ?>"
                               class="w-full bg-gray-600 text-gray-300 p-3 rounded border border-gray-500" readonly>
                    </div>
                </div>
                <button type="submit" name="regenerar_rutas" value="1" class="mt-4 bg-orange-600 hover:bg-orange-700 text-white px-6 py-3 rounded font-bold"
                        onclick="return confirm('¿Regenerar rutas? Esto puede afectar la configuración actual.')">
                    🔄 REGENERAR RUTAS
                </button>
            </form>
        </div>

        <!-- Tab Pagos -->
        <div id="panelPagos" class="hidden bg-gray-800 rounded-lg border border-gray-700 p-6">
            <h3 class="text-xl font-bold text-white mb-4">Configuración de Pagos</h3>
            <form method="POST">
                <input type="hidden" name="tab" value="pagos">
                <div class="space-y-6">
                    <!-- Mercado Pago -->
                    <div class="bg-gray-700 rounded p-4">
                        <h4 class="text-blue-400 font-bold mb-3">MERCADO PAGO</h4>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-gray-400 text-sm mb-2">Access Token</label>
                                <input type="text" name="mp_access_token" value="<?= htmlspecialchars($config['mp_access_token'] ?? '') ?>"
                                       class="w-full bg-gray-600 text-white p-3 rounded border border-gray-500">
                            </div>
                            <div>
                                <label class="block text-gray-400 text-sm mb-2">User ID</label>
                                <input type="text" name="mp_user_id" value="<?= htmlspecialchars($config['mp_user_id'] ?? '') ?>"
                                       class="w-full bg-gray-600 text-white p-3 rounded border border-gray-500">
                            </div>
                            <div>
                                <label class="block text-gray-400 text-sm mb-2">External ID (Caja)</label>
                                <input type="text" name="mp_external_id" value="<?= htmlspecialchars($config['mp_external_id'] ?? 'CAJA_01') ?>"
                                       class="w-full bg-gray-600 text-white p-3 rounded border border-gray-500">
                            </div>
                        </div>
                    </div>
                    <!-- Modo -->
                    <div class="bg-gray-700 rounded p-4">
                        <h4 class="text-green-400 font-bold mb-3">MODO</h4>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-gray-400 text-sm mb-2">Modo API Key</label>
                                <input type="text" name="modo_api_key" value="<?= htmlspecialchars($config['modo_api_key'] ?? '') ?>"
                                       class="w-full bg-gray-600 text-white p-3 rounded border border-gray-500">
                            </div>
                            <label class="flex items-center text-white cursor-pointer">
                                <input type="checkbox" name="modo_sandbox" value="1" <?= ($config['modo_sandbox'] ?? 1) ? 'checked' : '' ?> class="mr-2">
                                Modo Sandbox
                            </label>
                        </div>
                    </div>
                    <!-- PayWay -->
                    <div class="bg-gray-700 rounded p-4">
                        <h4 class="text-red-400 font-bold mb-3">PAYWAY</h4>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-gray-400 text-sm mb-2">PayWay API Key</label>
                                <input type="text" name="pw_api_key" value="<?= htmlspecialchars($config['pw_api_key'] ?? '') ?>"
                                       class="w-full bg-gray-600 text-white p-3 rounded border border-gray-500">
                            </div>
                            <div>
                                <label class="block text-gray-400 text-sm mb-2">Merchant ID</label>
                                <input type="text" name="pw_merchant_id" value="<?= htmlspecialchars($config['pw_merchant_id'] ?? '') ?>"
                                       class="w-full bg-gray-600 text-white p-3 rounded border border-gray-500">
                            </div>
                        </div>
                    </div>
                </div>
                <button type="submit" class="mt-4 bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded font-bold w-full md:w-auto">
                    💾 GUARDAR CONFIGURACIÓN
                </button>
            </form>
        </div>

        <!-- Tab IA -->
        <div id="panelIa" class="hidden bg-gray-800 rounded-lg border border-gray-700 p-6">
            <h3 class="text-xl font-bold text-white mb-4">Configuración de IA</h3>
            <form method="POST">
                <input type="hidden" name="tab" value="ia">
                <div class="space-y-4">
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Proveedor IA</label>
                        <select name="ia_proveedor" class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                            <?php foreach ($proveedores_ia as $prov): ?>
                                <option value="<?= htmlspecialchars($prov['nombre']) ?>" <?= ($config['ia_proveedor'] ?? '') === $prov['nombre'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($prov['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">URL del Servicio/API Endpoint</label>
                        <input type="text" name="ia_url" value="<?= htmlspecialchars($config['ia_url'] ?? '') ?>"
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">API Key</label>
                        <input type="password" name="ia_api_key" value="<?= htmlspecialchars($config['ia_api_key'] ?? '') ?>"
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Ruta Imágenes Generadas (Automática)</label>
                        <input type="text" value="<?= htmlspecialchars($config['ia_ruta_imagenes'] ?? 'No configurado') ?>"
                               class="w-full bg-gray-600 text-gray-300 p-3 rounded border border-gray-500" readonly>
                    </div>
                </div>
                <button type="submit" class="mt-4 bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded font-bold w-full md:w-auto">
                    💾 GUARDAR CONFIGURACIÓN
                </button>
            </form>
        </div>

        <!-- Tab DLNA -->
        <div id="panelDlna" class="hidden bg-gray-800 rounded-lg border border-gray-700 p-6">
            <h3 class="text-xl font-bold text-white mb-4">Configuración DLNA</h3>
            <form method="POST">
                <input type="hidden" name="tab" value="dlna">
                <input type="hidden" name="accion" value="guardar_dlna">
                <div class="space-y-6">
                    <!-- CONFIGURACIÓN SFTP -->
                    <div class="bg-gray-700 rounded p-4">
                        <h4 class="text-green-400 font-bold mb-3">CONFIGURACIÓN SFTP PARA BANNERS</h4>
                        <div class="space-y-3">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-gray-400 text-sm mb-2">Servidor SFTP</label>
                                    <input type="text" name="sftp_host" value="<?= htmlspecialchars($config['sftp_host'] ?? (defined('SFTP_HOST') ? SFTP_HOST : '192.168.31.101')) ?>"
                                           class="w-full bg-gray-600 text-white p-3 rounded border border-gray-500"
                                           placeholder="Ej: 192.168.31.102">
                                    <p class="text-xs text-gray-400 mt-1">IP o dominio del servidor SFTP</p>
                                </div>
                                <div>
                                    <label class="block text-gray-400 text-sm mb-2">Puerto SFTP</label>
                                    <input type="number" name="sftp_port" value="<?= htmlspecialchars($config['sftp_port'] ?? (defined('SFTP_PORT') ? SFTP_PORT : '22')) ?>"
                                           class="w-full bg-gray-600 text-white p-3 rounded border border-gray-500"
                                           placeholder="Ej: 22">
                                    <p class="text-xs text-gray-400 mt-1">Puerto SFTP (default: 22)</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-gray-400 text-sm mb-2">Usuario SFTP</label>
                                    <input type="text" name="sftp_user" value="<?= htmlspecialchars($config['sftp_user'] ?? (defined('SFTP_USER') ? SFTP_USER : 'pi')) ?>"
                                           class="w-full bg-gray-600 text-white p-3 rounded border border-gray-500"
                                           placeholder="Ej: pi">
                                    <p class="text-xs text-gray-400 mt-1">Usuario para conexión SFTP</p>
                                </div>
                                <div>
                                    <label class="block text-gray-400 text-sm mb-2">Password SFTP</label>
                                    <input type="password" name="sftp_password" value="<?= htmlspecialchars($config['sftp_password'] ?? (defined('SFTP_PASSWORD') ? SFTP_PASSWORD : 'juanmanuel')) ?>"
                                           class="w-full bg-gray-600 text-white p-3 rounded border border-gray-500"
                                           placeholder="Password">
                                    <p class="text-xs text-gray-400 mt-1">Contraseña para conexión SFTP</p>
                                </div>
                            </div>
                            <div>
                                <label class="block text-gray-400 text-sm mb-2">Ruta Destino en Servidor</label>
                                <input type="text" name="sftp_remote_path" value="<?= htmlspecialchars($config['sftp_remote_path'] ?? (defined('SFTP_REMOTE_PATH') ? SFTP_REMOTE_PATH : '/mnt/R2/SD64GB/www/facturacion/html/banners/')) ?>"
                                       class="w-full bg-gray-600 text-white p-3 rounded border border-gray-500"
                                       placeholder="Ej: /mnt/R2/SD64GB/www/facturacion/html/banners/">
                                <p class="text-xs text-gray-400 mt-1">Ruta absoluta donde se copiarán los banners en el servidor remoto</p>
                            </div>
                            <div>
                                <label class="flex items-center text-white cursor-pointer">
                                    <input type="checkbox" name="sftp_enabled" value="1" <?= ($config['sftp_enabled'] ?? 0) ? 'checked' : '' ?> class="mr-2">
                                    Habilitar Copia SFTP Automática
                                </label>
                                <p class="text-gray-400 text-xs mt-1">Los banners se copiarán automáticamente vía SFTP cuando se suban</p>
                            </div>
                            <div>
                                <button type="button" onclick="probarConexionSFTP()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded font-bold">
                                    🧪 PROBAR CONEXIÓN SFTP
                                </button>
                                <span id="sftp_test_result" class="ml-3 text-sm"></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- RUTAS DE ARCHIVOS (Automáticas) -->
                    <div class="bg-gray-700 rounded p-4">
                        <h4 class="text-purple-400 font-bold mb-3">RUTAS DE ARCHIVOS (Automáticas)</h4>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-gray-400 text-sm mb-2"> Ruta Banners DLNA (Única)</label>
                                <input type="text" value="<?= htmlspecialchars($config['dlna_ruta_banners'] ?? "files/empresa_{$empresa_id}/banners/proyectar/") ?>"
                                       class="w-full bg-gray-600 text-gray-300 p-3 rounded border border-gray-500" readonly>
                                <p class="text-xs text-gray-400 mt-1">Carpeta DLNA para banners activos (solo DLNA lee esta carpeta)</p>
                            </div>
                            <div>
                                <label class="block text-gray-400 text-sm mb-2"> Ruta Miniaturas (No DLNA)</label>
                                <input type="text" value="files/empresa_<?= $empresa_id ?>/banners/thumbnails/"
                                       class="w-full bg-gray-600 text-gray-300 p-3 rounded border border-gray-500" readonly>
                                <p class="text-xs text-gray-400 mt-1">Miniaturas para vista previa (excluido de DLNA)</p>
                            </div>
                            <div>
                                <label class="block text-gray-400 text-sm mb-2"> Ruta Expirados (No DLNA)</label>
                                <input type="text" value="files/empresa_<?= $empresa_id ?>/banners/expirados/"
                                       class="w-full bg-gray-600 text-gray-300 p-3 rounded border border-gray-500" readonly>
                                <p class="text-xs text-gray-400 mt-1">Banners expirados por fecha (no visible en DLNA)</p>
                            </div>
                            <div>
                                <label class="block text-gray-400 text-sm mb-2"> Ruta Desactivados (No DLNA)</label>
                                <input type="text" value="files/empresa_<?= $empresa_id ?>/banners/desactivados/"
                                       class="w-full bg-gray-600 text-gray-300 p-3 rounded border border-gray-500" readonly>
                                <p class="text-xs text-gray-400 mt-1">Banners desactivados manualmente (no visible en DLNA)</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Botón para crear carpetas automáticamente -->
                <div class="mt-4 bg-gray-700 rounded p-4">
                    <h4 class="text-yellow-400 font-bold mb-3">🔧 CREAR CARPETAS</h4>
                    <p class="text-sm text-gray-400 mb-3">Crea automáticamente las carpetas necesarias para banners (proyectar, thumbnails, desactivados)</p>
                    <a href="crear_carpetas.php" target="_blank" class="inline-block bg-yellow-600 hover:bg-yellow-700 text-white px-6 py-3 rounded font-bold">
                        📁 CREAR CARPETAS DE BANNERS
                    </a>
                </div>
                
                <button type="submit" class="mt-4 bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded font-bold w-full md:w-auto">
                    GUARDAR CONFIGURACIÓN
                </button>
            </form>
        </div>

        <!-- Tab Cámaras -->
        <div id="panelCamaras" class="hidden bg-gray-800 rounded-lg border border-gray-700 p-6">
            <h3 class="text-xl font-bold text-white mb-4">Configuración de Cámaras</h3>
            <form method="POST">
                <input type="hidden" name="tab" value="camaras">
                <div class="space-y-4">
                    <div class="bg-gray-700 rounded p-4">
                        <label class="flex items-center text-white cursor-pointer">
                            <input type="checkbox" name="deteccion_rostros" value="1" <?= ($config['deteccion_rostros'] ?? 1) ? 'checked' : '' ?> class="mr-2">
                            DETECCIÓN AUTOMÁTICA DE ROSTROS
                        </label>
                        <p class="text-gray-400 text-xs mt-1">Identificará automáticamente clientes cuando sean detectados por las cámaras</p>
                    </div>
                    <div class="bg-gray-700 rounded p-4">
                        <label class="flex items-center text-white cursor-pointer">
                            <input type="checkbox" name="deteccion_movimiento" value="1" <?= ($config['deteccion_movimiento'] ?? 1) ? 'checked' : '' ?> class="mr-2">
                            DETECCIÓN AUTOMÁTICA DE MOVIMIENTO
                        </label>
                        <p class="text-gray-400 text-xs mt-1">Generará eventos cuando se detecte movimiento fuera de horario</p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Umbral de Confianza</label>
                            <select name="umbral_confianza" class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                                <option value="0.6000" <?= ($config['umbral_confianza'] ?? '0.8000') === '0.6000' ? 'selected' : '' ?>>60% (Bajo)</option>
                                <option value="0.7000" <?= ($config['umbral_confianza'] ?? '0.8000') === '0.7000' ? 'selected' : '' ?>>70% (Medio)</option>
                                <option value="0.8000" <?= ($config['umbral_confianza'] ?? '0.8000') === '0.8000' ? 'selected' : '' ?>>80% (Estándar)</option>
                                <option value="0.9000" <?= ($config['umbral_confianza'] ?? '') === '0.9000' ? 'selected' : '' ?>>90% (Alto)</option>
                            </select>
                            <p class="text-gray-500 text-xs mt-1">Nivel de confianza mínimo para reconocimiento facial</p>
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Alertas fuera de horario</label>
                            <select name="alertas_fuera_horario" class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                                <option value="1" <?= ($config['alertas_fuera_horario'] ?? 1) ? 'selected' : '' ?>>Activadas</option>
                                <option value="0" <?= ($config['alertas_fuera_horario'] ?? '') === '0' ? 'selected' : '' ?>>Desactivadas</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Horario Inicio</label>
                            <input type="time" name="horario_inicio" value="<?= $config['horario_inicio'] ?? '08:00' ?>"
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Horario Fin</label>
                            <input type="time" name="horario_fin" value="<?= $config['horario_fin'] ?? '22:00' ?>"
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                        </div>
                    </div>
                </div>
                <button type="submit" class="mt-4 bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded font-bold w-full md:w-auto">
                    💾 GUARDAR CONFIGURACIÓN
                </button>
            </form>
        </div>

        <!-- Tab Cámaras - Red -->
        <div id="panelCamarasRed" class="hidden bg-gray-800 rounded-lg border border-gray-700 p-6">
            <h3 class="text-xl font-bold text-white mb-4">🔍 Gestión de Cámaras</h3>
            <p class="text-gray-400 mb-6">Agregá cámaras manualmente usando su URL de conexión</p>
            
            <!-- Botones de acción -->
            <div class="mb-6">
                <button onclick="mostrarFormularioCamara()" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded font-bold">
                    ➕ AGREGAR CÁMARA
                </button>
            </div>
            
            <!-- Formulario para agregar cámara manual -->
            <div id="formularioCamara" class="hidden bg-gray-700 rounded-lg p-4 mb-6">
                <h4 class="text-lg font-bold text-white mb-4">Agregar Cámara Manual</h4>
                <form id="camaraForm" onsubmit="return agregarCamara(event)">
                    <div class="space-y-4">
                        <!-- URL Completa - El único campo obligatorio -->
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">URL de la Cámara *</label>
                            <input type="text" name="url" id="camaraUrl" required
                                   class="w-full bg-gray-600 text-white p-3 rounded border border-gray-500"
                                   placeholder="rtsp://admin:password@192.168.1.100:554/stream1"
                                   onchange="parsearUrlCamara()">
                            <p class="text-gray-500 text-xs mt-1">
                                Formatos: rtsp://user:pass@ip:puerto/ruta o http://ip:puerto/video
                            </p>
                        </div>
                        
                        <!-- Campos parseados automáticamente (ocultos) -->
                        <input type="hidden" name="ip" id="camaraIp">
                        <input type="hidden" name="puerto" id="camaraPuerto">
                        <input type="hidden" name="tipo" id="camaraTipo">
                        <input type="hidden" name="usuario" id="camaraUsuario">
                        <input type="hidden" name="password" id="camaraPassword">
                        <input type="hidden" name="ruta_stream" id="camaraRuta">
                        
                        <!-- Vista previa de datos parseados -->
                        <div id="previewDatos" class="hidden bg-gray-800 rounded p-3">
                            <p class="text-gray-400 text-xs mb-2">Datos detectados:</p>
                            <div class="grid grid-cols-2 gap-2 text-sm">
                                <span class="text-gray-500">IP:</span> <span class="text-white" id="previewIp"></span>
                                <span class="text-gray-500">Puerto:</span> <span class="text-white" id="previewPuerto"></span>
                                <span class="text-gray-500">Tipo:</span> <span class="text-white" id="previewTipo"></span>
                                <span class="text-gray-500">Usuario:</span> <span class="text-white" id="previewUsuario"></span>
                                <span class="text-gray-500">Ruta:</span> <span class="text-white" id="previewRuta"></span>
                            </div>
                        </div>
                        
                        <!-- Nombre opcional -->
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Nombre (opcional)</label>
                            <input type="text" name="nombre" id="camaraNombre"
                                   class="w-full bg-gray-600 text-white p-3 rounded border border-gray-500"
                                   placeholder="Ej: Cámara Entrada Principal">
                        </div>
                    </div>
                    <div class="flex gap-2 mt-4">
                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded font-bold">
                            💾 GUARDAR CÁMARA
                        </button>
                        <button type="button" onclick="document.getElementById('camaraForm').reset(); document.getElementById('previewDatos').classList.add('hidden');" class="bg-gray-600 hover:bg-gray-500 text-white px-6 py-2 rounded">
                            CANCELAR
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Lista de cámaras existentes -->
            <div class="mt-6">
                <h4 class="text-lg font-bold text-white mb-4">Cámaras Configuradas</h4>
                <div id="listaCamaras" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Se carga dinámicamente -->
                </div>
            </div>
        </div>

        <!-- Tab WhatsApp -->
        <div id="panelWhatsapp" class="hidden bg-gray-800 rounded-lg border border-gray-700 p-6">
            <h3 class="text-xl font-bold text-white mb-4">Configuración WhatsApp API</h3>
            <form method="POST">
                <input type="hidden" name="tab" value="whatsapp">
                <div class="space-y-4">
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Account SID (Twilio)</label>
                        <input type="text" name="whatsapp_sid" value="<?= htmlspecialchars($config['whatsapp_sid'] ?? '') ?>"
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">API Key</label>
                        <input type="text" name="whatsapp_api_key" value="<?= htmlspecialchars($config['whatsapp_api_key'] ?? '') ?>"
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">API Secret</label>
                        <input type="password" name="whatsapp_api_secret" value="<?= htmlspecialchars($config['whatsapp_api_secret'] ?? '') ?>"
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Número de WhatsApp (con código país)</label>
                        <input type="text" name="whatsapp_phone" value="<?= htmlspecialchars($config['whatsapp_phone'] ?? '') ?>"
                               placeholder="Ej: 5491123456789"
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                    </div>
                    <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white py-3 rounded font-bold">
                        💾 GUARDAR
                    </button>
                </div>
            </form>
        </div>

        <!-- Panel Tickets -->
        <div id="panelTickets" class="hidden bg-gray-800 rounded-lg border border-gray-700 p-6">
            <h2 class="text-xl font-bold text-white mb-4">🖨️ Configuración de Impresión de Tickets</h2>
            <form method="POST">
                <input type="hidden" name="tab" value="tickets">
                <input type="hidden" name="accion" value="guardar">
                <div class="space-y-4">
                    <div class="flex items-center gap-3">
                        <input type="checkbox" name="impresora_auto" id="impresora_auto" value="1" <?= ($config['impresora_auto'] ?? 0) ? 'checked' : '' ?> class="w-5 h-5">
                        <label for="impresora_auto" class="text-white font-bold">Imprimir tickets automáticamente</label>
                    </div>
                    <p class="text-gray-400 text-sm">Si está activado, el ticket se generará y enviará directamente a la impresora sin mostrar vista previa.</p>
                    
                    <div class="mt-4">
                        <label class="block text-gray-400 text-sm mb-2">Tipo de Impresora</label>
                        <div class="flex gap-4 mb-3">
                            <label class="flex items-center gap-2">
                                <input type="radio" name="tipo_impresora" value="auto" id="tipo_auto" <?= ($config['tipo_impresora'] ?? 'auto') === 'auto' ? 'checked' : '' ?> class="w-4 h-4">
                                <span class="text-gray-300">🔍 Detectar automáticamente</span>
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="radio" name="tipo_impresora" value="sistema" id="tipo_sistema" <?= ($config['tipo_impresora'] ?? 'auto') === 'sistema' ? 'checked' : '' ?> class="w-4 h-4">
                                <span class="text-gray-300">🖨️ Impresora del sistema</span>
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="radio" name="tipo_impresora" value="ip" id="tipo_ip" <?= ($config['tipo_impresora'] ?? 'auto') === 'ip' ? 'checked' : '' ?> class="w-4 h-4">
                                <span class="text-gray-300">🌐 Impresora de red (IP)</span>
                            </label>
                        </div>
                        
                        <div id="config_ip" class="<?= ($config['tipo_impresora'] ?? 'auto') !== 'ip' ? 'hidden' : '' ?>">
                            <label class="block text-gray-400 text-sm mb-2">IP de la Impresora</label>
                            <input type="text" name="impresora_ip" value="<?= htmlspecialchars($config['impresora_ip'] ?? '') ?>"
                                   placeholder="Ej: 192.168.1.100:9100"
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                            <p class="text-gray-500 text-xs mt-1">Formato: IP:PUERTO (ej: 192.168.1.100:9100)</p>
                        </div>
                        
                        <div id="config_nombre" class="<?= ($config['tipo_impresora'] ?? 'auto') === 'ip' ? 'hidden' : '' ?>">
                            <label class="block text-gray-400 text-sm mb-2">Nombre de la Impresora</label>
                            <input type="text" name="impresora_ticket" value="<?= htmlspecialchars($config['impresora_ticket'] ?? 'Default') ?>"
                                   placeholder="Ej: EPSON_TM_T20, Default"
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                            <p class="text-gray-500 text-xs mt-1">Usar 'Default' para impresora del sistema o nombre específico</p>
                        </div>
                    </div>
                    
                    <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white py-3 rounded font-bold">
                        💾 GUARDAR
                    </button>
                </div>
            </form>
        </div>

        <!-- Tab Reconocimiento Facial -->
        <div id="panelReconocimiento" class="hidden bg-gray-800 rounded-lg border border-gray-700 p-6">
            <h3 class="text-xl font-bold text-white mb-4">🤖 Configuración de Reconocimiento Facial</h3>
            <form method="POST">
                <input type="hidden" name="tab" value="reconocimiento">
                <div class="space-y-4">
                    <div class="bg-gray-700 rounded p-4">
                        <h4 class="text-white font-bold mb-3">Alertas y Notificaciones</h4>
                        <div class="space-y-3">
                            <label class="flex items-center text-white cursor-pointer">
                                <input type="checkbox" name="alertas_activas" value="1" <?= ($config['alertas_activas'] ?? 0) ? 'checked' : '' ?> class="mr-2">
                                Activar alertas de reconocimiento
                            </label>
                            <label class="flex items-center text-white cursor-pointer">
                                <input type="checkbox" name="notificacion_sonido" value="1" <?= ($config['notificacion_sonido'] ?? 0) ? 'checked' : '' ?> class="mr-2">
                                Notificación con sonido
                            </label>
                            <label class="flex items-center text-white cursor-pointer">
                                <input type="checkbox" name="notificacion_pantalla" value="1" <?= ($config['notificacion_pantalla'] ?? 0) ? 'checked' : '' ?> class="mr-2">
                                Notificación en pantalla
                            </label>
                            <label class="flex items-center text-white cursor-pointer">
                                <input type="checkbox" name="email_alerta" value="1" <?= ($config['email_alerta'] ?? 0) ? 'checked' : '' ?> class="mr-2">
                                Enviar alertas por email
                            </label>
                            <label class="flex items-center text-white cursor-pointer">
                                <input type="checkbox" name="whatsapp_alerta" value="1" <?= ($config['whatsapp_alerta'] ?? 0) ? 'checked' : '' ?> class="mr-2">
                                Enviar alertas por WhatsApp
                            </label>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Umbral de Confianza (0.0 - 1.0)</label>
                            <input type="number" step="0.01" min="0" max="1" name="umbral_confianza" value="<?= $config['umbral_confianza'] ?? 0.80 ?>"
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                            <p class="text-gray-500 text-xs mt-1">Nivel mínimo de confianza para detección</p>
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Tiempo de Grabación (segundos)</label>
                            <input type="number" min="10" max="300" name="tiempo_grabacion_seg" value="<?= $config['tiempo_grabacion_seg'] ?? 60 ?>"
                                   class="w-full bg-gray-700 text-white p-3 rounded border-gray-600">
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

    <script>
        function showTab(tab) {
            const panels = ['panelEmpresa', 'panelAfip', 'panelVentas', 'panelRutas', 'panelPagos', 'panelInventario', 'panelEmail', 'panelCamaras', 'panelCamarasRed', 'panelWhatsapp', 'panelIa', 'panelDlna', 'panelTickets', 'panelReconocimiento'];
            const tabs = ['tabEmpresa', 'tabAfip', 'tabVentas', 'tabRutas', 'tabPagos', 'tabInventario', 'tabEmail', 'tabCamaras', 'tabCamarasRed', 'tabWhatsapp', 'tabIa', 'tabDlna', 'tabTickets', 'tabReconocimiento'];
            const colors = {
                'empresa': 'bg-purple-600',
                'afip': 'bg-blue-600',
                'ventas': 'bg-green-600',
                'rutas': 'bg-yellow-600',
                'pagos': 'bg-blue-500',
                'inventario': 'bg-orange-600',
                'email': 'bg-purple-600',
                'camaras': 'bg-red-600',
                'camaras_red': 'bg-blue-600',
                'whatsapp': 'bg-gray-700',
                'ia': 'bg-pink-600',
                'dlna': 'bg-indigo-600',
                'tickets': 'bg-gray-700',
                'reconocimiento': 'bg-orange-600'
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
                    // Remover todos los colores posibles
                    tabBtn.classList.remove('bg-purple-600', 'bg-blue-600', 'bg-green-600', 'bg-yellow-600', 'bg-blue-500', 'bg-purple-600', 'bg-red-600', 'bg-gray-700', 'bg-pink-600', 'bg-indigo-600', 'bg-orange-600');
                    tabBtn.classList.add('bg-gray-700');
                }
            });

            // Mostrar panel y activar botón seleccionado
            let panelId, tabBtnId;
            
            // Manejo especial para camaras_red
            if (tab === 'camaras_red') {
                panelId = 'panelCamarasRed';
                tabBtnId = 'tabCamarasRed';
            } else {
                panelId = 'panel' + tab.charAt(0).toUpperCase() + tab.slice(1);
                tabBtnId = 'tab' + tab.charAt(0).toUpperCase() + tab.slice(1);
            }
            
            const panel = document.getElementById(panelId);
            const tabBtn = document.getElementById(tabBtnId);
            if (panel) panel.classList.remove('hidden');
            if (tabBtn) {
                tabBtn.classList.remove('bg-gray-700');
                tabBtn.classList.add(colors[tab]);
            }
            
            // Cargar cámaras si se abre la pestaña RED
            if (tab === 'camaras_red') {
                cargarCamarasExistentes();
            }
        }

        function toggleTipoImpresora() {
            const tipo = document.querySelector('input[name="tipo_impresora"]:checked')?.value;
            const configIp = document.getElementById('config_ip');
            const configNombre = document.getElementById('config_nombre');

            if (tipo === 'ip') {
                configIp.classList.remove('hidden');
                configNombre.classList.add('hidden');
            } else {
                configIp.classList.add('hidden');
                configNombre.classList.remove('hidden');
            }
        }

        function probarEmail() {
            const form = document.querySelector('#panelEmail form');
            const formData = new FormData(form);
            const resultDiv = document.getElementById('emailTestResult');
            
            // Agregar acción de prueba
            formData.set('accion', 'probar_email');
            
            // Mostrar loading
            resultDiv.className = 'mt-4 p-4 rounded bg-blue-900 text-blue-300';
            resultDiv.innerHTML = '🔄 Probando conexión...';
            resultDiv.classList.remove('hidden');
            
            fetch('configurar.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.className = 'mt-4 p-4 rounded bg-green-900 text-green-300';
                    resultDiv.innerHTML = '✅ ' + data.message;
                } else {
                    resultDiv.className = 'mt-4 p-4 rounded bg-red-900 text-red-300';
                    resultDiv.innerHTML = '❌ Error: ' + data.error;
                }
            })
            .catch(error => {
                resultDiv.className = 'mt-4 p-4 rounded bg-red-900 text-red-300';
                resultDiv.innerHTML = '❌ Error de conexión: ' + error.message;
            });
        }

        function probarConexionSFTP() {
            const resultDiv = document.getElementById('sftp_test_result');
            
            // Mostrar loading
            resultDiv.className = 'text-blue-300';
            resultDiv.innerHTML = '🔄 Probando conexión...';
            
            fetch('configurar.php?accion_dlna=probar_sftp')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.className = 'text-green-400';
                    resultDiv.innerHTML = '✅ ' + data.message;
                } else {
                    resultDiv.className = 'text-red-400';
                    resultDiv.innerHTML = '❌ ' + data.message;
                }
            })
            .catch(error => {
                resultDiv.className = 'text-red-400';
                resultDiv.innerHTML = '❌ Error: ' + error.message;
            });
        }

        function generarScript() {
            actualizarScript();
        }
        
        function actualizarInterfaz() {
            const tipoInstalacion = document.getElementById('tipo_instalacion').value;
            const configSeparados = document.getElementById('config_separados');
            const configMismo = document.getElementById('config_mismo');
            
            if (tipoInstalacion === 'separados') {
                configSeparados.classList.remove('hidden');
                configMismo.classList.add('hidden');
            } else {
                configSeparados.classList.add('hidden');
                configMismo.classList.remove('hidden');
            }
            
            actualizarScript();
        }
        
        function actualizarScript() {
            const tipoInstalacion = document.getElementById('tipo_instalacion').value;
            const servidorWeb = document.getElementById('servidor_web_ip').value;
            const servidorDLNA = document.getElementById('servidor_dlna_ip').value;
            const pathServidorWeb = document.getElementById('path_servidor_web').value;
            const pathServidorDLNA = document.getElementById('path_servidor_dlna').value;
            const pathMismoServidor = document.getElementById('path_mismo_servidor').value;
            
            let script = '';

            if (!servidorDLNA) {
                document.getElementById('script_generado').value = '❌ Debes ingresar la IP del servidor DLNA';
                return;
            }
            
            script += '# SCRIPT DE CONFIGURACIÓN AUTOMÁTICA DLNA REMOTO\n';
            script += '# WARP POS - Sistema de Gestión Comercial\n';
            script += '# =============================================================================\n\n';
            
            if (tipoInstalacion === 'separados') {
                // Servidores separados
                const tipoComparticion = document.getElementById('tipo_comparticion').value;
                const pathWeb = document.getElementById('path_servidor_web').value;
                const pathDLNA = document.getElementById('path_servidor_dlna').value;
                
                if (!pathWeb || !pathDLNA) {
                    document.getElementById('script_generado').value = '❌ Debes ingresar los paths absolutos';
                    return;
                }
                
                script += '# Configuración: SERVIDORES SEPARADOS\n';
                script += '# Servidor Web: ' + servidorWeb + '\n';
                script += '# Servidor DLNA: ' + servidorDLNA + '\n';
                script += '# Tipo de compartición: ' + tipoComparticion.toUpperCase() + '\n';
                script += '# Path Web: ' + pathWeb + '\n';
                script += '# Path DLNA: ' + pathDLNA + '\n\n';
                
                script += '# === EJECUCIÓN AUTOMÁTICA CON MONTAJE DE RED ===\n';
                script += '# Este script configura montaje de red y symbolic links\n\n';
                
                if (tipoComparticion === 'nfs') {
                    script += '# === CONFIGURACIÓN NFS ===\n\n';
                    script += '# PASO 1: En servidor web (' + servidorWeb + ')\n';
                    script += 'echo "🔧 Configurando NFS en servidor web..."\n';
                    script += 'sudo apt update && sudo apt install -y nfs-kernel-server\n\n';
                    script += '# Exportar carpeta de empresas\n';
                    script += 'echo "' + pathWeb + '/files/empresas ' + servidorDLNA + '(rw,sync,no_subtree_check)" | sudo tee -a /etc/exports\n\n';
                    script += '# Iniciar y habilitar servicios\n';
                    script += 'sudo systemctl restart nfs-kernel-server\n';
                    script += 'sudo exportfs -a\n';
                    script += 'sudo systemctl enable nfs-kernel-server\n\n';
                    
                    script += '# PASO 2: En servidor DLNA (' + servidorDLNA + ')\n';
                    script += 'echo "📺 Configurando cliente NFS y MiniDLNA..."\n';
                    script += 'sudo apt update && sudo apt install -y nfs-common minidlna\n\n';
                    script += '# Crear punto de montaje temporal\n';
                    script += 'sudo mkdir -p /mnt/nexus_empresas\n\n';
                    script += '# Montar carpeta compartida\n';
                    script += 'sudo mount ' + servidorWeb + ':' + pathWeb + '/files/empresas /mnt/nexus_empresas\n\n';
                    script += '# Configurar montaje permanente\n';
                    script += 'echo "' + servidorWeb + ':' + pathWeb + '/files/empresas /mnt/nexus_empresas nfs defaults 0 0" | sudo tee -a /etc/fstab\n\n';
                } else {
                    script += '# === CONFIGURACIÓN SAMBA ===\n\n';
                    script += '# PASO 1: En servidor web (' + servidorWeb + ')\n';
                    script += 'echo "🔧 Configurando Samba en servidor web..."\n';
                    script += 'sudo apt update && sudo apt install -y samba\n\n';
                    script += '# Configurar compartición\n';
                    script += 'sudo bash -c \'cat >> /etc/samba/smb.conf << EOF\n';
                    script += '[nexus_empresas]\n';
                    script += '   path = ' + pathWeb + '/files/empresas\n';
                    script += '   browseable = yes\n';
                    script += '   writable = yes\n';
                    script += '   guest ok = yes\n';
                    script += '   read only = no\n';
                    script += '   force user = www-data\n';
                    script += '   force group = www-data\n';
                    script += 'EOF\'\n\n';
                    script += '# Iniciar y habilitar servicios\n';
                    script += 'sudo systemctl restart smbd nmbd\n';
                    script += 'sudo systemctl enable smbd nmbd\n\n';
                    
                    script += '# PASO 2: En servidor DLNA (' + servidorDLNA + ')\n';
                    script += 'echo "📺 Configurando cliente CIFS y MiniDLNA..."\n';
                    script += 'sudo apt update && sudo apt install -y cifs-utils minidlna\n\n';
                    script += '# Crear punto de montaje temporal\n';
                    script += 'sudo mkdir -p /mnt/nexus_empresas\n\n';
                    script += '# Montar carpeta compartida\n';
                    script += 'sudo mount -t cifs //' + servidorWeb + '/nexus_empresas /mnt/nexus_empresas -o guest\n\n';
                    script += '# Configurar montaje permanente\n';
                    script += 'echo "//' + servidorWeb + '/nexus_empresas /mnt/nexus_empresas cifs guest 0 0" | sudo tee -a /etc/fstab\n\n';
                }
                
                script += '# === CREAR SYMBOLIC LINKS ===\n\n';
                script += 'echo "🔗 Creando symbolic links para DLNA..."\n';
                script += '# Crear estructura de carpetas en DLNA (solo carpeta proyectar)\n';
                script += 'sudo mkdir -p "' + pathDLNA + '/banners"\n\n';
                
                script += '# Crear symbolic links (SOLO DESDE CARPETA PROYECTAR)\n';
                script += 'echo "📁 Link banners: /mnt/nexus_empresas/*/banners/proyectar -> ' + pathDLNA + '/banners"\n';
                script += '# Solo leer carpeta proyectar (excluye expirados y desactivados)\n';
                script += 'for empresa_dir in /mnt/nexus_empresas/*/; do\n';
                script += '    empresa=$(basename "$empresa_dir")\n';
                script += '    if [ -d "$empresa_dir/banners/proyectar" ]; then\n';
                script += '        echo "   📅 Creando links para $empresa/banners/proyectar"\n';
                script += '        # Link todos los archivos de proyectar (solo banners activos)\n';
                script += '        find "$empresa_dir/banners/proyectar" -maxdepth 1 -type f -exec ln -sf {} "' + pathDLNA + '/banners/" \\; 2>/dev/null || true\n';
                script += '    fi\n';
                script += 'done\n\n';
                
                script += '# === CONFIGURACIÓN MINIDLNA ===\n\n';
                script += 'echo "📺 Configurando MiniDLNA..."\n';
                script += 'sudo bash -c \'cat > /tmp/minidlna.conf << EOF\n';
                script += 'port=8200\n';
                script += 'friendly_name=WARP POS Banners\n';
                script += 'media_dir=A,' + pathDLNA + '/banners\n';
                script += 'db_dir=/var/cache/minidlna\n';
                script += 'log_dir=/var/log\n';
                script += 'inotify=yes\n';
                script += 'notify_interval=60\n';
                script += 'network_interface=eth0\n';
                script += 'listen_ip=' + servidorDLNA + '\n';
                script += 'EOF\'\n\n';
                
                script += '# Aplicar configuración\n';
                script += 'sudo mv /tmp/minidlna.conf /etc/minidlna.conf\n';
                script += 'sudo systemctl restart minidlna\n';
                script += 'sudo systemctl enable minidlna\n\n';
                
                script += '# === VERIFICACIÓN ===\n\n';
                script += 'echo "✅ Verificando configuración..."\n';
                script += 'echo "📁 Montaje de carpetas:"\n';
                script += 'df -h | grep nexus_empresas\n\n';
                script += 'echo "🔗 Symbolic links creados:"\n';
                script += 'find "' + pathDLNA + '" -type l -exec ls -la {} \\;\n\n';
                script += 'echo "🖼️ Archivos unificados en banners (estructura plana):"\n';
                script += 'find "' + pathDLNA + '/banners" -type f 2>/dev/null | head -10\n\n';
                script += 'echo "📺 Estado de MiniDLNA:"\n';
                script += 'sudo systemctl status minidlna --no-pager\n\n';
                script += 'echo "🌐 Acceso a los banners:"\n';
                script += 'echo "   http://' + servidorDLNA + ':8200"\n\n';
                script += 'echo "🎉 ¡Configuración completada exitosamente!"\n';
                
            } else {
                // Mismo servidor
                const pathMismo = document.getElementById('path_mismo_servidor').value;
                
                if (!pathMismo) {
                    document.getElementById('script_generado').value = '❌ Debes ingresar el path absoluto';
                    return;
                }
                
                script += '# Configuración: MISMO SERVIDOR\n';
                script += '# Servidor: ' + servidorWeb + '\n';
                script += '# Path: ' + pathMismo + '\n\n';
                
                script += '# === EJECUCIÓN AUTOMÁTICA LOCAL ===\n';
                script += '# WARP POS y MiniDLNA en la misma máquina\n\n';
                
                script += '# PASO 1: Instalar MiniDLNA\n';
                script += 'echo "📺 Instalando MiniDLNA..."\n';
                script += 'sudo apt update && sudo apt install -y minidlna\n\n';
                
                script += '# === CONFIGURACIÓN MINIDLNA DIRECTA ===\n\n';
                script += 'echo "📺 Configurando MiniDLNA con paths directos..."\n';
                script += 'sudo bash -c \'cat > /tmp/minidlna.conf << EOF\n';
                script += 'port=8200\n';
                script += 'friendly_name=WARP POS Banners\n';
                script += 'media_dir=A,' + pathMismo + '/files/empresas/*/banners\n';
                script += 'db_dir=/var/cache/minidlna\n';
                script += 'log_dir=/var/log\n';
                script += 'inotify=yes\n';
                script += 'notify_interval=60\n';
                script += 'network_interface=eth0\n';
                script += 'listen_ip=' + servidorWeb + '\n';
                script += 'EOF\'\n\n';
                
                script += '# Aplicar configuración\n';
                script += 'sudo mv /tmp/minidlna.conf /etc/minidlna.conf\n';
                script += 'sudo systemctl restart minidlna\n';
                script += 'sudo systemctl enable minidlna\n\n';
                
                script += '# === VERIFICACIÓN ===\n\n';
                script += 'echo "✅ Verificando configuración..."\n';
                script += 'echo "🖼️ Archivos unificados en banners (estructura plana):"\n';
                script += 'find "' + pathMismo + '/files/empresas/*/banners" -maxdepth 1 -type f 2>/dev/null | head -10\n\n';
                script += 'echo "📺 Estado de MiniDLNA:"\n';
                script += 'sudo systemctl status minidlna --no-pager\n\n';
                script += 'echo "🌐 Acceso a los banners:"\n';
                script += 'echo "   http://' + servidorWeb + ':8200"\n\n';
                script += 'echo "🎉 ¡Configuración local completada!"\n';
            }
            
            // Mostrar script en el textarea
            document.getElementById('script_generado').value = script;
            
            // Guardar script en variable global
            window.scriptDLNA = script;
        }
        
        function copiarScript() {
            navigator.clipboard.writeText(window.scriptDLNA).then(() => {
                alert('✅ Script copiado al portapapeles');
            });
        }
        
        function descargarScript() {
            const script = document.getElementById('script_generado').value;
            if (!script || script.includes('❌')) {
                alert('❌ No hay un script válido para descargar');
                return;
            }
            
            const blob = new Blob([script], { type: 'text/plain' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'configurar_dlna_remoto.sh';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
        
        function verInstrucciones() {
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            modal.innerHTML = `
                <div class="bg-gray-800 rounded-lg p-6 max-w-3xl max-h-[80vh] overflow-y-auto">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-bold text-white mb-4">📖 Instrucciones de Instalación</h3>
                        <button onclick="this.parentElement.parentElement.parentElement.remove()" class="text-gray-400 hover:text-white text-2xl">&times;</button>
                    </div>
                    <div class="space-y-4 text-gray-300">
                        <div class="bg-blue-900 rounded p-4">
                            <h4 class="font-bold text-blue-300 mb-2">🔧 PASO 1: Generar el Script</h4>
                            <p>1. Configura las IPs y tipo de compartición en la pestaña DLNA</p>
                            <p>2. Haz clic en "📜 GENERAR SCRIPT"</p>
                            <p>3. Copia o descarga el script generado</p>
                        </div>
                        
                        <div class="bg-green-900 rounded p-4">
                            <h4 class="font-bold text-green-300 mb-2">🖥️ PASO 2: Ejecutar en Servidor Web</h4>
                            <p>1. Conéctate por SSH al servidor web (<?= $_SERVER['SERVER_ADDR'] ?>)</p>
                            <p>2. Pega el script y ejecuta: <code class="bg-gray-700 px-2 py-1 rounded">bash configurar_dlna_remoto.sh</code></p>
                            <p>3. El script configurará automáticamente NFS/Samba</p>
                        </div>
                        
                        <div class="bg-purple-900 rounded p-4">
                            <h4 class="font-bold text-purple-300 mb-2">📺 PASO 3: Verificar MiniDLNA</h4>
                            <p>1. Conéctate al servidor DLNA por SSH</p>
                            <p>2. Verifica el montaje: <code class="bg-gray-700 px-2 py-1 rounded">df -h | grep banners</code></p>
                            <p>3. Reinicia MiniDLNA: <code class="bg-gray-700 px-2 py-1 rounded">sudo systemctl restart minidlna</code></p>
                            <p>4. Accede a: <code class="bg-gray-700 px-2 py-1 rounded">http://IP_DLNA:8200</code></p>
                        </div>
                        
                        <div class="bg-yellow-900 rounded p-4">
                            <h4 class="font-bold text-yellow-300 mb-2">🔍 VERIFICACIÓN FINAL</h4>
                            <p>1. Accede a http://IP_DLNA:8200</p>
                            <p>2. Deberías ver los banners en la interfaz web de MiniDLNA</p>
                            <p>3. Los archivos se organizan por año/mes automáticamente</p>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
        }

        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            // Configurar listeners para radio buttons
            document.querySelectorAll('input[name="tipo_impresora"]').forEach(radio => {
                radio.addEventListener('change', toggleTipoImpresora);
            });
            
            // Inicializar estado correcto
            toggleTipoImpresora();
            
            // Generar script automáticamente al cargar la página DLNA
            setTimeout(() => {
                if (document.getElementById('servidor_dlna_ip')) {
                    actualizarScript();
                }
            }, 500);
        });

        // ========== FUNCIONES PARA CÁMARAS - RED ==========
        
        // El formulario ya está visible, no necesitamos estas funciones
        
        function mostrarFormularioCamara() {
            document.getElementById('formularioCamara').classList.remove('hidden');
            // Hacer scroll al formulario
            document.getElementById('formularioCamara').scrollIntoView({ behavior: 'smooth' });
        }
        
        function editarCamara(id) {
            console.log('DEBUG: editando cámara ID:', id);
            
            try {
                // Mostrar el formulario primero
                const formulario = document.getElementById('formularioCamara');
                if (formulario) {
                    formulario.classList.remove('hidden');
                    console.log('DEBUG: formulario mostrado');
                } else {
                    console.error('DEBUG: no se encontró el formulario');
                    return;
                }
                
                // Cargar datos de la cámara desde el servidor
                const body = 'accion=obtener_camara&id=' + id;
                console.log('DEBUG: enviando body:', body);
                
                fetch('configurar.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: body
                })
                .then(response => {
                    console.log('DEBUG: respuesta status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('DEBUG: respuesta data:', data);
                    
                    if (data.success) {
                        const camara = data.camara;
                        console.log('DEBUG: cámara cargada:', camara);
                        
                        // Llenar el formulario con datos reales (con validación)
                        const urlInput = document.getElementById('camaraUrl');
                        if (urlInput) {
                            urlInput.value = camara.url_completa || '';
                            console.log('DEBUG: URL asignada:', camara.url_completa);
                        } else {
                            console.error('DEBUG: no se encontró campo URL');
                        }
                        
                        const nombreInput = document.getElementById('camaraNombre');
                        if (nombreInput) {
                            nombreInput.value = camara.nombre || '';
                            console.log('DEBUG: nombre asignado:', camara.nombre);
                        } else {
                            console.error('DEBUG: no se encontró campo nombre');
                        }
                        
                        // Parsear la URL para llenar campos ocultos
                        parsearUrlCamara();
                        console.log('DEBUG: URL parseada');
                        
                        // Cambiar el formulario a modo edición
                        const form = document.getElementById('camaraForm');
                        if (form) {
                            form.onsubmit = function(event) {
                                return actualizarCamara(event, id);
                            };
                            console.log('DEBUG: formulario configurado para edición');
                        }
                        
                        // Cambiar el texto del botón
                        const submitBtn = document.querySelector('#camaraForm button[type="submit"]');
                        if (submitBtn) {
                            submitBtn.textContent = '💾 ACTUALIZAR CÁMARA';
                            submitBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
                            submitBtn.classList.add('bg-blue-600', 'hover:bg-blue-700');
                            console.log('DEBUG: botón actualizado');
                        } else {
                            console.error('DEBUG: no se encontró botón submit');
                        }
                        
                        // Hacer scroll al formulario
                        formulario.scrollIntoView({ behavior: 'smooth' });
                        console.log('DEBUG: scroll realizado');
                        
                    } else {
                        console.error('DEBUG: error en respuesta:', data);
                        alert('❌ Error: ' + (data.error || 'No se pudo cargar la cámara'));
                    }
                })
                .catch(error => {
                    console.error('DEBUG: error en fetch:', error);
                    alert('❌ Error al cargar los datos de la cámara: ' + error.message);
                });
            } catch (error) {
                console.error('DEBUG: error general en editarCamara:', error);
                alert('❌ Error al editar cámara: ' + error.message);
            }
        }
        
        function actualizarCamara(event, camara_id) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            
            // Convertir FormData a objeto
            const datos = {};
            formData.forEach((value, key) => {
                datos[key] = value;
            });
            datos['id'] = camara_id;
            datos['accion'] = 'actualizar_camara';
            
            console.log('DEBUG: datos a enviar:', datos);
            
            const body = new URLSearchParams(datos);
            console.log('DEBUG: body string:', body.toString());
            
            fetch('configurar.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: body
            })
            .then(response => {
                console.log('DEBUG: actualizarCamara respuesta status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('DEBUG: actualizarCamara respuesta data:', data);
                if (data.success) {
                    alert('✅ Cámara actualizada correctamente');
                    // Recargar lista de cámaras
                    cargarCamarasExistentes();
                    // Resetear formulario y ocultarlo
                    form.reset();
                    document.getElementById('previewDatos').classList.add('hidden');
                    document.getElementById('formularioCamara').classList.add('hidden');
                    console.log('DEBUG: formulario ocultado después de actualizar');
                    // Restaurar función de agregar
                    document.getElementById('camaraForm').onsubmit = agregarCamara;
                } else {
                    console.error('DEBUG: error en actualizar cámara:', data);
                    alert('❌ Error: ' + (data.error || 'No se pudo actualizar la cámara'));
                }
            })
            .catch(error => {
                console.error('DEBUG: error en actualizarCamara fetch:', error);
                alert('❌ Error al actualizar la cámara: ' + error.message);
            });
        }
        
        function eliminarCamara(id, nombre) {
            if (confirm(`¿Estás seguro que querés eliminar la cámara "${nombre}"?\n\nEsta acción no se puede deshacer.`)) {
                fetch('configurar.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'accion=eliminar_camara&id=' + id
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('✅ Cámara eliminada correctamente');
                        cargarCamarasExistentes();
                    } else {
                        alert('❌ Error: ' + (data.error || 'No se pudo eliminar la cámara'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('❌ Error al eliminar la cámara');
                });
            }
        }
        
        function parsearUrlCamara() {
            const urlInput = document.getElementById('camaraUrl').value.trim();
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
                document.getElementById('camaraIp').value = ip;
                document.getElementById('camaraPuerto').value = puerto;
                document.getElementById('camaraTipo').value = tipo;
                document.getElementById('camaraUsuario').value = usuario;
                document.getElementById('camaraPassword').value = password;
                document.getElementById('camaraRuta').value = ruta;
                
                // Mostrar preview
                document.getElementById('previewIp').textContent = ip;
                document.getElementById('previewPuerto').textContent = puerto;
                document.getElementById('previewTipo').textContent = tipo;
                document.getElementById('previewUsuario').textContent = usuario || '(sin usuario)';
                document.getElementById('previewRuta').textContent = ruta;
                document.getElementById('previewDatos').classList.remove('hidden');
                
                // Auto-llenar nombre si está vacío
                const nombreInput = document.getElementById('camaraNombre');
                if (!nombreInput.value) {
                    nombreInput.value = `Cámara ${ip}:${puerto}`;
                }
                
            } catch (e) {
                console.error('Error parseando URL:', e);
                document.getElementById('previewDatos').classList.add('hidden');
            }
        }
        
        function agregarCamaraDesdeEscaneo(ip, puerto, tipo, marca, modelo) {
            // Construir URL desde los datos del escaneo
            const protocolo = tipo === 'RTSP' ? 'rtsp' : 'http';
            const url = `${protocolo}://${ip}:${puerto}/stream1`;
            
            document.getElementById('camaraUrl').value = url;
            document.getElementById('camaraNombre').value = marca ? `Cámara ${marca} ${modelo || ''}` : `Cámara ${ip}`;
            
            // Parsear para llenar campos ocultos
            parsearUrlCamara();
            
            mostrarFormularioCamara();
        }
        
        function agregarCamara(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            
            // Convertir FormData a objeto
            const datos = {};
            formData.forEach((value, key) => {
                datos[key] = value;
            });
            
            console.log('DEBUG: datos a agregar:', datos);
            console.log('DEBUG: JSON string:', JSON.stringify(datos));
            
            fetch('configurar.php?accion=camara_agregar', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(datos)
            })
            .then(response => {
                console.log('DEBUG: respuesta status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('DEBUG: respuesta data:', data);
                if (data.success) {
                    alert('✅ Cámara agregada correctamente');
                    form.reset();
                    document.getElementById('previewDatos').classList.add('hidden');
                    document.getElementById('formularioCamara').classList.add('hidden');
                    cargarCamarasExistentes();
                } else {
                    console.error('DEBUG: error en respuesta:', data);
                    alert('❌ Error: ' + (data.error || 'No se pudo agregar la cámara'));
                }
            })
            .catch(error => {
                alert('❌ Error de conexión: ' + error.message);
            });
            
            return false;
        }
        
        function cargarCamarasExistentes() {
            fetch('configurar.php?accion=camara_listar')
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('listaCamaras');
                    if (data.success && data.camaras.length > 0) {
                        let html = '';
                        data.camaras.forEach(camara => {
                            const estado = camara.activo ? '🟢' : '🔴';
                            html += `
                                <div class="bg-gray-700 rounded-lg p-4 border border-gray-600">
                                    <div class="flex items-start justify-between mb-3">
                                        <div class="flex items-start gap-2 flex-1 min-w-0">
                                            <span class="text-2xl flex-shrink-0">📹</span>
                                            <div class="flex-1 min-w-0">
                                                <p class="font-bold text-white truncate">${camara.nombre}</p>
                                                <p class="text-xs text-gray-500 truncate">${camara.ip}:${camara.puerto}</p>
                                            </div>
                                        </div>
                                        <span class="text-xl flex-shrink-0">${estado}</span>
                                    </div>
                                    <div class="flex gap-2 flex-wrap">
                                        <button onclick="editarCamara(${camara.id})" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm flex-shrink-0">
                                            ✏️ Editar
                                        </button>
                                        <button onclick="eliminarCamara(${camara.id}, '${camara.nombre}')" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm flex-shrink-0">
                                            🗑️ Eliminar
                                        </button>
                                    </div>
                                </div>
                            `;
                        });
                        container.innerHTML = html;
                    } else {
                        container.innerHTML = '<p class="text-gray-400 col-span-full">No hay cámaras configuradas</p>';
                    }
                })
                .catch(error => {
                    console.error('Error cargando cámaras:', error);
                });
        }
        
        // Cargar cámaras al abrir la pestaña
        document.addEventListener('DOMContentLoaded', function() {
            cargarCamarasExistentes();
        });
    </script>
</body>
</html>
