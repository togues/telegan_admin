<?php
/**
 * Script de debug para probar login
 * Ejecutar desde línea de comandos: php debug-login.php
 */

// Incluir dependencias
require_once 'config/Security.php';
require_once 'config/Database.php';

echo "==========================================\n";
echo "TELEGAN ADMIN - Debug de Login\n";
echo "==========================================\n\n";

// Datos de prueba
$testEmail = 'test@example.com';
$testPassword = 'password123';

echo "Probando login con:\n";
echo "Email: {$testEmail}\n";
echo "Password: {$testPassword}\n\n";

try {
    // Buscar usuario
    $sql = "SELECT id_admin, nombre_completo, email, password_hash, rol, activo, email_verificado 
            FROM admin_users 
            WHERE email = ?";
    
    $user = AuthDatabase::fetch($sql, [$testEmail]);
    
    if ($user) {
        echo "✅ Usuario encontrado en BD:\n";
        echo "ID: " . $user['id_admin'] . "\n";
        echo "Nombre: " . $user['nombre_completo'] . "\n";
        echo "Email: " . $user['email'] . "\n";
        echo "Rol: " . $user['rol'] . "\n";
        echo "Activo: " . ($user['activo'] ? 'SÍ' : 'NO') . "\n";
        echo "Email verificado: " . ($user['email_verificado'] ? 'SÍ' : 'NO') . "\n";
        echo "Hash (primeros 50 chars): " . substr($user['password_hash'], 0, 50) . "...\n\n";
        
        // Probar verificación de contraseña
        echo "Probando verificación de contraseña:\n";
        $passwordValid = AuthSecurity::verifyPassword($testPassword, $user['password_hash']);
        echo "Contraseña válida: " . ($passwordValid ? '✅ SÍ' : '❌ NO') . "\n";
        
        if (!$passwordValid) {
            echo "\n🔍 Debugging de hash:\n";
            echo "Hash completo: " . $user['password_hash'] . "\n";
            echo "Algoritmo detectado: " . (strpos($user['password_hash'], 'argon2id') !== false ? 'Argon2ID' : 'Desconocido') . "\n";
            
            // Probar con password_hash directo
            $newHash = AuthSecurity::hashPassword($testPassword);
            echo "Nuevo hash generado: " . $newHash . "\n";
            
            $newHashValid = AuthSecurity::verifyPassword($testPassword, $newHash);
            echo "Nuevo hash válido: " . ($newHashValid ? '✅ SÍ' : '❌ NO') . "\n";
        }
        
    } else {
        echo "❌ Usuario NO encontrado en BD\n\n";
        
        // Mostrar todos los usuarios para debug
        echo "📋 Usuarios en BD:\n";
        $allUsers = AuthDatabase::fetchAll("SELECT email, nombre_completo, activo FROM admin_users ORDER BY id_admin");
        foreach ($allUsers as $u) {
            echo "- {$u['email']} ({$u['nombre_completo']}) - " . ($u['activo'] ? 'Activo' : 'Inactivo') . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n==========================================\n";
echo "DEBUG COMPLETADO\n";
echo "==========================================\n";
?>


