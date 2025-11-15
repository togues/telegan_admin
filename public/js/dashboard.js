// Dashboard JavaScript - Premium Version
console.log('Dashboard script loaded');

// Import ApiClient
import { ApiClient } from './ApiClient.js';

// Global variables
let apiClient;
let insightsCache = null;
const DASHBOARD_CACHE_KEY = 'telegan-dashboard-data';
const DASHBOARD_CACHE_TTL = 5 * 60 * 1000; // 5 minutos
const chartInstances = {};

// Initialize dashboard
document.addEventListener('DOMContentLoaded', function() {
    console.log('Inicializando dashboard Telegan...');
    
    // Initialize API client
    apiClient = new ApiClient();
    
    // Initialize theme system
    initializeTheme();
    
    // Setup event listeners (including tabs)
    setupEventListeners();
    setupLogoutCleanup();

    // Sidebar collapsed state (persistido + colapsado por defecto en desktop)
    try {
        const saved = localStorage.getItem('sidebarCollapsed');
        if (saved === 'true') {
            document.body.classList.add('sidebar-collapsed');
        } else if (saved === null && window.innerWidth >= 1024) {
            document.body.classList.add('sidebar-collapsed');
        }
    } catch (_) {}
    
    // Load initial data (operational data by default)
    loadOperationalData();
    
    // Load new insights (charts) + alerts
    loadDashboardInsights();
    loadDashboardData();
    
    // Add loading animations
    addLoadingAnimations();
    
    console.log('Dashboard inicializado correctamente');
});

// ===================================
// Theme Management
// ===================================
// Nota: El tema se maneja principalmente con theme-common.js
// Esta función solo agrega la notificación cuando se cambia el tema desde el dashboard
function initializeTheme() {
    // Asegurar que el tema se aplique (theme-common.js lo hace, pero por si acaso)
    const savedTheme = localStorage.getItem('telegan-theme');
    const currentTheme = savedTheme || 'dark';
    document.documentElement.setAttribute('data-theme', currentTheme);
    
    // Agregar listener adicional para mostrar notificación cuando se cambia el tema
    const themeToggle = document.getElementById('theme-toggle');
    if (themeToggle && !themeToggle.dataset.dashboardBound) {
        themeToggle.dataset.dashboardBound = 'true';
        themeToggle.addEventListener('click', () => {
            setTimeout(() => {
                const activeTheme = document.documentElement.getAttribute('data-theme') || 'dark';
                showNotification(`Tema cambiado a ${activeTheme === 'dark' ? 'oscuro' : 'claro'}`, 'info');
                if (insightsCache) {
                    renderDashboardInsights(insightsCache);
                }
            }, 120);
        });
    }
}

