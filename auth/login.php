<?php
/**
 * Página de Login - Sistema de Autenticación Telegan
 */

// Inicializar sesión
session_start();

// Incluir dependencias
require_once 'config/Security.php';
require_once 'config/Database.php';
require_once 'config/Email.php';
require_once '../src/Config/Security.php';
require_once '../src/Services/WhatsAppNotifier.php';

// Inicializar seguridad
Security::init();

// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: ../public/dashboard.php');
    exit;
}

// Verificar mensajes de éxito desde otros procesos
$successMessage = '';
if (isset($_GET['verified']) && $_GET['verified'] == '1') {
    $successMessage = '¡Email verificado exitosamente! Ya puedes iniciar sesión.';
} elseif (isset($_GET['reset']) && $_GET['reset'] == '1') {
    $successMessage = '¡Contraseña actualizada exitosamente! Ya puedes iniciar sesión.';
}

function fetchPendingConfirmation($email) {
    $sql = "SELECT 
                pc.*,
                au.id_admin,
                au.nombre_completo,
                au.expiracion_confirmacion
            FROM pending_confirmations pc
            INNER JOIN admin_users au ON au.email = pc.email
            WHERE pc.email = ?
              AND pc.tipo_confirmacion = 'REGISTER'
              AND pc.completada = FALSE
            ORDER BY pc.fecha_creacion DESC
            LIMIT 1";
    return AuthDatabase::fetch($sql, [$email]);
}

function ensureConfirmationCode($user) {
    $email = $user['email'];
    $record = fetchPendingConfirmation($email);
    $needsNewCode = !$record || strtotime($record['fecha_expiracion']) <= time();
    
    if ($needsNewCode) {
        try {
            $code = AuthSecurity::generateConfirmationCode();
            $token = AuthSecurity::generateSecureToken(32);
            $expiration = date('Y-m-d H:i:s', time() + 3600);
            $txStarted = false;
            
            AuthDatabase::beginTransaction();
            $txStarted = true;
            AuthDatabase::update(
                "UPDATE admin_users 
                 SET codigo_confirmacion = ?, token_confirmacion = ?, expiracion_confirmacion = ?, activo = TRUE
                 WHERE id_admin = ?",
                [$code, $token, $expiration, $user['id_admin']],
                'CODE_REFRESH_USER'
            );
            
            if ($record) {
                AuthDatabase::update(
                    "UPDATE pending_confirmations 
                     SET codigo_confirmacion = ?, token_confirmacion = ?, fecha_expiracion = ?, intentos = 0
                     WHERE id_confirmacion = ?",
                    [$code, $token, $expiration, $record['id_confirmacion']],
                    'CODE_REFRESH_PENDING'
                );
            } else {
                AuthDatabase::insert(
                    "INSERT INTO pending_confirmations (email, codigo_confirmacion, token_confirmacion, tipo_confirmacion, fecha_expiracion) 
                     VALUES (?, ?, ?, 'REGISTER', ?)",
                    [$email, $code, $token, $expiration],
                    'CODE_REFRESH_PENDING_NEW'
                );
            }
            AuthDatabase::commit();
            
            EmailManager::sendConfirmationEmail($email, $user['nombre_completo'], $code, $token);
            $record = fetchPendingConfirmation($email);
        } catch (Exception $e) {
            if (!empty($txStarted)) {
                AuthDatabase::rollback();
            }
            error_log('Error generando PIN: ' . $e->getMessage());
        }
    }
    
    return $record;
}

