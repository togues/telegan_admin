<?php
/**
 * Script de prueba para la API del dashboard
 * Verifica que todos los métodos funcionan correctamente
 */

// Incluir dependencias
require_once '../../src/Config/Database.php';
require_once '../../src/Models/Dashboard.php';

echo "<h1>🧪 Prueba de la API del Dashboard</h1>";

try {
    // Crear instancia del modelo Dashboard
    $dashboard = new Dashboard();
    
    echo "<h2>📊 Prueba de Métodos Básicos:</h2>";
    
    // Probar métodos uno por uno
    $tests = [
        'getTotalUsuarios' => $dashboard->getTotalUsuarios(),
        'getUsuariosActivos' => $dashboard->getUsuariosActivos(),
        'getTotalFincas' => $dashboard->getTotalFincas(),
        'getTotalPotreros' => $dashboard->getTotalPotreros(),
        'getTotalRegistrosGanaderos' => $dashboard->getTotalRegistrosGanaderos(),
        'getUsuariosAdministradores' => $dashboard->getUsuariosAdministradores(),
        'getUsuariosColaboradores' => $dashboard->getUsuariosColaboradores(),
    ];
    
    echo "<table border='1' style='border-collapse: collapse; margin: 20px 0;'>";
    echo "<tr><th>Método</th><th>Resultado</th><th>Estado</th></tr>";
    
    foreach ($tests as $method => $result) {
        $status = (is_numeric($result) && $result >= 0) ? '✅ OK' : '❌ Error';
        echo "<tr><td>" . $method . "</td><td>" . $result . "</td><td>" . $status . "</td></tr>";
    }
    
    echo "</table>";
    
    echo "<h2>🚨 Prueba de Métodos de Alertas:</h2>";
    
    $alertTests = [
        'getUsuariosSinFinca' => $dashboard->getUsuariosSinFinca(),
        'getUsuariosInactivos' => $dashboard->getUsuariosInactivos(),
        'getUsuariosNuncaLogueados' => $dashboard->getUsuariosNuncaLogueados(),
        'getUsuariosSinDemografia' => $dashboard->getUsuariosSinDemografia(),
        'getFincasSinPotreros' => $dashboard->getFincasSinPotreros(),
        'getFincasSinActividad' => $dashboard->getFincasSinActividad(),
        'getFincasAreaSospechosa' => $dashboard->getFincasAreaSospechosa(),
    ];
    
    echo "<table border='1' style='border-collapse: collapse; margin: 20px 0;'>";
    echo "<tr><th>Método</th><th>Resultado</th><th>Estado</th></tr>";
    
    foreach ($alertTests as $method => $result) {
        $status = (is_array($result)) ? '✅ OK' : '❌ Error';
        echo "<tr><td>" . $method . "</td><td>" . json_encode($result) . "</td><td>" . $status . "</td></tr>";
    }
    
    echo "</table>";
    
    echo "<h2>🔗 Prueba de Resumen de Alertas:</h2>";
    
    $resumen = $dashboard->getResumenAlertas();
    
    if ($resumen && isset($resumen['usuarios']) && isset($resumen['fincas'])) {
        echo "<p style='color: green;'>✅ Resumen de alertas generado correctamente</p>";
        echo "<pre>" . json_encode($resumen, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    } else {
        echo "<p style='color: red;'>❌ Error al generar resumen de alertas</p>";
    }
    
    echo "<h2>🗄️ Prueba de Conexión a Base de Datos:</h2>";
    
    if ($dashboard->verificarConexion()) {
        echo "<p style='color: green;'>✅ Conexión a base de datos exitosa</p>";
        
        $dbInfo = $dashboard->getInfoBaseDatos();
        echo "<pre>" . json_encode($dbInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    } else {
        echo "<p style='color: red;'>❌ Error de conexión a base de datos</p>";
    }
    
    echo "<h2>📋 Simulación de API Completa:</h2>";
    
    // Simular la respuesta completa de la API
    $estadisticas = [
        'total_usuarios' => $dashboard->getTotalUsuarios(),
        'total_fincas' => $dashboard->getTotalFincas(),
        'total_potreros' => $dashboard->getTotalPotreros(),
        'total_registros_ganaderos' => $dashboard->getTotalRegistrosGanaderos(),
        'usuarios_activos' => $dashboard->getUsuariosActivos(),
        'usuarios_administradores' => $dashboard->getUsuariosAdministradores(),
        'usuarios_colaboradores' => $dashboard->getUsuariosColaboradores(),
    ];
    
    $alertas = [
        'usuarios_sin_finca' => $dashboard->getUsuariosSinFinca()['total_usuarios_sin_finca'] ?? 0,
        'fincas_sin_potreros' => $dashboard->getFincasSinPotreros()['fincas_sin_potreros'] ?? 0,
        'usuarios_inactivos' => $dashboard->getUsuariosInactivos()['usuarios_inactivos_30dias'] ?? 0,
        'fincas_sin_actividad' => $dashboard->getFincasSinActividad()['fincas_sin_actividad_30dias'] ?? 0,
    ];
    
    $sistema = [
        'version' => '1.0.0',
        'entorno' => 'development',
        'timestamp' => date('Y-m-d H:i:s'),
        'servidor' => $_SERVER['HTTP_HOST'] ?? 'localhost',
        'base_datos' => $dashboard->getInfoBaseDatos(),
    ];
    
    $response = [
        'success' => true,
        'data' => [
            'estadisticas' => $estadisticas,
            'alertas' => $alertas,
            'sistema' => $sistema
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo "<p style='color: green;'>✅ API simulada correctamente</p>";
    echo "<pre style='background: #f0f0f0; padding: 15px; border-radius: 5px; overflow-x: auto;'>";
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "<hr>";
echo "<p><small>Prueba ejecutada el " . date('Y-m-d H:i:s') . "</small></p>";
?>
