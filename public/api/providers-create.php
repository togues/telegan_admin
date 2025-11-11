<?php
/**
 * API: Crear proveedor satelital/climático (tabla proveedor)
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

function validateCodigo(string $codigo): bool {
    return (bool)preg_match('/^[A-Z0-9\-\_]{2,30}$/', $codigo);
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
    $nombre = isset($payload['nombre']) ? sanitizeText((string)$payload['nombre'], 200) : null;

    if ($codigo === '' || !validateCodigo($codigo)) {
        respond(['success' => false, 'error' => 'Código inválido. Usa letras/números sin espacios (ej. SENTINEL2)'], 422);
    }
    if ($nombre === null) {
        respond(['success' => false, 'error' => 'El nombre es requerido'], 422);
    }

    $descripcion  = sanitizeText($payload['descripcion'] ?? null, 500);
    $urlApiRaw    = sanitizeText($payload['url_api'] ?? null, 500);
    $requiereAuth = isset($payload['requiere_autenticacion']) ? filter_var($payload['requiere_autenticacion'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : true;
    $apiKey       = sanitizeText($payload['api_key_encriptada'] ?? null, 500);

    $frecuenciaHoras = null;
    if (isset($payload['frecuencia_horas']) && $payload['frecuencia_horas'] !== '') {
        $frecuenciaHoras = (int)$payload['frecuencia_horas'];
        if ($frecuenciaHoras < 1 || $frecuenciaHoras > 720) {
            respond(['success' => false, 'error' => 'frecuencia_horas debe estar entre 1 y 720 horas'], 422);
        }
    }

    $ventanaTemporal = null;
    if (isset($payload['ventana_temporal_dias']) && $payload['ventana_temporal_dias'] !== '') {
        $ventanaTemporal = (int)$payload['ventana_temporal_dias'];
        if ($ventanaTemporal < 1 || $ventanaTemporal > 365) {
            respond(['success' => false, 'error' => 'ventana_temporal_dias debe estar entre 1 y 365 días'], 422);
        }
    }

    $maxNubosidad = null;
    if (isset($payload['max_nubosidad_pct']) && $payload['max_nubosidad_pct'] !== '') {
        $maxNubosidad = (float)$payload['max_nubosidad_pct'];
        if ($maxNubosidad < 0 || $maxNubosidad > 100) {
            respond(['success' => false, 'error' => 'max_nubosidad_pct debe estar entre 0 y 100'], 422);
        }
    }

    $contacto = null;
    if (isset($payload['contacto']) && $payload['contacto'] !== null) {
        $contacto = is_array($payload['contacto']) ? $payload['contacto'] : json_decode((string)$payload['contacto'], true);
        if (!is_array($contacto)) {
            respond(['success' => false, 'error' => 'contacto debe ser un objeto JSON válido'], 422);
        }
    }

    $metadata = null;
    if (isset($payload['metadata']) && $payload['metadata'] !== null) {
        $metadata = is_array($payload['metadata']) ? $payload['metadata'] : json_decode((string)$payload['metadata'], true);
        if (!is_array($metadata)) {
            respond(['success' => false, 'error' => 'metadata debe ser un objeto JSON válido'], 422);
        }
    }

    if ($urlApiRaw !== null && !filter_var($urlApiRaw, FILTER_VALIDATE_URL)) {
        respond(['success' => false, 'error' => 'La URL de la API no es válida'], 422);
    }

    $pdo = Database::getInstance();
    $pdo->beginTransaction();

    $existing = Database::fetch(
        'SELECT id_proveedor FROM proveedor WHERE codigo = :codigo',
        ['codigo' => $codigo]
    );
    if ($existing) {
        $pdo->rollBack();
        respond(['success' => false, 'error' => 'Ya existe un proveedor con ese código'], 409);
    }

    $insertSql = "
        INSERT INTO proveedor (
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
            activo
        )
        VALUES (
            :codigo,
            :nombre,
            :descripcion,
            :url_api,
            :requiere_autenticacion,
            :api_key_encriptada,
            :frecuencia_horas,
            :ventana_temporal_dias,
            :max_nubosidad_pct,
            :contacto,
            :metadata,
            :activo
        )
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

    $params = [
        'codigo'                 => $codigo,
        'nombre'                 => $nombre,
        'descripcion'            => $descripcion,
        'url_api'                => $urlApiRaw,
        'requiere_autenticacion' => $requiereAuth !== null ? (bool)$requiereAuth : true,
        'api_key_encriptada'     => $apiKey,
        'frecuencia_horas'       => $frecuenciaHoras,
        'ventana_temporal_dias'  => $ventanaTemporal,
        'max_nubosidad_pct'      => $maxNubosidad,
        'contacto'               => $contacto !== null ? json_encode($contacto, JSON_UNESCAPED_UNICODE) : null,
        'metadata'               => $metadata !== null ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
        'activo'                 => isset($payload['activo']) ? filter_var($payload['activo'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true : true
    ];

    $created = Database::fetch($insertSql, $params);
    $pdo->commit();

    respond([
        'success' => true,
        'message' => 'Proveedor creado correctamente',
        'data'    => [
            'id_proveedor'           => (int)$created['id_proveedor'],
            'codigo'                 => $created['codigo'],
            'nombre'                 => $created['nombre'],
            'descripcion'            => $created['descripcion'],
            'url_api'                => $created['url_api'],
            'requiere_autenticacion' => (bool)$created['requiere_autenticacion'],
            'api_key_encriptada'     => $created['api_key_encriptada'],
            'frecuencia_horas'       => $created['frecuencia_horas'] !== null ? (int)$created['frecuencia_horas'] : null,
            'ventana_temporal_dias'  => $created['ventana_temporal_dias'] !== null ? (int)$created['ventana_temporal_dias'] : null,
            'max_nubosidad_pct'      => $created['max_nubosidad_pct'] !== null ? (float)$created['max_nubosidad_pct'] : null,
            'contacto'               => $created['contacto'] !== null ? json_decode($created['contacto'], true) : null,
            'metadata'               => $created['metadata'] !== null ? json_decode($created['metadata'], true) : null,
            'activo'                 => (bool)$created['activo'],
            'fecha_ultima_consulta'  => $created['fecha_ultima_consulta'],
            'fecha_creacion'         => $created['fecha_creacion'],
        ]
    ], 201);
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error PDO en providers-create.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error de base de datos: ' . $e->getMessage()
    ], 500);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error en providers-create.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error inesperado: ' . $e->getMessage()
    ], 500);
}


