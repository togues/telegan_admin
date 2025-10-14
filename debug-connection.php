<?php
/**
 * Script de Debug de Conexión - TELEGAN ADMIN
 * 
 * Verifica qué está pasando con las conexiones
 */

echo "🔍 DEBUG DE CONEXIÓN - TELEGAN ADMIN\n";
echo "====================================\n\n";

// 1. Verificar archivos .env
echo "📁 VERIFICANDO ARCHIVOS DE CONFIGURACIÓN:\n";
echo "------------------------------------------\n";

$envFiles = ['env', '.env'];
foreach ($envFiles as $file) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        echo "✅ $file: EXISTE\n";
        
        // Mostrar configuración de BD
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, 'DB_') === 0) {
                echo "   $line\n";
            }
        }
    } else {
        echo "❌ $file: NO EXISTE\n";
    }
}

echo "\n";

// 2. Probar carga de variables de entorno
echo "🔧 PROBANDO CARGA DE VARIABLES:\n";
echo "--------------------------------\n";

// Simular la carga que hace Database.php
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
    
    echo "✅ Variables cargadas desde .env\n";
    echo "   DB_HOST: " . ($_ENV['DB_HOST'] ?? 'NO DEFINIDO') . "\n";
    echo "   DB_USER: " . ($_ENV['DB_USER'] ?? 'NO DEFINIDO') . "\n";
    echo "   DB_NAME: " . ($_ENV['DB_NAME'] ?? 'NO DEFINIDO') . "\n";
} else {
    echo "❌ No se pudo cargar .env\n";
}

echo "\n";

// 3. Probar conexión directa
echo "🔌 PROBANDO CONEXIÓN DIRECTA:\n";
echo "------------------------------\n";

try {
    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $port = $_ENV['DB_PORT'] ?? '5432';
    $dbname = $_ENV['DB_NAME'] ?? 'telegan';
    $username = $_ENV['DB_USER'] ?? 'telegan';
    $password = $_ENV['DB_PASSWORD'] ?? 'telegan';
    
    $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
    echo "   DSN: $dsn\n";
    echo "   Usuario: $username\n";
    
    $pdo = new PDO($dsn, $username, $password);
    echo "✅ Conexión directa: EXITOSA\n";
    
    // Probar consulta simple
    $stmt = $pdo->query("SELECT COUNT(*) FROM usuario");
    $count = $stmt->fetchColumn();
    echo "✅ Consulta de prueba: $count usuarios encontrados\n";
    
} catch (Exception $e) {
    echo "❌ Error de conexión: " . $e->getMessage() . "\n";
}

echo "\n";

// 4. Probar Database.php
echo "📚 PROBANDO CLASE Database:\n";
echo "----------------------------\n";

try {
    require_once 'src/Config/Database.php';
    
    $count = Database::fetchColumn("SELECT COUNT(*) FROM usuario");
    echo "✅ Database::fetchColumn: $count usuarios encontrados\n";
    
} catch (Exception $e) {
    echo "❌ Error en Database: " . $e->getMessage() . "\n";
}

echo "\n";

// 5. Probar APIs específicas
echo "🌐 PROBANDO APIs:\n";
echo "------------------\n";

$apis = [
    'dashboard.php' => 'Dashboard',
    'search.php' => 'Búsqueda',
    'alerts.php' => 'Alertas'
];

foreach ($apis as $api => $name) {
    $path = __DIR__ . "/public/api/$api";
    if (file_exists($path)) {
        echo "✅ $name ($api): ARCHIVO EXISTE\n";
        
        // Verificar si incluye Database.php
        $content = file_get_contents($path);
        if (strpos($content, 'Database.php') !== false) {
            echo "   - Incluye Database.php\n";
        } else {
            echo "   - NO incluye Database.php\n";
        }
        
        // Verificar si incluye SecurityMiddleware
        if (strpos($content, 'SecurityMiddleware') !== false) {
            echo "   - Incluye SecurityMiddleware\n";
        } else {
            echo "   - NO incluye SecurityMiddleware\n";
        }
    } else {
        echo "❌ $name ($api): NO EXISTE\n";
    }
}

echo "\n";

// 6. Verificar variables de entorno del sistema
echo "🖥️ VARIABLES DE ENTORNO DEL SISTEMA:\n";
echo "--------------------------------------\n";

$systemEnvVars = ['DB_HOST', 'DB_USER', 'DB_NAME', 'DB_PASSWORD'];
foreach ($systemEnvVars as $var) {
    $value = getenv($var);
    if ($value) {
        echo "✅ $var: DEFINIDA EN SISTEMA\n";
    } else {
        echo "❌ $var: NO DEFINIDA EN SISTEMA\n";
    }
}

echo "\n🎯 RESUMEN:\n";
echo "===========\n";
echo "1. Si .env existe y tiene las credenciales correctas\n";
echo "2. Si la conexión directa funciona\n";
echo "3. Si Database.php funciona\n";
echo "4. Entonces el problema puede estar en:\n";
echo "   - SecurityMiddleware bloqueando la conexión\n";
echo "   - Headers CORS\n";
echo "   - Manejo de errores en dashboard.php\n";
echo "\n";
echo "✅ DIAGNÓSTICO COMPLETO\n";
?>

