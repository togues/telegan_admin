<?php
/**
 * API: Usuarios sin demografía - Listado completo
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

    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
    $pageSize = isset($_GET['page_size']) && is_numeric($_GET['page_size']) ? (int)$_GET['page_size'] : 20;
    if ($pageSize > 100) { $pageSize = 100; }
    $offset = ($page - 1) * $pageSize;

    // Usuarios sin datos demográficos completos
    $sql = "
        SELECT 
            u.id_usuario, u.nombre_completo, u.email, u.telefono,
            u.ubicacion_general, u.activo, u.email_verificado, u.telefono_verificado,
            u.fecha_registro, u.ultima_sesion, u.codigo_telegan,
            d.id_demografia, d.genero, d.edad, d.grupo_etnico
        FROM usuario u
        LEFT JOIN demografia_usuario d ON u.id_usuario = d.id_usuario
        WHERE u.activo = TRUE
        AND (d.id_demografia IS NULL 
             OR d.genero IS NULL 
             OR d.edad IS NULL 
             OR d.grupo_etnico IS NULL)
        ORDER BY u.fecha_registro DESC
        LIMIT :limit OFFSET :offset
    ";

    $params = [
        'limit' => $pageSize,
        'offset' => $offset
    ];

    $rows = Database::fetchAll($sql, $params, [
        'limit' => \PDO::PARAM_INT,
        'offset' => \PDO::PARAM_INT
    ]);

    // Contar total
    $countSql = "
        SELECT COUNT(*) AS total 
        FROM usuario u
        LEFT JOIN demografia_usuario d ON u.id_usuario = d.id_usuario
        WHERE u.activo = TRUE
        AND (d.id_demografia IS NULL 
             OR d.genero IS NULL 
             OR d.edad IS NULL 
             OR d.grupo_etnico IS NULL)
    ";
    
    $countRow = Database::fetch($countSql);
    $total = (int)($countRow['total'] ?? 0);

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
            'demografia' => [
                'tiene_registro' => !empty($r['id_demografia']),
                'genero' => $r['genero'],
                'edad' => $r['edad'],
                'grupo_etnico' => $r['grupo_etnico']
            ]
        ];
    }, $rows);

    respond([
        'success' => true,
        'data' => $data,
        'pagination' => [
            'page' => $page,
            'page_size' => $pageSize,
            'total' => $total,
            'total_pages' => ceil($total / $pageSize)
        ]
    ]);

} catch (Exception $e) {
    error_log('alerts-users-no-demography.php error: ' . $e->getMessage());
    respond(['success' => false, 'error' => 'Error interno del servidor'], 500);
}
?>

