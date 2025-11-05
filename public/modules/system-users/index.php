<?php
/**
 * Módulo de Gestión de Usuarios del Sistema (Administradores) - CRUD Completo
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
    <title>Administradores del Sistema - Telegan Admin</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- SheetJS para exportar a Excel/CSV -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.5/xlsx.full.min.js"></script>
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
        .btn-create { display: flex; align-items: center; gap: 0.5rem; border: none; background: var(--accent-primary); color: white; padding: 0.5rem 1rem; border-radius: 8px; cursor: pointer; font-size: 0.9rem; font-weight: 600; transition: all 0.2s ease; }
        .btn-create:hover { background: var(--accent-secondary); transform: translateY(-1px); box-shadow: var(--shadow-md); }
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
        table.admin-users { 
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
        .role-badge { display: inline-block; padding: 0.25rem 0.5rem; border-radius: 6px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
        .role-super_admin { background: var(--accent-primary); color: white; }
        .role-tecnico { background: var(--accent-secondary); color: white; }
        .role-admin_finca { background: var(--accent-tertiary); color: var(--text-primary); }
        .pagination { display: flex; gap: 0.5rem; align-items: center; justify-content: flex-end; padding: 0.5rem; }
        .btn { border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-primary); padding: 0.4rem 0.75rem; height: 32px; border-radius: 8px; cursor: pointer; font-size: 0.85rem; transition: all 0.2s ease; }
        .btn:disabled { opacity: .4; cursor: default; }
        .btn:hover:not(:disabled) { background: var(--bg-secondary); }
        .btn-edit, .btn-delete { border: none; background: transparent; color: var(--text-secondary); padding: 0.25rem; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s ease; width: 28px; height: 28px; }
        .btn-edit:hover { background: var(--accent-secondary); color: white; transform: scale(1.1); }
        .btn-delete:hover { background: var(--error-color); color: white; transform: scale(1.1); }
        .btn-download { display: flex; align-items: center; gap: 0.5rem; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-primary); padding: 0.4rem 1rem; border-radius: 8px; cursor: pointer; font-size: 0.85rem; transition: all 0.2s ease; }
        .btn-download:hover { background: var(--accent-primary); color: white; border-color: var(--accent-primary); transform: translateY(-1px); }
        .btn-download svg { width: 16px; height: 16px; }
        
        /* Modales */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center; }
        .modal.show { display: flex; }
        .modal-content { background: var(--bg-card); border-radius: 12px; padding: 1.5rem; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: var(--shadow-xl); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .modal-title { font-size: 1.25rem; font-weight: 600; color: var(--text-primary); }
        .modal-close { border: none; background: transparent; color: var(--text-secondary); cursor: pointer; padding: 0.25rem; border-radius: 6px; transition: all 0.2s ease; }
        .modal-close:hover { background: var(--bg-secondary); color: var(--text-primary); }
        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; margin-bottom: 0.5rem; font-size: 0.9rem; font-weight: 500; color: var(--text-primary); }
        .form-input, .form-select { width: 100%; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-primary); border-radius: 8px; padding: 0.5rem 0.75rem; font-size: 0.9rem; }
        .form-input:focus, .form-select:focus { outline: none; border-color: var(--accent-primary); box-shadow: 0 0 0 3px rgba(109, 190, 69, 0.1); }
        .form-checkbox { display: flex; align-items: center; gap: 0.5rem; }
        .form-checkbox input { width: auto; }
        .modal-actions { display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color); }
        .btn-modal-cancel { border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-primary); padding: 0.5rem 1rem; border-radius: 8px; cursor: pointer; }
        .btn-modal-save { border: none; background: var(--accent-primary); color: white; padding: 0.5rem 1.5rem; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .btn-modal-save:hover { background: var(--accent-secondary); }
        .btn-modal-delete { border: none; background: var(--error-color); color: white; padding: 0.5rem 1.5rem; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .btn-modal-delete:hover { background: #dc2626; }
        
        /* Confirmación */
        .confirm-modal { z-index: 10001; }
        .confirm-content { max-width: 400px; }
        .confirm-message { margin-bottom: 1.5rem; color: var(--text-primary); }
        .confirm-actions { display: flex; gap: 0.75rem; justify-content: flex-end; }
        
        @media (max-width: 768px) {
            .toolbar > div:first-child { grid-template-columns: 1fr; gap: 0.5rem; }
            .toolbar > div:first-child input, .toolbar > div:first-child select { width: 100%; }
            .modal-content { width: 95%; padding: 1rem; }
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
                <li class="menu-item">
                    <a href="../users/" class="menu-link">
                        <svg class="menu-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        <span class="menu-text">Usuarios</span>
                    </a>
                </li>
                <li class="menu-item active">
                    <a href="#" class="menu-link">
                        <svg class="menu-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="8.5" cy="7" r="4"></circle>
                            <path d="M20 8v6M23 11h-6"></path>
                        </svg>
                        <span class="menu-text">Administradores</span>
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
            </ul>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="content-header">
            <h1 class="page-title">Administradores del Sistema</h1>
            <p class="page-subtitle">Gestión completa de usuarios administrativos (CRUD)</p>
        </div>

        <!-- Toolbar -->
        <div class="toolbar">
            <div style="display: grid; grid-template-columns: 1fr auto auto auto; gap: 0.5rem; width: 100%;">
                <input id="q" class="input" placeholder="Buscar por nombre, email o teléfono" />
                <select id="activo" class="select">
                    <option value="">Todos</option>
                    <option value="1">Activos</option>
                    <option value="0">Inactivos</option>
                </select>
                <select id="rol" class="select">
                    <option value="">Todos los roles</option>
                    <option value="SUPER_ADMIN">Super Admin</option>
                    <option value="TECNICO">Técnico</option>
                    <option value="ADMIN_FINCA">Admin Finca</option>
                </select>
                <button id="clearFilters" class="btn" title="Limpiar filtros" style="width: auto; padding: 0.4rem 0.75rem;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <button id="btnCreate" class="btn-create">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    Nuevo Administrador
                </button>
            </div>
            <div class="pagination">
                <button id="prev" class="btn">Anterior</button>
                <span id="pageInfo" style="color: var(--text-secondary);"></span>
                <button id="next" class="btn">Siguiente</button>
            </div>
        </div>

        <!-- Download Button -->
        <div class="bulk-select">
            <button id="downloadAdmins" class="btn-download" title="Descargar administradores filtrados (CSV/Excel)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                Descargar Administradores
            </button>
        </div>

        <!-- Table -->
        <div class="table-wrap">
            <table class="admin-users">
                <thead class="sticky">
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Teléfono</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Email Verificado</th>
                        <th>Última Sesión</th>
                        <th>Registro</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tbody">
                    <tr>
                        <td colspan="10" style="text-align:center; color: var(--text-secondary); padding: 16px;">Cargando...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Modal Crear/Editar -->
    <div id="modalForm" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">Nuevo Administrador</h2>
                <button class="modal-close" id="modalClose">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <form id="formAdmin">
                <input type="hidden" id="formId" />
                <div class="form-group">
                    <label class="form-label" for="formNombre">Nombre Completo *</label>
                    <input type="text" id="formNombre" class="form-input" required maxlength="255" />
                </div>
                <div class="form-group">
                    <label class="form-label" for="formEmail">Email *</label>
                    <input type="email" id="formEmail" class="form-input" required maxlength="255" />
                </div>
                <div class="form-group">
                    <label class="form-label" for="formTelefono">Teléfono</label>
                    <input type="text" id="formTelefono" class="form-input" maxlength="20" />
                </div>
                <div class="form-group">
                    <label class="form-label" for="formPassword">Contraseña <span id="passwordRequired">*</span></label>
                    <input type="password" id="formPassword" class="form-input" minlength="8" />
                    <small style="color: var(--text-secondary); font-size: 0.8rem;">Mínimo 8 caracteres. Dejar vacío para mantener la actual (solo edición).</small>
                </div>
                <div class="form-group">
                    <label class="form-label" for="formRol">Rol *</label>
                    <select id="formRol" class="form-select" required>
                        <option value="">Seleccionar...</option>
                        <option value="SUPER_ADMIN">Super Admin</option>
                        <option value="TECNICO">Técnico</option>
                        <option value="ADMIN_FINCA">Admin Finca</option>
                    </select>
                </div>
                <div class="form-group">
                    <div class="form-checkbox">
                        <input type="checkbox" id="formActivo" />
                        <label class="form-label" for="formActivo" style="margin: 0;">Usuario Activo</label>
                    </div>
                </div>
                <div class="form-group">
                    <div class="form-checkbox">
                        <input type="checkbox" id="formEmailVerificado" />
                        <label class="form-label" for="formEmailVerificado" style="margin: 0;">Email Verificado</label>
                    </div>
                </div>
                <div class="form-group">
                    <div class="form-checkbox">
                        <input type="checkbox" id="formTelefonoVerificado" />
                        <label class="form-label" for="formTelefonoVerificado" style="margin: 0;">Teléfono Verificado</label>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-modal-cancel" id="formCancel">Cancelar</button>
                    <button type="submit" class="btn-modal-save" id="formSave">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Confirmación -->
    <div id="modalConfirm" class="modal confirm-modal">
        <div class="modal-content confirm-content">
            <div class="modal-header">
                <h2 class="modal-title">Confirmar Acción</h2>
                <button class="modal-close" id="confirmClose">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="confirm-message" id="confirmMessage"></div>
            <div class="confirm-actions">
                <button class="btn-modal-cancel" id="confirmCancel">Cancelar</button>
                <button class="btn-modal-delete" id="confirmOk">Confirmar</button>
            </div>
        </div>
    </div>

    <!-- Bottom Navigation (Mobile) -->
    <nav class="bottom-nav">
        <a href="../../dashboard.php" class="nav-item">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="7" height="7"></rect>
                <rect x="14" y="3" width="7" height="7"></rect>
                <rect x="14" y="14" width="7" height="7"></rect>
                <rect x="3" y="14" width="7" height="7"></rect>
            </svg>
            <span>Dashboard</span>
        </a>
        <a href="../users/" class="nav-item">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
            </svg>
            <span>Usuarios</span>
        </a>
        <a href="#" class="nav-item active">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="8.5" cy="7" r="4"></circle>
                <path d="M20 8v6M23 11h-6"></path>
            </svg>
            <span>Admins</span>
        </a>
    </nav>

    <script src="../../js/theme-common.js"></script>
    <script type="module" src="./system-users.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.getElementById('sidebar');
            const collapseToggle = document.getElementById('collapseToggle');

            if (menuToggle) {
                menuToggle.addEventListener('click', () => {
                    sidebar.classList.toggle('open');
                });
            }

            if (collapseToggle) {
                collapseToggle.addEventListener('click', () => {
                    document.body.classList.toggle('sidebar-collapsed');
                    try {
                        localStorage.setItem('sidebarCollapsed', document.body.classList.contains('sidebar-collapsed'));
                    } catch (_) {}
                });
            }

            // Cerrar sidebar al hacer click fuera (mobile)
            document.addEventListener('click', (e) => {
                if (window.innerWidth < 1024 && sidebar.classList.contains('open')) {
                    if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                        sidebar.classList.remove('open');
                    }
                }
            });
        });
    </script>
</body>
</html>

