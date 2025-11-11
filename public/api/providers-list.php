<?php
/**
 * API: Listado de proveedores satelitales/climáticos (tabla proveedor)
 */

declare(strict_types=1);

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

function respond(array $payload, int $status = 200): void {
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
            'error'   => 'Acceso no autorizado: ' . ($validation['error'] ?? 'Token inválido')
        ], 401);
    }

    $q          = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
    $activo     = isset($_GET['activo']) ? trim((string)$_GET['activo']) : '';
    $page       = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $pageSize   = isset($_GET['page_size']) && is_numeric($_GET['page_size']) ? (int)$_GET['page_size'] : 20;
    $sortBy     = isset($_GET['sort_by']) ? trim((string)$_GET['sort_by']) : 'fecha_creacion';
    $sortOrder  = isset($_GET['sort_order']) ? strtoupper(trim((string)$_GET['sort_order'])) : 'DESC';

    if ($pageSize > 100) {
        $pageSize = 100;
    } elseif ($pageSize < 1) {
        $pageSize = 20;
    }

    $offset = ($page - 1) * $pageSize;

    $allowedSortColumns = [
        'codigo',
        'nombre',
        'frecuencia_horas',
        'ventana_temporal_dias',
        'max_nubosidad_pct',
        'activo',
        'fecha_creacion',
        'fecha_ultima_consulta'
    ];
    if (!in_array($sortBy, $allowedSortColumns, true)) {
        $sortBy = 'fecha_creacion';
    }
    if (!in_array($sortOrder, ['ASC', 'DESC'], true)) {
        $sortOrder = 'DESC';
    }

    $whereParts = [];
    $params     = [];

    if ($q !== '') {
        $whereParts[]  = '(codigo ILIKE :q OR nombre ILIKE :q OR descripcion ILIKE :q OR url_api ILIKE :q)';
        $params['q']   = '%' . $q . '%';
    }

    if ($activo !== '') {
        $whereParts[] = 'activo = :activo';
        $params['activo'] = $activo === '1';
    }

    $whereSql = '';
    if (!empty($whereParts)) {
        $whereSql = 'WHERE ' . implode(' AND ', $whereParts);
    }

    $countSql  = "SELECT COUNT(*) AS total FROM proveedor {$whereSql}";
    $countRow  = Database::fetch($countSql, $params) ?? ['total' => 0];
    $totalRows = (int)$countRow['total'];

    $sql = "
        SELECT
            id_proveedor,
            codigo,
            nombre,
            descripcion,
            url_api,
            requiere_autenticacion,
            api_key_encriptada,
            frecuencia_horas,
            ventana_temporal_dias,
            max_nubosidad_pct,
            contacto,
            metadata,
            activo,
            fecha_ultima_consulta,
            fecha_creacion
        FROM proveedor
        {$whereSql}
        ORDER BY {$sortBy} {$sortOrder}
        LIMIT :limit OFFSET :offset
    ";

    $params['limit']  = $pageSize;
    $params['offset'] = $offset;

    $rows = Database::fetchAll($sql, $params);

    $data = array_map(static function (array $row): array {
        return [
            'id_proveedor'           => (int)$row['id_proveedor'],
            'codigo'                 => $row['codigo'],
            'nombre'                 => $row['nombre'],
            'descripcion'            => $row['descripcion'],
            'url_api'                => $row['url_api'],
            'requiere_autenticacion' => (bool)$row['requiere_autenticacion'],
            'api_key_encriptada'     => $row['api_key_encriptada'],
            'frecuencia_horas'       => $row['frecuencia_horas'] !== null ? (int)$row['frecuencia_horas'] : null,
            'ventana_temporal_dias'  => $row['ventana_temporal_dias'] !== null ? (int)$row['ventana_temporal_dias'] : null,
            'max_nubosidad_pct'      => $row['max_nubosidad_pct'] !== null ? (float)$row['max_nubosidad_pct'] : null,
            'contacto'               => $row['contacto'] !== null ? json_decode($row['contacto'], true) : null,
            'metadata'               => $row['metadata'] !== null ? json_decode($row['metadata'], true) : null,
            'activo'                 => (bool)$row['activo'],
            'fecha_ultima_consulta'  => $row['fecha_ultima_consulta'],
            'fecha_creacion'         => $row['fecha_creacion'],
        ];
    }, $rows);

    $totalPages = $pageSize > 0 ? (int)ceil($totalRows / $pageSize) : 1;

    respond([
        'success'    => true,
        'data'       => $data,
        'pagination' => [
            'page'        => $page,
            'page_size'   => $pageSize,
            'total'       => $totalRows,
            'total_pages' => $totalPages
        ]
    ]);
} catch (PDOException $e) {
    error_log('Error PDO en providers-list.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error de base de datos: ' . $e->getMessage()
    ], 500);
} catch (Throwable $e) {
    error_log('Error en providers-list.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error inesperado: ' . $e->getMessage()
    ], 500);
}


