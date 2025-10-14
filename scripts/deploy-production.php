<?php
/**
 * Script de Despliegue a Producción
 * Configura automáticamente el entorno para producción
 */

echo "<h1>🚀 Script de Despliegue a Producción - Telegan Admin</h1>";

// Verificar que estamos en el directorio correcto
if (!file_exists(__DIR__ . '/../env.production')) {
    die("❌ Error: No se encontró el archivo env.production");
}

echo "<h2>📋 Pasos de Despliegue:</h2>";

// 1. Backup de configuración actual
echo "<h3>1. 📦 Creando backup de configuración actual...</h3>";
$backupFile = __DIR__ . '/../env.backup.' . date('Y-m-d-H-i-s');
if (copy(__DIR__ . '/../env', $backupFile)) {
    echo "<p style='color: green;'>✅ Backup creado: " . basename($backupFile) . "</p>";
} else {
    echo "<p style='color: red;'>❌ Error al crear backup</p>";
}

// 2. Copiar configuración de producción
echo "<h3>2. 🔧 Configurando para producción...</h3>";
if (copy(__DIR__ . '/../env.production', __DIR__ . '/../env')) {
    echo "<p style='color: green;'>✅ Configuración de producción aplicada</p>";
} else {
    echo "<p style='color: red;'>❌ Error al aplicar configuración de producción</p>";
}

// 3. Verificar configuración
echo "<h3>3. ✅ Verificando configuración...</h3>";
$envFile = __DIR__ . '/../env';
$config = [];

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $config[trim($key)] = trim($value);
        }
    }
}

echo "<table border='1' style='border-collapse: collapse; margin: 20px 0;'>";
echo "<tr><th>Variable</th><th>Valor</th><th>Estado</th></tr>";

$checks = [
    'APP_ENV' => 'production',
    'APP_URL' => 'https://telegan.espacialhn.com/TELEGAN_ADMIN',
    'APP_DOMAIN' => 'telegan.espacialhn.com'
];

foreach ($checks as $var => $expected) {
    $actual = $config[$var] ?? 'NO CONFIGURADO';
    $status = ($actual === $expected) ? '✅ Correcto' : '❌ Incorrecto';
    echo "<tr><td>" . $var . "</td><td>" . htmlspecialchars($actual) . "</td><td>" . $status . "</td></tr>";
}

echo "</table>";

// 4. Generar token de aplicación si no existe
echo "<h3>4. 🔐 Generando token de aplicación...</h3>";
if (!isset($config['APP_TOKEN']) || $config['APP_TOKEN'] === 'generar_con_script_production') {
    $appToken = bin2hex(random_bytes(32));
    
    // Actualizar archivo .env con el token
    $envContent = file_get_contents($envFile);
    $envContent = str_replace('APP_TOKEN=generar_con_script_production', 'APP_TOKEN=' . $appToken, $envContent);
    file_put_contents($envFile, $envContent);
    
    echo "<p style='color: green;'>✅ Token de aplicación generado</p>";
    echo "<p><strong>Token:</strong> " . substr($appToken, 0, 20) . "...</p>";
} else {
    echo "<p style='color: orange;'>⚠️ Token de aplicación ya existe</p>";
}

// 5. Verificar conexión a base de datos
echo "<h3>5. 🗄️ Verificando conexión a base de datos...</h3>";
try {
    require_once __DIR__ . '/../auth/config/Database.php';
    $sql = "SELECT 1";
    Database::fetchColumn($sql);
    echo "<p style='color: green;'>✅ Conexión a base de datos exitosa</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error de conexión a base de datos: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// 6. Verificar sistema de emails
echo "<h3>6. 📧 Verificando sistema de emails...</h3>";
try {
    require_once __DIR__ . '/../auth/config/Email.php';
    require_once __DIR__ . '/../auth/config/Environment.php';
    
    $envConfig = EnvironmentConfig::getConfig();
    echo "<p style='color: green;'>✅ Sistema de emails configurado</p>";
    echo "<p><strong>URL base detectada:</strong> " . htmlspecialchars($envConfig['base_url']) . "</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error en sistema de emails: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// 7. Instrucciones finales
echo "<h3>7. 📋 Instrucciones Finales:</h3>";
echo "<div style='background: #f0f8ff; padding: 20px; border-radius: 8px; border-left: 4px solid #4da1d9;'>";
echo "<h4>🎯 Pasos para completar el despliegue:</h4>";
echo "<ol>";
echo "<li><strong>Subir archivos:</strong> Subir todos los archivos del proyecto al servidor</li>";
echo "<li><strong>Configurar servidor web:</strong> Asegurar que Apache/PHP esté configurado correctamente</li>";
echo "<li><strong>Verificar permisos:</strong> Verificar que PHP puede escribir en logs/ y cache/</li>";
echo "<li><strong>Probar registro:</strong> Probar el registro de un usuario real</li>";
echo "<li><strong>Verificar emails:</strong> Confirmar que los emails de validación llegan correctamente</li>";
echo "</ol>";
echo "</div>";

echo "<h3>🔗 URLs de Prueba:</h3>";
echo "<ul>";
echo "<li><a href='https://telegan.espacialhn.com/TELEGAN_ADMIN/auth/test-environment-detection.php' target='_blank'>Prueba de detección de entorno</a></li>";
echo "<li><a href='https://telegan.espacialhn.com/TELEGAN_ADMIN/auth/register.php' target='_blank'>Registro de usuario</a></li>";
echo "<li><a href='https://telegan.espacialhn.com/TELEGAN_ADMIN/public/dashboard.html' target='_blank'>Dashboard</a></li>";
echo "</ul>";

echo "<hr>";
echo "<p><small>Despliegue ejecutado el " . date('Y-m-d H:i:s') . "</small></p>";
?>
