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

$layoutActive = 'indices';
$moduleTitle = 'Índices Satelitales';
$moduleSubtitle = 'Catálogo maestro de indicadores (NDVI, NDMI, etc.) usados por el monitoreo territorial';

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
    table.indices {
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
        background: rgba(77, 161, 217, 0.15);
        color: var(--accent-secondary);
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
    .form-input, .form-textarea, .form-select {
        border: 1px solid var(--border-color);
        border-radius: 10px;
        background: var(--bg-card);
        color: var(--text-primary);
        padding: 0.55rem 0.75rem;
        font-size: 0.92rem;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .form-input:focus, .form-textarea:focus, .form-select:focus {
        border-color: var(--accent-secondary);
        box-shadow: 0 0 0 3px rgba(77,161,217,0.12);
        outline: none;
    }
    .form-textarea {
        min-height: 110px;
        resize: vertical;
    }
    .form-switch {
        display: flex;
        align-items: center;
        gap: 0.75rem;
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
    @media (max-width: 768px) {
        .modal-content {
            border-radius: 18px 18px 0 0;
            padding: 1.25rem;
        }
        .toolbar {
            grid-template-columns: 1fr;
            gap: 0.75rem;
        }
        .pagination {
            justify-content: center;
        }
    }
</style>
<?php
$moduleHead = ob_get_clean();

ob_start();
?>
<div class="toolbar">
    <div class="toolbar-filters" style="width:100%; display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:0.5rem;">
        <input id="filterSearch" class="input" type="search" placeholder="Buscar por código, nombre o descripción">
        <select id="filterCategoria" class="select">
            <option value="">Todas las categorías</option>
            <option value="VEGETACION">Vegetación</option>
            <option value="HUMEDAD">Humedad</option>
            <option value="CLIMA">Clima</option>
            <option value="BIOMASA">Biomasa</option>
        </select>
        <select id="filterActivo" class="select">
            <option value="">Todos</option>
            <option value="1">Solo activos</option>
            <option value="0">Solo inactivos</option>
        </select>
    </div>
    <button id="btnClearFilters" class="btn-secondary">Limpiar</button>
    <button id="btnNewIndex" class="btn-primary">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
        Nuevo índice
    </button>
</div>

<div class="table-card">
    <table class="indices">
        <thead>
            <tr>
                <th style="width:120px;">Código</th>
                <th>Nombre</th>
                <th style="width:140px;">Categoría</th>
                <th style="width:120px;">Unidad</th>
                <th style="width:160px;">Rango</th>
                <th style="width:180px;">Interpretación</th>
                <th style="width:100px;">Estado</th>
                <th style="width:100px;">Color</th>
                <th style="width:80px;">Acciones</th>
            </tr>
        </thead>
        <tbody id="indicesTableBody">
            <tr>
                <td colspan="9" class="empty-state">
                    <strong>Sin datos por el momento</strong>
                    Cargando índices...
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

<div id="indexModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title" id="modalTitle">Nuevo índice</h2>
            <button class="modal-close" id="modalClose">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <form id="indexForm">
            <div class="form-grid">
                <div class="form-grid two-col">
                    <div class="form-group">
                        <label class="form-label" for="codigo">Código *<span class="label-hint" title="Identificador único (ej. NDVI, NDMI). Usa mayúsculas sin espacios." aria-label="Ayuda campo código">?</span></label>
                        <input type="text" id="codigo" class="form-input" maxlength="30" required placeholder="NDVI, NDMI, EVI">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="nombre">Nombre *<span class="label-hint" title="Nombre descriptivo que verá el equipo." aria-label="Ayuda campo nombre">?</span></label>
                        <input type="text" id="nombre" class="form-input" maxlength="200" required placeholder="Normalized Difference Vegetation Index">
                    </div>
                </div>
                <div class="form-grid two-col">
                    <div class="form-group">
                        <label class="form-label" for="categoria">Categoría<span class="label-hint" title="Clasificación general (Vegetación, Humedad, Clima, etc.)." aria-label="Ayuda campo categoría">?</span></label>
                        <input type="text" id="categoria" class="form-input" maxlength="100" placeholder="VEGETACION, HUMEDAD, ...">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="unidad">Unidad<span class="label-hint" title="Unidad esperada del índice (adimensional, mm, °C...)." aria-label="Ayuda campo unidad">?</span></label>
                        <input type="text" id="unidad" class="form-input" maxlength="50" placeholder="adimensional, mm, °C">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="descripcion">Descripción<span class="label-hint" title="Contexto adicional o uso del índice." aria-label="Ayuda campo descripción">?</span></label>
                    <textarea id="descripcion" class="form-textarea" maxlength="500" placeholder="Describe qué mide el índice y cuándo usarlo."></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label" for="formula">Fórmula / Fuente<span class="label-hint" title="Expresión o referencia técnica para calcular el índice." aria-label="Ayuda campo fórmula">?</span></label>
                    <textarea id="formula" class="form-textarea" maxlength="500" placeholder="(NIR - RED) / (NIR + RED)"></textarea>
                </div>
                <div class="form-grid two-col">
                    <div class="form-group">
                        <label class="form-label" for="valor_min">Valor mínimo<span class="label-hint" title="Límite inferior recomendado (numérico)." aria-label="Ayuda campo valor mínimo">?</span></label>
                        <input type="number" id="valor_min" class="form-input" step="0.0001" placeholder="Ej. -1">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="valor_max">Valor máximo<span class="label-hint" title="Límite superior recomendado (numérico)." aria-label="Ayuda campo valor máximo">?</span></label>
                        <input type="number" id="valor_max" class="form-input" step="0.0001" placeholder="Ej. 1">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="interpretacion_bueno">Interpretación (bueno)<span class="label-hint" title="Texto guía cuando el valor está dentro de lo ideal." aria-label="Ayuda interpretación buena">?</span></label>
                    <textarea id="interpretacion_bueno" class="form-textarea" maxlength="500" placeholder="Ej. NDVI > 0.6 indica vegetación saludable."></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label" for="interpretacion_malo">Interpretación (alerta)<span class="label-hint" title="Texto guía cuando el valor indica riesgo." aria-label="Ayuda interpretación alerta">?</span></label>
                    <textarea id="interpretacion_malo" class="form-textarea" maxlength="500" placeholder="Ej. NDVI < 0.3 indica estrés severo."></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label" for="color_escala">Color escala (JSON opcional)<span class="label-hint" title="Definición visual del heatmap en formato JSON." aria-label="Ayuda campo color escala">?</span></label>
                    <textarea id="color_escala" class="form-textarea" placeholder='[{"valor":0,"color":"#d73027"},{"valor":0.6,"color":"#1a9850"}]'></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Estado inicial<span class="label-hint" title="Define si el índice está disponible desde el inicio." aria-label="Ayuda estado inicial">?</span></label>
                    <div class="form-switch">
                        <input type="checkbox" id="activo" checked>
                        <span style="font-size:0.85rem; color:var(--text-secondary);">Índice activo para ETL y dashboards</span>
                    </div>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-outline" id="formCancel">Cancelar</button>
                <button type="submit" class="btn-modal-confirm" id="modalSubmit">Guardar índice</button>
            </div>
        </form>
    </div>
</div>
<?php
$moduleContent = ob_get_clean();

ob_start();
?>
<script type="module" src="./indices.js"></script>
<?php
$moduleScripts = ob_get_clean();

require_once '../_layout.php';
