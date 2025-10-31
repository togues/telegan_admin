<?php
/**
 * Página de Registro - Sistema de Autenticación Telegan
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
    header('Location: ../public/dashboard.php');
    exit;
}

// Procesar registro si es POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $telefono = trim($_POST['telefono'] ?? '');
    $aceptaTerminos = isset($_POST['acepta_terminos']);
    
    $errors = [];
    
    // Validar inputs
    if (!AuthSecurity::validateInput($nombre, 'string', 255)) {
        $errors[] = 'Nombre completo inválido';
    }
    
    if (!AuthSecurity::validateInput($email, 'email')) {
        $errors[] = 'Email inválido';
    }
    
    if (!AuthSecurity::validateInput($password, 'password')) {
        $errors[] = 'La contraseña debe tener al menos 8 caracteres';
    }
    
    if ($password !== $confirmPassword) {
        $errors[] = 'Las contraseñas no coinciden';
    }
    
    if (!empty($telefono) && !AuthSecurity::validateInput($telefono, 'phone')) {
        $errors[] = 'Teléfono inválido';
    }
    
    if (!$aceptaTerminos) {
        $errors[] = 'Debes aceptar los términos y condiciones';
    }
    
    if (empty($errors)) {
        try {
            // Verificar si el email ya existe
            $checkSql = "SELECT id_admin FROM admin_users WHERE email = ?";
            $existingUser = AuthDatabase::fetch($checkSql, [$email]);
            
            if ($existingUser) {
                $errors[] = 'Este email ya está registrado';
            } else {
                // Generar código de confirmación
                $confirmationCode = AuthSecurity::generateConfirmationCode();
                $confirmationToken = AuthSecurity::generateSecureToken();
                $expirationTime = date('Y-m-d H:i:s', time() + 3600); // 1 hora
                
                // Hash de contraseña
                $passwordHash = AuthSecurity::hashPassword($password);
                
                // Iniciar transacción
                AuthDatabase::beginTransaction();
                
                try {
                    // Insertar usuario ACTIVADO AUTOMÁTICAMENTE (sin validación de email)
                    $userSql = "INSERT INTO admin_users (nombre_completo, email, password_hash, telefono, activo, email_verificado, rol) 
                               VALUES (?, ?, ?, ?, true, true, 'TECNICO')";
                    $userParams = [
                        $nombre,
                        $email,
                        $passwordHash,
                        $telefono ?: null
                    ];
                    
                    $userId = AuthDatabase::insert($userSql, $userParams, 'USER_REGISTERED');
                    
                    // Confirmar transacción
                    AuthDatabase::commit();
                    
                    // Log de registro exitoso
                    AuthSecurity::logSecurityEvent('USER_REGISTERED_AUTO_ACTIVATED', [
                        'email' => $email,
                        'user_id' => $userId,
                        'auto_activated' => true
                    ], 'INFO');
                    
                    // Crear sesión automáticamente
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_user_id'] = $userId;
                    $_SESSION['admin_email'] = $email;
                    $_SESSION['admin_nombre'] = $nombre;
                    $_SESSION['admin_rol'] = 'TECNICO';
                    
                    // Redirigir directamente al dashboard
                    header('Location: ../public/dashboard.php');
                    exit;
                    
                } catch (Exception $e) {
                    AuthDatabase::rollback();
                    throw $e;
                }
            }
        } catch (Exception $e) {
            error_log("Error en registro: " . $e->getMessage());
            $errors[] = 'Error interno. Intenta más tarde.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Cuenta - Telegan Admin</title>
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
                <h2>Únete a Dashboard Telegan</h2>
                <p>Crea tu cuenta para acceder al panel administrativo</p>
            </div>

            <form class="auth-form" method="POST" action="">
                <?php if (!empty($errors)): ?>
                    <div class="error-message">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo AuthSecurity::sanitizeOutput($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="nombre">Nombre completo</label>
                    <input 
                        type="text" 
                        id="nombre" 
                        name="nombre" 
                        value="<?php echo isset($_POST['nombre']) ? AuthSecurity::sanitizeOutput($_POST['nombre']) : ''; ?>"
                        required 
                        autocomplete="name"
                        placeholder="Juan Pérez"
                    >
                </div>

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
                    <label for="telefono">Teléfono (opcional)</label>
                    <input 
                        type="tel" 
                        id="telefono" 
                        name="telefono" 
                        value="<?php echo isset($_POST['telefono']) ? AuthSecurity::sanitizeOutput($_POST['telefono']) : ''; ?>"
                        autocomplete="tel"
                        placeholder="+1 234 567 8900"
                    >
                </div>

                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required 
                        autocomplete="new-password"
                        placeholder="••••••••"
                        minlength="8"
                    >
                    <small>Mínimo 8 caracteres</small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirmar contraseña</label>
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        required 
                        autocomplete="new-password"
                        placeholder="••••••••"
                    >
                </div>

                <div class="form-group checkbox-group">
                    <label class="checkbox-label">
                        <input 
                            type="checkbox" 
                            name="acepta_terminos" 
                            required
                            <?php echo isset($_POST['acepta_terminos']) ? 'checked' : ''; ?>
                        >
                        <span class="checkmark"></span>
                        Acepto los <a href="#" target="_blank">términos y condiciones</a> y la <a href="#" target="_blank">política de privacidad</a>
                    </label>
                </div>

                <button type="submit" class="btn-primary">
                    Crear cuenta
                </button>
            </form>

            <div class="auth-footer">
                <p>¿Ya tienes una cuenta? <a href="login.php">Inicia sesión aquí</a></p>
            </div>
        </div>
    </div>

    <script src="assets/js/auth.js"></script>
</body>
</html>
