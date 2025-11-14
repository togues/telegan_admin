<?php
session_start();

require_once '../../../src/Config/Security.php';
Security::init();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../../../auth/login.php');
    exit;
}

$userName = $_SESSION['admin_nombre'] ?? $_SESSION['admin_name'] ?? 'Usuario';
$sessionToken = $_SESSION['session_token'] ?? null;

$layoutActive = 'farms';
$moduleTitle = 'Fincas';
$moduleSubtitle = 'Consulta y administra las fincas registradas';

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
</script>
<style>
    .toolbar {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 0.75rem;
        align-items: center;
        margin-bottom: 1rem;
    }
    .filters-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 0.5rem;
    }
    .input, .select {
        border: 1px solid var(--border-color);
        background: var(--bg-card);
        color: var(--text-primary);
        border-radius: 10px;
        padding: 0.5rem 0.75rem;
        font-size: 0.9rem;
        height: 38px;
        transition: box-shadow 0.2s ease, transform 0.2s ease;
    }
    .input:focus, .select:focus {
        box-shadow: 0 8px 16px rgba(77,161,217,0.15);
        outline: none;
        border-color: var(--accent-secondary);
    }
    .table-card {
        border: 1px solid var(--border-color);
        border-radius: 16px;
        background: var(--bg-card);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
    }
    table.farms-table {
        width: 100%;
        border-collapse: collapse;
    }
    table.farms-table thead th {
        text-transform: uppercase;
        font-size: 0.72rem;
        letter-spacing: 0.05em;
        color: var(--text-secondary);
        background: var(--bg-secondary);
        padding: 12px 14px;
        text-align: left;
    }
    table.farms-table tbody td {
        padding: 14px;
        border-bottom: 1px solid rgba(0,0,0,0.05);
        font-size: 0.92rem;
    }
    table.farms-table tbody tr:hover {
        background: rgba(77, 161, 217, 0.06);
    }
    .status-pill {
        display: inline-flex;
        padding: 0.2rem 0.75rem;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 600;
        align-items: center;
        gap: 0.35rem;
    }
    .pill-activa {
        background: rgba(15, 159, 109, 0.16);
        color: #0f9f6d;
    }
    .pill-inactiva, .pill-desactivada {
        background: rgba(220, 38, 38, 0.16);
        color: #dc2626;
    }
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.2rem 0.6rem;
        border-radius: 8px;
        font-size: 0.75rem;
        background: rgba(77, 161, 217, 0.12);
        color: var(--accent-secondary);
    }
    .badge-warning {
        background: rgba(251, 191, 36, 0.18);
        color: #d97706;
    }
    .actions-cell {
        display: flex;
        gap: 0.35rem;
        align-items: center;
    }
    .btn-icon {
        border: none;
        background: transparent;
        color: var(--text-secondary);
        padding: 0.35rem;
        border-radius: 10px;
        cursor: pointer;
        transition: transform 0.2s ease, background 0.2s ease, color 0.2s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .btn-icon:hover {
        background: rgba(77, 161, 217, 0.1);
        color: var(--accent-secondary);
        transform: translateY(-1px);
    }
    .pagination {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        justify-content: flex-end;
        margin-top: 1rem;
    }
    .btn-secondary {
        border: 1px solid var(--border-color);
        background: var(--bg-card);
        color: var(--text-primary);
        padding: 0.45rem 0.9rem;
        border-radius: 10px;
        cursor: pointer;
        font-size: 0.85rem;
        transition: all 0.2s ease;
    }
    .btn-secondary:hover {
        background: var(--bg-secondary);
        transform: translateY(-1px);
    }
    .modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.45);
        z-index: 10000;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        backdrop-filter: blur(2px);
    }
    .modal.show {
        display: flex;
    }
    .modal-content {
        background: var(--bg-card);
        border-radius: 18px;
        width: min(720px, 100%);
        max-height: 96vh;
        overflow-y: auto;
        box-shadow: var(--shadow-lg);
        padding: 1.5rem;
        display: flex;
        flex-direction: column;
        gap: 1.1rem;
    }
    .form-grid {
        display: grid;
        gap: 0.75rem;
    }
    .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 0.75rem;
        align-items: center;
    }
    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
    }
    .form-label {
        font-size: 0.82rem;
        font-weight: 600;
        color: var(--text-secondary);
    }
    .form-textarea {
        min-height: 110px;
        resize: vertical;
        border: 1px solid var(--border-color);
        background: var(--bg-card);
        color: var(--text-primary);
        border-radius: 10px;
        padding: 0.6rem 0.75rem;
        transition: border 0.2s ease, box-shadow 0.2s ease;
    }
    .form-textarea:focus {
        outline: none;
        border-color: var(--accent-secondary);
        box-shadow: 0 0 0 3px rgba(77, 161, 217, 0.12);
    }
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 0.75rem;
    }
    .info-card {
        border: 1px solid var(--border-color);
        background: var(--bg-secondary);
        border-radius: 12px;
        padding: 0.8rem;
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }
    .info-card strong {
        font-size: 0.9rem;
    }
    .info-card span {
        font-size: 0.8rem;
        color: var(--text-secondary);
    }
    .modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 0.5rem;
    }
    .btn-primary {
        border: none;
        background: var(--gradient-primary);
        color: white;
        padding: 0.55rem 1.2rem;
        border-radius: 12px;
        cursor: pointer;
        font-size: 0.9rem;
        font-weight: 600;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .btn-primary:disabled {
        opacity: 0.6;
        cursor: default;
    }
    .btn-danger {
        border: none;
        background: linear-gradient(135deg, #f97316 0%, #ef4444 100%);
        color: white;
        padding: 0.55rem 1.2rem;
        border-radius: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .toast {
        position: fixed;
        bottom: 24px;
        right: 24px;
        background: rgba(109, 190, 69, 0.95);
        color: #fff;
        padding: 0.75rem 1.2rem;
        border-radius: 12px;
        box-shadow: 0 12px 24px rgba(0,0,0,0.15);
        z-index: 11000;
        opacity: 0;
        transform: translateY(20px);
        transition: opacity 0.3s ease, transform 0.3s ease;
    }
    .toast.show {
        opacity: 1;
        transform: translateY(0);
    }
    .toast.error {
        background: rgba(220, 38, 38, 0.92);
    }
    .toast.info {
        background: rgba(77, 161, 217, 0.92);
    }
    @media (max-width: 768px) {
        .toolbar {
            grid-template-columns: 1fr;
        }
        .modal-content {
            padding: 1.2rem;
        }
        .modal-actions {
            flex-direction: column;
            align-items: stretch;
        }
    }
</style>
<?php
$moduleHead = ob_get_clean();

ob_start();
?>
<div class="toolbar">
    <div class="filters-grid">
        <input id="filterSearch" class="input" type="search" placeholder="Buscar por nombre o código">
        <select id="filterEstado" class="select">
            <option value="">Todos los estados</option>
        </select>
        <select id="filterPais" class="select">
            <option value="">Todos los países</option>
        </select>
        <select id="filterGeometry" class="select">
            <option value="">Todas las geometrías</option>
            <option value="con">Con geometría</option>
            <option value="sin">Sin geometría</option>
        </select>
        <select id="filterCreador" class="select">
            <option value="">Todos los creadores</option>
        </select>
    </div>
    <button id="btnClearFilters" class="btn-secondary">Limpiar filtros</button>
</div>

<div class="table-card">
    <table class="farms-table">
        <thead>
            <tr>
                <th>Estado</th>
                <th>Finca</th>
                <th>País</th>
                <th>Creada por</th>
                <th>Creación</th>
                <th>Geometría</th>
                <th>Potreros</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody id="farmsTableBody">
            <tr>
                <td colspan="8" class="empty-state">
                    <strong>Sin registros</strong>
                    Aún no hay fincas que coincidan con los filtros seleccionados.
                </td>
            </tr>
        </tbody>
    </table>
</div>

<div class="pagination">
    <button id="btnPrevPage" class="btn-secondary">Anterior</button>
    <span id="paginationInfo" style="font-size:0.85rem; color: var(--text-secondary);">Página 1 de 1</span>
    <button id="btnNextPage" class="btn-secondary">Siguiente</button>
</div>

<div id="farmModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="farmModalTitle">
    <div class="modal-content">
        <div class="modal-header" style="display:flex; justify-content:space-between; align-items:center; gap:1rem;">
            <div>
                <h2 class="modal-title" id="farmModalTitle">Editar finca</h2>
                <p id="farmModalSubtitle" style="margin:0; font-size:0.85rem; color:var(--text-secondary);"></p>
            </div>
            <button class="btn-icon" id="farmModalClose" title="Cerrar">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        <form id="farmForm" class="form-grid">
            <input type="hidden" id="fieldId">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="fieldNombre">Nombre de la finca *</label>
                    <input id="fieldNombre" class="input" type="text" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="fieldCodigo">Código Telegan</label>
                    <input id="fieldCodigo" class="input" type="text" maxlength="50">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="fieldEstado">Estado</label>
                    <select id="fieldEstado" class="select">
                        <option value="ACTIVA">Activa</option>
                        <option value="INACTIVA">Inactiva</option>
                        <option value="DESACTIVADA">Desactivada</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="fieldPais">País</label>
                    <select id="fieldPais" class="select">
                        <option value="">Sin asignar</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="fieldDescripcion">Descripción</label>
                <textarea id="fieldDescripcion" class="form-textarea" placeholder="Notas o detalles de la finca"></textarea>
            </div>

            <div class="info-grid">
                <div class="info-card">
                    <strong>Geometría</strong>
                    <span id="infoGeometry">—</span>
                    <span id="infoArea">Área oficial: —</span>
                </div>
                <div class="info-card">
                    <strong>Potreros asociados</strong>
                    <span id="infoPotreros">—</span>
                </div>
                <div class="info-card">
                    <strong>Creada por</strong>
                    <span id="infoCreador">—</span>
                    <span id="infoCreadorEmail"></span>
                </div>
                <div class="info-card">
                    <strong>Registro</strong>
                    <span id="infoFechas">—</span>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" id="formCancel">Cancelar</button>
                <button type="submit" class="btn-primary" id="formSubmit">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>
<?php
$moduleContent = ob_get_clean();

ob_start();
?>
<script type="module" src="./farms.js"></script>
<?php
$moduleScripts = ob_get_clean();

require_once '../_layout.php';

