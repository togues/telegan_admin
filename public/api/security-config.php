<?php
/**
 * Configuración de Seguridad - TELEGAN ADMIN
 * 
 * Este archivo contiene configuraciones de seguridad centralizadas
 * para prevenir vulnerabilidades comunes.
 */

// Configuración de headers de seguridad
function setSecurityHeaders() {
    // Prevenir clickjacking
    header('X-Frame-Options: DENY');
    
    // Prevenir MIME type sniffing
    header('X-Content-Type-Options: nosniff');
    
    // Habilitar XSS protection
    header('X-XSS-Protection: 1; mode=block');
    
    // Política de referrer
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Content Security Policy (CSP)
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://unpkg.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self'");
}

// Validación de entrada
function validateInput($input, $type = 'string', $maxLength = 255) {
    if (empty($input)) {
        return false;
    }
    
    switch ($type) {
        case 'numeric':
            return is_numeric($input) && $input > 0;
            
        case 'email':
            return filter_var($input, FILTER_VALIDATE_EMAIL) !== false;
            
        case 'string':
        default:
            // Limpiar y validar string
            $cleaned = trim($input);
            if (strlen($cleaned) > $maxLength) {
                return false;
            }
            // Solo permitir caracteres seguros
            return preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ0-9\s\-_\.@]+$/', $cleaned);
    }
}

// Sanitización de salida
function sanitizeOutput($data) {
    if (is_array($data)) {
        return array_map('sanitizeOutput', $data);
    }
    
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

// Validación de ID numérico
function validateId($id) {
    return is_numeric($id) && $id > 0 && $id < 2147483647; // Max int32
}

// Rate limiting básico (simplificado)
function checkRateLimit($identifier, $maxRequests = 100, $timeWindow = 3600) {
    // En producción, usar Redis o base de datos
    // Aquí solo retornamos true por simplicidad
    return true;
}

// Log de seguridad
function logSecurityEvent($event, $details = '') {
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event' => $event,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'details' => $details
    ];
    
    error_log('SECURITY: ' . json_encode($logEntry));
}

// Función para validar tokens CSRF (futuro)
function validateCSRFToken($token) {
    // Implementar cuando se agregue autenticación
    return true;
}

// Configuración de CORS segura
function setSecureCORS() {
    $allowedOrigins = [
        'http://localhost',
        'https://telegan.espacialhn.com'
    ];
    
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    
    if (in_array($origin, $allowedOrigins)) {
        header("Access-Control-Allow-Origin: $origin");
    }
    
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    header("Access-Control-Max-Age: 3600");
}

// Inicializar configuración de seguridad
function initSecurity() {
    setSecurityHeaders();
    setSecureCORS();
    
    // Log de acceso
    logSecurityEvent('api_access', $_SERVER['REQUEST_URI'] ?? '');
}

?>



