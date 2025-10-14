<?php

/**
 * Health Check Endpoint
 * PHP Vanilla - Sin frameworks
 */

// Cargar clases necesarias
require_once __DIR__ . '/../src/Config/Database.php';
require_once __DIR__ . '/../src/Models/Dashboard.php';

// Configurar headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $dashboard = new Dashboard();
    
    // Verificar conexión a base de datos
    $conectado = $dashboard->verificarConexion();
    
    // Información del sistema
    $info = array(
        'status' => $conectado ? 'healthy' : 'unhealthy',
        'timestamp' => date('Y-m-d H:i:s'),
        'database' => array(
            'connected' => $conectado,
            'status' => $conectado ? 'OK' : 'ERROR'
        ),
        'php' => array(
            'version' => PHP_VERSION,
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true)
        ),
        'server' => array(
            'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown'
        )
    );
    
    if ($conectado) {
        $info['database']['version'] = $dashboard->getInfoBaseDatos();
    }
    
    http_response_code($conectado ? 200 : 503);
    echo json_encode($info, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array(
        'status' => 'error',
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ), JSON_UNESCAPED_UNICODE);
}






