<?php
/**
 * API: Detalle de región umbral
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

    $codigo = isset($_GET['codigo']) ? strtoupper(trim((string)$_GET['codigo'])) : '';
    if ($codigo === '') {
        respond(['success' => false, 'error' => 'Código requerido'], 422);
    }

    $sql = "
        SELECT
            id_region,
            codigo,
            nombre,
            pais_codigo_iso,
            tipo,
            ST_AsText(geom) AS geom_wkt,
            metadata,
            activo,
            fecha_creacion
        FROM region_umbral
        WHERE codigo = :codigo
    ";

    $row = Database::fetch($sql, ['codigo' => $codigo]);

    if (!$row) {
        respond(['success' => false, 'error' => 'Región no encontrada'], 404);
    }

    respond([
        'success' => true,
        'data'    => [
            'id_region'       => $row['id_region'],
            'codigo'          => $row['codigo'],
            'nombre'          => $row['nombre'],
            'pais_codigo_iso' => $row['pais_codigo_iso'],
            'tipo'            => $row['tipo'],
            'geom_wkt'        => $row['geom_wkt'],
            'metadata'        => $row['metadata'] ? json_decode($row['metadata'], true) : null,
            'activo'          => (bool)$row['activo'],
            'fecha_creacion'  => $row['fecha_creacion'],
        ]
    ]);
} catch (PDOException $e) {
    error_log('Error PDO en regions-detail.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error de base de datos: ' . $e->getMessage()
    ], 500);
} catch (Throwable $e) {
    error_log('Error en regions-detail.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error inesperado: ' . $e->getMessage()
    ], 500);
}
