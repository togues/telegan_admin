<?php
/**
 * API: Actualización en masa de usuarios
 * Permite activar/desactivar múltiples usuarios
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

    // Obtener datos del body
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        respond(['success' => false, 'error' => 'Datos JSON inválidos'], 400);
    }

    // Validar campos requeridos
    if (!isset($data['user_ids']) || !is_array($data['user_ids'])) {
        respond(['success' => false, 'error' => 'Campo user_ids es requerido y debe ser un array'], 400);
    }

    if (!isset($data['action']) || !in_array($data['action'], ['activate', 'deactivate'])) {
        respond(['success' => false, 'error' => 'Campo action es requerido y debe ser "activate" o "deactivate"'], 400);
    }

    $userIds = array_filter($data['user_ids'], 'is_numeric');

    // Validar que haya al menos un ID válido
    if (empty($userIds)) {
        respond(['success' => false, 'error' => 'Debe proporcionar al menos un ID de usuario válido'], 400);
    }

    // Validar que no haya demasiados IDs (máximo 100 por seguridad)
    if (count($userIds) > 100) {
        respond(['success' => false, 'error' => 'No se pueden actualizar más de 100 usuarios a la vez'], 400);
    }

    $action = $data['action'];
    $newStatus = ($action === 'activate') ? true : false;

    // Construir la consulta SQL
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));

    $sql = "
        UPDATE usuario
        SET activo = ?
        WHERE id_usuario IN ($placeholders)
    ";

    // Preparar parámetros: el nuevo estado + todos los IDs
    $params = array_merge([$newStatus], array_values($userIds));

    // Ejecutar la actualización usando el método update() existente
    $affected = Database::update($sql, $params);

    // Registrar en log
    error_log(sprintf(
        'Bulk update: action=%s, user_ids=%s, affected=%d',
        $action,
        implode(',', $userIds),
        $affected
    ));

    respond([
        'success' => true,
        'message' => sprintf(
            '%d usuario(s) %s correctamente',
            $affected,
            $action === 'activate' ? 'activado(s)' : 'desactivado(s)'
        ),
        'affected_count' => $affected,
        'requested_count' => count($userIds),
        'action' => $action
    ]);

} catch (Exception $e) {
    error_log('bulk-update-users.php error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    respond(['success' => false, 'error' => 'Error interno del servidor'], 500);
}
