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

$layoutActive = 'thresholds';
$moduleTitle = 'Umbrales por región e índice';
$moduleSubtitle = 'Configura niveles de alerta específicos para cada índice según región y temporada';

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
    table.thresholds {
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
    .range-chip {
        display: inline-flex;
        padding: 0.25rem 0.6rem;
        border-radius: 999px;
        background: rgba(109,190,69,0.15);
        color: var(--accent-primary);
        font-size: 0.75rem;
        font-weight: 600;
    }
    .level-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.6rem;
        border-radius: 8px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .level-badge.alto { background: rgba(252,165,165,0.25); color: #dc2626; }
    .level-badge.moderado { background: rgba(251,191,36,0.25); color: #d97706; }
    .level-badge.bajo { background: rgba(125,211,252,0.25); color: #0ea5e9; }
    .level-badge.info { background: rgba(209,213,219,0.4); color: var(--text-secondary); }
    .level-badge.critico { background: rgba(239,68,68,0.3); color: #b91c1c; }
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
        width: min(820px, 100%);
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
    .form-input, .form-select, .form-textarea {
        border: 1px solid var(--border-color);
        border-radius: 10px;
        background: var(--bg-card);
        color: var(--text-primary);
        padding: 0.55rem 0.75rem;
        font-size: 0.92rem;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .form-input:focus, .form-select:focus, .form-textarea:focus {
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
        <input id="filterSearch" class="input" type="search" placeholder="Buscar por índice, región o descripción">
        <select id="filterIndice" class="select">
            <option value="">Todos los índices</option>
        </select>
        <select id="filterRegion" class="select">
            <option value="">Todas las regiones</option>
        </select>
        <select id="filterTemporada" class="select">
            <option value="">Todas las temporadas</option>
            <option value="SEQUIA">Sequía</option>
            <option value="LLUVIAS">Lluvias</option>
            <option value="VERANO">Verano</option>
            <option value="INVIERNO">Invierno</option>
        </select>
        <select id="filterNivel" class="select">
            <option value="">Todos los niveles</option>
            <option value="INFO">Info</option>
            <option value="BAJO">Bajo</option>
            <option value="MODERADO">Moderado</option>
            <option value="ALTO">Alto</option>
            <option value="CRITICO">Crítico</option>
        </select>
    </div>
    <button id="btnClearFilters" class="btn-secondary">Limpiar</button>
    <button id="btnNewThreshold" class="btn-primary">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
        Nuevo umbral
    </button>
</div>

<div class="table-card">
    <table class="thresholds">
        <thead>
            <tr>
                <th style="width:160px;">Índice</th>
                <th style="width:180px;">Región</th>
                <th style="width:110px;">Temporada</th>
                <th style="width:140px;">Rango</th>
                <th style="width:120px;">Nivel</th>
                <th>Descripción</th>
                <th style="width:160px;">Recomendación</th>
                <th style="width:80px;">Acciones</th>
            </tr>
        </thead>
        <tbody id="thresholdsTableBody">
            <tr>
                <td colspan="8" class="empty-state">
                    <strong>Sin datos por el momento</strong>
                    Cargando umbrales...
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

<div id="thresholdModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title" id="modalTitle">Nuevo umbral</h2>
            <button class="modal-close" id="modalClose">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <form id="thresholdForm">
            <div class="form-grid">
                <div class="form-grid two-col">
                    <div class="form-group">
                        <label class="form-label" for="codigo_indice">Índice *<span class="label-hint" title="Selecciona el índice al que aplica este umbral." aria-label="Ayuda índice">?</span></label>
                        <select id="codigo_indice" class="form-select" required></select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="id_region">Región<span class="label-hint" title="Opcional: región específica donde aplica." aria-label="Ayuda región">?</span></label>
                        <select id="id_region" class="form-select"></select>
                    </div>
                </div>
                <div class="form-grid two-col">
                    <div class="form-group">
                        <label class="form-label" for="temporada">Temporada<span class="label-hint" title="Clave de temporada (Sequía, Lluvias, etc.)." aria-label="Ayuda temporada">?</span></label>
                        <select id="temporada" class="form-select">
                            <option value="">(Opcional)</option>
                            <option value="SEQUIA">SEQUIA</option>
                            <option value="LLUVIAS">LLUVIAS</option>
                            <option value="VERANO">VERANO</option>
                            <option value="INVIERNO">INVIERNO</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="nivel_alerta">Nivel de alerta *<span class="label-hint" title="Define la severidad (INFO, BAJO, MODERADO, ALTO, CRITICO)." aria-label="Ayuda nivel">?</span></label>
                        <select id="nivel_alerta" class="form-select" required>
                            <option value="INFO">INFO</option>
                            <option value="BAJO">BAJO</option>
                            <option value="MODERADO">MODERADO</option>
                            <option value="ALTO">ALTO</option>
                            <option value="CRITICO">CRITICO</option>
                        </select>
                    </div>
                </div>
                <div class="form-grid two-col">
                    <div class="form-group">
                        <label class="form-label" for="fecha_inicio">Fecha inicio<span class="label-hint" title="Inicio del periodo (MM-DD)." aria-label="Ayuda fecha inicio">?</span></label>
                        <input type="date" id="fecha_inicio" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="fecha_fin">Fecha fin<span class="label-hint" title="Fin del periodo (MM-DD)." aria-label="Ayuda fecha fin">?</span></label>
                        <input type="date" id="fecha_fin" class="form-input">
                    </div>
                </div>
                <div class="form-grid two-col">
                    <div class="form-group">
                        <label class="form-label" for="valor_min">Valor mínimo<span class="label-hint" title="Límite inferior que dispara la alerta." aria-label="Ayuda valor mínimo">?</span></label>
                        <input type="number" id="valor_min" class="form-input" step="0.0001" placeholder="Ej. 0.2">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="valor_max">Valor máximo<span class="label-hint" title="Límite superior que dispara la alerta." aria-label="Ayuda valor máximo">?</span></label>
                        <input type="number" id="valor_max" class="form-input" step="0.0001" placeholder="Ej. 0.4">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="tipo_alerta">Tipo de alerta<span class="label-hint" title="Categoría de alerta (Sequía, Inundación...)." aria-label="Ayuda tipo alerta">?</span></label>
                    <input type="text" id="tipo_alerta" class="form-input" maxlength="100" placeholder="SEQUIA">
                </div>
                <div class="form-group">
                    <label class="form-label" for="descripcion">Descripción<span class="label-hint" title="Mensaje que recibirá el productor." aria-label="Ayuda descripción">?</span></label>
                    <textarea id="descripcion" class="form-textarea" maxlength="500"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label" for="recomendacion_accion">Recomendación<span class="label-hint" title="Acción sugerida cuando se dispara la alerta." aria-label="Ayuda recomendación">?</span></label>
                    <textarea id="recomendacion_accion" class="form-textarea" maxlength="500"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label" for="metadata">Metadata (JSON opcional)<span class="label-hint" title="Datos adicionales (ej. tipo de potrero, observaciones)." aria-label="Ayuda metadata">?</span></label>
                    <textarea id="metadata" class="form-textarea" placeholder='{"fuente":"expertos","umbral_referencia":"FAO"}'></textarea>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-outline" id="formCancel">Cancelar</button>
                <button type="submit" class="btn-modal-confirm" id="modalSubmit">Guardar umbral</button>
            </div>
        </form>
    </div>
</div>
<?php
$moduleContent = ob_get_clean();

ob_start();
?>
<script type="module" src="./thresholds.js"></script>
<?php
$moduleScripts = ob_get_clean();

require_once '../_layout.php';
