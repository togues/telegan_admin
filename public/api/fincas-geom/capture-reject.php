<?php
/**
 * API: Rechazar captura de geometría (marcar como RECHAZADA con comentario)
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
        error_log('capture-reject.php: Error al codificar JSON (' . json_last_error_msg() . ')');
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
    $comentario = isset($payload['comentario']) ? trim((string)$payload['comentario']) : '';

    if ($idCaptura <= 0) {
        respond(['success' => false, 'error' => 'id_captura inválido'], 422);
    }

    if ($comentario === '') {
        respond(['success' => false, 'error' => 'Se requiere un comentario para rechazar'], 422);
    }

    Session::start();
    $adminId = Session::get('admin_id') ?? Session::get('admin_user_id') ?? null;
    $adminId = $adminId !== null ? (int)$adminId : null;

    $pdo = Database::getInstance();
    $pdo->beginTransaction();

    $captura = Database::fetch(
        'SELECT estado FROM finca_geometria_captura WHERE id_captura = :id FOR UPDATE',
        ['id' => $idCaptura]
    );

    if (!$captura) {
        $pdo->rollBack();
        respond(['success' => false, 'error' => 'Captura no encontrada'], 404);
    }

    if (strtoupper($captura['estado']) !== 'PENDIENTE') {
        $pdo->rollBack();
        respond([
            'success' => false,
            'error'   => 'La captura ya fue procesada previamente'
        ], 422);
    }

    Database::update(
        'UPDATE finca_geometria_captura
         SET estado = :estado,
             comentario = :comentario,
             fecha_procesado = NOW()
         WHERE id_captura = :id',
        [
            'estado'     => 'RECHAZADA',
            'comentario' => $comentario . ($adminId !== null ? ' (rechazado por #' . $adminId . ')' : ''),
            'id'         => $idCaptura,
        ]
    );

    $pdo->commit();

    respond([
        'success' => true,
        'message' => 'Captura rechazada correctamente'
    ]);
} catch (PDOException $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error PDO en fincas-geom/capture-reject.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error de base de datos: ' . $e->getMessage()
    ], 500);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error en fincas-geom/capture-reject.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error inesperado: ' . $e->getMessage()
    ], 500);
}
