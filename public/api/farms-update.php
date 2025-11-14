<?php
/**
 * API: Actualizar finca
 */

declare(strict_types=1);

require_once '../../src/Config/Database.php';
require_once '../../src/Config/ApiAuth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, OPTIONS');
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

function sanitizeText(?string $value, int $maxLength = 255): ?string {
    if ($value === null) {
        return null;
    }
    $trimmed = trim($value);
    if ($trimmed === '') {
        return null;
    }
    return mb_substr($trimmed, 0, $maxLength);
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
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

    $pdo = Database::getInstance();
    $pdo->beginTransaction();

    $current = Database::fetch('SELECT * FROM finca WHERE id_finca = :id', ['id' => $idFinca]);
    if (!$current) {
        $pdo->rollBack();
        respond(['success' => false, 'error' => 'Finca no encontrada'], 404);
    }

    $updates = [];
    $params  = ['id' => $idFinca];

    if (array_key_exists('nombre_finca', $payload)) {
        $nombre = sanitizeText($payload['nombre_finca'], 200);
        if ($nombre === null) {
            $pdo->rollBack();
            respond(['success' => false, 'error' => 'El nombre de la finca es obligatorio'], 422);
        }
        $updates[] = 'nombre_finca = :nombre_finca';
        $params['nombre_finca'] = $nombre;
    }

    if (array_key_exists('descripcion', $payload)) {
        $descripcion = $payload['descripcion'] !== null ? mb_substr(trim((string)$payload['descripcion']), 0, 1000) : null;
        $updates[] = 'descripcion = :descripcion';
        $params['descripcion'] = $descripcion;
    }

    if (array_key_exists('codigo_telegan', $payload)) {
        $codigo = $payload['codigo_telegan'] !== null ? sanitizeText((string)$payload['codigo_telegan'], 50) : null;
        $updates[] = 'codigo_telegan = :codigo_telegan';
        $params['codigo_telegan'] = $codigo;
    }

    if (array_key_exists('estado', $payload)) {
        $estado = strtoupper(trim((string)$payload['estado']));
        $allowedStates = ['ACTIVA', 'INACTIVA', 'DESACTIVADA'];
        if (!in_array($estado, $allowedStates, true)) {
            $pdo->rollBack();
            respond(['success' => false, 'error' => 'Estado inválido'], 422);
        }
        $updates[] = 'estado = :estado';
        $params['estado'] = $estado;
    }

    if (array_key_exists('id_pais', $payload)) {
        if ($payload['id_pais'] === null || $payload['id_pais'] === '') {
            $updates[] = 'id_pais = NULL';
        } elseif (is_numeric($payload['id_pais'])) {
            $idPais = (int)$payload['id_pais'];
            $pais = Database::fetch('SELECT id_pais FROM pais WHERE id_pais = :id', ['id' => $idPais]);
            if (!$pais) {
                $pdo->rollBack();
                respond(['success' => false, 'error' => 'País no válido'], 422);
            }
            $updates[] = 'id_pais = :id_pais';
            $params['id_pais'] = $idPais;
        } else {
            $pdo->rollBack();
            respond(['success' => false, 'error' => 'ID de país inválido'], 422);
        }
    }

    if (empty($updates)) {
        $pdo->rollBack();
        respond(['success' => false, 'error' => 'No se enviaron cambios para actualizar'], 422);
    }

    $updates[] = 'fecha_actualizacion = NOW()';

    $sql = 'UPDATE finca SET ' . implode(', ', $updates) . ' WHERE id_finca = :id';
    Database::update($sql, $params);

    $pdo->commit();

    respond([
        'success' => true,
        'message' => 'Finca actualizada correctamente'
    ]);
} catch (PDOException $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error PDO en farms-update.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error de base de datos: ' . $e->getMessage()
    ], 500);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error en farms-update.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error inesperado: ' . $e->getMessage()
    ], 500);
}

