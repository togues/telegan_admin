<?php

/**
 * Clase de Seguridad - TELEGAN ADMIN
 * 
 * Maneja validación de dominio, tokens de aplicación y headers de seguridad
 * SIN romper funcionalidad existente
 */
class Security
{
    private static $initialized = false;
    
    /**
     * Inicializar sistema de seguridad
     */
    public static function init()
    {
        if (self::$initialized) {
            return;
        }
        
        // Cargar variables de entorno
        self::loadEnv();
        
        // Establecer headers de seguridad
        self::setSecurityHeaders();
        
        // Validar dominio (solo si está configurado)
        self::validateDomain();
        
        self::$initialized = true;
    }
    
    /**
     * Cargar variables de entorno
     */
    private static function loadEnv()
    {
        $envFile = __DIR__ . '/../../.env';
        
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                    list($key, $value) = explode('=', $line, 2);
                    $_ENV[trim($key)] = trim($value);
                }
            }
        }
    }
    
    /**
     * Establecer headers de seguridad
     */
    private static function setSecurityHeaders()
    {
        // Prevenir clickjacking
        header('X-Frame-Options: DENY');
        
        // Prevenir MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Habilitar XSS protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Política de referrer
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Content Security Policy (CSP) - Permisivo para no romper funcionalidad
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://unpkg.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self'");
    }
    
    /**
     * Validar dominio (solo si está configurado)
     */
    private static function validateDomain()
    {
        $allowedDomain = $_ENV['APP_DOMAIN'] ?? null;
        
        // Si no está configurado, no validar (modo desarrollo)
        if (!$allowedDomain) {
            return;
        }
        
        $currentDomain = $_SERVER['HTTP_HOST'] ?? '';
        
        if ($currentDomain !== $allowedDomain) {
            http_response_code(403);
            die(json_encode([
                'success' => false,
                'error' => 'Access denied: Invalid domain',
                'timestamp' => date('Y-m-d H:i:s')
            ]));
        }
    }
    
    /**
     * Validar token de aplicación (futuro)
     */
    public static function validateAppToken()
    {
        // Por ahora retorna true para no romper funcionalidad
        // Se implementará en la siguiente fase
        return true;
    }
    
    /**
     * Validar input del usuario
     */
    public static function validateInput($input, $type = 'string', $maxLength = 255)
    {
        if (empty($input)) {
            return false;
        }
        
        switch ($type) {
            case 'email':
                return filter_var($input, FILTER_VALIDATE_EMAIL) !== false;
                
            case 'numeric':
                return is_numeric($input) && $input > 0;
                
            case 'string':
            default:
                $cleaned = trim($input);
                if (strlen($cleaned) > $maxLength) {
                    return false;
                }
                // Solo permitir caracteres seguros
                return preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ0-9\s\-_\.@]+$/', $cleaned);
        }
    }
    
    /**
     * Sanitizar output para prevenir XSS
     */
    public static function sanitizeOutput($data)
    {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeOutput'], $data);
        }
        
        return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Generar token de aplicación único
     */
    public static function generateAppToken()
    {
        $domain = $_ENV['APP_DOMAIN'] ?? 'localhost';
        $timestamp = time();
        $secret = $_ENV['APP_SECRET'] ?? 'telegan_default_secret';
        
        return hash('sha256', $domain . $timestamp . $secret);
    }
    
    /**
     * Verificar si es una request válida
     */
    public static function isValidRequest()
    {
        // Validar método HTTP
        $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE'];
        if (!in_array($_SERVER['REQUEST_METHOD'], $allowedMethods)) {
            return false;
        }
        
        // Validar headers básicos
        if (!isset($_SERVER['HTTP_HOST'])) {
            return false;
        }
        
        return true;
    }
}