$showPinStep = false;
$pinEmail = '';
$prefillEmail = '';
$loginStep = $_POST['login_step'] ?? 'credentials';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($loginStep === 'pin') {
        $pinEmail = filter_var(trim($_POST['pin_email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $pinCode = trim($_POST['pin_code'] ?? '');
        
        if (empty($pinEmail) || !AuthSecurity::validateInput($pinEmail, 'email')) {
            $error = 'Email inválido para verificar PIN';
            $showPinStep = true;
        } elseif (!preg_match('/^[0-9]{6}$/', $pinCode)) {
            $error = 'Ingresa el PIN de 6 dígitos';
            $showPinStep = true;
        } else {
            $pending = fetchPendingConfirmation($pinEmail);
            if (!$pending) {
                $error = 'No encontramos un PIN pendiente para este correo. Regístrate nuevamente.';
                $showPinStep = true;
            } elseif (strtotime($pending['fecha_expiracion']) <= time()) {
                $error = 'El PIN expiró. Solicita uno nuevo desde confirmación.';
                $showPinStep = true;
            } elseif ($pending['intentos'] >= 5) {
                $error = 'Demasiados intentos fallidos. Reenvía un nuevo PIN.';
                $showPinStep = true;
            } elseif ($pending['codigo_confirmacion'] !== $pinCode) {
                $error = 'PIN incorrecto';
                AuthDatabase::update(
                    "UPDATE pending_confirmations SET intentos = intentos + 1 WHERE id_confirmacion = ?",
                    [$pending['id_confirmacion']]
                );
                $showPinStep = true;
            } else {
                try {
                    AuthDatabase::beginTransaction();
                    AuthDatabase::update(
                        "UPDATE admin_users SET email_verificado = TRUE, codigo_confirmacion = NULL, token_confirmacion = NULL, expiracion_confirmacion = NULL WHERE id_admin = ?",
                        [$pending['id_admin']],
                        'PIN_VERIFIED'
                    );
                    AuthDatabase::update(
                        "UPDATE pending_confirmations SET completada = TRUE WHERE id_confirmacion = ?",
                        [$pending['id_confirmacion']],
                        'PIN_VERIFIED_PENDING'
                    );
                    AuthDatabase::commit();
                    
                    unset($_SESSION['pending_email']);
                    $successMessage = 'PIN verificado. Ahora ingresa tu contraseña para continuar.';
                    $prefillEmail = $pinEmail;
                    $showPinStep = false;
                } catch (Exception $e) {
                    AuthDatabase::rollback();
                    error_log('Error verificando PIN: ' . $e->getMessage());
                    $error = 'No pudimos validar el PIN. Intenta nuevamente.';
                    $showPinStep = true;
                }
            }
        }
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $prefillEmail = $email;
        
        if (!AuthSecurity::validateInput($email, 'email')) {
            $error = 'Email inválido';
        } elseif (!AuthSecurity::validateInput($password, 'password')) {
            $error = 'Contraseña inválida';
        } else {
            try {
                if (AuthSecurity::isUserBlocked($email)) {
                    $error = 'Cuenta temporalmente bloqueada. Intenta más tarde.';
                    AuthSecurity::logSecurityEvent('BLOCKED_LOGIN_ATTEMPT', ['email' => $email], 'WARNING');
                } else {
                    $sql = "SELECT id_admin, nombre_completo, email, password_hash, rol, activo, email_verificado 
                            FROM admin_users 
                            WHERE email = ?";
                    
                    $user = AuthDatabase::fetch($sql, [$email], 'LOGIN_ATTEMPT');
                    
                    if ($user && !$user['activo']) {
                        $error = 'Tu cuenta está desactivada. Contacta al administrador.';
                    } elseif ($user && AuthSecurity::verifyPassword($password, $user['password_hash'])) {
                        if (!$user['email_verificado']) {
                            $_SESSION['pending_email'] = $email;
                            ensureConfirmationCode($user);
                            $showPinStep = true;
                            $pinEmail = $email;
                            $successMessage = 'Antes de continuar valida el PIN que enviamos a tu correo.';
                            AuthSecurity::logSecurityEvent('UNVERIFIED_LOGIN_ATTEMPT', ['email' => $email], 'WARNING');
                        } else {
                            $sessionToken = AuthSecurity::generateSessionToken();
                            
                            $sessionSql = "INSERT INTO admin_sessions (id_admin, token_sesion, ip_address, user_agent, fecha_expiracion) 
                                          VALUES (?, ?, ?, ?, ?)";
                            $sessionParams = [
                                $user['id_admin'],
                                $sessionToken,
                                AuthSecurity::getClientIP(),
                                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                                date('Y-m-d H:i:s', time() + 86400)
                            ];
                            
                            AuthDatabase::insert($sessionSql, $sessionParams, 'SESSION_CREATED');
                            
                            $updateSql = "UPDATE admin_users SET ultima_sesion = NOW(), intentos_login = 0 WHERE id_admin = ?";
                            AuthDatabase::update($updateSql, [$user['id_admin']], 'LAST_SESSION_UPDATE');
                            
                            $_SESSION['admin_logged_in'] = true;
                            $_SESSION['admin_id'] = $user['id_admin'];
                            $_SESSION['admin_nombre'] = $user['nombre_completo'];
                        $_SESSION['admin_email'] = $user['email'];
                            $_SESSION['admin_role'] = $user['rol'];
                        $_SESSION['admin_session_token'] = $sessionToken;
                            $_SESSION['session_token'] = bin2hex(random_bytes(16));
                            $_SESSION['session_timestamp'] = time();
                            $_SESSION['session_valid'] = true;
                            
                            AuthSecurity::logSecurityEvent('LOGIN_SUCCESS', [
                                'email' => $email,
                                'user_id' => $user['id_admin'],
                                'role' => $user['rol']
                            ], 'INFO');
                            
                            $loginMessage = sprintf(
                                '<p><strong>Nombre:</strong> %s</p>
                                <p><strong>Email:</strong> %s</p>
                                <p><strong>Rol:</strong> %s</p>
                                <p><strong>Fecha:</strong> %s</p>
                                <p><strong>IP:</strong> %s</p>',
                                htmlspecialchars($user['nombre_completo'] ?? '—', ENT_QUOTES, 'UTF-8'),
                                htmlspecialchars($user['email'] ?? '—', ENT_QUOTES, 'UTF-8'),
                                htmlspecialchars($user['rol'] ?? '—', ENT_QUOTES, 'UTF-8'),
                                date('Y-m-d H:i:s'),
                                AuthSecurity::getClientIP()
                            );
                            EmailManager::sendAdminNotification(
                                'Nuevo inicio de sesión en Telegan Admin',
                                $loginMessage
                            );
                            
                            try {
                                $notifier = new WhatsAppNotifier();
                                $message = sprintf(
                                    "Inicio de sesión: %s (%s) a las %s",
                                    $user['nombre_completo'] ?? 'Usuario',
                                    $user['email'] ?? '',
                                    date('Y-m-d H:i:s')
                                );
                                $notifier->sendLoginAlert($message);
                            } catch (Throwable $notifyError) {
                                error_log('WhatsAppNotifier login error: ' . $notifyError->getMessage());
                            }
                            
                            header('Location: ../public/dashboard.php');
                            exit;
                        }
                    } else {
                        if ($user) {
                            AuthDatabase::update("UPDATE admin_users SET intentos_login = intentos_login + 1 WHERE email = ?", [$email], 'FAILED_LOGIN');
                            
                            $userData = AuthDatabase::fetch("SELECT intentos_login FROM admin_users WHERE email = ?", [$email]);
                            if ($userData && $userData['intentos_login'] >= 5) {
                                AuthSecurity::blockUser($email, 15);
                                $error = 'Demasiados intentos fallidos. Cuenta bloqueada por 15 minutos.';
                            } else {
                                $error = 'Credenciales incorrectas';
                            }
                        } else {
                            $error = 'Credenciales incorrectas';
                        }
                        
                        AuthSecurity::logSecurityEvent('LOGIN_FAILED', ['email' => $email], 'WARNING');
                        AuthSecurity::incrementRateLimit();
                    }
                }
            } catch (Exception $e) {
                error_log("Error en login: " . $e->getMessage());
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
    <title>Iniciar Sesión - Telegan Admin</title>
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
                <h2>Bienvenido</h2>
                <p>Ingresa a tu cuenta de Dashboard Telegan</p>
            </div>

            <?php if ($showPinStep): ?>
                <form class="auth-form" method="POST" action="">
                    <input type="hidden" name="login_step" value="pin">
                    <input type="hidden" name="pin_email" value="<?php echo AuthSecurity::sanitizeOutput($pinEmail); ?>">

                    <?php if (isset($error)): ?>
                        <div class="error-message">
                            <?php echo AuthSecurity::sanitizeOutput($error); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($successMessage): ?>
                        <div class="success-message">
                            <?php echo AuthSecurity::sanitizeOutput($successMessage); ?>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Correo a verificar</label>
                        <div class="input" style="background: var(--bg-secondary); opacity:0.9;">
                            <?php echo AuthSecurity::sanitizeOutput($pinEmail); ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="pin_code">PIN de verificación</label>
                        <input 
                            type="text"
                            id="pin_code"
                            name="pin_code"
                            maxlength="6"
                            pattern="[0-9]{6}"
                            required
                            placeholder="000000"
                            autocomplete="one-time-code"
                        >
                        <small>Ingresa el PIN que enviamos a tu correo.</small>
                    </div>

                    <div class="form-actions" style="justify-content: space-between; flex-wrap: wrap;">
                        <a href="confirm.php" class="forgot-password">¿No recibiste el PIN? Reenvía aquí.</a>
                        <a href="register.php" class="forgot-password">Registrar otro email</a>
                    </div>

                    <button type="submit" class="btn-primary">
                        Validar PIN
                    </button>
                </form>
            <?php else: ?>
                <form class="auth-form" method="POST" action="">
                    <input type="hidden" name="login_step" value="credentials">

                    <?php if (isset($error)): ?>
                        <div class="error-message">
                            <?php echo AuthSecurity::sanitizeOutput($error); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($successMessage): ?>
                        <div class="success-message">
                            <?php echo AuthSecurity::sanitizeOutput($successMessage); ?>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="email">Correo electrónico</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            value="<?php echo AuthSecurity::sanitizeOutput($prefillEmail !== '' ? $prefillEmail : (isset($_POST['email']) ? $_POST['email'] : '')); ?>"
                            required 
                            autocomplete="email"
                            placeholder="tu@email.com"
                        >
                    </div>

                    <div class="form-group">
                        <label for="password">Contraseña</label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            required 
                            autocomplete="current-password"
                            placeholder="••••••••"
                        >
                    </div>

                    <div class="form-actions">
                        <a href="forgot-password.php" class="forgot-password">¿Olvidaste tu contraseña?</a>
                    </div>

                    <button type="submit" class="btn-primary">
                        Iniciar sesión
                    </button>
                </form>
            <?php endif; ?>

            <div class="auth-footer">
                <p>¿No tienes una cuenta? <a href="register.php">Regístrate aquí</a></p>
            </div>
        </div>
    </div>

    <script src="assets/js/auth.js"></script>
</body>
</html>
