<?php
/**
 * API: Listado de usuarios del sistema (admin_users) con búsqueda, filtros y paginación
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
    
    // Validar token de autenticación
    $validation = ApiAuth::validateRequest();
    
    if (!$validation['valid']) {
        respond([
            'success' => false, 
            'error' => 'Acceso no autorizado: ' . ($validation['error'] ?? 'Token inválido')
        ], 401);
    }

    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $activo = isset($_GET['activo']) ? trim($_GET['activo']) : '';
    $rol = isset($_GET['rol']) ? trim($_GET['rol']) : '';
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
    $pageSize = isset($_GET['page_size']) && is_numeric($_GET['page_size']) ? (int)$_GET['page_size'] : 20;
    if ($pageSize > 100) { $pageSize = 100; }
    $offset = ($page - 1) * $pageSize;

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
        $params['activo'] = $activo === '1' ? true : false;
    }

    // Filtro por rol
    if ($rol !== '') {
        $where[] = 'au.rol = :rol';
        $params['rol'] = $rol;
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Contar total
    $countSql = "SELECT COUNT(*) as total FROM admin_users au $whereClause";
    $db = Database::getConnection();
    $countStmt = $db->prepare($countSql);
    foreach ($params as $key => $value) {
        $countStmt->bindValue(":$key", $value);
    }
    $countStmt->execute();
    $total = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];

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
        ORDER BY au.fecha_registro DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

} catch (Exception $e) {
    error_log("Error en system-users-list.php: " . $e->getMessage());
    respond([
        'success' => false,
        'error' => 'Error al cargar usuarios del sistema'
    ], 500);
}

