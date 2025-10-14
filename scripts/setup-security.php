<?php
/**
 * Script de Configuración de Seguridad - TELEGAN ADMIN
 * 
 * Configura el sistema de seguridad gradualmente sin romper funcionalidad
 */

echo "🔧 CONFIGURACIÓN DE SEGURIDAD TELEGAN ADMIN\n";
echo "==========================================\n\n";

// Verificar archivo .env
$envFile = __DIR__ . '/../.env';
if (!file_exists($envFile)) {
    echo "❌ Archivo .env no encontrado\n";
    echo "📝 Creando archivo .env desde env.example...\n";
    
    $exampleFile = __DIR__ . '/../env.example';
    if (file_exists($exampleFile)) {
        copy($exampleFile, $envFile);
        echo "✅ Archivo .env creado\n";
    } else {
        echo "❌ Archivo env.example no encontrado\n";
        exit(1);
    }
}

// Cargar configuración actual
$config = [];
$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

foreach ($lines as $line) {
    if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
        list($key, $value) = explode('=', $line, 2);
        $config[trim($key)] = trim($value);
    }
}

echo "📋 CONFIGURACIÓN ACTUAL:\n";
echo "------------------------\n";
echo "APP_ENV: " . ($config['APP_ENV'] ?? 'development') . "\n";
echo "APP_DOMAIN: " . ($config['APP_DOMAIN'] ?? 'localhost') . "\n";
echo "APP_SECRET: " . (isset($config['APP_SECRET']) ? '***configurado***' : 'NO CONFIGURADO') . "\n\n";

// Generar token de aplicación si no existe
if (!isset($config['APP_TOKEN']) || $config['APP_TOKEN'] === 'generar_con_script') {
    echo "🔑 Generando token de aplicación...\n";
    
    $domain = $config['APP_DOMAIN'] ?? 'localhost';
    $timestamp = time();
    $secret = $config['APP_SECRET'] ?? 'telegan_default_secret';
    
    $appToken = hash('sha256', $domain . $timestamp . $secret);
    
    // Actualizar archivo .env
    $newLines = [];
    $tokenUpdated = false;
    
    foreach ($lines as $line) {
        if (strpos($line, 'APP_TOKEN=') === 0) {
            $newLines[] = "APP_TOKEN={$appToken}";
            $tokenUpdated = true;
        } else {
            $newLines[] = $line;
        }
    }
    
    if (!$tokenUpdated) {
        $newLines[] = "APP_TOKEN={$appToken}";
    }
    
    file_put_contents($envFile, implode("\n", $newLines));
    
    echo "✅ Token de aplicación generado: " . substr($appToken, 0, 16) . "...\n";
}

// Crear directorio de logs si no existe
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
    echo "📁 Directorio de logs creado\n";
}

// Crear archivo .htaccess para seguridad adicional
$htaccessFile = __DIR__ . '/../public/.htaccess';
$htaccessContent = <<<'HTACCESS'
# TELEGAN ADMIN - Configuración de Seguridad

# Prevenir acceso a archivos sensibles
<Files ".env">
    Order allow,deny
    Deny from all
</Files>

<Files "*.log">
    Order allow,deny
    Deny from all
</Files>

# Headers de seguridad
<IfModule mod_headers.c>
    Header always set X-Frame-Options DENY
    Header always set X-Content-Type-Options nosniff
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>

# Prevenir listado de directorios
Options -Indexes

# Configuración de CORS (desarrollo)
<IfModule mod_headers.c>
    Header set Access-Control-Allow-Origin "*"
    Header set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
    Header set Access-Control-Allow-Headers "Content-Type, Authorization, X-App-Token, X-App-Timestamp"
</IfModule>
HTACCESS;

if (!file_exists($htaccessFile)) {
    file_put_contents($htaccessFile, $htaccessContent);
    echo "🔒 Archivo .htaccess de seguridad creado\n";
}

// Mostrar instrucciones de uso
echo "\n📚 INSTRUCCIONES DE USO:\n";
echo "========================\n";
echo "1. DESARROLLO (actual):\n";
echo "   - Las APIs funcionan sin validación\n";
echo "   - CORS abierto para desarrollo\n";
echo "   - Logs de peticiones habilitados\n\n";

echo "2. PARA ACTIVAR VALIDACIÓN GRADUAL:\n";
echo "   - Editar APIs y agregar: SecurityMiddleware::requireAppToken(false)\n";
echo "   - El frontend ya envía tokens automáticamente\n";
echo "   - En desarrollo, la validación es flexible\n\n";

echo "3. PARA PRODUCCIÓN:\n";
echo "   - Cambiar APP_ENV=production en .env\n";
echo "   - Usar SecurityMiddleware::requireAuth(true)\n";
echo "   - Configurar CORS restrictivo\n\n";

echo "4. ENDPOINTS DE PRUEBA:\n";
echo "   - GET /api/dashboard.php (con validación gradual)\n";
echo "   - Headers enviados automáticamente por frontend\n\n";

echo "✅ CONFIGURACIÓN COMPLETADA\n";
echo "🚀 El sistema está listo para desarrollo seguro\n\n";

// Mostrar ejemplo de uso
echo "💡 EJEMPLO DE USO EN API:\n";
echo "========================\n";
echo "<?php\n";
echo "require_once '../../src/Middleware/SecurityMiddleware.php';\n";
echo "SecurityMiddleware::init();\n\n";
echo "// Opción 1: Solo logging\n";
echo "SecurityMiddleware::publicApi();\n\n";
echo "// Opción 2: Token de app (desarrollo)\n";
echo "SecurityMiddleware::requireAppToken(false);\n\n";
echo "// Opción 3: Autenticación completa (producción)\n";
echo "SecurityMiddleware::requireAuth(true);\n";
echo "?>\n\n";

echo "🎯 PRÓXIMOS PASOS:\n";
echo "==================\n";
echo "1. Probar API dashboard.php\n";
echo "2. Revisar logs en /logs/\n";
echo "3. Gradualmente activar validación en otras APIs\n";
echo "4. Configurar dominio real en APP_DOMAIN\n";
echo "5. Cambiar a modo producción cuando esté listo\n\n";

echo "🔐 Sistema de seguridad configurado exitosamente!\n";
?>

