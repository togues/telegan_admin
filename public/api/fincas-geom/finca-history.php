<?php
/**
 * API: Historial de geometrías aprobadas para una finca
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

    if (!isset($_GET['id_finca']) || !is_numeric($_GET['id_finca'])) {
        respond(['success' => false, 'error' => 'Parámetro id_finca obligatorio'], 400);
    }

    $idFinca = (int)$_GET['id_finca'];

    $sql = "
        SELECT
            h.id_historial,
            h.geometria_wkt,
            ST_GeometryType(h.geometria_postgis) AS geometry_type,
            h.area_hectareas,
            h.comentario,
            h.fecha_aprobacion,
            h.aprobado_por,
            u.nombre_completo AS aprobado_por_nombre
        FROM finca_geometria_historial h
        LEFT JOIN usuario u ON u.id_usuario = h.aprobado_por
        WHERE h.id_finca = :id_finca
        ORDER BY h.fecha_aprobacion DESC
        LIMIT 100
    ";

    $rows = Database::fetchAll($sql, ['id_finca' => $idFinca]);

    $data = array_map(static function (array $row): array {
        return [
            'id_historial'           => (int)$row['id_historial'],
            'geometry_type'          => $row['geometry_type'] ?? null,
            'area_hectareas'         => $row['area_hectareas'] !== null ? (float)$row['area_hectareas'] : null,
            'comentario'             => $row['comentario'],
            'fecha_aprobacion'       => $row['fecha_aprobacion'],
            'aprobado_por'           => $row['aprobado_por'] !== null ? (int)$row['aprobado_por'] : null,
            'aprobado_por_nombre'    => $row['aprobado_por_nombre'],
        ];
    }, $rows);

    respond([
        'success' => true,
        'data'    => $data,
    ]);
} catch (PDOException $e) {
    error_log('Error PDO en fincas-geom/finca-history.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error de base de datos: ' . $e->getMessage()
    ], 500);
} catch (Throwable $e) {
    error_log('Error en fincas-geom/finca-history.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error inesperado: ' . $e->getMessage()
    ], 500);
}
