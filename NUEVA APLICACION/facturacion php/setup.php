<?php
require_once 'config.php';

// Verificar dependencias del sistema
function verificarDependencias() {
    $dependencias = [];
    
    // Verificar PHP y versión
    $php_version = phpversion();
    $dependencias['php'] = [
        'nombre' => 'PHP',
        'version' => $php_version,
        'estado' => version_compare($php_version, '8.0', '>=') ? 'ok' : 'error',
        'mensaje' => version_compare($php_version, '8.0', '>=') ? 'PHP ' . $php_version . ' - OK' : 'Se requiere PHP 8.0 o superior'
    ];
    
    // Verificar extensiones PHP necesarias
    $extensiones_requeridas = ['mysql', 'curl', 'gd', 'json', 'mbstring', 'xml', 'zip', 'redis', 'imagick'];
    foreach ($extensiones_requeridas as $ext) {
        $dependencias['ext_' . $ext] = [
            'nombre' => 'Extensión PHP: ' . $ext,
            'version' => extension_loaded($ext) ? 'Cargada' : 'No cargada',
            'estado' => extension_loaded($ext) ? 'ok' : 'error',
            'mensaje' => extension_loaded($ext) ? 'Extensión ' . $ext . ' - OK' : 'Extensión ' . $ext . ' - FALTANTE'
        ];
    }
    
    // Verificar Python y OpenCV
    $python_check = shell_exec('python3 --version 2>/dev/null');
    $dependencias['python'] = [
        'nombre' => 'Python 3',
        'version' => trim($python_check) ?: 'No encontrado',
        'estado' => $python_check ? 'ok' : 'warning',
        'mensaje' => $python_check ? trim($python_check) . ' - OK' : 'Python 3 no encontrado (opcional para reconocimiento facial)'
    ];
    
    // Verificar OpenCV
    $opencv_check = shell_exec('python3 -c "import cv2; print(cv2.__version__)" 2>/dev/null');
    $dependencias['opencv'] = [
        'nombre' => 'OpenCV',
        'version' => trim($opencv_check) ?: 'No encontrado',
        'estado' => $opencv_check ? 'ok' : 'warning',
        'mensaje' => $opencv_check ? 'OpenCV ' . trim($opencv_check) . ' - OK' : 'OpenCV no encontrado (opcional para reconocimiento facial)'
    ];
    
    // Verificar Haar Cascade
    $haar_check = file_exists('/usr/share/opencv4/haarcascades/haarcascade_frontalface_default.xml');
    $dependencias['haar'] = [
        'nombre' => 'Haar Cascade',
        'version' => $haar_check ? 'Disponible' : 'No encontrado',
        'estado' => $haar_check ? 'ok' : 'warning',
        'mensaje' => $haar_check ? 'Haar Cascade - OK' : 'Haar Cascade no encontrado (opcional para reconocimiento facial)'
    ];
    
    // Verificar servicios del sistema
    $services = ['apache2', 'mysql', 'php8.4-fpm', 'redis-server'];
    foreach ($services as $service) {
        $status = shell_exec("systemctl is-active $service 2>/dev/null");
        $dependencias['service_' . $service] = [
            'nombre' => 'Servicio: ' . $service,
            'version' => trim($status),
            'estado' => strpos($status, 'active') !== false ? 'ok' : 'error',
            'mensaje' => strpos($status, 'active') !== false ? $service . ' - Activo' : $service . ' - Inactivo'
        ];
    }
    
    // Verificar herramientas del sistema
    $tools = ['ffmpeg', 'convert', 'gs', 'lp'];
    foreach ($tools as $tool) {
        $tool_check = shell_exec("which $tool 2>/dev/null");
        $dependencias['tool_' . $tool] = [
            'nombre' => 'Herramienta: ' . $tool,
            'version' => trim($tool_check) ?: 'No encontrado',
            'estado' => $tool_check ? 'ok' : 'warning',
            'mensaje' => $tool_check ? $tool . ' - Disponible' : $tool . ' - No encontrado (opcional)'
        ];
    }
    
    // Verificar librerías de sistema
    $libs = ['libv4l2.so', 'libopencv_core.so'];
    foreach ($libs as $lib) {
        $lib_check = shell_exec("ldconfig -p | grep $lib 2>/dev/null");
        $dependencias['lib_' . str_replace(['.', '-'], '_', $lib)] = [
            'nombre' => 'Librería: ' . $lib,
            'version' => $lib_check ? 'Instalada' : 'No encontrada',
            'estado' => $lib_check ? 'ok' : 'warning',
            'mensaje' => $lib_check ? $lib . ' - Disponible' : $lib . ' - No encontrada (opcional)'
        ];
    }
    
    // Verificar permisos de escritura
    $dirs_to_check = ['uploads', 'logs', 'temp'];
    foreach ($dirs_to_check as $dir) {
        $dir_path = __DIR__ . '/' . $dir;
        if (!is_dir($dir_path)) {
            @mkdir($dir_path, 0755, true);
        }
        
        $writable = is_writable($dir_path);
        $dependencias['dir_' . $dir] = [
            'nombre' => 'Directorio: ' . $dir,
            'version' => $writable ? 'Writable' : 'Not writable',
            'estado' => $writable ? 'ok' : 'error',
            'mensaje' => $writable ? $dir . ' - Writable' : $dir . ' - No se puede escribir'
        ];
    }
    
    return $dependencias;
}

