<?php

/**
 * Servicio de Enmascaramiento de APIs - TELEGAN ADMIN
 * 
 * Prepara el sistema para ocultar endpoints reales en producción
 * SIN afectar el desarrollo actual
 */
class ApiMasking
{
    private static $initialized = false;
    private static $config = null;
    private static $endpointMap = null;
    
    /**
     * Inicializar servicio
     */
    public static function init()
    {
        if (self::$initialized) {
            return;
        }
        
        self::loadConfig();
        self::generateEndpointMap();
        self::$initialized = true;
    }
    
    /**
     * Cargar configuración
     */
    private static function loadConfig()
    {
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
     * Generar mapa de endpoints (desarrollo vs producción)
     */
    private static function generateEndpointMap()
    {
        $env = self::$config['APP_ENV'] ?? 'development';
        
        if ($env === 'development') {
            // En desarrollo, usar nombres reales (sin enmascaramiento)
            self::$endpointMap = [
                'dashboard-data' => 'dashboard.php',
                'system-alerts' => 'alerts.php',
                'search-users' => 'search.php',
                'user-farms' => 'user-farms.php',
                'farm-details' => 'farm-details.php',
                'operational-stats' => 'operational.php',
                'debug-users' => 'debug-users.php',
                'debug-geometry' => 'debug-geometry.php'
            ];
        } else {
            // En producción, usar nombres enmascarados
            self::$endpointMap = [
                'dashboard-data' => 'data_' . self::generateHash('dashboard') . '.php',
                'system-alerts' => 'alerts_' . self::generateHash('alerts') . '.php',
                'search-users' => 'search_' . self::generateHash('search') . '.php',
                'user-farms' => 'farms_' . self::generateHash('user_farms') . '.php',
                'farm-details' => 'details_' . self::generateHash('farm_details') . '.php',
                'operational-stats' => 'stats_' . self::generateHash('operational') . '.php',
                'debug-users' => 'debug_' . self::generateHash('debug_users') . '.php',
                'debug-geometry' => 'geom_' . self::generateHash('debug_geometry') . '.php'
            ];
        }
    }
    
    /**
     * Generar hash para endpoint
     */
    private static function generateHash($endpoint)
    {
        $secret = self::$config['APP_SECRET'] ?? 'telegan_default_secret';
        $domain = self::$config['APP_DOMAIN'] ?? 'localhost';
        
        return substr(hash('sha256', $endpoint . $secret . $domain), 0, 8);
    }
    
    /**
     * Obtener endpoint real basado en alias
     * 
     * @param string $alias Alias del endpoint (ej: 'dashboard-data')
     * @return string Nombre real del archivo PHP
     */
    public static function getRealEndpoint($alias)
    {
        self::init();
        
        return self::$endpointMap[$alias] ?? $alias;
    }
    
    /**
     * Obtener todos los endpoints disponibles
     */
    public static function getAllEndpoints()
    {
        self::init();
        
        return self::$endpointMap;
    }
    
    /**
     * Validar si un endpoint está disponible
     */
    public static function isValidEndpoint($alias)
    {
        self::init();
        
        return isset(self::$endpointMap[$alias]);
    }
    
    /**
     * Generar archivos enmascarados para producción
     * (Solo ejecutar cuando esté listo para producción)
     */
    public static function generateMaskedFiles()
    {
        $env = self::$config['APP_ENV'] ?? 'development';
        
        if ($env !== 'production') {
            throw new Exception('Solo se puede generar archivos enmascarados en modo producción');
        }
        
        $apiDir = __DIR__ . '/../../public/api/';
        $maskedDir = __DIR__ . '/../../public/api/masked/';
        
        // Crear directorio de archivos enmascarados
        if (!is_dir($maskedDir)) {
            mkdir($maskedDir, 0755, true);
        }
        
        $generated = [];
        
        foreach (self::$endpointMap as $alias => $maskedName) {
            $sourceFile = $apiDir . str_replace(['dashboard.php', 'alerts.php', 'search.php', 'user-farms.php', 'farm-details.php', 'operational.php', 'debug-users.php', 'debug-geometry.php'], 
                                              ['dashboard.php', 'alerts.php', 'search.php', 'user-farms.php', 'farm-details.php', 'operational.php', 'debug-users.php', 'debug-geometry.php'], 
                                              $alias);
            
            $targetFile = $maskedDir . $maskedName;
            
            if (file_exists($sourceFile)) {
                copy($sourceFile, $targetFile);
                $generated[] = $maskedName;
            }
        }
        
        return $generated;
    }
    
    /**
     * Obtener configuración para frontend
     */
    public static function getFrontendConfig()
    {
        self::init();
        
        $env = self::$config['APP_ENV'] ?? 'development';
        
        if ($env === 'development') {
            // En desarrollo, retornar endpoints reales
            return [
                'baseURL' => 'api',
                'endpoints' => self::$endpointMap,
                'masked' => false
            ];
        } else {
            // En producción, retornar endpoints enmascarados
            return [
                'baseURL' => 'api/masked',
                'endpoints' => self::$endpointMap,
                'masked' => true
            ];
        }
    }
}











