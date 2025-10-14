<?php

/**
 * Clase de Seguridad - SISTEMA DE AUTENTICACIÓN
 * 
 * Maneja validación, sanitización y seguridad específica para autenticación
 */
class AuthSecurity
{
    private static $initialized = false;
    private static $config = null;
    
    /**
     * Inicializar sistema de seguridad
     */
    public static function init()
    {
        if (self::$initialized) {
            return;
        }
        
        // Cargar configuración
        self::loadConfig();
        
        // Establecer headers de seguridad
        self::setSecurityHeaders();
        
        // Validar request básico
        self::validateRequest();
        
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
        
        // Content Security Policy estricto para autenticación
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'");
        
        // Prevenir caching de páginas sensibles
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
    
    /**
     * Validar request básico
     */
    private static function validateRequest()
    {
        // Validar método HTTP
        $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'];
        if (!in_array($_SERVER['REQUEST_METHOD'], $allowedMethods)) {
            self::sendErrorResponse('Método no permitido', 405);
        }
        
        // Validar headers básicos
        if (!isset($_SERVER['HTTP_HOST'])) {
            self::sendErrorResponse('Request inválido', 400);
        }
        
        // Rate limiting básico (por IP)
        self::checkRateLimit();
    }
    
    /**
     * Verificar rate limiting por IP
     */
    private static function checkRateLimit()
    {
        $ip = self::getClientIP();
        $key = 'auth_rate_limit_' . md5($ip);
        
        // Simulación de rate limiting (en producción usar Redis o similar)
        if (isset($_SESSION[$key])) {
            $attempts = $_SESSION[$key];
            $lastAttempt = $_SESSION[$key . '_time'] ?? 0;
            
            // Resetear contador cada hora
            if (time() - $lastAttempt > 3600) {
                $_SESSION[$key] = 0;
                $_SESSION[$key . '_time'] = time();
            } else {
                // Máximo 10 intentos por hora
                if ($attempts >= 10) {
                    self::sendErrorResponse('Demasiados intentos. Intenta más tarde.', 429);
                }
            }
        }
    }
    
    /**
     * Incrementar contador de rate limiting
     */
    public static function incrementRateLimit()
    {
        $ip = self::getClientIP();
        $key = 'auth_rate_limit_' . md5($ip);
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = 0;
            $_SESSION[$key . '_time'] = time();
        }
        
        $_SESSION[$key]++;
    }
    
    /**
     * Obtener IP del cliente
     */
    public static function getClientIP()
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
     * Validar input del usuario
     */
    public static function validateInput($input, $type = 'string', $maxLength = 255)
    {
        if (empty($input)) {
            return false;
        }
        
        switch ($type) {
            case 'email':
                $email = filter_var(trim($input), FILTER_VALIDATE_EMAIL);
                return $email !== false && strlen($email) <= 255;
                
            case 'password':
                $password = trim($input);
                return strlen($password) >= 8 && strlen($password) <= 128;
                
            case 'numeric':
                return is_numeric($input) && $input > 0;
                
            case 'phone':
                $phone = preg_replace('/[^0-9+\-\(\)\s]/', '', $input);
                return strlen($phone) >= 10 && strlen($phone) <= 20;
                
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
     * Generar token seguro
     */
    public static function generateSecureToken($length = 32)
    {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Generar código de confirmación de 6 dígitos
     */
    public static function generateConfirmationCode()
    {
        return str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Hash de contraseña seguro
     */
    public static function hashPassword($password)
    {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,       // 4 iteraciones
            'threads' => 3,         // 3 hilos
        ]);
    }
    
    /**
     * Verificar contraseña
     */
    public static function verifyPassword($password, $hash)
    {
        return password_verify($password, $hash);
    }
    
    /**
     * Generar token de sesión
     */
    public static function generateSessionToken()
    {
        return self::generateSecureToken(64);
    }
    
    /**
     * Enviar respuesta de error
     */
    private static function sendErrorResponse($message, $code = 400)
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
    
    /**
     * Enviar respuesta de éxito
     */
    public static function sendSuccessResponse($data = [], $message = 'Operación exitosa')
    {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
    
    /**
     * Log de evento de seguridad
     */
    public static function logSecurityEvent($eventType, $details = [], $severity = 'INFO')
    {
        try {
            require_once __DIR__ . '/Database.php';
            
            $logSql = "INSERT INTO security_logs (tipo_evento, ip_address, user_agent, detalles, severidad) VALUES (?, ?, ?, ?, ?)";
            $logParams = [
                $eventType,
                self::getClientIP(),
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                json_encode($details),
                $severity
            ];
            
            AuthDatabase::query($logSql, $logParams);
        } catch (Exception $e) {
            error_log("Error al hacer log de seguridad: " . $e->getMessage());
        }
    }
    
    /**
     * Verificar si el usuario está bloqueado
     */
    public static function isUserBlocked($email)
    {
        try {
            require_once __DIR__ . '/Database.php';
            
            $sql = "SELECT bloqueado_hasta FROM admin_users WHERE email = ? AND bloqueado_hasta > NOW()";
            $result = AuthDatabase::fetch($sql, [$email]);
            
            return $result !== null;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Bloquear usuario temporalmente
     */
    public static function blockUser($email, $minutes = 15)
    {
        try {
            require_once __DIR__ . '/Database.php';
            
            $sql = "UPDATE admin_users SET bloqueado_hasta = NOW() + INTERVAL '{$minutes} minutes' WHERE email = ?";
            AuthDatabase::update($sql, [$email]);
            
            self::logSecurityEvent('USER_BLOCKED', [
                'email' => $email,
                'blocked_until' => date('Y-m-d H:i:s', time() + ($minutes * 60))
            ], 'WARNING');
        } catch (Exception $e) {
            error_log("Error al bloquear usuario: " . $e->getMessage());
        }
    }
}
