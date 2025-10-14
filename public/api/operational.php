<?php
/**
 * API Endpoint para Datos Operativos del Dashboard
 * Retorna estadísticas operativas administrativas
 */

// Incluir dependencias
require_once '../../src/Config/Database.php';
require_once '../../src/Models/Dashboard.php';

// Configurar headers CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Función para enviar respuesta JSON
function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

// Función para manejar errores
function handleError($message, $status = 500) {
    sendResponse([
        'success' => false,
        'error' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ], $status);
}

try {
    // Verificar método HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        handleError('Método no permitido', 405);
    }

    // Crear instancia del modelo Dashboard
    $dashboard = new Dashboard();

    // Verificar conexión a la base de datos
    if (!$dashboard->verificarConexion()) {
        handleError('Error de conexión a la base de datos', 503);
    }

    // Obtener datos operativos
    $datosOperativos = $dashboard->getDatosOperativos();
    
    sendResponse([
        'success' => true,
        'data' => $datosOperativos,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    error_log("Error en operational.php: " . $e->getMessage());
    handleError('Error interno del servidor', 500);
}
?>



