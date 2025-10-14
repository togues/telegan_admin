<?php
/**
 * Herramienta para corregir links de email malformados
 */

echo "<h1>🔧 Corrector de Links de Email</h1>";

echo "<h2>📧 ¿Tienes un token de verificación?</h2>";
echo "<p>Si recibiste un email con un link malformado, puedes usar esta herramienta para generar el link correcto.</p>";

// Obtener configuración del entorno
require_once 'config/Environment.php';
$envConfig = EnvironmentConfig::getConfig();
$baseUrl = $envConfig['base_url'];

echo "<h3>🌍 Tu URL Base:</h3>";
echo "<p><code>" . htmlspecialchars($baseUrl) . "</code></p>";

echo "<h3>🔑 Ingresa tu token:</h3>";
echo "<form method='GET' style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<p><strong>Token de verificación:</strong></p>";
echo "<input type='text' name='token' placeholder='Pega aquí el token de tu email' style='width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-family: monospace;'>";
echo "<br><br>";
echo "<button type='submit' style='background: #6dbe45; color: white; border: none; padding: 12px 24px; border-radius: 5px; font-weight: bold;'>Generar Link Correcto</button>";
echo "</form>";

// Procesar token si se proporcionó
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = trim($_GET['token']);
    
    echo "<h2>✅ Link Corregido:</h2>";
    
    $correctLink = rtrim($baseUrl, '/') . '/auth/verify-email.php?token=' . urlencode($token);
    
    echo "<div style='background: #dcfce7; padding: 20px; border-radius: 8px; border-left: 4px solid #22c55e;'>";
    echo "<h4>🎯 Tu link correcto es:</h4>";
    echo "<div style='background: white; padding: 15px; border-radius: 5px; margin: 10px 0; word-break: break-all; border: 1px solid #22c55e;'>";
    echo "<a href='" . htmlspecialchars($correctLink) . "' target='_blank' style='color: #22c55e; text-decoration: none; font-weight: bold; font-size: 16px;'>";
    echo "🔗 " . htmlspecialchars($correctLink);
    echo "</a>";
    echo "</div>";
    echo "<p><strong>Instrucciones:</strong></p>";
    echo "<ol>";
    echo "<li>Haz clic en el link de arriba</li>";
    echo "<li>O copia y pega la URL en tu navegador</li>";
    echo "<li>Tu cuenta se activará automáticamente</li>";
    echo "</ol>";
    echo "</div>";
    
    echo "<h3>🧪 Verificación:</h3>";
    
    // Verificar que el token sea válido
    try {
        require_once 'config/Database.php';
        
        $checkSql = "SELECT id_admin, email, nombre_completo FROM admin_users WHERE token_confirmacion = ? AND expiracion_confirmacion > NOW() AND activo = FALSE";
        $user = Database::fetch($checkSql, [$token]);
        
        if ($user) {
            echo "<div style='background: #dcfce7; padding: 15px; border-radius: 5px; color: #166534;'>";
            echo "✅ <strong>Token válido:</strong> El token es válido y no ha expirado.<br>";
            echo "👤 <strong>Usuario:</strong> " . htmlspecialchars($user['nombre_completo']) . "<br>";
            echo "📧 <strong>Email:</strong> " . htmlspecialchars($user['email']);
            echo "</div>";
        } else {
            echo "<div style='background: #fef2f2; padding: 15px; border-radius: 5px; color: #dc2626;'>";
            echo "❌ <strong>Token inválido:</strong> El token no existe, ha expirado o ya fue usado.<br>";
            echo "💡 <strong>Solución:</strong> Solicita un nuevo email de verificación.";
            echo "</div>";
        }
        
    } catch (Exception $e) {
        echo "<div style='background: #fef2f2; padding: 15px; border-radius: 5px; color: #dc2626;'>";
        echo "⚠️ <strong>Error:</strong> No se pudo verificar el token: " . htmlspecialchars($e->getMessage());
        echo "</div>";
    }
}

echo "<h2>📋 Instrucciones Generales:</h2>";
echo "<div style='background: #eff6ff; padding: 20px; border-radius: 8px; border-left: 4px solid #3b82f6;'>";
echo "<h4>🔍 Cómo encontrar tu token:</h4>";
echo "<ol>";
echo "<li>Abre el email de verificación que recibiste</li>";
echo "<li>Busca el link que empieza con <code>http:///auth/verify-email.php?token=</code></li>";
echo "<li>Copia solo la parte después de <code>?token=</code></li>";
echo "<li>Pégala en el campo de arriba</li>";
echo "</ol>";

echo "<h4>🚨 Si no encuentras el token:</h4>";
echo "<ul>";
echo "<li>Verifica tu carpeta de spam</li>";
echo "<li>Busca emails de 'Telegan Admin' o 'noreply@telegan.com'</li>";
echo "<li>Si no lo encuentras, <a href='register.php'>registra una nueva cuenta</a></li>";
echo "</ul>";
echo "</div>";

echo "<hr>";
echo "<p><small>Herramienta ejecutada el " . date('Y-m-d H:i:s') . "</small></p>";
?>
