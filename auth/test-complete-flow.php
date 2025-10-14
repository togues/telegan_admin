<?php
/**
 * Prueba del flujo completo de registro y verificación
 */

// Incluir dependencias
require_once 'config/Database.php';
require_once 'config/Security.php';
require_once 'config/Email.php';

echo "<h1>🧪 Prueba del Flujo Completo de Registro</h1>";

// Inicializar seguridad
AuthSecurity::init();

try {
    // Datos de prueba
    $testEmail = 'marco.rios.test@example.com';
    $testName = 'Marco Antonio Rios Torres';
    $testPhone = '+50499999999';
    $testPassword = 'TestPassword123!';
    
    echo "<h2>📝 Datos de Prueba:</h2>";
    echo "<ul>";
    echo "<li><strong>Email:</strong> " . htmlspecialchars($testEmail) . "</li>";
    echo "<li><strong>Nombre:</strong> " . htmlspecialchars($testName) . "</li>";
    echo "<li><strong>Teléfono:</strong> " . htmlspecialchars($testPhone) . "</li>";
    echo "</ul>";
    
    // Limpiar datos de prueba anteriores
    echo "<h2>🧹 Limpiando datos de prueba anteriores...</h2>";
    
    $cleanupSql = "DELETE FROM admin_users WHERE email = ?";
    Database::update($cleanupSql, [$testEmail]);
    echo "<p style='color: green;'>✅ Datos de prueba anteriores eliminados</p>";
    
    // Simular registro
    echo "<h2>📋 Simulando Registro:</h2>";
    
    // Generar código y token de confirmación
    $confirmationCode = sprintf('%06d', rand(100000, 999999));
    $confirmationToken = bin2hex(random_bytes(32));
    $expirationTime = date('Y-m-d H:i:s', time() + 3600); // 1 hora
    
    echo "<ul>";
    echo "<li><strong>Código generado:</strong> " . $confirmationCode . "</li>";
    echo "<li><strong>Token generado:</strong> " . substr($confirmationToken, 0, 20) . "...</li>";
    echo "<li><strong>Expira:</strong> " . $expirationTime . "</li>";
    echo "</ul>";
    
    // Hash de contraseña
    $passwordHash = password_hash($testPassword, PASSWORD_ARGON2ID);
    
    // Insertar usuario de prueba
    $insertSql = "INSERT INTO admin_users (
        nombre_completo, email, password_hash, telefono, 
        codigo_confirmacion, token_confirmacion, expiracion_confirmacion,
        activo, email_verificado, rol, created_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?, FALSE, FALSE, 'TECNICO', 'SYSTEM_TEST')";
    
    $userId = Database::insert($insertSql, [
        $testName, $testEmail, $passwordHash, $testPhone,
        $confirmationCode, $confirmationToken, $expirationTime
    ]);
    
    if ($userId) {
        echo "<p style='color: green;'>✅ Usuario de prueba creado con ID: " . $userId . "</p>";
        
        // Insertar en confirmaciones pendientes
        $confirmSql = "INSERT INTO pending_confirmations (email, codigo_confirmacion, token_confirmacion, tipo_confirmacion, fecha_expiracion) VALUES (?, ?, ?, 'registration', ?)";
        Database::insert($confirmSql, [$testEmail, $confirmationCode, $confirmationToken, $expirationTime]);
        echo "<p style='color: green;'>✅ Confirmación pendiente registrada</p>";
        
        // Generar link de verificación
        $verificationLink = "http://localhost/TELEGAN_ADMIN/auth/verify-email.php?token=" . urlencode($confirmationToken);
        
        echo "<h2>🔗 Link de Verificación:</h2>";
        echo "<div style='background: #f0f0f0; padding: 15px; border-radius: 5px; margin: 10px 0; word-break: break-all;'>";
        echo "<a href='" . htmlspecialchars($verificationLink) . "' target='_blank' style='color: #6dbe45; text-decoration: none; font-weight: bold;'>";
        echo "🔗 HACER CLIC AQUÍ PARA ACTIVAR LA CUENTA";
        echo "</a>";
        echo "</div>";
        
        echo "<h2>📧 Simulación de Email:</h2>";
        echo "<p>El email se enviaría con:</p>";
        echo "<ul>";
        echo "<li>✅ Botón funcional con el link de arriba</li>";
        echo "<li>📱 Diseño responsive</li>";
        echo "<li>🎨 Colores de marca Telegan</li>";
        echo "<li>⏰ Código de verificación: <strong>" . $confirmationCode . "</strong></li>";
        echo "</ul>";
        
        // Probar verificación automática
        echo "<h2>✅ Prueba de Verificación:</h2>";
        echo "<p>Haz clic en el link de arriba para probar la verificación automática.</p>";
        echo "<p>Si funciona correctamente:</p>";
        echo "<ul>";
        echo "<li>✅ La cuenta se activará automáticamente</li>";
        echo "<li>✅ Se enviará email de bienvenida</li>";
        echo "<li>✅ Se registrará en logs de seguridad</li>";
        echo "<li>✅ Serás redirigido al login</li>";
        echo "</ul>";
        
        // Mostrar estado actual del usuario
        echo "<h2>📊 Estado Actual del Usuario:</h2>";
        $userSql = "SELECT id_admin, nombre_completo, email, activo, email_verificado, rol FROM admin_users WHERE email = ?";
        $user = Database::fetch($userSql, [$testEmail]);
        
        if ($user) {
            echo "<table border='1' style='border-collapse: collapse; margin: 20px 0;'>";
            echo "<tr><th>Campo</th><th>Valor</th></tr>";
            echo "<tr><td>ID</td><td>" . $user['id_admin'] . "</td></tr>";
            echo "<tr><td>Nombre</td><td>" . htmlspecialchars($user['nombre_completo']) . "</td></tr>";
            echo "<tr><td>Email</td><td>" . htmlspecialchars($user['email']) . "</td></tr>";
            echo "<tr><td>Activo</td><td>" . ($user['activo'] ? '✅ SÍ' : '❌ NO') . "</td></tr>";
            echo "<tr><td>Email Verificado</td><td>" . ($user['email_verificado'] ? '✅ SÍ' : '❌ NO') . "</td></tr>";
            echo "<tr><td>Rol</td><td>" . htmlspecialchars($user['rol']) . "</td></tr>";
            echo "</table>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ Error al crear usuario de prueba</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    error_log("Error en prueba de flujo completo: " . $e->getMessage());
}

echo "<h2>🧹 Limpieza:</h2>";
echo "<p>Para limpiar los datos de prueba, ejecuta:</p>";
echo "<code>DELETE FROM admin_users WHERE email = 'marco.rios.test@example.com';</code>";

echo "<hr>";
echo "<p><small>Prueba ejecutada el " . date('Y-m-d H:i:s') . "</small></p>";
?>
