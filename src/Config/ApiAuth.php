<?php
/**
 * Clase para autenticación de APIs con tokens
 * Genera y valida tokens hash para frontend
 */
class ApiAuth
{
    private static $secret = null;
    
    /**
     * Obtener secret desde .env
     */
    private static function getSecret()
    {
        if (self::$secret === null) {
            self::loadEnv();
            self::$secret = $_ENV['API_SECRET'] ?? 'telegan_default_secret_change_in_production';
        }
        return self::$secret;
    }
    
    /**
     * Cargar variables de entorno
     */
    private static function loadEnv()
    {
        if (file_exists(__DIR__ . '/../../env')) {
            $lines = file(__DIR__ . '/../../env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $_ENV[trim($key)] = trim($value);
                }
            }
        }
    }
    
    /**
     * Hash simple (coincide con frontend simpleHash)
     * @param string $str
     * @return string Hash hex (exactamente 8 caracteres)
     */
    private static function simpleHash($str)
    {
        $hash = 0;
        for ($i = 0; $i < strlen($str); $i++) {
            $char = ord($str[$i]);
            $hash = (($hash << 5) - $hash) + $char;
            // Forzar a 32 bits (igual que JavaScript: hash & hash = hash, pero limitamos a 32 bits)
            // En JavaScript, los números son de 64 bits pero las operaciones bitwise los tratan como 32 bits
            // En PHP necesitamos forzar explícitamente a 32 bits
            $hash = ($hash & 0xFFFFFFFF);
        }
        // Convertir a hex y asegurar exactamente 8 caracteres (como JavaScript)
        // JavaScript: Math.abs(hash).toString(16).padStart(8, '0')
        $hex = dechex(abs($hash));
        // Asegurar exactamente 8 caracteres
        if (strlen($hex) > 8) {
            $hex = substr($hex, -8); // Tomar últimos 8 si es muy largo
        } else {
            $hex = str_pad($hex, 8, '0', STR_PAD_LEFT); // Rellenar con ceros si es corto
        }
        return $hex;
    }
    
    /**
     * Generar token para una petición
     * @param string $timestamp Timestamp actual
     * @param string $url URL de la petición
     * @return string Hash del token
     */
    public static function generateToken($timestamp, $url = '')
    {
        $secret = self::getSecret();
        $data = $timestamp . $url . $secret;
        // Usar mismo algoritmo que frontend: simpleHash(data).repeat(4).substring(0, 64)
        // simpleHash devuelve 8 chars, repeat(4) = 32 chars, substring(0,64) = 32 chars
        $hash = self::simpleHash($data); // 8 caracteres
        $repeated = str_repeat($hash, 4); // 8 * 4 = 32 caracteres
        // substring(0, 64) de un string de 32 chars devuelve 32 chars
        return substr($repeated, 0, 64); // Máximo 32 chars (pero limitamos a 64 por si acaso)
    }
    
    /**
     * Validar token recibido
     * @param string $token Token recibido
     * @param string $timestamp Timestamp recibido
     * @param string $url URL de la petición
     * @param int $maxAge Segundos máximos de validez (default 300 = 5 min)
     * @return bool True si es válido
     */
    public static function validateToken($token, $timestamp, $url = '', $maxAge = 600)
    {
        // Validar timestamp (no muy viejo)
        if (!is_numeric($timestamp)) {
            return false;
        }
        
        $now = time();
        $age = abs($now - (int)$timestamp);
        
        if ($age > $maxAge) {
            return false; // Token muy viejo
        }
        
        // Generar token esperado
        $expectedToken = self::generateToken($timestamp, $url);
        
        // Comparar (timing-safe)
        return hash_equals($expectedToken, $token);
    }
    
    /**
     * Normalizar path para validación (asegurar que coincida con frontend)
     * @param string $path Path a normalizar
     * @return string Path normalizado
     */
    private static function normalizePath($path)
    {
        // Si viene vacío, extraer del REQUEST_URI
        if (empty($path)) {
            $requestUri = $_SERVER['REQUEST_URI'] ?? '';
            $path = parse_url($requestUri, PHP_URL_PATH) ?? '';
        }
        
        // Asegurar que empiece con /
        if (!empty($path) && substr($path, 0, 1) !== '/') {
            $path = '/' . $path;
        }
        
        // Eliminar trailing slash si no es solo /
        if (strlen($path) > 1 && substr($path, -1) === '/') {
            $path = rtrim($path, '/');
        }
        
        return $path;
    }
    
    /**
     * Validar token de sesión persistente
     * @return array ['valid' => bool, 'error' => string]
     */
    public static function validateSessionToken()
    {
        require_once __DIR__ . '/Session.php';
        
        Session::start();
        
        $sessionToken = Session::get('session_token');
        $sessionTimestamp = Session::get('session_timestamp');
        $sessionValid = Session::get('session_valid', false);
        
        // Verificar si hay token de sesión
        if (empty($sessionToken) || !$sessionValid) {
            return [
                'valid' => false,
                'error' => 'Sesión no iniciada o inválida'
            ];
        }
        
        // Verificar expiración (1 hora)
        if ($sessionTimestamp && (time() - $sessionTimestamp) > 3600) {
            Session::destroy();
            return [
                'valid' => false,
                'error' => 'Sesión expirada'
            ];
        }
        
        // Obtener token del header
        $headerToken = $_SERVER['HTTP_X_SESSION_TOKEN'] ?? '';
        
        if (empty($headerToken)) {
            return [
                'valid' => false,
                'error' => 'Token de sesión faltante'
            ];
        }
        
        // Comparar tokens (timing-safe)
        if (!hash_equals($sessionToken, $headerToken)) {
            return [
                'valid' => false,
                'error' => 'Token de sesión inválido'
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Validar petición desde headers (legacy - mantener por compatibilidad)
     * @param string $url URL de la petición (opcional)
     * @return array ['valid' => bool, 'error' => string]
     */
    public static function validateRequest($url = '')
    {
        // Intentar primero validar sesión
        $sessionValidation = self::validateSessionToken();
        if ($sessionValidation['valid']) {
            return ['valid' => true];
        }
        
        // Fallback a validación por token (legacy)
        $token = $_SERVER['HTTP_X_API_TOKEN'] ?? '';
        $timestamp = $_SERVER['HTTP_X_API_TIMESTAMP'] ?? '';
        
        if (empty($token) || empty($timestamp)) {
            return [
                'valid' => false,
                'error' => 'Token o sesión faltante'
            ];
        }
        
        // Normalizar path para que coincida con frontend
        $normalizedPath = self::normalizePath($url);
        
        // Generar token esperado para comparar usando el path normalizado
        $expectedToken = self::generateToken($timestamp, $normalizedPath);
        
        // Validar token
        if (!self::validateToken($token, $timestamp, $normalizedPath)) {
            return [
                'valid' => false,
                'error' => 'Token inválido o expirado'
            ];
        }
        
        return ['valid' => true];
    }
}

