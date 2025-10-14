<?php
/**
 * Debug endpoint para inspeccionar geometría de fincas
 * SOLO PARA DESARROLLO - NO USAR EN PRODUCCIÓN
 */

// Inicializar configuración de seguridad
require_once 'security-config.php';
initSecurity();

header('Content-Type: application/json');

// Incluir dependencias
require_once '../../src/Config/Database.php';

try {
    // Verificar método HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido']);
        exit;
    }

    // Obtener ID de la finca
    $farmId = $_GET['farm_id'] ?? null;
    
    if (!validateId($farmId)) {
        http_response_code(400);
        echo json_encode(['error' => 'ID de finca inválido']);
        exit;
    }

    // Consulta para obtener geometría raw
    $sql = "
        SELECT 
            f.id_finca,
            f.nombre_finca,
            f.geometria_wkt,
            ST_AsText(f.geometria_postgis) as geometria_postgis,
            ST_GeometryType(f.geometria_postgis) as geometry_type,
            ST_IsValid(f.geometria_postgis) as is_valid,
            ST_Area(f.geometria_postgis) as area_m2,
            ST_AsGeoJSON(ST_Transform(f.geometria_postgis, 4326)) as geojson,
            ST_SRID(f.geometria_postgis) as srid
        FROM finca f
        WHERE f.id_finca = :farm_id
    ";

    $result = Database::fetch($sql, ['farm_id' => $farmId]);

    if (!$result) {
        http_response_code(404);
        echo json_encode(['error' => 'Finca no encontrada']);
        exit;
    }

    // Preparar respuesta de debug
    $debug = [
        'farm_id' => $result['id_finca'],
        'farm_name' => $result['nombre_finca'],
        'geometry_wkt' => $result['geometria_wkt'],
        'geometry_postgis' => $result['geometria_postgis'],
        'geometry_type' => $result['geometry_type'],
        'is_valid' => $result['is_valid'],
        'area_m2' => $result['area_m2'],
        'srid' => $result['srid'],
        'geojson' => $result['geojson'],
        'geojson_parsed' => json_decode($result['geojson'], true),
        'analysis' => [
            'has_wkt' => !empty($result['geometria_wkt']),
            'has_postgis' => !empty($result['geometria_postgis']),
            'has_geojson' => !empty($result['geojson']),
            'wkt_length' => strlen($result['geometria_wkt'] ?? ''),
            'postgis_length' => strlen($result['geometria_postgis'] ?? ''),
            'geojson_length' => strlen($result['geojson'] ?? ''),
            'wkt_starts_with_polygon' => strpos(strtoupper($result['geometria_wkt'] ?? ''), 'POLYGON') === 0,
            'postgis_starts_with_polygon' => strpos(strtoupper($result['geometria_postgis'] ?? ''), 'POLYGON') === 0,
            'geojson_valid' => json_decode($result['geojson'], true) !== null
        ]
    ];

    echo json_encode($debug, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    error_log('Debug geometry error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
}
?>
