/**
 * Sistema común de gestión de tema para todas las páginas
 * Asegura consistencia y persistencia del tema oscuro/light
 */

(function() {
    'use strict';

    const THEME_KEY = 'telegan-theme';

    /**
     * Inicializar tema al cargar la página
     * Por defecto: tema oscuro
     */
    function initTheme() {
        const savedTheme = localStorage.getItem(THEME_KEY);
        // Por defecto siempre oscuro si no hay tema guardado
        const theme = savedTheme || 'dark';
        
        // Si no hay tema guardado, setearlo en localStorage
        if (!savedTheme) {
            localStorage.setItem(THEME_KEY, 'dark');
        }
        
        document.documentElement.setAttribute('data-theme', theme);
        updateThemeIcons(theme);
    }

    /**
     * Actualizar iconos del toggle según el tema actual
     */
    function updateThemeIcons(theme) {
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
     * Configurar toggle de tema
     */
    function setupThemeToggle() {
        const themeToggle = document.getElementById('theme-toggle');
        if (!themeToggle) return;

        themeToggle.addEventListener('click', function() {
            const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem(THEME_KEY, newTheme);
            updateThemeIcons(newTheme);

            // Transición suave
            document.body.style.transition = 'background-color 0.3s ease, color 0.3s ease';
            setTimeout(() => {
                document.body.style.transition = '';
            }, 300);
        });
    }

    // Inicializar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            initTheme();
            setupThemeToggle();
        });
    } else {
        // DOM ya está listo
        initTheme();
        setupThemeToggle();
    }

    // Exportar funciones globalmente si se necesitan
    window.TeleganTheme = {
        initTheme,
        setupThemeToggle,
        updateThemeIcons
    };
})();

