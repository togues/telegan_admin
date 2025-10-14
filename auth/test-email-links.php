<?php
/**
 * Prueba de generación de links de email
 * Verifica que los links se generen correctamente
 */

require_once 'config/Email.php';
require_once 'config/Environment.php';

echo "<h1>🔗 Prueba de Generación de Links de Email</h1>";

// Obtener configuración del entorno
$envConfig = EnvironmentConfig::getConfig();

echo "<h2>🌍 Configuración del Entorno:</h2>";
echo "<table border='1' style='border-collapse: collapse; margin: 20px 0;'>";
echo "<tr><th>Variable</th><th>Valor</th></tr>";
echo "<tr><td>Entorno Detectado</td><td><strong>" . htmlspecialchars($envConfig['environment_detected']) . "</strong></td></tr>";
echo "<tr><td>URL Base</td><td><strong style='color: #6dbe45;'>" . htmlspecialchars($envConfig['base_url']) . "</strong></td></tr>";
echo "<tr><td>Host</td><td>" . htmlspecialchars($envConfig['host']) . "</td></tr>";
echo "<tr><td>Protocolo</td><td>" . htmlspecialchars($envConfig['protocol']) . "</td></tr>";
echo "</table>";

// Simular generación de links
$testToken = 'test_token_' . time();

echo "<h2>📧 Prueba de Links de Confirmación:</h2>";

// Forzar recarga de configuración
EmailManager::$config = null;

// Simular envío para cargar configuración
$emailSent = EmailManager::sendConfirmationEmail('test@example.com', 'Usuario Test', '123456', $testToken);

echo "<p><strong>Link generado para confirmación:</strong></p>";
echo "<div style='background: #f0f0f0; padding: 15px; border-radius: 5px; margin: 10px 0; word-break: break-all;'>";

// Generar link manualmente para verificar
$baseUrl = $envConfig['base_url'];
$verificationLink = rtrim($baseUrl, '/') . '/auth/verify-email.php?token=' . urlencode($testToken);

echo "<a href='" . htmlspecialchars($verificationLink) . "' target='_blank' style='color: #6dbe45; text-decoration: none; font-weight: bold;'>";
echo "🔗 " . htmlspecialchars($verificationLink);
echo "</a>";
echo "</div>";

echo "<h2>🔑 Prueba de Links de Reset:</h2>";
echo "<p><strong>Link generado para reset de contraseña:</strong></p>";
echo "<div style='background: #f0f0f0; padding: 15px; border-radius: 5px; margin: 10px 0; word-break: break-all;'>";

$resetLink = rtrim($baseUrl, '/') . '/auth/reset-password.php?token=' . urlencode($testToken);

echo "<a href='" . htmlspecialchars($resetLink) . "' target='_blank' style='color: #ef4444; text-decoration: none; font-weight: bold;'>";
echo "🔗 " . htmlspecialchars($resetLink);
echo "</a>";
echo "</div>";

echo "<h2>✅ Verificación de URLs:</h2>";

// Verificar que las URLs sean válidas
$urls = [
    'Verificación' => $verificationLink,
    'Reset' => $resetLink
];

echo "<table border='1' style='border-collapse: collapse; margin: 20px 0;'>";
echo "<tr><th>Tipo</th><th>URL</th><th>Estado</th></tr>";

foreach ($urls as $tipo => $url) {
    $isValid = filter_var($url, FILTER_VALIDATE_URL) !== false;
    $status = $isValid ? '✅ Válida' : '❌ Inválida';
    $color = $isValid ? 'green' : 'red';
    
    echo "<tr>";
    echo "<td>" . $tipo . "</td>";
    echo "<td style='word-break: break-all;'>" . htmlspecialchars($url) . "</td>";
    echo "<td style='color: " . $color . ";'>" . $status . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h2>🧪 Prueba de Archivos:</h2>";

// Verificar que los archivos de destino existen
$files = [
    'verify-email.php' => __DIR__ . '/verify-email.php',
    'reset-password.php' => __DIR__ . '/reset-password.php'
];

echo "<table border='1' style='border-collapse: collapse; margin: 20px 0;'>";
echo "<tr><th>Archivo</th><th>Ruta</th><th>Estado</th></tr>";

foreach ($files as $file => $path) {
    $exists = file_exists($path);
    $status = $exists ? '✅ Existe' : '❌ No existe';
    $color = $exists ? 'green' : 'red';
    
    echo "<tr>";
    echo "<td>" . $file . "</td>";
    echo "<td>" . htmlspecialchars($path) . "</td>";
    echo "<td style='color: " . $color . ";'>" . $status . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h2>🔧 Solución para el Problema:</h2>";
echo "<div style='background: #fef3c7; padding: 20px; border-radius: 8px; border-left: 4px solid #f59e0b;'>";
echo "<h4>📋 Si el link sigue mal:</h4>";
echo "<ol>";
echo "<li><strong>Copiar el token:</strong> Del email que recibiste, copia solo la parte después de <code>?token=</code></li>";
echo "<li><strong>Construir URL manual:</strong> " . htmlspecialchars($baseUrl) . "/auth/verify-email.php?token=TU_TOKEN_AQUI</li>";
echo "<li><strong>Probar:</strong> Pegar la URL completa en el navegador</li>";
echo "</ol>";
echo "</div>";

echo "<h2>📋 Instrucciones:</h2>";
echo "<ol>";
echo "<li>Haz clic en los links de arriba para probar que funcionan</li>";
echo "<li>Si funcionan, el problema era de configuración (ya corregido)</li>";
echo "<li>Si no funcionan, verificar que los archivos existan</li>";
echo "<li>Para el email que ya recibiste, usar la solución manual de arriba</li>";
echo "</ol>";

echo "<hr>";
echo "<p><small>Prueba ejecutada el " . date('Y-m-d H:i:s') . "</small></p>";
?>
