<?php
/**
 * API: Cambiar estado activo/inactivo de proveedor (tabla proveedor)
 */

declare(strict_types=1);

require_once '../../src/Config/Database.php';
require_once '../../src/Config/ApiAuth.php';

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
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
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
    if (!is_array($payload) || empty($payload['id_proveedor'])) {
        respond(['success' => false, 'error' => 'ID de proveedor requerido'], 422);
    }

    $id = (int)$payload['id_proveedor'];
    if ($id <= 0) {
        respond(['success' => false, 'error' => 'ID de proveedor inválido'], 422);
    }

    $activoRaw = $payload['activo'] ?? null;
    $activo = filter_var($activoRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

    if ($activo === null) {
        respond(['success' => false, 'error' => 'Valor de activo inválido'], 422);
    }

    $updatedRows = Database::update(
        'UPDATE proveedor SET activo = CAST(:activo AS BOOLEAN) WHERE id_proveedor = :id',
        [
            'activo' => $activo ? 'true' : 'false',
            'id' => $id
        ]
    );

    if ($updatedRows === 0) {
        respond(['success' => false, 'error' => 'Proveedor no encontrado'], 404);
    }

    respond([
        'success' => true,
        'message' => 'Estado del proveedor actualizado correctamente',
        'data'    => [
            'id_proveedor' => $id,
            'activo'       => $activo
        ]
    ]);
} catch (PDOException $e) {
    error_log('Error PDO en providers-toggle.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error de base de datos: ' . $e->getMessage()
    ], 500);
} catch (Throwable $e) {
    error_log('Error en providers-toggle.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error inesperado: ' . $e->getMessage()
    ], 500);
}


