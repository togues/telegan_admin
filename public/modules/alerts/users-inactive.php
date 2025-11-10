<?php
/**
 * Módulo: Usuarios Inactivos >30 días - Listado
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
    <title>Usuarios Inactivos >30 días - Telegan Admin</title>
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
        
        // El tema se maneja ahora con theme-common.js
            
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
        @media (max-width: 768px) {
            .toolbar > div:first-child { grid-template-columns: 1fr; gap: 0.5rem; }
            .toolbar > div:first-child input, .toolbar > div:first-child select { width: 100%; }
        }
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
        .bulk-select { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; }
        .btn-download { display: flex; align-items: center; gap: 0.5rem; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-primary); padding: 0.4rem 1rem; border-radius: 8px; cursor: pointer; font-size: 0.85rem; transition: all 0.2s ease; }
        .btn-download:hover { background: var(--accent-primary); color: white; border-color: var(--accent-primary); transform: translateY(-1px); }
        .btn-download:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        .btn-download svg { width: 16px; height: 16px; }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .btn-deactivate {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            padding: 0.375rem;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .btn-deactivate:hover:not(:disabled) {
            background: var(--error-color);
            border-color: var(--error-color);
            color: white;
        }
        .btn-deactivate:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        th.sortable {
            cursor: pointer;
            user-select: none;
            position: relative;
            padding-right: 24px;
            transition: background-color 0.2s ease;
        }
        th.sortable:hover {
            background: var(--bg-secondary);
        }
        th.sortable .sort-indicator {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            display: inline-flex;
            flex-direction: column;
            gap: 2px;
            opacity: 0.3;
        }
        th.sortable.sort-asc .sort-indicator,
        th.sortable.sort-desc .sort-indicator {
            opacity: 1;
        }
        th.sortable.sort-asc .sort-indicator .arrow-up {
            color: var(--accent-primary);
        }
        th.sortable.sort-desc .sort-indicator .arrow-down {
            color: var(--accent-primary);
        }
        th.sortable .sort-indicator svg {
            width: 10px;
            height: 10px;
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
            <h1 class="page-title">Usuarios Inactivos >30 días</h1>
            <p class="page-subtitle">Usuarios sin actividad en los últimos 30 días (listos para borrar)</p>
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

        <!-- Download Button -->
        <div class="bulk-select" style="margin-bottom: 0.5rem;">
            <button id="downloadUsers" class="btn-download" title="Descargar usuarios filtrados (CSV/Excel)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                Descargar Usuarios
            </button>
        </div>

        <!-- Tabla usuarios -->
        <div class="table-wrap">
            <table class="users">
                <thead class="sticky">
                    <tr>
                        <th style="width:80px;">Acciones</th>
                        <th style="width:80px;">ID</th>
                        <th class="sortable" data-sort="nombre_completo">
                            Nombre
                            <span class="sort-indicator">
                                <svg class="arrow-up" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="18 15 12 9 6 15"></polyline>
                                </svg>
                                <svg class="arrow-down" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </span>
                        </th>
                        <th class="sortable" data-sort="email">
                            Email
                            <span class="sort-indicator">
                                <svg class="arrow-up" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="18 15 12 9 6 15"></polyline>
                                </svg>
                                <svg class="arrow-down" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </span>
                        </th>
                        <th class="sortable" data-sort="telefono" style="width:120px;">
                            Teléfono
                            <span class="sort-indicator">
                                <svg class="arrow-up" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="18 15 12 9 6 15"></polyline>
                                </svg>
                                <svg class="arrow-down" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </span>
                        </th>
                        <th class="sortable" data-sort="fecha_registro" style="width:160px;">
                            Registro
                            <span class="sort-indicator">
                                <svg class="arrow-up" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="18 15 12 9 6 15"></polyline>
                                </svg>
                                <svg class="arrow-down" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </span>
                        </th>
                        <th class="sortable" data-sort="ultima_sesion" style="width:160px;">
                            Última sesión
                            <span class="sort-indicator">
                                <svg class="arrow-up" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="18 15 12 9 6 15"></polyline>
                                </svg>
                                <svg class="arrow-down" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </span>
                        </th>
                        <th class="sortable" data-sort="codigo_telegan" style="width:120px;">
                            Código
                            <span class="sort-indicator">
                                <svg class="arrow-up" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="18 15 12 9 6 15"></polyline>
                                </svg>
                                <svg class="arrow-down" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </span>
                        </th>
                        <th style="width:120px;">Días Inactivo</th>
                    </tr>
                </thead>
                <tbody id="tbody">
                    <tr><td colspan="9" style="text-align:center; color: var(--text-secondary); padding: 16px;">Cargando...</td></tr>
                </tbody>
            </table>
        </div>
    </main>

    <script type="module">
        import { ApiClient } from '../../js/ApiClient.js';

        const state = {
            q: '',
            codigo: '',
            fechaDesde: '',
            fechaHasta: '',
            activo: '',
            page: 1,
            pageSize: 20,
            totalPages: 1,
            sortBy: 'fecha_registro',
            sortOrder: 'DESC'
        };

        const els = {
            q: document.getElementById('q'),
            codigo: document.getElementById('codigo'),
            fechaDesde: document.getElementById('fecha_desde'),
            fechaHasta: document.getElementById('fecha_hasta'),
            activo: document.getElementById('activo'),
            clearFilters: document.getElementById('clearFilters'),
            prev: document.getElementById('prev'),
            next: document.getElementById('next'),
            pageInfo: document.getElementById('pageInfo'),
            tbody: document.getElementById('tbody'),
            downloadUsers: document.getElementById('downloadUsers')
        };

        function buildUrl(includePagination = true) {
            const params = new URLSearchParams();
            if (state.q) params.set('q', state.q);
            if (state.codigo) params.set('codigo', state.codigo);
            if (state.fechaDesde) params.set('fecha_desde', state.fechaDesde);
            if (state.fechaHasta) params.set('fecha_hasta', state.fechaHasta);
            if (state.activo !== '') params.set('activo', state.activo);
            
            // Parámetros de ordenamiento
            params.set('sort_by', state.sortBy);
            params.set('sort_order', state.sortOrder);
            
            if (includePagination) {
                params.set('page', String(state.page));
                params.set('page_size', String(state.pageSize));
            } else {
                params.set('page', '1');
                params.set('page_size', '10000');
            }
            
            return `api/alerts-inactive-users.php?${params.toString()}`;
        }

        function clearFilters() {
            state.q = '';
            state.codigo = '';
            state.fechaDesde = '';
            state.fechaHasta = '';
            state.activo = '';
            state.page = 1;
            
            if (els.q) els.q.value = '';
            if (els.codigo) els.codigo.value = '';
            if (els.fechaDesde) els.fechaDesde.value = '';
            if (els.fechaHasta) els.fechaHasta.value = '';
            if (els.activo) els.activo.value = '';
            
            loadUsers();
        }

        async function loadUsers() {
            try {
                els.tbody.innerHTML = `<tr><td colspan="9" style="text-align:center; color: var(--text-secondary); padding: 16px;">Cargando...</td></tr>`;
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
                els.tbody.innerHTML = `<tr><td colspan="9" style="text-align:center; color: var(--error-color); padding: 16px;">Error: ${e.message || 'Error desconocido'}</td></tr>`;
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
                els.tbody.innerHTML = `<tr><td colspan="9" style="text-align:center; color: var(--text-secondary); padding: 16px;">Sin resultados</td></tr>`;
                return;
            }
            const html = rows.map(r => {
                const diasInactivo = r.dias_inactivo || 0;
                const badgeColor = diasInactivo > 60 ? 'var(--error-color)' : diasInactivo > 30 ? 'var(--accent-warm)' : 'var(--text-secondary)';
                return `
                <tr>
                    <td style="text-align: center;">
                        <button class="btn-deactivate" data-id="${r.id_usuario}" data-nombre="${escapeHtml(r.nombre_completo)}" title="Desactivar cuenta" ${r.activo === false ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''}>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            </svg>
                        </button>
                    </td>
                    <td>${r.id_usuario}</td>
                    <td>${escapeHtml(r.nombre_completo)}</td>
                    <td>${escapeHtml(r.email || '')}</td>
                    <td>${escapeHtml(r.telefono || '')}</td>
                    <td>${fmtDate(r.fecha_registro)}</td>
                    <td>${fmtDate(r.ultima_sesion)}</td>
                    <td>${escapeHtml(r.codigo_telegan || '')}</td>
                    <td style="text-align: center;"><span style="padding: 0.25rem 0.5rem; border-radius: 4px; background: ${badgeColor}; color: white; font-size: 0.85rem; font-weight: 600;">${diasInactivo} días</span></td>
                </tr>
            `;
            }).join('');
            els.tbody.innerHTML = html;
            
            // Agregar event listeners a botones de desactivar
            document.querySelectorAll('.btn-deactivate').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = parseInt(this.dataset.id);
                    const nombre = this.dataset.nombre;
                    if (!this.disabled) {
                        deactivateUser(id, nombre);
                    }
                });
            });
        }

        // Event listeners para filtros
        function debounce(fn, ms) {
            let t; return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
        }

        if (els.q) els.q.addEventListener('input', debounce(e => { state.q = e.target.value.trim(); state.page = 1; loadUsers(); }, 300));
        if (els.codigo) els.codigo.addEventListener('input', debounce(e => { state.codigo = e.target.value.trim(); state.page = 1; loadUsers(); }, 300));
        if (els.fechaDesde) els.fechaDesde.addEventListener('change', e => { state.fechaDesde = e.target.value; state.page = 1; loadUsers(); });
        if (els.fechaHasta) els.fechaHasta.addEventListener('change', e => { state.fechaHasta = e.target.value; state.page = 1; loadUsers(); });
        if (els.activo) els.activo.addEventListener('change', e => { state.activo = e.target.value; state.page = 1; loadUsers(); });
        if (els.clearFilters) els.clearFilters.addEventListener('click', clearFilters);

        els.prev.addEventListener('click', () => { if (state.page > 1) { state.page--; loadUsers(); } });
        els.next.addEventListener('click', () => { if (state.page < state.totalPages) { state.page++; loadUsers(); } });

        function fmtDateOnly(d) {
            if (!d) return '';
            try { 
                const date = new Date(d);
                return date.toLocaleDateString('es-ES', { year: 'numeric', month: '2-digit', day: '2-digit' });
            } catch (_) { 
                if (typeof d === 'string' && d.includes('T')) {
                    return d.split('T')[0].split('-').reverse().join('/');
                }
                return d; 
            }
        }

        async function downloadUsers() {
            const activeFilters = [];
            if (state.q) activeFilters.push(`Búsqueda: "${state.q}"`);
            if (state.codigo) activeFilters.push(`Código: "${state.codigo}"`);
            if (state.fechaDesde) activeFilters.push(`Desde: ${state.fechaDesde}`);
            if (state.fechaHasta) activeFilters.push(`Hasta: ${state.fechaHasta}`);
            if (state.activo === '1') activeFilters.push('Estado: Activos');
            if (state.activo === '0') activeFilters.push('Estado: Inactivos');
            
            const filterInfo = activeFilters.length > 0 
                ? `\n\nFiltros aplicados:\n${activeFilters.join('\n')}` 
                : '\n\n⚠️ Se descargarán TODOS los usuarios (sin filtros aplicados)';
            
            if (!confirm(`¿Descargar usuarios filtrados?${filterInfo}\n\nEl archivo contendrá los datos actualmente visibles según los filtros aplicados.`)) {
                return;
            }

            try {
                if (els.downloadUsers) {
                    els.downloadUsers.disabled = true;
                    els.downloadUsers.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation: spin 1s linear infinite;"><circle cx="12" cy="12" r="10"></circle></svg> Descargando...`;
                }

                const api = new ApiClient();
                const url = buildUrl(false);
                const response = await api.get(url);
                
                if (!response.success) {
                    throw new Error(response.error || 'Error al obtener datos para descarga');
                }

                const users = response.data || [];
                
                if (users.length === 0) {
                    alert('No hay usuarios para descargar con los filtros aplicados.');
                    return;
                }

                const exportData = users.map(user => ({
                    'ID': user.id_usuario,
                    'Nombre Completo': user.nombre_completo || '',
                    'Email': user.email || '',
                    'Teléfono': user.telefono || '',
                    'Estado': user.activo ? 'Activo' : 'Inactivo',
                    'Fecha Registro': user.fecha_registro ? fmtDateOnly(user.fecha_registro) : '',
                    'Última Sesión': user.ultima_sesion ? fmtDate(user.ultima_sesion) : 'Nunca',
                    'Código Telegan': user.codigo_telegan || '',
                    'Días Inactivo': user.dias_inactivo || 0
                }));

                const ws = XLSX.utils.json_to_sheet(exportData);
                const wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, 'Usuarios Inactivos');

                if (activeFilters.length > 0) {
                    const filterData = [{ 'Filtros Aplicados': activeFilters.join('; ') }];
                    const filterWs = XLSX.utils.json_to_sheet(filterData);
                    XLSX.utils.book_append_sheet(wb, filterWs, 'Filtros');
                }

                const dateStr = new Date().toISOString().split('T')[0];
                const fileName = `usuarios_inactivos_${dateStr}.xlsx`;
                XLSX.writeFile(wb, fileName);

                alert(`✅ ${users.length} usuario(s) descargado(s) exitosamente.\n\nArchivo: ${fileName}`);
            } catch (error) {
                console.error('Error al descargar usuarios:', error);
                alert('Error al descargar usuarios: ' + error.message);
            } finally {
                if (els.downloadUsers) {
                    els.downloadUsers.disabled = false;
                    els.downloadUsers.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg> Descargar Usuarios`;
                }
            }
        }

        if (els.downloadUsers) {
            els.downloadUsers.addEventListener('click', downloadUsers);
        }

        // Función para manejar el ordenamiento
        function handleSort(column) {
            if (state.sortBy === column) {
                state.sortOrder = state.sortOrder === 'ASC' ? 'DESC' : 'ASC';
            } else {
                state.sortBy = column;
                state.sortOrder = 'ASC';
            }
            state.page = 1;
            updateSortIndicators();
            loadUsers();
        }

        function updateSortIndicators() {
            document.querySelectorAll('th.sortable').forEach(th => {
                th.classList.remove('sort-asc', 'sort-desc');
            });
            const activeHeader = document.querySelector(`th.sortable[data-sort="${state.sortBy}"]`);
            if (activeHeader) {
                activeHeader.classList.add(state.sortOrder === 'ASC' ? 'sort-asc' : 'sort-desc');
            }
        }

        async function deactivateUser(userId, userName) {
            if (!confirm(`¿Estás seguro de desactivar la cuenta de "${userName}"?\n\nEsta acción desactivará el usuario y no podrá acceder al sistema.`)) {
                return;
            }
            
            try {
                const api = new ApiClient();
                const response = await api.put('api/users-update.php', {
                    id_usuario: userId,
                    activo: false
                });
                
                if (!response.success) {
                    throw new Error(response.error || 'Error al desactivar usuario');
                }
                
                alert('Usuario desactivado exitosamente');
                loadUsers();
            } catch (e) {
                console.error('Error deactivating user:', e);
                alert('Error al desactivar usuario: ' + e.message);
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('th.sortable').forEach(th => {
                th.addEventListener('click', function() {
                    const column = this.getAttribute('data-sort');
                    if (column) {
                        handleSort(column);
                    }
                });
            });
            updateSortIndicators();
        });

        loadUsers();
    </script>
    <!-- SheetJS para exportar a Excel/CSV (desde CDNJS) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.5/xlsx.full.min.js"></script>
    <script src="../../js/theme-common.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            
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

