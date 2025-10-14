<?php
/**
 * Verificación Simple de Email - Sin dependencias complejas
 */

// Configuración básica
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Headers básicos
header('Content-Type: text/html; charset=UTF-8');

$error = '';
$success = '';
$email = '';
$token = $_GET['token'] ?? '';

echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Verificación de Email - Telegan Admin</title>
    <style>
        body { 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; 
            background: #f8fafc;
            margin: 0;
            padding: 20px;
            color: #1e293b;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .success { background: #dcfce7; color: #166534; border: 1px solid #22c55e; padding: 15px; border-radius: 8px; margin: 20px 0; }
        .error { background: #fef2f2; color: #dc2626; border: 1px solid #ef4444; padding: 15px; border-radius: 8px; margin: 20px 0; }
        .btn { background: #6dbe45; color: white; padding: 12px 24px; border: none; border-radius: 8px; text-decoration: none; display: inline-block; font-weight: 500; }
        .btn:hover { background: #5aa835; }
    </style>
</head>
<body>";

echo "<div class='container'>";

if (!$token) {
    $error = 'Token de verificación no válido.';
} else {
    try {
        // Conexión simple a la base de datos
        $host = '157.245.241.220';
        $port = '5432';
        $dbname = 'telegan';
        $user = 'telegan';
        $password = 'telegan';
        
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
        $pdo = new PDO($dsn, $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Verificar token
        $stmt = $pdo->prepare("SELECT id_admin, email, nombre_completo FROM admin_users WHERE token_confirmacion = ? AND expiracion_confirmacion > NOW() AND activo = FALSE");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Activar usuario
            $stmt = $pdo->prepare("UPDATE admin_users SET activo = TRUE, email_verificado = TRUE, codigo_confirmacion = NULL, token_confirmacion = NULL, expiracion_confirmacion = NULL WHERE id_admin = ?");
            $stmt->execute([$user['id_admin']]);
            
            $success = '¡Cuenta activada exitosamente!';
            $email = $user['email'];
            
            echo "<h1>✅ Email Verificado</h1>";
            echo "<div class='success'>";
            echo "<h2>¡Felicitaciones!</h2>";
            echo "<p>Tu cuenta ha sido activada exitosamente.</p>";
            echo "<p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>";
            echo "<p><strong>Usuario:</strong> " . htmlspecialchars($user['nombre_completo']) . "</p>";
            echo "</div>";
            
            echo "<div style='text-align: center; margin: 30px 0;'>";
            echo "<a href='login.php' class='btn'>Ir al Login</a>";
            echo "</div>";
            
            // Redirigir automáticamente después de 5 segundos
            echo "<script>
                setTimeout(function() {
                    window.location.href = 'login.php';
                }, 5000);
            </script>";
            
        } else {
            $error = 'El token de verificación es inválido o ha expirado.';
        }
        
    } catch (Exception $e) {
        $error = 'Error interno: ' . $e->getMessage();
        error_log("Error en verify-email-simple.php: " . $e->getMessage());
    }
}

if ($error) {
    echo "<h1>❌ Error de Verificación</h1>";
    echo "<div class='error'>";
    echo "<h2>No se pudo verificar tu email</h2>";
    echo "<p>" . htmlspecialchars($error) . "</p>";
    echo "</div>";
    
    echo "<div style='text-align: center; margin: 30px 0;'>";
    echo "<a href='register.php' class='btn'>Registrar Nueva Cuenta</a>";
    echo "<a href='login.php' class='btn' style='background: #64748b; margin-left: 10px;'>Ir al Login</a>";
    echo "</div>";
}

echo "</div>";
echo "</body></html>";
?>
