<?php
/**
 * Logout seguro para módulo de autenticación
 */

session_start();

require_once 'config/Security.php';
require_once 'config/Database.php';

AuthSecurity::init();

$adminSessionToken = $_SESSION['admin_session_token'] ?? null;

if ($adminSessionToken) {
    try {
        AuthDatabase::update(
            "UPDATE admin_sessions SET activa = FALSE, fecha_expiracion = NOW() WHERE token_sesion = ?",
            [$adminSessionToken],
            'SESSION_LOGOUT'
        );
    } catch (Exception $e) {
        error_log('Error marcando sesión como cerrada: ' . $e->getMessage());
    }
}

$_SESSION = [];
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

header('Location: login.php');
exit;

