<?php
/**
 * API: Detalle de finca
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

    $id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        respond(['success' => false, 'error' => 'ID inválido'], 422);
    }

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
            u.nombre_completo AS creador_nombre,
            u.email AS creador_email,
            p.codigo_iso2,
            p.nombre_pais,
            CASE
                WHEN f.geometria_wkt IS NOT NULL AND LENGTH(TRIM(f.geometria_wkt)) > 0 THEN TRUE
                ELSE FALSE
            END AS has_geometry,
            COALESCE(pc.total_potreros, 0) AS potreros_count,
            f.geometria_wkt
        FROM finca f
        LEFT JOIN usuario u ON u.id_usuario = f.id_usuario_creador
        LEFT JOIN pais p ON p.id_pais = f.id_pais
        LEFT JOIN LATERAL (
            SELECT COUNT(*)::INT AS total_potreros
            FROM potrero pt
            WHERE pt.id_finca = f.id_finca
        ) pc ON TRUE
        WHERE f.id_finca = :id
    ";

    $row = Database::fetch($sql, ['id' => $id]);

    if (!$row) {
        respond(['success' => false, 'error' => 'Finca no encontrada'], 404);
    }

    respond([
        'success' => true,
        'data'    => [
            'id_finca'           => (int)$row['id_finca'],
            'nombre_finca'       => $row['nombre_finca'],
            'codigo_telegan'     => $row['codigo_telegan'],
            'descripcion'        => $row['descripcion'],
            'estado'             => $row['estado'],
            'id_pais'            => $row['id_pais'] !== null ? (int)$row['id_pais'] : null,
            'pais_codigo'        => $row['codigo_iso2'],
            'pais_nombre'        => $row['nombre_pais'],
            'creador_id'         => $row['id_usuario_creador'] !== null ? (int)$row['id_usuario_creador'] : null,
            'creador_nombre'     => $row['creador_nombre'],
            'creador_email'      => $row['creador_email'],
            'area_hectareas'     => $row['area_hectareas'] !== null ? (float)$row['area_hectareas'] : null,
            'fecha_creacion'     => $row['fecha_creacion'],
            'fecha_actualizacion'=> $row['fecha_actualizacion'],
            'has_geometry'       => (bool)$row['has_geometry'],
            'potreros_count'     => (int)$row['potreros_count'],
            'geometria_wkt'      => $row['geometria_wkt'],
        ]
    ]);
} catch (PDOException $e) {
    error_log('Error PDO en farms-detail.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error de base de datos: ' . $e->getMessage()
    ], 500);
} catch (Throwable $e) {
    error_log('Error en farms-detail.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error inesperado: ' . $e->getMessage()
    ], 500);
}

