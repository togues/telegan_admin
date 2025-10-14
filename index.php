<?php

/**
 * Punto de entrada principal
 * Sistema de routing manual
 */

// Obtener la ruta solicitada
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

// Debug: Ver qué ruta está llegando
error_log("Request URI: " . $requestUri);
error_log("Path: " . $path);

// Limpiar la ruta
$path = trim($path, '/');

// Routing manual
if (empty($path) || $path === 'index.php') {
    // Página principal - redirigir al dashboard
    header('Location: public/dashboard.html');
    exit();
} elseif (strpos($path, 'api/') === 0) {
    // Rutas de API
    $apiPath = substr($path, 4); // Remover 'api/'
    error_log("API Path: " . $apiPath);
    
    // Limpiar la ruta de API
    $apiPath = trim($apiPath, '/');
    
    if (file_exists(__DIR__ . '/api/' . $apiPath . '.php')) {
        // Incluir archivo específico de API
        error_log("Including: " . __DIR__ . '/api/' . $apiPath . '.php');
        include __DIR__ . '/api/' . $apiPath . '.php';
    } else {
        // Usar router general
        error_log("Using router for: " . $apiPath);
        include __DIR__ . '/api/router.php';
    }
} else {
    // Archivos estáticos
    $filePath = __DIR__ . '/' . $path;
    
    if (file_exists($filePath) && is_file($filePath)) {
        // Servir archivo estático
        $mimeType = mime_content_type($filePath);
        header('Content-Type: ' . $mimeType);
        readfile($filePath);
    } else {
        // 404 - Archivo no encontrado
        http_response_code(404);
        echo '<!DOCTYPE html>
<html>
<head>
    <title>404 - No encontrado</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
        h1 { color: #666; }
    </style>
</head>
<body>
    <h1>404 - Página no encontrada</h1>
    <p>La página solicitada no existe.</p>
    <p>Ruta solicitada: ' . htmlspecialchars($path) . '</p>
    <a href="public/dashboard.html">Ir al Dashboard</a>
</body>
</html>';
    }
}
