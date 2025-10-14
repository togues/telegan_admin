<?php
/**
 * Debug de la API del dashboard
 * Muestra errores detallados y respuestas
 */

// Habilitar mostrar errores para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Incluir middleware de seguridad
require_once '../../src/Middleware/SecurityMiddleware.php';

// Inicializar middleware
SecurityMiddleware::init();

// Configurar headers de respuesta
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-App-Token, X-App-Timestamp');

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
        'timestamp' => date('Y-m-d H:i:s'),
        'debug_info' => [
            'file' => __FILE__,
            'line' => __LINE__,
            'request_method' => $_SERVER['REQUEST_METHOD'],
            'request_uri' => $_SERVER['REQUEST_URI']
        ]
    ], $status);
}

try {
    echo "<!-- Iniciando debug de API -->\n";
    
    // Verificar método HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        handleError('Método no permitido', 405);
    }

    // VALIDACIÓN GRADUAL DE SEGURIDAD
    SecurityMiddleware::publicApi();
    
    echo "<!-- Middleware de seguridad OK -->\n";
    
    // Incluir dependencias
    require_once '../../src/Config/Database.php';
    require_once '../../src/Models/Dashboard.php';
    
    echo "<!-- Dependencias cargadas -->\n";

    // Crear instancia del modelo Dashboard
    $dashboard = new Dashboard();
    
    echo "<!-- Dashboard model creado -->\n";

    // Verificar conexión a la base de datos
    if (!$dashboard->verificarConexion()) {
        handleError('Error de conexión a la base de datos', 503);
    }
    
    echo "<!-- Conexión a BD verificada -->\n";

    // Obtener datos del dashboard
    $estadisticas = [
        'total_usuarios' => $dashboard->getTotalUsuarios(),
        'total_fincas' => $dashboard->getTotalFincas(),
        'total_potreros' => $dashboard->getTotalPotreros(),
        'total_registros_ganaderos' => $dashboard->getTotalRegistrosGanaderos(),
        'usuarios_activos' => $dashboard->getUsuariosActivos(),
        'usuarios_administradores' => $dashboard->getUsuariosAdministradores(),
        'usuarios_colaboradores' => $dashboard->getUsuariosColaboradores(),
    ];
    
    echo "<!-- Estadísticas obtenidas -->\n";

    // Obtener alertas
    $alertas = [
        'usuarios_sin_finca' => $dashboard->getUsuariosSinFinca()['total_usuarios_sin_finca'] ?? 0,
        'fincas_sin_potreros' => $dashboard->getFincasSinPotreros()['fincas_sin_potreros'] ?? 0,
        'usuarios_inactivos' => $dashboard->getUsuariosInactivos()['usuarios_inactivos_30dias'] ?? 0,
        'fincas_sin_actividad' => $dashboard->getFincasSinActividad()['fincas_sin_actividad_30dias'] ?? 0,
    ];
    
    echo "<!-- Alertas obtenidas -->\n";

    // Información del sistema
    $sistema = [
        'version' => '1.0.0',
        'entorno' => 'development',
        'timestamp' => date('Y-m-d H:i:s'),
        'servidor' => $_SERVER['HTTP_HOST'] ?? 'localhost',
        'base_datos' => $dashboard->getInfoBaseDatos(),
        'seguridad' => [
            'app_token_validado' => !empty($_SERVER['HTTP_X_APP_TOKEN']),
            'auth_header_presente' => !empty($_SERVER['HTTP_AUTHORIZATION']),
            'modo_desarrollo' => true
        ]
    ];
    
    echo "<!-- Sistema info obtenida -->\n";

    $response = [
        'success' => true,
        'data' => [
            'estadisticas' => $estadisticas,
            'alertas' => $alertas,
            'sistema' => $sistema
        ],
        'timestamp' => date('Y-m-d H:i:s'),
        'debug_info' => [
            'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ]
    ];
    
    echo "<!-- Respuesta preparada -->\n";
    
    sendResponse($response);

} catch (Exception $e) {
    echo "<!-- Error capturado: " . $e->getMessage() . " -->\n";
    error_log("Error en debug-dashboard.php: " . $e->getMessage());
    handleError('Error interno del servidor: ' . $e->getMessage(), 500);
}
?>
