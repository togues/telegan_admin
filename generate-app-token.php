<?php
/**
 * Script para generar token de aplicación único
 * Ejecutar una sola vez al instalar el sistema
 */

// Cargar variables de entorno
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// Generar token único
$domain = $_ENV['APP_DOMAIN'] ?? 'localhost';
$timestamp = time();
$secret = $_ENV['APP_SECRET'] ?? 'telegan_default_secret_' . uniqid();
$appToken = hash('sha256', $domain . $timestamp . $secret);

echo "==========================================\n";
echo "TELEGAN ADMIN - Generador de Token de App\n";
echo "==========================================\n\n";

echo "Dominio configurado: " . $domain . "\n";
echo "Timestamp: " . $timestamp . "\n";
echo "Secret: " . $secret . "\n";
echo "Token generado: " . $appToken . "\n\n";

echo "==========================================\n";
echo "INSTRUCCIONES:\n";
echo "==========================================\n";
echo "1. Copia el token generado\n";
echo "2. Agrega esta línea a tu archivo .env:\n";
echo "   APP_TOKEN=" . $appToken . "\n";
echo "3. Guarda el archivo .env\n";
echo "4. Reinicia tu servidor web\n\n";

echo "¡Token generado exitosamente!\n";
?>


