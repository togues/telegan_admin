/**
 * JavaScript para Sistema de Autenticación Telegan
 * Funcionalidades modernas y mobile-first
 */

class AuthApp {
    constructor() {
        this.init();
    }

    init() {
        this.setupThemeToggle();
        this.setupFormValidation();
        this.setupAnimations();
        this.setupPasswordStrength();
        this.setupAutoSubmit();
        this.setupKeyboardNavigation();
    }

    /**
     * Configurar toggle de tema claro/oscuro
     */
    setupThemeToggle() {
        // Crear botón de toggle si no existe
        if (!document.querySelector('.theme-toggle')) {
            const themeToggle = document.createElement('button');
            themeToggle.className = 'theme-toggle';
            themeToggle.innerHTML = `
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            `;
            themeToggle.setAttribute('aria-label', 'Cambiar tema');
            themeToggle.setAttribute('title', 'Cambiar tema');
            
            // Agregar al header
            const authHeader = document.querySelector('.auth-header');
            if (authHeader) {
                authHeader.style.position = 'relative';
                themeToggle.style.position = 'absolute';
                themeToggle.style.top = '0';
                themeToggle.style.right = '0';
                themeToggle.style.background = 'none';
                themeToggle.style.border = 'none';
                themeToggle.style.cursor = 'pointer';
                themeToggle.style.padding = '8px';
                themeToggle.style.borderRadius = '50%';
                themeToggle.style.color = 'var(--text-secondary)';
                themeToggle.style.transition = 'all 0.3s ease';
                
                themeToggle.addEventListener('mouseenter', () => {
                    themeToggle.style.background = 'var(--bg-tertiary)';
                    themeToggle.style.color = 'var(--text-primary)';
                });
                
                themeToggle.addEventListener('mouseleave', () => {
                    themeToggle.style.background = 'none';
                    themeToggle.style.color = 'var(--text-secondary)';
                });
                
                authHeader.appendChild(themeToggle);
            }
        }

        // Manejar click del toggle
        document.addEventListener('click', (e) => {
            if (e.target.closest('.theme-toggle')) {
                this.toggleTheme();
            }
        });
    }

    /**
     * Alternar entre tema claro y oscuro
     */
    toggleTheme() {
        const currentTheme = document.documentElement.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        
        document.documentElement.setAttribute('data-theme', newTheme);
        localStorage.setItem('telegan-theme', newTheme);
        
        // Animación suave
        document.body.style.transition = 'background-color 0.3s ease, color 0.3s ease';
        setTimeout(() => {
            document.body.style.transition = '';
        }, 300);
    }

    /**
     * Cargar tema guardado
     */
    loadSavedTheme() {
        const savedTheme = localStorage.getItem('telegan-theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const theme = savedTheme || (prefersDark ? 'dark' : 'light');
        
        document.documentElement.setAttribute('data-theme', theme);
    }

    /**
     * Configurar validación de formularios en tiempo real
     */
    setupFormValidation() {
        const forms = document.querySelectorAll('.auth-form');
        
        forms.forEach(form => {
            const inputs = form.querySelectorAll('input[required]');
            
            inputs.forEach(input => {
                // Validación en tiempo real
                input.addEventListener('input', () => {
                    this.validateField(input);
                });
                
                // Validación al perder foco
                input.addEventListener('blur', () => {
                    this.validateField(input);
                });
            });
        });
    }

    /**
     * Validar campo individual
     */
    validateField(input) {
        const value = input.value.trim();
        const type = input.type;
        const name = input.name;
        
        let isValid = true;
        let message = '';
        
        // Validaciones específicas
        if (type === 'email') {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            isValid = emailRegex.test(value);
            message = isValid ? '' : 'Email inválido';
        } else if (type === 'password') {
            isValid = value.length >= 8;
            message = isValid ? '' : 'Mínimo 8 caracteres';
        } else if (name === 'verification_code') {
            const codeRegex = /^[0-9]{6}$/;
            isValid = codeRegex.test(value);
            message = isValid ? '' : 'Código de 6 dígitos requerido';
        } else if (name === 'confirm_password') {
            const password = document.querySelector('input[name="new_password"]')?.value || '';
            isValid = value === password && value.length >= 8;
            message = isValid ? '' : 'Las contraseñas no coinciden';
        }
        
        // Mostrar/ocultar mensaje de error
        this.showFieldError(input, isValid, message);
        
        return isValid;
    }

    /**
     * Mostrar error de campo
     */
    showFieldError(input, isValid, message) {
        const existingError = input.parentNode.querySelector('.field-error');
        
        if (existingError) {
            existingError.remove();
        }
        
        if (!isValid && message) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'field-error';
            errorDiv.textContent = message;
            errorDiv.style.color = 'var(--error-color)';
            errorDiv.style.fontSize = '0.75rem';
            errorDiv.style.marginTop = '4px';
            
            input.parentNode.appendChild(errorDiv);
            input.style.borderColor = 'var(--error-color)';
        } else {
            input.style.borderColor = 'var(--border-color)';
        }
    }

