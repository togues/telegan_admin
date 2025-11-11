<?php
/**
 * API: Listado de umbrales por índice y región (umbral_indice)
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

    $q            = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
    $indice       = isset($_GET['indice']) ? trim((string)$_GET['indice']) : '';
    $region       = isset($_GET['region']) ? trim((string)$_GET['region']) : '';
    $temporada    = isset($_GET['temporada']) ? trim((string)$_GET['temporada']) : '';
    $nivel        = isset($_GET['nivel']) ? trim((string)$_GET['nivel']) : '';
    $page         = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $pageSize     = isset($_GET['page_size']) && is_numeric($_GET['page_size']) ? (int)$_GET['page_size'] : 20;
    $sortBy       = isset($_GET['sort_by']) ? trim((string)$_GET['sort_by']) : 'fecha_creacion';
    $sortOrder    = isset($_GET['sort_order']) ? strtoupper(trim((string)$_GET['sort_order'])) : 'DESC';

    if ($pageSize > 100) {
        $pageSize = 100;
    } elseif ($pageSize < 1) {
        $pageSize = 20;
    }

    $offset = ($page - 1) * $pageSize;

    $allowedSortColumns = [
        'fecha_creacion', 'temporada', 'nivel_alerta', 'codigo_indice', 'codigo_region'
    ];
    if (!in_array($sortBy, $allowedSortColumns, true)) {
        $sortBy = 'fecha_creacion';
    }
    if (!in_array($sortOrder, ['ASC', 'DESC'], true)) {
        $sortOrder = 'DESC';
    }

    $where = [];
    $params = [];

    if ($q !== '') {
        $where[] = '(ui.descripcion ILIKE :q OR ui.recomendacion_accion ILIKE :q OR idx.nombre ILIKE :q OR reg.nombre ILIKE :q)';
        $params['q'] = '%' . $q . '%';
    }
    if ($indice !== '') {
        $where[] = 'ui.codigo_indice = :indice';
        $params['indice'] = $indice;
    }
    if ($region !== '') {
        $where[] = 'reg.codigo = :region';
        $params['region'] = $region;
    }
    if ($temporada !== '') {
        $where[] = 'ui.temporada = :temporada';
        $params['temporada'] = $temporada;
    }
    if ($nivel !== '') {
        $where[] = 'ui.nivel_alerta = :nivel';
        $params['nivel'] = $nivel;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countSql = "
        SELECT COUNT(*) AS total
        FROM umbral_indice ui
        LEFT JOIN region_umbral reg ON ui.id_region = reg.id_region
        LEFT JOIN indice_satelital idx ON ui.codigo_indice = idx.codigo
        {$whereSql}
    ";
    $countRow = Database::fetch($countSql, $params) ?? ['total' => 0];
    $totalRows = (int)$countRow['total'];

    $sql = "
        SELECT
            ui.id_umbral,
            ui.id_region,
            ui.codigo_indice,
            ui.temporada,
            ui.fecha_inicio,
            ui.fecha_fin,
            ui.valor_min,
            ui.valor_max,
            ui.nivel_alerta,
            ui.tipo_alerta,
            ui.descripcion,
            ui.recomendacion_accion,
            ui.metadata,
            ui.creado_por,
            ui.fecha_creacion,
            reg.codigo AS region_codigo,
            reg.nombre AS region_nombre,
            reg.pais_codigo_iso,
            idx.nombre AS indice_nombre
        FROM umbral_indice ui
        LEFT JOIN region_umbral reg ON ui.id_region = reg.id_region
        LEFT JOIN indice_satelital idx ON ui.codigo_indice = idx.codigo
        {$whereSql}
        ORDER BY {$sortBy} {$sortOrder}
        LIMIT :limit OFFSET :offset
    ";

    $params['limit'] = $pageSize;
    $params['offset'] = $offset;

    $rows = Database::fetchAll($sql, $params);

    $data = array_map(static function (array $row): array {
        return [
            'id_umbral'            => $row['id_umbral'],
            'id_region'            => $row['id_region'],
            'codigo_indice'        => $row['codigo_indice'],
            'indice_nombre'        => $row['indice_nombre'],
            'region_codigo'        => $row['region_codigo'],
            'region_nombre'        => $row['region_nombre'],
            'pais_codigo_iso'      => $row['pais_codigo_iso'],
            'temporada'            => $row['temporada'],
            'fecha_inicio'         => $row['fecha_inicio'],
            'fecha_fin'            => $row['fecha_fin'],
            'valor_min'            => $row['valor_min'] !== null ? (float)$row['valor_min'] : null,
            'valor_max'            => $row['valor_max'] !== null ? (float)$row['valor_max'] : null,
            'nivel_alerta'         => $row['nivel_alerta'],
            'tipo_alerta'          => $row['tipo_alerta'],
            'descripcion'          => $row['descripcion'],
            'recomendacion_accion' => $row['recomendacion_accion'],
            'metadata'             => $row['metadata'] ? json_decode($row['metadata'], true) : null,
            'creado_por'           => $row['creado_por'],
            'fecha_creacion'       => $row['fecha_creacion']
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
    error_log('Error PDO en thresholds-list.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error de base de datos: ' . $e->getMessage()
    ], 500);
} catch (Throwable $e) {
    error_log('Error en thresholds-list.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error inesperado: ' . $e->getMessage()
    ], 500);
}
