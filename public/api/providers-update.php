<?php
/**
 * API: Actualizar proveedor satelital/climático (tabla proveedor)
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

function validateCodigo(string $codigo): bool {
    return (bool)preg_match('/^[A-Z0-9\-\_]{2,30}$/', $codigo);
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
    if (!is_array($payload) || empty($payload['id_proveedor'])) {
        respond(['success' => false, 'error' => 'ID de proveedor requerido'], 422);
    }

    $id = (int)$payload['id_proveedor'];
    if ($id <= 0) {
        respond(['success' => false, 'error' => 'ID de proveedor inválido'], 422);
    }

    $pdo = Database::getInstance();
    $pdo->beginTransaction();

    $current = Database::fetch(
        'SELECT * FROM proveedor WHERE id_proveedor = :id',
        ['id' => $id]
    );

    if (!$current) {
        $pdo->rollBack();
        respond(['success' => false, 'error' => 'Proveedor no encontrado'], 404);
    }

    $codigo = isset($payload['codigo']) ? strtoupper(trim((string)$payload['codigo'])) : $current['codigo'];
    if (!validateCodigo($codigo)) {
        $pdo->rollBack();
        respond(['success' => false, 'error' => 'Código inválido. Usa letras/números sin espacios (ej. SENTINEL2)'], 422);
    }

    $nombre = isset($payload['nombre']) ? sanitizeText((string)$payload['nombre'], 200) : $current['nombre'];
    if ($nombre === null) {
        $pdo->rollBack();
        respond(['success' => false, 'error' => 'El nombre es requerido'], 422);
    }

    if ($codigo !== $current['codigo']) {
        $exists = Database::fetch(
            'SELECT id_proveedor FROM proveedor WHERE codigo = :codigo AND id_proveedor <> :id',
            ['codigo' => $codigo, 'id' => $id]
        );
        if ($exists) {
            $pdo->rollBack();
            respond(['success' => false, 'error' => 'Ya existe otro proveedor con ese código'], 409);
        }
    }

    $descripcion  = array_key_exists('descripcion', $payload) ? sanitizeText($payload['descripcion'], 500) : $current['descripcion'];
    $urlApiRaw    = array_key_exists('url_api', $payload) ? sanitizeText($payload['url_api'], 500) : $current['url_api'];
    $requiereAuth = array_key_exists('requiere_autenticacion', $payload)
        ? filter_var($payload['requiere_autenticacion'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
        : (bool)$current['requiere_autenticacion'];
    $apiKey       = array_key_exists('api_key_encriptada', $payload) ? sanitizeText($payload['api_key_encriptada'], 500) : $current['api_key_encriptada'];

    $frecuenciaHoras = array_key_exists('frecuencia_horas', $payload) ? (int)$payload['frecuencia_horas'] : $current['frecuencia_horas'];
    if ($frecuenciaHoras !== null && ($frecuenciaHoras < 1 || $frecuenciaHoras > 720)) {
        $pdo->rollBack();
        respond(['success' => false, 'error' => 'frecuencia_horas debe estar entre 1 y 720 horas'], 422);
    }

    $ventanaTemporal = array_key_exists('ventana_temporal_dias', $payload) ? (int)$payload['ventana_temporal_dias'] : $current['ventana_temporal_dias'];
    if ($ventanaTemporal !== null && ($ventanaTemporal < 1 || $ventanaTemporal > 365)) {
        $pdo->rollBack();
        respond(['success' => false, 'error' => 'ventana_temporal_dias debe estar entre 1 y 365 días'], 422);
    }

    $maxNubosidad = array_key_exists('max_nubosidad_pct', $payload) ? (float)$payload['max_nubosidad_pct'] : $current['max_nubosidad_pct'];
    if ($maxNubosidad !== null && ($maxNubosidad < 0 || $maxNubosidad > 100)) {
        $pdo->rollBack();
        respond(['success' => false, 'error' => 'max_nubosidad_pct debe estar entre 0 y 100'], 422);
    }

    if ($urlApiRaw !== null && !filter_var($urlApiRaw, FILTER_VALIDATE_URL)) {
        $pdo->rollBack();
        respond(['success' => false, 'error' => 'La URL de la API no es válida'], 422);
    }

    $contacto = array_key_exists('contacto', $payload) ? $payload['contacto'] : $current['contacto'];
    if (is_string($contacto)) {
        $contactoDecoded = json_decode($contacto, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($contactoDecoded)) {
            $pdo->rollBack();
            respond(['success' => false, 'error' => 'contacto debe ser un objeto JSON válido'], 422);
        }
        $contacto = $contactoDecoded;
    }
    if ($contacto !== null && !is_array($contacto)) {
        $pdo->rollBack();
        respond(['success' => false, 'error' => 'contacto debe ser un objeto JSON válido'], 422);
    }

    $metadata = array_key_exists('metadata', $payload) ? $payload['metadata'] : $current['metadata'];
    if (is_string($metadata)) {
        $metadataDecoded = json_decode($metadata, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($metadataDecoded)) {
            $pdo->rollBack();
            respond(['success' => false, 'error' => 'metadata debe ser un objeto JSON válido'], 422);
        }
        $metadata = $metadataDecoded;
    }
    if ($metadata !== null && !is_array($metadata)) {
        $pdo->rollBack();
        respond(['success' => false, 'error' => 'metadata debe ser un objeto JSON válido'], 422);
    }

    $activo = array_key_exists('activo', $payload)
        ? filter_var($payload['activo'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
        : (bool)$current['activo'];

    $updateSql = "
        UPDATE proveedor
        SET
            codigo = :codigo,
            nombre = :nombre,
            descripcion = :descripcion,
            url_api = :url_api,
            requiere_autenticacion = :requiere_autenticacion,
            api_key_encriptada = :api_key_encriptada,
            frecuencia_horas = :frecuencia_horas,
            ventana_temporal_dias = :ventana_temporal_dias,
            max_nubosidad_pct = :max_nubosidad_pct,
            contacto = :contacto,
            metadata = :metadata,
            activo = :activo
        WHERE id_proveedor = :id
        RETURNING
            id_proveedor,
            codigo,
            nombre,
            descripcion,
            url_api,
            requiere_autenticacion,
            api_key_encriptada,
            frecuencia_horas,
            ventana_temporal_dias,
            max_nubosidad_pct,
            contacto,
            metadata,
            activo,
            fecha_ultima_consulta,
            fecha_creacion
    ";

    $updated = Database::fetch($updateSql, [
        'codigo'                 => $codigo,
        'nombre'                 => $nombre,
        'descripcion'            => $descripcion,
        'url_api'                => $urlApiRaw,
        'requiere_autenticacion' => $requiereAuth ?? true,
        'api_key_encriptada'     => $apiKey,
        'frecuencia_horas'       => $frecuenciaHoras !== null ? $frecuenciaHoras : null,
        'ventana_temporal_dias'  => $ventanaTemporal !== null ? $ventanaTemporal : null,
        'max_nubosidad_pct'      => $maxNubosidad !== null ? $maxNubosidad : null,
        'contacto'               => $contacto !== null ? json_encode($contacto, JSON_UNESCAPED_UNICODE) : null,
        'metadata'               => $metadata !== null ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
        'activo'                 => $activo ?? true,
        'id'                     => $id
    ]);

    $pdo->commit();

    respond([
        'success' => true,
        'message' => 'Proveedor actualizado correctamente',
        'data'    => [
            'id_proveedor'           => (int)$updated['id_proveedor'],
            'codigo'                 => $updated['codigo'],
            'nombre'                 => $updated['nombre'],
            'descripcion'            => $updated['descripcion'],
            'url_api'                => $updated['url_api'],
            'requiere_autenticacion' => (bool)$updated['requiere_autenticacion'],
            'api_key_encriptada'     => $updated['api_key_encriptada'],
            'frecuencia_horas'       => $updated['frecuencia_horas'] !== null ? (int)$updated['frecuencia_horas'] : null,
            'ventana_temporal_dias'  => $updated['ventana_temporal_dias'] !== null ? (int)$updated['ventana_temporal_dias'] : null,
            'max_nubosidad_pct'      => $updated['max_nubosidad_pct'] !== null ? (float)$updated['max_nubosidad_pct'] : null,
            'contacto'               => $updated['contacto'] !== null ? json_decode($updated['contacto'], true) : null,
            'metadata'               => $updated['metadata'] !== null ? json_decode($updated['metadata'], true) : null,
            'activo'                 => (bool)$updated['activo'],
            'fecha_ultima_consulta'  => $updated['fecha_ultima_consulta'],
            'fecha_creacion'         => $updated['fecha_creacion'],
        ]
    ]);
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error PDO en providers-update.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error de base de datos: ' . $e->getMessage()
    ], 500);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error en providers-update.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error inesperado: ' . $e->getMessage()
    ], 500);
}