    /**
     * Configurar animaciones suaves
     */
    setupAnimations() {
        // Intersection Observer para animaciones de entrada
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, { threshold: 0.1 });

        // Observar elementos animables
        const animatedElements = document.querySelectorAll('.auth-card, .form-group, .btn-primary');
        animatedElements.forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(el);
        });
    }

    /**
     * Configurar indicador de fortaleza de contraseña
     */
    setupPasswordStrength() {
        const passwordInputs = document.querySelectorAll('input[type="password"][name="password"], input[name="new_password"]');
        
        passwordInputs.forEach(input => {
            input.addEventListener('input', () => {
                this.showPasswordStrength(input);
            });
        });
    }

    /**
     * Mostrar fortaleza de contraseña
     */
    showPasswordStrength(input) {
        const password = input.value;
        const strength = this.calculatePasswordStrength(password);
        
        let existingIndicator = input.parentNode.querySelector('.password-strength');
        if (!existingIndicator) {
            existingIndicator = document.createElement('div');
            existingIndicator.className = 'password-strength';
            existingIndicator.style.marginTop = '8px';
            input.parentNode.appendChild(existingIndicator);
        }
        
        const strengthText = ['Muy débil', 'Débil', 'Regular', 'Fuerte', 'Muy fuerte'];
        const strengthColors = ['#ef4444', '#f59e0b', '#eab308', '#10b981', '#059669'];
        
        existingIndicator.innerHTML = `
            <div style="display: flex; gap: 4px; margin-bottom: 4px;">
                ${[1,2,3,4,5].map(i => `
                    <div style="
                        width: 20px; 
                        height: 4px; 
                        background: ${i <= strength ? strengthColors[strength - 1] : 'var(--border-color)'};
                        border-radius: 2px;
                        transition: background 0.3s ease;
                    "></div>
                `).join('')}
            </div>
            <small style="color: ${strengthColors[strength - 1]}; font-weight: 500;">
                ${strengthText[strength - 1]}
            </small>
        `;
    }

    /**
     * Calcular fortaleza de contraseña
     */
    calculatePasswordStrength(password) {
        let score = 0;
        
        if (password.length >= 8) score++;
        if (password.length >= 12) score++;
        if (/[a-z]/.test(password)) score++;
        if (/[A-Z]/.test(password)) score++;
        if (/[0-9]/.test(password)) score++;
        if (/[^A-Za-z0-9]/.test(password)) score++;
        
        return Math.min(score, 5);
    }

    /**
     * Configurar auto-submit para códigos de verificación
     */
    setupAutoSubmit() {
        const verificationInputs = document.querySelectorAll('input[name="verification_code"]');
        
        verificationInputs.forEach(input => {
            input.addEventListener('input', (e) => {
                if (e.target.value.length === 6) {
                    // Pequeño delay para que el usuario vea el código completo
                    setTimeout(() => {
                        e.target.form.submit();
                    }, 500);
                }
            });
        });
    }

    /**
     * Configurar navegación por teclado
     */
    setupKeyboardNavigation() {
        document.addEventListener('keydown', (e) => {
            // Enter en formularios
            if (e.key === 'Enter' && e.target.tagName === 'INPUT') {
                const form = e.target.closest('form');
                if (form) {
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn && !submitBtn.disabled) {
                        submitBtn.click();
                    }
                }
            }
            
            // Escape para limpiar formularios
            if (e.key === 'Escape') {
                const activeInput = document.activeElement;
                if (activeInput && activeInput.tagName === 'INPUT') {
                    activeInput.value = '';
                    activeInput.focus();
                }
            }
        });
    }

    /**
     * Mostrar notificación toast
     */
    showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--bg-primary);
            color: var(--text-primary);
            padding: 12px 16px;
            border-radius: 8px;
            box-shadow: var(--shadow-lg);
            border-left: 4px solid var(--${type === 'error' ? 'error' : type === 'success' ? 'success' : 'info'}-color);
            z-index: 1000;
            transform: translateX(100%);
            transition: transform 0.3s ease;
        `;
        
        document.body.appendChild(toast);
        
        // Animar entrada
        setTimeout(() => {
            toast.style.transform = 'translateX(0)';
        }, 100);
        
        // Auto-remover
        setTimeout(() => {
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => {
                document.body.removeChild(toast);
            }, 300);
        }, 3000);
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    new AuthApp();
});

// Cargar tema guardado
document.addEventListener('DOMContentLoaded', () => {
    const authApp = new AuthApp();
    authApp.loadSavedTheme();
});

// Exportar para uso global
window.AuthApp = AuthApp;


