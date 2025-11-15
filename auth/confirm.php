<?php
/**
 * Página de Confirmación de Email - Sistema de Autenticación Telegan
 * Flujo moderno como las grandes empresas
 */

// Inicializar sesión
session_start();

require_once 'config/Security.php';
require_once 'config/Database.php';
require_once 'config/Email.php';

AuthSecurity::init();

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: ../public/dashboard.php');
    exit;
}

$requestedEmail = $_SESSION['pending_email'] ?? '';
if (isset($_GET['email'])) {
    $requestedEmail = filter_var(trim($_GET['email']), FILTER_SANITIZE_EMAIL);
}

$error = '';
$success = '';
$pendingRecord = null;
$maxAttempts = 5;
$dbNowRow = AuthDatabase::fetch("SELECT NOW() AS current_time");
$currentDbTimestamp = $dbNowRow ? strtotime($dbNowRow['current_time']) : time();

function fetchPendingRecord($email) {
    if (empty($email)) {
        return null;
    }
    $sql = "SELECT 
                pc.*,
                au.id_admin,
                au.nombre_completo,
                au.token_confirmacion,
                au.expiracion_confirmacion,
                au.activo
            FROM pending_confirmations pc
            INNER JOIN admin_users au ON au.email = pc.email
            WHERE pc.email = ?
              AND pc.tipo_confirmacion = 'REGISTER'
              AND pc.completada = FALSE
            ORDER BY pc.fecha_creacion DESC
            LIMIT 1";
    return AuthDatabase::fetch($sql, [$email]);
}

