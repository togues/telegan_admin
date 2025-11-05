<?php
/**
 * Módulo de Gestión de Usuarios - Con sesión PHP
 */

// IMPORTANTE: Iniciar sesión ANTES de cualquier output
session_start();

// Incluir Security para headers CSP
require_once '../../../src/Config/Security.php';
Security::init();

// Verificar autenticación
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../../../auth/login.php');
    exit;
}

// Obtener datos del usuario
$userName = $_SESSION['admin_nombre'] ?? $_SESSION['admin_name'] ?? 'Usuario';

// Token de sesión (generado en login)
$sessionToken = $_SESSION['session_token'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - Telegan Admin</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        // Variables de sesión para JavaScript
        window.userSession = {
            loggedIn: true,
            userName: <?php echo json_encode($userName); ?>,
            userId: <?php echo json_encode($_SESSION['admin_id'] ?? null); ?>
        };
        
        // Token de sesión desde PHP (generado en login)
        <?php 
        if ($sessionToken) {
            echo "window.sessionToken = " . json_encode($sessionToken) . ";\n";
        }
        ?>
        
        // Cargar tema guardado o detectar preferencia del sistema
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('telegan-theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const theme = savedTheme || (prefersDark ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', theme);
            
            // Sidebar collapsed state
            try {
                const saved = localStorage.getItem('sidebarCollapsed');
                if (saved === 'true') {
                    document.body.classList.add('sidebar-collapsed');
                } else if (saved === null && window.innerWidth >= 1024) {
                    document.body.classList.add('sidebar-collapsed');
                }
            } catch (_) {}
        });
    </script>
    <style>
        .toolbar { display: grid; grid-template-columns: 1fr auto auto; gap: 0.5rem; align-items: center; margin-bottom: 0.75rem; position: sticky; top: calc(var(--header-height) + 8px); z-index: 10; background: var(--bg-primary); padding: 0.5rem 0 0.25rem 0; }
        .input, .select { border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-primary); border-radius: 10px; padding: 0.5rem 0.75rem; font-size: 0.9rem; height: 36px; }
        .bulk-select { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; }
        .bulk-select button { border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-primary); padding: 0.4rem 1rem; border-radius: 8px; cursor: pointer; font-size: 0.85rem; white-space: nowrap; }
        .bulk-select button:hover { background: var(--bg-secondary); }
        .bulk-actions { display: none; align-items: center; gap: 0.5rem; padding: 0.75rem; background: var(--accent-primary); border-radius: 8px; margin-bottom: 0.5rem; position: sticky; top: calc(var(--header-height) + 50px); z-index: 9; }
        .bulk-actions button { border: 1px solid white; background: white; color: var(--accent-primary); padding: 0.5rem 1rem; border-radius: 8px; cursor: pointer; font-size: 0.9rem; font-weight: 600; }
        .bulk-actions button:hover { background: var(--bg-secondary); color: var(--text-primary); }
        .user-checkbox { cursor: pointer; }
        .user-checkbox:checked { accent-color: var(--accent-primary); }
        .table-wrap { 
            border: 1px solid var(--border-color); 
            border-radius: 10px; 
            overflow: hidden; 
            background: var(--bg-card); 
            margin-top: 0.5rem;
            max-height: calc(100vh - 200px);
            overflow-y: auto;
            position: relative;
        }
        table.users { 
            width: 100%; 
            border-collapse: collapse; 
            border-spacing: 0;
        }
        thead.sticky { 
            position: sticky; 
            top: 0; 
            background: var(--bg-card); 
            z-index: 10; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
        }
        th, td { 
            text-align: left; 
            padding: 10px 12px; 
            border-bottom: 1px solid var(--border-color); 
            font-size: 0.9rem; 
            white-space: nowrap; 
        }
        tbody tr:hover { 
            background: var(--bg-secondary); 
        }
        th { 
            font-size: 0.75rem; 
            text-transform: uppercase; 
            letter-spacing: 0.05em; 
            color: var(--text-secondary); 
            background: var(--bg-card);
            font-weight: 600;
        }
        .status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 6px; }
        .dot-on { background: var(--success-color); }
        .dot-off { background: var(--error-color); }
        .pagination { display: flex; gap: 0.5rem; align-items: center; justify-content: flex-end; padding: 0.5rem; }
        .btn { border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-primary); padding: 0.4rem 0.75rem; height: 32px; border-radius: 8px; cursor: pointer; }
        .btn:disabled { opacity: .4; cursor: default; }
        .btn-view-user { border: none; background: transparent; color: var(--text-secondary); padding: 0.25rem; border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; width: 28px; height: 28px; }
        .btn-view-user:hover { background: var(--bg-secondary); color: var(--accent-primary); transform: scale(1.1); }
        .user-checkbox { cursor: pointer; }
        .user-checkbox:checked { accent-color: var(--accent-primary); }
        .btn-download { display: flex; align-items: center; gap: 0.5rem; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-primary); padding: 0.4rem 1rem; border-radius: 8px; cursor: pointer; font-size: 0.85rem; transition: all 0.2s ease; }
        .btn-download:hover { background: var(--accent-primary); color: white; border-color: var(--accent-primary); transform: translateY(-1px); }
        .btn-download:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        .btn-download svg { width: 16px; height: 16px; }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        #farm-modal-nested { z-index: 10001 !important; }
        #user-profile-modal { z-index: 10000; }
        .farm-item { transition: all 0.2s ease; }
        .farm-item:hover { background: var(--bg-secondary); transform: translateX(4px); }
        @media (max-width: 768px) {
            .toolbar > div:first-child { grid-template-columns: 1fr; gap: 0.5rem; }
            .toolbar > div:first-child input, .toolbar > div:first-child select { width: 100%; }
        }
    </style>
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
                <li class="menu-item">
                    <a href="../../dashboard.php" class="menu-link">
                        <svg class="menu-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="7" height="7"></rect>
                            <rect x="14" y="3" width="7" height="7"></rect>
                            <rect x="14" y="14" width="7" height="7"></rect>
                            <rect x="3" y="14" width="7" height="7"></rect>
                        </svg>
                        <span class="menu-text">Dashboard</span>
                    </a>
                </li>
                <li class="menu-item active">
                    <a href="#" class="menu-link">
                        <svg class="menu-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        <span class="menu-text">Usuarios</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="../farms/" class="menu-link">
                        <svg class="menu-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                        </svg>
                        <span class="menu-text">Fincas</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="../records/" class="menu-link">
                        <svg class="menu-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                            <path d="M2 17l10 5 10-5"></path>
                            <path d="M2 12l10 5 10-5"></path>
                        </svg>
                        <span class="menu-text">Potreros</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="../records/" class="menu-link">
                        <svg class="menu-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14,2 14,8 20,8"></polyline>
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
            <h1 class="page-title">Gestión de Usuarios</h1>
            <p class="page-subtitle">Administra usuarios del sistema, roles y permisos</p>
        </div>
        <!-- Toolbar filtros -->
        <div class="toolbar">
            <div style="display: grid; grid-template-columns: 1fr auto auto auto auto auto auto; gap: 0.5rem; width: 100%;">
                <input id="q" class="input" placeholder="Buscar por nombre, email o teléfono" />
                <input id="codigo" type="text" class="input" placeholder="Código Telegan" style="width: 140px;" />
                <input id="fecha_desde" type="date" class="input" placeholder="Desde" style="width: 140px;" />
                <input id="fecha_hasta" type="date" class="input" placeholder="Hasta" style="width: 140px;" />
                <select id="activo" class="select">
                    <option value="">Todos</option>
                    <option value="1">Activos</option>
                    <option value="0">Inactivos</option>
                </select>
                <button id="clearFilters" class="btn" title="Limpiar filtros" style="width: auto; padding: 0.4rem 0.75rem;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="pagination">
                <button id="prev" class="btn">Anterior</button>
                <span id="pageInfo" style="color: var(--text-secondary);"></span>
                <button id="next" class="btn">Siguiente</button>
            </div>
        </div>

        <!-- Bulk Select -->
        <div class="bulk-select">
            <button id="selectAll">Marcar todos</button>
            <button id="downloadUsers" class="btn-download" title="Descargar usuarios filtrados (CSV/Excel)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                Descargar Usuarios
            </button>
        </div>

        <!-- Bulk Actions -->
        <div id="bulkActions" class="bulk-actions">
            <span style="color: white; font-weight: 600;">Acciones masivas:</span>
            <button id="bulkBtnActivate">✓ Activar</button>
            <button id="bulkBtnDeactivate">✗ Desactivar</button>
        </div>

        <!-- Tabla usuarios -->
        <div class="table-wrap">
            <table class="users">
                <thead class="sticky">
                    <tr>
                        <th style="width:40px;"></th>
                        <th style="width:40px;"></th>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th style="width:120px;">Teléfono</th>
                        <th style="width:120px;">Estado</th>
                        <th style="width:120px;">Registro</th>
                        <th style="width:120px;">Última sesión</th>
                        <th style="width:120px;">Código</th>
                    </tr>
                </thead>
                <tbody id="tbody">
                    <tr><td colspan="9" style="text-align:center; color: var(--text-secondary); padding: 16px;">Cargando...</td></tr>
                </tbody>
            </table>
        </div>
    </main>

    <!-- User Profile Modal (reutilizado del dashboard) -->
    <div id="user-profile-modal" class="search-modal" style="display: none; z-index: 10000;">
        <div class="search-modal-overlay" onclick="closeUserProfileModal()"></div>
        <div class="search-modal-content">
            <!-- Modal Header -->
            <div class="search-modal-header">
                <h2 class="search-modal-title">Perfil de Usuario</h2>
                <button class="search-modal-close" onclick="closeUserProfileModal()">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>

            <!-- User Profile -->
            <div class="user-profile" id="user-profile-content">
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
                </div>
            </div>
        </div>
    </div>

    <!-- Farm Modal (anidado sobre el modal de usuario) -->
    <div id="farm-modal-nested" class="farm-main-modal" style="display: none; z-index: 10001;">
        <div class="farm-modal-overlay" onclick="closeFarmModalNested()"></div>
        <div class="farm-modal-content">
            <!-- Farm Modal Header -->
            <div class="farm-modal-header">
                <div class="farm-header-info">
                    <h2 class="farm-modal-title" id="farm-nested-title">Finca</h2>
                    <p class="farm-modal-subtitle" id="farm-nested-subtitle">Detalles completos</p>
                </div>
                <div class="farm-header-actions">
                    <button class="farm-back-btn" onclick="closeFarmModalNested()">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="15 18 9 12 15 6"></polyline>
                        </svg>
                        Volver al Perfil
                    </button>
                    <button class="farm-modal-close" onclick="closeFarmModalNested()">
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
                            <h3 class="farm-name" id="farm-nested-name">-</h3>
                            <p class="farm-location" id="farm-nested-location">-</p>
                            <div class="farm-status-row">
                                <span class="status-badge" id="farm-nested-status">-</span>
                                <span class="farm-area" id="farm-nested-area">-</span>
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
                        <div class="detail-grid" id="farm-nested-details">
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
                        <div class="map-container" id="farm-nested-map" style="width: 100%; height: 400px; min-height: 400px; position: relative;">
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
                        <div class="users-list" id="farm-nested-admins">
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
                        <div class="users-list" id="farm-nested-collaborators">
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
                        <div class="paddocks-list" id="farm-nested-paddocks">
                            <!-- Paddocks will be populated here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Leaflet JS (para mapas de fincas) -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <!-- SheetJS para exportar a Excel/CSV (desde CDNJS) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.5/xlsx.full.min.js"></script>

    <script src="../../js/theme-common.js"></script>
    <script type="module" src="./users.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            
            // Mobile menu toggle
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.getElementById('sidebar');
            
            if (menuToggle && sidebar) {
                menuToggle.addEventListener('click', () => {
                    sidebar.classList.toggle('open');
                });
            }
            
            // Desktop collapse toggle
            const collapseToggle = document.getElementById('collapseToggle');
            if (collapseToggle) {
                collapseToggle.addEventListener('click', () => {
                    document.body.classList.toggle('sidebar-collapsed');
                    try {
                        localStorage.setItem('sidebarCollapsed', document.body.classList.contains('sidebar-collapsed') ? 'true' : 'false');
                    } catch (_) {}
                });
            }
        });
    </script>
</body>
</html>
