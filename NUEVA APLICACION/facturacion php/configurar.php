<?php
require_once 'config.php';
requireLogin();

$user = getUser();
$empresa_id = $user['empresa_id'];

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $dlna_tipo_servidor = trim($_POST['dlna_tipo_servidor'] ?? 'local');
        $dlna_ip_servidor = trim($_POST['dlna_ip_servidor'] ?? '192.168.1.100');
        $dlna_puerto_servidor = trim($_POST['dlna_puerto_servidor'] ?? '8200');
        // Las rutas DLNA se configuran automáticamente
        $dlna_activo = isset($_POST['dlna_activo']) ? 1 : 0;
        $dlna_auto_start = isset($_POST['dlna_auto_start']) ? 1 : 0;

        query("UPDATE nombre_negocio SET dlna_tipo_servidor=?, dlna_ip_servidor=?, dlna_puerto_servidor=?, dlna_activo=?, dlna_auto_start=? WHERE empresa_id=?",
              [$dlna_tipo_servidor, $dlna_ip_servidor, $dlna_puerto_servidor, $dlna_activo, $dlna_auto_start, $empresa_id]);
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

    // Guardar configuración de Cámaras
    if ($tab === 'camaras') {
        $grabar_ventas = isset($_POST['grabar_ventas']) ? 1 : 0;
        $deteccion_movimiento = isset($_POST['deteccion_movimiento']) ? 1 : 0;
        $calidad_video = trim($_POST['calidad_video'] ?? '720p');
        $duracion_grabacion = intval($_POST['duracion_grabacion'] ?? 30);
        $almacenamiento_maximo = intval($_POST['almacenamiento_maximo'] ?? 1000);
        $horario_inicio = trim($_POST['horario_inicio'] ?? '08:00:00');
        $horario_fin = trim($_POST['horario_fin'] ?? '22:00:00');
        $alertas_fuera_horario = isset($_POST['alertas_fuera_horario']) ? 1 : 0;

        query("INSERT INTO config_camara (empresa_id, grabar_ventas, deteccion_movimiento, calidad_video, 
                              duracion_grabacion, almacenamiento_maximo, horario_inicio, horario_fin, alertas_fuera_horario) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                grabar_ventas = VALUES(grabar_ventas), 
                deteccion_movimiento = VALUES(deteccion_movimiento), 
                calidad_video = VALUES(calidad_video), 
                duracion_grabacion = VALUES(duracion_grabacion), 
                almacenamiento_maximo = VALUES(almacenamiento_maximo), 
                horario_inicio = VALUES(horario_inicio), 
                horario_fin = VALUES(horario_fin), 
                alertas_fuera_horario = VALUES(alertas_fuera_horario)",
              [$empresa_id, $grabar_ventas, $deteccion_movimiento, $calidad_video, $duracion_grabacion, 
               $almacenamiento_maximo, $horario_inicio, $horario_fin, $alertas_fuera_horario]);
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
$proveedores_ia = fetchAll("SELECT nombre FROM proveedores_ia WHERE (empresa_id = ? OR empresa_id IS NULL) AND activo = 1", [$empresa_id]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar - NEXUS POS</title>
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
            <button onclick="showTab('email')" id="tabEmail" class="px-4 py-2 rounded font-bold bg-gray-700 text-white">📧 EMAIL</button>
            <button onclick="showTab('camaras')" id="tabCamaras" class="px-4 py-2 rounded font-bold bg-gray-700 text-white">📹 CÁMARAS</button>
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
                               class="w-full bg-gray-600 text-gray-300 p-3 rounded border border-gray-500" readonly>
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Ruta Imágenes IA</label>
                        <input type="text" value="<?= htmlspecialchars($config['ia_ruta_imagenes'] ?? 'No configurado') ?>"
                               class="w-full bg-gray-600 text-gray-300 p-3 rounded border border-gray-500" readonly>
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Ruta Banners DLNA</label>
                        <input type="text" value="<?= htmlspecialchars($config['dlna_ruta_banners'] ?? 'No configurado') ?>"
                               class="w-full bg-gray-600 text-gray-300 p-3 rounded border border-gray-500" readonly>
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Ruta Imágenes DLNA</label>
                        <input type="text" value="<?= htmlspecialchars($config['dlna_ruta_imagenes'] ?? 'No configurado') ?>"
                               class="w-full bg-gray-600 text-gray-300 p-3 rounded border border-gray-500" readonly>
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Ruta Videos DLNA</label>
                        <input type="text" value="<?= htmlspecialchars($config['dlna_ruta_videos'] ?? 'No configurado') ?>"
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
                <div class="space-y-6">
                    <!-- Servidor DLNA -->
                    <div class="bg-gray-700 rounded p-4">
                        <h4 class="text-purple-400 font-bold mb-3">SERVIDOR DLNA</h4>
                        <div class="space-y-3">
                            <div class="flex gap-4">
                                <label class="flex items-center text-white cursor-pointer">
                                    <input type="radio" name="dlna_tipo_servidor" value="local" <?= ($config['dlna_tipo_servidor'] ?? 'local') === 'local' ? 'checked' : '' ?> class="mr-2">
                                    🏠 Local
                                </label>
                                <label class="flex items-center text-white cursor-pointer">
                                    <input type="radio" name="dlna_tipo_servidor" value="remoto" <?= ($config['dlna_tipo_servidor'] ?? 'local') === 'remoto' ? 'checked' : '' ?> class="mr-2">
                                    🌐 Remoto
                                </label>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-gray-400 text-sm mb-2">IP del Servidor</label>
                                    <input type="text" name="dlna_ip_servidor" value="<?= htmlspecialchars($config['dlna_ip_servidor'] ?? '192.168.1.100') ?>"
                                           class="w-full bg-gray-600 text-white p-3 rounded border border-gray-500"
                                           placeholder="Ej: 192.168.1.100">
                                </div>
                                <div>
                                    <label class="block text-gray-400 text-sm mb-2">Puerto</label>
                                    <input type="text" name="dlna_puerto_servidor" value="<?= htmlspecialchars($config['dlna_puerto_servidor'] ?? '8200') ?>"
                                           class="w-full bg-gray-600 text-white p-3 rounded border border-gray-500"
                                           placeholder="MiniDLNA usa 8200">
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Rutas de Archivos -->
                    <div class="bg-gray-700 rounded p-4">
                        <h4 class="text-gray-400 font-bold mb-3">RUTAS DE ARCHIVOS (Automáticas)</h4>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-gray-400 text-sm mb-2">Ruta Banners</label>
                                <input type="text" value="<?= htmlspecialchars($config['dlna_ruta_banners'] ?? 'No configurado') ?>"
                                       class="w-full bg-gray-600 text-gray-300 p-3 rounded border border-gray-500" readonly>
                            </div>
                            <div>
                                <label class="block text-gray-400 text-sm mb-2">Ruta Imágenes</label>
                                <input type="text" value="<?= htmlspecialchars($config['dlna_ruta_imagenes'] ?? 'No configurado') ?>"
                                       class="w-full bg-gray-600 text-gray-300 p-3 rounded border border-gray-500" readonly>
                            </div>
                            <div>
                                <label class="block text-gray-400 text-sm mb-2">Ruta Videos</label>
                                <input type="text" value="<?= htmlspecialchars($config['dlna_ruta_videos'] ?? 'No configurado') ?>"
                                       class="w-full bg-gray-600 text-gray-300 p-3 rounded border border-gray-500" readonly>
                            </div>
                        </div>
                    </div>
                    <!-- Opciones -->
                    <div class="bg-gray-700 rounded p-4">
                        <h4 class="text-gray-400 font-bold mb-3">OPCIONES</h4>
                        <div class="flex gap-4">
                            <label class="flex items-center text-white cursor-pointer">
                                <input type="checkbox" name="dlna_activo" value="1" <?= ($config['dlna_activo'] ?? 0) ? 'checked' : '' ?> class="mr-2">
                                HABILITAR SERVIDOR DLNA
                            </label>
                            <label class="flex items-center text-white cursor-pointer">
                                <input type="checkbox" name="dlna_auto_start" value="1" <?= ($config['dlna_auto_start'] ?? 0) ? 'checked' : '' ?> class="mr-2">
                                INICIAR AUTOMÁTICAMENTE
                            </label>
                        </div>
                    </div>
                </div>
                <button type="submit" class="mt-4 bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded font-bold w-full md:w-auto">
                    💾 GUARDAR CONFIGURACIÓN
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
                            <input type="checkbox" name="grabar_ventas" value="1" <?= ($config['grabar_ventas'] ?? 1) ? 'checked' : '' ?> class="mr-2">
                            GRABAR AUTOMÁTICAMENTE AL REALIZAR VENTAS
                        </label>
                        <p class="text-gray-400 text-xs mt-1">Se iniciará grabación en todas las cámaras activas cuando se complete una venta</p>
                    </div>
                    <div class="bg-gray-700 rounded p-4">
                        <label class="flex items-center text-white cursor-pointer">
                            <input type="checkbox" name="deteccion_movimiento" value="1" <?= ($config['deteccion_movimiento'] ?? 1) ? 'checked' : '' ?> class="mr-2">
                            DETECCIÓN AUTOMÁTICA DE MOVIMIENTO
                        </label>
                        <p class="text-gray-400 text-xs mt-1">Grabará automáticamente cuando se detecte movimiento fuera de horario</p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Calidad de Video</label>
                            <select name="calidad_video" class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                                <option value="360p" <?= ($config['calidad_video'] ?? '720p') === '360p' ? 'selected' : '' ?>>360p (Baja)</option>
                                <option value="720p" <?= ($config['calidad_video'] ?? '720p') === '720p' ? 'selected' : '' ?>>720p (Estándar)</option>
                                <option value="1080p" <?= ($config['calidad_video'] ?? '') === '1080p' ? 'selected' : '' ?>>1080p (Alta)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Duración de Grabación (segundos)</label>
                            <input type="number" name="duracion_grabacion" value="<?= $config['duracion_grabacion'] ?? 30 ?>" min="5" max="300"
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
                        </div>
                        <div>
                            <label class="block text-gray-400 text-sm mb-2">Almacenamiento Máximo (MB)</label>
                            <input type="number" name="almacenamiento_maximo" value="<?= $config['almacenamiento_maximo'] ?? 1000 ?>" min="100" max="10000"
                                   class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600">
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
            const panels = ['panelEmpresa', 'panelAfip', 'panelVentas', 'panelRutas', 'panelPagos', 'panelEmail', 'panelCamaras', 'panelWhatsapp', 'panelIa', 'panelDlna', 'panelTickets', 'panelReconocimiento'];
            const tabs = ['tabEmpresa', 'tabAfip', 'tabVentas', 'tabRutas', 'tabPagos', 'tabEmail', 'tabCamaras', 'tabWhatsapp', 'tabIa', 'tabDlna', 'tabTickets', 'tabReconocimiento'];
            const colors = {
                'empresa': 'bg-purple-600',
                'afip': 'bg-blue-600',
                'ventas': 'bg-green-600',
                'rutas': 'bg-yellow-600',
                'pagos': 'bg-blue-500',
                'email': 'bg-purple-600',
                'camaras': 'bg-red-600',
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
                    tabBtn.classList.remove('bg-purple-600', 'bg-blue-600', 'bg-green-600', 'bg-yellow-600', 'bg-blue-500', 'bg-pink-600', 'bg-indigo-600');
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

        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            // Configurar listeners para radio buttons
            document.querySelectorAll('input[name="tipo_impresora"]').forEach(radio => {
                radio.addEventListener('change', toggleTipoImpresora);
            });
            
            // Inicializar estado correcto
            toggleTipoImpresora();
        });
    </script>
</body>
</html>
