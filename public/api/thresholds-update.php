<?php
/**
 * API: Actualizar umbral de índice
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
    if ($value === null) return null;
    $trimmed = trim($value);
    if ($trimmed === '') return null;
    return mb_substr($trimmed, 0, $maxLength);
}

function validateDateOrNull($value): ?string {
    if ($value === null) return null;
    $trimmed = trim((string)$value);
    if ($trimmed === '') return null;
    $dt = date_create($trimmed);
    return $dt ? $dt->format('Y-m-d') : null;
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
    if (!is_array($payload) || empty($payload['id_umbral'])) {
        respond(['success' => false, 'error' => 'ID requerido'], 422);
    }

    $idUmbral = trim((string)$payload['id_umbral']);
    if (!preg_match('/^[0-9a-fA-F-]{32,36}$/', $idUmbral)) {
        respond(['success' => false, 'error' => 'ID inválido'], 422);
    }

    $pdo = Database::getInstance();
    $pdo->beginTransaction();

    $current = Database::fetch('SELECT * FROM umbral_indice WHERE id_umbral = :id', ['id' => $idUmbral]);
    if (!$current) {
        $pdo->rollBack();
        respond(['success' => false, 'error' => 'Umbral no encontrado'], 404);
    }

    $codigoIndice = array_key_exists('codigo_indice', $payload) ? trim((string)$payload['codigo_indice']) : $current['codigo_indice'];
    if ($codigoIndice === '') {
        $pdo->rollBack();
        respond(['success' => false, 'error' => 'El código de índice es requerido'], 422);
    }

    $idRegion = array_key_exists('id_region', $payload) ? trim((string)$payload['id_region']) : $current['id_region'];
    if ($idRegion !== null && $idRegion !== '' && !preg_match('/^[0-9a-fA-F-]{32,36}$/', $idRegion)) {
        $pdo->rollBack();
        respond(['success' => false, 'error' => 'El id_region debe ser un UUID válido'], 422);
    }
    if ($idRegion === '') {
        $idRegion = null;
    }

    $temporada    = array_key_exists('temporada', $payload) ? sanitizeText($payload['temporada'], 50) : $current['temporada'];
    $fechaInicio  = array_key_exists('fecha_inicio', $payload) ? validateDateOrNull($payload['fecha_inicio']) : $current['fecha_inicio'];
    $fechaFin     = array_key_exists('fecha_fin', $payload) ? validateDateOrNull($payload['fecha_fin']) : $current['fecha_fin'];

    $valorMin = array_key_exists('valor_min', $payload) ? ($payload['valor_min'] === '' ? null : (float)$payload['valor_min']) : $current['valor_min'];
    $valorMax = array_key_exists('valor_max', $payload) ? ($payload['valor_max'] === '' ? null : (float)$payload['valor_max']) : $current['valor_max'];

    $nivelAlerta = array_key_exists('nivel_alerta', $payload) ? strtoupper(trim((string)$payload['nivel_alerta'])) : $current['nivel_alerta'];
    $allowedLevels = ['INFO', 'BAJO', 'MODERADO', 'ALTO', 'CRITICO'];
    if (!in_array($nivelAlerta, $allowedLevels, true)) {
        $pdo->rollBack();
        respond(['success' => false, 'error' => 'Nivel de alerta inválido'], 422);
    }

    $tipoAlerta = array_key_exists('tipo_alerta', $payload) ? sanitizeText($payload['tipo_alerta'], 100) : $current['tipo_alerta'];
    $descripcion = array_key_exists('descripcion', $payload) ? sanitizeText($payload['descripcion'], 500) : $current['descripcion'];
    $recomendacion = array_key_exists('recomendacion_accion', $payload) ? sanitizeText($payload['recomendacion_accion'], 500) : $current['recomendacion_accion'];

    $metadata = $current['metadata'] ? json_decode($current['metadata'], true) : null;
    if (array_key_exists('metadata', $payload)) {
        if ($payload['metadata'] === null || $payload['metadata'] === '') {
            $metadata = null;
        } else {
            $decoded = is_array($payload['metadata']) ? $payload['metadata'] : json_decode((string)$payload['metadata'], true);
            if (!is_array($decoded)) {
                $pdo->rollBack();
                respond(['success' => false, 'error' => 'metadata debe ser un objeto JSON válido'], 422);
            }
            $metadata = $decoded;
        }
    }

    $creadoPor = array_key_exists('creado_por', $payload) ? sanitizeText($payload['creado_por'], 100) : $current['creado_por'];

    // Validaciones de existencia
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

    $updateSql = "
        UPDATE umbral_indice
        SET
            id_region = :id_region,
            codigo_indice = :codigo_indice,
            temporada = :temporada,
            fecha_inicio = :fecha_inicio,
            fecha_fin = :fecha_fin,
            valor_min = :valor_min,
            valor_max = :valor_max,
            nivel_alerta = :nivel_alerta,
            tipo_alerta = :tipo_alerta,
            descripcion = :descripcion,
            recomendacion_accion = :recomendacion,
            metadata = :metadata,
            creado_por = :creado_por
        WHERE id_umbral = :id
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

    $updated = Database::fetch($updateSql, [
        'id_region'     => $idRegion,
        'codigo_indice' => $codigoIndice,
        'temporada'     => $temporada,
        'fecha_inicio'  => $fechaInicio,
        'fecha_fin'     => $fechaFin,
        'valor_min'     => $valorMin,
        'valor_max'     => $valorMax,
        'nivel_alerta'  => $nivelAlerta,
        'tipo_alerta'   => $tipoAlerta,
        'descripcion'   => $descripcion,
        'recomendacion' => $recomendacion,
        'metadata'      => $metadata !== null ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
        'creado_por'    => $creadoPor,
        'id'            => $idUmbral
    ]);

    $pdo->commit();

    respond([
        'success' => true,
        'message' => 'Umbral actualizado correctamente',
        'data'    => [
            'id_umbral'           => $updated['id_umbral'],
            'id_region'           => $updated['id_region'],
            'codigo_indice'       => $updated['codigo_indice'],
            'temporada'           => $updated['temporada'],
            'fecha_inicio'        => $updated['fecha_inicio'],
            'fecha_fin'           => $updated['fecha_fin'],
            'valor_min'           => $updated['valor_min'] !== null ? (float)$updated['valor_min'] : null,
            'valor_max'           => $updated['valor_max'] !== null ? (float)$updated['valor_max'] : null,
            'nivel_alerta'        => $updated['nivel_alerta'],
            'tipo_alerta'         => $updated['tipo_alerta'],
            'descripcion'         => $updated['descripcion'],
            'recomendacion_accion'=> $updated['recomendacion_accion'],
            'metadata'            => $updated['metadata'] ? json_decode($updated['metadata'], true) : null,
            'creado_por'          => $updated['creado_por'],
            'fecha_creacion'      => $updated['fecha_creacion']
        ]
    ]);
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error PDO en thresholds-update.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error de base de datos: ' . $e->getMessage()
    ], 500);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error en thresholds-update.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error inesperado: ' . $e->getMessage()
    ], 500);
}
