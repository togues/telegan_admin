<?php

/**
 * Clase para envío de emails - SISTEMA DE AUTENTICACIÓN TELEGAN
 * 
 * Usa la función mail() nativa de PHP con templates HTML modernos
 */
class EmailManager
{
    private static $config = null;
    
    /**
     * Inicializar configuración
     */
    private static function loadConfig()
    {
        if (self::$config !== null) {
            return;
        }

        // Cargar configuración del entorno
        require_once __DIR__ . '/Environment.php';
        $envConfig = EnvironmentConfig::getConfig();
        
        $envFile = __DIR__ . '/../../env';
        
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                    list($key, $value) = explode('=', $line, 2);
                    self::$config[trim($key)] = trim($value);
                }
            }
        }
        
        // Configuración por defecto con detección automática de entorno
        self::$config = array_merge([
            'MAIL_FROM_NAME' => 'Telegan Admin',
            'MAIL_FROM_EMAIL' => 'noreply@telegan.com',
            'MAIL_REPLY_TO' => 'support@telegan.com',
            'APP_NAME' => 'Telegan Admin Panel',
            'APP_URL' => $envConfig['base_url'] // URL automática basada en el entorno
        ], self::$config ?? []);
    }
    
    /**
     * Enviar email de confirmación de registro
     */
    public static function sendConfirmationEmail($email, $name, $confirmationCode, $confirmationToken = null)
    {
        $subject = 'Confirma tu cuenta - ' . self::$config['APP_NAME'];
        
        // Generar link de verificación automática
        $verificationLink = '';
        if ($confirmationToken) {
            // Obtener URL base de la configuración del entorno
            $baseUrl = self::$config['APP_URL'] ?? 'http://localhost/TELEGAN_ADMIN';
            
            // Verificar que la URL base esté completa
            if (empty($baseUrl) || $baseUrl === 'http:///' || strpos($baseUrl, 'http:///') === 0) {
                // Fallback: detectar automáticamente el entorno
                require_once __DIR__ . '/Environment.php';
                $envConfig = EnvironmentConfig::getConfig();
                $baseUrl = $envConfig['base_url'];
            }
            
            $verificationLink = rtrim($baseUrl, '/') . '/auth/verify-email.php?token=' . urlencode($confirmationToken);
            
            // Log para debugging
            error_log("Email verification link generated: " . $verificationLink);
        }
        
        $template = self::getEmailTemplate('confirmation', [
            'name' => $name,
            'confirmation_code' => $confirmationCode,
            'verification_link' => $verificationLink,
            'app_name' => self::$config['APP_NAME'],
            'app_url' => self::$config['APP_URL']
        ]);
        
        return self::sendEmail($email, $subject, $template);
    }
    
    /**
     * Enviar email de recuperación de contraseña
     */
    public static function sendPasswordResetEmail($email, $name, $resetCode, $resetToken = null)
    {
        $subject = 'Recupera tu contraseña - ' . self::$config['APP_NAME'];
        
        // Generar link de reset automático
        $resetLink = '';
        if ($resetToken) {
            // Obtener URL base de la configuración del entorno
            $baseUrl = self::$config['APP_URL'] ?? 'http://localhost/TELEGAN_ADMIN';
            
            // Verificar que la URL base esté completa
            if (empty($baseUrl) || $baseUrl === 'http:///' || strpos($baseUrl, 'http:///') === 0) {
                // Fallback: detectar automáticamente el entorno
                require_once __DIR__ . '/Environment.php';
                $envConfig = EnvironmentConfig::getConfig();
                $baseUrl = $envConfig['base_url'];
            }
            
            $resetLink = rtrim($baseUrl, '/') . '/auth/reset-password.php?token=' . urlencode($resetToken);
            
            // Log para debugging
            error_log("Password reset link generated: " . $resetLink);
        }
        
        $template = self::getEmailTemplate('password_reset', [
            'name' => $name,
            'reset_code' => $resetCode,
            'reset_link' => $resetLink,
            'app_name' => self::$config['APP_NAME'],
            'app_url' => self::$config['APP_URL']
        ]);
        
        return self::sendEmail($email, $subject, $template);
    }
    
    /**
     * Enviar email de bienvenida
     */
    public static function sendWelcomeEmail($email, $name)
    {
        $subject = '¡Bienvenido a ' . self::$config['APP_NAME'] . '!';
        
        $template = self::getEmailTemplate('welcome', [
            'name' => $name,
            'app_name' => self::$config['APP_NAME'],
            'app_url' => self::$config['APP_URL']
        ]);
        
        return self::sendEmail($email, $subject, $template);
    }
    
    /**
     * Enviar email usando mail() de PHP
     */
    private static function sendEmail($to, $subject, $htmlContent)
    {
        self::loadConfig();
        
        try {
            // Headers para email HTML
            $headers = [
                'MIME-Version: 1.0',
                'Content-type: text/html; charset=UTF-8',
                'From: ' . self::$config['MAIL_FROM_NAME'] . ' <' . self::$config['MAIL_FROM_EMAIL'] . '>',
                'Reply-To: ' . self::$config['MAIL_REPLY_TO'],
                'X-Mailer: PHP/' . phpversion(),
                'X-Priority: 3'
            ];
            
            $headerString = implode("\r\n", $headers);
            
            // Enviar email
            $result = mail($to, $subject, $htmlContent, $headerString);
            
            if ($result) {
                error_log("Email enviado exitosamente a: $to");
                return true;
            } else {
                error_log("Error al enviar email a: $to");
                return false;
            }
            
        } catch (Exception $e) {
            error_log("Error en envío de email: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener template de email
     */
    private static function getEmailTemplate($templateName, $variables = [])
    {
        $templatePath = __DIR__ . '/../templates/emails/' . $templateName . '.html';
        
        if (!file_exists($templatePath)) {
            // Template por defecto si no existe
            return self::getDefaultTemplate($templateName, $variables);
        }
        
        $template = file_get_contents($templatePath);
        
        // Reemplazar variables
        foreach ($variables as $key => $value) {
            $template = str_replace('{{' . $key . '}}', htmlspecialchars($value), $template);
        }
        
        return $template;
    }
    
    /**
     * Template por defecto si no existe archivo
     */
    private static function getDefaultTemplate($templateName, $variables)
    {
        $name = $variables['name'] ?? 'Usuario';
        $appName = $variables['app_name'] ?? 'Telegan Admin';
        
        switch ($templateName) {
            case 'confirmation':
                $code = $variables['confirmation_code'] ?? '000000';
                return self::getBaseTemplate(
                    'Confirma tu cuenta',
                    "
                    <h2>¡Hola {$name}!</h2>
                    <p>Gracias por registrarte en <strong>{$appName}</strong>.</p>
                    <p>Para activar tu cuenta, ingresa el siguiente código de verificación:</p>
                    <div style='background: #f8f9fa; padding: 20px; text-align: center; margin: 20px 0; border-radius: 8px;'>
                        <h1 style='color: #6dbe45; font-size: 32px; margin: 0; letter-spacing: 8px;'>{$code}</h1>
                    </div>
                    <p><strong>Este código expira en 1 hora.</strong></p>
                    <p>Si no solicitaste esta cuenta, puedes ignorar este email.</p>
                    ",
                    'Confirma tu cuenta'
                );
                
            case 'password_reset':
                $code = $variables['reset_code'] ?? '000000';
                return self::getBaseTemplate(
                    'Recupera tu contraseña',
                    "
                    <h2>¡Hola {$name}!</h2>
                    <p>Recibimos una solicitud para restablecer la contraseña de tu cuenta en <strong>{$appName}</strong>.</p>
                    <p>Para crear una nueva contraseña, ingresa el siguiente código:</p>
                    <div style='background: #f8f9fa; padding: 20px; text-align: center; margin: 20px 0; border-radius: 8px;'>
                        <h1 style='color: #ef4444; font-size: 32px; margin: 0; letter-spacing: 8px;'>{$code}</h1>
                    </div>
                    <p><strong>Este código expira en 30 minutos.</strong></p>
                    <p>Si no solicitaste este cambio, puedes ignorar este email. Tu contraseña permanecerá sin cambios.</p>
                    ",
                    'Recuperar contraseña'
                );
                
            case 'welcome':
                return self::getBaseTemplate(
                    '¡Bienvenido!',
                    "
                    <h2>¡Bienvenido a {$appName}, {$name}!</h2>
                    <p>Tu cuenta ha sido activada exitosamente.</p>
                    <p>Ahora puedes acceder al panel administrativo y comenzar a gestionar tu sistema.</p>
                    <p>Si tienes alguna pregunta, no dudes en contactarnos.</p>
                    ",
                    'Acceder al panel'
                );
                
            default:
                return self::getBaseTemplate(
                    'Notificación',
                    "<p>Hola {$name},</p><p>Has recibido una notificación de {$appName}.</p>",
                    'Ver más'
                );
        }
    }
    
    /**
     * Template base HTML
     */
    private static function getBaseTemplate($title, $content, $buttonText = null, $buttonUrl = null)
    {
        $appName = self::$config['APP_NAME'] ?? 'Telegan Admin';
        $appUrl = self::$config['APP_URL'] ?? '#';
        
        return "
        <!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$title}</title>
            <style>
                body { 
                    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; 
                    line-height: 1.6; 
                    color: #1e293b; 
                    background: #f8fafc;
                    margin: 0; 
                    padding: 20px;
                }
                .container { 
                    max-width: 600px; 
                    margin: 0 auto; 
                    background: white; 
                    border-radius: 12px; 
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                    overflow: hidden;
                }
                .header { 
                    background: linear-gradient(135deg, #6dbe45 0%, #4da1d9 100%); 
                    color: white; 
                    padding: 30px; 
                    text-align: center; 
                }
                .header h1 { 
                    margin: 0; 
                    font-size: 24px; 
                    font-weight: 600; 
                }
                .content { 
                    padding: 30px; 
                }
                .content h2 { 
                    color: #1e293b; 
                    margin-bottom: 20px; 
                    font-size: 20px;
                }
                .content p { 
                    margin-bottom: 16px; 
                    color: #64748b;
                }
                .button { 
                    display: inline-block; 
                    background: #6dbe45; 
                    color: white; 
                    padding: 12px 24px; 
                    text-decoration: none; 
                    border-radius: 8px; 
                    font-weight: 500; 
                    margin: 20px 0; 
                }
                .footer { 
                    background: #f8fafc; 
                    padding: 20px; 
                    text-align: center; 
                    color: #64748b; 
                    font-size: 14px;
                    border-top: 1px solid #e2e8f0;
                }
                .footer a { 
                    color: #6dbe45; 
                    text-decoration: none; 
                }
                @media (max-width: 600px) {
                    .container { margin: 0; border-radius: 0; }
                    .header, .content { padding: 20px; }
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>{$appName}</h1>
                </div>
                <div class='content'>
                    {$content}
                    " . ($buttonText ? "<a href='{$buttonUrl}' class='button'>{$buttonText}</a>" : "") . "
                </div>
                <div class='footer'>
                    <p>Este email fue enviado desde {$appName}</p>
                    <p>Si tienes problemas, contacta a <a href='mailto:support@telegan.com'>support@telegan.com</a></p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Validar dirección de email
     */
    public static function isValidEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Limpiar dirección de email
     */
    public static function sanitizeEmail($email)
    {
        return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    }
    
    /**
     * Probar envío de email
     */
    public static function testEmail($email)
    {
        return self::sendWelcomeEmail($email, 'Usuario de Prueba');
    }
}

