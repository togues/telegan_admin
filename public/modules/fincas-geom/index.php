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

$layoutActive = 'fincas-geom';
$moduleTitle = 'Geometrías de Fincas';
$moduleSubtitle = 'Revisa, aprueba o rechaza las geometrías capturadas desde la PWA antes de migrarlas a PostGIS';

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
    .filters-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 0.5rem;
        width: 100%;
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
    .btn-primary:disabled {
        opacity: 0.5;
        cursor: default;
        transform: none;
        box-shadow: none;
    }
    .btn-primary:hover:not(:disabled) {
        transform: translateY(-2px) scale(1.01);
        box-shadow: var(--shadow-md);
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
    table.captures {
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
        background: rgba(77, 161, 217, 0.06);
    }
    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.25rem 0.75rem;
        border-radius: 100px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .pill-pendiente { background: rgba(251, 191, 36, 0.18); color: #d97706; }
    .pill-validada { background: rgba(110, 231, 183, 0.18); color: #0f9f6d; }
    .pill-rechazada { background: rgba(248, 113, 113, 0.18); color: #dc2626; }
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.25rem 0.6rem;
        border-radius: 8px;
        font-size: 0.75rem;
        background: rgba(77, 161, 217, 0.12);
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
        background: rgba(77, 161, 217, 0.1);
        color: var(--accent-secondary);
        transform: translateY(-1px);
    }
    .bulk-banner {
        display: none;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.75rem 1rem;
        margin-bottom: 0.75rem;
        border-radius: 12px;
        background: rgba(16, 185, 129, 0.12);
    }
    .bulk-banner strong { font-size: 0.95rem; }
    .bulk-banner-actions {
        display: flex;
        gap: 0.5rem;
    }
    .map-wrapper {
        position: relative;
        border-radius: 16px;
        overflow: hidden;
        border: 1px solid var(--border-color);
        background: var(--bg-secondary);
    }
    #captureMap {
        height: 360px;
        width: 100%;
    }
    .map-placeholder {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        gap: 0.4rem;
        color: var(--text-secondary);
        background: var(--bg-secondary);
    }
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 0.85rem;
        margin-top: 1rem;
    }
    .info-card {
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 0.9rem 1rem;
        background: var(--bg-secondary);
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
    }
    .info-card h4 {
        font-size: 0.82rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--text-secondary);
    }
    .comment-box {
        width: 100%;
        min-height: 90px;
        resize: vertical;
        padding: 0.65rem 0.75rem;
        border-radius: 12px;
        border: 1px solid var(--border-color);
        background: var(--bg-card);
        color: var(--text-primary);
        transition: border 0.2s ease, box-shadow 0.2s ease;
    }
    .comment-box:focus {
        outline: none;
        border-color: var(--accent-secondary);
        box-shadow: 0 0 0 3px rgba(77, 161, 217, 0.12);
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
        width: min(920px, 100%);
        max-height: 96vh;
        overflow-y: auto;
        box-shadow: var(--shadow-lg);
        padding: 1.5rem;
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
        animation: slideUp 0.35s ease;
    }
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
    }
    .modal-title {
        font-size: 1.35rem;
        font-weight: 600;
    }
    .modal-close {
        border: none;
        background: transparent;
        color: var(--text-secondary);
        cursor: pointer;
        padding: 0.35rem;
        border-radius: 10px;
        transition: background 0.2s ease;
    }
    .modal-close:hover {
        background: rgba(0,0,0,0.05);
    }
    .modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 0.5rem;
    }
    .btn-danger {
        border: none;
        background: linear-gradient(135deg, #f97316 0%, #ef4444 100%);
        color: white;
        padding: 0.55rem 1.35rem;
        border-radius: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .btn-danger:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 15px rgba(239, 68, 68, 0.25);
    }
    .history-list {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }
    .history-item {
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 0.75rem 1rem;
        background: var(--bg-secondary);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
    }
    .history-item time {
        font-size: 0.8rem;
        color: var(--text-secondary);
    }
    @keyframes slideUp {
        from { transform: translateY(30px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    @media (max-width: 768px) {
        .toolbar { grid-template-columns: 1fr; gap: 0.75rem; }
        .filters-grid { grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); }
        .modal-content { padding: 1.25rem; border-radius: 16px; }
        .modal-actions { flex-direction: column; align-items: stretch; }
    }
</style>
<?php
$moduleHead = ob_get_clean();

ob_start();
?>
<div class="bulk-banner" id="bulkBanner">
    <div>
        <strong id="bulkCount">0 capturas seleccionadas</strong>
        <p style="margin:0; font-size:0.82rem; color:var(--text-secondary);">Puedes procesarlas en lote sin repetir capturas ya revisadas.</p>
    </div>
    <div class="bulk-banner-actions">
        <button id="bulkApprove" class="btn-primary" disabled>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
            Aprobar selección
        </button>
        <button id="bulkReject" class="btn-danger" disabled>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
            Rechazar selección
        </button>
    </div>
</div>

<div class="toolbar">
    <div class="filters-grid">
        <input id="filterSearch" class="input" type="search" placeholder="Buscar por finca, código o comentario">
        <select id="filterEstado" class="select">
            <option value="">Todas las capturas</option>
            <option value="PENDIENTE">Pendientes</option>
            <option value="VALIDADA">Validadas</option>
            <option value="RECHAZADA">Rechazadas</option>
        </select>
        <select id="filterTipo" class="select">
            <option value="">Todos los tipos</option>
            <option value="POLYGON">Polígonos</option>
            <option value="POINT">Puntos</option>
        </select>
        <input id="filterFechaDesde" type="date" class="input" placeholder="Desde">
        <input id="filterFechaHasta" type="date" class="input" placeholder="Hasta">
    </div>
    <button id="btnClearFilters" class="btn-secondary">Limpiar filtros</button>
    <div class="pagination" style="gap:0.35rem;">
        <button id="btnPrevPage" class="btn-secondary">Anterior</button>
        <span id="paginationInfo" style="font-size:0.85rem; color: var(--text-secondary);"></span>
        <button id="btnNextPage" class="btn-secondary">Siguiente</button>
    </div>
</div>

<div class="table-card">
    <table class="captures">
        <thead>
            <tr>
                <th style="width:44px;"><input type="checkbox" id="selectAll" aria-label="Seleccionar todas"></th>
                <th style="width:110px;">Estado</th>
                <th>Finca</th>
                <th style="width:130px;">Código</th>
                <th style="width:160px;">Capturado por</th>
                <th style="width:160px;">Fecha captura</th>
                <th style="width:120px;">Tipo</th>
                <th style="width:140px;">Área estimada</th>
                <th style="width:120px;">Acciones</th>
            </tr>
        </thead>
        <tbody id="capturesTableBody">
            <tr>
                <td colspan="9" class="empty-state">
                    <strong>Sin capturas por el momento</strong>
                    Cuando la PWA sincronice un envío aparecerá aquí.
                </td>
            </tr>
        </tbody>
    </table>
</div>

<div class="pagination" style="margin-top:1rem; justify-content:flex-end; gap:0.35rem;">
    <button id="btnPrevPageBottom" class="btn-secondary">Anterior</button>
    <span id="paginationInfoBottom" style="font-size:0.85rem; color: var(--text-secondary);"></span>
    <button id="btnNextPageBottom" class="btn-secondary">Siguiente</button>
</div>

<div id="captureModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="captureModalTitle">
    <div class="modal-content">
        <div class="modal-header">
            <div>
                <h2 class="modal-title" id="captureModalTitle">Detalle de captura</h2>
                <p id="captureModalSubtitle" style="margin:0; font-size:0.85rem; color:var(--text-secondary);"></p>
            </div>
            <button class="modal-close" id="captureModalClose">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        <div class="map-wrapper">
            <div id="captureMap"></div>
            <div id="mapFallback" class="map-placeholder" hidden>
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                    <circle cx="12" cy="10" r="3"></circle>
                    <path d="M12 2C8 2 4 5 4 9c0 5 8 13 8 13s8-8 8-13c0-4-4-7-8-7z"></path>
                </svg>
                <span>Esta captura no trae geometría válida.</span>
            </div>
        </div>

        <div class="info-grid">
            <div class="info-card">
                <h4>Finca</h4>
                <strong id="infoFincaNombre">—</strong>
                <span>Código: <span id="infoFincaCodigo">—</span></span>
                <span>Estado actual: <span id="infoFincaEstado">—</span></span>
            </div>
            <div class="info-card">
                <h4>Captura</h4>
                <strong id="infoEstado">—</strong>
                <span>Capturado: <span id="infoFechaCaptura">—</span></span>
                <span>Procesado: <span id="infoFechaProcesado">—</span></span>
            </div>
            <div class="info-card">
                <h4>Área y validaciones</h4>
                <strong id="infoArea">—</strong>
                <span>Tipo: <span id="infoTipoGeom">—</span></span>
                <span>Perímetro: <span id="infoPerimetro">—</span></span>
            </div>
            <div class="info-card">
                <h4>Capturista</h4>
                <strong id="infoCapturistaNombre">—</strong>
                <span id="infoCapturistaEmail">—</span>
                <span>Última actividad: <span id="infoCapturistaActividad">—</span></span>
            </div>
        </div>

        <div>
            <label for="detailComentario" style="display:block; font-weight:600; font-size:0.85rem; margin-bottom:0.35rem;">Comentarios / observaciones</label>
            <textarea id="detailComentario" class="comment-box" placeholder="Notas internas o justificación cuando se aprueba/rechaza"></textarea>
        </div>

        <div class="modal-actions">
            <button type="button" class="btn-secondary" id="detailVerHistorial">Ver historial de la finca</button>
            <div style="display:flex; gap:0.5rem;">
                <button type="button" class="btn-danger" id="detailRejectBtn">Rechazar</button>
                <button type="button" class="btn-primary" id="detailApproveBtn">Aprobar</button>
            </div>
        </div>
    </div>
</div>

<div id="historyModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="historyModalTitle">
    <div class="modal-content" style="max-width:680px;">
        <div class="modal-header">
            <div>
                <h2 class="modal-title" id="historyModalTitle">Historial de geometrías</h2>
                <p style="color: var(--text-secondary); font-size:0.85rem;" id="historySubtitle">—</p>
            </div>
            <button class="modal-close" id="historyModalClose">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <div id="historyContent" style="display:flex; flex-direction:column; gap:0.75rem;">
            <div class="empty-state">
                <strong>Sin historial cargado</strong>
                Selecciona una capturación para ver versiones previas.
            </div>
        </div>
    </div>
</div>
<?php
$moduleContent = ob_get_clean();

ob_start();
?>
<script type="module" src="./fincas-geom.js"></script>
<?php
$moduleScripts = ob_get_clean();

require_once '../_layout.php';
