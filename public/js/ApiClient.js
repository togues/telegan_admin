/**
 * ApiClient - Cliente para manejo de peticiones HTTP
 * Maneja comunicación con la API usando variables en memoria (sin localStorage)
 */
import { AppConfig } from './config.js';

class ApiClient {
    constructor() {
        // Usar variable en memoria en lugar de localStorage
        this.token = window.adminToken || null;
        // Token de sesión persistente (viene desde PHP o se obtiene al iniciar)
        this.sessionToken = window.sessionToken || null;
        // Promesa de inicialización de sesión (para evitar race conditions)
        this._sessionInitPromise = null;
        
        console.log('API Base URL:', this.baseURL);
        console.log('Session Token disponible:', this.sessionToken ? 'Sí (desde PHP)' : 'No');
        
        // Inicializar sesión SOLO si no existe token (el token viene desde PHP en páginas PHP)
        if (!this.sessionToken) {
            console.log('No hay token desde PHP, inicializando sesión...');
            this._sessionInitPromise = this.initSession();
        }
    }
    
    // Getter para obtener la base URL dinámicamente
    get baseURL() {
        return AppConfig.apiBaseUrl;
    }
    
    /**
     * Esperar a que la sesión esté inicializada
     */
    async waitForSession() {
        // Verificar nuevamente window.sessionToken (puede haberse establecido desde PHP después de que ApiClient se creó)
        if (!this.sessionToken && window.sessionToken) {
            this.sessionToken = window.sessionToken;
            console.log('Token de sesión detectado desde window.sessionToken');
        }
        
        if (this.sessionToken) {
            return; // Ya tiene token
        }
        
        if (this._sessionInitPromise) {
            await this._sessionInitPromise; // Esperar que termine la inicialización
        }
        
        // Verificar una vez más después de esperar
        if (!this.sessionToken && window.sessionToken) {
            this.sessionToken = window.sessionToken;
            console.log('Token de sesión detectado después de initSession');
        }
    }
    
    /**
     * Inicializar sesión y obtener token persistente
     */
    async initSession() {
        try {
            const response = await fetch(`${this.baseURL}/init-session.php`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    ...this.getHeaders(`${this.baseURL}/init-session.php`, false)
                }
            });
            
            if (!response.ok) {
                console.warn('No se pudo inicializar sesión, usando tokens por petición');
                return;
            }
            
