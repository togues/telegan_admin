<?php

/**
 * Clase para manejo de conexión a PostgreSQL
 * PHP Vanilla - Sin frameworks, sin namespaces
 */
class Database
{
    private static $instance = null;

    /**
     * Obtener instancia única de PDO
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            try {
                // Cargar variables de entorno
                self::loadEnv();
                
                $host = $_ENV['DB_HOST'] ?? 'localhost';
                $port = $_ENV['DB_PORT'] ?? '5432';
                $dbname = $_ENV['DB_NAME'] ?? 'telegan_agricultores';
                $username = $_ENV['DB_USER'] ?? 'postgres';
                $password = $_ENV['DB_PASSWORD'] ?? '';

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
                    )
                );
            } catch (PDOException $e) {
                throw new Exception("Error de conexión a la base de datos: " . $e->getMessage());
            }
        }

        return self::$instance;
    }

    /**
     * Cargar variables de entorno desde archivo .env
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
     * Ejecutar consulta preparada
     */
    public static function query($sql, $params = array())
    {
        $pdo = self::getInstance();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Obtener un solo registro
     */
    public static function fetch($sql, $params = array())
    {
        $stmt = self::query($sql, $params);
        $result = $stmt->fetch();
        return $result ? $result : null;
    }

    /**
     * Obtener múltiples registros
     */
    public static function fetchAll($sql, $params = array())
    {
        $stmt = self::query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Obtener un solo valor
     */
    public static function fetchColumn($sql, $params = array())
    {
        $stmt = self::query($sql, $params);
        return $stmt->fetchColumn();
    }

    /**
     * Insertar registro y retornar ID
     */
    public static function insert($sql, $params = array())
    {
        $pdo = self::getInstance();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $pdo->lastInsertId();
    }

    /**
     * Actualizar registros
     */
    public static function update($sql, $params = array())
    {
        $stmt = self::query($sql, $params);
        return $stmt->rowCount();
    }
}
