<?php

/**
 * Sistema de Validación de Token de Aplicación - TELEGAN ADMIN
 * 
 * Valida que las peticiones vengan del frontend autorizado
 * SIN romper funcionalidad durante desarrollo
 */
class AppToken
{
    private static $initialized = false;
    private static $config = null;
    
    /**
     * Inicializar sistema
     */
    public static function init()
    {
        if (self::$initialized) {
            return;
        }
        
        self::loadConfig();
        self::$initialized = true;
    }
    
    /**
     * Cargar configuración desde .env
     */
    private static function loadConfig()
    {
        if (self::$config !== null) {
            return;
        }

        $envFile = __DIR__ . '/../../.env';
        
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                    list($key, $value) = explode('=', $line, 2);
                    self::$config[trim($key)] = trim($value);
                }
            }
        }
    }
    
    /**
     * Generar hash de validación para el frontend
     * 
     * @param string $timestamp Timestamp actual
     * @param string $userAgent User Agent del cliente
     * @return string Hash de validación
     */
    public static function generateFrontendHash($timestamp = null, $userAgent = null)
    {
        $timestamp = $timestamp ?: time();
        $userAgent = $userAgent ?: ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
        $secret = self::$config['APP_SECRET'] ?? 'telegan_default_secret';
        $domain = self::$config['APP_DOMAIN'] ?? 'localhost';
        
        // Crear string de validación
        $validationString = $timestamp . $userAgent . $secret . $domain;
        
        // Generar hash
        return hash('sha256', $validationString);
    }
    
    /**
     * Validar hash enviado desde el frontend
     * 
     * @param string $frontendHash Hash enviado por el frontend
     * @param string $timestamp Timestamp enviado por el frontend
     * @param string $userAgent User Agent del cliente
     * @return bool True si es válido
     */
    public static function validateFrontendHash($frontendHash, $timestamp = null, $userAgent = null)
    {
        // Validar timestamp (máximo 5 minutos de diferencia)
        if ($timestamp) {
            $currentTime = time();
            $timeDiff = abs($currentTime - (int)$timestamp);
            
            if ($timeDiff > 300) { // 5 minutos
                return false;
            }
        }
        
        // Generar hash esperado
        $expectedHash = self::generateFrontendHash($timestamp, $userAgent);
        
        // Comparar hashes
        return hash_equals($expectedHash, $frontendHash);
    }
    
    /**
     * Verificar si la petición es válida
     * 
     * @param bool $strict Si es true, requiere validación obligatoria
     *                     Si es false, permite bypass en desarrollo
     * @return bool True si la petición es válida
     */
    public static function isValidRequest($strict = false)
    {
        // En modo desarrollo, si no es estricto, permitir bypass
        $env = self::$config['APP_ENV'] ?? 'development';
        if (!$strict && $env === 'development') {
            // Log para desarrollo
            error_log('AppToken: Bypass en modo desarrollo');
            return true;
        }
        
        // Obtener hash del header
        $frontendHash = $_SERVER['HTTP_X_APP_TOKEN'] ?? '';
        $timestamp = $_SERVER['HTTP_X_APP_TIMESTAMP'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (empty($frontendHash)) {
            return false;
        }
        
        return self::validateFrontendHash($frontendHash, $timestamp, $userAgent);
    }
    
    /**
     * Middleware de validación para APIs
     * 
     * @param bool $strict Si requiere validación obligatoria
     * @param bool $returnResponse Si debe retornar respuesta en lugar de die()
     * @return bool|array True si válido, array con error si no
     */
    public static function validateApiRequest($strict = false, $returnResponse = false)
    {
        if (self::isValidRequest($strict)) {
            return true;
        }
        
        $errorResponse = [
            'success' => false,
            'error' => 'Token de aplicación inválido',
            'code' => 'INVALID_APP_TOKEN',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        if ($returnResponse) {
            return $errorResponse;
        }
        
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode($errorResponse, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * Obtener configuración actual
     */
    public static function getConfig()
    {
        return self::$config;
    }
    
    /**
     * Generar token para uso en frontend
     * Retorna objeto con timestamp y hash para enviar en headers
     */
    public static function getFrontendToken()
    {
        $timestamp = time();
        $hash = self::generateFrontendHash($timestamp);
        
        return [
            'timestamp' => $timestamp,
            'hash' => $hash,
            'headers' => [
                'X-App-Token' => $hash,
                'X-App-Timestamp' => $timestamp
            ]
        ];
    }
}












