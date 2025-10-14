<?php

/**
 * Debug de variables de entorno
 */

echo "<h1>Debug de Variables de Entorno</h1>";

echo "<h2>Archivo .env existe:</h2>";
$envFile = __DIR__ . '/.env';
echo "<p>Ruta: " . $envFile . "</p>";
echo "<p>Existe: " . (file_exists($envFile) ? 'SÍ' : 'NO') . "</p>";

if (file_exists($envFile)) {
    echo "<h2>Contenido del .env:</h2>";
    $content = file_get_contents($envFile);
    echo "<pre>" . htmlspecialchars($content) . "</pre>";
    
    echo "<h2>Variables cargadas:</h2>";
    
    // Cargar manualmente
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
    
    echo "<pre>";
    foreach ($_ENV as $key => $value) {
        if (strpos($key, 'DB_') === 0) {
            echo "$key = $value\n";
        }
    }
    echo "</pre>";
    
    echo "<h2>Test de conexión:</h2>";
    try {
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $port = $_ENV['DB_PORT'] ?? '5432';
        $dbname = $_ENV['DB_NAME'] ?? 'telegan_agricultores';
        $username = $_ENV['DB_USER'] ?? 'postgres';
        $password = $_ENV['DB_PASSWORD'] ?? '';
        
        echo "<p>Host: $host</p>";
        echo "<p>Port: $port</p>";
        echo "<p>Database: $dbname</p>";
        echo "<p>User: $username</p>";
        echo "<p>Password: " . (empty($password) ? 'VACÍO' : 'CONFIGURADO') . "</p>";
        
        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
        $pdo = new PDO($dsn, $username, $password);
        echo "<p style='color: green;'>✅ Conexión exitosa</p>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Archivo .env no encontrado</p>";
    echo "<p>Crea el archivo .env copiando desde env.example</p>";
}
?>






