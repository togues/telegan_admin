<?php
/**
 * Reset de Contraseña con Token - Sistema de Autenticación Telegan
 * Página que permite crear nueva contraseña usando token del email
 */

// Inicializar sesión
session_start();

// Incluir dependencias
require_once 'config/Security.php';
require_once 'config/Database.php';

// Inicializar seguridad
AuthSecurity::init();

$error = '';
$success = '';
$token = $_GET['token'] ?? '';
$validToken = false;
$email = '';

// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: ../public/dashboard.php');
    exit;
}

// Validar token si está presente
if ($token) {
    try {
        // Verificar que el token es válido y no ha expirado
        $checkSql = "SELECT id, email FROM pending_confirmations WHERE token_confirmacion = ? AND tipo_confirmacion = 'RESET_PASSWORD' AND fecha_expiracion > NOW() AND completada = FALSE";
        $confirmation = Database::fetch($checkSql, [$token]);
        
        if ($confirmation) {
            $validToken = true;
            $email = $confirmation['email'];
            
            // Guardar en sesión para el procesamiento del formulario
            $_SESSION['password_reset_token'] = [
                'token' => $token,
                'email' => $email,
                'confirmation_id' => $confirmation['id']
            ];
        } else {
            $error = 'El link de recuperación es inválido o ha expirado.';
            AuthSecurity::logSecurityEvent('INVALID_RESET_LINK', ['token' => substr($token, 0, 10) . '...'], 'WARNING');
        }
    } catch (Exception $e) {
        error_log("Error en validación de token: " . $e->getMessage());
        $error = 'Error interno. Intenta más tarde.';
    }
} else {
    $error = 'Link de recuperación no válido.';
}

// Procesar formulario de nueva contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    $resetData = $_SESSION['password_reset_token'] ?? null;
    
    if (!$resetData) {
        $error = 'Sesión expirada. Solicita un nuevo link de recuperación.';
    } else {
        if (!AuthSecurity::validateInput($newPassword, 'password')) {
            $error = 'La contraseña debe tener al menos 8 caracteres';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Las contraseñas no coinciden';
        } else {
            try {
                // Hash de nueva contraseña
                $passwordHash = AuthSecurity::hashPassword($newPassword);
                
                // Actualizar contraseña en la tabla de usuarios
                $updateSql = "UPDATE admin_users SET password_hash = ?, fecha_actualizacion = NOW() WHERE email = ?";
                Database::update($updateSql, [$passwordHash, $resetData['email']]);
                
                // Marcar confirmación como completada
                $confirmSql = "UPDATE pending_confirmations SET completada = TRUE WHERE id = ?";
                Database::update($confirmSql, [$resetData['confirmation_id']]);
                
                // Limpiar sesión
                unset($_SESSION['password_reset_token']);
                
                $success = 'Contraseña actualizada exitosamente.';
                
                // Redirigir al login después de 3 segundos
                echo "<script>setTimeout(() => { window.location.href = 'login.php?reset=1'; }, 3000);</script>";
                
                AuthSecurity::logSecurityEvent('PASSWORD_RESET_COMPLETED', ['email' => $resetData['email']], 'INFO');
            } catch (Exception $e) {
                error_log("Error al resetear contraseña: " . $e->getMessage());
                $error = 'Error interno. Intenta más tarde.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Contraseña - Telegan Admin</title>
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
                
                <?php if ($validToken): ?>
                    <div class="verification-icon">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 1L3 5V11C3 16.55 6.84 21.74 12 23C17.16 21.74 21 16.55 21 11V5L12 1Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M9 12L11 14L15 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <h2>Nueva contraseña</h2>
                    <p>Crea una nueva contraseña segura para</p>
                    <div class="email-display"><?php echo AuthSecurity::sanitizeOutput($email); ?></div>
                <?php else: ?>
                    <div class="verification-icon error">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <h2>Link inválido</h2>
                    <p>El link de recuperación no es válido</p>
                <?php endif; ?>
            </div>

            <?php if ($validToken): ?>
                <form class="auth-form" method="POST" action="">
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

                    <div class="form-group">
                        <label for="new_password">Nueva contraseña</label>
                        <input 
                            type="password" 
                            id="new_password" 
                            name="new_password" 
                            required 
                            autocomplete="new-password"
                            placeholder="••••••••"
                            minlength="8"
                        >
                        <small>Mínimo 8 caracteres</small>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirmar nueva contraseña</label>
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            required 
                            autocomplete="new-password"
                            placeholder="••••••••"
                        >
                    </div>

                    <button type="submit" class="btn-primary">
                        Actualizar contraseña
                    </button>
                </form>
            <?php else: ?>
                <div class="auth-form">
                    <?php if ($error): ?>
                        <div class="error-message">
                            <?php echo AuthSecurity::sanitizeOutput($error); ?>
                        </div>
                    <?php endif; ?>

                    <div class="verification-actions">
                        <a href="forgot-password.php" class="btn-primary">
                            Solicitar Nuevo Link
                        </a>
                        
                        <a href="login.php" class="btn-link">
                            Volver al Login
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <div class="auth-footer">
                <p>¿Necesitas ayuda? <a href="mailto:support@telegan.com">Contacta soporte</a></p>
            </div>
        </div>
    </div>

    <script src="assets/js/auth.js"></script>
    <script>
        // Auto-focus en el primer campo
        document.addEventListener('DOMContentLoaded', function() {
            const newPasswordInput = document.getElementById('new_password');
            if (newPasswordInput) {
                newPasswordInput.focus();
            }
        });
    </script>
</body>
</html>
