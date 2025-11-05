<?php
/**
 * Redirección automática al dashboard con sesión
 */

// Iniciar sesión
session_start();

// Verificar autenticación
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../auth/login.php');
    exit;
}

// Redirigir al dashboard con sesión
header('Location: dashboard.php');
exit;
?>













