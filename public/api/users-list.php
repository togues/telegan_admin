<?php
/**
 * API: Listado de usuarios con búsqueda, filtros y paginación
 */

require_once '../../src/Config/Database.php';
require_once '../../src/Config/ApiAuth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Token, X-API-Timestamp, X-Session-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

function respond($payload, $status = 200) {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    // Solo permitir GET
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        respond(['success' => false, 'error' => 'Método no permitido'], 405);
    }
    
    // Validar token de autenticación (ApiAuth normaliza el path automáticamente)
    $validation = ApiAuth::validateRequest();
    
    if (!$validation['valid']) {
        respond([
            'success' => false, 
            'error' => 'Acceso no autorizado: ' . ($validation['error'] ?? 'Token inválido')
        ], 401);
    }

    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $activo = isset($_GET['activo']) ? trim($_GET['activo']) : ''; // '', '1', '0'
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
    $pageSize = isset($_GET['page_size']) && is_numeric($_GET['page_size']) ? (int)$_GET['page_size'] : 20;
    if ($pageSize > 100) { $pageSize = 100; }
    $offset = ($page - 1) * $pageSize;

    $where = [];
    $params = [];

    if ($q !== '') {
        $where[] = '(u.nombre_completo ILIKE :q OR u.email ILIKE :q OR u.telefono ILIKE :q)';
        $params['q'] = "%$q%";
    }

    if ($activo === '1') {
        $where[] = 'u.activo = TRUE';
    } elseif ($activo === '0') {
        $where[] = 'u.activo = FALSE';
    }

    $whereSql = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

    // Total
    $countSql = "SELECT COUNT(*) AS total FROM usuario u $whereSql";
    $countRow = Database::fetch($countSql, $params);
    $total = (int)($countRow['total'] ?? 0);

    // Datos
    $sql = "
        SELECT 
            u.id_usuario, u.nombre_completo, u.email, u.telefono,
            u.ubicacion_general, u.activo, u.email_verificado, u.telefono_verificado,
            u.fecha_registro, u.ultima_sesion, u.codigo_telegan
        FROM usuario u
        $whereSql
        ORDER BY u.fecha_registro DESC
        LIMIT :limit OFFSET :offset
    ";

    $paramsWithLimit = $params;
    $paramsWithLimit['limit'] = $pageSize;
    $paramsWithLimit['offset'] = $offset;

    $rows = Database::fetchAll($sql, $paramsWithLimit, [
        'limit' => \PDO::PARAM_INT,
        'offset' => \PDO::PARAM_INT
    ]);

    $data = array_map(function ($r) {
        return [
            'id_usuario' => (int)$r['id_usuario'],
            'nombre_completo' => $r['nombre_completo'],
            'email' => $r['email'],
            'telefono' => $r['telefono'],
            'ubicacion_general' => $r['ubicacion_general'],
            'activo' => (bool)$r['activo'],
            'email_verificado' => (bool)$r['email_verificado'],
            'telefono_verificado' => (bool)$r['telefono_verificado'],
            'fecha_registro' => $r['fecha_registro'],
            'ultima_sesion' => $r['ultima_sesion'],
            'codigo_telegan' => $r['codigo_telegan']
        ];
    }, $rows ?? []);

    respond([
        'success' => true,
        'data' => $data,
        'pagination' => [
            'page' => $page,
            'page_size' => $pageSize,
            'total' => $total,
            'total_pages' => $pageSize ? ceil($total / $pageSize) : 1
        ]
    ]);
} catch (Exception $e) {
    error_log('users-list.php error: ' . $e->getMessage());
    respond(['success' => false, 'error' => 'Error interno del servidor'], 500);
}
?>


