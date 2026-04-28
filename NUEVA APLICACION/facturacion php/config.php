<?php
// Configuración
define('DB_HOST', '192.168.31.102');
define('DB_NAME', 'facturacion');
define('DB_USER', 'facturacion');
define('DB_PASS', 'juanmanuel');
define('BASE_URL', 'http://localhost:8000');

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
}

function getUser() {
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'nombre' => $_SESSION['user_nombre'] ?? '',
        'rol' => $_SESSION['user_rol'] ?? '',
        'empresa_id' => $_SESSION['empresa_id'] ?? null
    ];
}
