/**
 * Auth.js - Manejo de autenticación en el frontend
 */

// Utilidades para mostrar notificaciones
function showNotification(message, type = 'success') {
    const notification = document.getElementById('notification');
    notification.textContent = message;
    notification.className = `notification ${type}`;
    notification.style.display = 'block';

    setTimeout(() => {
        notification.style.display = 'none';
    }, 5000);
}

// Manejar estados de carga de botones
function setButtonLoading(button, loading = true) {
    if (loading) {
        button.classList.add('loading');
        button.disabled = true;
    } else {
        button.classList.remove('loading');
        button.disabled = false;
    }
}

// Validar formulario de login
function validateLoginForm(formData) {
    const { email, password } = formData;
    
    if (!email || !password) {
        throw new Error('Todos los campos son requeridos');
    }

    if (!isValidEmail(email)) {
        throw new Error('Email inválido');
    }

    return true;
}

// Validar formulario de registro
function validateRegisterForm(formData) {
    const { nombre, email, password, confirmPassword } = formData;
    
    if (!nombre || !email || !password || !confirmPassword) {
        throw new Error('Todos los campos son requeridos');
    }

    if (!isValidEmail(email)) {
        throw new Error('Email inválido');
    }

    if (password.length < 6) {
        throw new Error('La contraseña debe tener al menos 6 caracteres');
    }

    if (password !== confirmPassword) {
        throw new Error('Las contraseñas no coinciden');
    }

    return true;
}

// Validar email
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Manejar login
async function handleLogin(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    
    const submitButton = form.querySelector('button[type="submit"]');
    
    try {
        // Validar formulario
        validateLoginForm(data);
        
        // Mostrar estado de carga
        setButtonLoading(submitButton, true);
        
        // Realizar login
        const response = await apiClient.login(data.email, data.password);
        
        if (response.success) {
            showNotification('Login exitoso. Redirigiendo...', 'success');
            setTimeout(() => {
                window.location.href = 'dashboard.html';
            }, 1500);
        } else {
            throw new Error(response.message || 'Error en el login');
        }
        
    } catch (error) {
        console.error('Error en login:', error);
        showNotification(error.message || 'Error en el login', 'error');
    } finally {
        setButtonLoading(submitButton, false);
    }
}

// Manejar registro
async function handleRegister(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    
    const submitButton = form.querySelector('button[type="submit"]');
    
    try {
        // Validar formulario
        validateRegisterForm(data);
        
        // Mostrar estado de carga
        setButtonLoading(submitButton, true);
        
        // Realizar registro
        const response = await apiClient.register(data.nombre, data.email, data.password);
        
        if (response.success) {
            showNotification('Registro exitoso. Redirigiendo...', 'success');
            setTimeout(() => {
                window.location.href = 'dashboard.html';
            }, 1500);
        } else {
            throw new Error(response.message || 'Error en el registro');
        }
        
    } catch (error) {
        console.error('Error en registro:', error);
        showNotification(error.message || 'Error en el registro', 'error');
    } finally {
        setButtonLoading(submitButton, false);
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    // Verificar si ya está autenticado
    if (apiClient.isAuthenticated()) {
        // Si está en login o register, redirigir al dashboard
        if (window.location.pathname.includes('login.html') || 
            window.location.pathname.includes('register.html')) {
            window.location.href = 'dashboard.html';
        }
    }

    // Configurar formularios
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
    }
    
    if (registerForm) {
        registerForm.addEventListener('submit', handleRegister);
    }
});

















