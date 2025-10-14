<?php
/**
 * Sistema de Configuración Automática de Entorno
 * Detecta automáticamente si está en desarrollo o producción
 */

class EnvironmentConfig
{
    private static $config = null;
    
    /**
     * Detectar entorno automáticamente
     */
    public static function detectEnvironment()
    {
        // PRIMERO: Verificar si hay configuración manual en .env
        $envFile = __DIR__ . '/../../env';
        $manualConfig = null;
        
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    
                    if ($key === 'APP_URL') {
                        $manualConfig = $value;
                        break;
                    }
                }
            }
        }
        
        // Si hay configuración manual, usarla
        if ($manualConfig && !empty($manualConfig)) {
            return [
                'is_development' => strpos($manualConfig, 'localhost') !== false,
                'is_production' => strpos($manualConfig, 'localhost') === false,
                'is_https' => strpos($manualConfig, 'https://') === 0,
                'host' => parse_url($manualConfig, PHP_URL_HOST),
                'protocol' => parse_url($manualConfig, PHP_URL_SCHEME),
                'base_url' => $manualConfig,
                'environment' => strpos($manualConfig, 'localhost') !== false ? 'development' : 'production'
            ];
        }
        
        // Fallback: Detección automática solo si no hay configuración manual
        $isLocalhost = (
            $_SERVER['HTTP_HOST'] === 'localhost' ||
            $_SERVER['HTTP_HOST'] === '127.0.0.1' ||
            strpos($_SERVER['HTTP_HOST'], 'localhost') !== false ||
            strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false
        );
        
        $isHttps = (
            isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ||
            isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ||
            isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on'
        );
        
        $protocol = $isHttps ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $baseUrl = $protocol . '://' . $host . '/TELEGAN_ADMIN';
        
        return [
            'is_development' => $isLocalhost,
            'is_production' => !$isLocalhost,
            'is_https' => $isHttps,
            'host' => $host,
            'protocol' => $protocol,
            'base_url' => $baseUrl,
            'environment' => $isLocalhost ? 'development' : 'production'
        ];
    }
    
    /**
     * Obtener configuración del entorno
     */
    public static function getConfig()
    {
        if (self::$config === null) {
            self::$config = self::detectEnvironment();
        }
        
        return self::$config;
    }
    
    /**
     * Obtener URL base para emails
     */
    public static function getBaseUrl()
    {
        $config = self::getConfig();
        return $config['base_url'];
    }
    
    /**
     * Verificar si estamos en desarrollo
     */
    public static function isDevelopment()
    {
        $config = self::getConfig();
        return $config['is_development'];
    }
    
    /**
     * Verificar si estamos en producción
     */
    public static function isProduction()
    {
        $config = self::getConfig();
        return $config['is_production'];
    }
    
    /**
     * Obtener información de debug del entorno
     */
    public static function getDebugInfo()
    {
        $config = self::getConfig();
        
        return [
            'environment_detected' => $config['environment'],
            'base_url' => $config['base_url'],
            'host' => $config['host'],
            'protocol' => $config['protocol'],
            'is_https' => $config['is_https'],
            'server_vars' => [
                'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'N/A',
                'HTTPS' => $_SERVER['HTTPS'] ?? 'N/A',
                'HTTP_X_FORWARDED_PROTO' => $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'N/A',
                'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'N/A'
            ]
        ];
    }
}
?>
