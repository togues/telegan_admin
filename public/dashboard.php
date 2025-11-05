<?php
/**
 * Dashboard Principal - Con sesión PHP
 */

// IMPORTANTE: Iniciar sesión ANTES de cualquier output
session_start();

// Incluir Security para headers CSP (debe ser antes de cualquier output HTML)
require_once '../src/Config/Security.php';
Security::init();

// Manejar logout
if (isset($_GET['logout']) && $_GET['logout'] == '1') {
    session_destroy();
    header('Location: ../auth/login.php');
    exit;
}

// Verificar autenticación
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../auth/login.php');
    exit;
}

// Obtener datos del usuario
$userName = $_SESSION['admin_nombre'] ?? $_SESSION['admin_name'] ?? 'Usuario';
$userEmail = $_SESSION['admin_email'] ?? '';
$userRole = $_SESSION['admin_rol'] ?? $_SESSION['admin_role'] ?? 'TECNICO';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Telegan Admin</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script>
        // Variables de sesión para JavaScript
        window.userSession = {
            loggedIn: true,
            userName: <?php echo json_encode($userName); ?>,
            userEmail: <?php echo json_encode($userEmail); ?>,
            userRole: <?php echo json_encode($userRole); ?>,
            userId: <?php echo json_encode($_SESSION['admin_user_id'] ?? $_SESSION['admin_id'] ?? null); ?>
        };
        
        // Token de sesión desde PHP (generado en login)
        <?php 
        $sessionToken = $_SESSION['session_token'] ?? null;
        if ($sessionToken) {
            echo "window.sessionToken = " . json_encode($sessionToken) . ";\n";
        }
        ?>
        
        // El tema se maneja ahora con theme-common.js
    </script>
