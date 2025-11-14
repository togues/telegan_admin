<?php
/**
 * API: Listado de capturas de geometría provenientes de la PWA (finca_geometria_captura)
 */

declare(strict_types=1);

require_once '../../../src/Config/Database.php';
require_once '../../../src/Config/ApiAuth.php';

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
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);

    if ($json === false) {
        error_log('captures-list.php: Error al codificar JSON (' . json_last_error_msg() . ')');
        $fallback = json_encode([
            'success' => false,
            'error'   => 'Respuesta inválida (codificación)'
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        echo $fallback !== false ? $fallback : '{"success":false,"error":"Respuesta inválida"}';
    } else {
        echo $json;
    }
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
    $estado     = isset($_GET['estado']) ? strtoupper(trim((string)$_GET['estado'])) : '';
    $tipo       = isset($_GET['tipo']) ? strtoupper(trim((string)$_GET['tipo'])) : '';
    $idFinca    = isset($_GET['id_finca']) && is_numeric($_GET['id_finca']) ? (int)$_GET['id_finca'] : null;
    $capturado  = isset($_GET['capturado_por']) && is_numeric($_GET['capturado_por']) ? (int)$_GET['capturado_por'] : null;
    $fechaDesde = isset($_GET['fecha_desde']) ? trim((string)$_GET['fecha_desde']) : '';
    $fechaHasta = isset($_GET['fecha_hasta']) ? trim((string)$_GET['fecha_hasta']) : '';
    $page       = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $pageSize   = isset($_GET['page_size']) && is_numeric($_GET['page_size']) ? (int)$_GET['page_size'] : 20;
    $sortBy     = isset($_GET['sort_by']) ? trim((string)$_GET['sort_by']) : 'fecha_captura';
    $sortOrder  = isset($_GET['sort_order']) ? strtoupper(trim((string)$_GET['sort_order'])) : 'DESC';

    if ($pageSize > 100) {
        $pageSize = 100;
    } elseif ($pageSize < 1) {
        $pageSize = 20;
    }

    $offset = ($page - 1) * $pageSize;

    $allowedSortColumns = [
        'fecha_captura',
        'fecha_procesado',
        'estado',
        'tipo_geometria',
        'area_estimado',
        'nombre_finca'
    ];
    if (!in_array($sortBy, $allowedSortColumns, true)) {
        $sortBy = 'fecha_captura';
    }
    if (!in_array($sortOrder, ['ASC', 'DESC'], true)) {
        $sortOrder = 'DESC';
    }

    $whereParts = [];
    $params     = [];

    if ($q !== '') {
        $whereParts[] = '(
            f.nombre_finca ILIKE :q OR
            f.codigo_telegan ILIKE :q OR
            c.comentario ILIKE :q
        )';
        $params['q'] = '%' . $q . '%';
    }

    if ($estado !== '') {
        $allowedEstados = ['PENDIENTE', 'VALIDADA', 'RECHAZADA'];
        if (!in_array($estado, $allowedEstados, true)) {
            respond(['success' => false, 'error' => 'Estado inválido'], 400);
        }
        $whereParts[] = 'c.estado = :estado';
        $params['estado'] = $estado;
    }

    if ($tipo !== '') {
        $whereParts[] = 'UPPER(c.tipo_geometria) = :tipo';
        $params['tipo'] = $tipo;
    }

    if ($idFinca !== null) {
        $whereParts[] = 'c.id_finca = :id_finca';
        $params['id_finca'] = $idFinca;
    }

    if ($capturado !== null) {
        $whereParts[] = 'c.capturado_por = :capturado_por';
        $params['capturado_por'] = $capturado;
    }

    if ($fechaDesde !== '') {
        $whereParts[] = 'c.fecha_captura >= :fecha_desde';
        $params['fecha_desde'] = $fechaDesde;
    }

    if ($fechaHasta !== '') {
        $whereParts[] = 'c.fecha_captura <= :fecha_hasta';
        $params['fecha_hasta'] = $fechaHasta;
    }

    $whereSql = '';
    if (!empty($whereParts)) {
        $whereSql = 'WHERE ' . implode(' AND ', $whereParts);
    }

    $countSql = "
        SELECT COUNT(*) AS total
        FROM finca_geometria_captura c
        INNER JOIN finca f ON f.id_finca = c.id_finca
        {$whereSql}
    ";
    $countRow = Database::fetch($countSql, $params) ?? ['total' => 0];
    $totalRows = (int)$countRow['total'];

    $sql = "
        SELECT
            c.id_captura,
            c.id_finca,
            c.tipo_geometria,
            c.estado,
            c.fecha_captura,
            c.fecha_procesado,
            c.fuente,
            c.capturado_por,
            c.metadata,
            c.comentario,
            f.nombre_finca,
            f.codigo_telegan,
            f.estado AS estado_finca,
            u.nombre_completo AS capturista_nombre,
            u.email AS capturista_email,
            u.ultima_sesion AS capturista_ultima_sesion,
            CASE
                WHEN g.is_valid THEN g.area_hectareas
                ELSE NULL
            END AS area_estimado,
            g.is_valid,
            g.geometry_type,
            g.geom_geojson,
            g.validation_message
        FROM finca_geometria_captura c
        INNER JOIN finca f ON f.id_finca = c.id_finca
        LEFT JOIN usuario u ON u.id_usuario = c.capturado_por
        LEFT JOIN LATERAL (
            SELECT
                vw.es_valida AS is_valid,
                vw.mensaje   AS validation_message,
                CASE WHEN vw.es_valida THEN ST_GeometryType(vw.geom_geom) END AS geometry_type,
                CASE WHEN vw.es_valida THEN ST_Area(vw.geom_geom::geography) / 10000 ELSE NULL END AS area_hectareas,
                CASE WHEN vw.es_valida THEN ST_AsGeoJSON(vw.geom_geom, 6) END AS geom_geojson
            FROM validar_geometria_wkt(c.geometria_wkt) vw
        ) g ON TRUE
        {$whereSql}
        ORDER BY {$sortBy} {$sortOrder}
        LIMIT :limit OFFSET :offset
    ";

    $params['limit']  = $pageSize;
    $params['offset'] = $offset;

    $rows = Database::fetchAll($sql, $params);

    $data = array_map(static function (array $row): array {
        return [
            'id_captura'               => (int)$row['id_captura'],
            'id_finca'                 => (int)$row['id_finca'],
            'nombre_finca'             => $row['nombre_finca'],
            'codigo_telegan'           => $row['codigo_telegan'],
            'estado_finca'             => $row['estado_finca'],
            'tipo_geometria'           => $row['tipo_geometria'],
            'estado'                   => $row['estado'],
            'fecha_captura'            => $row['fecha_captura'],
            'fecha_procesado'          => $row['fecha_procesado'],
            'fuente'                   => $row['fuente'],
            'capturado_por'            => $row['capturado_por'] !== null ? (int)$row['capturado_por'] : null,
            'capturista_nombre'        => $row['capturista_nombre'],
            'capturista_email'         => $row['capturista_email'],
            'capturista_ultima_sesion' => $row['capturista_ultima_sesion'],
            'metadata'                 => $row['metadata'] !== null ? json_decode($row['metadata'], true) : null,
            'comentario'               => $row['comentario'],
            'area_estimado'            => $row['area_estimado'] !== null ? (float)$row['area_estimado'] : null,
            'is_valid'                 => $row['is_valid'] !== null ? (bool)$row['is_valid'] : null,
            'geometry_type'            => $row['geometry_type'],
            'geometria_geojson'        => $row['geom_geojson'] ? json_decode($row['geom_geojson'], true) : null,
            'validation_message'       => $row['validation_message'] ?? null,
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
    error_log('Error PDO en fincas-geom/captures-list.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error de base de datos: ' . $e->getMessage()
    ], 500);
} catch (Throwable $e) {
    error_log('Error en fincas-geom/captures-list.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error inesperado: ' . $e->getMessage()
    ], 500);
}
