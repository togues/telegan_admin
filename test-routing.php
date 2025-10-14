<?php

/**
 * Archivo de test para verificar el routing
 */

echo "<h1>Test de Routing</h1>";

echo "<h2>Variables del servidor:</h2>";
echo "<pre>";
echo "REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "\n";
echo "REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD'] . "\n";
echo "PATH_INFO: " . (isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : 'No definido') . "\n";
echo "SCRIPT_NAME: " . $_SERVER['SCRIPT_NAME'] . "\n";
echo "</pre>";

echo "<h2>Rutas de prueba:</h2>";
echo "<ul>";
echo "<li><a href='api/dashboard'>API Dashboard</a></li>";
echo "<li><a href='api/health'>API Health</a></li>";
echo "<li><a href='public/dashboard.html'>Dashboard HTML</a></li>";
echo "</ul>";

echo "<h2>Test de conexión a BD:</h2>";
try {
    require_once __DIR__ . '/src/Config/Database.php';
    $pdo = Database::getInstance();
    echo "<p style='color: green;'>✅ Conexión a base de datos: OK</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error de conexión: " . $e->getMessage() . "</p>";
}

echo "<h2>Test de modelo Dashboard:</h2>";
try {
    require_once __DIR__ . '/src/Models/Dashboard.php';
    $dashboard = new Dashboard();
    $total = $dashboard->getTotalUsuarios();
    echo "<p style='color: green;'>✅ Total usuarios: " . $total . "</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error en modelo: " . $e->getMessage() . "</p>";
}
?>






