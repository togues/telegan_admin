<?php
/**
 * Página de Confirmación de Email - Sistema de Autenticación Telegan
 * Flujo moderno como las grandes empresas
 */

// Inicializar sesión
session_start();

// Incluir dependencias
require_once 'config/Security.php';
require_once 'config/Database.php';
require_once 'config/Email.php';

// Inicializar seguridad
AuthSecurity::init();

// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: ../public/dashboard.html');
    exit;
}

// Verificar si hay confirmación pendiente
if (!isset($_SESSION['pending_confirmation'])) {
    header('Location: register.php');
    exit;
}

$confirmationData = $_SESSION['pending_confirmation'];
$email = $confirmationData['email'];
$confirmationCode = $confirmationData['confirmation_code'];
$confirmationToken = $confirmationData['confirmation_token'];

$error = '';
$success = '';

// Procesar confirmación si es POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'verify_code') {
        $inputCode = trim($_POST['verification_code'] ?? '');
        
        if (empty($inputCode)) {
            $error = 'Ingresa el código de verificación';
        } elseif ($inputCode !== $confirmationCode) {
            $error = 'Código de verificación incorrecto';
            AuthSecurity::logSecurityEvent('INVALID_VERIFICATION_CODE', ['email' => $email], 'WARNING');
        } else {
            try {
                // Verificar que el token aún es válido
                $checkSql = "SELECT id_admin FROM admin_users WHERE email = ? AND token_confirmacion = ? AND expiracion_confirmacion > NOW()";
                $user = AuthDatabase::fetch($checkSql, [$email, $confirmationToken]);
                
                if ($user) {
                    // Activar usuario
                    $activateSql = "UPDATE admin_users SET activo = TRUE, email_verificado = TRUE, codigo_confirmacion = NULL, token_confirmacion = NULL, expiracion_confirmacion = NULL WHERE id_admin = ?";
                    AuthDatabase::update($activateSql, [$user['id_admin']], 'USER_ACTIVATED');
                    
                    // Marcar confirmación como completada
                    $confirmSql = "UPDATE pending_confirmations SET completada = TRUE WHERE email = ? AND token_confirmacion = ?";
                    AuthDatabase::update($confirmSql, [$email, $confirmationToken], 'CONFIRMATION_COMPLETED');
                    
                    // Enviar email de bienvenida
                    $userData = AuthDatabase::fetch("SELECT nombre_completo FROM admin_users WHERE id_admin = ?", [$user['id_admin']]);
                    if ($userData) {
                        $welcomeSent = EmailManager::sendWelcomeEmail($email, $userData['nombre_completo']);
                        if (!$welcomeSent) {
                            AuthSecurity::logSecurityEvent('WELCOME_EMAIL_FAILED', ['email' => $email], 'WARNING');
                        }
                    }
                    
                    // Log de activación exitosa
                    AuthSecurity::logSecurityEvent('USER_ACTIVATED', [
                        'email' => $email,
                        'user_id' => $user['id_admin']
                    ], 'INFO');
                    
                    // Limpiar sesión
                    unset($_SESSION['pending_confirmation']);
                    
                    // Mostrar mensaje de éxito y redirigir
                    $success = '¡Cuenta activada exitosamente! Redirigiendo al login...';
                    echo "<script>setTimeout(() => { window.location.href = 'login.php'; }, 2000);</script>";
                } else {
                    $error = 'El código de verificación ha expirado. Por favor, regístrate nuevamente.';
                    unset($_SESSION['pending_confirmation']);
                }
            } catch (Exception $e) {
                error_log("Error en confirmación: " . $e->getMessage());
                $error = 'Error interno. Intenta más tarde.';
            }
        }
    } elseif ($action === 'resend_code') {
        try {
            // Generar nuevo código
            $newCode = AuthSecurity::generateConfirmationCode();
            $newToken = AuthSecurity::generateSecureToken();
            $expirationTime = date('Y-m-d H:i:s', time() + 3600);
            
            // Actualizar en BD
            $updateSql = "UPDATE admin_users SET codigo_confirmacion = ?, token_confirmacion = ?, expiracion_confirmacion = ? WHERE email = ?";
            AuthDatabase::update($updateSql, [$newCode, $newToken, $expirationTime, $email], 'CODE_RESENT');
            
            // Actualizar en sesión
            $_SESSION['pending_confirmation']['confirmation_code'] = $newCode;
            $_SESSION['pending_confirmation']['confirmation_token'] = $newToken;
            $confirmationCode = $newCode;
            $confirmationToken = $newToken;
            
            $success = 'Nuevo código enviado a tu email';
            AuthSecurity::logSecurityEvent('CODE_RESENT', ['email' => $email], 'INFO');
        } catch (Exception $e) {
            error_log("Error al reenviar código: " . $e->getMessage());
            $error = 'Error al reenviar código. Intenta más tarde.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar Email - Telegan Admin</title>
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
                <div class="verification-icon">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M20 6L9 17L4 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <h2>Verifica tu email</h2>
                <p>Hemos enviado un código de verificación a</p>
                <div class="email-display"><?php echo AuthSecurity::sanitizeOutput($email); ?></div>
            </div>

            <form class="auth-form" method="POST" action="">
                <input type="hidden" name="action" value="verify_code">
                
                <?php if ($error): ?>
                    <div class="error-message">
                        <?php echo AuthSecurity::sanitizeOutput($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="success-message">
                        <?php echo AuthSecurity::sanitizeOutput($success); ?>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="verification_code">Código de verificación</label>
                    <div class="verification-input-container">
                        <input 
                            type="text" 
                            id="verification_code" 
                            name="verification_code" 
                            required 
                            maxlength="6"
                            pattern="[0-9]{6}"
                            placeholder="000000"
                            class="verification-input"
                            autocomplete="one-time-code"
                        >
                    </div>
                    <small>Ingresa el código de 6 dígitos que enviamos a tu email</small>
                </div>

                <button type="submit" class="btn-primary">
                    Verificar cuenta
                </button>
            </form>

            <div class="verification-actions">
                <form method="POST" action="" style="display: inline;">
                    <input type="hidden" name="action" value="resend_code">
                    <button type="submit" class="btn-secondary">
                        Reenviar código
                    </button>
                </form>
                
                <a href="register.php" class="btn-link">
                    Cambiar email
                </a>
            </div>

            <div class="auth-footer">
                <p>¿Ya tienes una cuenta? <a href="login.php">Inicia sesión aquí</a></p>
            </div>
        </div>
    </div>

    <script src="assets/js/auth.js"></script>
    <script>
        // Auto-focus en el input de verificación
        document.addEventListener('DOMContentLoaded', function() {
            const input = document.getElementById('verification_code');
            if (input) {
                input.focus();
                
                // Auto-submit cuando se complete el código
                input.addEventListener('input', function(e) {
                    if (e.target.value.length === 6) {
                        // Pequeño delay para que el usuario vea el código completo
                        setTimeout(() => {
                            e.target.form.submit();
                        }, 500);
                    }
                });
            }
        });
    </script>
</body>
</html>
