<?php
require_once 'config.php';
require_once 'lib/CSRFProtection.php';

// Establecer headers de seguridad
setSecurityHeaders();

// Test de protección CSRF
echo "<h2>🧪 Test de Seguridad - CSRF Protection</h2>";

// Test 1: Generar y validar token
$token1 = generateCSRFToken();
$token2 = generateCSRFToken(); // Debe ser el mismo token

echo "<h3>✅ Test 1: Generación de Token CSRF</h3>";
echo "<p>Token generado: " . htmlspecialchars($token1) . "</p>";
echo "<p>Token consistente: " . ($token1 === $token2 ? "✅ SÍ" : "❌ NO") . "</p>";

// Test 2: Validación de token válido
$valido = validateCSRFToken($token1);
echo "<h3>✅ Test 2: Validación de Token Válido</h3>";
echo "<p>Token válido: " . ($valido ? "✅ SÍ" : "❌ NO") . "</p>";

// Test 3: Validación de token inválido
$invalido = validateCSRFToken('token_invalido');
echo "<h3>✅ Test 3: Validación de Token Inválido</h3>";
echo "<p>Token inválido rechazado: " . ($invalido ? "✅ SÍ" : "❌ NO") . "</p>";

// Test 4: Token expirado (simulado)
$_SESSION['csrf_token_time'] = time() - 3700; // Token expirado
$expirado = validateCSRFToken($token1);
echo "<h3>✅ Test 4: Validación de Token Expirado</h3>";
echo "<p>Token expirado rechazado: " . ($expirado ? "✅ SÍ" : "❌ NO") . "</p>";

// Restaurar tiempo del token
$_SESSION['csrf_token_time'] = time();

echo "<h2>🍪 Test de Seguridad - Cookies</h2>";

// Test 5: Configuración de cookies
$cookie_httponly = ini_get('session.cookie_httponly');
$cookie_samesite = ini_get('session.cookie_samesite');
$cookie_secure = ini_get('session.cookie_secure');
$cookie_lifetime = ini_get('session.cookie_lifetime');

echo "<h3>✅ Test 5: Configuración de Cookies</h3>";
echo "<p>HttpOnly: " . ($cookie_httponly ? "✅ SÍ" : "❌ NO") . "</p>";
echo "<p>SameSite: " . ($cookie_samesite ? "✅ $cookie_samesite" : "❌ NO") . "</p>";
echo "<p>Secure (HTTPS): " . ($cookie_secure ? "✅ SÍ" : "❌ NO") . "</p>";
echo "<p>Lifetime: " . $cookie_lifetime . " segundos (" . round($cookie_lifetime/60) . " minutos)</p>";

echo "<h2>⏱️ Test de Seguridad - Timeout de Sesión</h2>";

// Test 6: Timeout de sesión
$timeout_configurado = ini_get('session.gc_maxlifetime');
echo "<h3>✅ Test 6: Timeout de Sesión</h3>";
echo "<p>Timeout configurado: " . $timeout_configurado . " segundos (" . round($timeout_configurado/60) . " minutos)</p>";

echo "<h2>📋 Test de Seguridad - Headers</h2>";

// Test 7: Headers de seguridad
$headers = array(
    'X-Frame-Options' => 'SAMEORIGIN',
    'X-Content-Type-Options' => 'nosniff',
    'X-XSS-Protection' => '1; mode=block',
    'Content-Security-Policy' => 'default-src \'self\' https:',
    'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains'
);

