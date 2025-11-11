<?php
/**
 * API: Detalle de proveedor satelital/climático (tabla proveedor)
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

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        respond(['success' => false, 'error' => 'ID de proveedor inválido'], 422);
    }

    $sql = "
        SELECT
            id_proveedor,
            codigo,
            nombre,
            descripcion,
            url_api,
            requiere_autenticacion,
            api_key_encriptada,
            frecuencia_horas,
            ventana_temporal_dias,
            max_nubosidad_pct,
            contacto,
            metadata,
            activo,
            fecha_ultima_consulta,
            fecha_creacion
        FROM proveedor
        WHERE id_proveedor = :id
    ";

    $row = Database::fetch($sql, ['id' => $id]);

    if (!$row) {
        respond(['success' => false, 'error' => 'Proveedor no encontrado'], 404);
    }

    respond([
        'success' => true,
        'data'    => [
            'id_proveedor'           => (int)$row['id_proveedor'],
            'codigo'                 => $row['codigo'],
            'nombre'                 => $row['nombre'],
            'descripcion'            => $row['descripcion'],
            'url_api'                => $row['url_api'],
            'requiere_autenticacion' => (bool)$row['requiere_autenticacion'],
            'api_key_encriptada'     => $row['api_key_encriptada'],
            'frecuencia_horas'       => $row['frecuencia_horas'] !== null ? (int)$row['frecuencia_horas'] : null,
            'ventana_temporal_dias'  => $row['ventana_temporal_dias'] !== null ? (int)$row['ventana_temporal_dias'] : null,
            'max_nubosidad_pct'      => $row['max_nubosidad_pct'] !== null ? (float)$row['max_nubosidad_pct'] : null,
            'contacto'               => $row['contacto'] !== null ? json_decode($row['contacto'], true) : null,
            'metadata'               => $row['metadata'] !== null ? json_decode($row['metadata'], true) : null,
            'activo'                 => (bool)$row['activo'],
            'fecha_ultima_consulta'  => $row['fecha_ultima_consulta'],
            'fecha_creacion'         => $row['fecha_creacion'],
        ]
    ]);
} catch (PDOException $e) {
    error_log('Error PDO en providers-detail.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error de base de datos: ' . $e->getMessage()
    ], 500);
} catch (Throwable $e) {
    error_log('Error en providers-detail.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error inesperado: ' . $e->getMessage()
    ], 500);
}


