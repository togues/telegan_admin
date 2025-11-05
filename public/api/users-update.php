<?php
/**
 * API: Actualizar usuario (tabla usuario)
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
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
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
    
    if (!$input || !isset($input['id_usuario'])) {
        respond(['success' => false, 'error' => 'ID y datos requeridos'], 400);
    }

    $id = (int)$input['id_usuario'];
    
    if ($id <= 0) {
        respond(['success' => false, 'error' => 'ID inválido'], 400);
    }

    // Verificar que el usuario existe y obtener valores actuales (para campos NOT NULL)
    $checkSql = "SELECT id_usuario, activo, email_verificado, telefono_verificado FROM usuario WHERE id_usuario = :id";
    $existing = Database::fetch($checkSql, ['id' => $id]);
    
    if (!$existing) {
        respond(['success' => false, 'error' => 'Usuario no encontrado'], 404);
    }

    // Guardar valores actuales para campos NOT NULL (por si no vienen en el input)
    $currentActivo = (bool)$existing['activo'];
    $currentEmailVerificado = (bool)$existing['email_verificado'];
    $currentTelefonoVerificado = (bool)$existing['telefono_verificado'];

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
        $emailCheckSql = "SELECT id_usuario FROM usuario WHERE email = :email AND id_usuario != :id";
        $emailExists = Database::fetch($emailCheckSql, ['email' => trim(strtolower($input['email'])), 'id' => $id]);
        
        if ($emailExists) {
            respond(['success' => false, 'error' => 'El email ya está en uso por otro usuario'], 409);
        }
        $updates[] = 'email = :email';
        $params['email'] = trim(strtolower($input['email']));
    }

    if (isset($input['telefono'])) {
        if (!empty($input['telefono']) && !validateInput($input['telefono'], 'string', 20)) {
            respond(['success' => false, 'error' => 'Teléfono inválido'], 400);
        }
        $updates[] = 'telefono = :telefono';
        $params['telefono'] = !empty($input['telefono']) ? trim($input['telefono']) : null;
    }

    if (isset($input['ubicacion_general'])) {
        $updates[] = 'ubicacion_general = :ubicacion_general';
        $params['ubicacion_general'] = !empty($input['ubicacion_general']) ? trim($input['ubicacion_general']) : null;
    }

    if (isset($input['codigo_telegan'])) {
        $updates[] = 'codigo_telegan = :codigo_telegan';
        $params['codigo_telegan'] = !empty($input['codigo_telegan']) ? trim($input['codigo_telegan']) : null;
    }

    // Siempre actualizar estos campos (son NOT NULL en la BD)
    // Usar valores del input si vienen, sino mantener los actuales
    $updates[] = 'activo = :activo';
    $params['activo'] = isset($input['activo']) ? (bool)$input['activo'] : $currentActivo;
    
    $updates[] = 'email_verificado = :email_verificado';
    $params['email_verificado'] = isset($input['email_verificado']) ? (bool)$input['email_verificado'] : $currentEmailVerificado;
    
    $updates[] = 'telefono_verificado = :telefono_verificado';
    $params['telefono_verificado'] = isset($input['telefono_verificado']) ? (bool)$input['telefono_verificado'] : $currentTelefonoVerificado;

    if (empty($updates)) {
        respond(['success' => false, 'error' => 'No hay campos para actualizar'], 400);
    }

    // Agregar fecha_actualizacion si existe el campo
    $updateSql = "UPDATE usuario SET " . implode(', ', $updates) . " WHERE id_usuario = :id";
    
    Database::update($updateSql, $params);

    // Obtener datos actualizados
    $selectSql = "SELECT id_usuario, nombre_completo, email, telefono, ubicacion_general, codigo_telegan, activo, email_verificado, telefono_verificado FROM usuario WHERE id_usuario = :id";
    $updated = Database::fetch($selectSql, ['id' => $id]);

    respond([
        'success' => true,
        'message' => 'Usuario actualizado exitosamente',
        'data' => [
            'id_usuario' => (int)$updated['id_usuario'],
            'nombre_completo' => $updated['nombre_completo'],
            'email' => $updated['email'],
            'telefono' => $updated['telefono'],
            'ubicacion_general' => $updated['ubicacion_general'],
            'codigo_telegan' => $updated['codigo_telegan'],
            'activo' => (bool)$updated['activo'],
            'email_verificado' => (bool)$updated['email_verificado'],
            'telefono_verificado' => (bool)$updated['telefono_verificado']
        ]
    ]);

} catch (Exception $e) {
    error_log("Error en users-update.php: " . $e->getMessage());
    respond([
        'success' => false,
        'error' => 'Error al actualizar usuario: ' . $e->getMessage()
    ], 500);
}

