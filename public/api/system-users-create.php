<?php
/**
 * API: Crear nuevo usuario del sistema (admin_users)
 */

require_once '../../src/Config/Database.php';
require_once '../../src/Config/ApiAuth.php';

// Iniciar sesión para acceder a $_SESSION
session_start();

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
    
    if (!$input) {
        respond(['success' => false, 'error' => 'Datos inválidos'], 400);
    }

    // Validar campos requeridos
    if (empty($input['nombre_completo']) || !validateInput($input['nombre_completo'], 'string', 255)) {
        respond(['success' => false, 'error' => 'Nombre completo es requerido y debe ser válido'], 400);
    }

    if (empty($input['email']) || !validateInput($input['email'], 'email')) {
        respond(['success' => false, 'error' => 'Email es requerido y debe ser válido'], 400);
    }

    if (empty($input['password']) || strlen($input['password']) < 8) {
        respond(['success' => false, 'error' => 'Contraseña es requerida y debe tener al menos 8 caracteres'], 400);
    }

    if (empty($input['rol']) || !in_array($input['rol'], ['SUPER_ADMIN', 'TECNICO', 'ADMIN_FINCA'])) {
        respond(['success' => false, 'error' => 'Rol inválido. Debe ser: SUPER_ADMIN, TECNICO o ADMIN_FINCA'], 400);
    }

    // Validar teléfono (opcional)
    if (!empty($input['telefono']) && !validateInput($input['telefono'], 'string', 20)) {
        respond(['success' => false, 'error' => 'Teléfono inválido'], 400);
    }

    $db = Database::getConnection();

    // Verificar que el email no exista
    $checkSql = "SELECT id_admin FROM admin_users WHERE email = :email";
    $checkStmt = $db->prepare($checkSql);
    $checkStmt->bindValue(':email', trim($input['email']));
    $checkStmt->execute();
    
    if ($checkStmt->fetch()) {
        respond(['success' => false, 'error' => 'El email ya está registrado'], 409);
    }

    // Hash de contraseña
    $passwordHash = password_hash($input['password'], PASSWORD_BCRYPT, ['cost' => 12]);

    // Obtener ID del usuario que crea (desde sesión)
    $createdBy = null;
    if (isset($_SESSION['admin_id'])) {
        $createdBy = (int)$_SESSION['admin_id'];
    }

    // Insertar nuevo usuario
    $insertSql = "
        INSERT INTO admin_users (
            nombre_completo,
            email,
            password_hash,
            telefono,
            rol,
            activo,
            email_verificado,
            telefono_verificado,
            fecha_registro,
            fecha_actualizacion,
            created_by
        ) VALUES (
            :nombre_completo,
            :email,
            :password_hash,
            :telefono,
            :rol,
            :activo,
            :email_verificado,
            :telefono_verificado,
            CURRENT_TIMESTAMP,
            CURRENT_TIMESTAMP,
            :created_by
        )
        RETURNING id_admin, nombre_completo, email, telefono, rol, activo, fecha_registro
    ";

    $insertStmt = $db->prepare($insertSql);
    $insertStmt->bindValue(':nombre_completo', trim($input['nombre_completo']));
    $insertStmt->bindValue(':email', trim(strtolower($input['email'])));
    $insertStmt->bindValue(':password_hash', $passwordHash);
    $insertStmt->bindValue(':telefono', !empty($input['telefono']) ? trim($input['telefono']) : null);
    $insertStmt->bindValue(':rol', $input['rol']);
    $insertStmt->bindValue(':activo', isset($input['activo']) ? (bool)$input['activo'] : false, PDO::PARAM_BOOL);
    $insertStmt->bindValue(':email_verificado', isset($input['email_verificado']) ? (bool)$input['email_verificado'] : false, PDO::PARAM_BOOL);
    $insertStmt->bindValue(':telefono_verificado', isset($input['telefono_verificado']) ? (bool)$input['telefono_verificado'] : false, PDO::PARAM_BOOL);
    $insertStmt->bindValue(':created_by', $createdBy, PDO::PARAM_INT);
    
    $insertStmt->execute();
    $newUser = $insertStmt->fetch(PDO::FETCH_ASSOC);

    respond([
        'success' => true,
        'message' => 'Usuario creado exitosamente',
        'data' => [
            'id_admin' => (int)$newUser['id_admin'],
            'nombre_completo' => $newUser['nombre_completo'],
            'email' => $newUser['email'],
            'telefono' => $newUser['telefono'],
            'rol' => $newUser['rol'],
            'activo' => (bool)$newUser['activo'],
            'fecha_registro' => $newUser['fecha_registro']
        ]
    ], 201);

} catch (Exception $e) {
    error_log("Error en system-users-create.php: " . $e->getMessage());
    respond([
        'success' => false,
        'error' => 'Error al crear usuario del sistema'
    ], 500);
}