</head>
<body>
    

    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="header-left">
                <button class="menu-toggle" id="menuToggle" title="Menú">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <div class="logo">
                    <img src="https://assets.zyrosite.com/cdn-cgi/image/format=auto,w=768,fit=crop,q=95/Awv47aJZwzHGVp4M/logos-mk3vk7Qp1gS7Jann.png" alt="Telegan">
                    <span class="logo-text">Telegan Admin</span>
                </div>
            </div>
            
            <div class="header-right">
                <div class="tools">
                    <button id="search-toggle" class="tool-btn" aria-label="Buscar">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="M21 21l-4.35-4.35"></path>
                        </svg>
                    </button>
                    <button id="theme-toggle" class="tool-btn" aria-label="Tema">
                        <svg class="sun-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="5"></circle>
                            <line x1="12" y1="1" x2="12" y2="3"></line>
                            <line x1="12" y1="21" x2="12" y2="23"></line>
                            <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                            <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                            <line x1="1" y1="12" x2="3" y2="12"></line>
                            <line x1="21" y1="12" x2="23" y2="12"></line>
                            <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                            <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                        </svg>
                        <svg class="moon-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                        </svg>
                    </button>
                </div>
                <div class="user-info">
                    <div class="user-avatar">
                        <span><?php echo strtoupper(substr($userName, 0, 2)); ?></span>
                    </div>
                    <span class="user-name"><?php echo htmlspecialchars($userName); ?></span>
                </div>
            </div>
        </div>
    </header>

    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-content">
            <div class="sidebar-tools">
                <button class="sidebar-handle" id="collapseToggle" title="Colapsar/expandir">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                </button>
            </div>
            <ul class="sidebar-menu">
                <li class="menu-item active">
                    <a href="#" class="menu-link">
                        <svg class="menu-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="7" height="7"></rect>
                            <rect x="14" y="3" width="7" height="7"></rect>
                            <rect x="14" y="14" width="7" height="7"></rect>
                            <rect x="3" y="14" width="7" height="7"></rect>
                        </svg>
                        <span class="menu-text">Dashboard</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="modules/users/" class="menu-link">
                        <svg class="menu-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        <span class="menu-text">Usuarios</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="modules/system-users/" class="menu-link">
                        <svg class="menu-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="8.5" cy="7" r="4"></circle>
                            <path d="M20 8v6M23 11h-6"></path>
                        </svg>
                        <span class="menu-text">Administradores</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="modules/farms/" class="menu-link">
                        <svg class="menu-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                            <polyline points="3.27,6.96 12,12.01 20.73,6.96"></polyline>
                            <line x1="12" y1="22.08" x2="12" y2="12"></line>
                        </svg>
                        <span class="menu-text">Fincas</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="modules/records/" class="menu-link">
                        <svg class="menu-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                            <path d="M2 17l10 5 10-5"></path>
                            <path d="M2 12l10 5 10-5"></path>
                        </svg>
                        <span class="menu-text">Potreros</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="modules/records/" class="menu-link">
                        <svg class="menu-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14,2 14,8 20,8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                        </svg>
                        <span class="menu-text">Registros</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="content-header">
            <h1 class="page-title">Dashboard</h1>
            <p class="page-subtitle">Panel de control Telegan</p>
        </div>

        <!-- Connection Status Card -->
        <div class="status-card" id="connectionStatus">
            <div class="status-content">
                <div class="status-indicator">
                    <div class="status-dot"></div>
                    <span class="status-text">Verificando conexión...</span>
                </div>
                <div class="status-actions">
                    <button class="refresh-btn" id="refreshBtn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="23 4 23 10 17 10"></polyline>
                            <polyline points="1 20 1 14 7 14"></polyline>
                            <path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="tabs-container">
            <div class="tabs-nav">
                <button class="tab-button active" data-tab="operational">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7"></rect>
                        <rect x="14" y="3" width="7" height="7"></rect>
                        <rect x="14" y="14" width="7" height="7"></rect>
                        <rect x="3" y="14" width="7" height="7"></rect>
                    </svg>
                    Datos Operativos
                </button>
                <button class="tab-button" data-tab="alerts">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                    </svg>
                    Alertas Críticas
                </button>
            </div>

            <!-- Tab Content: Datos Operativos -->
            <div class="tab-content active" id="operational-tab">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                            </div>
                            <div class="stat-trend">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                                    <polyline points="17 6 23 6 23 12"></polyline>
                                </svg>
                            </div>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-title">Usuarios Total</h3>
                            <p class="stat-value" id="totalUsuarios">-</p>
                            <p class="stat-description">Registrados en el sistema</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M9 12l2 2 4-4"></path>
                                    <path d="M21 12c-1 0-3-1-3-3s2-3 3-3 3 1 3 3-2 3-3 3"></path>
                                    <path d="M3 12c1 0 3-1 3-3s-2-3-3-3-3 1-3 3 2 3 3 3"></path>
                                </svg>
                            </div>
                            <div class="stat-trend">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                                    <polyline points="17 6 23 6 23 12"></polyline>
                                </svg>
                            </div>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-title">Usuarios Activos</h3>
                            <p class="stat-value" id="usuariosActivos">-</p>
                            <p class="stat-description">Cuentas activas</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                                </svg>
                            </div>
                            <div class="stat-trend">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                                    <polyline points="17 6 23 6 23 12"></polyline>
                                </svg>
                            </div>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-title">Fincas Activas</h3>
                            <p class="stat-value" id="totalFincas">-</p>
                            <p class="stat-description">Propiedades activas</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                                    <path d="M2 17l10 5 10-5"></path>
                                    <path d="M2 12l10 5 10-5"></path>
                                </svg>
                            </div>
                            <div class="stat-trend">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                                    <polyline points="17 6 23 6 23 12"></polyline>
                                </svg>
                            </div>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-title">Potreros Activos</h3>
                            <p class="stat-value" id="totalPotreros">-</p>
                            <p class="stat-description">Áreas de pastoreo</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14,2 14,8 20,8"></polyline>
                                    <line x1="16" y1="13" x2="8" y2="13"></line>
                                    <line x1="16" y1="17" x2="8" y2="17"></line>
                                </svg>
                            </div>
                            <div class="stat-trend">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                                    <polyline points="17 6 23 6 23 12"></polyline>
                                </svg>
                            </div>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-title">Registros</h3>
                            <p class="stat-value" id="totalRegistros">-</p>
                            <p class="stat-description">Registros ganaderos</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="8.5" cy="7" r="4"></circle>
                                    <line x1="20" y1="8" x2="20" y2="14"></line>
                                    <line x1="23" y1="11" x2="17" y2="11"></line>
                                </svg>
                            </div>
                            <div class="stat-trend">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                                    <polyline points="17 6 23 6 23 12"></polyline>
                                </svg>
                            </div>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-title">Administradores</h3>
                            <p class="stat-value" id="usuariosAdministradores">-</p>
                            <p class="stat-description">Rol administrador</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                </svg>
                            </div>
                            <div class="stat-trend">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                                    <polyline points="17 6 23 6 23 12"></polyline>
                                </svg>
                            </div>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-title">Colaboradores</h3>
                            <p class="stat-value" id="usuariosColaboradores">-</p>
                            <p class="stat-description">Rol colaborador</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Content: Alertas Críticas -->
            <div class="tab-content" id="alerts-tab">
                <!-- Alertas Críticas -->
                <div class="alerts-section">
                    <div class="section-header">
                        <h2 class="section-title">🚨 Alertas Críticas</h2>
                        <p class="section-subtitle">Problemas que requieren atención inmediata</p>
                    </div>
                    
                    <div class="alerts-grid">
                        <!-- Alertas de Usuarios -->
                        <div class="alert-category">
                            <h3 class="category-title">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                                Usuarios
                            </h3>
                            <div class="alert-cards">
                                <a href="modules/alerts/users-no-farms.php" class="alert-card-link">
                                    <div class="alert-card critical" id="usuariosSinFinca">
                                        <div class="alert-content">
                                            <span class="alert-value">-</span>
                                            <span class="alert-label">Sin finca</span>
                                        </div>
                                    </div>
                                </a>
                                <div class="alert-card warning" id="usuariosInactivos">
                                    <div class="alert-content">
                                        <span class="alert-value">-</span>
                                        <span class="alert-label">Inactivos 30+ días</span>
                                    </div>
                                </div>
                                <a href="modules/alerts/users-never-logged-in.php" class="alert-card-link">
                                    <div class="alert-card info" id="usuariosNuncaLogin">
                                        <div class="alert-content">
                                            <span class="alert-value">-</span>
                                            <span class="alert-label">Nunca logueados</span>
                                        </div>
                                    </div>
                                </a>
                                <a href="modules/alerts/users-no-demography.php" class="alert-card-link">
                                    <div class="alert-card warning" id="usuariosSinDemo">
                                        <div class="alert-content">
                                            <span class="alert-value">-</span>
                                            <span class="alert-label">Sin demografía</span>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>

                        <!-- Alertas de Fincas -->
                        <div class="alert-category">
                            <h3 class="category-title">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                                </svg>
                                Fincas
                            </h3>
                            <div class="alert-cards">
                                <div class="alert-card critical" id="fincasSinPotreros">
                                    <div class="alert-content">
                                        <span class="alert-value">-</span>
                                        <span class="alert-label">Sin potreros</span>
                                    </div>
                                </div>
                                <div class="alert-card warning" id="fincasSinActividad">
                                    <div class="alert-content">
                                        <span class="alert-value">-</span>
                                        <span class="alert-label">Sin actividad 30+ días</span>
                                    </div>
                                </div>
                                <div class="alert-card info" id="fincasAreaSospechosa">
                                    <div class="alert-content">
                                        <span class="alert-value">-</span>
                                        <span class="alert-label">Área sospechosa</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Resumen de Calidad -->
                        <div class="alert-category">
                            <h3 class="category-title">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M9 12l2 2 4-4"></path>
                                    <path d="M21 12c-1 0-3-1-3-3s2-3 3-3 3 1 3 3-2 3-3 3"></path>
                                    <path d="M3 12c1 0 3-1 3-3s-2-3-3-3-3 1-3 3 2 3 3 3"></path>
                                </svg>
                                Calidad de Datos
                            </h3>
                            <div class="alert-cards">
                                <div class="alert-card success" id="totalAlertas">
                                    <div class="alert-content">
                                        <span class="alert-value">-</span>
                                        <span class="alert-label">Total alertas</span>
                                    </div>
                                </div>
                                <div class="alert-card info" id="nivelCritico">
                                    <div class="alert-content">
                                        <span class="alert-value">-</span>
                                        <span class="alert-label">Nivel crítico</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Info Card -->
        <div class="system-card">
            <div class="card-header">
                <h3 class="card-title">Información del Sistema</h3>
                <button class="card-action">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="1"></circle>
                        <circle cx="19" cy="12" r="1"></circle>
                        <circle cx="5" cy="12" r="1"></circle>
                    </svg>
                </button>
            </div>
            <div class="info-grid" id="systemInfo">
                <!-- Se llenará dinámicamente -->
            </div>
        </div>
    </main>

    <!-- Mobile Navigation (hidden per request)
    <nav class="mobile-nav">
        <a href="#" class="nav-item active">
            <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="7" height="7"></rect>
                <rect x="14" y="3" width="7" height="7"></rect>
                <rect x="14" y="14" width="7" height="7"></rect>
                <rect x="3" y="14" width="7" height="7"></rect>
            </svg>
            <span class="nav-text">Dashboard</span>
        </a>
        <a href="modules/users/" class="nav-item">
            <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
            </svg>
            <span class="nav-text">Usuarios</span>
        </a>
        <a href="modules/farms/" class="nav-item">
            <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
            </svg>
            <span class="nav-text">Fincas</span>
        </a>
        <a href="modules/records/" class="nav-item">
            <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                <path d="M2 17l10 5 10-5"></path>
                <path d="M2 12l10 5 10-5"></path>
            </svg>
            <span class="nav-text">Potreros</span>
        </a>
        <a href="modules/records/" class="nav-item">
            <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14,2 14,8 20,8"></polyline>
                <line x1="16" y1="13" x2="8" y2="13"></line>
                <line x1="16" y1="17" x2="8" y2="17"></line>
            </svg>
            <span class="nav-text">Registros</span>
        </a>
    </nav>
    -->

    <!-- Search Modal -->
    <div id="search-modal" class="search-modal">
        <div class="search-modal-overlay"></div>
        <div class="search-modal-content">
            <!-- Modal Header -->
            <div class="search-modal-header">
                <h2 class="search-modal-title">Buscar Agricultor</h2>
                <button class="search-modal-close" id="search-close">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>

            <!-- Navigation Breadcrumbs -->
            <div class="navigation-breadcrumbs" id="navigation-breadcrumbs" style="display: none;">
                <div class="breadcrumb-item" onclick="goBackToSearch()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="M21 21l-4.35-4.35"></path>
                    </svg>
                    <span>Búsqueda</span>
                </div>
                <div class="breadcrumb-separator">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </div>
                <div class="breadcrumb-item current" id="current-breadcrumb">
                    <span>Perfil</span>
                </div>
            </div>

            <!-- Search Input -->
            <div class="search-input-container" id="search-input-container">
                <div class="search-input-wrapper">
                    <svg class="search-input-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="M21 21l-4.35-4.35"></path>
                    </svg>
                    <input 
                        type="text" 
                        id="search-input" 
                        class="search-input" 
                        placeholder="Escribe el nombre del agricultor..."
                        autocomplete="off"
                    >
                    <button class="search-clear" id="search-clear" style="display: none;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Search Results -->
            <div class="search-results" id="search-results">
                <div class="search-empty">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="M21 21l-4.35-4.35"></path>
                    </svg>
                    <p>Escribe el nombre del agricultor para buscar</p>
                </div>
            </div>

            <!-- User Profile (Hidden by default) -->
            <div class="user-profile" id="user-profile" style="display: none;">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <span id="profile-initials">-</span>
                    </div>
                    <div class="profile-info">
                        <h3 class="profile-name" id="profile-name">-</h3>
                        <p class="profile-location" id="profile-location">-</p>
                        <div class="profile-status">
                            <span class="status-badge" id="profile-status">-</span>
                        </div>
                    </div>
                </div>

                <div class="profile-content">
                    <div class="profile-section">
                        <h4 class="section-title">Información Personal</h4>
                        <div class="profile-grid">
                            <div class="profile-item">
                                <label>Email</label>
                                <span id="profile-email">-</span>
                            </div>
                            <div class="profile-item">
                                <label>Teléfono</label>
                                <span id="profile-phone">-</span>
                            </div>
                            <div class="profile-item">
                                <label>Ubicación</label>
                                <span id="profile-ubicacion">-</span>
                            </div>
                            <div class="profile-item">
                                <label>Fecha de Registro</label>
                                <span id="profile-fecha-registro">-</span>
                            </div>
                            <div class="profile-item">
                                <label>Última Sesión</label>
                                <span id="profile-ultima-sesion">-</span>
                            </div>
                            <div class="profile-item">
                                <label>Código Telegan</label>
                                <span id="profile-codigo">-</span>
                            </div>
                        </div>
                    </div>

                    <!-- User's Farms Section -->
                    <div class="profile-section">
                        <h4 class="section-title">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                            </svg>
                            Fincas del Agricultor
                        </h4>
                        <div class="farms-list" id="farms-list">
                            <div class="loading-farms">
                                <div class="loading-spinner"></div>
                                <p>Cargando fincas...</p>
                            </div>
                        </div>
                    </div>

                    <div class="profile-actions">
                        <button class="btn btn-primary" onclick="loadCurrentUserFarms()">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                            </svg>
                            Ver Fincas
                        </button>
                        <button class="btn btn-secondary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            </svg>
                            Editar Perfil
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Farm Main Modal -->
    <div id="farm-main-modal" class="farm-main-modal">
        <div class="farm-modal-overlay"></div>
        <div class="farm-modal-content">
            <!-- Farm Modal Header -->
            <div class="farm-modal-header">
                <div class="farm-header-info">
                    <h2 class="farm-modal-title" id="farm-main-title">Finca</h2>
                    <p class="farm-modal-subtitle" id="farm-main-subtitle">Detalles completos</p>
                </div>
                <div class="farm-header-actions">
                    <button class="farm-back-btn" onclick="goBackToSearch()">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="M21 21l-4.35-4.35"></path>
                        </svg>
                        Nueva Búsqueda
                    </button>
                    <button class="farm-modal-close" id="farm-close">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Farm Content -->
            <div class="farm-modal-body">
                <!-- Farm Basic Info -->
                <div class="farm-info-section">
                    <div class="farm-basic-info">
                        <div class="farm-avatar">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                            </svg>
                        </div>
                        <div class="farm-info">
                            <h3 class="farm-name" id="farm-name">-</h3>
                            <p class="farm-location" id="farm-location">-</p>
                            <div class="farm-status-row">
                                <span class="status-badge" id="farm-status">-</span>
                                <span class="farm-area" id="farm-area">-</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Farm Details Grid -->
                <div class="farm-details-grid">
                    <!-- Farm Data -->
                    <div class="farm-detail-card">
                        <h4 class="card-title">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14,2 14,8 20,8"></polyline>
                            </svg>
                            Información de la Finca
                        </h4>
                        <div class="detail-grid" id="farm-details">
                            <!-- Farm details will be populated here -->
                        </div>
                    </div>

                    <!-- Map Section -->
                    <div class="farm-detail-card map-card">
                        <h4 class="card-title">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                            Ubicación Geográfica
                        </h4>
                        <div class="map-container" id="farm-map">
                            <div class="map-loading">
                                <div class="loading-spinner"></div>
                                <p>Cargando mapa...</p>
                            </div>
                        </div>
                    </div>

                    <!-- Administrators -->
                    <div class="farm-detail-card">
                        <h4 class="card-title">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="8.5" cy="7" r="4"></circle>
                                <line x1="20" y1="8" x2="20" y2="14"></line>
                                <line x1="23" y1="11" x2="17" y2="11"></line>
                            </svg>
                            Administradores
                        </h4>
                        <div class="users-list" id="farm-admins">
                            <!-- Administrators will be populated here -->
                        </div>
                    </div>

                    <!-- Collaborators -->
                    <div class="farm-detail-card">
                        <h4 class="card-title">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                            Colaboradores
                        </h4>
                        <div class="users-list" id="farm-collaborators">
                            <!-- Collaborators will be populated here -->
                        </div>
                    </div>

                    <!-- Paddocks -->
                    <div class="farm-detail-card">
                        <h4 class="card-title">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                                <path d="M2 17l10 5 10-5"></path>
                                <path d="M2 12l10 5 10-5"></path>
                            </svg>
                            Potreros
                        </h4>
                        <div class="paddocks-list" id="farm-paddocks">
                            <!-- Paddocks will be populated here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <!-- Scripts (módulos ES6) -->
    <script src="js/theme-common.js"></script>
    <script type="module" src="js/ApiClient.js"></script>
    <script type="module" src="js/dashboard.js"></script>
</body>
</html>