<?php
/**
 * API: Crear índice satelital (indice_satelital)
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
    if ($codigo === '' || !preg_match('/^[A-Z0-9_\-]{2,30}$/', $codigo)) {
        respond(['success' => false, 'error' => 'Código inválido. Usa letras mayúsculas y guiones, ej. NDVI'], 422);
    }

    $nombre = isset($payload['nombre']) ? sanitizeText($payload['nombre'], 200) : null;
    if ($nombre === null) {
        respond(['success' => false, 'error' => 'El nombre es requerido'], 422);
    }

    $categoria   = sanitizeText($payload['categoria'] ?? null, 100);
    $descripcion = sanitizeText($payload['descripcion'] ?? null, 500);
    $formula     = sanitizeText($payload['formula'] ?? null, 500);
    $unidad      = sanitizeText($payload['unidad'] ?? null, 50);

    $valorMin = null;
    if (isset($payload['valor_min']) && $payload['valor_min'] !== '') {
        $valorMin = (float)$payload['valor_min'];
    }
    $valorMax = null;
    if (isset($payload['valor_max']) && $payload['valor_max'] !== '') {
        $valorMax = (float)$payload['valor_max'];
    }

    $interpBueno = sanitizeText($payload['interpretacion_bueno'] ?? null, 500);
    $interpMalo  = sanitizeText($payload['interpretacion_malo'] ?? null, 500);

    $colorEscala = null;
    if (isset($payload['color_escala']) && $payload['color_escala'] !== null) {
        $colorEscala = is_array($payload['color_escala']) ? $payload['color_escala'] : json_decode((string)$payload['color_escala'], true);
        if (!is_array($colorEscala)) {
            respond(['success' => false, 'error' => 'color_escala debe ser un arreglo JSON válido'], 422);
        }
    }

    $activoRaw = $payload['activo'] ?? true;
    $activo = filter_var($activoRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if ($activo === null) {
        $activo = true;
    }

    $pdo = Database::getInstance();
    $pdo->beginTransaction();

    $exists = Database::fetch('SELECT codigo FROM indice_satelital WHERE codigo = :codigo', ['codigo' => $codigo]);
    if ($exists) {
        $pdo->rollBack();
        respond(['success' => false, 'error' => 'Ya existe un índice con ese código'], 409);
    }

    $insertSql = "
        INSERT INTO indice_satelital (
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
            activo
        )
        VALUES (
            :codigo,
            :nombre,
            :categoria,
            :descripcion,
            :formula,
            :unidad,
            :valor_min,
            :valor_max,
            :interpretacion_bueno,
            :interpretacion_malo,
            :color_escala,
            :activo
        )
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

    $created = Database::fetch($insertSql, [
        'codigo'                => $codigo,
        'nombre'                => $nombre,
        'categoria'             => $categoria,
        'descripcion'           => $descripcion,
        'formula'               => $formula,
        'unidad'                => $unidad,
        'valor_min'             => $valorMin,
        'valor_max'             => $valorMax,
        'interpretacion_bueno'  => $interpBueno,
        'interpretacion_malo'   => $interpMalo,
        'color_escala'          => $colorEscala !== null ? json_encode($colorEscala, JSON_UNESCAPED_UNICODE) : null,
        'activo'                => $activo
    ]);

    $pdo->commit();

    respond([
        'success' => true,
        'message' => 'Índice creado correctamente',
        'data'    => [
            'codigo'                => $created['codigo'],
            'nombre'                => $created['nombre'],
            'categoria'             => $created['categoria'],
            'descripcion'           => $created['descripcion'],
            'formula'               => $created['formula'],
            'unidad'                => $created['unidad'],
            'valor_min'             => $created['valor_min'] !== null ? (float)$created['valor_min'] : null,
            'valor_max'             => $created['valor_max'] !== null ? (float)$created['valor_max'] : null,
            'interpretacion_bueno'  => $created['interpretacion_bueno'],
            'interpretacion_malo'   => $created['interpretacion_malo'],
            'color_escala'          => $created['color_escala'] ? json_decode($created['color_escala'], true) : null,
            'activo'                => (bool)$created['activo'],
            'fecha_creacion'        => $created['fecha_creacion'],
        ]
    ], 201);
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error PDO en indices-create.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error de base de datos: ' . $e->getMessage()
    ], 500);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error en indices-create.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error inesperado: ' . $e->getMessage()
    ], 500);
}
