<?php
/**
 * API: Actualizar usuario del sistema (admin_users)
 */

require_once '../../src/Config/Database.php';
require_once '../../src/Config/ApiAuth.php';

// Iniciar sesión para acceder a $_SESSION
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Token, X-API-Timestamp, X-Session-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

function respond($payload, $status = 200) {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit();
}

function validateInput($input, $type = 'string', $maxLength = 255) {
    if (empty($input)) return false;
    
    switch ($type) {
        case 'email':
            return filter_var($input, FILTER_VALIDATE_EMAIL) !== false;
        case 'numeric':
            return is_numeric($input) && $input > 0;
        case 'string':
        default:
            $cleaned = trim($input);
            return strlen($cleaned) <= $maxLength && 
                   preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ0-9\s\-_\.@]+$/', $cleaned);
    }
}

try {
    // Solo permitir PUT
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
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

    // Obtener datos del PUT
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['id_admin'])) {
        respond(['success' => false, 'error' => 'ID y datos requeridos'], 400);
    }

    $id = (int)$input['id_admin'];
    
    if ($id <= 0) {
        respond(['success' => false, 'error' => 'ID inválido'], 400);
    }

    // Verificar que el usuario existe
    $checkSql = "SELECT id_admin FROM admin_users WHERE id_admin = :id";
    $existing = Database::fetch($checkSql, ['id' => $id]);
    
    if (!$existing) {
        respond(['success' => false, 'error' => 'Usuario no encontrado'], 404);
    }

    // Construir campos a actualizar
    $updates = [];
    $params = ['id' => $id];

    if (isset($input['nombre_completo'])) {
        if (!validateInput($input['nombre_completo'], 'string', 255)) {
            respond(['success' => false, 'error' => 'Nombre completo inválido'], 400);
        }
        $updates[] = 'nombre_completo = :nombre_completo';
        $params['nombre_completo'] = trim($input['nombre_completo']);
    }

    if (isset($input['email'])) {
        if (!validateInput($input['email'], 'email')) {
            respond(['success' => false, 'error' => 'Email inválido'], 400);
        }
        // Verificar que el email no esté en uso por otro usuario
        $emailCheckSql = "SELECT id_admin FROM admin_users WHERE email = :email AND id_admin != :id";
        $emailExists = Database::fetch($emailCheckSql, ['email' => trim(strtolower($input['email'])), 'id' => $id]);
        
        if ($emailExists) {
            respond(['success' => false, 'error' => 'El email ya está en uso por otro usuario'], 409);
        }
        $updates[] = 'email = :email';
        $params['email'] = trim(strtolower($input['email']));
    }

    if (isset($input['password']) && !empty($input['password'])) {
        if (strlen($input['password']) < 8) {
            respond(['success' => false, 'error' => 'La contraseña debe tener al menos 8 caracteres'], 400);
        }
        $updates[] = 'password_hash = :password_hash';
        $params['password_hash'] = password_hash($input['password'], PASSWORD_BCRYPT, ['cost' => 12]);
    }

    if (array_key_exists('telefono', $input)) {
        if (!empty($input['telefono']) && !validateInput($input['telefono'], 'string', 20)) {
            respond(['success' => false, 'error' => 'Teléfono inválido'], 400);
        }
        $updates[] = 'telefono = :telefono';
        $params['telefono'] = !empty($input['telefono']) ? trim($input['telefono']) : null;
    }

    if (isset($input['rol'])) {
        if (!in_array($input['rol'], ['SUPER_ADMIN', 'TECNICO', 'ADMIN_FINCA'])) {
            respond(['success' => false, 'error' => 'Rol inválido'], 400);
        }
        $updates[] = 'rol = :rol';
        $params['rol'] = $input['rol'];
    }

    if (array_key_exists('activo', $input)) {
        $updates[] = 'activo = :activo';
        $params['activo'] = $input['activo'] ? 'true' : 'false';
    }

    if (array_key_exists('email_verificado', $input)) {
        $updates[] = 'email_verificado = :email_verificado';
        $params['email_verificado'] = $input['email_verificado'] ? 'true' : 'false';
    }

    if (array_key_exists('telefono_verificado', $input)) {
        $updates[] = 'telefono_verificado = :telefono_verificado';
        $params['telefono_verificado'] = $input['telefono_verificado'] ? 'true' : 'false';
    }

    if (empty($updates)) {
        respond(['success' => false, 'error' => 'No hay campos para actualizar'], 400);
    }

    // Agregar fecha_actualizacion
    $updates[] = 'fecha_actualizacion = CURRENT_TIMESTAMP';

    $updateSql = "UPDATE admin_users SET " . implode(', ', $updates) . " WHERE id_admin = :id";
    
    Database::update($updateSql, $params);

    // Obtener datos actualizados
    $selectSql = "SELECT id_admin, nombre_completo, email, telefono, rol, activo, email_verificado, telefono_verificado, fecha_actualizacion FROM admin_users WHERE id_admin = :id";
    $updated = Database::fetch($selectSql, ['id' => $id]);

    respond([
        'success' => true,
        'message' => 'Usuario actualizado exitosamente',
        'data' => [
            'id_admin' => (int)$updated['id_admin'],
            'nombre_completo' => $updated['nombre_completo'],
            'email' => $updated['email'],
            'telefono' => $updated['telefono'],
            'rol' => $updated['rol'],
            'activo' => (bool)$updated['activo'],
            'email_verificado' => (bool)$updated['email_verificado'],
            'telefono_verificado' => (bool)$updated['telefono_verificado'],
            'fecha_actualizacion' => $updated['fecha_actualizacion']
        ]
    ]);

} catch (Exception $e) {
    error_log("Error en system-users-update.php: " . $e->getMessage());
    respond([
        'success' => false,
        'error' => 'Error al actualizar usuario del sistema: ' . $e->getMessage()
    ], 500);
}

