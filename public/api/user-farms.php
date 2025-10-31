<?php
/**
 * API Endpoint para obtener fincas de un usuario específico
 */

// Incluir dependencias
require_once '../../src/Config/Database.php';
require_once '../../src/Config/ApiAuth.php';

// Configurar headers CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Token, X-API-Timestamp, X-Session-Token');

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
    
    // Validar token de autenticación (ApiAuth normaliza el path automáticamente)
    $validation = ApiAuth::validateRequest();
    
    if (!$validation['valid']) {
        handleError('Acceso no autorizado: ' . ($validation['error'] ?? 'Token inválido'), 401);
    }

    // Obtener ID del usuario
    $userId = $_GET['user_id'] ?? null;
    
    if (!$userId || !is_numeric($userId)) {
        handleError('ID de usuario requerido', 400);
    }

    // Consulta SQL para obtener fincas del usuario
    $sql = "
        SELECT 
            f.id_finca,
            f.nombre_finca,
            f.descripcion,
            f.area_hectareas,
            f.estado,
            f.fecha_creacion,
            f.codigo_telegan,
            p.nombre_pais,
            uf.rol,
            uf.fecha_asociacion,
            COUNT(DISTINCT pt.id_potrero) as total_potreros,
            COUNT(DISTINCT rg.id_registro) as total_registros
        FROM finca f
        INNER JOIN usuario_finca uf ON f.id_finca = uf.id_finca
        LEFT JOIN pais p ON f.id_pais = p.id_pais
        LEFT JOIN potrero pt ON f.id_finca = pt.id_finca AND pt.estado = 'ACTIVO'
        LEFT JOIN registro_ganadero rg ON pt.id_potrero = rg.id_potrero
        WHERE uf.id_usuario = :user_id
        GROUP BY f.id_finca, f.nombre_finca, f.descripcion, f.area_hectareas, 
                 f.estado, f.fecha_creacion, f.codigo_telegan, p.nombre_pais,
                 uf.rol, uf.fecha_asociacion
        ORDER BY f.fecha_creacion DESC
    ";

    // Ejecutar consulta
    $results = Database::fetchAll($sql, ['user_id' => $userId]);

    // Procesar resultados
    $farms = [];
    foreach ($results as $farm) {
        // Formatear fechas
        $fechaCreacion = $farm['fecha_creacion'] ? date('d/m/Y', strtotime($farm['fecha_creacion'])) : 'No especificada';
        $fechaAsociacion = $farm['fecha_asociacion'] ? date('d/m/Y', strtotime($farm['fecha_asociacion'])) : 'No especificada';

        // Determinar estado visual
        $estadoClass = 'info';
        $estadoText = $farm['estado'];
        
        if ($farm['estado'] === 'ACTIVA') {
            $estadoClass = 'connected';
            $estadoText = 'Activa';
        } elseif ($farm['estado'] === 'INACTIVA') {
            $estadoClass = 'error';
            $estadoText = 'Inactiva';
        }

        // Determinar rol
        $rolText = 'Colaborador';
        if ($farm['rol'] === 'ADMIN') {
            $rolText = 'Administrador';
        }

        $farms[] = [
            'id_finca' => (int)$farm['id_finca'],
            'nombre_finca' => $farm['nombre_finca'],
            'descripcion' => $farm['descripcion'],
            'area_hectareas' => $farm['area_hectareas'] ? (float)$farm['area_hectareas'] : null,
            'estado' => $farm['estado'],
            'estado_text' => $estadoText,
            'estado_class' => $estadoClass,
            'fecha_creacion' => $fechaCreacion,
            'fecha_asociacion' => $fechaAsociacion,
            'codigo_telegan' => $farm['codigo_telegan'],
            'nombre_pais' => $farm['nombre_pais'],
            'rol' => $farm['rol'],
            'rol_text' => $rolText,
            'total_potreros' => (int)$farm['total_potreros'],
            'total_registros' => (int)$farm['total_registros'],
            'display_info' => $farm['nombre_pais'] . ' • ' . $farm['area_hectareas'] . ' ha • ' . $farm['total_potreros'] . ' potreros'
        ];
    }

    sendResponse([
        'success' => true,
        'data' => $farms,
        'user_id' => (int)$userId,
        'total' => count($farms),
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    error_log("Error en user-farms.php: " . $e->getMessage());
    handleError('Error interno del servidor', 500);
}
?>








