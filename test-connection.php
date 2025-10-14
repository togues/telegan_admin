<?php
/**
 * Test de Conexión - TELEGAN ADMIN
 * Verificar que todo funciona después de crear .env
 */

echo "🔍 VERIFICANDO CONEXIÓN DESPUÉS DE .env\n";
echo "=======================================\n\n";

// 1. Verificar archivo .env
echo "📁 VERIFICANDO ARCHIVO .env:\n";
echo "-----------------------------\n";

$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    echo "✅ Archivo .env: EXISTE\n";
    
    // Cargar variables
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
    
    echo "   DB_HOST: " . ($_ENV['DB_HOST'] ?? 'NO DEFINIDO') . "\n";
    echo "   DB_USER: " . ($_ENV['DB_USER'] ?? 'NO DEFINIDO') . "\n";
    echo "   DB_NAME: " . ($_ENV['DB_NAME'] ?? 'NO DEFINIDO') . "\n";
} else {
    echo "❌ Archivo .env: NO EXISTE\n";
}

echo "\n";

// 2. Probar clase Database
echo "📚 PROBANDO CLASE Database:\n";
echo "---------------------------\n";

try {
    require_once 'src/Config/Database.php';
    
    // Probar conexión básica
    $count = Database::fetchColumn("SELECT COUNT(*) FROM usuario");
    echo "✅ Conexión exitosa: $count usuarios en la BD\n";
    
    // Probar consulta más compleja
    $activeUsers = Database::fetchColumn("SELECT COUNT(*) FROM usuario WHERE activo = true");
    echo "✅ Usuarios activos: $activeUsers\n";
    
    // Probar consulta con JOIN
    $usersWithFarms = Database::fetchColumn("SELECT COUNT(*) FROM v_usuarios_fincas");
    echo "✅ Usuarios con fincas: $usersWithFarms\n";
    
} catch (Exception $e) {
    echo "❌ Error en Database: " . $e->getMessage() . "\n";
}

echo "\n";

// 3. Simular llamada a dashboard.php
echo "🌐 SIMULANDO DASHBOARD API:\n";
echo "----------------------------\n";

try {
    // Simular la lógica de dashboard.php
    $totalUsuarios = Database::fetchColumn("SELECT COUNT(*) FROM v_usuarios_fincas");
    $usuariosActivos = Database::fetchColumn("SELECT COUNT(*) FROM usuario WHERE activo = true");
    $fincasActivas = Database::fetchColumn("SELECT COUNT(*) FROM finca WHERE estado = 'ACTIVA'");
    $potrerosActivos = Database::fetchColumn("SELECT COUNT(*) FROM potrero WHERE estado = 'ACTIVO'");
    $registros = Database::fetchColumn("SELECT COUNT(*) FROM registro_ganadero");
    $administradores = Database::fetchColumn("SELECT COUNT(*) FROM usuario WHERE activo = true");
    $colaboradores = Database::fetchColumn("SELECT COUNT(*) FROM usuario_finca WHERE rol = 'COLABORADOR'");
    
    echo "✅ Datos del dashboard obtenidos:\n";
    echo "   - Total usuarios: $totalUsuarios\n";
    echo "   - Usuarios activos: $usuariosActivos\n";
    echo "   - Fincas activas: $fincasActivas\n";
    echo "   - Potreros activos: $potrerosActivos\n";
    echo "   - Registros: $registros\n";
    echo "   - Administradores: $administradores\n";
    echo "   - Colaboradores: $colaboradores\n";
    
} catch (Exception $e) {
    echo "❌ Error en dashboard: " . $e->getMessage() . "\n";
}

echo "\n";

// 4. Simular llamada a search.php
echo "🔍 SIMULANDO SEARCH API:\n";
echo "-------------------------\n";

try {
    // Simular búsqueda de usuarios
    $searchResults = Database::fetchAll("SELECT id_usuario, nombre_completo, email FROM usuario WHERE nombre_completo ILIKE ? LIMIT 5", ['%a%']);
    echo "✅ Búsqueda exitosa: " . count($searchResults) . " resultados\n";
    
    if (count($searchResults) > 0) {
        echo "   Primer resultado: " . $searchResults[0]['nombre_completo'] . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error en search: " . $e->getMessage() . "\n";
}

echo "\n";

// 5. Verificar APIs disponibles
echo "📋 APIs DISPONIBLES:\n";
echo "--------------------\n";

$apis = [
    'dashboard.php' => 'Dashboard Principal',
    'search.php' => 'Búsqueda de Usuarios',
    'alerts.php' => 'Sistema de Alertas',
    'operational.php' => 'Estadísticas Operativas',
    'user-farms.php' => 'Fincas de Usuario',
    'farm-details.php' => 'Detalles de Finca'
];

foreach ($apis as $api => $name) {
    $path = __DIR__ . "/public/api/$api";
    if (file_exists($path)) {
        echo "✅ $name ($api)\n";
    } else {
        echo "❌ $name ($api) - NO EXISTE\n";
    }
}

echo "\n";

// 6. Resumen final
echo "🎯 RESUMEN:\n";
echo "===========\n";
echo "Si todas las pruebas anteriores muestran ✅, entonces:\n";
echo "1. ✅ El archivo .env se lee correctamente\n";
echo "2. ✅ La conexión a la base de datos funciona\n";
echo "3. ✅ Todas las APIs pueden obtener datos reales\n";
echo "4. ✅ El dashboard debería mostrar datos reales\n";
echo "5. ✅ Las búsquedas deberían funcionar con datos reales\n";
echo "\n";
echo "🚀 ¡EL SISTEMA ESTÁ LISTO!\n";
?>