if (!empty($requestedEmail)) {
    $pendingRecord = fetchPendingRecord($requestedEmail);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $emailInput = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    
    if ($emailInput !== '') {
        $requestedEmail = $emailInput;
        $_SESSION['pending_email'] = $emailInput;
        $pendingRecord = fetchPendingRecord($requestedEmail);
    } else {
        $pendingRecord = null;
    }
    
    if ($action === 'verify_code') {
        $inputCode = trim($_POST['verification_code'] ?? '');
        
        if (empty($requestedEmail) || !$pendingRecord) {
            $error = 'No encontramos una confirmación pendiente para ese email.';
        } elseif (empty($inputCode)) {
            $error = 'Ingresa el código de verificación';
        } elseif (!$pendingRecord['activo']) {
            $error = 'Esta cuenta está desactivada. Contacta al administrador.';
        } elseif ($pendingRecord['intentos'] >= $maxAttempts) {
            $error = 'Demasiados intentos. Solicita un nuevo código.';
        } elseif (strtotime($pendingRecord['fecha_expiracion']) <= $currentDbTimestamp) {
            $error = 'El código ha expirado. Solicita uno nuevo.';
        } elseif ($inputCode !== $pendingRecord['codigo_confirmacion']) {
            $error = 'Código de verificación incorrecto';
            AuthDatabase::update(
                "UPDATE pending_confirmations SET intentos = intentos + 1 WHERE id_confirmacion = ?",
                [$pendingRecord['id_confirmacion']]
            );
            AuthSecurity::logSecurityEvent('INVALID_VERIFICATION_CODE', ['email' => $requestedEmail], 'WARNING');
            $pendingRecord['intentos']++;
        } else {
            try {
                $txStarted = false;
                AuthDatabase::beginTransaction();
                $txStarted = true;
                
                AuthDatabase::update(
                    "UPDATE admin_users 
                     SET activo = TRUE, 
                         email_verificado = TRUE, 
                         codigo_confirmacion = NULL, 
                         token_confirmacion = NULL, 
                         expiracion_confirmacion = NULL 
                     WHERE email = ?",
                    [$requestedEmail],
                    'USER_ACTIVATED'
                );
                
                AuthDatabase::update(
                    "UPDATE pending_confirmations 
                     SET completada = TRUE, fecha_expiracion = NOW() 
                     WHERE id_confirmacion = ?",
                    [$pendingRecord['id_confirmacion']],
                    'CONFIRMATION_COMPLETED'
                );
                
                AuthDatabase::commit();
                
                unset($_SESSION['pending_email']);
                $success = '¡Cuenta activada exitosamente! Redirigiendo al login...';
                AuthSecurity::logSecurityEvent('USER_ACTIVATED', ['email' => $requestedEmail], 'INFO');
                echo "<script>setTimeout(() => { window.location.href = 'login.php'; }, 1800);</script>";
            } catch (Exception $e) {
                if (!empty($txStarted)) {
                    AuthDatabase::rollback();
                }
                error_log("Error en confirmación: " . $e->getMessage());
                $error = 'Error interno. Intenta más tarde.';
            }
        }
    } elseif ($action === 'resend_code') {
        if (empty($requestedEmail)) {
            $error = 'Ingresa un email válido para reenviar el código.';
        } else {
            try {
                $newCode = AuthSecurity::generateConfirmationCode();
                $newToken = AuthSecurity::generateSecureToken(32);
                $expirationTime = date('Y-m-d H:i:s', time() + 3600);
                
                $txStarted = false;
                AuthDatabase::beginTransaction();
                $txStarted = true;
                
                AuthDatabase::update(
                    "UPDATE admin_users 
                     SET codigo_confirmacion = ?, 
                         token_confirmacion = ?, 
                         expiracion_confirmacion = ? 
                     WHERE email = ?",
                    [$newCode, $newToken, $expirationTime, $requestedEmail],
                    'CODE_RESENT_USER'
                );
                
                if ($pendingRecord) {
                    AuthDatabase::update(
                        "UPDATE pending_confirmations 
                         SET codigo_confirmacion = ?, 
                             token_confirmacion = ?, 
                             fecha_expiracion = ?, 
                             intentos = 0 
                         WHERE id_confirmacion = ?",
                        [$newCode, $newToken, $expirationTime, $pendingRecord['id_confirmacion']],
                        'CODE_RESENT_PENDING'
                    );
                } else {
                    AuthDatabase::insert(
                        "INSERT INTO pending_confirmations (email, codigo_confirmacion, token_confirmacion, tipo_confirmacion, fecha_expiracion) 
                         VALUES (?, ?, ?, 'REGISTER', ?)",
                        [$requestedEmail, $newCode, $newToken, $expirationTime],
                        'CODE_RESENT_PENDING_NEW'
                    );
                }
                
                AuthDatabase::commit();
                
                $userData = AuthDatabase::fetch("SELECT nombre_completo FROM admin_users WHERE email = ?", [$requestedEmail]);
                if ($userData) {
                    EmailManager::sendConfirmationEmail($requestedEmail, $userData['nombre_completo'], $newCode, $newToken);
                }
                
                $success = 'Nuevo código enviado a tu email';
                $error = '';
                AuthSecurity::logSecurityEvent('CODE_RESENT', ['email' => $requestedEmail], 'INFO');
                $pendingRecord = fetchPendingRecord($requestedEmail);
            } catch (Exception $e) {
                if (!empty($txStarted)) {
                    AuthDatabase::rollback();
                }
                error_log("Error al reenviar código: " . $e->getMessage());
                $error = 'Error al reenviar código. Intenta más tarde.';
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
                <?php if (!empty($requestedEmail)): ?>
                    <p>Hemos enviado un código de verificación a</p>
                    <div class="email-display"><?php echo AuthSecurity::sanitizeOutput($requestedEmail); ?></div>
                <?php else: ?>
                    <p>Ingresa el correo que registraste para recibir el código</p>
                <?php endif; ?>
            </div>

            <form class="auth-form" method="POST" action="">
                <?php if (!empty($requestedEmail)): ?>
                    <input type="hidden" name="email" value="<?php echo AuthSecurity::sanitizeOutput($requestedEmail); ?>">
                <?php endif; ?>
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

                <?php if (empty($requestedEmail)): ?>
                    <div class="form-group">
                        <label for="email">Correo electrónico</label>
                        <input 
                            type="email"
                            id="email"
                            name="email"
                            required
                            placeholder="tu@email.com"
                            autocomplete="email"
                        >
                        <small>Necesitamos tu email para validar el código.</small>
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
                    <input type="hidden" name="email" value="<?php echo AuthSecurity::sanitizeOutput($requestedEmail); ?>">
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
            if (input && !input.disabled) {
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
