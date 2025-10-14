<?php

/**
 * Test endpoint en public/api
 */

// Configurar headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

echo json_encode(array(
    'success' => true,
    'message' => 'Test endpoint funcionando desde public/api',
    'timestamp' => date('Y-m-d H:i:s'),
    'server_info' => array(
        'request_uri' => $_SERVER['REQUEST_URI'],
        'request_method' => $_SERVER['REQUEST_METHOD'],
        'path' => parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
    )
), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>






