<?php
/**
 * API: Actualizar región umbral
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

function normalizeGeoJson($value): ?string {
    if ($value === null || $value === '') {
        return null;
    }
    if (is_array($value)) {
        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }
    $trimmed = trim((string)$value);
    return $trimmed === '' ? null : $trimmed;
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

    $current = Database::fetch(
        'SELECT *, ST_AsText(geom) AS geom_wkt, CASE WHEN geom IS NOT NULL THEN ST_AsGeoJSON(geom, 6) END AS geom_geojson FROM region_umbral WHERE codigo = :codigo',
        ['codigo' => $codigo]
    );
    if (!$current) {
        $pdo->rollBack();
        respond(['success' => false, 'error' => 'Región no encontrada'], 404);
    }

    $nombre = array_key_exists('nombre', $payload) ? sanitizeText($payload['nombre'], 200) : $current['nombre'];
    if ($nombre === null) {
        $pdo->rollBack();
        respond(['success' => false, 'error' => 'El nombre es requerido'], 422);
    }

    $pais = array_key_exists('pais_codigo_iso', $payload) ? sanitizeText($payload['pais_codigo_iso'], 2) : $current['pais_codigo_iso'];
    $tipo = array_key_exists('tipo', $payload) ? sanitizeText($payload['tipo'], 100) : $current['tipo'];

    $geomGeoJsonProvided = array_key_exists('geom_geojson', $payload);
    $geomGeoJsonValue = $geomGeoJsonProvided ? normalizeGeoJson($payload['geom_geojson']) : null;
    $geomWktProvided = array_key_exists('geom_wkt', $payload);
    $geomWktInput = $geomWktProvided ? trim((string)($payload['geom_wkt'] ?? '')) : $current['geom_wkt'];

    $geomProvided = $geomGeoJsonProvided || $geomWktProvided;
    $geomWkt = $geomWktInput;
    if ($geomGeoJsonProvided) {
        if ($geomGeoJsonValue === null) {
            $geomWkt = null;
        } else {
            $conversion = Database::fetch("
                WITH geom AS (
                    SELECT ST_SetSRID(ST_GeomFromGeoJSON(:geojson::json), 4326) AS g
                )
                SELECT
                    ST_AsText(g) AS geom_wkt,
                    ST_IsValid(g) AS valid,
                    ST_Area(g::geography) AS area_m2
                FROM geom
            ", ['geojson' => $geomGeoJsonValue]);

            if (!$conversion || !$conversion['valid']) {
                $pdo->rollBack();
                respond(['success' => false, 'error' => 'La geometría GeoJSON no es válida'], 422);
            }
            if (($conversion['area_m2'] ?? 0) <= 0) {
                $pdo->rollBack();
                respond(['success' => false, 'error' => 'La geometría debe tener un área mayor a cero'], 422);
            }
            $geomWkt = $conversion['geom_wkt'];
        }
    } elseif ($geomWktProvided && $geomWkt === '') {
        $geomWkt = null;
    }

    if ($geomProvided && $geomWkt !== null) {
        $validCheck = Database::fetch(
            'SELECT ST_IsValid(ST_GeomFromText(:wkt, 4326)) AS valid, ST_Area(ST_GeomFromText(:wkt, 4326)::geography) AS area',
            ['wkt' => $geomWkt]
        );
        if (!$validCheck || !$validCheck['valid']) {
            $pdo->rollBack();
            respond(['success' => false, 'error' => 'La geometría proporcionada no es válida'], 422);
        }
        if (($validCheck['area'] ?? 0) <= 0) {
            $pdo->rollBack();
            respond(['success' => false, 'error' => 'La geometría debe tener un área mayor a cero'], 422);
        }
    }

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

    $activo = array_key_exists('activo', $payload)
        ? filter_var($payload['activo'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
        : (bool)$current['activo'];
    if ($activo === null) {
        $activo = (bool)$current['activo'];
    }

    $updateSql = "
        UPDATE region_umbral
        SET
            nombre = :nombre,
            pais_codigo_iso = :pais_codigo_iso,
            tipo = :tipo,
            geom = CASE
                WHEN :geom_provided = true THEN CASE WHEN :geom_wkt::text IS NOT NULL THEN ST_GeomFromText(:geom_wkt, 4326) ELSE NULL END
                ELSE geom
            END,
            metadata = :metadata,
            activo = :activo
        WHERE codigo = :codigo
        RETURNING
            id_region,
            codigo,
            nombre,
            pais_codigo_iso,
            tipo,
            ST_AsText(geom) AS geom_wkt,
            CASE WHEN geom IS NOT NULL THEN ST_AsGeoJSON(geom, 6) END AS geom_geojson,
            metadata,
            activo,
            fecha_creacion,
            CASE WHEN geom IS NOT NULL THEN ST_Area(geom::geography) ELSE NULL END AS area_m2
    ";

    $updated = Database::fetch($updateSql, [
        'nombre'          => $nombre,
        'pais_codigo_iso' => $pais,
        'tipo'            => $tipo,
        'geom_provided'   => $geomProvided,
        'geom_wkt'        => $geomWkt,
        'metadata'        => $metadata !== null ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
        'activo'          => $activo,
        'codigo'          => $codigo
    ]);

    $pdo->commit();

    respond([
        'success' => true,
        'message' => 'Región actualizada correctamente',
        'data'    => [
            'id_region'       => $updated['id_region'],
            'codigo'          => $updated['codigo'],
            'nombre'          => $updated['nombre'],
            'pais_codigo_iso' => $updated['pais_codigo_iso'],
            'tipo'            => $updated['tipo'],
            'geom_wkt'        => $updated['geom_wkt'],
            'geom_geojson'    => $updated['geom_geojson'] ? json_decode($updated['geom_geojson'], true) : null,
            'metadata'        => $updated['metadata'] ? json_decode($updated['metadata'], true) : null,
            'activo'          => (bool)$updated['activo'],
            'fecha_creacion'  => $updated['fecha_creacion'],
            'area_m2'         => $updated['area_m2'] !== null ? (float)$updated['area_m2'] : null
        ]
    ]);
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error PDO en regions-update.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error de base de datos: ' . $e->getMessage()
    ], 500);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error en regions-update.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error inesperado: ' . $e->getMessage()
    ], 500);
}
