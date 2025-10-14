<?php

/**
 * Sistema de Routing Manual
 * PHP Vanilla - Sin frameworks
 */

// Cargar clases necesarias
require_once __DIR__ . '/../src/Config/Database.php';
require_once __DIR__ . '/../src/Models/Dashboard.php';

// Configurar headers CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

/**
 * Función para enviar respuesta JSON
 */
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * Función para manejar errores
 */
function handleError($message, $statusCode = 500) {
    sendResponse(array(
        'success' => false,
        'error' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ), $statusCode);
}

// Obtener la ruta solicitada
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Extraer la ruta desde la URI
$path = parse_url($requestUri, PHP_URL_PATH);
$path = str_replace('/api/', '', $path);
$path = trim($path, '/');

// Debug
error_log("Router - Request URI: " . $requestUri);
error_log("Router - Path: " . $path);
error_log("Router - Method: " . $requestMethod);

// Routing manual
switch ($path) {
    case 'dashboard':
    case 'dashboard.php':
        // Dashboard - GET
        if ($requestMethod === 'GET') {
            try {
                $dashboard = new Dashboard();
                
                $estadisticas = $dashboard->getEstadisticasCompletas();
                $infoBD = $dashboard->getInfoBaseDatos();
                
                sendResponse(array(
                    'success' => true,
                    'data' => array(
                        'estadisticas' => $estadisticas,
                        'base_datos' => $infoBD
                    ),
                    'timestamp' => date('Y-m-d H:i:s')
                ));
                
            } catch (Exception $e) {
                handleError($e->getMessage());
            }
        } else {
            handleError('Método no permitido', 405);
        }
        break;
        
    case 'dashboard-simple':
        // Dashboard Simple - GET
        if ($requestMethod === 'GET') {
            try {
                // Test básico de conexión
                $host = '157.245.241.220';
                $port = '5432';
                $dbname = 'telegan';
                $username = 'rios';
                $password = '*gS&Di*ue.,RQ[E]X}.10QP8539';
                
                $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
                $pdo = new PDO($dsn, $username, $password);
                
                // Test de consulta
                $sql = "SELECT COUNT(*) FROM v_usuarios_fincas";
                $stmt = $pdo->prepare($sql);
                $stmt->execute();
                $totalUsuarios = $stmt->fetchColumn();
                
                sendResponse(array(
                    'success' => true,
                    'data' => array(
                        'estadisticas' => array(
                            'total_usuarios' => (int)$totalUsuarios,
                            'total_fincas' => 0,
                            'total_potreros' => 0,
                            'total_registros_ganaderos' => 0,
                            'timestamp' => date('Y-m-d H:i:s')
                        ),
                        'base_datos' => array(
                            'conectado' => true,
                            'version_postgresql' => 'PostgreSQL 14+',
                            'timestamp' => date('Y-m-d H:i:s')
                        )
                    ),
                    'timestamp' => date('Y-m-d H:i:s')
                ));
                
            } catch (Exception $e) {
                handleError($e->getMessage());
            }
        } else {
            handleError('Método no permitido', 405);
        }
        break;
        
    case 'health':
        // Health check
        try {
            $dashboard = new Dashboard();
            $conectado = $dashboard->verificarConexion();
            
            sendResponse(array(
                'success' => true,
                'status' => $conectado ? 'healthy' : 'unhealthy',
                'database' => $conectado ? 'connected' : 'disconnected',
                'timestamp' => date('Y-m-d H:i:s')
            ));
            
        } catch (Exception $e) {
            handleError($e->getMessage());
        }
        break;
        
    case 'usuarios':
        // Endpoint para usuarios (futuro)
        if ($requestMethod === 'GET') {
            // TODO: Implementar listado de usuarios
            sendResponse(array(
                'success' => true,
                'message' => 'Endpoint de usuarios en desarrollo',
                'data' => array(),
                'timestamp' => date('Y-m-d H:i:s')
            ));
        } else {
            handleError('Método no permitido', 405);
        }
        break;
        
    case 'fincas':
        // Endpoint para fincas (futuro)
        if ($requestMethod === 'GET') {
            // TODO: Implementar listado de fincas
            sendResponse(array(
                'success' => true,
                'message' => 'Endpoint de fincas en desarrollo',
                'data' => array(),
                'timestamp' => date('Y-m-d H:i:s')
            ));
        } else {
            handleError('Método no permitido', 405);
        }
        break;
        
    case '':
    case 'index.php':
        // Ruta vacía - redirigir al dashboard
        try {
            $dashboard = new Dashboard();
            $estadisticas = $dashboard->getEstadisticasCompletas();
            $infoBD = $dashboard->getInfoBaseDatos();
            
            sendResponse(array(
                'success' => true,
                'data' => array(
                    'estadisticas' => $estadisticas,
                    'base_datos' => $infoBD
                ),
                'timestamp' => date('Y-m-d H:i:s')
            ));
            
        } catch (Exception $e) {
            handleError($e->getMessage());
        }
        break;
        
    default:
        // Ruta no encontrada
        handleError('Endpoint no encontrado: ' . $path, 404);
        break;
}
