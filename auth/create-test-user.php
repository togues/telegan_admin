<?php
/**
 * Script para crear usuario de prueba
 * Ejecutar desde línea de comandos: php create-test-user.php
 */

// Incluir dependencias
require_once 'config/Security.php';
require_once 'config/Database.php';

echo "==========================================\n";
echo "TELEGAN ADMIN - Crear Usuario de Prueba\n";
echo "==========================================\n\n";

$testEmail = 'admin@telegan.com';
$testPassword = 'admin123';
$testName = 'Administrador de Prueba';

echo "Creando usuario de prueba:\n";
echo "Email: {$testEmail}\n";
echo "Password: {$testPassword}\n";
echo "Nombre: {$testName}\n\n";

try {
    // Verificar si el usuario ya existe
    $existingUser = AuthDatabase::fetch("SELECT id_admin FROM admin_users WHERE email = ?", [$testEmail]);
    
    if ($existingUser) {
        echo "⚠️  Usuario ya existe. Actualizando contraseña...\n";
        
        // Actualizar contraseña
        $passwordHash = AuthSecurity::hashPassword($testPassword);
        $updateSql = "UPDATE admin_users SET password_hash = ?, activo = TRUE, email_verificado = TRUE, intentos_login = 0 WHERE email = ?";
        AuthDatabase::update($updateSql, [$passwordHash, $testEmail]);
        
        echo "✅ Usuario actualizado exitosamente\n";
    } else {
        echo "🆕 Creando nuevo usuario...\n";
        
        // Crear usuario
        $passwordHash = AuthSecurity::hashPassword($testPassword);
        $insertSql = "INSERT INTO admin_users (nombre_completo, email, password_hash, rol, activo, email_verificado) 
                      VALUES (?, ?, ?, ?, ?, ?)";
        $params = [
            $testName,
            $testEmail,
            $passwordHash,
            'SUPER_ADMIN',
            TRUE,
            TRUE
        ];
        
        $userId = AuthDatabase::insert($insertSql, $params);
        
        echo "✅ Usuario creado exitosamente con ID: {$userId}\n";
    }
    
    // Verificar que el usuario se puede loguear
    echo "\n🔍 Verificando login...\n";
    $user = AuthDatabase::fetch("SELECT password_hash FROM admin_users WHERE email = ?", [$testEmail]);
    
    if ($user && AuthSecurity::verifyPassword($testPassword, $user['password_hash'])) {
        echo "✅ Login verificado exitosamente\n";
    } else {
        echo "❌ Error en verificación de login\n";
    }
    
    echo "\n📋 Datos del usuario:\n";
    echo "Email: {$testEmail}\n";
    echo "Password: {$testPassword}\n";
    echo "Hash: " . substr($user['password_hash'], 0, 50) . "...\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n==========================================\n";
echo "PROCESO COMPLETADO\n";
echo "==========================================\n";
echo "Ahora puedes loguearte con:\n";
echo "Email: {$testEmail}\n";
echo "Password: {$testPassword}\n";
echo "\nVe a: https://telegan.espacialhn.com/TELEGAN_ADMIN/auth/login.php\n";
?>


