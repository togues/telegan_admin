/**
 * ApiClient - Cliente para manejo de peticiones HTTP
 * Maneja comunicación con la API usando variables en memoria (sin localStorage)
 */
class ApiClient {
    constructor() {
        // Rutas completamente relativas - funcionan desde cualquier ubicación
        this.baseURL = 'api';
        // Usar variable en memoria en lugar de localStorage
        this.token = window.adminToken || null;
        // Token de aplicación para validación
        this.appToken = window.appToken || null;
        
        console.log('API Base URL:', this.baseURL);
        
        // Generar token de aplicación si no existe
        if (!this.appToken) {
            this.generateAppToken();
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
     * Generar token de aplicación
     */
    generateAppToken() {
        const timestamp = Math.floor(Date.now() / 1000);
        const userAgent = navigator.userAgent;
        const secret = 'telegan_default_secret'; // En producción, esto vendrá del servidor
        const domain = window.location.hostname;
        
        // Crear string de validación
        const validationString = timestamp + userAgent + secret + domain;
        
        // Generar hash SHA-256 (simplificado para demo)
        const hash = this.simpleHash(validationString);
        
        this.appToken = {
            hash: hash,
            timestamp: timestamp
        };
        
        window.appToken = this.appToken;
        
        console.log('App Token generado:', this.appToken);
    }
    
    /**
     * Hash simple para demo (en producción usar crypto.subtle)
     */
    simpleHash(str) {
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            const char = str.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash; // Convertir a 32bit integer
        }
        return Math.abs(hash).toString(16);
    }

    /**
     * Obtener headers por defecto
     */
    getHeaders(includeAuth = true, includeAppToken = true) {
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };

        if (includeAuth && this.token) {
            headers['Authorization'] = `Bearer ${this.token}`;
        }
        
        if (includeAppToken && this.appToken) {
            headers['X-App-Token'] = this.appToken.hash;
            headers['X-App-Timestamp'] = this.appToken.timestamp.toString();
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
            // Asegurar que haya una barra entre baseURL y endpoint
            const url = endpoint.startsWith('/') 
                ? `${this.baseURL}${endpoint}` 
                : `${this.baseURL}/${endpoint}`;
                
            console.log('GET URL:', url);
            const response = await fetch(url, {
                method: 'GET',
                headers: this.getHeaders(includeAuth)
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
            const url = endpoint.startsWith('/') 
                ? `${this.baseURL}${endpoint}` 
                : `${this.baseURL}/${endpoint}`;
                
            const response = await fetch(url, {
                method: 'POST',
                headers: this.getHeaders(includeAuth),
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
            const url = endpoint.startsWith('/') 
                ? `${this.baseURL}${endpoint}` 
                : `${this.baseURL}/${endpoint}`;
                
            const response = await fetch(url, {
                method: 'PUT',
                headers: this.getHeaders(includeAuth),
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
            const url = endpoint.startsWith('/') 
                ? `${this.baseURL}${endpoint}` 
                : `${this.baseURL}/${endpoint}`;
                
            const response = await fetch(url, {
                method: 'DELETE',
                headers: this.getHeaders(includeAuth)
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
