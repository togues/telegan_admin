<?php
/**
 * Prueba de Configuración - Verificar que todo esté correcto
 */

echo "<h1>🔧 Prueba de Configuración</h1>";

// 1. Verificar archivo .env
echo "<h2>📁 Archivo .env:</h2>";
$envFile = __DIR__ . '/../env';

if (file_exists($envFile)) {
    echo "<p style='color: green;'>✅ Archivo .env encontrado</p>";
    
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $config = [];
    
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $config[trim($key)] = trim($value);
        }
    }
    
    echo "<table border='1' style='border-collapse: collapse; margin: 20px 0;'>";
    echo "<tr><th>Variable</th><th>Valor</th><th>Estado</th></tr>";
    
    $checks = [
        'APP_URL' => 'https://telegan.espacialhn.com/TELEGAN_ADMIN',
        'APP_DOMAIN' => 'telegan.espacialhn.com',
        'APP_ENV' => 'production',
        'DB_HOST' => '157.245.241.220',
        'DB_NAME' => 'telegan',
        'DB_USER' => 'telegan'
    ];
    
    foreach ($checks as $var => $expected) {
        $actual = $config[$var] ?? 'NO CONFIGURADO';
        $isCorrect = ($actual === $expected);
        $status = $isCorrect ? '✅ Correcto' : '❌ Incorrecto';
        $color = $isCorrect ? 'green' : 'red';
        
        echo "<tr>";
        echo "<td><strong>" . $var . "</strong></td>";
        echo "<td>" . htmlspecialchars($actual) . "</td>";
        echo "<td style='color: " . $color . ";'>" . $status . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
} else {
    echo "<p style='color: red;'>❌ Archivo .env NO encontrado</p>";
}

// 2. Verificar detección de entorno
echo "<h2>🌍 Detección de Entorno:</h2>";

try {
    require_once 'config/Environment.php';
    $envConfig = EnvironmentConfig::getConfig();
    
    echo "<table border='1' style='border-collapse: collapse; margin: 20px 0;'>";
    echo "<tr><th>Variable</th><th>Valor</th></tr>";
    echo "<tr><td>Entorno Detectado</td><td><strong>" . htmlspecialchars($envConfig['environment_detected']) . "</strong></td></tr>";
    echo "<tr><td>URL Base</td><td><strong style='color: #6dbe45;'>" . htmlspecialchars($envConfig['base_url']) . "</strong></td></tr>";
    echo "<tr><td>Host</td><td>" . htmlspecialchars($envConfig['host']) . "</td></tr>";
    echo "<tr><td>Protocolo</td><td>" . htmlspecialchars($envConfig['protocol']) . "</td></tr>";
    echo "<tr><td>HTTPS</td><td>" . ($envConfig['is_https'] ? '✅ Sí' : '❌ No') . "</td></tr>";
    echo "</table>";
    
    // Verificar que la URL base sea correcta
    if ($envConfig['base_url'] === 'https://telegan.espacialhn.com/TELEGAN_ADMIN') {
        echo "<p style='color: green;'>✅ URL base correcta para producción</p>";
    } else {
        echo "<p style='color: red;'>❌ URL base incorrecta: " . htmlspecialchars($envConfig['base_url']) . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error en detección de entorno: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// 3. Verificar conexión a base de datos
echo "<h2>🗄️ Conexión a Base de Datos:</h2>";

try {
    $host = '157.245.241.220';
    $port = '5432';
    $dbname = 'telegan';
    $user = 'telegan';
    $password = 'telegan';
    
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p style='color: green;'>✅ Conexión a base de datos exitosa</p>";
    
    // Verificar tabla de usuarios
    $stmt = $pdo->query("SELECT COUNT(*) FROM admin_users");
    $count = $stmt->fetchColumn();
    echo "<p>📊 Usuarios en la base de datos: <strong>" . $count . "</strong></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error de conexión: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// 4. Generar link de prueba
echo "<h2>🔗 Link de Prueba:</h2>";

$testToken = 'test_token_' . time();
$testLink = 'https://telegan.espacialhn.com/TELEGAN_ADMIN/auth/verify-email-simple.php?token=' . urlencode($testToken);

echo "<p><strong>Link de verificación simple:</strong></p>";
echo "<div style='background: #f0f0f0; padding: 15px; border-radius: 5px; margin: 10px 0; word-break: break-all;'>";
echo "<a href='" . htmlspecialchars($testLink) . "' target='_blank' style='color: #6dbe45; text-decoration: none; font-weight: bold;'>";
echo "🔗 " . htmlspecialchars($testLink);
echo "</a>";
echo "</div>";

// 5. Tu token específico
echo "<h2>🎯 Tu Token Específico:</h2>";

$yourToken = '3b2926aaafd5a52af7b2bad9e870f050923bf77cc312becafde4f1e796225fa9';
$yourLink = 'https://telegan.espacialhn.com/TELEGAN_ADMIN/auth/verify-email-simple.php?token=' . urlencode($yourToken);

echo "<p><strong>Link con tu token real:</strong></p>";
echo "<div style='background: #dcfce7; padding: 15px; border-radius: 5px; margin: 10px 0; word-break: break-all; border: 1px solid #22c55e;'>";
echo "<a href='" . htmlspecialchars($yourLink) . "' target='_blank' style='color: #22c55e; text-decoration: none; font-weight: bold; font-size: 16px;'>";
echo "🔗 " . htmlspecialchars($yourLink);
echo "</a>";
echo "</div>";

echo "<h2>📋 Instrucciones:</h2>";
echo "<ol>";
echo "<li>Verifica que todas las configuraciones estén correctas</li>";
echo "<li>Haz clic en el link de tu token específico de arriba</li>";
echo "<li>Si funciona, tu cuenta se activará automáticamente</li>";
echo "<li>Si no funciona, revisar los logs del servidor</li>";
echo "</ol>";

echo "<hr>";
echo "<p><small>Prueba ejecutada el " . date('Y-m-d H:i:s') . "</small></p>";
?>
