<?php
/**
 * CSRFProtection - Librería de Protección contra ataques CSRF
 * Proporciona funciones para generar y validar tokens CSRF
 */

class CSRFProtection {
    
    /**
     * Genera un token CSRF seguro y lo almacena en sesión
     * @return string Token CSRF de 64 caracteres hexadecimales
     */
    public static function generateToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Valida un token CSRF contra el almacenado en sesión
     * @param string $token Token a validar
     * @return bool True si el token es válido
     */
    public static function validateToken($token) {
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
            return false;
        }
        
        // Validar que el token coincida usando hash_equals para prevenir timing attacks
        $isValid = hash_equals($_SESSION['csrf_token'], $token);
        
        // Validar que el token no haya expirado (1 hora)
        $isNotExpired = (time() - $_SESSION['csrf_token_time']) < 3600;
        
        return $isValid && $isNotExpired;
    }
    
    /**
     * Genera un campo de formulario HTML con el token CSRF
     * @return string Campo input oculto con el token
     */
    public static function getHiddenField() {
        $token = self::generateToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
    
    /**
     * Regenera el token CSRF (útil después de login/logout)
     */
    public static function regenerateToken() {
        unset($_SESSION['csrf_token']);
        unset($_SESSION['csrf_token_time']);
        self::generateToken();
    }
    
    /**
     * Obtiene el token actual sin generar uno nuevo
     * @return string|null Token actual o null si no existe
     */
    public static function getCurrentToken() {
        return $_SESSION['csrf_token'] ?? null;
    }
    
    /**
     * Limpia todos los tokens CSRF de la sesión
     */
    public static function clearTokens() {
        unset($_SESSION['csrf_token']);
        unset($_SESSION['csrf_token_time']);
    }
}

// Funciones helper globales para fácil uso
function generateCSRFToken() {
    return CSRFProtection::generateToken();
}

function validateCSRFToken($token) {
    return CSRFProtection::validateToken($token);
}

function getCSRFHiddenField() {
    return CSRFProtection::getHiddenField();
}
?>
