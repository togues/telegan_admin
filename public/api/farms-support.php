<?php
/**
 * API: Datos de soporte para módulo de fincas
 */

declare(strict_types=1);

require_once '../../src/Config/Database.php';
require_once '../../src/Config/ApiAuth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        respond(['success' => false, 'error' => 'Método no permitido'], 405);
    }

    $validation = ApiAuth::validateRequest();
    if (!$validation['valid']) {
        respond([
            'success' => false,
            'error'   => 'Acceso no autorizado: ' . ($validation['error'] ?? 'Token inválido')
        ], 401);
    }

    $countries = Database::fetchAll(
        'SELECT id_pais, codigo_iso2, nombre_pais
         FROM pais
         WHERE activo IS NULL OR activo = TRUE
         ORDER BY nombre_pais ASC'
    );

    $creators = Database::fetchAll(
        'SELECT DISTINCT u.id_usuario, u.nombre_completo
         FROM usuario u
         INNER JOIN finca f ON f.id_usuario_creador = u.id_usuario
         ORDER BY u.nombre_completo ASC'
    );

    respond([
        'success' => true,
        'data'    => [
            'countries' => array_map(static function (array $row): array {
                return [
                    'id_pais'     => (int)$row['id_pais'],
                    'codigo_iso2' => $row['codigo_iso2'],
                    'nombre_pais' => $row['nombre_pais'],
                ];
            }, $countries),
            'creators'  => array_map(static function (array $row): array {
                return [
                    'id_usuario'      => (int)$row['id_usuario'],
                    'nombre_completo' => $row['nombre_completo'],
                ];
            }, $creators),
            'states'    => [
                ['value' => 'ACTIVA', 'label' => 'Activa'],
                ['value' => 'INACTIVA', 'label' => 'Inactiva'],
            ],
        ],
    ]);
} catch (PDOException $e) {
    error_log('Error PDO en farms-support.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error de base de datos: ' . $e->getMessage()
    ], 500);
} catch (Throwable $e) {
    error_log('Error en farms-support.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error inesperado: ' . $e->getMessage()
    ], 500);
}