// ===================================
// Notifications
// ===================================
function showNotification(message, type = 'info') {
    // Create notification element if it doesn't exist
    let notification = document.getElementById('notification');
    if (!notification) {
        notification = document.createElement('div');
        notification.id = 'notification';
        notification.className = 'notification';
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            color: white;
            font-weight: 500;
            transform: translateX(100%);
            transition: transform 0.3s ease;
            max-width: 300px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        `;
        document.body.appendChild(notification);
    }
    
    // Set notification content and type
    notification.textContent = message;
    
    // Set colors based on type
    const colors = {
        success: '#10b981',
        error: '#ef4444',
        warning: '#f59e0b',
        info: '#3b82f6'
    };
    notification.style.backgroundColor = colors[type] || colors.info;
    
    // Show notification
    notification.style.display = 'block';
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    // Auto hide after 4 seconds
    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            notification.style.display = 'none';
        }, 300);
    }, 4000);
}

// ===================================
// Connection Status
// ===================================
function updateConnectionStatus(isConnected, message = '') {
    const statusElement = document.getElementById('connectionStatus');
    const statusDot = statusElement?.querySelector('.status-dot');
    const statusText = statusElement?.querySelector('.status-text');
    
    if (statusElement && statusDot && statusText) {
        if (isConnected) {
            statusDot.classList.add('connected');
            statusDot.classList.remove('error');
            statusText.textContent = message || 'Conectado';
            statusElement.classList.add('connected');
            statusElement.classList.remove('error');
        } else {
            statusDot.classList.add('error');
            statusDot.classList.remove('connected');
            statusText.textContent = message || 'Error de Conexión';
            statusElement.classList.add('error');
            statusElement.classList.remove('connected');
        }
    }
}

// ===================================
// Stats Animation
// ===================================
function animateValue(element, start, end, duration) {
    const startTimestamp = performance.now();
    const step = (timestamp) => {
        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
        const current = Math.floor(progress * (end - start) + start);
        element.textContent = current.toLocaleString();
        if (progress < 1) {
            requestAnimationFrame(step);
        }
    };
    requestAnimationFrame(step);
}

// ===================================
// Update Stats
// ===================================
function updateStats(data) {
    if (data && data.estadisticas) {
        const { total_usuarios, total_fincas, total_potreros, total_registros_ganaderos } = data.estadisticas;
        
        // Update DOM elements with animation
        const usuariosEl = document.getElementById('totalUsuarios');
        const fincasEl = document.getElementById('totalFincas');
        const potrerosEl = document.getElementById('totalPotreros');
        const registrosEl = document.getElementById('totalRegistros');
        
        if (usuariosEl) {
            animateValue(usuariosEl, 0, total_usuarios || 0, 1000);
        }
        if (fincasEl) {
            animateValue(fincasEl, 0, total_fincas || 0, 1200);
        }
        if (potrerosEl) {
            animateValue(potrerosEl, 0, total_potreros || 0, 1400);
        }
        if (registrosEl) {
            animateValue(registrosEl, 0, total_registros_ganaderos || 0, 1600);
        }
        
        console.log('Estadísticas actualizadas:', data.estadisticas);
    }
}

// ===================================
// Load Dashboard Data
// ===================================
async function loadDashboardData() {
    try {
        console.log('Cargando datos del dashboard...');
        
        // Add loading state to cards
        addLoadingToCards();
        
        // Load basic stats and alerts in parallel
        const [statsResponse, alertsResponse] = await Promise.all([
            apiClient.getDashboardData(),
            apiClient.get('alerts.php?tipo=resumen')
        ]);
        
        console.log('Respuesta del dashboard:', statsResponse);
        console.log('Respuesta de alertas:', alertsResponse);
        
        if (statsResponse && statsResponse.success) {
            updateConnectionStatus(true, 'Conectado');
            updateStats(statsResponse.data);
            
            // Update system info if available
            if (statsResponse.data.base_datos) {
                updateSystemInfo(statsResponse.data.base_datos);
            }
        } else {
            updateConnectionStatus(false, 'Error al cargar datos');
            showNotification('Error al cargar datos del dashboard', 'error');
        }
        
        if (alertsResponse && alertsResponse.success) {
            console.log('Cargando alertas desde API...', alertsResponse.data);
            
            // Forzar actualización de alertas con datos reales
            const alertasData = alertsResponse.data;
            console.log('Datos de alertas procesados:', alertasData);
            
            // Actualizar cada tarjeta individualmente
            updateAlertCard('usuariosSinFinca', alertasData.usuarios?.sin_finca || 0);
            updateAlertCard('usuariosInactivos30', alertasData.usuarios?.inactivos_30d || 0);
            updateAlertCard('usuariosNuncaLogin', alertasData.usuarios?.nunca_logueados || 0);
            updateAlertCard('usuariosSinDemo', alertasData.usuarios?.sin_demografia || 0);
            updateAlertCard('fincasSinPotreros', alertasData.fincas?.sin_potreros || 0);
            updateAlertCard('fincasSinActividad', alertasData.fincas?.sin_actividad_30d || 0);
            updateAlertCard('fincasAreaSospechosa', alertasData.calidad?.areas_sospechosas || 0);
            
            // Calcular total
            const total = (alertasData.usuarios?.sin_finca || 0) + 
                         (alertasData.usuarios?.inactivos_30d || 0) + 
                         (alertasData.usuarios?.nunca_logueados || 0) + 
                         (alertasData.usuarios?.sin_demografia || 0) + 
                         (alertasData.fincas?.sin_potreros || 0) + 
                         (alertasData.fincas?.sin_actividad_30d || 0) + 
                         (alertasData.calidad?.areas_sospechosas || 0);
            
            updateAlertCard('totalAlertas', total);
            
            // Nivel crítico
            let nivel = '🟢 Normal';
            if (total > 200) nivel = '🔴 Crítico';
            else if (total > 100) nivel = '🟠 Alto';
            else if (total > 50) nivel = '🟡 Moderado';
            
            updateAlertCard('nivelCritico', nivel);
            
            console.log('✅ Alertas actualizadas correctamente. Total:', total);
        } else {
            console.error('Error en respuesta de alertas:', alertsResponse);
            showNotification('Error al cargar alertas', 'warning');
        }
        
        // Remove loading state
        removeLoadingFromCards();
        
        showNotification('Dashboard actualizado correctamente', 'success');
        
    } catch (error) {
        console.error('Error al cargar datos del dashboard:', error);
        updateConnectionStatus(false, 'Error de conexión');
        removeLoadingFromCards();
        showNotification('Error de conexión al servidor', 'error');
    }
}

// ===================================
// Dashboard Insights (ECharts)
// ===================================
async function loadDashboardInsights(forceRefresh = false) {
    const cached = insightsCache || getCachedInsights();
    if (!insightsCache && cached) {
        insightsCache = cached;
        renderDashboardInsights(insightsCache);
    }

    const shouldShowLoader = forceRefresh || !insightsCache;
    if (shouldShowLoader) toggleChartsLoading(true);

    try {
        const response = await apiClient.get('dashboard-insights.php', { months: 12 });
        if (response && response.success && response.data) {
            insightsCache = response.data;
            cacheInsightsData(response.data);
            renderDashboardInsights(response.data);
        }
    } catch (error) {
        console.error('Error al cargar insights del dashboard:', error);
        if (!insightsCache) {
            showNotification('Error al cargar insights del dashboard', 'error');
        }
    } finally {
        if (shouldShowLoader) toggleChartsLoading(false);
    }
}

function renderDashboardInsights(data) {
    if (!data) return;
    renderOperationalCharts(data.series || {});
    renderAlertRadars(data.radar || {});
    setTimeout(resizeCharts, 100);
}

function renderOperationalCharts(series) {
    const usuariosSeries = series.usuarios || [];
    const fincasSeries = series.fincas || [];
    const registrosSeries = series.registros || [];

    const usuariosMeses = usuariosSeries.map(item => item.mes);
    const usuariosValores = usuariosSeries.map(item => item.total);
    const fincasMeses = fincasSeries.map(item => item.mes);
    const fincasValores = fincasSeries.map(item => item.total);
    const registrosMeses = registrosSeries.map(item => item.mes);
    const registrosValores = registrosSeries.map(item => item.total);

    renderLineChart('chartUsuariosLine', usuariosMeses, usuariosValores, getChartThemeColors().primary);
    renderLineChart('chartRegistrosLine', registrosMeses, registrosValores, getChartThemeColors().accent);
    renderLineChart('chartFincasLine', fincasMeses, fincasValores, getChartThemeColors().secondary);
}

function renderAlertRadars(radarData) {
    renderRadarChart('chartRadarUsuarios', radarData.usuarios || [], 'Alertas de usuarios');
    renderRadarChart('chartRadarFincas', radarData.fincas || [], 'Alertas de fincas');
}

function renderLineChart(elementId, categories, values, color) {
    const chart = initChartInstance(elementId);
    if (!chart || !categories.length) {
        if (chart) chart.clear();
        return;
    }

    const colors = getChartThemeColors();
    chart.setOption({
        backgroundColor: 'transparent',
        tooltip: { trigger: 'axis', axisPointer: { type: 'line' } },
        grid: { left: 0, right: 0, top: 10, bottom: 10, containLabel: false },
        xAxis: {
            type: 'category',
            data: categories,
            boundaryGap: false,
            axisLine: { show: false },
            axisTick: { show: false },
            axisLabel: { show: false }
        },
        yAxis: {
            type: 'value',
            show: false
        },
        series: [{
            type: 'line',
            data: values,
            smooth: true,
            symbol: 'none',
            lineStyle: { width: 2, color },
            areaStyle: {
                color: new window.echarts.graphic.LinearGradient(0, 0, 0, 1, [
                    { offset: 0, color: hexToRgba(color, 0.35) },
                    { offset: 1, color: hexToRgba(color, 0.05) }
                ])
            }
        }]
    });
    requestAnimationFrame(() => chart.resize());
}

function renderRadarChart(elementId, dataSet, label) {
    const chart = initChartInstance(elementId);
    if (!chart || !dataSet.length) {
        if (chart) chart.clear();
        return;
    }

    const values = dataSet.map(item => item.valor || 0);
    const maxValue = Math.max(...values, 5);
    const indicators = dataSet.map(item => ({
        name: item.categoria,
        max: Math.ceil(maxValue * 1.2) || 5
    }));

    const colors = getChartThemeColors();

    chart.setOption({
        tooltip: {},
        radar: {
            indicator: indicators,
            splitNumber: 4,
            axisName: {
                color: colors.text
            },
            splitLine: {
                lineStyle: {
                    color: hexToRgba(colors.text, 0.25)
                }
            },
            splitArea: { show: false },
            axisLine: {
                lineStyle: {
                    color: hexToRgba(colors.text, 0.2)
                }
            }
        },
        series: [{
            type: 'radar',
            data: [{
                value: values,
                name: label,
                areaStyle: { color: hexToRgba(colors.primary, 0.25) },
                lineStyle: { color: colors.primary, width: 2 },
                itemStyle: { color: colors.primary }
            }]
        }]
    });
    requestAnimationFrame(() => chart.resize());
}

function initChartInstance(elementId) {
    if (typeof window === 'undefined' || !window.echarts) {
        console.warn('ECharts no está disponible en este contexto.');
        return null;
    }
    const el = document.getElementById(elementId);
    if (!el) {
        console.warn(`No se encontró contenedor para el gráfico ${elementId}`);
        return null;
    }
    if (chartInstances[elementId]) {
        return chartInstances[elementId];
    }
    chartInstances[elementId] = window.echarts.init(el);
    return chartInstances[elementId];
}

function cacheInsightsData(data) {
    try {
        const payload = {
            timestamp: Date.now(),
            data
        };
        localStorage.setItem(DASHBOARD_CACHE_KEY, JSON.stringify(payload));
    } catch (_) {
        // Ignorar errores de almacenamiento
    }
}

function getCachedInsights() {
    try {
        const raw = localStorage.getItem(DASHBOARD_CACHE_KEY);
        if (!raw) return null;
        const parsed = JSON.parse(raw);
        if (!parsed || !parsed.timestamp || !parsed.data) {
            localStorage.removeItem(DASHBOARD_CACHE_KEY);
            return null;
        }
        if ((Date.now() - parsed.timestamp) > DASHBOARD_CACHE_TTL) {
            localStorage.removeItem(DASHBOARD_CACHE_KEY);
            return null;
        }
        return parsed.data;
    } catch (_) {
        return null;
    }
}

function setupLogoutCleanup() {
    const logoutLinks = document.querySelectorAll('a[href*="logout.php"]');
    const clear = () => clearDashboardCache();
    logoutLinks.forEach(link => {
        link.addEventListener('click', clear);
    });
}

function clearDashboardCache() {
    try {
        localStorage.removeItem(DASHBOARD_CACHE_KEY);
    } catch (_) {}
}

function toggleChartsLoading(show) {
    const loader = document.getElementById('chartsLoading');
    if (!loader) return;
    loader.classList.toggle('show', show);
}

// ===================================
// Loading States
// ===================================
function addLoadingToCards() {
    const cards = document.querySelectorAll('.stat-card');
    cards.forEach(card => {
        card.classList.add('loading');
    });
}

function removeLoadingFromCards() {
    const cards = document.querySelectorAll('.stat-card');
    cards.forEach(card => {
        card.classList.remove('loading');
    });
}

// ===================================
// Update Alertas
// ===================================
function updateAlertas(alertasData) {
    if (!alertasData) {
        console.log('No hay datos de alertas para actualizar');
        return;
    }
    
    console.log('Datos de alertas recibidos:', alertasData);
    
    const { usuarios, fincas, calidad } = alertasData;
    
    // Actualizar alertas de usuarios
    updateAlertCard('usuariosSinFinca', usuarios?.sin_finca || 0);
    updateAlertCard('usuariosInactivos30', usuarios?.inactivos_30d || 0);
    updateAlertCard('usuariosNuncaLogin', usuarios?.nunca_logueados || 0);
    updateAlertCard('usuariosSinDemo', usuarios?.sin_demografia || 0);
    
    // Actualizar alertas de fincas
    updateAlertCard('fincasSinPotreros', fincas?.sin_potreros || 0);
    updateAlertCard('fincasSinActividad', fincas?.sin_actividad_30d || 0);
    updateAlertCard('fincasAreaSospechosa', calidad?.areas_sospechosas || 0);
    
    // Calcular total de alertas
    const totalAlertas = (usuarios?.sin_finca || 0) + 
                        (usuarios?.inactivos_30d || 0) + 
                        (usuarios?.nunca_logueados || 0) + 
                        (usuarios?.sin_demografia || 0) + 
                        (fincas?.sin_potreros || 0) + 
                        (fincas?.sin_actividad_30d || 0) + 
                        (calidad?.areas_sospechosas || 0);
    
    updateAlertCard('totalAlertas', totalAlertas);
    
    // Determinar nivel crítico
    let nivelCritico = '🟢 Normal';
    if (totalAlertas > 200) {
        nivelCritico = '🔴 Crítico';
    } else if (totalAlertas > 100) {
        nivelCritico = '🟠 Alto';
    } else if (totalAlertas > 50) {
        nivelCritico = '🟡 Moderado';
    }
    
    updateAlertCard('nivelCritico', nivelCritico);
    
    console.log('Alertas actualizadas correctamente. Total:', totalAlertas);
}

function updateAlertCard(cardId, value) {
    console.log(`🔄 Actualizando tarjeta ${cardId} con valor: ${value}`);
    
    const card = document.getElementById(cardId);
    if (!card) {
        console.error(`❌ No se encontró elemento con ID ${cardId}`);
        return;
    }
    
    const valueEl = card.querySelector('.alert-value');
    if (!valueEl) {
        console.error(`❌ No se encontró elemento .alert-value en ${cardId}`);
        return;
    }
    
    try {
        if (typeof value === 'number') {
            // Animar el valor numérico
            animateValue(valueEl, 0, value, 1000);
        } else {
            // Actualizar texto directamente
            valueEl.textContent = value;
        }
        console.log(`✅ Tarjeta ${cardId} actualizada: ${value}`);
    } catch (error) {
        console.error(`❌ Error actualizando tarjeta ${cardId}:`, error);
    }
}

// ===================================
// System Info
// ===================================
function updateSystemInfo(dbInfo) {
    const systemInfoEl = document.getElementById('systemInfo');
    
    if (systemInfoEl && dbInfo) {
        systemInfoEl.innerHTML = `
            <div class="info-item">
                <strong>Base de Datos:</strong>
                <span>PostgreSQL ${dbInfo.version_postgresql?.split(' ')[1] || 'Conectada'}</span>
            </div>
            <div class="info-item">
                <strong>Estado:</strong>
                <span class="status-badge ${dbInfo.conectado ? 'connected' : 'error'}">
                    ${dbInfo.conectado ? 'Conectado' : 'Desconectado'}
                </span>
            </div>
            <div class="info-item">
                <strong>Última actualización:</strong>
                <span>${new Date().toLocaleString()}</span>
            </div>
            <div class="info-item">
                <strong>Servidor:</strong>
                <span>Apache + PHP 8.1+</span>
            </div>
        `;
    }
}

// ===================================
// Tabs Management
// ===================================
function setupTabs() {
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            const tabId = button.getAttribute('data-tab');
            
            // Remove active class from all buttons and contents
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Add active class to clicked button and corresponding content
            button.classList.add('active');
            document.getElementById(`${tabId}-tab`).classList.add('active');
            
            // Load data for the active tab
            if (tabId === 'operational') {
                loadOperationalData();
            } else if (tabId === 'alerts') {
                // Cargar alertas cuando se active la pestaña
                loadAlertsData();
            }
            
            setTimeout(resizeCharts, 150);
        });
    });
}

// ===================================
// Load Alerts Data
// ===================================
async function loadAlertsData() {
    try {
        console.log('Cargando datos de alertas...');
        
        const response = await apiClient.get('alerts.php?tipo=resumen');
        console.log('Respuesta de alertas:', response);
        
        if (response && response.success) {
            updateAlertas(response.data);
            showNotification('Alertas actualizadas', 'success');
            setTimeout(resizeCharts, 120);
        } else {
            showNotification('Error al cargar alertas', 'error');
        }
    } catch (error) {
        console.error('Error al cargar datos de alertas:', error);
        showNotification('Error de conexión al cargar alertas', 'error');
    }
}

// ===================================
// Load Operational Data
// ===================================
async function loadOperationalData() {
    try {
        console.log('Cargando datos operativos...');
        
        const response = await apiClient.get('operational.php');
        console.log('Respuesta operacional:', response);
        
        if (response && response.success) {
            updateOperationalStats(response.data);
            showNotification('Datos operativos actualizados', 'success');
            setTimeout(resizeCharts, 120);
        } else {
            showNotification('Error al cargar datos operativos', 'error');
        }
    } catch (error) {
        console.error('Error al cargar datos operativos:', error);
        showNotification('Error de conexión al cargar datos operativos', 'error');
    }
}

// ===================================
// Update Operational Stats
// ===================================
function updateOperationalStats(data) {
    if (!data) return;
    
    const { usuarios, fincas, potreros, registros } = data;
    
    // Update operational stats
    updateStatCard('totalUsuarios', usuarios.total || 0);
    updateStatCard('usuariosActivos', usuarios.activos || 0);
    updateStatCard('totalFincas', fincas.total_activas || 0);
    updateStatCard('totalPotreros', potreros.total_activos || 0);
    updateStatCard('totalRegistros', registros.total || 0);
    updateStatCard('usuariosAdministradores', usuarios.administradores || 0);
    updateStatCard('usuariosColaboradores', usuarios.colaboradores || 0);
    
    console.log('Estadísticas operativas actualizadas:', data);
}

function updateStatCard(cardId, value) {
    const card = document.getElementById(cardId);
    if (card) {
        animateValue(card, 0, value, 1000);
    }
}

// ===================================
// Search System
// ===================================
function setupSearchSystem() {
    const searchToggle = document.getElementById('search-toggle');
    const searchModal = document.getElementById('search-modal');
    const searchClose = document.getElementById('search-close');
    const searchInput = document.getElementById('search-input');
    const searchClear = document.getElementById('search-clear');
    const searchResults = document.getElementById('search-results');
    const userProfile = document.getElementById('user-profile');

    // Si no existen los elementos de búsqueda, salir silenciosamente
    if (!searchToggle || !searchModal || !searchInput || !searchResults) {
        console.warn('Elementos de búsqueda no encontrados, sistema de búsqueda deshabilitado');
        return;
    }

    let searchTimeout;

    // Open search modal
    searchToggle.addEventListener('click', () => {
        searchModal.classList.add('show');
        setTimeout(() => {
            searchInput.focus();
        }, 300);
    });

    // Close search modal
    function closeSearchModal() {
        searchModal.classList.remove('show');
        searchInput.value = '';
        searchClear.style.display = 'none';
        searchResults.innerHTML = `
            <div class="search-empty">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="M21 21l-4.35-4.35"></path>
                </svg>
                <p>Escribe el nombre del agricultor para buscar</p>
            </div>
        `;
        userProfile.style.display = 'none';
    }

    searchClose.addEventListener('click', closeSearchModal);
    
    // Close on overlay click
    searchModal.addEventListener('click', (e) => {
        if (e.target === searchModal || e.target.classList.contains('search-modal-overlay')) {
            closeSearchModal();
        }
    });

    // Close on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && searchModal.classList.contains('show')) {
            closeSearchModal();
        }
    });

    // Search input events
    searchInput.addEventListener('input', (e) => {
        const query = e.target.value.trim();
        
        if (query.length > 0) {
            searchClear.style.display = 'block';
        } else {
            searchClear.style.display = 'none';
        }

        // Clear previous timeout
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }

        // Debounce search (500ms para evitar múltiples peticiones rápidas)
        if (query.length >= 2) {
            searchTimeout = setTimeout(() => {
                performSearch(query);
            }, 500);
        } else {
            showEmptyState();
        }
    });

    // Clear search
    searchClear.addEventListener('click', () => {
        searchInput.value = '';
        searchClear.style.display = 'none';
        showEmptyState();
        userProfile.style.display = 'none';
        searchResults.style.display = 'block';
        clearNavigationBreadcrumbs();
    });

    // Perform search
    async function performSearch(query) {
        try {
            searchResults.innerHTML = `
                <div class="search-loading">
                    <div class="loading-spinner"></div>
                    <p>Buscando agricultores...</p>
                </div>
            `;

            const response = await apiClient.get(`search.php?q=${encodeURIComponent(query)}&limit=10`);
            
            if (response && response.success) {
                displaySearchResults(response.data);
            } else {
                showSearchError('Error al buscar agricultores');
            }
        } catch (error) {
            console.error('Error en búsqueda:', error);
            // Si es error de conexión, dar mensaje más claro
            if (error.message && error.message.includes('conexión')) {
                showSearchError('Error de conexión. Espera un momento e intenta de nuevo.');
            } else {
                showSearchError('Error al buscar. Intenta de nuevo.');
            }
        }
    }

    // Display search results
    function displaySearchResults(users) {
        if (users.length === 0) {
            searchResults.innerHTML = `
                <div class="search-empty">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="M21 21l-4.35-4.35"></path>
                    </svg>
                    <p>No se encontraron agricultores con ese nombre</p>
                </div>
            `;
            return;
        }

        const resultsHTML = users.map(user => `
            <div class="search-result-item" onclick="showUserProfile(${user.id_usuario})" data-user='${JSON.stringify(user)}'>
                <div class="search-result-avatar">
                    ${user.initials}
                </div>
                <div class="search-result-info">
                    <h4>${user.nombre_completo}</h4>
                    <p>${user.display_info}</p>
                    <small class="status-badge ${getStatusClass(user.estado_usuario)}">${user.estado_usuario}</small>
                </div>
            </div>
        `).join('');

        searchResults.innerHTML = resultsHTML;
    }

    // Show empty state
    function showEmptyState() {
        searchResults.innerHTML = `
            <div class="search-empty">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="M21 21l-4.35-4.35"></path>
                </svg>
                <p>Escribe el nombre del agricultor para buscar</p>
            </div>
        `;
    }

    // Show search error
    function showSearchError(message) {
        searchResults.innerHTML = `
            <div class="search-empty">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="15" y1="9" x2="9" y2="15"></line>
                    <line x1="9" y1="9" x2="15" y2="15"></line>
                </svg>
                <p>${message}</p>
            </div>
        `;
    }

}

// Global function to get status class for styling
function getStatusClass(status) {
    switch (status) {
        case 'Activo': return 'connected';
        case 'Inactivo 30+ días': return 'warning';
        case 'Nunca inició sesión': return 'error';
        default: return 'info';
    }
}

// Global function to show user profile
window.showUserProfile = function(userId) {
    // Find user data from search results
    const userElements = document.querySelectorAll('.search-result-item');
    let userData = null;
    
    userElements.forEach(element => {
        const data = JSON.parse(element.getAttribute('data-user'));
        if (data.id_usuario === userId) {
            userData = data;
        }
    });

    if (!userData) return;

    // Show profile with navigation
    showUserProfileView(userData);
};

// Show user profile view with navigation
function showUserProfileView(userData) {
    // Hide search results and show profile
    document.getElementById('search-results').style.display = 'none';
    document.getElementById('user-profile').style.display = 'block';
    
    // Add navigation breadcrumb
    addNavigationBreadcrumb('profile', userData);
    
    // Clear previous farms data immediately
    clearFarmsData();
    
    // Populate profile data
    populateUserProfile(userData);
}

// Clear farms data to prevent showing previous user's farms
function clearFarmsData() {
    const farmsList = document.getElementById('farms-list');
    farmsList.innerHTML = `
        <div class="loading-farms">
            <div class="loading-spinner"></div>
            <p>Cargando fincas...</p>
        </div>
    `;
}

// Global function to go back to search
window.goBackToSearch = function() {
    // Close farm modal if open
    const farmModal = document.getElementById('farm-main-modal');
    if (farmModal.classList.contains('show')) {
        farmModal.classList.remove('show');
        cleanupFarmMap(); // Clean up map
    }
    
    // Show search modal
    const searchModal = document.getElementById('search-modal');
    searchModal.classList.add('show');
    
    // Reset search modal content
    document.getElementById('search-results').style.display = 'block';
    document.getElementById('user-profile').style.display = 'none';
    
    // Clear navigation
    clearNavigationBreadcrumbs();
    
    // Focus search input
    document.getElementById('search-input').focus();
};

// ===================================
// Chart Utilities & Helpers
// ===================================
function getChartThemeColors() {
    const isDark = (document.documentElement.getAttribute('data-theme') || 'dark') === 'dark';
    return {
        text: isDark ? '#e5e7eb' : '#1f2937',
        grid: isDark ? '#1f2937' : '#f3f4f6',
        primary: '#6dbe45',
        secondary: '#4da1d9',
        accent: '#a4d65e'
    };
}

function hexToRgba(hex, alpha = 1) {
    let sanitized = hex.replace('#', '');
    if (sanitized.length === 3) {
        sanitized = sanitized.split('').map(c => c + c).join('');
    }
    const bigint = parseInt(sanitized, 16);
    const r = (bigint >> 16) & 255;
    const g = (bigint >> 8) & 255;
    const b = bigint & 255;
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

function resizeCharts() {
    Object.values(chartInstances).forEach(instance => {
        if (instance && typeof instance.resize === 'function') {
            instance.resize();
        }
    });
}

window.addEventListener('resize', throttle(resizeCharts, 200));

function throttle(fn, wait = 200) {
    let lastTime = 0;
    return function throttled(...args) {
        const now = Date.now();
        if (now - lastTime >= wait) {
            lastTime = now;
            fn.apply(this, args);
        }
    };
}

// Global function to show farm details
window.showFarmDetails = function(farmId, farmName) {
    // Hide profile and show farm details
    document.getElementById('user-profile').style.display = 'none';
    // Create farm details view dynamically
    showFarmDetailsView(farmId, farmName);
};

// Populate user profile
function populateUserProfile(user) {
    document.getElementById('profile-initials').textContent = user.initials;
    document.getElementById('profile-name').textContent = user.nombre_completo;
    document.getElementById('profile-location').textContent = user.nombre_pais || user.ubicacion_general || 'Ubicación no especificada';
    document.getElementById('profile-status').textContent = user.estado_usuario;
    document.getElementById('profile-status').className = `status-badge ${getStatusClass(user.estado_usuario)}`;
    
    document.getElementById('profile-email').textContent = user.email || 'No especificado';
    document.getElementById('profile-phone').textContent = user.telefono || 'No especificado';
    document.getElementById('profile-ubicacion').textContent = user.ubicacion_general || 'No especificada';
    document.getElementById('profile-fecha-registro').textContent = user.fecha_registro;
    document.getElementById('profile-ultima-sesion').textContent = user.ultima_sesion;
    document.getElementById('profile-codigo').textContent = user.codigo_telegan || 'No asignado';
    
    // Load user's farms
    loadUserFarms(user.id_usuario);
}

// Load user farms
async function loadUserFarms(userId) {
    try {
        const response = await apiClient.get(`user-farms.php?user_id=${userId}`);
        
        if (response && response.success) {
            displayUserFarms(response.data);
        } else {
            showFarmsError('Error al cargar fincas');
        }
    } catch (error) {
        console.error('Error al cargar fincas:', error);
        showFarmsError('Error de conexión');
    }
}

// Display user farms
function displayUserFarms(farms) {
    const farmsList = document.getElementById('farms-list');
    
    if (farms.length === 0) {
        farmsList.innerHTML = `
            <div class="no-farms">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                </svg>
                <p>Este agricultor no tiene fincas registradas</p>
            </div>
        `;
        updateFarmsButton('no-farms');
        return;
    }

    const farmsHTML = farms.map(farm => `
        <div class="farm-item" onclick="showFarmDetails(${farm.id_finca}, '${farm.nombre_finca}')" data-farm='${JSON.stringify(farm)}'>
            <div class="farm-info">
                <h5>${farm.nombre_finca}</h5>
                <p>${farm.display_info}</p>
                <small>Rol: ${farm.rol_text} • Creada: ${farm.fecha_creacion}</small>
            </div>
            <div class="farm-status">
                <span class="status-badge ${farm.estado_class}">${farm.estado_text}</span>
                <div class="farm-actions">
                    <button class="farm-action-btn" onclick="event.stopPropagation(); showFarmDetails(${farm.id_finca}, '${farm.nombre_finca}')" title="Ver detalles">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    `).join('');

    farmsList.innerHTML = farmsHTML;
    updateFarmsButton('has-farms');
}

// Show farms error
function showFarmsError(message) {
    document.getElementById('farms-list').innerHTML = `
        <div class="no-farms">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="15" y1="9" x2="9" y2="15"></line>
                <line x1="9" y1="9" x2="15" y2="15"></line>
            </svg>
            <p>${message}</p>
        </div>
    `;
}

// Global function to refresh user farms
window.refreshUserFarms = function() {
    // Get current user ID from the profile
    const profileName = document.getElementById('profile-name').textContent;
    // Find user data and reload farms
    const userElements = document.querySelectorAll('.search-result-item');
    userElements.forEach(element => {
        const data = JSON.parse(element.getAttribute('data-user'));
        if (data.nombre_completo === profileName) {
            loadUserFarms(data.id_usuario);
        }
    });
};

// Global function to load current user farms (when no farms are shown)
window.loadCurrentUserFarms = function() {
    refreshUserFarms();
};

// Update farms button based on state
function updateFarmsButton(state) {
    const farmsButton = document.querySelector('button[onclick*="refreshUserFarms"], button[onclick*="loadCurrentUserFarms"]');
    if (!farmsButton) return;
    
    if (state === 'no-farms') {
        farmsButton.innerHTML = `
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
            </svg>
            Ver Fincas
        `;
        farmsButton.setAttribute('onclick', 'loadCurrentUserFarms()');
    } else if (state === 'has-farms') {
        farmsButton.innerHTML = `
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="23 4 23 10 17 10"></polyline>
                <polyline points="1 20 1 14 7 14"></polyline>
                <path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"></path>
            </svg>
            Actualizar Fincas
        `;
        farmsButton.setAttribute('onclick', 'refreshUserFarms()');
    }
}

// Navigation functions
function addNavigationBreadcrumb(type, data) {
    const breadcrumbs = document.getElementById('navigation-breadcrumbs');
    const currentBreadcrumb = document.getElementById('current-breadcrumb');
    
    breadcrumbs.style.display = 'flex';
    
    if (type === 'profile') {
        currentBreadcrumb.innerHTML = `<span>${data.nombre_completo}</span>`;
    }
}

function clearNavigationBreadcrumbs() {
    const breadcrumbs = document.getElementById('navigation-breadcrumbs');
    breadcrumbs.style.display = 'none';
}

// Farm details view - Show main farm modal
function showFarmDetailsView(farmId, farmName) {
    console.log('Mostrar detalles de finca:', farmId, farmName);
    
    // Clean up any existing map FIRST
    cleanupFarmMap();
    
    // Hide search modal
    const searchModal = document.getElementById('search-modal');
    searchModal.classList.remove('show');
    
    // Show farm modal
    const farmModal = document.getElementById('farm-main-modal');
    farmModal.classList.add('show');
    
    // Load farm data with a small delay to ensure DOM is ready
    setTimeout(() => {
        loadFarmDetails(farmId);
    }, 100);
}

// Load farm details from API
async function loadFarmDetails(farmId) {
    try {
        // Show loading state
        document.getElementById('farm-name').textContent = 'Cargando...';
        document.getElementById('farm-location').textContent = 'Obteniendo datos...';
        
        const response = await apiClient.get(`farm-details.php?farm_id=${farmId}`);
        
        if (response && response.success) {
            populateFarmDetails(response.data);
            showNotification('Datos de finca cargados', 'success');
        } else {
            showNotification('Error al cargar datos de finca', 'error');
        }
    } catch (error) {
        console.error('Error al cargar detalles de finca:', error);
        showNotification('Error de conexión', 'error');
    }
}

// Populate farm details in modal
function populateFarmDetails(data) {
    const { farm, administrators, collaborators, paddocks, stats } = data;
    
    // Basic farm info
    document.getElementById('farm-main-title').textContent = farm.nombre_finca;
    document.getElementById('farm-main-subtitle').textContent = farm.display_info.pais + ' • ' + farm.display_info.area;
    document.getElementById('farm-name').textContent = farm.nombre_finca;
    document.getElementById('farm-location').textContent = farm.nombre_pais || 'Ubicación no especificada';
    document.getElementById('farm-status').textContent = farm.estado_text;
    document.getElementById('farm-status').className = `status-badge ${farm.estado_class}`;
    document.getElementById('farm-area').textContent = farm.display_info.area;
    
    // Farm details
    populateFarmDetailsCard(farm);
    
    // Administrators
    populateUsersList('farm-admins', administrators);
    
    // Collaborators
    populateUsersList('farm-collaborators', collaborators);
    
    // Paddocks
    populatePaddocksList('farm-paddocks', paddocks);
    
    // Map
    initializeFarmMap(farm);
    
    console.log('Datos de finca cargados:', data);
}

// Sanitize HTML to prevent XSS
function sanitizeHTML(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// Populate farm details card
function populateFarmDetailsCard(farm) {
    const detailsContainer = document.getElementById('farm-details');
    detailsContainer.innerHTML = `
        <div class="detail-item">
            <label>Descripción</label>
            <span>${sanitizeHTML(farm.descripcion || 'No especificada')}</span>
        </div>
        <div class="detail-item">
            <label>Área Total</label>
            <span>${sanitizeHTML(farm.display_info.area)}</span>
        </div>
        <div class="detail-item">
            <label>Estado</label>
            <span>${sanitizeHTML(farm.estado_text)}</span>
        </div>
        <div class="detail-item">
            <label>Fecha Creación</label>
            <span>${sanitizeHTML(farm.fecha_creacion)}</span>
        </div>
        <div class="detail-item">
            <label>Código Telegan</label>
            <span>${sanitizeHTML(farm.codigo_telegan || 'No asignado')}</span>
        </div>
        <div class="detail-item">
            <label>Creador</label>
            <span>${sanitizeHTML(farm.creador_nombre || 'No especificado')}</span>
        </div>
    `;
}

// Populate users list
function populateUsersList(containerId, users) {
    const container = document.getElementById(containerId);
    
    if (users.length === 0) {
        container.innerHTML = `
            <div class="no-users">
                <p>No hay usuarios registrados</p>
            </div>
        `;
        return;
    }
    
    const usersHTML = users.map(user => `
        <div class="user-item">
            <div class="user-avatar">
                ${sanitizeHTML(user.initials)}
            </div>
            <div class="user-info">
                <h5>${sanitizeHTML(user.nombre_completo)}</h5>
                <p>${sanitizeHTML(user.email || 'No especificado')} • ${sanitizeHTML(user.nombre_pais || user.ubicacion_general || 'Sin ubicación')}</p>
                <small>Asociado: ${sanitizeHTML(user.fecha_asociacion)}</small>
            </div>
        </div>
    `).join('');
    
    container.innerHTML = usersHTML;
}

// Populate paddocks list
function populatePaddocksList(containerId, paddocks) {
    const container = document.getElementById(containerId);
    
    if (paddocks.length === 0) {
        container.innerHTML = `
            <div class="no-paddocks">
                <p>No hay potreros registrados</p>
            </div>
        `;
        return;
    }
    
    const paddocksHTML = paddocks.map(paddock => `
        <div class="paddock-item">
            <div class="paddock-info">
                <h5>${sanitizeHTML(paddock.nombre_potrero)}</h5>
                <p>${sanitizeHTML(paddock.display_info)}</p>
                <small>Creado: ${sanitizeHTML(paddock.fecha_creacion)} • Último registro: ${sanitizeHTML(paddock.ultimo_registro)}</small>
            </div>
            <div class="paddock-status">
                <span class="status-badge ${sanitizeHTML(paddock.estado_class)}">${sanitizeHTML(paddock.estado_text)}</span>
            </div>
        </div>
    `).join('');
    
    container.innerHTML = paddocksHTML;
}

// ===================================
// Map Functions
// ===================================
// Global variable to store map instance
let currentFarmMap = null;

function initializeFarmMap(farm) {
    console.log('Initializing farm map for:', farm.nombre_finca);
    
    const mapContainer = document.getElementById('farm-map');
    
    // Clear previous map instance (defensive)
    if (currentFarmMap) {
        try {
            currentFarmMap.remove();
            currentFarmMap = null;
        } catch (error) {
            console.warn('Error removing previous map:', error);
            currentFarmMap = null;
        }
    }
    
    // Clear container content
    mapContainer.innerHTML = '';
    
    // Check if farm has geometry
    if (!farm.geometria_wkt && !farm.geometria_postgis && !farm.geojson) {
        console.log('No geometry data available');
        showMapNoGeometry(mapContainer, farm);
        return;
    }
    
    // Initialize map with error handling
    try {
        // Double check that container is empty and ready
        if (mapContainer.children.length > 0) {
            console.warn('Map container not empty, clearing again...');
            mapContainer.innerHTML = '';
        }
        
        currentFarmMap = L.map('farm-map', {
            center: [15.45, -90.35], // Default center (Guatemala)
            zoom: 15,
            zoomControl: true,
            attributionControl: true
        });
        console.log('Map initialized successfully');
    } catch (error) {
        console.error('Error initializing map:', error);
        showMapError(mapContainer, 'Error al inicializar el mapa');
        return;
    }
    
    // Add satellite tile layer (Esri World Imagery)
    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: '© Esri &mdash; Source: Esri, DigitalGlobe, GeoEye, Earthstar Geographics, CNES/Airbus DS, USDA, USGS, AeroGRID, IGN, and the GIS User Community',
        maxZoom: 18
    }).addTo(currentFarmMap);
    
    // Parse and add geometry
    try {
        const geometry = parseFarmGeometry(farm);
        if (geometry) {
            const success = addGeometryToMap(currentFarmMap, geometry, farm);
            if (!success) {
                showMapError(mapContainer, 'Error al procesar geometría');
            }
        } else {
            showMapNoGeometry(mapContainer, farm);
        }
    } catch (error) {
        console.error('Error al procesar mapa:', error);
        showMapError(mapContainer, 'Error al cargar mapa: ' + error.message);
    }
}

// Parse farm geometry from GeoJSON or fallback to WKT
function parseFarmGeometry(farm) {
    console.log('Parsing farm geometry:', farm);
    
    // Prefer GeoJSON (from PostGIS)
    if (farm.geojson) {
        console.log('Using GeoJSON geometry:', farm.geojson);
        // If it's a Geometry object, wrap it in a Feature for consistency
        if (farm.geojson.type === 'Point' || farm.geojson.type === 'Polygon' || farm.geojson.type === 'MultiPolygon') {
            return {
                type: 'Feature',
                geometry: farm.geojson,
                properties: {}
            };
        }
        // If it's already a Feature or FeatureCollection, return as is
        return farm.geojson;
    }
    
    // Fallback to WKT parsing
    let wktString = null;
    
    if (farm.geometria_postgis) {
        wktString = farm.geometria_postgis;
        console.log('Using PostGIS WKT geometry:', wktString);
    } else if (farm.geometria_wkt) {
        wktString = farm.geometria_wkt;
        console.log('Using WKT geometry:', wktString);
    }
    
    if (!wktString) {
        console.log('No geometry data available');
        return null;
    }
    
    // Clean and validate WKT string
    wktString = wktString.trim();
    if (wktString === '' || wktString.toLowerCase() === 'null') {
        console.log('Empty or null geometry');
        return null;
    }
    
    // Parse WKT to Leaflet coordinates
    return parseWKTToLeaflet(wktString);
}

// Parse WKT string to Leaflet format
function parseWKTToLeaflet(wktString) {
    try {
        console.log('Parsing WKT:', wktString);
        
        // Remove POLYGON wrapper and parse coordinates
        const coordMatch = wktString.match(/POLYGON\(\(([^)]+)\)\)/);
        if (!coordMatch) {
            throw new Error('Invalid WKT format');
        }
        
        const coordString = coordMatch[1];
        console.log('Coordinate string:', coordString);
        
        // Split by comma and handle both formats (with and without spaces after comma)
        const coords = coordString.split(',').map(coord => {
            // Clean coordinate string and split
            const cleanCoord = coord.trim();
            const parts = cleanCoord.split(/\s+/);
            
            if (parts.length !== 2) {
                console.error('Invalid coordinate format:', cleanCoord);
                throw new Error(`Invalid coordinate: ${cleanCoord}`);
            }
            
            const lng = parseFloat(parts[0]);
            const lat = parseFloat(parts[1]);
            
            console.log(`Parsed coord: lng=${lng}, lat=${lat}`);
            
            // Validate coordinates
            if (isNaN(lng) || isNaN(lat)) {
                console.error('NaN coordinates:', { lng, lat, original: cleanCoord });
                throw new Error(`Invalid coordinates: ${cleanCoord}`);
            }
            
            if (lng < -180 || lng > 180 || lat < -90 || lat > 90) {
                console.error('Out of range coordinates:', { lng, lat });
                throw new Error(`Coordinates out of range: lng=${lng}, lat=${lat}`);
            }
            
            return [lat, lng]; // Leaflet uses [lat, lng] format
        });
        
        console.log('Final coordinates:', coords);
        
        // Validate that we have at least 3 points for a polygon
        if (coords.length < 3) {
            throw new Error('Polygon needs at least 3 points');
        }
        
        return {
            type: 'polygon',
            coordinates: coords
        };
    } catch (error) {
        console.error('Error parsing WKT:', error, 'WKT string:', wktString);
        return null;
    }
}

// Add geometry to map
function addGeometryToMap(map, geometry, farm) {
    console.log('Adding geometry to map:', geometry);
    console.log('Geometry type:', geometry?.type);
    console.log('Geometry keys:', Object.keys(geometry || {}));
    
    let leafletLayer;
    
    try {
        // Check if it's GeoJSON Feature format
        if (geometry && geometry.type === 'Feature') {
            console.log('Adding GeoJSON Feature layer');
            leafletLayer = L.geoJSON(geometry, {
                style: {
                    color: '#6dbe45',
                    weight: 3,
                    opacity: 0.8,
                    fillColor: '#6dbe45',
                    fillOpacity: 0.2
                }
            });
        } 
        // Check if it's GeoJSON FeatureCollection format
        else if (geometry && geometry.type === 'FeatureCollection') {
            console.log('Adding GeoJSON FeatureCollection layer');
            leafletLayer = L.geoJSON(geometry, {
                style: {
                    color: '#6dbe45',
                    weight: 3,
                    opacity: 0.8,
                    fillColor: '#6dbe45',
                    fillOpacity: 0.2
                }
            });
        }
        // Check if it's a Geometry object (from PostGIS GeoJSON)
        else if (geometry && geometry.type && geometry.coordinates) {
            console.log('Adding Geometry object layer:', geometry.type);
            // Wrap in a Feature for Leaflet
            const feature = {
                type: 'Feature',
                geometry: geometry,
                properties: {}
            };
            leafletLayer = L.geoJSON(feature, {
                style: {
                    color: '#6dbe45',
                    weight: 3,
                    opacity: 0.8,
                    fillColor: '#6dbe45',
                    fillOpacity: 0.2
                }
            });
        }
        // Fallback to manual polygon creation (for WKT)
        else if (geometry && geometry.type === 'polygon' && geometry.coordinates) {
            console.log('Creating polygon from coordinates');
            
            // Validate coordinates before creating polygon
            const validCoords = geometry.coordinates.filter(coord => {
                return Array.isArray(coord) && 
                       coord.length === 2 && 
                       !isNaN(coord[0]) && !isNaN(coord[1]) &&
                       coord[0] >= -90 && coord[0] <= 90 &&
                       coord[1] >= -180 && coord[1] <= 180;
            });
            
            if (validCoords.length < 3) {
                console.error('Not enough valid coordinates for polygon:', validCoords);
                return false;
            }
            
            console.log('Creating polygon with coordinates:', validCoords);
            
            leafletLayer = L.polygon(validCoords, {
                color: '#6dbe45',
                weight: 3,
                opacity: 0.8,
                fillColor: '#6dbe45',
                fillOpacity: 0.2
            });
        } else {
            console.error('Invalid geometry format:', geometry);
            console.error('Expected: Feature, FeatureCollection, or Geometry object with type and coordinates');
            console.error('Received type:', geometry?.type);
            console.error('Has coordinates:', !!geometry?.coordinates);
            return false;
        }
        
        if (leafletLayer) {
            leafletLayer.addTo(map);
            
            // Add popup with farm info
            leafletLayer.bindPopup(`
                <div class="map-popup">
                    <h4>${sanitizeHTML(farm.nombre_finca)}</h4>
                    <p><strong>Área:</strong> ${sanitizeHTML(farm.display_info.area)}</p>
                    <p><strong>Estado:</strong> ${sanitizeHTML(farm.estado_text)}</p>
                    <p><strong>País:</strong> ${sanitizeHTML(farm.nombre_pais || 'No especificado')}</p>
                </div>
            `);
            
            // Fit map to geometry bounds
            try {
                map.fitBounds(leafletLayer.getBounds(), {
                    padding: [20, 20]
                });
            } catch (boundsError) {
                console.error('Error fitting bounds:', boundsError);
                // Fallback to default center
                map.setView([15.45, -90.35], 15);
            }
            
            return true;
        }
    } catch (error) {
        console.error('Error adding geometry to map:', error);
        return false;
    }
    
    return false;
}

// Show map when no geometry available
function showMapNoGeometry(container, farm) {
    // Store farm data globally for button click
    window.currentFarmForMap = farm;
    
    container.innerHTML = `
        <div class="map-no-geometry">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                <circle cx="12" cy="10" r="3"></circle>
            </svg>
            <h4>Sin Ubicación Geográfica</h4>
            <p>Esta finca no tiene coordenadas registradas</p>
            <small>${sanitizeHTML(farm.nombre_finca)} - ${sanitizeHTML(farm.nombre_pais || 'País no especificado')}</small>
            ${farm.tiene_geometria ? `
                <button class="btn btn-primary load-map-btn" onclick="loadFarmMapWithGeometry(${farm.id_finca})" style="margin-top: 16px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                    Cargar Mapa
                </button>
            ` : `
                <div class="map-suggestion">
                    <p>💡 <strong>Sugerencia:</strong> Contactar al administrador para agregar ubicación GPS</p>
                </div>
            `}
        </div>
    `;
}

// Load farm map with geometry from API
async function loadFarmMapWithGeometry(farmId) {
    const mapContainer = document.getElementById('farm-map');
    
    // Show loading state
    mapContainer.innerHTML = `
        <div class="map-loading">
            <div class="loading-spinner"></div>
            <p>Cargando mapa...</p>
        </div>
    `;
    
    try {
        const api = new ApiClient();
        const response = await api.get(`farm-details.php?farm_id=${farmId}`);
        
        if (response.success && response.data && response.data.farm) {
            const farm = response.data.farm;
            
            // Check if we have geometry
            if (!farm.geojson && !farm.geometria_wkt && !farm.geometria_postgis) {
                showMapNoGeometry(mapContainer, farm);
                showNotification('Esta finca no tiene geometría disponible', 'warning');
                return;
            }
            
            // Initialize map with satellite layer
            initializeFarmMapWithSatellite(farm);
        } else {
            throw new Error('No se pudieron cargar los datos de la finca');
        }
    } catch (error) {
        console.error('Error loading farm map:', error);
        showMapError(mapContainer, 'Error al cargar el mapa. Por favor, intenta nuevamente.');
        showNotification('Error al cargar el mapa', 'error');
    }
}

// Initialize farm map with satellite layer
function initializeFarmMapWithSatellite(farm) {
    const mapContainer = document.getElementById('farm-map');
    
    // Clear previous map
    if (currentFarmMap) {
        try {
            currentFarmMap.remove();
        } catch (error) {
            console.warn('Error removing previous map:', error);
        }
        currentFarmMap = null;
    }
    
    // Clear container
    mapContainer.innerHTML = '';
    
    try {
        // Initialize map
        currentFarmMap = L.map('farm-map', {
            center: [15.45, -90.35], // Default center (Guatemala)
            zoom: 15,
            zoomControl: true,
            attributionControl: true
        });
        
        // Add satellite tile layer (Esri World Imagery)
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: '© Esri &mdash; Source: Esri, DigitalGlobe, GeoEye, Earthstar Geographics, CNES/Airbus DS, USDA, USGS, AeroGRID, IGN, and the GIS User Community',
            maxZoom: 18
        }).addTo(currentFarmMap);
        
        // Get geometry
        const geometry = parseFarmGeometry(farm);
        
        if (geometry) {
            // Add geometry to map
            const added = addGeometryToMap(currentFarmMap, geometry, farm);
            
            if (added) {
                // Fit map to geometry bounds
                if (geometry.type === 'Feature' && geometry.geometry) {
                    const geoJSONLayer = L.geoJSON(geometry);
                    const bounds = geoJSONLayer.getBounds();
                    if (bounds.isValid()) {
                        currentFarmMap.fitBounds(bounds, { padding: [20, 20] });
                    }
                } else if (geometry.type === 'polygon' && geometry.coordinates) {
                    const polygonLayer = L.polygon(geometry.coordinates);
                    const bounds = polygonLayer.getBounds();
                    if (bounds.isValid()) {
                        currentFarmMap.fitBounds(bounds, { padding: [20, 20] });
                    }
                }
                
                showNotification('Mapa cargado correctamente', 'success');
            } else {
                throw new Error('No se pudo agregar la geometría al mapa');
            }
        } else {
            throw new Error('No se pudo parsear la geometría');
        }
    } catch (error) {
        console.error('Error initializing map:', error);
        showMapError(mapContainer, 'Error al inicializar el mapa: ' + error.message);
        showNotification('Error al cargar el mapa', 'error');
    }
}

// Show map error
function showMapError(container, message) {
    container.innerHTML = `
        <div class="map-error">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="15" y1="9" x2="9" y2="15"></line>
                <line x1="9" y1="9" x2="15" y2="15"></line>
            </svg>
            <h4>Error al Cargar Mapa</h4>
            <p>${message}</p>
            <button class="btn btn-secondary" onclick="retryMapLoad()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="23 4 23 10 17 10"></polyline>
                    <polyline points="1 20 1 14 7 14"></polyline>
                    <path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"></path>
                </svg>
                Reintentar
            </button>
        </div>
    `;
}

// Global function to retry map load
window.retryMapLoad = function() {
    // Get current farm data and reload map
    const farmName = document.getElementById('farm-name').textContent;
    if (farmName && farmName !== 'Cargando...') {
        // This would need to be implemented to get current farm data
        showNotification('Función de reintento en desarrollo', 'info');
    }
};

// Function to clean up map when modal is closed
function cleanupFarmMap() {
    console.log('Cleaning up farm map...');
    
    if (currentFarmMap) {
        try {
            console.log('Removing existing map instance');
            currentFarmMap.remove();
            currentFarmMap = null;
        } catch (error) {
            console.error('Error removing map:', error);
            currentFarmMap = null;
        }
    }
    
    // Also clear the map container HTML
    const mapContainer = document.getElementById('farm-map');
    if (mapContainer) {
        mapContainer.innerHTML = '';
        console.log('Cleared map container HTML');
    }
    
    console.log('Map cleanup completed');
}

// ===================================
// Farm Modal Setup
// ===================================
function setupFarmModal() {
    const farmModal = document.getElementById('farm-main-modal');
    const farmClose = document.getElementById('farm-close');
    
    // Si no existen los elementos del modal, salir silenciosamente
    if (!farmModal || !farmClose) {
        console.warn('Elementos del modal de finca no encontrados, sistema deshabilitado');
        return;
    }
    
    // Close farm modal
    farmClose.addEventListener('click', () => {
        farmModal.classList.remove('show');
        cleanupFarmMap(); // Clean up map
        // Go back to search
        goBackToSearch();
    });
    
    // Close on overlay click
    farmModal.addEventListener('click', (e) => {
        if (e.target === farmModal || e.target.classList.contains('farm-modal-overlay')) {
            farmModal.classList.remove('show');
            cleanupFarmMap(); // Clean up map
            goBackToSearch();
        }
    });
    
    // Close on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && farmModal.classList.contains('show')) {
            farmModal.classList.remove('show');
            cleanupFarmMap(); // Clean up map
            goBackToSearch();
        }
    });
}

// ===================================
// Event Listeners
// ===================================
function setupEventListeners() {
    // Setup tabs
    setupTabs();
    
    // Setup search system
    setupSearchSystem();
    
    // Setup farm modal
    setupFarmModal();
    
    // Mobile menu toggle
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const collapseToggle = document.getElementById('collapseToggle');
    
    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            // Close on outside click
            if (sidebar.classList.contains('open')) {
                document.addEventListener('click', closeSidebarOnOutsideClick);
            } else {
                document.removeEventListener('click', closeSidebarOnOutsideClick);
            }
        });
    }

    // Desktop collapse toggle (viñeta persistente)
    if (collapseToggle) {
        collapseToggle.addEventListener('click', () => {
            document.body.classList.toggle('sidebar-collapsed');
            try {
                localStorage.setItem('sidebarCollapsed', document.body.classList.contains('sidebar-collapsed') ? 'true' : 'false');
            } catch (_) {}
        });
    }
    
    // Refresh button
    const refreshBtn = document.getElementById('refreshBtn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', () => {
            loadDashboardData();
            
            // Add rotation animation
            refreshBtn.style.transform = 'rotate(360deg)';
            setTimeout(() => {
                refreshBtn.style.transform = '';
            }, 500);
        });
    }
    
    // Card hover effects
    const cards = document.querySelectorAll('.stat-card, .status-card, .system-card');
    cards.forEach(card => {
        card.addEventListener('mouseenter', () => {
            card.style.transform = 'translateY(-4px)';
        });
        
        card.addEventListener('mouseleave', () => {
            card.style.transform = 'translateY(0)';
        });
    });
    
    // Auto refresh every 30 seconds
    setInterval(() => {
        loadDashboardData();
    }, 30000);
}

// ===================================
// Sidebar Management
// ===================================
function closeSidebarOnOutsideClick(event) {
    const sidebar = document.getElementById('sidebar');
    const menuToggle = document.getElementById('menuToggle');
    
    if (sidebar && !sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
        sidebar.classList.remove('open');
        document.removeEventListener('click', closeSidebarOnOutsideClick);
    }
}

// ===================================
// Loading Animations
// ===================================
function addLoadingAnimations() {
    // Add staggered animation to cards
    const cards = document.querySelectorAll('.stat-card');
    cards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
        card.classList.add('animate-in');
    });
    
    // Add pulse animation to status dot
    const statusDot = document.querySelector('.status-dot');
    if (statusDot) {
        statusDot.style.animation = 'pulse 2s infinite';
    }
}

// ===================================
// Utility Functions
// ===================================
function formatNumber(num) {
    return new Intl.NumberFormat('es-ES').format(num);
}

function formatDate(date) {
    return new Intl.DateTimeFormat('es-ES', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    }).format(new Date(date));
}

// ===================================
// Mobile Navigation
// ===================================
function setupMobileNavigation() {
    const navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            
            // Remove active class from all items
            navItems.forEach(nav => nav.classList.remove('active'));
            
            // Add active class to clicked item
            item.classList.add('active');
            
            // Show notification
            const navText = item.querySelector('.nav-text').textContent;
            showNotification(`Navegando a ${navText}`, 'info');
        });
    });
}

// Initialize mobile navigation
document.addEventListener('DOMContentLoaded', () => {
    setupMobileNavigation();
});