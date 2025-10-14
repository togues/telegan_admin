<?php

/**
 * Sistema de Sesiones PHP Nativas
 * PHP Vanilla - Sin frameworks
 */
class Session
{
    /**
     * Inicializar sesión
     */
    public static function start()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Establecer variable de sesión
     */
    public static function set($key, $value)
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    /**
     * Obtener variable de sesión
     */
    public static function get($key, $default = null)
    {
        self::start();
        return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
    }

    /**
     * Verificar si existe variable de sesión
     */
    public static function has($key)
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    /**
     * Eliminar variable de sesión
     */
    public static function remove($key)
    {
        self::start();
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }

    /**
     * Destruir sesión completamente
     */
    public static function destroy()
    {
        self::start();
        session_destroy();
        $_SESSION = array();
    }

    /**
     * Regenerar ID de sesión
     */
    public static function regenerateId()
    {
        self::start();
        session_regenerate_id(true);
    }

    /**
     * Obtener ID de sesión
     */
    public static function getId()
    {
        self::start();
        return session_id();
    }

    /**
     * Establecer datos de administrador
     */
    public static function setAdmin($adminData)
    {
        self::set('admin_logged_in', true);
        self::set('admin_id', $adminData['id']);
        self::set('admin_nombre', $adminData['nombre']);
        self::set('admin_email', $adminData['email']);
        self::set('login_time', time());
    }

    /**
     * Obtener datos de administrador
     */
    public static function getAdmin()
    {
        if (!self::isAdminLoggedIn()) {
            return null;
        }

        return array(
            'id' => self::get('admin_id'),
            'nombre' => self::get('admin_nombre'),
            'email' => self::get('admin_email'),
            'login_time' => self::get('login_time')
        );
    }

    /**
     * Verificar si admin está logueado
     */
    public static function isAdminLoggedIn()
    {
        return self::get('admin_logged_in', false) === true;
    }

    /**
     * Cerrar sesión de administrador
     */
    public static function logoutAdmin()
    {
        self::remove('admin_logged_in');
        self::remove('admin_id');
        self::remove('admin_nombre');
        self::remove('admin_email');
        self::remove('login_time');
    }

    /**
     * Verificar si la sesión ha expirado
     */
    public static function isExpired($maxLifetime = 3600)
    {
        $loginTime = self::get('login_time');
        if (!$loginTime) {
            return true;
        }

        return (time() - $loginTime) > $maxLifetime;
    }

    /**
     * Limpiar sesiones expiradas
     */
    public static function cleanup()
    {
        if (self::isExpired()) {
            self::destroy();
            return true;
        }
        return false;
    }
}