echo "<h3>✅ Test 7: Headers de Seguridad</h3>";
foreach ($headers as $header => $expected) {
    $actual = $_SERVER['HTTP_' . str_replace('-', '_', strtoupper($header)] ?? '';
    $status = (strpos($actual, $expected) !== false) ? "✅ SÍ" : "❌ NO";
    echo "<p>$header: $status</p>";
}

echo "<h2>🗄️ Test de Seguridad - Base de Datos</h2>";

// Test 8: Tabla de auditoría de login
$table_exists = fetch("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = 'facturacion' AND table_name = 'login_attempts'")['count'];
echo "<h3>✅ Test 8: Tabla de Auditoría</h3>";
echo "<p>Tabla login_attempts existe: " . ($table_exists > 0 ? "✅ SÍ" : "❌ NO") . "</p>";

echo "<h2>📁 Test de Seguridad - Archivos</h2>";

// Test 9: Archivos de testing movidos
$debug_files = ['debug_sftp.php', 'test_sftp_simple.php', 'test_upload.php', 'test_banner_simple.php'];
$all_protegidos = true;

foreach ($debug_files as $file) {
    $in_debug_dir = file_exists("_debug/$file");
    $in_root = file_exists($file);
    
    if ($in_root) {
        echo "<p>⚠️ $file: ❌ Aún accesible en raíz</p>";
        $all_protegidos = false;
    } else {
        $status = $in_debug_dir ? "✅ SÍ" : "❌ NO";
        echo "<p>$file: $status (protegido en _debug/)</p>";
    }
}

echo "<h3>✅ Test 9: Archivos de Testing</h3>";
echo "<p>Todos los archivos protegidos: " . ($all_protegidos ? "✅ SÍ" : "❌ NO") . "</p>";

echo "<h2>🎯 Resumen de Seguridad</h2>";
$total_tests = 9;
$passed_tests = 0;

// Contar tests pasados
if ($token1 === $token2) $passed_tests++;
if ($valido) $passed_tests++;
if ($invalido === false) $passed_tests++;
if ($expirado === false) $passed_tests++;
if ($cookie_httponly) $passed_tests++;
if ($cookie_samesite) $passed_tests++;
if ($table_exists > 0) $passed_tests++;
if ($all_protegidos) $passed_tests++;

$security_score = round(($passed_tests / $total_tests) * 10, 1);
$security_level = $security_score >= 8 ? '🟢 EXCELENTE' : 
                 $security_score >= 6 ? '🟡 BUENO' : 
                 $security_score >= 4 ? '🟠 REGULAR' : '🔴 CRÍTICO';

echo "<div class='text-2xl font-bold mb-4'>Nivel de Seguridad: $security_level ($security_score/10)</div>";
echo "<p>Tests pasados: $passed_tests/$total_tests</p>";

echo "<h2>📝 Recomendaciones</h2>";
if ($security_score < 10) {
    echo "<ul class='list-disc list-inside'>";
    if (!$all_protegidos) echo "<li>Mover archivos de testing restantes a directorio protegido</li>";
    if ($cookie_secure === false) echo "<li>Configurar HTTPS para habilitar cookies seguras</li>";
    if ($cookie_httponly === false) echo "<li>Habilitar HttpOnly en cookies</li>";
    if ($cookie_samesite === false) echo "<li>Configurar SameSite en cookies</li>";
    echo "</ul>";
}

echo "<p><a href='dashboard.php' class='bg-blue-500 text-white px-4 py-2 rounded'>Volver al Dashboard</a></p>";
?>

<style>
body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
h2 { color: #2563eb; border-bottom: 2px solid #2563eb; padding-bottom: 10px; }
h3.test-section { color: #10b981; margin-top: 20px; }
p { margin: 10px 0; }
.text-2xl { font-size: 1.5rem; }
.font-bold { font-weight: bold; }
.mb-4 { margin-bottom: 1rem; }
.mt-20px { margin-top: 20px; }
.list-disc { list-style-type: disc; }
.list-inside { padding-left: 20px; }
.bg-blue-500 { background-color: #3b82f6; }
.text-white { color: white; }
.px-4 { padding-left: 1rem; padding-right: 1rem; }
.py-2 { padding-top: 0.5rem; padding-bottom: 0.5rem; }
.rounded { border-radius: 0.25rem; }
</style>