<?php
require_once 'config.php';
require_once 'lib/CSRFProtection.php';

// Establecer headers de seguridad
setSecurityHeaders();

echo "<!DOCTYPE html>";
echo "<html lang='es'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>Test de Seguridad - NEXUS POS</title>";
echo "<script src='https://cdn.tailwindcss.com'></script>";
echo "</head>";
echo "<body class='font-sans bg-gray-900 text-white min-h-screen p-8'>";
echo "<div class='max-w-4xl mx-auto'>";

echo "<h1 class='text-3xl font-bold mb-8 text-center'>🔒 Test de Seguridad del Sistema</h1>";

// Test 1: CSRF Protection
echo "<div class='bg-gray-800 p-6 rounded-lg mb-6'>";
echo "<h2 class='text-xl font-semibold mb-4 text-green-400'>✅ Protección CSRF</h2>";

$token1 = generateCSRFToken();
$token2 = generateCSRFToken();

echo "<p class='mb-2'>Token generado: " . htmlspecialchars($token1) . "</p>";
echo "<p class='mb-2'>Tokens consistentes: " . ($token1 === $token2 ? "✅ SÍ" : "❌ NO") . "</p>";

$valido = validateCSRFToken($token1);
echo "<p class='mb-2'>Token válido: " . ($valido ? "✅ SÍ" : "❌ NO") . "</p>";

$invalido = validateCSRFToken('token_invalido');
echo "<p class='mb-2'>Token inválido rechazado: " . ($invalido ? "✅ SÍ" : "❌ NO") . "</p>";

echo "</div>";

// Test 2: Cookies Seguras
echo "<div class='bg-gray-800 p-6 rounded-lg mb-6'>";
echo "<h2 class='text-xl font-semibold mb-4 text-blue-400'>🍪 Configuración de Cookies</h2>";

$cookie_httponly = ini_get('session.cookie_httponly');
$cookie_samesite = ini_get('session.cookie_samesite');
$cookie_secure = ini_get('session.cookie_secure');
$cookie_lifetime = ini_get('session.cookie_lifetime');

echo "<p class='mb-2'>HttpOnly: " . ($cookie_httponly ? "✅ Habilitado" : "❌ No habilitado") . "</p>";
echo "<p class='mb-2'>SameSite: " . ($cookie_samesite ? "✅ $cookie_samesite" : "❌ No configurado") . "</p>";
echo "<p class='mb-2'>Secure (HTTPS): " . ($cookie_secure ? "✅ Habilitado" : "❌ No habilitado") . "</p>";
echo "<p class='mb-2'>Lifetime: " . $cookie_lifetime . " segundos (" . round($cookie_lifetime/60) . " minutos)</p>";

echo "</div>";

// Test 3: Timeout de Sesión
echo "<div class='bg-gray-800 p-6 rounded-lg mb-6'>";
echo "<h2 class='text-xl font-semibold mb-4 text-yellow-400'>⏱️ Timeout de Sesión</h2>";

$timeout_configurado = ini_get('session.gc_maxlifetime');
echo "<p class='mb-2'>Timeout configurado: " . $timeout_configurado . " segundos (" . round($timeout_configurado/60) . " minutos)</p>";

echo "</div>";

// Test 4: Headers de Seguridad
echo "<div class='bg-gray-800 p-6 rounded-lg mb-6'>";
echo "<h2 class='text-xl font-semibold mb-4 text-purple-400'>🛡️ Headers de Seguridad</h2>";

$headers_to_check = [
    'X-Frame-Options' => 'SAMEORIGIN',
    'X-Content-Type-Options' => 'nosniff',
    'X-XSS-Protection' => '1; mode=block'
];

