<?php
/**
 * Script de prueba para envío de emails
 * Ejecutar desde línea de comandos: php test-email.php
 */

// Incluir dependencias
require_once 'config/Email.php';

echo "==========================================\n";
echo "TELEGAN ADMIN - Prueba de Envío de Emails\n";
echo "==========================================\n\n";

// Email de prueba
$testEmail = 'test@example.com';
$testName = 'Usuario de Prueba';

echo "Probando envío de emails a: {$testEmail}\n\n";

// Probar email de confirmación
echo "1. Probando email de confirmación...\n";
$confirmationResult = EmailManager::sendConfirmationEmail($testEmail, $testName, '123456');
echo $confirmationResult ? "✅ Email de confirmación enviado\n" : "❌ Error en email de confirmación\n";

// Probar email de recuperación
echo "2. Probando email de recuperación...\n";
$resetResult = EmailManager::sendPasswordResetEmail($testEmail, $testName, '654321');
echo $resetResult ? "✅ Email de recuperación enviado\n" : "❌ Error en email de recuperación\n";

// Probar email de bienvenida
echo "3. Probando email de bienvenida...\n";
$welcomeResult = EmailManager::sendWelcomeEmail($testEmail, $testName);
echo $welcomeResult ? "✅ Email de bienvenida enviado\n" : "❌ Error en email de bienvenida\n";

echo "\n==========================================\n";
echo "PRUEBA COMPLETADA\n";
echo "==========================================\n";

if ($confirmationResult && $resetResult && $welcomeResult) {
    echo "🎉 ¡Todos los emails se enviaron correctamente!\n";
    echo "Revisa tu bandeja de entrada y carpeta de spam.\n";
} else {
    echo "⚠️  Algunos emails fallaron. Revisa la configuración del servidor.\n";
    echo "\nPosibles problemas:\n";
    echo "- Función mail() no está habilitada en PHP\n";
    echo "- Servidor no tiene configuración SMTP\n";
    echo "- Firewall bloqueando puerto 25/587\n";
    echo "- Configuración de DNS incorrecta\n";
}

echo "\nPara habilitar mail() en PHP:\n";
echo "1. Instalar sendmail: sudo apt-get install sendmail\n";
echo "2. Configurar sendmail: sudo sendmailconfig\n";
echo "3. O usar SMTP externo (Gmail, etc.)\n";
?>


