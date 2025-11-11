<?php
/**
 * API: Crear región umbral (region_umbral)
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

    $codigo = isset($payload['codigo']) ? strtoupper(trim((string)$payload['codigo'])) : '';
    if ($codigo === '') {
        respond(['success' => false, 'error' => 'El código es requerido'], 422);
    }

    $nombre = isset($payload['nombre']) ? sanitizeText($payload['nombre'], 200) : null;
    if ($nombre === null) {
        respond(['success' => false, 'error' => 'El nombre es requerido'], 422);
    }

    $pais = sanitizeText($payload['pais_codigo_iso'] ?? null, 2);
    $tipo = sanitizeText($payload['tipo'] ?? null, 100);
    $metadata = null;
    if (isset($payload['metadata']) && $payload['metadata'] !== null) {
        $metadata = is_array($payload['metadata']) ? $payload['metadata'] : json_decode((string)$payload['metadata'], true);
        if (!is_array($metadata)) {
            respond(['success' => false, 'error' => 'metadata debe ser un objeto JSON válido'], 422);
        }
    }

    $activoRaw = $payload['activo'] ?? true;
    $activo = filter_var($activoRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if ($activo === null) {
        $activo = true;
    }

    // Geometría opcional (geojson WKT string)
    $geomWkt = null;
    $geomArea = null;
    if (!empty($payload['geom_wkt'])) {
        $geomWkt = trim((string)$payload['geom_wkt']);
    }

    $pdo = Database::getInstance();
    $pdo->beginTransaction();

    $exists = Database::fetch('SELECT id_region FROM region_umbral WHERE codigo = :codigo', ['codigo' => $codigo]);
    if ($exists) {
        $pdo->rollBack();
        respond(['success' => false, 'error' => 'Ya existe una región con ese código'], 409);
    }

    if ($geomWkt !== null) {
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
        $geomArea = (float)$validCheck['area'];
    }

    $insertSql = "
        INSERT INTO region_umbral (
            codigo,
            nombre,
            pais_codigo_iso,
            tipo,
            geom,
            metadata,
            activo
        )
        VALUES (
            :codigo,
            :nombre,
            :pais_codigo_iso,
            :tipo,
            CASE WHEN :geom_wkt::text IS NOT NULL THEN ST_GeomFromText(:geom_wkt, 4326) ELSE NULL END,
            :metadata,
            :activo
        )
        RETURNING
            id_region,
            codigo,
            nombre,
            pais_codigo_iso,
            tipo,
            ST_AsText(geom) AS geom_wkt,
            metadata,
            activo,
            fecha_creacion,
            CASE WHEN geom IS NOT NULL THEN ST_Area(geom::geography) ELSE NULL END AS area_m2
    ";

    $created = Database::fetch($insertSql, [
        'codigo'         => $codigo,
        'nombre'         => $nombre,
        'pais_codigo_iso'=> $pais,
        'tipo'           => $tipo,
        'geom_wkt'       => $geomWkt,
        'metadata'       => $metadata !== null ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
        'activo'         => $activo
    ]);

    $pdo->commit();

    respond([
        'success' => true,
        'message' => 'Región creada correctamente',
        'data'    => [
            'id_region'       => $created['id_region'],
            'codigo'          => $created['codigo'],
            'nombre'          => $created['nombre'],
            'pais_codigo_iso' => $created['pais_codigo_iso'],
            'tipo'            => $created['tipo'],
            'geom_wkt'        => $created['geom_wkt'],
            'metadata'        => $created['metadata'] ? json_decode($created['metadata'], true) : null,
            'activo'          => (bool)$created['activo'],
            'fecha_creacion'  => $created['fecha_creacion'],
            'area_m2'         => $created['area_m2'] !== null ? (float)$created['area_m2'] : null
        ]
    ], 201);
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error PDO en regions-create.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error de base de datos: ' . $e->getMessage()
    ], 500);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error en regions-create.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error inesperado: ' . $e->getMessage()
    ], 500);
}
