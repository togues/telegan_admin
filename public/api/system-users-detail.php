<?php
/**
 * API: Detalle de usuario del sistema (admin_users)
 */

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

function respond($payload, $status = 200) {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    // Solo permitir GET
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        respond(['success' => false, 'error' => 'Método no permitido'], 405);
    }
    
    // Validar token de autenticación
    $validation = ApiAuth::validateRequest();
    
    if (!$validation['valid']) {
        respond([
            'success' => false, 
            'error' => 'Acceso no autorizado: ' . ($validation['error'] ?? 'Token inválido')
        ], 401);
    }

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($id <= 0) {
        respond(['success' => false, 'error' => 'ID inválido'], 400);
    }

    $sql = "
        SELECT 
            au.*,
            creator.nombre_completo as creado_por_nombre
        FROM admin_users au
        LEFT JOIN admin_users creator ON au.created_by = creator.id_admin
        WHERE au.id_admin = :id
    ";

    $row = Database::fetch($sql, ['id' => $id]);

    if (!$row) {
        respond(['success' => false, 'error' => 'Usuario no encontrado'], 404);
    }

    // NO devolver password_hash por seguridad
    unset($row['password_hash']);
    unset($row['codigo_confirmacion']);
    unset($row['token_confirmacion']);

    // Formatear datos
    $data = [
        'id_admin' => (int)$row['id_admin'],
        'nombre_completo' => $row['nombre_completo'],
        'email' => $row['email'],
        'telefono' => $row['telefono'] ?? null,
        'rol' => $row['rol'],
        'activo' => (bool)$row['activo'],
        'email_verificado' => (bool)$row['email_verificado'],
        'telefono_verificado' => (bool)$row['telefono_verificado'],
        'ultima_sesion' => $row['ultima_sesion'],
        'fecha_registro' => $row['fecha_registro'],
        'fecha_actualizacion' => $row['fecha_actualizacion'],
        'intentos_login' => (int)$row['intentos_login'],
        'bloqueado_hasta' => $row['bloqueado_hasta'],
        'created_by' => $row['created_by'] ? (int)$row['created_by'] : null,
        'creado_por_nombre' => $row['creado_por_nombre'] ?? null,
        'metadata' => $row['metadata'] ? json_decode($row['metadata'], true) : null
    ];

    respond([
        'success' => true,
        'data' => $data
    ]);

} catch (Exception $e) {
    error_log("Error en system-users-detail.php: " . $e->getMessage());
    respond([
        'success' => false,
        'error' => 'Error al cargar usuario del sistema'
    ], 500);
}

