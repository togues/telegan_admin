<?php
/**
 * API Endpoint para Alertas del Dashboard
 * Retorna métricas críticas y alertas administrativas
 */

// Incluir dependencias
require_once '../../src/Config/Database.php';
require_once '../../src/Config/ApiAuth.php';
require_once '../../src/Models/Dashboard.php';

// Configurar headers CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Token, X-API-Timestamp, X-Session-Token');

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
    
    // Validar token de autenticación (ApiAuth normaliza el path automáticamente)
    $validation = ApiAuth::validateRequest();
    
    if (!$validation['valid']) {
        handleError('Acceso no autorizado: ' . ($validation['error'] ?? 'Token inválido'), 401);
    }

    // Crear instancia del modelo Dashboard
    $dashboard = new Dashboard();

    // Verificar conexión a la base de datos
    if (!$dashboard->verificarConexion()) {
        handleError('Error de conexión a la base de datos', 503);
    }

    // Obtener tipo de alerta desde query parameter
    $tipo = $_GET['tipo'] ?? 'resumen';

    switch ($tipo) {
        case 'resumen':
            // Resumen ejecutivo de todas las alertas
            $alertas = $dashboard->getResumenAlertas();
            sendResponse([
                'success' => true,
                'data' => $alertas,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        case 'usuarios':
            // Alertas específicas de usuarios
            $alertas = [
                'sin_finca' => $dashboard->getUsuariosSinFinca(),
                'inactivos' => $dashboard->getUsuariosInactivos(),
                'nunca_logueados' => $dashboard->getUsuariosNuncaLogueados(),
                'sin_demografia' => $dashboard->getUsuariosSinDemografia()
            ];
            sendResponse([
                'success' => true,
                'data' => $alertas,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        case 'fincas':
            // Alertas específicas de fincas
            $alertas = [
                'sin_potreros' => $dashboard->getFincasSinPotreros(),
                'sin_actividad' => $dashboard->getFincasSinActividad(),
                'area_sospechosa' => $dashboard->getFincasAreaSospechosa()
            ];
            sendResponse([
                'success' => true,
                'data' => $alertas,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        case 'calidad':
            // Alertas de calidad de datos
            $alertas = [
                'sin_demografia' => $dashboard->getUsuariosSinDemografia(),
                'area_sospechosa' => $dashboard->getFincasAreaSospechosa()
            ];
            sendResponse([
                'success' => true,
                'data' => $alertas,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        default:
            handleError('Tipo de alerta no válido. Opciones: resumen, usuarios, fincas, calidad', 400);
    }

} catch (Exception $e) {
    error_log("Error en alerts.php: " . $e->getMessage());
    handleError('Error interno del servidor', 500);
}
?>



