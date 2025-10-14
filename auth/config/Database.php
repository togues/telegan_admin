<?php

/**
 * Clase de Base de Datos - SISTEMA DE AUTENTICACIÓN
 * 
 * Conexión independiente para el sistema de autenticación
 * Con configuración de seguridad mejorada
 */
class AuthDatabase
{
    private static $instance = null;
    private static $config = null;

    /**
     * Obtener instancia única de PDO
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            try {
                // Cargar configuración
                self::loadConfig();
                
                $host = self::$config['DB_HOST'] ?? 'localhost';
                $port = self::$config['DB_PORT'] ?? '5432';
                $dbname = self::$config['DB_NAME'] ?? 'telegan';
                $username = self::$config['DB_USER'] ?? 'postgres';
                $password = self::$config['DB_PASSWORD'] ?? '';

                $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";

                self::$instance = new PDO(
                    $dsn,
                    $username,
                    $password,
                    array(
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::ATTR_STRINGIFY_FETCHES => false,
                        PDO::ATTR_PERSISTENT => false, // No usar conexiones persistentes por seguridad
                    )
                );
            } catch (PDOException $e) {
                error_log("Error de conexión a la base de datos de autenticación: " . $e->getMessage());
                throw new Exception("Error de conexión a la base de datos");
            }
        }

        return self::$instance;
    }

    /**
     * Cargar configuración desde archivo .env
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
        } else {
            // Configuración por defecto para desarrollo
            self::$config = [
                'DB_HOST' => 'localhost',
                'DB_PORT' => '5432',
                'DB_NAME' => 'telegan',
                'DB_USER' => 'postgres',
                'DB_PASSWORD' => ''
            ];
        }
    }

    /**
     * Ejecutar consulta preparada con logging de seguridad
     */
    public static function query($sql, $params = array(), $logOperation = null)
    {
        $pdo = self::getInstance();
        
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            // Log de operaciones sensibles
            if ($logOperation) {
                self::logSecurityEvent($logOperation, $sql, $params);
            }
            
            return $stmt;
        } catch (PDOException $e) {
            error_log("Error en consulta de autenticación: " . $e->getMessage() . " SQL: " . $sql);
            throw new Exception("Error en la operación de base de datos");
        }
    }

    /**
     * Obtener un solo registro
     */
    public static function fetch($sql, $params = array(), $logOperation = null)
    {
        $stmt = self::query($sql, $params, $logOperation);
        $result = $stmt->fetch();
        return $result ? $result : null;
    }

    /**
     * Obtener múltiples registros
     */
    public static function fetchAll($sql, $params = array(), $logOperation = null)
    {
        $stmt = self::query($sql, $params, $logOperation);
        return $stmt->fetchAll();
    }

    /**
     * Obtener un solo valor
     */
    public static function fetchColumn($sql, $params = array(), $logOperation = null)
    {
        $stmt = self::query($sql, $params, $logOperation);
        return $stmt->fetchColumn();
    }

    /**
     * Insertar registro y retornar ID
     */
    public static function insert($sql, $params = array(), $logOperation = null)
    {
        $pdo = self::getInstance();
        
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            if ($logOperation) {
                self::logSecurityEvent($logOperation, $sql, $params);
            }
            
            return $pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error en inserción de autenticación: " . $e->getMessage());
            throw new Exception("Error al crear el registro");
        }
    }

    /**
     * Actualizar registros
     */
    public static function update($sql, $params = array(), $logOperation = null)
    {
        $stmt = self::query($sql, $params, $logOperation);
        return $stmt->rowCount();
    }

    /**
     * Iniciar transacción
     */
    public static function beginTransaction()
    {
        $pdo = self::getInstance();
        return $pdo->beginTransaction();
    }

    /**
     * Confirmar transacción
     */
    public static function commit()
    {
        $pdo = self::getInstance();
        return $pdo->commit();
    }

    /**
     * Revertir transacción
     */
    public static function rollback()
    {
        $pdo = self::getInstance();
        return $pdo->rollback();
    }

    /**
     * Log de eventos de seguridad
     */
    private static function logSecurityEvent($operation, $sql, $params)
    {
        try {
            $logSql = "INSERT INTO security_logs (tipo_evento, detalles, severidad) VALUES (?, ?, ?)";
            $logParams = [
                $operation,
                json_encode([
                    'sql' => $sql,
                    'params' => $params,
                    'timestamp' => date('Y-m-d H:i:s')
                ]),
                'INFO'
            ];
            
            $pdo = self::getInstance();
            $stmt = $pdo->prepare($logSql);
            $stmt->execute($logParams);
        } catch (Exception $e) {
            // No fallar si no se puede hacer log
            error_log("Error al hacer log de seguridad: " . $e->getMessage());
        }
    }

    /**
     * Verificar si las tablas de autenticación existen
     */
    public static function checkTablesExist()
    {
        try {
            $sql = "SELECT COUNT(*) FROM information_schema.tables 
                    WHERE table_schema = 'public' 
                    AND table_name IN ('admin_users', 'admin_sessions', 'app_tokens')";
            
            $count = self::fetchColumn($sql);
            return $count >= 3;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Crear tablas de autenticación si no existen
     */
    public static function createTablesIfNotExist()
    {
        if (self::checkTablesExist()) {
            return true;
        }

        try {
            $schemaFile = __DIR__ . '/../database-schema.sql';
            if (file_exists($schemaFile)) {
                $schema = file_get_contents($schemaFile);
                $pdo = self::getInstance();
                $pdo->exec($schema);
                return true;
            }
        } catch (Exception $e) {
            error_log("Error al crear tablas de autenticación: " . $e->getMessage());
        }

        return false;
    }
}


