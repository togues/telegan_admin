<?php
/**
 * Debug del sistema de emails - Verificar que el link se genera correctamente
 */

// Incluir dependencias
require_once 'config/Email.php';

echo "<h1>🔍 Debug del Sistema de Emails</h1>";

// Cargar configuración
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

echo "<h2>📧 Configuración Actual:</h2>";
echo "<table border='1' style='border-collapse: collapse; margin: 20px 0;'>";
echo "<tr><th>Variable</th><th>Valor</th></tr>";
echo "<tr><td>APP_URL</td><td>" . htmlspecialchars($config['APP_URL'] ?? 'NO CONFIGURADO') . "</td></tr>";
echo "<tr><td>MAIL_FROM_NAME</td><td>" . htmlspecialchars($config['MAIL_FROM_NAME'] ?? 'NO CONFIGURADO') . "</td></tr>";
echo "<tr><td>MAIL_FROM_EMAIL</td><td>" . htmlspecialchars($config['MAIL_FROM_EMAIL'] ?? 'NO CONFIGURADO') . "</td></tr>";
echo "<tr><td>MAIL_REPLY_TO</td><td>" . htmlspecialchars($config['MAIL_REPLY_TO'] ?? 'NO CONFIGURADO') . "</td></tr>";
echo "</table>";

// Simular generación de link
$testToken = 'test_token_123456';
$verificationLink = $config['APP_URL'] . '/auth/verify-email.php?token=' . urlencode($testToken);

echo "<h2>🔗 Link de Verificación Generado:</h2>";
echo "<p><strong>Link completo:</strong></p>";
echo "<div style='background: #f0f0f0; padding: 15px; border-radius: 5px; margin: 10px 0; word-break: break-all;'>";
echo "<a href='" . htmlspecialchars($verificationLink) . "' target='_blank' style='color: #6dbe45; text-decoration: none;'>";
echo htmlspecialchars($verificationLink);
echo "</a>";
echo "</div>";

// Verificar que el archivo verify-email.php existe
$verifyFile = __DIR__ . '/verify-email.php';
if (file_exists($verifyFile)) {
    echo "<p style='color: green;'>✅ Archivo verify-email.php existe</p>";
} else {
    echo "<p style='color: red;'>❌ Archivo verify-email.php NO existe</p>";
}

// Simular template de email
echo "<h2>📨 Vista Previa del Email:</h2>";
echo "<div style='border: 1px solid #ddd; padding: 20px; border-radius: 8px; background: white; max-width: 600px;'>";

$testName = 'Marco Antonio Rios Torres';
$testCode = '123456';

echo "<div style='background: linear-gradient(135deg, #6dbe45 0%, #4da1d9 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0;'>";
echo "<h1 style='margin: 0; font-size: 24px;'>Telegan Admin Panel</h1>";
echo "</div>";

echo "<div style='padding: 30px;'>";
echo "<h2>¡Hola " . htmlspecialchars($testName) . "!</h2>";
echo "<p>Gracias por registrarte en <strong>Telegan Admin Panel</strong>. Para activar tu cuenta y comenzar a usar el panel administrativo, necesitamos verificar tu dirección de email.</p>";
echo "<p>Para activar tu cuenta, simplemente haz clic en el botón de abajo:</p>";

echo "<div style='text-align: center; margin: 30px 0;'>";
echo "<a href='" . htmlspecialchars($verificationLink) . "' style='display: inline-block; background: linear-gradient(135deg, #6dbe45 0%, #4da1d9 100%); color: white; padding: 16px 32px; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 16px;'>";
echo "✅ Activar Mi Cuenta";
echo "</a>";
echo "</div>";

echo "<p><strong>⏰ El link y código son válidos por 1 hora</strong> por motivos de seguridad.</p>";
echo "</div>";

echo "</div>";

// Verificar problemas comunes
echo "<h2>🔍 Diagnóstico:</h2>";
echo "<ul>";

if (isset($config['APP_URL']) && !empty($config['APP_URL'])) {
    echo "<li style='color: green;'>✅ APP_URL configurado</li>";
} else {
    echo "<li style='color: red;'>❌ APP_URL no configurado</li>";
}

if (strpos($config['APP_URL'] ?? '', 'localhost') !== false) {
    echo "<li style='color: orange;'>⚠️ Usando localhost - verificar que coincide con tu servidor</li>";
}

if (file_exists($verifyFile)) {
    echo "<li style='color: green;'>✅ Archivo de verificación existe</li>";
} else {
    echo "<li style='color: red;'>❌ Archivo de verificación NO existe</li>";
}

echo "</ul>";

echo "<h2>📋 Instrucciones:</h2>";
echo "<ol>";
echo "<li>Haz clic en el link de arriba para probar la verificación</li>";
echo "<li>Si funciona, el problema está en el envío del email</li>";
echo "<li>Si no funciona, verificar la configuración de APP_URL</li>";
echo "<li>Probar registro real con un email válido</li>";
echo "</ol>";

echo "<hr>";
echo "<p><small>Debug ejecutado el " . date('Y-m-d H:i:s') . "</small></p>";
?>