            const data = await response.json();
            if (data.success && data.data.session_token) {
                this.sessionToken = data.data.session_token;
                window.sessionToken = this.sessionToken;
                console.log('Sesión iniciada correctamente');
            }
        } catch (error) {
            console.warn('Error al inicializar sesión:', error);
            // Continuar sin sesión (usará tokens por petición como fallback)
        }
    }

    /**
     * Establecer token de autenticación en memoria
     */
    setToken(token) {
        this.token = token;
        window.adminToken = token;
    }

    /**
     * Construir path completo para validación del token
     */
    buildFullPath(url) {
        // Si ya es un path absoluto que empieza con /, devolverlo
        if (url.startsWith('/')) {
            return url;
        }
        
        // Obtener base path desde la URL actual
        const currentPath = window.location.pathname;
        let basePath = '';
        
        // Extraer base path: encontrar /public/ y usar todo lo anterior
        // Ejemplos:
        // /TELEGAN_ADMIN/public/dashboard.html -> /TELEGAN_ADMIN/public
        // /TELEGAN_ADMIN/public/modules/users/ -> /TELEGAN_ADMIN/public
        const publicMatch = currentPath.match(/^(.+\/public)/);
        if (publicMatch) {
            basePath = publicMatch[1]; // /TELEGAN_ADMIN/public
        } else {
            // Si no hay /public/, buscar /modules/ o /dashboard
            if (currentPath.includes('/modules/')) {
                basePath = currentPath.split('/modules/')[0] + '/public';
            } else if (currentPath.includes('/dashboard')) {
                basePath = currentPath.split('/dashboard')[0] + '/public';
            } else {
                // Fallback: usar path actual sin el nombre del archivo
                basePath = currentPath.substring(0, currentPath.lastIndexOf('/'));
            }
        }
        
        // Construir path completo: /TELEGAN_ADMIN/public/api/dashboard.php
        if (url.startsWith('api/')) {
            return basePath + '/api/' + url.substring(4); // Quitar 'api/' del inicio
        }
        
        return basePath + '/' + url;
    }
    
    /**
     * Obtener headers por defecto con autenticación API
     */
    getHeaders(url = '', includeAuth = true) {
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };

        // Usar token de sesión si está disponible (preferido)
        if (this.sessionToken) {
            headers['X-Session-Token'] = this.sessionToken;
        } else {
            // Fallback a token por petición (legacy)
            let urlPath = url;
            if (urlPath.includes('?')) {
                urlPath = urlPath.split('?')[0];
            }
            if (urlPath.includes('#')) {
                urlPath = urlPath.split('#')[0];
            }
            urlPath = this.buildFullPath(urlPath);
            const authHeaders = AppConfig.getAuthHeaders(urlPath);
            headers['X-API-Token'] = authHeaders['X-API-Token'];
            headers['X-API-Timestamp'] = authHeaders['X-API-Timestamp'];
        }

        // Token de usuario si está disponible
        if (includeAuth && this.token) {
            headers['Authorization'] = `Bearer ${this.token}`;
        }

        return headers;
    }

    /**
     * Manejar respuesta de la API
     */
    async handleResponse(response) {
        const contentType = response.headers.get('content-type');
        
        if (!contentType || !contentType.includes('application/json')) {
            throw new Error('Respuesta no válida del servidor');
        }

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Error en la petición');
        }

        return data;
    }

    /**
     * Realizar petición GET
     */
    async get(endpoint, includeAuth = true) {
        try {
            // Esperar a que la sesión esté inicializada antes de hacer la petición
            await this.waitForSession();
            
            // Si el endpoint ya incluye 'api/', solo usar el nombre del archivo
            // api/users-list.php -> users-list.php (baseURL ya tiene /api)
            let cleanEndpoint = endpoint;
            if (endpoint.startsWith('api/')) {
                cleanEndpoint = endpoint.substring(4); // Quitar 'api/'
            } else if (endpoint.startsWith('/api/')) {
                cleanEndpoint = endpoint.substring(5); // Quitar '/api/'
            }
            
            // Construir URL final
            const url = `${this.baseURL}/${cleanEndpoint}`;
                
            console.log('GET URL:', url);
            const response = await fetch(url, {
                method: 'GET',
                headers: this.getHeaders(url, includeAuth)
            });

            return await this.handleResponse(response);
        } catch (error) {
            console.error('Error en GET:', error);
            throw error;
        }
    }

    /**
     * Realizar petición POST
     */
    async post(endpoint, data = {}, includeAuth = false) {
        try {
            // Esperar a que la sesión esté inicializada antes de hacer la petición
            await this.waitForSession();
            
            // Limpiar endpoint si incluye 'api/'
            let cleanEndpoint = endpoint;
            if (endpoint.startsWith('api/')) {
                cleanEndpoint = endpoint.substring(4);
            } else if (endpoint.startsWith('/api/')) {
                cleanEndpoint = endpoint.substring(5);
            }
            
            const url = `${this.baseURL}/${cleanEndpoint}`;
                
            const response = await fetch(url, {
                method: 'POST',
                headers: this.getHeaders(url, includeAuth),
                body: JSON.stringify(data)
            });

            return await this.handleResponse(response);
        } catch (error) {
            console.error('Error en POST:', error);
            throw error;
        }
    }

    /**
     * Realizar petición PUT
     */
    async put(endpoint, data = {}, includeAuth = true) {
        try {
            // Esperar a que la sesión esté inicializada antes de hacer la petición
            await this.waitForSession();
            
            // Limpiar endpoint si incluye 'api/'
            let cleanEndpoint = endpoint;
            if (endpoint.startsWith('api/')) {
                cleanEndpoint = endpoint.substring(4);
            } else if (endpoint.startsWith('/api/')) {
                cleanEndpoint = endpoint.substring(5);
            }
            
            const url = `${this.baseURL}/${cleanEndpoint}`;
                
            const response = await fetch(url, {
                method: 'PUT',
                headers: this.getHeaders(url, includeAuth),
                body: JSON.stringify(data)
            });

            return await this.handleResponse(response);
        } catch (error) {
            console.error('Error en PUT:', error);
            throw error;
        }
    }

    /**
     * Realizar petición DELETE
     */
    async delete(endpoint, includeAuth = true) {
        try {
            // Esperar a que la sesión esté inicializada antes de hacer la petición
            await this.waitForSession();
            
            // Limpiar endpoint si incluye 'api/'
            let cleanEndpoint = endpoint;
            if (endpoint.startsWith('api/')) {
                cleanEndpoint = endpoint.substring(4);
            } else if (endpoint.startsWith('/api/')) {
                cleanEndpoint = endpoint.substring(5);
            }
            
            const url = `${this.baseURL}/${cleanEndpoint}`;
                
            const response = await fetch(url, {
                method: 'DELETE',
                headers: this.getHeaders(url, includeAuth)
            });

            return await this.handleResponse(response);
        } catch (error) {
            console.error('Error en DELETE:', error);
            throw error;
        }
    }

    /**
     * Verificar si el usuario está autenticado
     */
    isAuthenticated() {
        return !!this.token;
    }

    /**
     * Cerrar sesión
     */
    logout() {
        this.setToken(null);
        window.location.href = 'login.html';
    }

    /**
     * Obtener configuración de endpoints
     */
    getEndpointConfig() {
        // En desarrollo, usar nombres reales
        // En producción, usar nombres enmascarados
        const isDev = window.location.hostname === 'localhost' || 
                     window.location.hostname.includes('127.0.0.1') ||
                     window.location.protocol === 'file:';
        
        if (isDev) {
            return {
                baseURL: 'api',
                endpoints: {
                    'dashboard-data': 'dashboard.php',
                    'system-alerts': 'alerts.php',
                    'search-users': 'search.php',
                    'user-farms': 'user-farms.php',
                    'farm-details': 'farm-details.php',
                    'operational-stats': 'operational.php'
                }
            };
        } else {
            // En producción, usar endpoints enmascarados
            // (Se implementará cuando se active el enmascaramiento)
            return {
                baseURL: 'api',
                endpoints: {
                    'dashboard-data': 'dashboard.php', // Por ahora, mantener igual
                    'system-alerts': 'alerts.php',
                    'search-users': 'search.php',
                    'user-farms': 'user-farms.php',
                    'farm-details': 'farm-details.php',
                    'operational-stats': 'operational.php'
                }
            };
        }
    }

    /**
     * Obtener endpoint por alias
     */
    getEndpoint(alias) {
        const config = this.getEndpointConfig();
        return config.endpoints[alias] || alias;
    }

    /**
     * Métodos del dashboard usando aliases
     */
    async getDashboardData() {
        return await this.get(this.getEndpoint('dashboard-data'));
    }
    
    async getSystemAlerts(tipo = 'resumen') {
        return await this.get(`${this.getEndpoint('system-alerts')}?tipo=${tipo}`);
    }
    
    async searchUsers(query, limit = 10) {
        return await this.get(`${this.getEndpoint('search-users')}?q=${encodeURIComponent(query)}&limit=${limit}`);
    }
    
    async getUserFarms(userId) {
        return await this.get(`${this.getEndpoint('user-farms')}?user_id=${userId}`);
    }
    
    async getFarmDetails(farmId) {
        return await this.get(`${this.getEndpoint('farm-details')}?farm_id=${farmId}`);
    }
    
    async getOperationalStats() {
        return await this.get(this.getEndpoint('operational-stats'));
    }
}

// Exportar la clase para uso con ES6 modules
export { ApiClient };

// Crear instancia global para compatibilidad
window.apiClient = new ApiClient();
