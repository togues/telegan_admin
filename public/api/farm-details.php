<?php
/**
 * API Endpoint para obtener detalles completos de una finca
 * Incluye: datos básicos, administradores, colaboradores, potreros, geometría
 */

// Inicializar configuración de seguridad
require_once 'security-config.php';
initSecurity();

// Incluir sistema de seguridad mejorado
require_once '../../src/Config/Security.php';

// Inicializar seguridad
Security::init();

header('Content-Type: application/json');

// Incluir dependencias
require_once '../../src/Config/Database.php';
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Función para enviar respuesta JSON
function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

// Función para manejar errores
function handleError($message, $status = 500) {
    sendResponse([
        'success' => false,
        'error' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ], $status);
}

try {
    // Verificar método HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        handleError('Método no permitido', 405);
    }

    // Obtener ID de la finca
    $farmId = $_GET['farm_id'] ?? null;
    
    if (!validateId($farmId)) {
        handleError('ID de finca inválido', 400);
    }

    // ===========================================
    // 1. DATOS BÁSICOS DE LA FINCA
    // ===========================================
    $farmSql = "
        SELECT 
            f.id_finca,
            f.nombre_finca,
            f.descripcion,
            f.area_hectareas,
            f.estado,
            f.fecha_creacion,
            f.fecha_actualizacion,
            f.codigo_telegan,
            f.geometria_wkt,
            ST_AsText(f.geometria_postgis) as geometria_postgis,
            ST_AsGeoJSON(ST_Transform(f.geometria_postgis, 4326)) as geojson,
            p.nombre_pais,
            uc.nombre_completo as creador_nombre,
            uc.email as creador_email
        FROM finca f
        LEFT JOIN pais p ON f.id_pais = p.id_pais
        LEFT JOIN usuario uc ON f.id_usuario_creador = uc.id_usuario
        WHERE f.id_finca = :farm_id
    ";

    $farmData = Database::fetch($farmSql, ['farm_id' => $farmId]);

    if (!$farmData) {
        handleError('Finca no encontrada', 404);
    }

    // ===========================================
    // 2. ADMINISTRADORES Y COLABORADORES
    // ===========================================
    $usersSql = "
        SELECT 
            u.id_usuario,
            u.nombre_completo,
            u.email,
            u.telefono,
            u.ubicacion_general,
            u.activo,
            uf.rol,
            uf.fecha_asociacion,
            p.nombre_pais
        FROM usuario_finca uf
        INNER JOIN usuario u ON uf.id_usuario = u.id_usuario
        LEFT JOIN pais p ON u.ubicacion_general = p.nombre_pais
        WHERE uf.id_finca = :farm_id
        ORDER BY uf.rol DESC, uf.fecha_asociacion ASC
    ";

    $usersData = Database::fetchAll($usersSql, ['farm_id' => $farmId]);

    // Separar administradores y colaboradores
    $administrators = [];
    $collaborators = [];

    foreach ($usersData as $user) {
        $userData = [
            'id_usuario' => (int)$user['id_usuario'],
            'nombre_completo' => $user['nombre_completo'],
            'email' => $user['email'],
            'telefono' => $user['telefono'],
            'ubicacion_general' => $user['ubicacion_general'],
            'nombre_pais' => $user['nombre_pais'],
            'activo' => (bool)$user['activo'],
            'rol' => $user['rol'],
            'fecha_asociacion' => $user['fecha_asociacion'] ? date('d/m/Y', strtotime($user['fecha_asociacion'])) : 'No especificada',
            'initials' => generateInitials($user['nombre_completo'])
        ];

        if ($user['rol'] === 'ADMIN') {
            $administrators[] = $userData;
        } else {
            $collaborators[] = $userData;
        }
    }

    // ===========================================
    // 3. POTREROS DE LA FINCA
    // ===========================================
    $paddocksSql = "
        SELECT 
            pt.id_potrero,
            pt.nombre_potrero,
            pt.descripcion,
            pt.area_hectareas,
            pt.estado,
            pt.fecha_creacion,
            pt.codigo_telegan,
            pt.geometria_wkt,
            pt.geometria_postgis,
            COUNT(rg.id_registro) as total_registros,
            MAX(rg.fecha_registro) as ultimo_registro
        FROM potrero pt
        LEFT JOIN registro_ganadero rg ON pt.id_potrero = rg.id_potrero
        WHERE pt.id_finca = :farm_id
        GROUP BY pt.id_potrero, pt.nombre_potrero, pt.descripcion, 
                 pt.area_hectareas, pt.estado, pt.fecha_creacion, 
                 pt.codigo_telegan, pt.geometria_wkt, pt.geometria_postgis
        ORDER BY pt.fecha_creacion DESC
    ";

    $paddocksData = Database::fetchAll($paddocksSql, ['farm_id' => $farmId]);

    $paddocks = [];
    foreach ($paddocksData as $paddock) {
        $paddocks[] = [
            'id_potrero' => (int)$paddock['id_potrero'],
            'nombre_potrero' => $paddock['nombre_potrero'],
            'descripcion' => $paddock['descripcion'],
            'area_hectareas' => $paddock['area_hectareas'] ? (float)$paddock['area_hectareas'] : null,
            'estado' => $paddock['estado'],
            'estado_text' => $paddock['estado'] === 'ACTIVO' ? 'Activo' : 'Inactivo',
            'estado_class' => $paddock['estado'] === 'ACTIVO' ? 'connected' : 'error',
            'fecha_creacion' => $paddock['fecha_creacion'] ? date('d/m/Y', strtotime($paddock['fecha_creacion'])) : 'No especificada',
            'codigo_telegan' => $paddock['codigo_telegan'],
            'total_registros' => (int)$paddock['total_registros'],
            'ultimo_registro' => $paddock['ultimo_registro'] ? date('d/m/Y', strtotime($paddock['ultimo_registro'])) : 'Sin registros',
            'geometria_wkt' => $paddock['geometria_wkt'],
            'geometria_postgis' => $paddock['geometria_postgis'],
            'display_info' => ($paddock['area_hectareas'] ? $paddock['area_hectareas'] . ' ha' : 'Sin área') . ' • ' . $paddock['total_registros'] . ' registros'
        ];
    }

    // ===========================================
    // 4. PROCESAR DATOS DE LA FINCA
    // ===========================================
    $farm = [
        'id_finca' => (int)$farmData['id_finca'],
        'nombre_finca' => $farmData['nombre_finca'],
        'descripcion' => $farmData['descripcion'],
        'area_hectareas' => $farmData['area_hectareas'] ? (float)$farmData['area_hectareas'] : null,
        'estado' => $farmData['estado'],
        'estado_text' => $farmData['estado'] === 'ACTIVA' ? 'Activa' : 'Inactiva',
        'estado_class' => $farmData['estado'] === 'ACTIVA' ? 'connected' : 'error',
        'fecha_creacion' => $farmData['fecha_creacion'] ? date('d/m/Y', strtotime($farmData['fecha_creacion'])) : 'No especificada',
        'fecha_actualizacion' => $farmData['fecha_actualizacion'] ? date('d/m/Y', strtotime($farmData['fecha_actualizacion'])) : 'No especificada',
        'codigo_telegan' => $farmData['codigo_telegan'],
        'nombre_pais' => $farmData['nombre_pais'],
        'creador_nombre' => $farmData['creador_nombre'],
        'creador_email' => $farmData['creador_email'],
        'geometria_wkt' => $farmData['geometria_wkt'],
        'geometria_postgis' => $farmData['geometria_postgis'],
        'geojson' => $farmData['geojson'] ? json_decode($farmData['geojson'], true) : null,
        'display_info' => [
            'pais' => $farmData['nombre_pais'],
            'area' => $farmData['area_hectareas'] ? $farmData['area_hectareas'] . ' hectáreas' : 'Área no calculada',
            'potreros' => count($paddocks) . ' potreros',
            'usuarios' => count($usersData) . ' usuarios asociados'
        ]
    ];

    // ===========================================
    // 5. ESTADÍSTICAS RESUMEN
    // ===========================================
    $stats = [
        'total_administradores' => count($administrators),
        'total_colaboradores' => count($collaborators),
        'total_potreros' => count($paddocks),
        'total_registros' => array_sum(array_column($paddocks, 'total_registros')),
        'potreros_activos' => count(array_filter($paddocks, fn($p) => $p['estado'] === 'ACTIVO'))
    ];

    sendResponse([
        'success' => true,
        'data' => [
            'farm' => $farm,
            'administrators' => $administrators,
            'collaborators' => $collaborators,
            'paddocks' => $paddocks,
            'stats' => $stats
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    error_log("Error en farm-details.php: " . $e->getMessage());
    handleError('Error interno del servidor', 500);
}

// Función para generar iniciales
function generateInitials($fullName) {
    $names = explode(' ', trim($fullName));
    if (count($names) >= 2) {
        return strtoupper(substr($names[0], 0, 1) . substr($names[count($names)-1], 0, 1));
    } else {
        return strtoupper(substr($fullName, 0, 2));
    }
}
?>