foreach ($headers_to_check as $header => $expected) {
    $actual = $_SERVER['HTTP_' . str_replace('-', '_', strtoupper($header)] ?? '';
    $status = (strpos($actual, $expected) !== false) ? "✅ Presente" : "❌ Ausente";
    echo "<p class='mb-2'>$header: $status</p>";
}

echo "</div>";

// Test 5: Auditoría de Login
echo "<div class='bg-gray-800 p-6 rounded-lg mb-6'>";
echo "<h2 class='text-xl font-semibold mb-4 text-red-400'>🗄️ Auditoría de Login</h2>";

$table_exists = fetch("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = 'facturacion' AND table_name = 'login_attempts'")['count'];
echo "<p class='mb-2'>Tabla de auditoría: " . ($table_exists > 0 ? "✅ Creada" : "❌ No existe") . "</p>";

echo "</div>";

// Test 6: Archivos Protegidos
echo "<div class='bg-gray-800 p-6 rounded-lg mb-6'>";
echo "<h2 class='text-xl font-semibold mb-4 text-indigo-400'>📁 Archivos de Testing</h2>";

$debug_files = ['debug_sftp.php', 'test_sftp_simple.php', 'test_upload.php', 'test_banner_simple.php'];
$all_protected = true;

foreach ($debug_files as $file) {
    $in_debug_dir = file_exists("_debug/$file");
    $in_root = file_exists($file);
    
    if ($in_root) {
        echo "<p class='mb-2 text-red-400'>⚠️ $file: ❌ Aún accesible en raíz</p>";
        $all_protected = false;
    } else {
        $status = $in_debug_dir ? "✅ Protegido" : "❌ No encontrado";
        echo "<p class='mb-2'>$file: $status</p>";
    }
}

echo "<p class='mb-4'>Todos los archivos protegidos: " . ($all_protected ? "✅ SÍ" : "❌ NO") . "</p>";

echo "</div>";

// Resumen
echo "<div class='bg-gray-700 p-6 rounded-lg border border-gray-600'>";
echo "<h2 class='text-2xl font-bold mb-4 text-center'>📊 Resumen de Seguridad</h2>";

$total_tests = 6;
$passed_tests = 0;

if ($token1 === $token2) $passed_tests++;
if ($valido) $passed_tests++;
if ($invalido === false) $passed_tests++;
if ($cookie_httponly) $passed_tests++;
if ($table_exists > 0) $passed_tests++;
if ($all_protected) $passed_tests++;

$security_score = round(($passed_tests / $total_tests) * 10, 1);
$security_level = $security_score >= 8 ? '🟢 EXCELENTE' : 
                 $security_score >= 6 ? '🟡 BUENO' : 
                 $security_score >= 4 ? '🟠 REGULAR' : '🔴 CRÍTICO';

echo "<div class='text-center mb-4'>";
echo "<div class='text-4xl font-bold mb-2'>Nivel de Seguridad: $security_level</div>";
echo "<div class='text-lg mb-2'>Puntuación: $security_score/10</div>";
echo "<div class='text-sm text-gray-400'>Tests pasados: $passed_tests/$total_tests</div>";
echo "</div>";

if ($security_score < 10) {
    echo "<div class='bg-yellow-600 p-4 rounded-lg mb-6'>";
    echo "<h3 class='text-lg font-semibold mb-2'>📝 Recomendaciones</h3>";
    echo "<ul class='list-disc list-inside space-y-1'>";
    if (!$all_protected) echo "<li>Mover archivos de testing restantes a directorio protegido</li>";
    if (!$cookie_secure) echo "<li>Configurar HTTPS para habilitar cookies seguras</li>";
    if (!$cookie_httponly) echo "<li>Habilitar HttpOnly en cookies</li>";
    if (!$cookie_samesite) echo "<li>Configurar SameSite en cookies</li>";
    echo "</ul>";
    echo "</div>";
}

echo "<div class='text-center mt-8'>";
echo "<a href='dashboard.php' class='bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors'>Volver al Dashboard</a>";
echo "</div>";

echo "</div>";
echo "</body>";
echo "</html>";
?>