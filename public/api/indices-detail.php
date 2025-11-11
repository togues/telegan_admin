<?php
/**
 * API: Detalle de índice satelital (indice_satelital)
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
            codigo,
            nombre,
            categoria,
            descripcion,
            formula,
            unidad,
            valor_min,
            valor_max,
            interpretacion_bueno,
            interpretacion_malo,
            color_escala,
            activo,
            fecha_creacion
        FROM indice_satelital
        WHERE codigo = :codigo
    ";

    $row = Database::fetch($sql, ['codigo' => $codigo]);

    if (!$row) {
        respond(['success' => false, 'error' => 'Índice no encontrado'], 404);
    }

    respond([
        'success' => true,
        'data'    => [
            'codigo'                => $row['codigo'],
            'nombre'                => $row['nombre'],
            'categoria'             => $row['categoria'],
            'descripcion'           => $row['descripcion'],
            'formula'               => $row['formula'],
            'unidad'                => $row['unidad'],
            'valor_min'             => $row['valor_min'] !== null ? (float)$row['valor_min'] : null,
            'valor_max'             => $row['valor_max'] !== null ? (float)$row['valor_max'] : null,
            'interpretacion_bueno'  => $row['interpretacion_bueno'],
            'interpretacion_malo'   => $row['interpretacion_malo'],
            'color_escala'          => $row['color_escala'] ? json_decode($row['color_escala'], true) : null,
            'activo'                => (bool)$row['activo'],
            'fecha_creacion'        => $row['fecha_creacion'],
        ]
    ]);
} catch (PDOException $e) {
    error_log('Error PDO en indices-detail.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error de base de datos: ' . $e->getMessage()
    ], 500);
} catch (Throwable $e) {
    error_log('Error en indices-detail.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error inesperado: ' . $e->getMessage()
    ], 500);
}
