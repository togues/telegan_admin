<?php
/**
 * Módulo de Gestión de Usuarios del Sistema (Administradores) - CRUD Completo
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

$layoutActive = 'system-users';
$moduleTitle = 'Administradores del Sistema';
$moduleSubtitle = 'Gestión completa de usuarios administrativos (CRUD)';

ob_start();
?>
<script>
    window.userSession = {
        loggedIn: true,
        userName: <?php echo json_encode($userName); ?>,
        userId: <?php echo json_encode($_SESSION['admin_id'] ?? null); ?>
    };
    <?php if ($sessionToken) : ?>
    window.sessionToken = <?php echo json_encode($sessionToken); ?>;
    <?php endif; ?>
    document.addEventListener('DOMContentLoaded', function() {
        try {
            const savedSidebar = localStorage.getItem('sidebarCollapsed');
            if (savedSidebar === 'true') {
                document.body.classList.add('sidebar-collapsed');
            } else if (savedSidebar === null && window.innerWidth >= 1024) {
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
    .table-wrap { border: 1px solid var(--border-color); border-radius: 10px; overflow: hidden; background: var(--bg-card); margin-top: 0.5rem; max-height: calc(100vh - 200px); overflow-y: auto; position: relative; }
    table.admin-users { width: 100%; border-collapse: collapse; border-spacing: 0; }
    thead.sticky { position: sticky; top: 0; background: var(--bg-card); z-index: 10; box-shadow: 0 2px 4px rgba(0,0,0,0.08); }
    th, td { text-align: left; padding: 10px 12px; border-bottom: 1px solid var(--border-color); font-size: 0.9rem; white-space: nowrap; }
    th.header-two-lines { height: 60px; line-height: 1.3; white-space: normal; word-break: break-word; vertical-align: middle; }
    th.sortable { cursor: pointer; user-select: none; position: relative; padding-right: 24px; transition: background-color 0.2s ease; }
    th.sortable:hover { background: var(--bg-secondary); }
    th.sortable .sort-indicator { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); display: inline-flex; flex-direction: column; gap: 2px; opacity: 0.3; }
    th.sortable.sort-asc .sort-indicator,
    th.sortable.sort-desc .sort-indicator { opacity: 1; }
    th.sortable.sort-asc .sort-indicator .arrow-up { color: var(--accent-primary); }
    th.sortable.sort-desc .sort-indicator .arrow-down { color: var(--accent-primary); }
    th.sortable .sort-indicator svg { width: 10px; height: 10px; }
    tbody tr:hover { background: var(--bg-secondary); }
    th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary); background: var(--bg-card); font-weight: 600; }
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
<?php
$moduleHead = ob_get_clean();

ob_start();
?>
<div class="content-header">
    <h1 class="page-title">Administradores del Sistema</h1>
    <p class="page-subtitle">Gestión completa de usuarios administrativos (CRUD)</p>
</div>

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

<div class="table-wrap">
    <table class="admin-users">
        <thead class="sticky">
            <tr>
                <th style="width:80px;">Acciones</th>
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
                <th class="sortable header-two-lines" data-sort="telefono">
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
                <th class="sortable" data-sort="rol">
                    Rol
                    <span class="sort-indicator">
                        <svg class="arrow-up" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="18 15 12 9 6 15"></polyline>
                        </svg>
                        <svg class="arrow-down" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </span>
                </th>
                <th class="sortable" data-sort="activo">
                    Estado
                    <span class="sort-indicator">
                        <svg class="arrow-up" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="18 15 12 9 6 15"></polyline>
                        </svg>
                        <svg class="arrow-down" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </span>
                </th>
                <th class="sortable header-two-lines" data-sort="email_verificado">
                    Email<br>Verificado
                    <span class="sort-indicator">
                        <svg class="arrow-up" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="18 15 12 9 6 15"></polyline>
                        </svg>
                        <svg class="arrow-down" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </span>
                </th>
                <th class="sortable header-two-lines" data-sort="ultima_sesion">
                    Última<br>Sesión
                    <span class="sort-indicator">
                        <svg class="arrow-up" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="18 15 12 9 6 15"></polyline>
                        </svg>
                        <svg class="arrow-down" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </span>
                </th>
                <th class="sortable" data-sort="fecha_registro">
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
            </tr>
        </thead>
        <tbody id="tbody">
            <tr>
                <td colspan="10" style="text-align:center; color: var(--text-secondary); padding: 16px;">Cargando...</td>
            </tr>
        </tbody>
    </table>
</div>

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
<?php
$moduleContent = ob_get_clean();

ob_start();
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.5/xlsx.full.min.js"></script>
<script type="module" src="./system-users.js"></script>
<?php
$moduleScripts = ob_get_clean();

require_once '../_layout.php';

