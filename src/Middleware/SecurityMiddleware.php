<?php

/**
 * Middleware de Seguridad - TELEGAN ADMIN
 * 
 * Proporciona validación gradual sin romper funcionalidad durante desarrollo
 */
class SecurityMiddleware
{
    private static $initialized = false;
    private static $config = null;
    
    /**
     * Inicializar middleware
     */
    public static function init()
    {
        if (self::$initialized) {
            return;
        }
        
        // Incluir dependencias
        require_once __DIR__ . '/../Config/AppToken.php';
        require_once __DIR__ . '/../Config/Security.php';
        
        self::loadConfig();
        
        // Establecer headers básicos de seguridad
        Security::init();
        
        self::$initialized = true;
    }
    
    /**
     * Cargar configuración
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
     * Validar petición API con opciones flexibles
     * 
     * @param array $options Opciones de validación
     * @return bool True si la petición es válida
     */
    public static function validateApiRequest($options = [])
    {
        $defaultOptions = [
            'require_auth' => false,        // Requerir autenticación de usuario
            'require_app_token' => false,   // Requerir token de aplicación
            'strict_mode' => false,         // Modo estricto (no bypass en desarrollo)
            'log_request' => true,          // Log de la petición
            'return_on_error' => false      // Retornar en lugar de die()
        ];
        
        $options = array_merge($defaultOptions, $options);
        
        // Inicializar AppToken
        AppToken::init();
        
        // Log de petición si está habilitado
        if ($options['log_request']) {
            self::logRequest();
        }
        
        // Validar token de aplicación si es requerido
        if ($options['require_app_token']) {
            $tokenValid = AppToken::isValidRequest($options['strict_mode']);
            
            if (!$tokenValid) {
                if ($options['return_on_error']) {
                    return false;
                }
                
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Token de aplicación requerido',
                    'code' => 'MISSING_APP_TOKEN',
                    'timestamp' => date('Y-m-d H:i:s')
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
        
        // Validar autenticación de usuario si es requerida
        if ($options['require_auth']) {
            // Aquí se implementaría la validación de sesión de usuario
            // Por ahora, retornamos true para no romper funcionalidad
            $authValid = self::validateUserAuth();
            
            if (!$authValid) {
                if ($options['return_on_error']) {
                    return false;
                }
                
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Autenticación requerida',
                    'code' => 'UNAUTHORIZED',
                    'timestamp' => date('Y-m-d H:i:s')
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
        
        return true;
    }
    
    /**
     * Validar autenticación de usuario (placeholder)
     */
    private static function validateUserAuth()
    {
        // TODO: Implementar validación de sesión de usuario
        // Por ahora retornamos true para no romper funcionalidad
        return true;
    }
    
    /**
     * Log de petición
     */
    private static function logRequest()
    {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'UNKNOWN',
            'ip' => self::getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN',
            'has_app_token' => !empty($_SERVER['HTTP_X_APP_TOKEN']),
            'has_auth' => !empty($_SERVER['HTTP_AUTHORIZATION'])
        ];
        
        error_log('SECURITY_MIDDLEWARE: ' . json_encode($logData));
    }
    
    /**
     * Obtener IP del cliente
     */
    private static function getClientIP()
    {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Método de conveniencia para APIs que requieren solo token de app
     */
    public static function requireAppToken($strict = false)
    {
        return self::validateApiRequest([
            'require_app_token' => true,
            'strict_mode' => $strict
        ]);
    }
    
    /**
     * Método de conveniencia para APIs que requieren autenticación completa
     */
    public static function requireAuth($strict = false)
    {
        return self::validateApiRequest([
            'require_auth' => true,
            'require_app_token' => true,
            'strict_mode' => $strict
        ]);
    }
    
    /**
     * Método de conveniencia para APIs públicas (solo logging)
     */
    public static function publicApi()
    {
        return self::validateApiRequest([
            'require_auth' => false,
            'require_app_token' => false,
            'log_request' => true
        ]);
    }
}















