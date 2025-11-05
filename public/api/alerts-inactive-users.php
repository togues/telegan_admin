<?php
/**
 * API: Usuarios inactivos >30 días (listos para borrar)
 * Usuarios que desde que se crearon no han tenido actividad mayor en 30 días
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
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        respond(['success' => false, 'error' => 'Método no permitido'], 405);
    }
    
    $validation = ApiAuth::validateRequest();
    
    if (!$validation['valid']) {
        respond([
            'success' => false, 
            'error' => 'Acceso no autorizado: ' . ($validation['error'] ?? 'Token inválido')
        ], 401);
    }

    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $activo = isset($_GET['activo']) ? trim($_GET['activo']) : '';
    $codigo = isset($_GET['codigo']) ? trim($_GET['codigo']) : '';
    $fechaDesde = isset($_GET['fecha_desde']) ? trim($_GET['fecha_desde']) : '';
    $fechaHasta = isset($_GET['fecha_hasta']) ? trim($_GET['fecha_hasta']) : '';
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
    $pageSize = isset($_GET['page_size']) && is_numeric($_GET['page_size']) ? (int)$_GET['page_size'] : 20;
    if ($pageSize > 100) { $pageSize = 100; }
    $offset = ($page - 1) * $pageSize;
    
    // Ordenamiento
    $sortBy = isset($_GET['sort_by']) ? trim($_GET['sort_by']) : 'fecha_registro';
    $sortOrder = isset($_GET['sort_order']) ? strtoupper(trim($_GET['sort_order'])) : 'DESC';
    
    $allowedSortColumns = [
        'nombre_completo', 'email', 'telefono', 'fecha_registro', 
        'ultima_sesion', 'codigo_telegan', 'activo'
    ];
    if (!in_array($sortBy, $allowedSortColumns)) {
        $sortBy = 'fecha_registro';
    }
    
    if ($sortOrder !== 'ASC' && $sortOrder !== 'DESC') {
        $sortOrder = 'DESC';
    }

    // Usuarios inactivos: sin actividad en los últimos 30 días desde su creación
    // O que nunca han iniciado sesión y fueron creados hace más de 30 días
    $where = [
        "(u.ultima_sesion IS NULL OR u.ultima_sesion < NOW() - INTERVAL '30 days')",
        "(u.fecha_registro < NOW() - INTERVAL '30 days')"
    ];
    $params = [];

    // Búsqueda general
    if ($q !== '') {
        $where[] = '(u.nombre_completo ILIKE :q OR u.email ILIKE :q OR u.telefono ILIKE :q)';
        $params['q'] = "%$q%";
    }

    // Filtro por código Telegan
    if ($codigo !== '') {
        $where[] = 'u.codigo_telegan ILIKE :codigo';
        $params['codigo'] = "%$codigo%";
    }

    // Filtro por fecha de registro (rango)
    if ($fechaDesde !== '') {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaDesde)) {
            $where[] = 'DATE(u.fecha_registro) >= :fecha_desde';
            $params['fecha_desde'] = $fechaDesde;
        }
    }
    
    if ($fechaHasta !== '') {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaHasta)) {
            $where[] = 'DATE(u.fecha_registro) <= :fecha_hasta';
            $params['fecha_hasta'] = $fechaHasta;
        }
    }

    if ($activo === '1') {
        $where[] = 'u.activo = TRUE';
    } elseif ($activo === '0') {
        $where[] = 'u.activo = FALSE';
    }

    $whereSql = 'WHERE ' . implode(' AND ', $where);

    // Total
    $countSql = "SELECT COUNT(*) AS total FROM usuario u $whereSql";
    $countRow = Database::fetch($countSql, $params);
    $total = (int)($countRow['total'] ?? 0);

    // Datos
    $sql = "
        SELECT 
            u.id_usuario, 
            u.nombre_completo, 
            u.email, 
            u.telefono,
            u.ubicacion_general, 
            u.activo, 
            u.email_verificado, 
            u.telefono_verificado,
            u.fecha_registro, 
            u.ultima_sesion, 
            u.codigo_telegan,
            EXTRACT(EPOCH FROM (NOW() - COALESCE(u.ultima_sesion, u.fecha_registro))) / 86400 as dias_inactivo
        FROM usuario u
        $whereSql
        ORDER BY u.$sortBy $sortOrder
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
            'codigo_telegan' => $r['codigo_telegan'],
            'dias_inactivo' => (int)round($r['dias_inactivo'] ?? 0)
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
    error_log('alerts-inactive-users.php error: ' . $e->getMessage());
    respond(['success' => false, 'error' => 'Error interno del servidor'], 500);
}
?>

