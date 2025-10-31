<?php
/**
 * Activador Manual de Usuario - Para activar cuentas existentes
 */

// Incluir dependencias
require_once 'config/Database.php';
require_once 'config/Security.php';

// Inicializar seguridad
AuthSecurity::init();

echo "<h1>🔧 Activador Manual de Usuario</h1>";

// Verificar si se proporcionó un token
$token = $_GET['token'] ?? '';

if ($token) {
    try {
        // Buscar usuario por token de confirmación
        $sql = "SELECT id_admin, nombre_completo, email FROM admin_users WHERE token_confirmacion = ?";
        $user = AuthDatabase::fetch($sql, [$token]);
        
        if ($user) {
            // Activar usuario
            $updateSql = "UPDATE admin_users SET 
                         activo = true, 
                         email_verificado = true, 
                         codigo_confirmacion = NULL, 
                         token_confirmacion = NULL,
                         expiracion_confirmacion = NULL,
                         rol = 'TECNICO'
                         WHERE id_admin = ?";
            
            AuthDatabase::update($updateSql, [$user['id_admin']]);
            
            echo "<div style='background: #dcfce7; padding: 20px; border-radius: 8px; border: 1px solid #22c55e; margin: 20px 0;'>";
            echo "<h2 style='color: #22c55e; margin: 0 0 10px 0;'>✅ Usuario Activado Exitosamente</h2>";
            echo "<p><strong>Nombre:</strong> " . htmlspecialchars($user['nombre_completo']) . "</p>";
            echo "<p><strong>Email:</strong> " . htmlspecialchars($user['email']) . "</p>";
            echo "<p><strong>Estado:</strong> Activo y verificado</p>";
            echo "<p><strong>Rol:</strong> Técnico</p>";
            echo "</div>";
            
            echo "<div style='background: #dbeafe; padding: 15px; border-radius: 8px; border: 1px solid #3b82f6; margin: 20px 0;'>";
            echo "<p><strong>🎯 Ahora puedes:</strong></p>";
            echo "<ul>";
            echo "<li><a href='login.php' style='color: #3b82f6; font-weight: bold;'>Iniciar sesión con tu email y contraseña</a></li>";
            echo "<li>Acceder al dashboard administrativo</li>";
            echo "<li>Gestionar fincas y usuarios</li>";
            echo "</ul>";
            echo "</div>";
            
            // Log de activación manual
            AuthSecurity::logSecurityEvent('USER_MANUALLY_ACTIVATED', [
                'email' => $user['email'],
                'user_id' => $user['id_admin'],
                'token_used' => $token
            ], 'INFO');
            
        } else {
            echo "<div style='background: #fef2f2; padding: 20px; border-radius: 8px; border: 1px solid #ef4444; margin: 20px 0;'>";
            echo "<h2 style='color: #ef4444; margin: 0 0 10px 0;'>❌ Token No Válido</h2>";
            echo "<p>El token proporcionado no corresponde a ningún usuario pendiente de activación.</p>";
            echo "</div>";
        }
        
    } catch (Exception $e) {
        echo "<div style='background: #fef2f2; padding: 20px; border-radius: 8px; border: 1px solid #ef4444; margin: 20px 0;'>";
        echo "<h2 style='color: #ef4444; margin: 0 0 10px 0;'>❌ Error</h2>";
        echo "<p>Error al activar usuario: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "</div>";
    }
    
} else {
    echo "<div style='background: #fef3c7; padding: 20px; border-radius: 8px; border: 1px solid #f59e0b; margin: 20px 0;'>";
    echo "<h2 style='color: #f59e0b; margin: 0 0 10px 0;'>⚠️ Token Requerido</h2>";
    echo "<p>Para activar un usuario, proporciona el token en la URL:</p>";
    echo "<code style='background: #f3f4f6; padding: 5px; border-radius: 4px;'>activate-user.php?token=TU_TOKEN_AQUI</code>";
    echo "</div>";
}

// Mostrar usuarios pendientes de activación
echo "<h2>👥 Usuarios Pendientes de Activación</h2>";

try {
    $pendingSql = "SELECT id_admin, nombre_completo, email, fecha_registro FROM admin_users WHERE activo = false OR email_verificado = false";
    $pendingUsers = AuthDatabase::fetchAll($pendingSql);
    
    if ($pendingUsers) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
        echo "<tr style='background: #f3f4f6;'>";
        echo "<th style='padding: 10px;'>ID</th>";
        echo "<th style='padding: 10px;'>Nombre</th>";
        echo "<th style='padding: 10px;'>Email</th>";
        echo "<th style='padding: 10px;'>Fecha Registro</th>";
        echo "<th style='padding: 10px;'>Acción</th>";
        echo "</tr>";
        
        foreach ($pendingUsers as $user) {
            echo "<tr>";
            echo "<td style='padding: 10px;'>" . $user['id_admin'] . "</td>";
            echo "<td style='padding: 10px;'>" . htmlspecialchars($user['nombre_completo']) . "</td>";
            echo "<td style='padding: 10px;'>" . htmlspecialchars($user['email']) . "</td>";
            echo "<td style='padding: 10px;'>" . $user['fecha_registro'] . "</td>";
            echo "<td style='padding: 10px;'>";
            echo "<button onclick=\"activateUser(" . $user['id_admin'] . ")\" style='background: #22c55e; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;'>Activar</button>";
            echo "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        
        echo "<script>";
        echo "function activateUser(userId) {";
        echo "  if (confirm('¿Estás seguro de activar este usuario?')) {";
        echo "    window.location.href = 'activate-user.php?user_id=' + userId;";
        echo "  }";
        echo "}";
        echo "</script>";
        
    } else {
        echo "<p style='color: #22c55e;'>✅ No hay usuarios pendientes de activación</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: #ef4444;'>Error al obtener usuarios pendientes: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Manejar activación por ID de usuario
$userId = $_GET['user_id'] ?? '';
if ($userId) {
    try {
        $sql = "SELECT id_admin, nombre_completo, email FROM admin_users WHERE id_admin = ?";
        $user = AuthDatabase::fetch($sql, [$userId]);
        
        if ($user) {
            $updateSql = "UPDATE admin_users SET 
                         activo = true, 
                         email_verificado = true, 
                         rol = 'TECNICO'
                         WHERE id_admin = ?";
            
            AuthDatabase::update($updateSql, [$userId]);
            
            echo "<script>";
            echo "alert('Usuario " . htmlspecialchars($user['nombre_completo']) . " activado exitosamente!');";
            echo "window.location.href = 'activate-user.php';";
            echo "</script>";
        }
        
    } catch (Exception $e) {
        echo "<script>";
        echo "alert('Error al activar usuario: " . htmlspecialchars($e->getMessage()) . "');";
        echo "</script>";
    }
}

echo "<hr>";
echo "<div style='text-align: center; margin: 20px 0;'>";
echo "<a href='login.php' style='background: #3b82f6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; margin: 0 10px;'>🔑 Ir al Login</a>";
echo "<a href='register.php' style='background: #22c55e; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; margin: 0 10px;'>👤 Crear Nueva Cuenta</a>";
echo "</div>";

echo "<p><small>Script ejecutado el " . date('Y-m-d H:i:s') . "</small></p>";
?>








