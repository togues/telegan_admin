<?php
/**
 * API: Actualizar índice satelital (indice_satelital)
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
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
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
    if (!is_array($payload) || empty($payload['codigo'])) {
        respond(['success' => false, 'error' => 'Código requerido'], 422);
    }

    $codigo = strtoupper(trim((string)$payload['codigo']));
    if ($codigo === '') {
        respond(['success' => false, 'error' => 'Código inválido'], 422);
    }

    $pdo = Database::getInstance();
    $pdo->beginTransaction();

    $current = Database::fetch('SELECT * FROM indice_satelital WHERE codigo = :codigo', ['codigo' => $codigo]);
    if (!$current) {
        $pdo->rollBack();
        respond(['success' => false, 'error' => 'Índice no encontrado'], 404);
    }

    $nombre       = array_key_exists('nombre', $payload) ? sanitizeText($payload['nombre'], 200) : $current['nombre'];
    if ($nombre === null) {
        $pdo->rollBack();
        respond(['success' => false, 'error' => 'El nombre es requerido'], 422);
    }

    $categoria    = array_key_exists('categoria', $payload) ? sanitizeText($payload['categoria'], 100) : $current['categoria'];
    $descripcion  = array_key_exists('descripcion', $payload) ? sanitizeText($payload['descripcion'], 500) : $current['descripcion'];
    $formula      = array_key_exists('formula', $payload) ? sanitizeText($payload['formula'], 500) : $current['formula'];
    $unidad       = array_key_exists('unidad', $payload) ? sanitizeText($payload['unidad'], 50) : $current['unidad'];

    $valorMin = array_key_exists('valor_min', $payload) ? ($payload['valor_min'] === '' ? null : (float)$payload['valor_min']) : $current['valor_min'];
    $valorMax = array_key_exists('valor_max', $payload) ? ($payload['valor_max'] === '' ? null : (float)$payload['valor_max']) : $current['valor_max'];

    $interpBueno = array_key_exists('interpretacion_bueno', $payload) ? sanitizeText($payload['interpretacion_bueno'], 500) : $current['interpretacion_bueno'];
    $interpMalo  = array_key_exists('interpretacion_malo', $payload) ? sanitizeText($payload['interpretacion_malo'], 500) : $current['interpretacion_malo'];

    $colorEscala = $current['color_escala'] ? json_decode($current['color_escala'], true) : null;
    if (array_key_exists('color_escala', $payload)) {
        if ($payload['color_escala'] === null || $payload['color_escala'] === '') {
            $colorEscala = null;
        } else {
            $decoded = is_array($payload['color_escala']) ? $payload['color_escala'] : json_decode((string)$payload['color_escala'], true);
            if (!is_array($decoded)) {
                $pdo->rollBack();
                respond(['success' => false, 'error' => 'color_escala debe ser un arreglo JSON válido'], 422);
            }
            $colorEscala = $decoded;
        }
    }

    $activo = array_key_exists('activo', $payload)
        ? filter_var($payload['activo'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
        : (bool)$current['activo'];
    if ($activo === null) {
        $activo = (bool)$current['activo'];
    }

    $updateSql = "
        UPDATE indice_satelital
        SET
            nombre = :nombre,
            categoria = :categoria,
            descripcion = :descripcion,
            formula = :formula,
            unidad = :unidad,
            valor_min = :valor_min,
            valor_max = :valor_max,
            interpretacion_bueno = :interpretacion_bueno,
            interpretacion_malo = :interpretacion_malo,
            color_escala = :color_escala,
            activo = :activo
        WHERE codigo = :codigo
        RETURNING
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
    ";

    $updated = Database::fetch($updateSql, [
        'nombre'               => $nombre,
        'categoria'            => $categoria,
        'descripcion'          => $descripcion,
        'formula'              => $formula,
        'unidad'               => $unidad,
        'valor_min'            => $valorMin,
        'valor_max'            => $valorMax,
        'interpretacion_bueno' => $interpBueno,
        'interpretacion_malo'  => $interpMalo,
        'color_escala'         => $colorEscala !== null ? json_encode($colorEscala, JSON_UNESCAPED_UNICODE) : null,
        'activo'               => $activo,
        'codigo'               => $codigo
    ]);

    $pdo->commit();

    respond([
        'success' => true,
        'message' => 'Índice actualizado correctamente',
        'data'    => [
            'codigo'                => $updated['codigo'],
            'nombre'                => $updated['nombre'],
            'categoria'             => $updated['categoria'],
            'descripcion'           => $updated['descripcion'],
            'formula'               => $updated['formula'],
            'unidad'                => $updated['unidad'],
            'valor_min'             => $updated['valor_min'] !== null ? (float)$updated['valor_min'] : null,
            'valor_max'             => $updated['valor_max'] !== null ? (float)$updated['valor_max'] : null,
            'interpretacion_bueno'  => $updated['interpretacion_bueno'],
            'interpretacion_malo'   => $updated['interpretacion_malo'],
            'color_escala'          => $updated['color_escala'] ? json_decode($updated['color_escala'], true) : null,
            'activo'                => (bool)$updated['activo'],
            'fecha_creacion'        => $updated['fecha_creacion'],
        ]
    ]);
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error PDO en indices-update.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error de base de datos: ' . $e->getMessage()
    ], 500);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error en indices-update.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error inesperado: ' . $e->getMessage()
    ], 500);
}
