<?php
/**
 * API: Listado de usuarios del sistema (admin_users) con búsqueda, filtros y paginación
 */

// Activar reporte de errores para debugging (solo en desarrollo)
error_reporting(E_ALL);
ini_set('display_errors', 0); // No mostrar errores en output, solo en logs

// Manejador de errores global para capturar errores fatales
function handleFatalError() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Error fatal del servidor: ' . $error['message'],
            'file' => $error['file'],
            'line' => $error['line']
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
}

// Registrar el manejador de errores
register_shutdown_function('handleFatalError');

// Manejador de errores para capturar warnings y notices
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Solo loguear, no interrumpir la ejecución
    error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
    return false; // Continuar con el manejador de errores por defecto
});

function respond($payload, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}

// Headers CORS primero
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Token, X-API-Timestamp, X-Session-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Cargar dependencias con manejo de errores
    if (!file_exists('../../src/Config/Database.php')) {
        throw new Exception('Archivo Database.php no encontrado');
    }
    require_once '../../src/Config/Database.php';
    
    if (!file_exists('../../src/Config/ApiAuth.php')) {
        throw new Exception('Archivo ApiAuth.php no encontrado');
    }
    require_once '../../src/Config/ApiAuth.php';
    // Solo permitir GET
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        respond(['success' => false, 'error' => 'Método no permitido'], 405);
    }
    
    // Validar token de autenticación (con manejo de errores)
    try {
        $validation = ApiAuth::validateRequest();
        
        if (!$validation || !isset($validation['valid'])) {
            throw new Exception('Error en validación de autenticación: respuesta inválida');
        }
        
        if (!$validation['valid']) {
            respond([
                'success' => false, 
                'error' => 'Acceso no autorizado: ' . ($validation['error'] ?? 'Token inválido')
            ], 401);
        }
    } catch (Exception $authError) {
        error_log("Error en validación de autenticación: " . $authError->getMessage());
        respond([
            'success' => false,
            'error' => 'Error en validación de autenticación: ' . $authError->getMessage()
        ], 500);
    }

    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $activo = isset($_GET['activo']) ? trim($_GET['activo']) : '';
    $rol = isset($_GET['rol']) ? trim($_GET['rol']) : '';
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
    $pageSize = isset($_GET['page_size']) && is_numeric($_GET['page_size']) ? (int)$_GET['page_size'] : 20;
    if ($pageSize > 100) { $pageSize = 100; }
    $offset = ($page - 1) * $pageSize;
    
    // Ordenamiento
    $sortBy = isset($_GET['sort_by']) ? trim($_GET['sort_by']) : 'fecha_registro';
    $sortOrder = isset($_GET['sort_order']) ? strtoupper(trim($_GET['sort_order'])) : 'DESC';
    
    // Validar columna de ordenamiento (whitelist)
    $allowedSortColumns = [
        'nombre_completo', 'email', 'telefono', 'fecha_registro', 
        'ultima_sesion', 'rol', 'activo'
    ];
    if (!in_array($sortBy, $allowedSortColumns)) {
        $sortBy = 'fecha_registro';
    }
    
    // Validar dirección de ordenamiento
    if ($sortOrder !== 'ASC' && $sortOrder !== 'DESC') {
        $sortOrder = 'DESC';
    }

    $where = [];
    $params = [];

    // Búsqueda general (nombre, email, teléfono)
    if ($q !== '') {
        $where[] = '(au.nombre_completo ILIKE :q OR au.email ILIKE :q OR au.telefono ILIKE :q)';
        $params['q'] = "%$q%";
    }

    // Filtro por estado activo
    if ($activo !== '') {
        $where[] = 'au.activo = :activo';
        // PDO con PostgreSQL maneja booleanos directamente
        $params['activo'] = $activo === '1';
    }

    // Filtro por rol
    if ($rol !== '') {
        $where[] = 'au.rol = :rol';
        $params['rol'] = $rol;
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Contar total
    $countSql = "SELECT COUNT(*) as total FROM admin_users au $whereClause";
    $countResult = Database::fetch($countSql, $params);
    $total = (int)($countResult['total'] ?? 0);

    // Obtener datos con paginación
    $sql = "
        SELECT 
            au.id_admin,
            au.nombre_completo,
            au.email,
            au.telefono,
            au.rol,
            au.activo,
            au.email_verificado,
            au.telefono_verificado,
            au.ultima_sesion,
            au.fecha_registro,
            au.fecha_actualizacion,
            au.intentos_login,
            au.bloqueado_hasta,
            au.created_by,
            creator.nombre_completo as creado_por_nombre
        FROM admin_users au
        LEFT JOIN admin_users creator ON au.created_by = creator.id_admin
        $whereClause
        ORDER BY au.$sortBy $sortOrder
        LIMIT :limit OFFSET :offset
    ";

    // Agregar parámetros de paginación
    $params['limit'] = $pageSize;
    $params['offset'] = $offset;
    
    $rows = Database::fetchAll($sql, $params);

    // Formatear datos
    $data = array_map(function($row) {
        return [
            'id_admin' => (int)$row['id_admin'],
            'nombre_completo' => $row['nombre_completo'],
            'email' => $row['email'],
            'telefono' => $row['telefono'] ?? null,
            'rol' => $row['rol'],
            'activo' => (bool)$row['activo'],
            'email_verificado' => (bool)$row['email_verificado'],
            'telefono_verificado' => (bool)$row['telefono_verificado'],
            'ultima_sesion' => $row['ultima_sesion'],
            'fecha_registro' => $row['fecha_registro'],
            'fecha_actualizacion' => $row['fecha_actualizacion'],
            'intentos_login' => (int)$row['intentos_login'],
            'bloqueado_hasta' => $row['bloqueado_hasta'],
            'created_by' => $row['created_by'] ? (int)$row['created_by'] : null,
            'creado_por_nombre' => $row['creado_por_nombre'] ?? null
        ];
    }, $rows);

    $totalPages = $pageSize > 0 ? ceil($total / $pageSize) : 1;

    respond([
        'success' => true,
        'data' => $data,
        'pagination' => [
            'page' => $page,
            'page_size' => $pageSize,
            'total' => $total,
            'total_pages' => $totalPages
        ]
    ]);

} catch (PDOException $e) {
    error_log("Error PDO en system-users-list.php: " . $e->getMessage());
    error_log("SQL State: " . $e->getCode());
    error_log("SQL Error Info: " . print_r($e->errorInfo ?? [], true));
    respond([
        'success' => false,
        'error' => 'Error de base de datos: ' . $e->getMessage(),
        'code' => $e->getCode()
    ], 500);
} catch (Throwable $e) {
    // Capturar cualquier error (incluyendo errores fatales convertidos a excepciones)
    error_log("Error en system-users-list.php: " . $e->getMessage());
    error_log("Tipo: " . get_class($e));
    error_log("Archivo: " . $e->getFile() . " Línea: " . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());
    respond([
        'success' => false,
        'error' => 'Error al cargar usuarios del sistema: ' . $e->getMessage(),
        'type' => get_class($e)
    ], 500);
}

