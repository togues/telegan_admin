/**
 * Theme Manager - Gestor de tema oscuro/claro persistente
 * 
 * Gestiona el tema oscuro/claro de forma consistente en todas las páginas
 * Usa localStorage con la clave 'telegan-theme' para persistencia entre sesiones
 */

class ThemeManager {
    constructor() {
        this.themeKey = 'telegan-theme';
        this.init();
    }

    /**
     * Inicializar el gestor de temas
     */
    init() {
        // Cargar tema guardado al iniciar
        this.loadSavedTheme();
        
        // Configurar toggle del botón de tema
        this.setupThemeToggle();
        
        // Escuchar cambios en la preferencia del sistema
        this.watchSystemPreference();
    }

    /**
     * Cargar tema guardado o usar preferencia del sistema
     */
    loadSavedTheme() {
        const savedTheme = localStorage.getItem(this.themeKey);
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        // Prioridad: 1) Tema guardado, 2) Preferencia del sistema
        const theme = savedTheme || (prefersDark ? 'dark' : 'light');
        
        this.applyTheme(theme);
    }

    /**
     * Aplicar tema al documento
     */
    applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        
        // Guardar preferencia
        localStorage.setItem(this.themeKey, theme);
        
        // Actualizar iconos del toggle
        this.updateToggleIcons(theme);
    }

    /**
     * Cambiar entre tema claro y oscuro
     */
    toggleTheme() {
        const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        
        this.applyTheme(newTheme);
        
        // Transición suave
        this.addTransitionEffect();
        
        return newTheme;
    }

    /**
     * Configurar el botón toggle de tema
     */
    setupThemeToggle() {
        const themeToggle = document.getElementById('theme-toggle');
        
        if (themeToggle) {
            themeToggle.addEventListener('click', () => {
                const newTheme = this.toggleTheme();
                console.log(`Tema cambiado a: ${newTheme}`);
            });
            
            // Actualizar iconos al cargar
            const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
            this.updateToggleIcons(currentTheme);
        }
    }

    /**
     * Actualizar iconos del toggle según el tema
     */
    updateToggleIcons(theme) {
        const themeToggle = document.getElementById('theme-toggle');
        if (!themeToggle) return;
        
        const sunIcon = themeToggle.querySelector('.sun-icon');
        const moonIcon = themeToggle.querySelector('.moon-icon');
        
        if (sunIcon && moonIcon) {
            if (theme === 'dark') {
                sunIcon.style.display = 'none';
                moonIcon.style.display = 'block';
            } else {
                sunIcon.style.display = 'block';
                moonIcon.style.display = 'none';
            }
        }
    }

    /**
     * Agregar efecto de transición suave
     */
    addTransitionEffect() {
        document.body.style.transition = 'background-color 0.3s ease, color 0.3s ease';
        setTimeout(() => {
            document.body.style.transition = '';
        }, 300);
    }

    /**
     * Escuchar cambios en la preferencia del sistema
     * (Solo si no hay tema guardado)
     */
    watchSystemPreference() {
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        
        mediaQuery.addEventListener('change', (e) => {
            // Solo aplicar si no hay tema guardado por el usuario
            if (!localStorage.getItem(this.themeKey)) {
                const newTheme = e.matches ? 'dark' : 'light';
                this.applyTheme(newTheme);
            }
        });
    }

    /**
     * Obtener tema actual
     */
    getCurrentTheme() {
        return document.documentElement.getAttribute('data-theme') || 'light';
    }

    /**
     * Forzar tema (útil para debugging)
     */
    setTheme(theme) {
        if (theme === 'dark' || theme === 'light') {
            this.applyTheme(theme);
        }
    }
}

// Auto-inicializar cuando el DOM esté listo
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.themeManager = new ThemeManager();
    });
} else {
    // DOM ya está listo
    window.themeManager = new ThemeManager();
}

// Exportar para uso en módulos ES6
export default ThemeManager;

