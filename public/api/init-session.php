<?php
/**
 * Inicializar sesión y generar token de sesión persistente
 * Se llama una vez al cargar el dashboard
 */

require_once '../../src/Config/Database.php';
require_once '../../src/Config/ApiAuth.php';
require_once '../../src/Config/Session.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Iniciar sesión PHP
Session::start();

// Verificar si ya existe un token de sesión (generado en login)
$sessionToken = Session::get('session_token');
$sessionTimestamp = Session::get('session_timestamp');

// Si no existe token o está expirado, generar uno nuevo
if (empty($sessionToken) || ($sessionTimestamp && (time() - $sessionTimestamp) > 3600)) {
    // Generar nuevo token de sesión único
    $sessionToken = bin2hex(random_bytes(32)); // Token de 64 caracteres
    $sessionTimestamp = time();
    
    // Guardar token en sesión
    Session::set('session_token', $sessionToken);
    Session::set('session_timestamp', $sessionTimestamp);
    Session::set('session_valid', true);
} else {
    // Usar token existente (ya generado en login)
    Session::set('session_valid', true);
}

$sessionId = Session::getId();

// Respuesta con token de sesión
echo json_encode([
    'success' => true,
    'data' => [
        'session_token' => $sessionToken,
        'session_id' => $sessionId,
        'timestamp' => $sessionTimestamp,
        'expires_in' => 3600 // 1 hora
    ],
    'timestamp' => date('Y-m-d H:i:s')
], JSON_UNESCAPED_UNICODE);

