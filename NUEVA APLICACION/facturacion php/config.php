<?php
// Configuración
define('DB_HOST', 'localhost');
define('DB_NAME', 'facturacion');
define('DB_USER', 'facturacion');
define('DB_PASS', 'facturacion');
define('BASE_URL', 'http://localhost');

// Configuración SFTP para banners
define('SFTP_HOST', '192.168.31.101');
define('SFTP_PORT', '22');
define('SFTP_USER', 'pi');
define('SFTP_PASSWORD', 'juanmanuel');
define('SFTP_REMOTE_PATH', '/mnt/R2/SD64GB/www/facturacion/html/banners/');
define('SFTP_ENABLED', false);

// Configurar zona horaria (Argentina)
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Conexión a BD
function getDB() {
    try {
        $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch(PDOException $e) {
        die("Error de conexión: " . $e->getMessage());
    }
}

// Wrapper para consultas con parámetros
function query($sql, $params = []) {
    $db = getDB();
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

// Función para obtener la empresa del usuario logueado
function getUser() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    $db = getDB();
    $stmt = $db->prepare("SELECT id, nombre, rol, empresa_id FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

// Inicializar gestor de URLs si está disponible
if (file_exists(__DIR__ . '/lib/url_manager.php')) {
    require_once __DIR__ . '/lib/url_manager.php';
    
    // Detectar empresa actual basado en URL
    if (!isset($_SESSION['empresa_id']) || !$_SESSION['empresa_id']) {
        $empresa_actual = getEmpresaActual();
        if ($empresa_actual) {
            $_SESSION['empresa_id'] = $empresa_actual;
        }
    }
}

// Fetch all
function fetchAll($sql, $params = []) {
    $stmt = query($sql, $params);
    return $stmt->fetchAll();
}

// Fetch one
function fetch($sql, $params = []) {
    $stmt = query($sql, $params);
    return $stmt->fetch();
}

// Sesión
if (session_status() === PHP_SESSION_NONE) {
    // Configurar cookies seguras antes de iniciar sesión
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
    ini_set('session.gc_maxlifetime', 1800); // 30 minutos
    ini_set('session.cookie_lifetime', 1800);
    
    session_start();
}

// Auth
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
    
    // Validar timeout de sesión (30 minutos)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 1800) {
        session_destroy();
        header('Location: login.php?timeout=1');
        exit;
    }
    
    // Actualizar última actividad
    $_SESSION['last_activity'] = time();
}

// Headers de seguridad
function setSecurityHeaders() {
    // Prevenir clickjacking
    header('X-Frame-Options: SAMEORIGIN');
    
    // Prevenir MIME type sniffing
    header('X-Content-Type-Options: nosniff');
    
    // Protección XSS
    header('X-XSS-Protection: 1; mode=block');
    
    // Política de seguridad de contenido (básico)
    header('Content-Security-Policy: default-src \'self\' https:; script-src \'self\' https: cdjs.cloudflare.com; style-src \'self\' https: cdn.tailwindcss.com; img-src \'self\' data: https:; font-src \'self\' https:; connect-src \'self\' https:; frame-ancestors \'none\';');
    
    // Strict Transport Security
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

