<?php
/**
 * API: Detalle de una captura de geometría (finca_geometria_captura)
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
        error_log('capture-detail.php: Error al codificar JSON (' . json_last_error_msg() . ')');
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

    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        respond(['success' => false, 'error' => 'Parámetro id obligatorio'], 400);
    }

    $idCaptura = (int)$_GET['id'];

    $sql = "
        SELECT
            c.id_captura,
            c.id_finca,
            c.tipo_geometria,
            c.geometria_wkt,
            c.metadata,
            c.estado,
            c.fecha_captura,
            c.fecha_procesado,
            c.fuente,
            c.capturado_por,
            c.comentario,
            f.nombre_finca,
            f.codigo_telegan,
            f.estado AS estado_finca,
            f.area_hectareas AS area_oficial,
            f.fecha_actualizacion,
            u.nombre_completo AS capturista_nombre,
            u.email AS capturista_email,
            u.ultima_sesion AS capturista_ultima_sesion,
            g.geom_geojson,
            g.is_valid,
            g.validation_message,
            g.geometry_type,
            g.area_hectareas
        FROM finca_geometria_captura c
        INNER JOIN finca f ON f.id_finca = c.id_finca
        LEFT JOIN usuario u ON u.id_usuario = c.capturado_por
        LEFT JOIN LATERAL (
            SELECT
                vw.es_valida AS is_valid,
                vw.mensaje   AS validation_message,
                CASE WHEN vw.es_valida THEN ST_GeometryType(vw.geom_geom) END AS geometry_type,
                CASE WHEN vw.es_valida THEN ST_AsGeoJSON(vw.geom_geom, 6) END AS geom_geojson,
                CASE WHEN vw.es_valida THEN ST_Area(vw.geom_geom::geography) / 10000 ELSE NULL END AS area_hectareas
            FROM validar_geometria_wkt(c.geometria_wkt) vw
        ) g ON TRUE
        WHERE c.id_captura = :id_captura
        LIMIT 1
    ";

    $row = Database::fetch($sql, ['id_captura' => $idCaptura]);

    if (!$row) {
        respond(['success' => false, 'error' => 'Captura no encontrada'], 404);
    }

    $metadata = $row['metadata'] !== null ? json_decode($row['metadata'], true) : null;

    respond([
        'success' => true,
        'data' => [
            'id_captura'               => (int)$row['id_captura'],
            'id_finca'                 => (int)$row['id_finca'],
            'nombre_finca'             => $row['nombre_finca'],
            'codigo_telegan'           => $row['codigo_telegan'],
            'estado_finca'             => $row['estado_finca'],
            'tipo_geometria'           => $row['tipo_geometria'],
            'geometria_wkt'            => $row['geometria_wkt'],
            'geometria_geojson'        => $row['geom_geojson'] ? json_decode($row['geom_geojson'], true) : null,
            'geometria_valida'         => $row['is_valid'] !== null ? (bool)$row['is_valid'] : null,
            'geometria_mensaje'        => $row['validation_message'] ?? null,
            'geometry_type'            => $row['geometry_type'] ?? null,
            'area_hectareas_calculada' => $row['area_hectareas'] !== null ? (float)$row['area_hectareas'] : null,
            'estado_captura'           => $row['estado'],
            'fecha_captura'            => $row['fecha_captura'],
            'fecha_procesado'          => $row['fecha_procesado'],
            'fuente'                   => $row['fuente'],
            'capturado_por'            => $row['capturado_por'] !== null ? (int)$row['capturado_por'] : null,
            'capturista_nombre'        => $row['capturista_nombre'],
            'capturista_email'         => $row['capturista_email'],
            'capturista_ultima_sesion' => $row['capturista_ultima_sesion'],
            'metadata'                 => $metadata,
            'comentario'               => $row['comentario'],
            'validaciones'             => [
                'area_oficial'                => $row['area_oficial'] !== null ? (float)$row['area_oficial'] : null,
                'ultima_actualizacion_finca' => $row['fecha_actualizacion'],
            ],
        ]
    ]);
} catch (PDOException $e) {
    error_log('Error PDO en fincas-geom/capture-detail.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error de base de datos: ' . $e->getMessage()
    ], 500);
} catch (Throwable $e) {
    error_log('Error en fincas-geom/capture-detail.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error inesperado: ' . $e->getMessage()
    ], 500);
}
