<?php
if (!isset($moduleTitle)) {
    $moduleTitle = 'Panel';
}
if (!isset($moduleSubtitle)) {
    $moduleSubtitle = '';
}
if (!isset($moduleContent)) {
    $moduleContent = '<p>No hay contenido</p>';
}
if (!isset($moduleScripts)) {
    $moduleScripts = '';
}
$layoutActive = $layoutActive ?? '';
$bottomNav = $bottomNav ?? true;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($moduleTitle); ?> - Telegan Admin</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        (function() {
            try {
                const savedTheme = localStorage.getItem('telegan-theme');
                const theme = savedTheme || 'dark';
                document.documentElement.setAttribute('data-theme', theme);
            } catch (_) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
    <?php echo $moduleHead ?? ''; ?>
</head>
<body>
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
                        <span><?php echo strtoupper(substr($_SESSION['admin_nombre'] ?? 'US', 0, 2)); ?></span>
                    </div>
                    <span class="user-name"><?php echo htmlspecialchars($_SESSION['admin_nombre'] ?? 'Usuario'); ?></span>
                    <a href="../../../auth/logout.php" class="btn-secondary" style="margin-left:0.75rem;">Cerrar sesión</a>
                </div>
            </div>
        </div>
    </header>

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
                <li class="menu-item <?php echo $layoutActive === 'dashboard' ? 'active' : ''; ?>">
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
                <li class="menu-item <?php echo $layoutActive === 'users' ? 'active' : ''; ?>">
                    <a href="../users/" class="menu-link">
                        <svg class="menu-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        <span class="menu-text">Usuarios</span>
                    </a>
                </li>
                <li class="menu-item <?php echo $layoutActive === 'system-users' ? 'active' : ''; ?>">
                    <a href="../system-users/" class="menu-link">
                        <svg class="menu-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="8.5" cy="7" r="4"></circle>
                            <path d="M20 8v6M23 11h-6"></path>
                        </svg>
                        <span class="menu-text">Administradores</span>
                    </a>
                </li>
                <li class="menu-item <?php echo $layoutActive === 'farms' ? 'active' : ''; ?>">
                    <a href="../farms/" class="menu-link">
                        <svg class="menu-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                        </svg>
                        <span class="menu-text">Fincas</span>
                    </a>
                </li>
                <li class="menu-item <?php echo $layoutActive === 'providers' ? 'active' : ''; ?>">
                    <a href="../providers/" class="menu-link">
                        <svg class="menu-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 8V6a2 2 0 0 0-2-2h-4"></path>
                            <path d="M3 8V6a2 2 0 0 1 2-2h4"></path>
                            <path d="M21 16v2a2 2 0 0 1-2 2h-4"></path>
                            <path d="M3 16v2a2 2 0 0 0 2 2h4"></path>
                        </svg>
                        <span class="menu-text">Proveedores</span>
                    </a>
                </li>
                <li class="menu-item <?php echo $layoutActive === 'indices' ? 'active' : ''; ?>">
                    <a href="../indices/" class="menu-link">
                        <svg class="menu-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 3h18v6H3z"></path>
                            <path d="M3 15h18v6H3z"></path>
                            <path d="M8 9v6"></path>
                            <path d="M16 9v6"></path>
                        </svg>
                        <span class="menu-text">Índices</span>
                    </a>
                </li>
                <li class="menu-item <?php echo $layoutActive === 'regions' ? 'active' : ''; ?>">
                    <a href="../regions/" class="menu-link">
                        <svg class="menu-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 4h16v4H4z"></path>
                            <path d="M4 12h16v4H4z"></path>
                            <path d="M4 20h10v-4H4z"></path>
                        </svg>
                        <span class="menu-text">Regiones</span>
                    </a>
                </li>
                <li class="menu-item <?php echo $layoutActive === 'fincas-geom' ? 'active' : ''; ?>">
                    <a href="../fincas-geom/" class="menu-link">
                        <svg class="menu-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                            <path d="M8 10h8"></path>
                            <path d="M12 6v8"></path>
                        </svg>
                        <span class="menu-text">Geom. Fincas</span>
                    </a>
                </li>
                <li class="menu-item <?php echo $layoutActive === 'thresholds' ? 'active' : ''; ?>">
                    <a href="../thresholds/" class="menu-link">
                        <svg class="menu-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 14l2 2 4-4"></path>
                            <rect x="3" y="5" width="18" height="14" rx="2" ry="2"></rect>
                        </svg>
                        <span class="menu-text">Umbrales</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <main class="main-content">
        <div class="content-header">
            <h1 class="page-title"><?php echo htmlspecialchars($moduleTitle); ?></h1>
            <?php if ($moduleSubtitle) : ?>
                <p class="page-subtitle"><?php echo htmlspecialchars($moduleSubtitle); ?></p>
            <?php endif; ?>
        </div>

        <?php echo $moduleContent; ?>
    </main>

    <?php if ($bottomNav) : ?>
    <nav class="bottom-nav">
        <a href="../../dashboard.php" class="nav-item <?php echo $layoutActive === 'dashboard' ? 'active' : ''; ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="7" height="7"></rect>
                <rect x="14" y="3" width="7" height="7"></rect>
                <rect x="14" y="14" width="7" height="7"></rect>
                <rect x="3" y="14" width="7" height="7"></rect>
            </svg>
            <span>Dashboard</span>
        </a>
        <a href="../providers/" class="nav-item <?php echo $layoutActive === 'providers' ? 'active' : ''; ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 8V6a2 2 0 0 0-2-2h-4"></path>
                <path d="M3 8V6a2 2 0 0 1 2-2h4"></path>
                <path d="M21 16v2a2 2 0 0 1-2 2h-4"></path>
                <path d="M3 16v2a2 2 0 0 0 2 2h4"></path>
            </svg>
            <span>Proveedores</span>
        </a>
        <a href="../indices/" class="nav-item <?php echo $layoutActive === 'indices' ? 'active' : ''; ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 3h18v6H3z"></path>
                <path d="M3 15h18v6H3z"></path>
                <path d="M8 9v6"></path>
                <path d="M16 9v6"></path>
            </svg>
            <span>Índices</span>
        </a>
        <a href="../regions/" class="nav-item <?php echo $layoutActive === 'regions' ? 'active' : ''; ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 4h16v4H4z"></path>
                <path d="M4 12h16v4H4z"></path>
                <path d="M4 20h10v-4H4z"></path>
            </svg>
            <span>Regiones</span>
        </a>
        <a href="../fincas-geom/" class="nav-item <?php echo $layoutActive === 'fincas-geom' ? 'active' : ''; ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                <path d="M8 10h8"></path>
                <path d="M12 6v8"></path>
            </svg>
            <span>Geom. Fincas</span>
        </a>
        <a href="../thresholds/" class="nav-item <?php echo $layoutActive === 'thresholds' ? 'active' : ''; ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 14l2 2 4-4"></path>
                <rect x="3" y="5" width="18" height="14" rx="2" ry="2"></rect>
            </svg>
            <span>Umbrales</span>
        </a>
    </nav>
    <?php endif; ?>

    <script src="../../js/theme-common.js"></script>
    <?php echo $moduleScripts; ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
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
