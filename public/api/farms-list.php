<?php
/**
 * API: Listado de fincas
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
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
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

    $q             = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
    $estado        = isset($_GET['estado']) ? strtoupper(trim((string)$_GET['estado'])) : '';
    $paisIso       = isset($_GET['pais']) ? strtoupper(trim((string)$_GET['pais'])) : '';
    $geometryFlag  = isset($_GET['geometry']) ? strtolower(trim((string)$_GET['geometry'])) : '';
    $creatorId     = isset($_GET['creador']) && is_numeric($_GET['creador']) ? (int)$_GET['creador'] : null;
    $fechaDesde    = isset($_GET['fecha_desde']) ? trim((string)$_GET['fecha_desde']) : '';
    $fechaHasta    = isset($_GET['fecha_hasta']) ? trim((string)$_GET['fecha_hasta']) : '';
    $page          = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $pageSize      = isset($_GET['page_size']) && is_numeric($_GET['page_size']) ? (int)$_GET['page_size'] : 20;
    $sortBy        = isset($_GET['sort_by']) ? trim((string)$_GET['sort_by']) : 'fecha_creacion';
    $sortOrder     = isset($_GET['sort_order']) ? strtoupper(trim((string)$_GET['sort_order'])) : 'DESC';

    if ($pageSize < 1) {
        $pageSize = 20;
    } elseif ($pageSize > 100) {
        $pageSize = 100;
    }

    $offset = ($page - 1) * $pageSize;

    $allowedSortColumns = ['fecha_creacion', 'fecha_actualizacion', 'nombre_finca'];
    if (!in_array($sortBy, $allowedSortColumns, true)) {
        $sortBy = 'fecha_creacion';
    }
    if (!in_array($sortOrder, ['ASC', 'DESC'], true)) {
        $sortOrder = 'DESC';
    }

    $whereParts = [];
    $params     = [];

    if ($q !== '') {
        $whereParts[] = '(f.nombre_finca ILIKE :q OR f.codigo_telegan ILIKE :q OR f.descripcion ILIKE :q)';
        $params['q'] = '%' . $q . '%';
    }

    if ($estado !== '') {
        $allowedStates = ['ACTIVA', 'INACTIVA', 'DESACTIVADA'];
        if (!in_array($estado, $allowedStates, true)) {
            respond(['success' => false, 'error' => 'Estado inválido'], 422);
        }
        $whereParts[] = 'UPPER(f.estado) = :estado';
        $params['estado'] = $estado;
    }

    if ($paisIso !== '') {
        $whereParts[] = 'UPPER(p.codigo_iso2) = :pais';
        $params['pais'] = $paisIso;
    }

    if ($creatorId !== null) {
        $whereParts[] = 'f.id_usuario_creador = :creador';
        $params['creador'] = $creatorId;
    }

    if ($fechaDesde !== '') {
        $whereParts[] = 'f.fecha_creacion >= :fecha_desde';
        $params['fecha_desde'] = $fechaDesde;
    }

    if ($fechaHasta !== '') {
        $whereParts[] = 'f.fecha_creacion <= :fecha_hasta';
        $params['fecha_hasta'] = $fechaHasta;
    }

    if ($geometryFlag === 'con') {
        $whereParts[] = "(f.geometria_wkt IS NOT NULL AND LENGTH(TRIM(f.geometria_wkt)) > 0)";
    } elseif ($geometryFlag === 'sin') {
        $whereParts[] = "(f.geometria_wkt IS NULL OR LENGTH(TRIM(f.geometria_wkt)) = 0)";
    }

    $whereSql = '';
    if (!empty($whereParts)) {
        $whereSql = 'WHERE ' . implode(' AND ', $whereParts);
    }

    $countSql = "
        SELECT COUNT(*) AS total
        FROM finca f
        LEFT JOIN pais p ON p.id_pais = f.id_pais
        LEFT JOIN usuario u ON u.id_usuario = f.id_usuario_creador
        {$whereSql}
    ";
    $countRow = Database::fetch($countSql, $params) ?? ['total' => 0];
    $totalRows = (int)$countRow['total'];

    $sql = "
        SELECT
            f.id_finca,
            f.nombre_finca,
            f.codigo_telegan,
            f.descripcion,
            f.estado,
            f.id_pais,
            f.area_hectareas,
            f.fecha_creacion,
            f.fecha_actualizacion,
            f.id_usuario_creador,
            p.codigo_iso2,
            p.nombre_pais,
            u.nombre_completo AS creador_nombre,
            CASE
                WHEN f.geometria_wkt IS NOT NULL AND LENGTH(TRIM(f.geometria_wkt)) > 0 THEN TRUE
                ELSE FALSE
            END AS has_geometry,
            COALESCE(pc.total_potreros, 0) AS potreros_count
        FROM finca f
        LEFT JOIN pais p ON p.id_pais = f.id_pais
        LEFT JOIN usuario u ON u.id_usuario = f.id_usuario_creador
        LEFT JOIN LATERAL (
            SELECT COUNT(*)::INT AS total_potreros
            FROM potrero pt
            WHERE pt.id_finca = f.id_finca
        ) pc ON TRUE
        {$whereSql}
        ORDER BY {$sortBy} {$sortOrder}
        LIMIT :limit OFFSET :offset
    ";

    $params['limit']  = $pageSize;
    $params['offset'] = $offset;

    $rows = Database::fetchAll($sql, $params);

    $data = array_map(static function (array $row): array {
        return [
            'id_finca'          => (int)$row['id_finca'],
            'nombre_finca'      => $row['nombre_finca'],
            'codigo_telegan'    => $row['codigo_telegan'],
            'descripcion'       => $row['descripcion'],
            'estado'            => $row['estado'],
            'id_pais'           => $row['id_pais'] !== null ? (int)$row['id_pais'] : null,
            'pais_codigo'       => $row['codigo_iso2'],
            'pais_nombre'       => $row['nombre_pais'],
            'creador_id'        => $row['id_usuario_creador'] !== null ? (int)$row['id_usuario_creador'] : null,
            'creador_nombre'    => $row['creador_nombre'],
            'area_hectareas'    => $row['area_hectareas'] !== null ? (float)$row['area_hectareas'] : null,
            'fecha_creacion'    => $row['fecha_creacion'],
            'fecha_actualizacion'=> $row['fecha_actualizacion'],
            'has_geometry'      => (bool)$row['has_geometry'],
            'potreros_count'    => (int)$row['potreros_count'],
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
            'total_pages' => $totalPages,
        ],
    ]);
} catch (PDOException $e) {
    error_log('Error PDO en farms-list.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error de base de datos: ' . $e->getMessage()
    ], 500);
} catch (Throwable $e) {
    error_log('Error en farms-list.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error inesperado: ' . $e->getMessage()
    ], 500);
}

