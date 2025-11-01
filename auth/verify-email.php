<?php
/**
 * Validación Automática de Email - Sistema de Autenticación Telegan
 * Página que valida automáticamente el email cuando el usuario hace clic en el link
 */

// Inicializar sesión
session_start();

// Incluir dependencias
require_once 'config/Security.php';
require_once 'config/Database.php';
require_once 'config/Email.php';

// Inicializar seguridad
AuthSecurity::init();

$error = '';
$success = '';
$email = '';
$token = $_GET['token'] ?? '';

// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: ../public/dashboard.php');
    exit;
}

// Validar token si está presente
if ($token) {
    try {
        // Verificar que el token es válido y no ha expirado
        $checkSql = "SELECT id_admin, email, nombre_completo FROM admin_users WHERE token_confirmacion = ? AND expiracion_confirmacion > NOW() AND activo = FALSE";
        $user = Database::fetch($checkSql, [$token]);
        
        if ($user) {
            // Activar usuario automáticamente
            $activateSql = "UPDATE admin_users SET activo = TRUE, email_verificado = TRUE, codigo_confirmacion = NULL, token_confirmacion = NULL, expiracion_confirmacion = NULL WHERE id_admin = ?";
            Database::update($activateSql, [$user['id_admin']]);
            
            // Marcar confirmación como completada
            $confirmSql = "UPDATE pending_confirmations SET completada = TRUE WHERE email = ? AND token_confirmacion = ?";
            Database::update($confirmSql, [$user['email'], $token]);
            
            // Enviar email de bienvenida
            $welcomeSent = EmailManager::sendWelcomeEmail($user['email'], $user['nombre_completo']);
            if (!$welcomeSent) {
                AuthSecurity::logSecurityEvent('WELCOME_EMAIL_FAILED', ['email' => $user['email']], 'WARNING');
            }
            
            // Log de activación exitosa
            AuthSecurity::logSecurityEvent('USER_ACTIVATED_VIA_LINK', [
                'email' => $user['email'],
                'user_id' => $user['id_admin']
            ], 'INFO');
            
            $success = '¡Cuenta activada exitosamente!';
            $email = $user['email'];
            
            // Redirigir al login después de 3 segundos
            echo "<script>setTimeout(() => { window.location.href = 'login.php?verified=1'; }, 3000);</script>";
            
        } else {
            $error = 'El link de verificación es inválido o ha expirado.';
            AuthSecurity::logSecurityEvent('INVALID_VERIFICATION_LINK', ['token' => substr($token, 0, 10) . '...'], 'WARNING');
        }
    } catch (Exception $e) {
        error_log("Error en verificación automática: " . $e->getMessage());
        $error = 'Error interno. Intenta más tarde.';
    }
} else {
    $error = 'Link de verificación no válido.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de Email - Telegan Admin</title>
    <link rel="stylesheet" href="assets/css/auth.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        // Cargar tema guardado o detectar preferencia del sistema
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('telegan-theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const theme = savedTheme || (prefersDark ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', theme);
        });
    </script>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="logo">
                    <h1>Telegan</h1>
                    <span>Admin Panel</span>
                </div>
                
                <?php if ($success): ?>
                    <div class="verification-icon success">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M9 12L11 14L15 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <h2>¡Email verificado!</h2>
                    <p>Tu cuenta ha sido activada exitosamente</p>
                    <div class="email-display success"><?php echo AuthSecurity::sanitizeOutput($email); ?></div>
                <?php else: ?>
                    <div class="verification-icon error">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <h2>Error de verificación</h2>
                    <p>No se pudo verificar tu email</p>
                <?php endif; ?>
            </div>

            <div class="auth-form">
                <?php if ($error): ?>
                    <div class="error-message">
                        <?php echo AuthSecurity::sanitizeOutput($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="success-message">
                        <?php echo AuthSecurity::sanitizeOutput($success); ?>
                        <br><br>
                        <small>Serás redirigido al login automáticamente...</small>
                    </div>
                <?php endif; ?>

                <div class="verification-actions">
                    <?php if ($success): ?>
                        <a href="login.php" class="btn-primary">
                            Ir al Login
                        </a>
                    <?php else: ?>
                        <a href="register.php" class="btn-primary">
                            Intentar Registro Nuevamente
                        </a>
                    <?php endif; ?>
                    
                    <a href="login.php" class="btn-link">
                        Ya tengo una cuenta
                    </a>
                </div>
            </div>

            <div class="auth-footer">
                <p>¿Necesitas ayuda? <a href="mailto:support@telegan.com">Contacta soporte</a></p>
            </div>
        </div>
    </div>

    <script src="assets/js/auth.js"></script>
</body>
</html>
