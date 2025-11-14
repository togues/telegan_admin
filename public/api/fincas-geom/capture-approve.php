<?php
/**
 * API: Aprobar captura de geometría (migrar WKT a PostGIS y registrar historial)
 */

declare(strict_types=1);

require_once '../../../src/Config/Database.php';
require_once '../../../src/Config/ApiAuth.php';
require_once '../../../src/Config/Session.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Token, X-API-Timestamp, X-Session-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

function respond(array $payload, int $status = 200): void {
    http_response_code($status);
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);

    if ($json === false) {
        error_log('capture-approve.php: Error al codificar JSON (' . json_last_error_msg() . ')');
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
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(['success' => false, 'error' => 'Método no permitido'], 405);
    }

    $validation = ApiAuth::validateRequest();
    if (!$validation['valid']) {
        respond([
            'success' => false,
            'error'   => 'Acceso no autorizado: ' . ($validation['error'] ?? 'Token inválido')
        ], 401);
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        respond(['success' => false, 'error' => 'JSON inválido'], 400);
    }

    $idCaptura = isset($payload['id_captura']) ? (int)$payload['id_captura'] : 0;
    $comentario = isset($payload['comentario']) ? trim((string)$payload['comentario']) : null;

    if ($idCaptura <= 0) {
        respond(['success' => false, 'error' => 'id_captura inválido'], 422);
    }

    Session::start();
    $adminId = Session::get('admin_id') ?? Session::get('admin_user_id') ?? null;
    if ($adminId !== null) {
        $adminId = (int)$adminId;
    }

    $pdo = Database::getInstance();
    $pdo->beginTransaction();

    $captura = Database::fetch(
        'SELECT c.*, f.nombre_finca
         FROM finca_geometria_captura c
         INNER JOIN finca f ON f.id_finca = c.id_finca
         WHERE c.id_captura = :id FOR UPDATE',
        ['id' => $idCaptura]
    );

    if (!$captura) {
        $pdo->rollBack();
        respond(['success' => false, 'error' => 'Captura no encontrada'], 404);
    }

    if (strtoupper((string)$captura['estado']) !== 'PENDIENTE') {
        $pdo->rollBack();
        respond(['success' => false, 'error' => 'La captura ya fue procesada previamente'], 422);
    }

    if (empty($captura['geometria_wkt'])) {
        $pdo->rollBack();
        respond(['success' => false, 'error' => 'La captura no contiene geometría WKT'], 422);
    }

    $validation = Database::fetch(
        'SELECT
            ST_IsValid(ST_GeomFromText(:wkt, 4326)) AS is_valid,
            ST_IsValidReason(ST_GeomFromText(:wkt, 4326)) AS reason,
            ST_GeometryType(ST_GeomFromText(:wkt, 4326)) AS geometry_type,
            ST_Area(ST_GeomFromText(:wkt, 4326)::geography) AS area_m2,
            ST_AsGeoJSON(ST_GeomFromText(:wkt, 4326), 6) AS geom_geojson
        ',
        ['wkt' => $captura['geometria_wkt']]
    );

    if (!$validation || !$validation['is_valid']) {
        $msg = $comentario !== null && $comentario !== '' ? $comentario : ($validation['reason'] ?? 'Geometría inválida');
        Database::update(
            'UPDATE finca_geometria_captura
             SET estado = :estado,
                 comentario = :comentario,
                 fecha_procesado = NOW()
             WHERE id_captura = :id',
            [
                'estado'     => 'RECHAZADA',
                'comentario' => $msg,
                'id'         => $idCaptura,
            ]
        );
        $pdo->commit();
        respond([
            'success' => false,
            'error'   => $validation['reason'] ?? 'La geometría no es válida'
        ], 422);
    }

    $geometryType = $validation['geometry_type'] ?? null;
    if ($geometryType && stripos($geometryType, 'polygon') === false) {
        $msg = $comentario !== null && $comentario !== '' ? $comentario : 'Tipo de geometría no compatible con la finca';
        Database::update(
            'UPDATE finca_geometria_captura
             SET estado = :estado,
                 comentario = :comentario,
                 fecha_procesado = NOW()
             WHERE id_captura = :id',
            [
                'estado'     => 'RECHAZADA',
                'comentario' => $msg,
                'id'         => $idCaptura,
            ]
        );
        $pdo->commit();
        respond([
            'success' => false,
            'error'   => 'La geometría debe ser Polígono o MultiPolígono para aprobarse'
        ], 422);
    }

    $areaHectareas = $validation['area_m2'] !== null ? ((float)$validation['area_m2'] / 10000) : null;
    $geomWkt = $captura['geometria_wkt'];
    $aprobadoComentario = $comentario !== null && $comentario !== '' ? $comentario : 'Aprobada';

    Database::query(
        'INSERT INTO finca_geometria_historial (
            id_finca,
            geometria_wkt,
            geometria_postgis,
            area_hectareas,
            aprobado_por,
            comentario,
            fecha_aprobacion
        ) VALUES (
            :id_finca,
            :geometria_wkt,
            ST_SetSRID(ST_GeomFromText(:geometria_wkt, 4326), 4326),
            :area_hectareas,
            :aprobado_por,
            :comentario,
            NOW()
        )',
        [
            'id_finca'      => $captura['id_finca'],
            'geometria_wkt' => $geomWkt,
            'area_hectareas'=> $areaHectareas,
            'aprobado_por'  => $adminId,
            'comentario'    => $aprobadoComentario,
        ]
    );

    Database::update(
        'UPDATE finca
         SET geometria_wkt = :geometria_wkt,
             geometria_postgis = ST_SetSRID(ST_GeomFromText(:geometria_wkt, 4326), 4326),
             area_hectareas = :area_hectareas,
             fecha_actualizacion = NOW()
         WHERE id_finca = :id_finca',
        [
            'geometria_wkt' => $geomWkt,
            'area_hectareas'=> $areaHectareas,
            'id_finca'      => $captura['id_finca'],
        ]
    );

    Database::update(
        'UPDATE finca_geometria_captura
         SET estado = :estado,
             comentario = :comentario,
             fecha_procesado = NOW()
         WHERE id_captura = :id',
        [
            'estado'     => 'VALIDADA',
            'comentario' => $aprobadoComentario,
            'id'         => $idCaptura,
        ]
    );

    $pdo->commit();

    respond([
        'success' => true,
        'message' => 'Geometría aprobada correctamente',
        'data'    => [
            'area_hectareas' => $areaHectareas,
            'geometry_type'  => $validation['geometry_type'] ?? null,
            'geom_geojson'   => $validation['geom_geojson'] ? json_decode($validation['geom_geojson'], true) : null,
        ]
    ]);
} catch (PDOException $e) {
    error_log('Error PDO en fincas-geom/capture-approve.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error de base de datos: ' . $e->getMessage()
    ], 500);
} catch (Throwable $e) {
    error_log('Error en fincas-geom/capture-approve.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error inesperado: ' . $e->getMessage()
    ], 500);
}
