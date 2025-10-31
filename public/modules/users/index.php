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
    <script>
        // Token de sesión desde PHP (disponible inmediatamente)
        <?php 
        if ($sessionToken) {
            echo "window.sessionToken = " . json_encode($sessionToken) . ";\n";
        }
        ?>
    </script>
    <style>
        .module-header { background: var(--gradient-primary); color: white; padding: 1.25rem; margin-bottom: 1rem; }
        .module-header h1 { font-size: 1.25rem; margin-bottom: 0.25rem; }
        .breadcrumb { background: var(--bg-secondary); padding: 0.75rem 1rem; border-bottom: 1px solid var(--border-color); }
        .breadcrumb a { color: var(--accent-primary); text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .content-area { padding: 1rem; max-width: 1200px; margin: 0 auto; }
        .toolbar { display: grid; grid-template-columns: 1fr auto auto; gap: 0.5rem; align-items: center; margin-bottom: 0.75rem; position: sticky; top: calc(var(--header-height) + 8px); z-index: 10; background: var(--bg-primary); padding: 0.5rem 0 0.25rem 0; }
        .input, .select { border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-primary); border-radius: 10px; padding: 0.5rem 0.75rem; font-size: 0.9rem; height: 36px; }
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
    </style>
</head>
<body>
    <!-- Breadcrumb -->
    <nav class="breadcrumb">
        <a href="../../index.html">🏠 Inicio</a> > 
        <a href="../index.html">Módulos</a> > 
        <span>Gestión de Usuarios</span>
    </nav>

    <!-- Module Header -->
    <header class="module-header">
        <h1>👥 Gestión de Usuarios</h1>
        <p>Administra usuarios del sistema, roles, permisos y datos demográficos</p>
    </header>

    <!-- Content Area -->
    <div class="content-area">
        <!-- Toolbar filtros -->
        <div class="toolbar">
            <input id="q" class="input" placeholder="Buscar por nombre, email o teléfono" />
            <select id="activo" class="select">
                <option value="">Todos</option>
                <option value="1">Activos</option>
                <option value="0">Inactivos</option>
            </select>
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
                        <th style="width:120px;">Estado</th>
                        <th style="width:160px;">Registro</th>
                        <th style="width:160px;">Última sesión</th>
                        <th style="width:120px;">Código</th>
                    </tr>
                </thead>
                <tbody id="tbody">
                    <tr><td colspan="8" style="text-align:center; color: var(--text-secondary); padding: 16px;">Cargando...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <script type="module" src="./users.js"></script>
</body>
</html>
