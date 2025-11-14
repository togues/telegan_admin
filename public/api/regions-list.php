<?php
/**
 * API: Listado de regiones umbral (region_umbral)
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

    $q         = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
    $pais      = isset($_GET['pais']) ? strtoupper(trim((string)$_GET['pais'])) : '';
    $tipo      = isset($_GET['tipo']) ? trim((string)$_GET['tipo']) : '';
    $activo    = isset($_GET['activo']) ? trim((string)$_GET['activo']) : '';
    $page      = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $pageSize  = isset($_GET['page_size']) && is_numeric($_GET['page_size']) ? (int)$_GET['page_size'] : 20;
    $sortBy    = isset($_GET['sort_by']) ? trim((string)$_GET['sort_by']) : 'fecha_creacion';
    $sortOrder = isset($_GET['sort_order']) ? strtoupper(trim((string)$_GET['sort_order'])) : 'DESC';

    if ($pageSize > 100) {
        $pageSize = 100;
    } elseif ($pageSize < 1) {
        $pageSize = 20;
    }

    $offset = ($page - 1) * $pageSize;

    $allowedSortColumns = ['codigo', 'nombre', 'pais_codigo_iso', 'tipo', 'activo', 'fecha_creacion'];
    if (!in_array($sortBy, $allowedSortColumns, true)) {
        $sortBy = 'fecha_creacion';
    }
    if (!in_array($sortOrder, ['ASC', 'DESC'], true)) {
        $sortOrder = 'DESC';
    }

    $where = [];
    $params = [];

    if ($q !== '') {
        $where[] = '(codigo ILIKE :q OR nombre ILIKE :q)';
        $params['q'] = '%' . $q . '%';
    }

    if ($pais !== '') {
        $where[] = 'pais_codigo_iso = :pais';
        $params['pais'] = $pais;
    }

    if ($tipo !== '') {
        $where[] = 'tipo ILIKE :tipo';
        $params['tipo'] = '%' . $tipo . '%';
    }

    if ($activo !== '') {
        $where[] = 'activo = :activo';
        $params['activo'] = $activo === '1';
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countSql = "SELECT COUNT(*) AS total FROM region_umbral {$whereSql}";
    $countRow = Database::fetch($countSql, $params) ?? ['total' => 0];
    $totalRows = (int)$countRow['total'];

    $sql = "
        SELECT
            id_region,
            codigo,
            nombre,
            pais_codigo_iso,
            tipo,
            ST_AsText(geom) AS geom_wkt,
            CASE WHEN geom IS NOT NULL THEN ST_AsGeoJSON(geom, 6) END AS geom_geojson,
            metadata,
            activo,
            fecha_creacion
        FROM region_umbral
        {$whereSql}
        ORDER BY {$sortBy} {$sortOrder}
        LIMIT :limit OFFSET :offset
    ";

    $params['limit'] = $pageSize;
    $params['offset'] = $offset;

    $rows = Database::fetchAll($sql, $params);

    $data = array_map(static function (array $row): array {
        return [
            'id_region'       => $row['id_region'],
            'codigo'          => $row['codigo'],
            'nombre'          => $row['nombre'],
            'pais_codigo_iso' => $row['pais_codigo_iso'],
            'tipo'            => $row['tipo'],
            'geom_wkt'        => $row['geom_wkt'],
            'geom_geojson'    => $row['geom_geojson'] ? json_decode($row['geom_geojson'], true) : null,
            'metadata'        => $row['metadata'] ? json_decode($row['metadata'], true) : null,
            'activo'          => (bool)$row['activo'],
            'fecha_creacion'  => $row['fecha_creacion'],
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
    error_log('Error PDO en regions-list.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error de base de datos: ' . $e->getMessage()
    ], 500);
} catch (Throwable $e) {
    error_log('Error en regions-list.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error inesperado: ' . $e->getMessage()
    ], 500);
}
