<?php
/**
 * API: Operaciones en masa sobre usuarios (activar/desactivar)
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
    
    // Validar token de autenticación (ApiAuth normaliza el path automáticamente)
    $validation = ApiAuth::validateRequest();
    
    if (!$validation['valid']) {
        respond([
            'success' => false, 
            'error' => 'Acceso no autorizado: ' . ($validation['error'] ?? 'Token inválido')
        ], 401);
    }

    // Obtener JSON del body
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data) {
        respond(['success' => false, 'error' => 'Datos inválidos'], 400);
    }

    // Validar que existan los campos requeridos
    $ids = $data['ids'] ?? [];
    $action = $data['action'] ?? '';

    if (empty($ids) || !is_array($ids)) {
        respond(['success' => false, 'error' => 'Lista de IDs vacía o inválida'], 400);
    }

    if (empty($action)) {
        respond(['success' => false, 'error' => 'Acción no especificada'], 400);
    }

    // Validar acción permitida
    $allowedActions = ['activate', 'deactivate'];
    if (!in_array($action, $allowedActions)) {
        respond(['success' => false, 'error' => 'Acción no permitida'], 400);
    }

    // Validar que todos los IDs sean numéricos
    $ids = array_filter(array_map('intval', $ids));
    
    if (empty($ids)) {
        respond(['success' => false, 'error' => 'No hay IDs válidos para procesar'], 400);
    }

    // Establecer nuevo estado según la acción
    $newStatus = ($action === 'activate') ? 'TRUE' : 'FALSE';

    // Construir la lista de IDs para el IN clause usando placeholders ?
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    // Ejecutar actualización masiva
    $sql = "UPDATE usuario SET activo = {$newStatus} WHERE id_usuario IN ({$placeholders})";
    
    $affected = Database::update($sql, $ids);
    
    if ($affected === 0) {
        respond(['success' => false, 'error' => 'No se actualizaron registros'], 400);
    }

    respond([
        'success' => true,
        'message' => "Se procesaron {$affected} usuario(s) correctamente",
        'affected' => $affected
    ]);

} catch (Exception $e) {
    error_log('users-bulk-operations.php error: ' . $e->getMessage());
    respond(['success' => false, 'error' => 'Error interno del servidor'], 500);
}
?>

