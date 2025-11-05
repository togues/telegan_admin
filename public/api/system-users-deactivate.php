<?php
/**
 * API: Desactivar usuario del sistema (soft delete)
 */

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

function respond($payload, $status = 200) {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    // Solo permitir POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

    // Obtener datos del POST
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['id_admin'])) {
        respond(['success' => false, 'error' => 'ID requerido'], 400);
    }

    $id = (int)$input['id_admin'];
    
    if ($id <= 0) {
        respond(['success' => false, 'error' => 'ID inválido'], 400);
    }

    $db = Database::getConnection();

    // Verificar que el usuario existe
    $checkSql = "SELECT id_admin, activo FROM admin_users WHERE id_admin = :id";
    $checkStmt = $db->prepare($checkSql);
    $checkStmt->bindValue(':id', $id, PDO::PARAM_INT);
    $checkStmt->execute();
    $user = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        respond(['success' => false, 'error' => 'Usuario no encontrado'], 404);
    }

    // Verificar si ya está desactivado
    if (!$user['activo']) {
        respond(['success' => false, 'error' => 'El usuario ya está desactivado'], 400);
    }

    // Soft delete: solo cambiar activo a false
    $updateSql = "UPDATE admin_users SET activo = false, fecha_actualizacion = CURRENT_TIMESTAMP WHERE id_admin = :id";
    $updateStmt = $db->prepare($updateSql);
    $updateStmt->bindValue(':id', $id, PDO::PARAM_INT);
    $updateStmt->execute();

    respond([
        'success' => true,
        'message' => 'Usuario desactivado exitosamente'
    ]);

} catch (Exception $e) {
    error_log("Error en system-users-deactivate.php: " . $e->getMessage());
    respond([
        'success' => false,
        'error' => 'Error al desactivar usuario del sistema'
    ], 500);
}