// Verificar si ya hay usuarios en el sistema
$db = getDB();
$usuarios_count = fetch("SELECT COUNT(*) as total FROM usuarios")['total'];
$empresas_count = fetch("SELECT COUNT(*) as total FROM empresas")['total'];

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'setup_inicial') {
        $nombre_empresa = trim($_POST['nombre_empresa'] ?? '');
        $cuit = trim($_POST['cuit'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        $admin_usuario = trim($_POST['admin_usuario'] ?? '');
        $admin_password = trim($_POST['admin_password'] ?? '');
        $admin_email = trim($_POST['admin_email'] ?? '');
        
        if ($nombre_empresa && $cuit && $admin_usuario && $admin_password) {
            try {
                $db->beginTransaction();
                
                // 1. Ejecutar el SQL completo para crear todas las tablas
                $sql_file = __DIR__ . '/sql/facturacion_complete.sql';
                if (!file_exists($sql_file)) {
                    throw new Exception("Archivo SQL completo no encontrado: $sql_file");
                }
                
                $sql_content = file_get_contents($sql_file);
                if ($sql_content === false) {
                    throw new Exception("No se pudo leer el archivo SQL completo");
                }
                
                // Eliminar comentarios y dividir en sentencias
                $sql_content = preg_replace('/--.*$/m', '', $sql_content);
                $sql_content = preg_replace('/\/\*.*?\*\//s', '', $sql_content);
                $statements = array_filter(array_map('trim', explode(';', $sql_content)));
                
                $executed_statements = 0;
                foreach ($statements as $statement) {
                    if (!empty($statement) && 
                        !preg_match('/^(CREATE|INSERT|UPDATE|DELETE|ALTER|DROP|TRUNCATE)/i', $statement)) {
                        continue;
                    }
                    
                    try {
                        $db->exec($statement);
                        $executed_statements++;
                    } catch (Exception $e) {
                        // Ignorar errores de tablas que ya existen
                        if (strpos($e->getMessage(), 'already exists') === false &&
                            strpos($e->getMessage(), 'Duplicate entry') === false) {
                            throw $e;
                        }
                    }
                }
                
                // 2. Crear empresa
                $stmt = $db->prepare("INSERT INTO empresas (nombre, cuit, direccion, telefono, email, activo, creado_en) VALUES (?, ?, ?, ?, ?, 1, NOW())");
                $stmt->execute([$nombre_empresa, $cuit, $direccion, $telefono, $email]);
                $empresa_id = $db->lastInsertId();
                
                // 3. Crear usuario admin
                $stmt = $db->prepare("INSERT INTO usuarios (nombre, email, password, rol, empresa_id, activo, creado_en) VALUES (?, ?, SHA2(?, 256), 'admin', ?, 1, NOW())");
                $stmt->execute([$admin_usuario, $admin_email, $admin_password, $empresa_id]);
                $admin_id = $db->lastInsertId();
                
                // 4. Crear configuración inicial de la empresa
                $stmt = $db->prepare("INSERT INTO nombre_negocio (empresa_id, nombre_negocio, direccion, cuit, impuesto, ingresos_brutos, ganancia_sugerida, creado_en) VALUES (?, ?, ?, ?, 21, 0, 30, NOW())");
                $stmt->execute([$empresa_id, $nombre_empresa, $direccion, $cuit]);
                
                // 5. Crear configuración de pagos por defecto
                $stmt = $db->prepare("INSERT INTO config_pagos (empresa_id, mp_access_token, mp_user_id, mp_external_id, modo_api_key, modo_sandbox, pw_api_key, pw_merchant_id, creado_en) VALUES (?, '', '', '', '', 1, '', '', NOW())");
                $stmt->execute([$empresa_id]);
                
                // 6. Inicializar permisos del usuario admin con todas las capabilities
                $stmt = $db->prepare("SELECT id FROM capabilities");
                $stmt->execute();
                $all_capabilities = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                foreach ($all_capabilities as $capability_id) {
                    $stmt = $db->prepare("INSERT INTO user_capabilities (user_id, capability_id, granted_by) VALUES (?, ?, ?)");
                    $stmt->execute([$admin_id, $capability_id, $admin_id]);
                }
                
                $db->commit();
                $success = "✅ Sistema WARP POS v2.0 instalado completamente. Se ejecutaron $executed_statements sentencias SQL. Ahora puedes ingresar con tus credenciales.";
                
                // Redirigir al login después de 3 segundos
                header("refresh:3;url=login.php");
                
            } catch (Exception $e) {
                $db->rollback();
                $error = "❌ Error en la instalación: " . $e->getMessage();
            }
        } else {
            $error = "❌ Complete todos los campos obligatorios";
        }
    }
}

// Si ya hay usuarios, redirigir al login
if ($usuarios_count > 0 || $empresas_count > 0) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WARP POS v2.0 - Instalación Completa</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-gray-900 to-gray-800">
    <div class="bg-gray-800 p-8 rounded-lg shadow-2xl w-full max-w-2xl">
        <div class="text-center mb-8">
            <div class="text-6xl mb-4">◈</div>
            <h1 class="text-3xl font-bold text-white">INSTALACIÓN COMPLETA</h1>
            <p class="text-gray-400 mt-2">WARP POS v2.0 - Sistema Integral de Gestión</p>
            <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white p-4 rounded mt-4">
                <p class="text-sm">🚀 Bienvenido a WARP POS v2.0</p>
                <p class="text-xs mt-1">Instalación completa con 80+ módulos: Ventas, Cámaras, IA, Marketing, Contabilidad y más</p>
            </div>
            
            <!-- Verificación de Dependencias -->
            <div class="bg-gray-700 p-4 rounded mt-4">
                <h3 class="text-white font-bold mb-3">🔍 Verificación de Dependencias</h3>
                <?php
                $dependencias = verificarDependencias();
                $errores_criticos = 0;
                $advertencias = 0;
                
                foreach ($dependencias as $key => $dep) {
                    if ($dep['estado'] === 'error') $errores_criticos++;
                    if ($dep['estado'] === 'warning') $advertencias++;
                }
                ?>
                
                <?php if ($errores_criticos > 0): ?>
                    <div class="bg-red-600 text-white p-3 rounded mb-3">
                        <p class="font-bold">❌ Se detectaron <?= $errores_criticos ?> errores críticos que deben resolverse antes de continuar</p>
                    </div>
                <?php elseif ($advertencias > 0): ?>
                    <div class="bg-yellow-600 text-white p-3 rounded mb-3">
                        <p class="font-bold">⚠️ Se detectaron <?= $advertencias ?> advertencias (opcionales)</p>
                    </div>
                <?php else: ?>
                    <div class="bg-green-600 text-white p-3 rounded mb-3">
                        <p class="font-bold">✅ Todas las dependencias están correctas</p>
                    </div>
                <?php endif; ?>
                
                <div class="space-y-2 max-h-64 overflow-y-auto">
                    <?php foreach ($dependencias as $key => $dep): ?>
                        <div class="flex items-center justify-between p-2 bg-gray-800 rounded">
                            <div class="flex items-center">
                                <?php if ($dep['estado'] === 'ok'): ?>
                                    <span class="text-green-400 mr-2">✅</span>
                                <?php elseif ($dep['estado'] === 'warning'): ?>
                                    <span class="text-yellow-400 mr-2">⚠️</span>
                                <?php else: ?>
                                    <span class="text-red-400 mr-2">❌</span>
                                <?php endif; ?>
                                <span class="text-gray-300 text-sm"><?= $dep['nombre'] ?></span>
                            </div>
                            <div class="text-right">
                                <div class="text-gray-400 text-xs"><?= $dep['version'] ?></div>
                                <div class="text-gray-500 text-xs"><?= $dep['mensaje'] ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($errores_criticos > 0): ?>
                    <div class="mt-4 p-3 bg-blue-600 rounded">
                        <p class="text-sm font-bold text-white">🔧 Solución rápida:</p>
                        <p class="text-xs text-white mt-1">Ejecuta el siguiente comando para instalar todas las dependencias:</p>
                        <div class="bg-black bg-opacity-30 p-2 rounded font-mono text-xs mt-2">
                            bash install_dependencies.sh
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($error): ?>
            <div class="bg-red-500 text-white p-3 rounded mb-4"><?= $error ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-500 text-white p-3 rounded mb-4"><?= $success ?></div>
            
            <!-- Guía de instalación del daemon -->
            <div class="bg-indigo-600 text-white p-6 rounded-lg mb-4">
                <h3 class="text-lg font-bold mb-4">📹 Siguiente Paso: Instalar Daemon de Cámaras</h3>
                <p class="text-sm mb-4">Para activar el sistema de cámaras y reconocimiento facial, ejecuta estos comandos en la terminal:</p>
                
                <div class="bg-black bg-opacity-30 p-4 rounded font-mono text-sm">
                    <div class="mb-2">
                        <span class="text-yellow-300"># 1. Ir al directorio del daemon</span><br>
                        <span>cd /var/www/facturacion/camera_daemon</span>
                    </div>
                    <div class="mb-2">
                        <span class="text-yellow-300"># 2. Ejecutar script de instalación</span><br>
                        <span>bash scripts/install_daemon.sh</span>
                    </div>
                    <div class="mb-2">
                        <span class="text-yellow-300"># 3. Activar el servicio</span><br>
                        <span>sudo systemctl enable camera-daemon</span><br>
                        <span>sudo systemctl start camera-daemon</span>
                    </div>
                    <div class="mb-2">
                        <span class="text-yellow-300"># 4. Verificar estado</span><br>
                        <span>sudo systemctl status camera-daemon</span>
                    </div>
                </div>
                
                <div class="mt-4 p-3 bg-indigo-700 rounded">
                    <p class="text-xs mb-2">📋 <strong>El script instalará automáticamente:</strong></p>
                    <ul class="text-xs space-y-1">
                        <li>• Python 3 y dependencias</li>
                        <li>• OpenCV para reconocimiento facial</li>
                        <li>• YOLO para detección de objetos</li>
                        <li>• Servicio systemd del daemon</li>
                        <li>• Configuración inicial</li>
                    </ul>
                </div>
                
                <div class="mt-4">
                    <a href="login.php" class="bg-white text-indigo-600 px-4 py-2 rounded text-sm font-bold hover:bg-gray-100">
                        🚀 Ir al Login (Configurar cámaras después)
                    </a>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!$success): ?>
        <form method="POST" class="space-y-6">
            <input type="hidden" name="accion" value="setup_inicial">
            
            <!-- Datos de la Empresa -->
            <div class="bg-gray-700 p-4 rounded">
                <h3 class="text-lg font-bold text-white mb-4">🏢 Datos de la Empresa</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Nombre de Empresa *</label>
                        <input type="text" name="nombre_empresa" required 
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600"
                               placeholder="Mi Empresa S.A.">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">CUIT *</label>
                        <input type="text" name="cuit" required 
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600"
                               placeholder="30-12345678-9" maxlength="13">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Dirección</label>
                        <input type="text" name="direccion" 
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600"
                               placeholder="Av. Principal 123">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Teléfono</label>
                        <input type="text" name="telefono" 
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600"
                               placeholder="(011) 1234-5678">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-gray-400 text-sm mb-2">Email</label>
                        <input type="email" name="email" 
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600"
                               placeholder="contacto@miempresa.com">
                    </div>
                </div>
            </div>
            
            <!-- Datos del Administrador -->
            <div class="bg-gray-700 p-4 rounded">
                <h3 class="text-lg font-bold text-white mb-4">👤 Usuario Administrador</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Nombre de Usuario *</label>
                        <input type="text" name="admin_usuario" required 
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600"
                               placeholder="admin">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm mb-2">Contraseña *</label>
                        <input type="password" name="admin_password" required 
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600"
                               placeholder="Mínimo 6 caracteres" minlength="6">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-gray-400 text-sm mb-2">Email del Administrador</label>
                        <input type="email" name="admin_email" 
                               class="w-full bg-gray-700 text-white p-3 rounded border border-gray-600"
                               placeholder="admin@miempresa.com">
                    </div>
                </div>
            </div>
            
            <!-- Información Adicional -->
            <div class="bg-gray-700 p-4 rounded">
                <h3 class="text-lg font-bold text-white mb-2">📋 Información Importante</h3>
                <ul class="text-gray-300 text-sm space-y-1">
                    <li>• El usuario administrador tendrá acceso completo al sistema</li>
                    <li>• Podrás crear más usuarios después del login</li>
                    <li>• La configuración inicial se puede modificar luego</li>
                    <li>• Se crearán las configuraciones básicas automáticamente</li>
                </ul>
            </div>
            
            <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 rounded flex items-center justify-center">
                <span class="mr-2">🚀</span>
                CONFIGURAR SISTEMA
            </button>
        </form>
        <?php endif; ?>
    </div>
    
    <script>
        // Formatear CUIT automáticamente
        document.querySelector('input[name="cuit"]').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2 && value.length <= 11) {
                if (value.length <= 2) {
                    value = value;
                } else if (value.length <= 10) {
                    value = value.slice(0, 2) + '-' + value.slice(2);
                } else {
                    value = value.slice(0, 2) + '-' + value.slice(2, 10) + '-' + value.slice(10, 11);
                }
            }
            e.target.value = value;
        });
    </script>
</body>
</html>
