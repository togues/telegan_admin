<?php
/**
 * Página de Olvido de Contraseña - Sistema de Autenticación Telegan
 * Flujo moderno de recuperación de contraseña
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

$error = '';
$success = '';
$step = $_GET['step'] ?? 'request'; // request, verify, reset

// Procesar formulario según el paso
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'request_reset' && $step === 'request') {
        $email = trim($_POST['email'] ?? '');
        
        if (!AuthSecurity::validateInput($email, 'email')) {
            $error = 'Email inválido';
        } else {
            try {
                // Verificar si el usuario existe y está activo
                $userSql = "SELECT id_admin, nombre_completo, email FROM admin_users WHERE email = ? AND activo = TRUE";
                $user = AuthDatabase::fetch($userSql, [$email]);
                
                if ($user) {
                    // Generar código de recuperación
                    $resetCode = AuthSecurity::generateConfirmationCode();
                    $resetToken = AuthSecurity::generateSecureToken();
                    $expirationTime = date('Y-m-d H:i:s', time() + 1800); // 30 minutos
                    
                    // Guardar en BD
                    $resetSql = "INSERT INTO pending_confirmations (email, codigo_confirmacion, token_confirmacion, tipo_confirmacion, fecha_expiracion) 
                                VALUES (?, ?, ?, ?, ?)";
                    $resetParams = [
                        $email,
                        $resetCode,
                        $resetToken,
                        'RESET_PASSWORD',
                        $expirationTime
                    ];
                    
                    AuthDatabase::insert($resetSql, $resetParams, 'PASSWORD_RESET_REQUESTED');
                    
                    // Enviar email de recuperación con link automático
                    $emailSent = EmailManager::sendPasswordResetEmail($email, $user['nombre_completo'], $resetCode, $resetToken);
                    
                    if (!$emailSent) {
                        AuthSecurity::logSecurityEvent('PASSWORD_RESET_EMAIL_FAILED', ['email' => $email], 'WARNING');
                    }
                    
                    // Guardar en sesión para el siguiente paso
                    $_SESSION['password_reset'] = [
                        'email' => $email,
                        'reset_code' => $resetCode,
                        'reset_token' => $resetToken
                    ];
                    
                    $success = 'Código de recuperación enviado a tu email';
                    AuthSecurity::logSecurityEvent('PASSWORD_RESET_REQUESTED', ['email' => $email], 'INFO');
                } else {
                    // Por seguridad, mostrar mensaje genérico
                    $success = 'Si el email existe, recibirás un código de recuperación';
                    AuthSecurity::logSecurityEvent('PASSWORD_RESET_ATTEMPT_UNKNOWN_EMAIL', ['email' => $email], 'WARNING');
                }
            } catch (Exception $e) {
                error_log("Error en solicitud de reset: " . $e->getMessage());
                $error = 'Error interno. Intenta más tarde.';
            }
        }
    } elseif ($action === 'verify_code' && $step === 'verify') {
        $inputCode = trim($_POST['verification_code'] ?? '');
        
        if (empty($inputCode)) {
            $error = 'Ingresa el código de verificación';
        } else {
            $resetData = $_SESSION['password_reset'] ?? null;
            
            if (!$resetData) {
                header('Location: forgot-password.php');
                exit;
            }
            
            if ($inputCode !== $resetData['reset_code']) {
                $error = 'Código de verificación incorrecto';
                AuthSecurity::logSecurityEvent('INVALID_RESET_CODE', ['email' => $resetData['email']], 'WARNING');
            } else {
                // Verificar que el token aún es válido
                $checkSql = "SELECT id FROM pending_confirmations WHERE email = ? AND token_confirmacion = ? AND tipo_confirmacion = 'RESET_PASSWORD' AND fecha_expiracion > NOW()";
                $confirmation = AuthDatabase::fetch($checkSql, [$resetData['email'], $resetData['reset_token']]);
                
                if ($confirmation) {
                    // Redirigir al paso de reset
                    header('Location: forgot-password.php?step=reset');
                    exit;
                } else {
                    $error = 'El código de verificación ha expirado. Solicita uno nuevo.';
                    unset($_SESSION['password_reset']);
                }
            }
        }
    } elseif ($action === 'reset_password' && $step === 'reset') {
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        $resetData = $_SESSION['password_reset'] ?? null;
        
        if (!$resetData) {
            header('Location: forgot-password.php');
            exit;
        }
        
        if (!AuthSecurity::validateInput($newPassword, 'password')) {
            $error = 'La contraseña debe tener al menos 8 caracteres';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Las contraseñas no coinciden';
        } else {
            try {
                // Hash de nueva contraseña
                $passwordHash = AuthSecurity::hashPassword($newPassword);
                
                // Actualizar contraseña
                $updateSql = "UPDATE admin_users SET password_hash = ?, fecha_actualizacion = NOW() WHERE email = ?";
                AuthDatabase::update($updateSql, [$passwordHash, $resetData['email']], 'PASSWORD_RESET');
                
                // Marcar confirmación como completada
                $confirmSql = "UPDATE pending_confirmations SET completada = TRUE WHERE email = ? AND token_confirmacion = ? AND tipo_confirmacion = 'RESET_PASSWORD'";
                AuthDatabase::update($confirmSql, [$resetData['email'], $resetData['reset_token']], 'PASSWORD_RESET_COMPLETED');
                
                // Limpiar sesión
                unset($_SESSION['password_reset']);
                
                $success = 'Contraseña actualizada exitosamente. Redirigiendo al login...';
                echo "<script>setTimeout(() => { window.location.href = 'login.php'; }, 2000);</script>";
                
                AuthSecurity::logSecurityEvent('PASSWORD_RESET_COMPLETED', ['email' => $resetData['email']], 'INFO');
            } catch (Exception $e) {
                error_log("Error al resetear contraseña: " . $e->getMessage());
                $error = 'Error interno. Intenta más tarde.';
            }
        }
    }
}

// Redirigir si no hay datos de reset en pasos 2 y 3
if (in_array($step, ['verify', 'reset']) && !isset($_SESSION['password_reset'])) {
    header('Location: forgot-password.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - Telegan Admin</title>
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
                
                <?php if ($step === 'request'): ?>
                    <div class="verification-icon">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M4 4H20C21.1 4 22 4.9 22 6V18C22 19.1 21.1 20 20 20H4C2.9 20 2 19.1 2 18V6C2 4.9 2.9 4 4 4Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M22 6L12 13L2 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <h2>Recuperar contraseña</h2>
                    <p>Ingresa tu email para recibir un código de recuperación</p>
                <?php elseif ($step === 'verify'): ?>
                    <div class="verification-icon">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M9 12L11 14L15 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <h2>Verifica tu código</h2>
                    <p>Hemos enviado un código de verificación a</p>
                    <div class="email-display"><?php echo AuthSecurity::sanitizeOutput($_SESSION['password_reset']['email']); ?></div>
                <?php elseif ($step === 'reset'): ?>
                    <div class="verification-icon">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 1L3 5V11C3 16.55 6.84 21.74 12 23C17.16 21.74 21 16.55 21 11V5L12 1Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M9 12L11 14L15 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <h2>Nueva contraseña</h2>
                    <p>Crea una nueva contraseña segura para tu cuenta</p>
                <?php endif; ?>
            </div>

            <form class="auth-form" method="POST" action="">
                <?php if ($step === 'request'): ?>
                    <input type="hidden" name="action" value="request_reset">
                    
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
                        <label for="email">Correo electrónico</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            value="<?php echo isset($_POST['email']) ? AuthSecurity::sanitizeOutput($_POST['email']) : ''; ?>"
                            required 
                            autocomplete="email"
                            placeholder="tu@email.com"
                        >
                    </div>

                    <button type="submit" class="btn-primary">
                        Enviar código de recuperación
                    </button>

                <?php elseif ($step === 'verify'): ?>
                    <input type="hidden" name="action" value="verify_code">
                    
                    <?php if ($error): ?>
                        <div class="error-message">
                            <?php echo AuthSecurity::sanitizeOutput($error); ?>
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
                        Verificar código
                    </button>

                <?php elseif ($step === 'reset'): ?>
                    <input type="hidden" name="action" value="reset_password">
                    
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
                <?php endif; ?>
            </form>

            <div class="auth-footer">
                <p>¿Recordaste tu contraseña? <a href="login.php">Inicia sesión aquí</a></p>
            </div>
        </div>
    </div>

    <script src="assets/js/auth.js"></script>
    <script>
        // Auto-focus en inputs según el paso
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = ['email', 'verification_code', 'new_password'];
            for (const inputId of inputs) {
                const input = document.getElementById(inputId);
                if (input) {
                    input.focus();
                    break;
                }
            }
            
            // Auto-submit para código de verificación
            const verificationInput = document.getElementById('verification_code');
            if (verificationInput) {
                verificationInput.addEventListener('input', function(e) {
                    if (e.target.value.length === 6) {
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
