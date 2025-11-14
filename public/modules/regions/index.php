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

$layoutActive = 'regions';
$moduleTitle = 'Regiones Umbral';
$moduleSubtitle = 'Define zonas geográficas para aplicar umbrales diferenciados en el monitoreo';

ob_start();
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>
<script src="https://unpkg.com/leaflet-providers@1.13.0/leaflet-providers.js"></script>
<script src="https://unpkg.com/@turf/turf@6.5.0/turf.min.js"></script>
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
    .toolbar {
        display: grid;
        grid-template-columns: 1fr auto auto;
        gap: 0.5rem;
        align-items: center;
        margin-bottom: 0.75rem;
        position: sticky;
        top: calc(var(--header-height) + 8px);
        z-index: 10;
        background: var(--bg-primary);
        padding: 0.5rem 0 0.25rem 0;
    }
    .input, .select {
        border: 1px solid var(--border-color);
        background: var(--bg-card);
        color: var(--text-primary);
        border-radius: 10px;
        padding: 0.5rem 0.75rem;
        font-size: 0.9rem;
        height: 36px;
        transition: box-shadow 0.2s ease, transform 0.2s ease;
    }
    .input:focus, .select:focus {
        box-shadow: 0 8px 16px rgba(77,161,217,0.15);
        transform: translateY(-1px);
        border-color: var(--accent-secondary);
        outline: none;
    }
    .btn-primary {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
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
    .btn-primary:hover {
        transform: translateY(-2px) scale(1.01);
        box-shadow: var(--shadow-md);
    }
    .table-card {
        border: 1px solid var(--border-color);
        border-radius: 16px;
        overflow: hidden;
        background: var(--bg-card);
        margin-top: 0.5rem;
        box-shadow: var(--shadow-sm);
        transition: box-shadow 0.3s ease;
    }
    .table-card:hover {
        box-shadow: var(--shadow-md);
    }
    table.regions {
        width: 100%;
        border-collapse: collapse;
    }
    th, td {
        text-align: left;
        padding: 12px 16px;
        border-bottom: 1px solid rgba(0,0,0,0.05);
        font-size: 0.92rem;
    }
    thead th {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-secondary);
        background: var(--bg-secondary);
        position: sticky;
        top: 0;
        z-index: 5;
    }
    tbody tr:hover {
        background: rgba(77,161,217,0.06);
    }
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.25rem 0.6rem;
        border-radius: 8px;
        font-size: 0.75rem;
        background: rgba(77,161,217,0.15);
        color: var(--accent-secondary);
    }
    .actions-cell {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }
    .btn-icon {
        border: none;
        background: transparent;
        color: var(--text-secondary);
        padding: 0.35rem;
        border-radius: 10px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: transform 0.2s ease, background 0.2s ease, color 0.2s ease;
    }
    .btn-icon:hover {
        background: rgba(77,161,217,0.1);
        color: var(--accent-secondary);
        transform: translateY(-1px);
    }
    .btn-icon svg {
        width: 16px;
        height: 16px;
    }
    .pagination {
        display: flex;
        gap: 0.5rem;
        align-items: center;
        justify-content: flex-end;
        margin-top: 0.75rem;
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
    .btn-secondary:disabled {
        opacity: 0.4;
        cursor: default;
        transform: none;
    }
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.45);
        z-index: 10000;
        align-items: flex-end;
        justify-content: center;
        padding: 1rem;
        backdrop-filter: blur(2px);
    }
    .modal.show {
        display: flex;
    }
    .modal-content {
        background: var(--bg-card);
        border-radius: 18px 18px 0 0;
        width: min(760px, 100%);
        max-height: 92vh;
        overflow-y: auto;
        box-shadow: var(--shadow-lg);
        animation: slideUp 0.35s ease;
        padding: 1.5rem;
    }
    @keyframes slideUp {
        from { transform: translateY(30px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }
    .modal-title {
        font-size: 1.3rem;
        font-weight: 600;
    }
    .modal-close {
        border: none;
        background: transparent;
        color: var(--text-secondary);
        cursor: pointer;
        padding: 0.25rem;
        border-radius: 999px;
    }
    .modal-close:hover {
        background: rgba(0,0,0,0.05);
    }
    .form-grid {
        display: grid;
        gap: 1rem;
    }
    .form-grid.two-col {
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
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
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
    }
    .label-hint {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: rgba(77,161,217,0.15);
        color: var(--accent-secondary);
        font-size: 0.7rem;
        font-weight: 700;
        cursor: help;
    }
    .label-hint:hover {
        background: rgba(77,161,217,0.25);
    }
    .form-input, .form-textarea {
        border: 1px solid var(--border-color);
        border-radius: 10px;
        background: var(--bg-card);
        color: var(--text-primary);
        padding: 0.55rem 0.75rem;
        font-size: 0.92rem;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .form-input:focus, .form-textarea:focus {
        border-color: var(--accent-secondary);
        box-shadow: 0 0 0 3px rgba(77,161,217,0.12);
        outline: none;
    }
    .form-textarea {
        min-height: 110px;
        resize: vertical;
    }
    .modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
        margin-top: 1.5rem;
        padding-top: 1.5rem;
        border-top: 1px solid var(--border-color);
    }
    .btn-outline {
        border: 1px solid var(--border-color);
        background: transparent;
        color: var(--text-primary);
        padding: 0.55rem 1.2rem;
        border-radius: 12px;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.2s ease;
    }
    .btn-outline:hover {
        background: var(--bg-secondary);
    }
    .btn-modal-confirm {
        border: none;
        background: var(--accent-primary);
        color: white;
        padding: 0.55rem 1.3rem;
        border-radius: 12px;
        font-weight: 600;
        cursor: pointer;
    }
    .empty-state {
        padding: 2.5rem 1.5rem;
        text-align: center;
        color: var(--text-secondary);
    }
    .empty-state strong {
        display: block;
        margin-bottom: 0.5rem;
        color: var(--text-primary);
    }
    #regionMap {
        margin-top: 0.75rem;
        height: 320px;
        border-radius: 14px;
        overflow: hidden;
        border: 1px solid var(--border-color);
    }
    @media (max-width: 768px) {
        .modal-content { padding: 1.25rem; }
        .toolbar { grid-template-columns: 1fr; gap: 0.75rem; }
        .pagination { justify-content: center; }
    }
</style>
<?php
$moduleHead = ob_get_clean();

ob_start();
?>
<div class="toolbar">
    <div style="width:100%; display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:0.5rem;">
        <input id="filterSearch" class="input" type="search" placeholder="Buscar por código o nombre">
        <input id="filterCountry" class="input" maxlength="2" placeholder="País (ISO2) ej. HN">
        <select id="filterType" class="select">
            <option value="">Todos los tipos</option>
            <option value="PAIS">País</option>
            <option value="DEPARTAMENTO">Departamento</option>
            <option value="MUNICIPIO">Municipio</option>
            <option value="ZONA_CLIMATICA">Zona climática</option>
        </select>
        <select id="filterActive" class="select">
            <option value="">Todas</option>
            <option value="1">Solo activas</option>
            <option value="0">Solo inactivas</option>
        </select>
    </div>
    <button id="btnClearFilters" class="btn-secondary">Limpiar</button>
    <button id="btnNewRegion" class="btn-primary">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
        Nueva región
    </button>
</div>

<div class="table-card">
    <table class="regions">
        <thead>
            <tr>
                <th style="width:110px;">Código</th>
                <th>Nombre</th>
                <th style="width:100px;">País</th>
                <th style="width:140px;">Tipo</th>
                <th style="width:170px;">Metadata</th>
                <th style="width:100px;">Estado</th>
                <th style="width:140px;">Creación</th>
                <th style="width:80px;">Acciones</th>
            </tr>
        </thead>
        <tbody id="regionsTableBody">
            <tr>
                <td colspan="8" class="empty-state">
                    <strong>Sin datos por el momento</strong>
                    Cargando regiones...
                </td>
            </tr>
        </tbody>
    </table>
</div>

<div class="pagination">
    <button id="btnPrevPage" class="btn-secondary">Anterior</button>
    <span id="paginationInfo" style="font-size:0.85rem; color: var(--text-secondary);"></span>
    <button id="btnNextPage" class="btn-secondary">Siguiente</button>
</div>

<div id="regionModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title" id="modalTitle">Nueva región</h2>
            <button class="modal-close" id="modalClose">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <form id="regionForm">
            <div class="form-grid">
                <div class="form-grid two-col">
                    <div class="form-group">
                        <label class="form-label" for="codigo">Código *<span class="label-hint" title="Identificador único de la región, ej. HN_OLANCHO." aria-label="Ayuda campo código">?</span></label>
                        <input type="text" id="codigo" class="form-input" maxlength="30" required placeholder="HN_OLANCHO">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="nombre">Nombre *<span class="label-hint" title="Nombre descriptivo de la región." aria-label="Ayuda campo nombre">?</span></label>
                        <input type="text" id="nombre" class="form-input" maxlength="200" required placeholder="Olancho">
                    </div>
                </div>
                <div class="form-grid two-col">
                    <div class="form-group">
                        <label class="form-label" for="pais_codigo_iso">País (ISO2)<span class="label-hint" title="Código ISO-3166 de dos letras (ej. HN, GT, MX)." aria-label="Ayuda campo país">?</span></label>
                        <input type="text" id="pais_codigo_iso" class="form-input" maxlength="2" placeholder="HN">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="tipo">Tipo<span class="label-hint" title="Clasificación de la región (País, Departamento, Municipio, etc.)." aria-label="Ayuda campo tipo">?</span></label>
                        <input type="text" id="tipo" class="form-input" maxlength="100" placeholder="DEPARTAMENTO">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="geom_wkt">Geometría (GeoJSON)<span class="label-hint" title="Polígono/multipolígono en formato GeoJSON (SRID 4326)." aria-label="Ayuda campo geometría">?</span></label>
                    <textarea id="geom_wkt" class="form-textarea" placeholder='{"type":"Polygon","coordinates":[...]}'></textarea>
                    <button type="button" id="btnLoadWkt" class="btn-outline" style="align-self:flex-start;">Dibujar desde GeoJSON</button>
                    <div id="regionMap"></div>
                    <small id="areaInfo" style="font-size:0.78rem;color:var(--text-secondary);"></small>
                </div>
                <div class="form-group">
                    <label class="form-label" for="metadata">Metadata (JSON opcional)<span class="label-hint" title="Información adicional en formato JSON (clima, altitud, notas)." aria-label="Ayuda campo metadata">?</span></label>
                    <textarea id="metadata" class="form-textarea" placeholder='{"clima":"tropical_humedo","altitud_promedio_msnm":450}'></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Estado inicial<span class="label-hint" title="Define si la región estará disponible inmediatamente." aria-label="Ayuda estado inicial">?</span></label>
                    <div class="form-switch">
                        <input type="checkbox" id="activo" checked>
                        <span style="font-size:0.85rem; color:var(--text-secondary);">Región activa</span>
                    </div>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-outline" id="formCancel">Cancelar</button>
                <button type="submit" class="btn-modal-confirm" id="modalSubmit">Guardar región</button>
            </div>
        </form>
    </div>
</div>
<?php
$moduleContent = ob_get_clean();

ob_start();
?>
<script type="module" src="./regions.js"></script>
<?php
$moduleScripts = ob_get_clean();

require_once '../_layout.php';
