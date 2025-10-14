<?php
/**
 * Script de prueba para verificar el sistema de emails
 * Genera un email de prueba con un link funcional
 */

// Incluir dependencias
require_once 'config/Email.php';
require_once 'config/Database.php';

echo "<h2>🧪 Prueba del Sistema de Emails - Telegan Admin</h2>\n";

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

echo "<h3>📧 Configuración de Email:</h3>\n";
echo "<ul>\n";
echo "<li><strong>APP_URL:</strong> " . ($config['APP_URL'] ?? 'NO CONFIGURADO') . "</li>\n";
echo "<li><strong>MAIL_FROM_NAME:</strong> " . ($config['MAIL_FROM_NAME'] ?? 'NO CONFIGURADO') . "</li>\n";
echo "<li><strong>MAIL_FROM_EMAIL:</strong> " . ($config['MAIL_FROM_EMAIL'] ?? 'NO CONFIGURADO') . "</li>\n";
echo "<li><strong>MAIL_REPLY_TO:</strong> " . ($config['MAIL_REPLY_TO'] ?? 'NO CONFIGURADO') . "</li>\n";
echo "</ul>\n";

// Generar datos de prueba
$testEmail = 'marco.rios@example.com';
$testName = 'Marco Antonio Rios Torres';
$testCode = '123456';
$testToken = 'test_token_' . time();

echo "<h3>🔗 Generación de Link de Verificación:</h3>\n";
$verificationLink = $config['APP_URL'] . '/auth/verify-email.php?token=' . urlencode($testToken);
echo "<p><strong>Link generado:</strong></p>\n";
echo "<p style='background: #f0f0f0; padding: 10px; border-radius: 5px; word-break: break-all;'>";
echo "<a href='" . htmlspecialchars($verificationLink) . "' target='_blank'>" . htmlspecialchars($verificationLink) . "</a>";
echo "</p>\n";

echo "<h3>📨 Template de Email:</h3>\n";
echo "<p>El email incluirá:</p>\n";
echo "<ul>\n";
echo "<li>✅ Botón funcional con link de verificación</li>\n";
echo "<li>📱 Diseño responsive (iOS style)</li>\n";
echo "<li>🎨 Colores de marca Telegan</li>\n";
echo "<li>⏰ Información de expiración (1 hora)</li>\n";
echo "</ul>\n";

// Simular envío de email (sin enviar realmente)
echo "<h3>🧪 Simulación de Envío:</h3>\n";

// Generar el template completo
$template = EmailManager::getEmailTemplate('confirmation', [
    'name' => $testName,
    'confirmation_code' => $testCode,
    'verification_link' => $verificationLink,
    'app_name' => $config['APP_NAME'] ?? 'Telegan Admin Panel',
    'app_url' => $config['APP_URL'] ?? 'http://localhost'
]);

if ($template) {
    echo "<p style='color: green;'>✅ Template generado correctamente</p>\n";
    echo "<p><strong>Tamaño del template:</strong> " . strlen($template) . " caracteres</p>\n";
    
    // Verificar que el link está en el template
    if (strpos($template, $verificationLink) !== false) {
        echo "<p style='color: green;'>✅ Link de verificación incluido en el template</p>\n";
    } else {
        echo "<p style='color: red;'>❌ Link de verificación NO encontrado en el template</p>\n";
    }
    
    // Verificar que es un enlace HTML válido
    if (strpos($template, 'href="' . $verificationLink . '"') !== false) {
        echo "<p style='color: green;'>✅ Enlace HTML válido generado</p>\n";
    } else {
        echo "<p style='color: red;'>❌ Enlace HTML inválido</p>\n";
    }
    
} else {
    echo "<p style='color: red;'>❌ Error al generar template</p>\n";
}

echo "<h3>🔍 Diagnóstico:</h3>\n";

// Verificar configuración crítica
$issues = [];
if (!isset($config['APP_URL']) || empty($config['APP_URL'])) {
    $issues[] = "APP_URL no está configurado";
}
if (!isset($config['MAIL_FROM_EMAIL']) || empty($config['MAIL_FROM_EMAIL'])) {
    $issues[] = "MAIL_FROM_EMAIL no está configurado";
}

if (empty($issues)) {
    echo "<p style='color: green;'>✅ Configuración correcta</p>\n";
} else {
    echo "<p style='color: red;'>❌ Problemas encontrados:</p>\n";
    echo "<ul>\n";
    foreach ($issues as $issue) {
        echo "<li style='color: red;'>" . htmlspecialchars($issue) . "</li>\n";
    }
    echo "</ul>\n";
}

echo "<h3>📋 Próximos Pasos:</h3>\n";
echo "<ol>\n";
echo "<li>Verificar que el servidor web puede enviar emails (función mail() habilitada)</li>\n";
echo "<li>Probar el registro real con un email válido</li>\n";
echo "<li>Verificar que el link del email abre correctamente</li>\n";
echo "<li>Confirmar que la activación funciona en la base de datos</li>\n";
echo "</ol>\n";

echo "<hr>\n";
echo "<p><small>Script de prueba ejecutado el " . date('Y-m-d H:i:s') . "</small></p>\n";
?>
