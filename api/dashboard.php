<?php

// Configurar headers para JSON y CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Cargar clases PHP vanilla (sin autoloader)
require_once __DIR__ . '/../src/Config/Database.php';
require_once __DIR__ . '/../src/Models/Dashboard.php';

try {
    // Crear instancia del modelo Dashboard
    $dashboard = new Dashboard();
    
    // Verificar método HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método no permitido');
    }
    
    // Obtener estadísticas del dashboard
    $estadisticas = $dashboard->getEstadisticasCompletas();
    
    // Verificar conexión a BD
    $infoBD = $dashboard->getInfoBaseDatos();
    
    // Respuesta exitosa
    echo json_encode(array(
        'success' => true,
        'data' => array(
            'estadisticas' => $estadisticas,
            'base_datos' => $infoBD
        ),
        'timestamp' => date('Y-m-d H:i:s')
    ), JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Respuesta de error
    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ), JSON_UNESCAPED_UNICODE);
    
    // Log del error
    error_log("Error en dashboard API: " . $e->getMessage());
}
