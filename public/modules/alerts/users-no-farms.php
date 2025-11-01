<?php
/**
 * Módulo: Usuarios sin Fincas - Listado
 */

session_start();

require_once '../../../src/Config/Security.php';
Security::init();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../../../auth/login.php');
    exit;
}

$userName = $_SESSION['admin_nombre'] ?? $_SESSION['admin_name'] ?? 'Usuario';
$sessionToken = $_SESSION['session_token'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios sin Fincas - Telegan Admin</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        window.userSession = {
            loggedIn: true,
            userName: <?php echo json_encode($userName); ?>,
            userId: <?php echo json_encode($_SESSION['admin_id'] ?? null); ?>
        };
        
        <?php 
        if ($sessionToken) {
            echo "window.sessionToken = " . json_encode($sessionToken) . ";\n";
        }
        ?>
        
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('telegan-theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const theme = savedTheme || (prefersDark ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', theme);
            
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
        .toolbar { display: grid; grid-template-columns: 1fr auto; gap: 0.5rem; align-items: center; margin-bottom: 0.75rem; position: sticky; top: calc(var(--header-height) + 8px); z-index: 10; background: var(--bg-primary); padding: 0.5rem 0 0.25rem 0; }
        .input, .select { border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-primary); border-radius: 10px; padding: 0.5rem 0.75rem; font-size: 0.9rem; height: 36px; }
        .table-wrap { border: 1px solid var(--border-color); border-radius: 10px; overflow: hidden; background: var(--bg-card); margin-top: 0.5rem; max-height: calc(100vh - 280px); overflow-y: auto; position: relative; }
        table.users { width: 100%; border-collapse: collapse; border-spacing: 0; }
        thead.sticky { position: sticky; top: 0; background: var(--bg-card); z-index: 10; box-shadow: 0 2px 4px rgba(0,0,0,0.08); }
        th, td { text-align: left; padding: 10px 12px; border-bottom: 1px solid var(--border-color); font-size: 0.9rem; white-space: nowrap; }
        tbody tr:hover { background: var(--bg-secondary); }
        th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary); background: var(--bg-card); font-weight: 600; }
        .status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 6px; }
        .dot-on { background: var(--success-color); }
        .dot-off { background: var(--error-color); }
        .pagination { display: flex; gap: 0.5rem; align-items: center; justify-content: flex-end; padding: 0.5rem; }
        .btn { border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-primary); padding: 0.4rem 0.75rem; height: 32px; border-radius: 8px; cursor: pointer; transition: all var(--timing-fast) ease; }
        .btn:hover { background: var(--bg-secondary); }
        .btn:disabled { opacity: .4; cursor: default; }
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
                    <a href="../users/index.php" class="menu-link">
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
                        </svg>
                        <span class="menu-text">Potreros</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="../records/" class="menu-link">
                        <svg class="menu-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
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
            <h1 class="page-title">Usuarios sin Fincas</h1>
            <p class="page-subtitle">Listado de usuarios activos sin fincas asociadas</p>
        </div>

        <!-- Toolbar paginación -->
        <div class="toolbar">
            <div></div>
            <div class="pagination">
                <button id="prev" class="btn">Anterior</button>
                <span id="pageInfo" style="color: var(--text-secondary);"></span>
                <button id="next" class="btn">Siguiente</button>
            </div>
        </div>

        <!-- Tabla usuarios -->
        <div class="table-wrap">
            <table class="users">
                <thead class="sticky">
                    <tr>
                        <th style="width:80px;">ID</th>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th style="width:120px;">Teléfono</th>
                        <th style="width:160px;">Registro</th>
                        <th style="width:160px;">Última sesión</th>
                        <th style="width:120px;">Código</th>
                    </tr>
                </thead>
                <tbody id="tbody">
                    <tr><td colspan="7" style="text-align:center; color: var(--text-secondary); padding: 16px;">Cargando...</td></tr>
                </tbody>
            </table>
        </div>
    </main>

    <script type="module">
        import { ApiClient } from '../../js/ApiClient.js';

        const state = {
            page: 1,
            pageSize: 20,
            totalPages: 1
        };

        const els = {
            prev: document.getElementById('prev'),
            next: document.getElementById('next'),
            pageInfo: document.getElementById('pageInfo'),
            tbody: document.getElementById('tbody')
        };

        function buildUrl() {
            const params = new URLSearchParams();
            params.set('page', String(state.page));
            params.set('page_size', String(state.pageSize));
            return `api/alerts-users-no-farms.php?${params.toString()}`;
        }

        async function loadUsers() {
            try {
                els.tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; color: var(--text-secondary); padding: 16px;">Cargando...</td></tr>`;
                const url = buildUrl();
                
                const api = new ApiClient();
                const response = await api.get(url);
                
                if (!response.success) {
                    throw new Error(response.error || 'Error al cargar usuarios');
                }
                
                renderRows(response.data || []);
                const pg = response.pagination || {};
                state.totalPages = pg.total_pages || 1;
                els.pageInfo.textContent = `Página ${pg.page || state.page} de ${state.totalPages} • ${pg.total || 0} usuarios`;
                els.prev.disabled = state.page <= 1;
                els.next.disabled = state.page >= state.totalPages;
            } catch (e) {
                console.error('Error loading users:', e);
                els.tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; color: var(--error-color); padding: 16px;">Error: ${e.message || 'Error desconocido'}</td></tr>`;
            }
        }

        function escapeHtml(s) {
            if (s == null) return '';
            return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        }

        function fmtDate(d) {
            if (!d) return '';
            try { return new Date(d).toLocaleString(); } catch (_) { return d; }
        }

        function renderRows(rows) {
            if (!rows.length) {
                els.tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; color: var(--text-secondary); padding: 16px;">Sin resultados</td></tr>`;
                return;
            }
            const html = rows.map(r => `
                <tr>
                    <td>${r.id_usuario}</td>
                    <td>${escapeHtml(r.nombre_completo)}</td>
                    <td>${escapeHtml(r.email || '')}</td>
                    <td>${escapeHtml(r.telefono || '')}</td>
                    <td>${fmtDate(r.fecha_registro)}</td>
                    <td>${fmtDate(r.ultima_sesion)}</td>
                    <td>${escapeHtml(r.codigo_telegan || '')}</td>
                </tr>
            `).join('');
            els.tbody.innerHTML = html;
        }

        els.prev.addEventListener('click', () => { if (state.page > 1) { state.page--; loadUsers(); } });
        els.next.addEventListener('click', () => { if (state.page < state.totalPages) { state.page++; loadUsers(); } });

        loadUsers();
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const themeToggle = document.getElementById('theme-toggle');
            if (themeToggle) {
                themeToggle.addEventListener('click', function() {
                    const currentTheme = document.documentElement.getAttribute('data-theme');
                    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                    document.documentElement.setAttribute('data-theme', newTheme);
                    localStorage.setItem('telegan-theme', newTheme);
                });
            }
            
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.getElementById('sidebar');
            if (menuToggle && sidebar) {
                menuToggle.addEventListener('click', () => sidebar.classList.toggle('open'));
            }
            
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

