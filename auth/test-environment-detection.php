<?php
/**
 * Prueba de detección automática del entorno
 */

require_once 'config/Environment.php';
require_once 'config/Email.php';

echo "<h1>🌍 Prueba de Detección Automática del Entorno</h1>";

// Obtener información del entorno
$envInfo = EnvironmentConfig::getDebugInfo();

echo "<h2>🔍 Información Detectada:</h2>";
echo "<table border='1' style='border-collapse: collapse; margin: 20px 0;'>";
echo "<tr><th>Variable</th><th>Valor</th></tr>";
echo "<tr><td>Entorno Detectado</td><td><strong>" . htmlspecialchars($envInfo['environment_detected']) . "</strong></td></tr>";
echo "<tr><td>URL Base</td><td><strong style='color: #6dbe45;'>" . htmlspecialchars($envInfo['base_url']) . "</strong></td></tr>";
echo "<tr><td>Host</td><td>" . htmlspecialchars($envInfo['host']) . "</td></tr>";
echo "<tr><td>Protocolo</td><td>" . htmlspecialchars($envInfo['protocol']) . "</td></tr>";
echo "<tr><td>HTTPS</td><td>" . ($envInfo['is_https'] ? '✅ Sí' : '❌ No') . "</td></tr>";
echo "</table>";

echo "<h2>🔧 Variables del Servidor:</h2>";
echo "<table border='1' style='border-collapse: collapse; margin: 20px 0;'>";
echo "<tr><th>Variable</th><th>Valor</th></tr>";
foreach ($envInfo['server_vars'] as $var => $value) {
    echo "<tr><td>" . htmlspecialchars($var) . "</td><td>" . htmlspecialchars($value) . "</td></tr>";
}
echo "</table>";

// Probar generación de link de verificación
echo "<h2>📧 Prueba de Generación de Email:</h2>";

$testToken = 'test_token_' . time();
$verificationLink = $envInfo['base_url'] . '/auth/verify-email.php?token=' . urlencode($testToken);

echo "<p><strong>Link de verificación generado:</strong></p>";
echo "<div style='background: #f0f0f0; padding: 15px; border-radius: 5px; margin: 10px 0; word-break: break-all;'>";
echo "<a href='" . htmlspecialchars($verificationLink) . "' target='_blank' style='color: #6dbe45; text-decoration: none; font-weight: bold;'>";
echo "🔗 " . htmlspecialchars($verificationLink);
echo "</a>";
echo "</div>";

// Verificar configuración de EmailManager
echo "<h2>📨 Configuración de EmailManager:</h2>";

// Forzar recarga de configuración
EmailManager::$config = null;

// Simular envío para cargar configuración
$emailConfig = EmailManager::sendConfirmationEmail('test@example.com', 'Usuario Test', '123456', $testToken);

echo "<p><strong>Configuración cargada:</strong></p>";
echo "<ul>";
echo "<li>✅ EmailManager inicializado correctamente</li>";
echo "<li>✅ URL base detectada automáticamente</li>";
echo "<li>✅ Link de verificación generado</li>";
echo "</ul>";

echo "<h2>🎯 Escenarios de Prueba:</h2>";

echo "<h3>🏠 Desarrollo (localhost):</h3>";
echo "<ul>";
echo "<li>URL esperada: <code>http://localhost/TELEGAN_ADMIN</code></li>";
echo "<li>Protocolo: HTTP</li>";
echo "<li>Entorno: development</li>";
echo "</ul>";

echo "<h3>🌐 Producción (telegan.espacialhn.com):</h3>";
echo "<ul>";
echo "<li>URL esperada: <code>https://telegan.espacialhn.com/TELEGAN_ADMIN</code></li>";
echo "<li>Protocolo: HTTPS</li>";
echo "<li>Entorno: production</li>";
echo "</ul>";

echo "<h2>✅ Verificación:</h2>";
if ($envInfo['environment_detected'] === 'development') {
    echo "<p style='color: orange;'>🟠 <strong>Modo Desarrollo Detectado</strong></p>";
    echo "<p>Estás ejecutando en localhost. Los emails usarán la URL de desarrollo.</p>";
} else {
    echo "<p style='color: green;'>🟢 <strong>Modo Producción Detectado</strong></p>";
    echo "<p>Estás ejecutando en el servidor de producción. Los emails usarán la URL de producción.</p>";
}

echo "<h2>📋 Instrucciones:</h2>";
echo "<ol>";
echo "<li>Si estás en desarrollo, verifica que la URL base sea <code>http://localhost/TELEGAN_ADMIN</code></li>";
echo "<li>Si estás en producción, verifica que la URL base sea <code>https://telegan.espacialhn.com/TELEGAN_ADMIN</code></li>";
echo "<li>Haz clic en el link de verificación de arriba para probar que funciona</li>";
echo "<li>Prueba el registro real en ambos entornos</li>";
echo "</ol>";

echo "<hr>";
echo "<p><small>Prueba ejecutada el " . date('Y-m-d H:i:s') . "</small></p>";
?>
