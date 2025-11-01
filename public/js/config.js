/**
 * Configuración de la aplicación
 * Tokens, URLs base, etc.
 */
export const AppConfig = {
    // URL base de la API - detectar automáticamente desde la URL actual
    get apiBaseUrl() {
        const path = window.location.pathname;
        let base = path.split('/public/')[0] + '/public/api';
        if (!base.startsWith('/')) base = '/' + base;
        return window.location.origin + base;
    },
    
    // Secret para generar tokens (DEBE COINCIDIR con API_SECRET en archivo env)
    // ⚠️ IMPORTANTE: Este valor debe ser IGUAL al API_SECRET en tu archivo env
    apiSecret: 'telegan_default_secret_change_in_production',
    
    // Hash SHA-256 usando Web Crypto API
    async sha256Async(message) {
        const msgBuffer = new TextEncoder().encode(message);
        const hashBuffer = await crypto.subtle.digest('SHA-256', msgBuffer);
        const hashArray = Array.from(new Uint8Array(hashBuffer));
        return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
    },
    
    // Hash simple sincrónico (fallback si crypto.subtle no disponible)
    simpleHash(str) {
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            const char = str.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            // Forzar a 32 bits (igual que PHP: hash & 0xFFFFFFFF)
            hash = hash >>> 0;
        }
        return hash.toString(16).padStart(8, '0');
    },
    
    // Generar token para una petición (sincrónico)
    generateToken(timestamp, url = '') {
        const data = timestamp + url + this.apiSecret;
        // Usar hash simple sincrónico para desarrollo
        // En producción, el backend debe coincidir con este método
        return this.simpleHash(data).repeat(4).substring(0, 64); // Simular hash de 64 chars
    },
    
    // Generar headers de autenticación
    getAuthHeaders(url = '') {
        const timestamp = Math.floor(Date.now() / 1000).toString();
        const token = this.generateToken(timestamp, url);
        
        return {
            'X-API-Token': token,
            'X-API-Timestamp': timestamp,
            'Content-Type': 'application/json'
        };
    }
};

