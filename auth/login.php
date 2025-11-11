<?php
/**
 * Página de Login - Sistema de Autenticación Telegan
 */

// Inicializar sesión
session_start();

// Incluir dependencias
require_once 'config/Security.php';
require_once 'config/Database.php';
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

// Procesar login si es POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validar inputs
    if (!AuthSecurity::validateInput($email, 'email')) {
        $error = 'Email inválido';
    } elseif (!AuthSecurity::validateInput($password, 'password')) {
        $error = 'Contraseña inválida';
    } else {
        try {
            // Verificar si el usuario está bloqueado
            if (AuthSecurity::isUserBlocked($email)) {
                $error = 'Cuenta temporalmente bloqueada. Intenta más tarde.';
                AuthSecurity::logSecurityEvent('BLOCKED_LOGIN_ATTEMPT', ['email' => $email], 'WARNING');
            } else {
                // Buscar usuario
                $sql = "SELECT id_admin, nombre_completo, email, password_hash, rol, activo, email_verificado 
                        FROM admin_users 
                        WHERE email = ? AND activo = TRUE";
                
                $user = AuthDatabase::fetch($sql, [$email], 'LOGIN_ATTEMPT');
                
                if ($user && AuthSecurity::verifyPassword($password, $user['password_hash'])) {
                    if (!$user['email_verificado']) {
                        $error = 'Debes verificar tu email antes de iniciar sesión';
                        AuthSecurity::logSecurityEvent('UNVERIFIED_LOGIN_ATTEMPT', ['email' => $email], 'WARNING');
                    } else {
                        // Login exitoso
                        $sessionToken = AuthSecurity::generateSessionToken();
                        
                        // Crear sesión en BD
                        $sessionSql = "INSERT INTO admin_sessions (id_admin, token_sesion, ip_address, user_agent, fecha_expiracion) 
                                      VALUES (?, ?, ?, ?, ?)";
                        $sessionParams = [
                            $user['id_admin'],
                            $sessionToken,
                            AuthSecurity::getClientIP(),
                            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                            date('Y-m-d H:i:s', time() + 86400) // 24 horas
                        ];
                        
                        AuthDatabase::insert($sessionSql, $sessionParams, 'SESSION_CREATED');
                        
                        // Actualizar última sesión
                        $updateSql = "UPDATE admin_users SET ultima_sesion = NOW(), intentos_login = 0 WHERE id_admin = ?";
                        AuthDatabase::update($updateSql, [$user['id_admin']], 'LAST_SESSION_UPDATE');
                        
                        // Crear sesión PHP
                        $_SESSION['admin_logged_in'] = true;
                        $_SESSION['admin_id'] = $user['id_admin'];
                        $_SESSION['admin_nombre'] = $user['nombre_completo'];
                        $_SESSION['admin_email'] = $user['email'];
                        $_SESSION['admin_role'] = $user['rol'];
                        $_SESSION['session_token'] = bin2hex(random_bytes(16));
                        $_SESSION['session_timestamp'] = time(); // Timestamp para validar expiración
                        $_SESSION['session_valid'] = true; // Marcar sesión como válida
                        
                        // Log de login exitoso
                        AuthSecurity::logSecurityEvent('LOGIN_SUCCESS', [
                            'email' => $email,
                            'user_id' => $user['id_admin'],
                            'role' => $user['rol']
                        ], 'INFO');
                        
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
                        
                        // Redirigir al dashboard
                        header('Location: ../public/dashboard.php');
                        exit;
                    }
                } else {
                    // Login fallido - agregar debugging
                    error_log("Login fallido para: $email");
                    error_log("Usuario encontrado: " . ($user ? 'SÍ' : 'NO'));
                    
                    if ($user) {
                        error_log("Hash en BD: " . substr($user['password_hash'], 0, 50) . '...');
                        error_log("Password verificado: " . (AuthSecurity::verifyPassword($password, $user['password_hash']) ? 'SÍ' : 'NO'));
                    }
                    
                    $error = 'Credenciales incorrectas';
                    
                    // Incrementar intentos de login solo si el usuario existe
                    if ($user) {
                        $updateSql = "UPDATE admin_users SET intentos_login = intentos_login + 1 WHERE email = ?";
                        AuthDatabase::update($updateSql, [$email], 'FAILED_LOGIN');
                        
                        // Bloquear si hay muchos intentos
                        $userSql = "SELECT intentos_login FROM admin_users WHERE email = ?";
                        $userData = AuthDatabase::fetch($userSql, [$email]);
                        
                        if ($userData && $userData['intentos_login'] >= 5) {
                            AuthSecurity::blockUser($email, 15);
                            $error = 'Demasiados intentos fallidos. Cuenta bloqueada por 15 minutos.';
                        }
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

            <form class="auth-form" method="POST" action="">
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
                        value="<?php echo isset($_POST['email']) ? AuthSecurity::sanitizeOutput($_POST['email']) : ''; ?>"
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

            <div class="auth-footer">
                <p>¿No tienes una cuenta? <a href="register.php">Regístrate aquí</a></p>
            </div>
        </div>
    </div>

    <script src="assets/js/auth.js"></script>
</body>
</html>
