<?php
/**
 * API: Crear umbral de índice
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

function sanitizeText(?string $value, int $maxLength = 255): ?string {
    if ($value === null) return null;
    $trimmed = trim($value);
    if ($trimmed === '') return null;
    return mb_substr($trimmed, 0, $maxLength);
}

function validateDateOrNull(?string $value): ?string {
    if ($value === null) return null;
    $trimmed = trim($value);
    if ($trimmed === '') return null;
    $dt = date_create($trimmed);
    return $dt ? $dt->format('Y-m-d') : null;
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

    $codigoIndice = isset($payload['codigo_indice']) ? trim((string)$payload['codigo_indice']) : '';
    if ($codigoIndice === '') {
        respond(['success' => false, 'error' => 'El código de índice es requerido'], 422);
    }

    $idRegion = null;
    if (!empty($payload['id_region'])) {
        $candidate = trim((string)$payload['id_region']);
        if (!preg_match('/^[0-9a-fA-F-]{32,36}$/', $candidate)) {
            respond(['success' => false, 'error' => 'El id_region debe ser un UUID válido'], 422);
        }
        $idRegion = $candidate;
    }

    $temporada = sanitizeText($payload['temporada'] ?? null, 50);
    $fechaInicio = validateDateOrNull($payload['fecha_inicio'] ?? null);
    $fechaFin = validateDateOrNull($payload['fecha_fin'] ?? null);

    $valorMin = isset($payload['valor_min']) && $payload['valor_min'] !== '' ? (float)$payload['valor_min'] : null;
    $valorMax = isset($payload['valor_max']) && $payload['valor_max'] !== '' ? (float)$payload['valor_max'] : null;

    $nivelAlerta = isset($payload['nivel_alerta']) ? strtoupper(trim((string)$payload['nivel_alerta'])) : '';
    $allowedLevels = ['INFO', 'BAJO', 'MODERADO', 'ALTO', 'CRITICO'];
    if (!in_array($nivelAlerta, $allowedLevels, true)) {
        respond(['success' => false, 'error' => 'Nivel de alerta inválido'], 422);
    }

    $tipoAlerta = sanitizeText($payload['tipo_alerta'] ?? null, 100);
    $descripcion = sanitizeText($payload['descripcion'] ?? null, 500);
    $recomendacion = sanitizeText($payload['recomendacion_accion'] ?? null, 500);

    $metadata = null;
    if (isset($payload['metadata']) && $payload['metadata'] !== null) {
        $metadata = is_array($payload['metadata']) ? $payload['metadata'] : json_decode((string)$payload['metadata'], true);
        if (!is_array($metadata)) {
            respond(['success' => false, 'error' => 'metadata debe ser un objeto JSON válido'], 422);
        }
    }

    $creadoPor = isset($payload['creado_por']) ? trim((string)$payload['creado_por']) : null;

    $pdo = Database::getInstance();
    $pdo->beginTransaction();

    // Validar existencia de índice
    $idxExists = Database::fetch('SELECT codigo FROM indice_satelital WHERE codigo = :codigo', ['codigo' => $codigoIndice]);
    if (!$idxExists) {
        $pdo->rollBack();
        respond(['success' => false, 'error' => 'El índice especificado no existe'], 404);
    }

    if ($idRegion !== null) {
        $regExists = Database::fetch('SELECT id_region FROM region_umbral WHERE id_region = :id', ['id' => $idRegion]);
        if (!$regExists) {
            $pdo->rollBack();
            respond(['success' => false, 'error' => 'La región especificada no existe'], 404);
        }
    }

    $insertSql = "
        INSERT INTO umbral_indice (
            id_region,
            codigo_indice,
            temporada,
            fecha_inicio,
            fecha_fin,
            valor_min,
            valor_max,
            nivel_alerta,
            tipo_alerta,
            descripcion,
            recomendacion_accion,
            metadata,
            creado_por
        )
        VALUES (
            :id_region,
            :codigo_indice,
            :temporada,
            :fecha_inicio,
            :fecha_fin,
            :valor_min,
            :valor_max,
            :nivel_alerta,
            :tipo_alerta,
            :descripcion,
            :recomendacion,
            :metadata,
            :creado_por
        )
        RETURNING
            id_umbral,
            id_region,
            codigo_indice,
            temporada,
            fecha_inicio,
            fecha_fin,
            valor_min,
            valor_max,
            nivel_alerta,
            tipo_alerta,
            descripcion,
            recomendacion_accion,
            metadata,
            creado_por,
            fecha_creacion
    ";

    $created = Database::fetch($insertSql, [
        'id_region'      => $idRegion,
        'codigo_indice'  => $codigoIndice,
        'temporada'      => $temporada,
        'fecha_inicio'   => $fechaInicio,
        'fecha_fin'      => $fechaFin,
        'valor_min'      => $valorMin,
        'valor_max'      => $valorMax,
        'nivel_alerta'   => $nivelAlerta,
        'tipo_alerta'    => $tipoAlerta,
        'descripcion'    => $descripcion,
        'recomendacion'  => $recomendacion,
        'metadata'       => $metadata !== null ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
        'creado_por'     => $creadoPor
    ]);

    $pdo->commit();

    respond([
        'success' => true,
        'message' => 'Umbral creado correctamente',
        'data'    => [
            'id_umbral'           => $created['id_umbral'],
            'id_region'           => $created['id_region'],
            'codigo_indice'       => $created['codigo_indice'],
            'temporada'           => $created['temporada'],
            'fecha_inicio'        => $created['fecha_inicio'],
            'fecha_fin'           => $created['fecha_fin'],
            'valor_min'           => $created['valor_min'] !== null ? (float)$created['valor_min'] : null,
            'valor_max'           => $created['valor_max'] !== null ? (float)$created['valor_max'] : null,
            'nivel_alerta'        => $created['nivel_alerta'],
            'tipo_alerta'         => $created['tipo_alerta'],
            'descripcion'         => $created['descripcion'],
            'recomendacion_accion'=> $created['recomendacion_accion'],
            'metadata'            => $created['metadata'] ? json_decode($created['metadata'], true) : null,
            'creado_por'          => $created['creado_por'],
            'fecha_creacion'      => $created['fecha_creacion']
        ]
    ], 201);
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error PDO en thresholds-create.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error de base de datos: ' . $e->getMessage()
    ], 500);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error en thresholds-create.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error inesperado: ' . $e->getMessage()
    ], 500);
}
