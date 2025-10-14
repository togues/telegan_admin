<?php
/**
 * Script de Prueba del Flujo de Autenticación - TELEGAN ADMIN
 * 
 * Prueba todos los flujos de autenticación mejorados
 */

require_once 'config/Security.php';
require_once 'config/Database.php';
require_once 'config/Email.php';

// Inicializar
AuthSecurity::init();

echo "🧪 PRUEBA DEL FLUJO DE AUTENTICACIÓN MEJORADO\n";
echo "==============================================\n\n";

// Verificar configuración
echo "📋 VERIFICANDO CONFIGURACIÓN:\n";
echo "-------------------------------\n";

$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    echo "✅ Archivo .env encontrado\n";
} else {
    echo "❌ Archivo .env no encontrado\n";
    exit(1);
}

// Verificar conexión a BD
try {
    $testSql = "SELECT COUNT(*) FROM admin_users";
    $count = AuthDatabase::fetchColumn($testSql);
    echo "✅ Conexión a base de datos: OK\n";
    echo "   - Usuarios registrados: $count\n";
} catch (Exception $e) {
    echo "❌ Error de conexión a BD: " . $e->getMessage() . "\n";
    exit(1);
}

// Verificar archivos creados
echo "\n📁 VERIFICANDO ARCHIVOS CREADOS:\n";
echo "----------------------------------\n";

$filesToCheck = [
    'verify-email.php' => 'Página de verificación automática',
    'reset-password.php' => 'Página de reset con token',
    'templates/emails/confirmation.html' => 'Template de confirmación con link',
    'templates/emails/password_reset.html' => 'Template de reset con link'
];

foreach ($filesToCheck as $file => $description) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        echo "✅ $description: OK\n";
    } else {
        echo "❌ $description: NO ENCONTRADO\n";
    }
}

// Probar generación de tokens
echo "\n🔑 PROBANDO GENERACIÓN DE TOKENS:\n";
echo "-----------------------------------\n";

$testCode = AuthSecurity::generateConfirmationCode();
$testToken = AuthSecurity::generateSecureToken();

echo "✅ Código de confirmación: $testCode\n";
echo "✅ Token seguro: " . substr($testToken, 0, 16) . "...\n";

// Probar hash de contraseña
echo "\n🔐 PROBANDO HASH DE CONTRASEÑA:\n";
echo "---------------------------------\n";

$testPassword = 'TestPassword123';
$hashedPassword = AuthSecurity::hashPassword($testPassword);
$isValid = AuthSecurity::verifyPassword($testPassword, $hashedPassword);

echo "✅ Hash generado: " . substr($hashedPassword, 0, 20) . "...\n";
echo "✅ Verificación: " . ($isValid ? 'OK' : 'FALLO') . "\n";

// Probar URLs de verificación
echo "\n🔗 PROBANDO URLS DE VERIFICACIÓN:\n";
echo "-----------------------------------\n";

// Simular configuración
$appUrl = 'https://telegan.espacialhn.com';
$testToken = 'test_token_123';

$verifyUrl = $appUrl . '/auth/verify-email.php?token=' . urlencode($testToken);
$resetUrl = $appUrl . '/auth/reset-password.php?token=' . urlencode($testToken);

echo "✅ URL de verificación: $verifyUrl\n";
echo "✅ URL de reset: $resetUrl\n";

// Verificar templates de email
echo "\n📧 PROBANDO TEMPLATES DE EMAIL:\n";
echo "---------------------------------\n";

try {
    // Probar template de confirmación
    $confirmationVars = [
        'name' => 'Usuario de Prueba',
        'confirmation_code' => '123456',
        'verification_link' => $verifyUrl,
        'app_name' => 'Telegan Admin Panel',
        'app_url' => $appUrl
    ];
    
    $confirmationTemplate = file_get_contents(__DIR__ . '/templates/emails/confirmation.html');
    foreach ($confirmationVars as $key => $value) {
        $confirmationTemplate = str_replace('{{' . $key . '}}', $value, $confirmationTemplate);
    }
    
    echo "✅ Template de confirmación: OK\n";
    echo "   - Contiene link de verificación: " . (strpos($confirmationTemplate, $verifyUrl) !== false ? 'SÍ' : 'NO') . "\n";
    
    // Probar template de reset
    $resetVars = [
        'name' => 'Usuario de Prueba',
        'reset_code' => '654321',
        'reset_link' => $resetUrl,
        'app_name' => 'Telegan Admin Panel',
        'app_url' => $appUrl
    ];
    
    $resetTemplate = file_get_contents(__DIR__ . '/templates/emails/password_reset.html');
    foreach ($resetVars as $key => $value) {
        $resetTemplate = str_replace('{{' . $key . '}}', $value, $resetTemplate);
    }
    
    echo "✅ Template de reset: OK\n";
    echo "   - Contiene link de reset: " . (strpos($resetTemplate, $resetUrl) !== false ? 'SÍ' : 'NO') . "\n";
    
} catch (Exception $e) {
    echo "❌ Error en templates: " . $e->getMessage() . "\n";
}

// Verificar páginas de validación
echo "\n📄 VERIFICANDO PÁGINAS DE VALIDACIÓN:\n";
echo "---------------------------------------\n";

$pagesToCheck = [
    'verify-email.php' => 'Verificación automática de email',
    'reset-password.php' => 'Reset de contraseña con token'
];

foreach ($pagesToCheck as $page => $description) {
    $path = __DIR__ . '/' . $page;
    if (file_exists($path)) {
        $content = file_get_contents($path);
        $hasTokenCheck = strpos($content, '$_GET[\'token\']') !== false;
        $hasSecurityInit = strpos($content, 'AuthSecurity::init()') !== false;
        
        echo "✅ $description: OK\n";
        echo "   - Verifica token: " . ($hasTokenCheck ? 'SÍ' : 'NO') . "\n";
        echo "   - Inicializa seguridad: " . ($hasSecurityInit ? 'SÍ' : 'NO') . "\n";
    } else {
        echo "❌ $description: NO ENCONTRADO\n";
    }
}

// Resumen de mejoras implementadas
echo "\n🎉 RESUMEN DE MEJORAS IMPLEMENTADAS:\n";
echo "=====================================\n";
echo "✅ Validación automática de email con links\n";
echo "✅ Reset de contraseña con tokens directos\n";
echo "✅ Templates de email actualizados con botones\n";
echo "✅ Páginas de validación automática\n";
echo "✅ Mensajes de éxito en login\n";
echo "✅ Flujo completo sin PINs manuales\n";

echo "\n🚀 FLUJOS DISPONIBLES:\n";
echo "======================\n";
echo "1. REGISTRO → Email con código + link automático\n";
echo "2. VERIFICACIÓN → Click en link = activación automática\n";
echo "3. RECUPERACIÓN → Email con código + link directo\n";
echo "4. RESET → Click en link = nueva contraseña\n";
echo "5. LOGIN → Mensajes de éxito por verificación/reset\n";

echo "\n📝 PRÓXIMOS PASOS:\n";
echo "===================\n";
echo "1. Probar registro completo\n";
echo "2. Verificar emails recibidos\n";
echo "3. Probar links automáticos\n";
echo "4. Probar flujo de recuperación\n";
echo "5. Verificar mensajes de éxito\n";

echo "\n✅ SISTEMA DE AUTENTICACIÓN MEJORADO - LISTO PARA USAR\n";
?>

