<?php
/**
 * API: Usuarios duplicados/sospechosos
 * Detecta usuarios que parecen ser el mismo basado en:
 * - Correos similares
 * - Teléfonos similares (con/sin código de área)
 * - Códigos Telegan repetidos
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

    // Detectar usuarios duplicados/sospechosos
    // Construir condiciones base de duplicados
    $duplicateConditions = [
        // Email duplicado
        "EXISTS (
            SELECT 1 FROM usuario u2 
            WHERE u2.id_usuario != u.id_usuario 
            AND u2.email = u.email
            AND u.email IS NOT NULL AND u.email != ''
        )",
        // Teléfono duplicado (normalizado)
        "EXISTS (
            SELECT 1 FROM usuario u2 
            WHERE u2.id_usuario != u.id_usuario 
            AND REGEXP_REPLACE(REGEXP_REPLACE(REGEXP_REPLACE(u.telefono, '[^0-9]', '', 'g'), '^504', '', 'g'), '^\\+504', '', 'g') =
                REGEXP_REPLACE(REGEXP_REPLACE(REGEXP_REPLACE(u2.telefono, '[^0-9]', '', 'g'), '^504', '', 'g'), '^\\+504', '', 'g')
            AND u.telefono IS NOT NULL AND u.telefono != ''
            AND u2.telefono IS NOT NULL AND u2.telefono != ''
        )",
        // Código Telegan duplicado
        "EXISTS (
            SELECT 1 FROM usuario u2 
            WHERE u2.id_usuario != u.id_usuario 
            AND u2.codigo_telegan = u.codigo_telegan
            AND u.codigo_telegan IS NOT NULL AND u.codigo_telegan != ''
        )"
    ];
    
    // Construir WHERE para duplicados
    $duplicateWhere = '(' . implode(' OR ', $duplicateConditions) . ')';
    
    // Aplicar filtros adicionales
    $where = [$duplicateWhere];
    $params = [];
    
    if ($q !== '') {
        $where[] = '(u.nombre_completo ILIKE :q OR u.email ILIKE :q OR u.telefono ILIKE :q)';
        $params['q'] = "%$q%";
    }
    
    if ($codigo !== '') {
        $where[] = 'u.codigo_telegan ILIKE :codigo';
        $params['codigo'] = "%$codigo%";
    }
    
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
    
    // Consulta principal
    $sql = "
        SELECT 
            u.id_usuario,
            u.email,
            u.nombre_completo,
            u.telefono,
            u.codigo_telegan,
            u.activo,
            u.email_verificado,
            u.telefono_verificado,
            u.fecha_registro,
            u.ultima_sesion,
            CASE 
                WHEN EXISTS (
                    SELECT 1 FROM usuario u2 
                    WHERE u2.id_usuario != u.id_usuario 
                    AND u2.email = u.email
                    AND u.email IS NOT NULL AND u.email != ''
                ) THEN 'email_duplicado'
                WHEN EXISTS (
                    SELECT 1 FROM usuario u2 
                    WHERE u2.id_usuario != u.id_usuario 
                    AND REGEXP_REPLACE(REGEXP_REPLACE(REGEXP_REPLACE(u.telefono, '[^0-9]', '', 'g'), '^504', '', 'g'), '^\\+504', '', 'g') =
                        REGEXP_REPLACE(REGEXP_REPLACE(REGEXP_REPLACE(u2.telefono, '[^0-9]', '', 'g'), '^504', '', 'g'), '^\\+504', '', 'g')
                    AND u.telefono IS NOT NULL AND u.telefono != ''
                    AND u2.telefono IS NOT NULL AND u2.telefono != ''
                ) THEN 'telefono_duplicado'
                WHEN EXISTS (
                    SELECT 1 FROM usuario u2 
                    WHERE u2.id_usuario != u.id_usuario 
                    AND u2.codigo_telegan = u.codigo_telegan
                    AND u.codigo_telegan IS NOT NULL AND u.codigo_telegan != ''
                ) THEN 'codigo_telegan_duplicado'
                ELSE 'duplicado'
            END as motivo,
            (
                SELECT COUNT(*) 
                FROM usuario u2 
                WHERE u2.id_usuario != u.id_usuario 
                AND (
                    (u2.email = u.email AND u.email IS NOT NULL AND u.email != '')
                    OR (
                        REGEXP_REPLACE(REGEXP_REPLACE(REGEXP_REPLACE(u.telefono, '[^0-9]', '', 'g'), '^504', '', 'g'), '^\\+504', '', 'g') =
                        REGEXP_REPLACE(REGEXP_REPLACE(REGEXP_REPLACE(u2.telefono, '[^0-9]', '', 'g'), '^504', '', 'g'), '^\\+504', '', 'g')
                        AND u.telefono IS NOT NULL AND u.telefono != ''
                        AND u2.telefono IS NOT NULL AND u2.telefono != ''
                    )
                    OR (u2.codigo_telegan = u.codigo_telegan AND u.codigo_telegan IS NOT NULL AND u.codigo_telegan != '')
                )
            ) as duplicados_count
        FROM usuario u
        $whereSql
    ";
    
    // Contar total
    $countSql = "SELECT COUNT(*) AS total FROM ($sql) AS all_duplicates";
    $countRow = Database::fetch($countSql, $params);
    $total = (int)($countRow['total'] ?? 0);
    
    // Obtener datos con paginación
    $finalSql = "
        SELECT * FROM ($sql) AS all_duplicates
        ORDER BY $sortBy $sortOrder
        LIMIT :limit OFFSET :offset
    ";
    
    $paramsWithLimit = $params;
    $paramsWithLimit['limit'] = $pageSize;
    $paramsWithLimit['offset'] = $offset;
    
    $rows = Database::fetchAll($finalSql, $paramsWithLimit, [
        'limit' => \PDO::PARAM_INT,
        'offset' => \PDO::PARAM_INT
    ]);
    
    $data = array_map(function ($r) {
        return [
            'id_usuario' => (int)$r['id_usuario'],
            'nombre_completo' => $r['nombre_completo'],
            'email' => $r['email'],
            'telefono' => $r['telefono'],
            'codigo_telegan' => $r['codigo_telegan'],
            'activo' => (bool)$r['activo'],
            'email_verificado' => (bool)$r['email_verificado'],
            'telefono_verificado' => (bool)$r['telefono_verificado'],
            'fecha_registro' => $r['fecha_registro'],
            'ultima_sesion' => $r['ultima_sesion'],
            'motivo' => $r['motivo'] ?? 'duplicado',
            'duplicados_count' => (int)($r['duplicados_count'] ?? 1)
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
    error_log('alerts-duplicate-users.php error: ' . $e->getMessage());
    respond(['success' => false, 'error' => 'Error interno del servidor'], 500);
}
?>

