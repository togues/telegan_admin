<?php
/**
 * API de Debug para verificar datos de usuarios
 */

// Incluir dependencias
require_once '../../src/Config/Database.php';

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

try {
    // Verificar método HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendResponse(['error' => 'Método no permitido'], 405);
    }

    // Consulta SQL simple para verificar datos
    $sql = "
        SELECT 
            id_usuario,
            nombre_completo,
            email,
            telefono,
            ubicacion_general,
            activo,
            email_verificado,
            telefono_verificado,
            fecha_registro,
            ultima_sesion,
            codigo_telegan
        FROM usuario 
        ORDER BY id_usuario 
        LIMIT 10
    ";

    // Ejecutar consulta
    $results = Database::fetchAll($sql, []);

    // Mostrar resultados raw
    sendResponse([
        'success' => true,
        'data' => $results,
        'count' => count($results),
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    error_log("Error en debug-users.php: " . $e->getMessage());
    sendResponse([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], 500);
}
?>



