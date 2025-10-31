<?php

/**
 * Clase de Seguridad - TELEGAN ADMIN
 */
class Security
{
    /**
     * Establecer headers de seguridad
     */
    public static function setSecurityHeaders()
    {
        // Prevenir clickjacking
        header('X-Frame-Options: DENY');
        
        // Prevenir MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Habilitar XSS protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Política de referrer
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Content Security Policy - TEMPORALMENTE DESACTIVADO para desarrollo
        // TODO: Reactivar y ajustar en producción
        // header("Content-Security-Policy: default-src 'self' https:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://unpkg.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://unpkg.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https: blob:; connect-src 'self' https:;");
    }
    
    /**
     * Inicializar sistema de seguridad
     */
    public static function init()
    {
        self::setSecurityHeaders();
    }
}
