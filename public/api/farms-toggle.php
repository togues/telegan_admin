<?php
/**
 * API: Cambiar estado (soft delete) de finca
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
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
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

    $idFinca = isset($payload['id_finca']) ? (int)$payload['id_finca'] : 0;
    if ($idFinca <= 0) {
        respond(['success' => false, 'error' => 'ID de finca inválido'], 422);
    }

    $estado = null;
    if (isset($payload['estado'])) {
        $estado = strtoupper(trim((string)$payload['estado']));
    } elseif (isset($payload['activo'])) {
        $estado = filter_var($payload['activo'], FILTER_VALIDATE_BOOLEAN) ? 'ACTIVA' : 'INACTIVA';
    }

    if ($estado === null) {
        respond(['success' => false, 'error' => 'Nuevo estado no especificado'], 422);
    }

    $allowedStates = ['ACTIVA', 'INACTIVA', 'DESACTIVADA'];
    if (!in_array($estado, $allowedStates, true)) {
        respond(['success' => false, 'error' => 'Estado inválido'], 422);
    }

    $affected = Database::update(
        'UPDATE finca SET estado = :estado, fecha_actualizacion = NOW() WHERE id_finca = :id',
        ['estado' => $estado, 'id' => $idFinca]
    );

    if ($affected === 0) {
        respond(['success' => false, 'error' => 'No se encontró la finca especificada'], 404);
    }

    respond([
        'success' => true,
        'message' => $estado === 'ACTIVA' ? 'Finca activada' : 'Finca desactivada',
        'estado'  => $estado
    ]);
} catch (PDOException $e) {
    error_log('Error PDO en farms-toggle.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error de base de datos: ' . $e->getMessage()
    ], 500);
} catch (Throwable $e) {
    error_log('Error en farms-toggle.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error inesperado: ' . $e->getMessage()
    ], 500);
}

