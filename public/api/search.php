<?php
/**
 * API Endpoint para Búsqueda de Agricultores
 * Búsqueda con autocompletar en nombre_completo
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

    // Obtener parámetros de búsqueda
    $query = trim($_GET['q'] ?? '');
    $limit = (int)($_GET['limit'] ?? 10);
    
    // Validación adicional
    if (empty($query)) {
        sendResponse([
            'success' => true,
            'data' => [],
            'query' => $query,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
    // Limitar longitud de búsqueda
    if (strlen($query) > 100) {
        handleError('Término de búsqueda demasiado largo', 400);
    }
    
    // Limitar número de resultados
    if ($limit < 1 || $limit > 50) {
        $limit = 10;
    }

    // Limpiar y preparar query
    $query = trim($query);
    $query = preg_replace('/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/', '', $query); // Solo letras y espacios
    
    if (strlen($query) < 2) {
        sendResponse([
            'success' => true,
            'data' => [],
            'query' => $query,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    // Construir consulta SQL con ILIKE para búsqueda insensible a mayúsculas
    $searchTerm = '%' . $query . '%';
    
    $sql = "
        SELECT 
            u.id_usuario,
            u.nombre_completo,
            u.email,
            u.telefono,
            u.ubicacion_general,
            u.activo,
            u.email_verificado,
            u.telefono_verificado,
            u.fecha_registro,
            u.ultima_sesion,
            u.codigo_telegan,
            p.nombre_pais,
            COUNT(DISTINCT uf.id_finca) as total_fincas,
            CASE 
                WHEN u.activo = FALSE THEN 'Inactivo'
                WHEN u.ultima_sesion IS NULL THEN 'Nunca inició sesión'
                WHEN u.ultima_sesion < CURRENT_DATE - INTERVAL '30 days' THEN 'Inactivo 30+ días'
                WHEN u.ultima_sesion < CURRENT_DATE - INTERVAL '7 days' THEN 'Inactivo 7+ días'
                ELSE 'Activo'
            END as estado_usuario
        FROM usuario u
        LEFT JOIN pais p ON u.ubicacion_general = p.nombre_pais
        LEFT JOIN usuario_finca uf ON u.id_usuario = uf.id_usuario
        WHERE u.nombre_completo ILIKE :query
        GROUP BY u.id_usuario, u.nombre_completo, u.email, u.telefono, 
                 u.ubicacion_general, u.activo, u.email_verificado, 
                 u.telefono_verificado, u.fecha_registro, u.ultima_sesion, 
                 u.codigo_telegan, p.nombre_pais
        ORDER BY 
            CASE 
                WHEN u.nombre_completo ILIKE :exact_query THEN 1
                WHEN u.nombre_completo ILIKE :start_query THEN 2
                ELSE 3
            END,
            u.nombre_completo ASC
        LIMIT :limit
    ";

    // Preparar parámetros
    $params = [
        'query' => $searchTerm,
        'exact_query' => $query,
        'start_query' => $query . '%',
        'limit' => $limit
    ];

    // Ejecutar consulta
    $results = Database::fetchAll($sql, $params);

    // Procesar resultados
    $users = [];
    foreach ($results as $user) {
        // Generar iniciales para avatar
        $initials = '';
        $names = explode(' ', trim($user['nombre_completo']));
        if (count($names) >= 2) {
            $initials = strtoupper(substr($names[0], 0, 1) . substr($names[count($names)-1], 0, 1));
        } else {
            $initials = strtoupper(substr($user['nombre_completo'], 0, 2));
        }

        // Formatear fechas
        $fechaRegistro = $user['fecha_registro'] ? date('d/m/Y', strtotime($user['fecha_registro'])) : 'No registrado';
        $ultimaSesion = $user['ultima_sesion'] ? date('d/m/Y H:i', strtotime($user['ultima_sesion'])) : 'Nunca';

        $users[] = [
            'id_usuario' => (int)$user['id_usuario'],
            'nombre_completo' => $user['nombre_completo'],
            'email' => $user['email'],
            'telefono' => $user['telefono'],
            'ubicacion_general' => $user['ubicacion_general'],
            'nombre_pais' => $user['nombre_pais'],
            'activo' => (bool)$user['activo'],
            'email_verificado' => (bool)$user['email_verificado'],
            'telefono_verificado' => (bool)$user['telefono_verificado'],
            'fecha_registro' => $fechaRegistro,
            'ultima_sesion' => $ultimaSesion,
            'codigo_telegan' => $user['codigo_telegan'],
            'total_fincas' => (int)$user['total_fincas'],
            'estado_usuario' => $user['estado_usuario'],
            'initials' => $initials,
            'display_info' => $user['nombre_completo'] . ' • ' . ($user['nombre_pais'] ?: $user['ubicacion_general']) . ' • ' . $user['total_fincas'] . ' fincas'
        ];
    }

    sendResponse([
        'success' => true,
        'data' => $users,
        'query' => $query,
        'total' => count($users),
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    error_log("Error en search.php: " . $e->getMessage());
    handleError('Error interno del servidor', 500);
}
?>
