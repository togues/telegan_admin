<?php
/**
 * API: Detalle de umbral de índice
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

    $id = isset($_GET['id']) ? trim((string)$_GET['id']) : '';
    if ($id === '' || !preg_match('/^[0-9a-fA-F-]{32,36}$/', $id)) {
        respond(['success' => false, 'error' => 'ID inválido'], 422);
    }

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
            idx.nombre AS indice_nombre
        FROM umbral_indice ui
        LEFT JOIN region_umbral reg ON ui.id_region = reg.id_region
        LEFT JOIN indice_satelital idx ON ui.codigo_indice = idx.codigo
        WHERE ui.id_umbral = :id
    ";

    $row = Database::fetch($sql, ['id' => $id]);

    if (!$row) {
        respond(['success' => false, 'error' => 'Umbral no encontrado'], 404);
    }

    respond([
        'success' => true,
        'data'    => [
            'id_umbral'           => $row['id_umbral'],
            'id_region'           => $row['id_region'],
            'codigo_indice'       => $row['codigo_indice'],
            'indice_nombre'       => $row['indice_nombre'],
            'region_codigo'       => $row['region_codigo'],
            'region_nombre'       => $row['region_nombre'],
            'temporada'           => $row['temporada'],
            'fecha_inicio'        => $row['fecha_inicio'],
            'fecha_fin'           => $row['fecha_fin'],
            'valor_min'           => $row['valor_min'] !== null ? (float)$row['valor_min'] : null,
            'valor_max'           => $row['valor_max'] !== null ? (float)$row['valor_max'] : null,
            'nivel_alerta'        => $row['nivel_alerta'],
            'tipo_alerta'         => $row['tipo_alerta'],
            'descripcion'         => $row['descripcion'],
            'recomendacion_accion'=> $row['recomendacion_accion'],
            'metadata'            => $row['metadata'] ? json_decode($row['metadata'], true) : null,
            'creado_por'          => $row['creado_por'],
            'fecha_creacion'      => $row['fecha_creacion']
        ]
    ]);
} catch (PDOException $e) {
    error_log('Error PDO en thresholds-detail.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error de base de datos: ' . $e->getMessage()
    ], 500);
} catch (Throwable $e) {
    error_log('Error en thresholds-detail.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error inesperado: ' . $e->getMessage()
    ], 500);
}
