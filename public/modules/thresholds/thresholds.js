import { ApiClient } from '../../js/ApiClient.js';

const apiClient = window.apiClient instanceof ApiClient ? window.apiClient : new ApiClient();

const state = {
    page: 1,
    pageSize: 10,
    totalPages: 1,
    search: '',
    indice: '',
    region: '',
    temporada: '',
    nivel: '',
    loading: false,
    mode: 'create',
    editingId: null,
    indices: [],
    regions: []
};

const dom = {
    tableBody: document.getElementById('thresholdsTableBody'),
    paginationInfo: document.getElementById('paginationInfo'),
    btnPrev: document.getElementById('btnPrevPage'),
    btnNext: document.getElementById('btnNextPage'),
    filterSearch: document.getElementById('filterSearch'),
    filterIndice: document.getElementById('filterIndice'),
    filterRegion: document.getElementById('filterRegion'),
    filterTemporada: document.getElementById('filterTemporada'),
    filterNivel: document.getElementById('filterNivel'),
    btnClearFilters: document.getElementById('btnClearFilters'),
    btnNewThreshold: document.getElementById('btnNewThreshold'),
    modal: document.getElementById('thresholdModal'),
    modalTitle: document.getElementById('modalTitle'),
    modalClose: document.getElementById('modalClose'),
    modalSubmit: document.getElementById('modalSubmit'),
    form: document.getElementById('thresholdForm'),
    fields: {
        codigo_indice: document.getElementById('codigo_indice'),
        id_region: document.getElementById('id_region'),
        temporada: document.getElementById('temporada'),
        fecha_inicio: document.getElementById('fecha_inicio'),
        fecha_fin: document.getElementById('fecha_fin'),
        valor_min: document.getElementById('valor_min'),
        valor_max: document.getElementById('valor_max'),
        nivel_alerta: document.getElementById('nivel_alerta'),
        tipo_alerta: document.getElementById('tipo_alerta'),
        descripcion: document.getElementById('descripcion'),
        recomendacion_accion: document.getElementById('recomendacion_accion'),
        metadata: document.getElementById('metadata')
    }
};

const animation = {
    flashRow(row) {
        if (!row) return;
        row.classList.add('row-flash');
        setTimeout(() => row.classList.remove('row-flash'), 900);
    },
    shimmerRow() {
        return `
            <tr class="skeleton-row">
                <td colspan="8">
                    <div class="skeleton-line" style="width: 95%;"></div>
                </td>
            </tr>
        `;
    }
};

function setLoading(isLoading) {
    state.loading = isLoading;
    dom.tableBody.classList.toggle('loading', isLoading);
    dom.btnPrev.disabled = isLoading || state.page <= 1;
    dom.btnNext.disabled = isLoading || state.page >= state.totalPages;

    if (isLoading) {
        dom.tableBody.innerHTML = `
            ${animation.shimmerRow()}
            ${animation.shimmerRow()}
            ${animation.shimmerRow()}
        `;
    }
}

function formatNumber(value, fallback = '—') {
    if (value === null || value === undefined || value === '') return fallback;
    return Number(value).toLocaleString('es-HN', { maximumFractionDigits: 4 });
}

function formatDate(value) {
    if (!value) return '—';
    try {
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return '—';
        return date.toLocaleDateString('es-HN', { month: '2-digit', day: '2-digit' });
    } catch (_) {
        return value;
    }
}

function levelBadge(level) {
    const cls = `level-badge ${level.toLowerCase()}`;
    return `<span class="${cls}">${level}</span>`;
}

function renderTable(rows) {
    if (!Array.isArray(rows) || rows.length === 0) {
        dom.tableBody.innerHTML = `
            <tr>
                <td colspan="8" class="empty-state">
                    <strong>No encontramos umbrales con esos filtros</strong>
                    Ajusta la búsqueda o crea uno nuevo.
                </td>
            </tr>
        `;
        return;
    }

    dom.tableBody.innerHTML = rows.map((row) => {
        const rango = (row.valor_min !== null || row.valor_max !== null)
            ? `<span class="range-chip">${formatNumber(row.valor_min,'—')} → ${formatNumber(row.valor_max,'—')}</span>`
            : '<span style="color:var(--text-tertiary);">Sin definir</span>';

        return `
            <tr data-id="${row.id_umbral}">
                <td>
                    <div style="display:flex;flex-direction:column;gap:0.15rem;">
                        <span style="font-weight:600;">${row.codigo_indice}</span>
                        ${row.indice_nombre ? `<span style="font-size:0.78rem;color:var(--text-secondary);">${row.indice_nombre}</span>` : ''}
                    </div>
                </td>
                <td>
                    ${row.region_codigo ? `<div style="display:flex;flex-direction:column;gap:0.15rem;">
                        <span style="font-weight:600;">${row.region_nombre || row.region_codigo}</span>
                        <span style="font-size:0.78rem;color:var(--text-secondary);">${row.region_codigo}${row.pais_codigo_iso ? ' · ' + row.pais_codigo_iso : ''}</span>
                    </div>` : '<span style="color:var(--text-tertiary);">Global</span>'}
                </td>
                <td>${row.temporada || '—'}</td>
                <td>${rango}</td>
                <td>${levelBadge(row.nivel_alerta)}</td>
                <td>${row.descripcion || '—'}</td>
                <td>${row.recomendacion_accion || '—'}</td>
                <td>
                    <div class="actions-cell">
                        <button class="btn-icon" data-action="edit" title="Editar umbral" aria-label="Editar umbral">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 20h9"></path>
                                <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"></path>
                            </svg>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

async function loadThresholds(force = false) {
    if (state.loading && !force) return;
    setLoading(true);
    try {
        const params = new URLSearchParams({
            page: state.page.toString(),
            page_size: state.pageSize.toString()
        });
        if (state.search) params.append('q', state.search);
        if (state.indice) params.append('indice', state.indice);
        if (state.region) params.append('region', state.region);
        if (state.temporada) params.append('temporada', state.temporada);
        if (state.nivel) params.append('nivel', state.nivel);

        const response = await apiClient.get(`thresholds-list.php?${params.toString()}`);
        if (!response.success) {
            throw new Error(response.error || 'No se pudo cargar el listado');
        }

        const { pagination, data } = response;
        state.totalPages = pagination?.total_pages ? Number(pagination.total_pages) : 1;
        state.page = pagination?.page ? Number(pagination.page) : 1;

        renderTable(data);
        updatePaginationSummary(pagination);
    } catch (error) {
        console.error('Error al cargar umbrales', error);
        dom.tableBody.innerHTML = `
            <tr>
                <td colspan="8" class="empty-state">
                    <strong>Ups, algo salió mal</strong>
                    ${error.message || 'No pudimos conectar con la API.'}
                </td>
            </tr>
        `;
    } finally {
        setLoading(false);
    }
}

function updatePaginationSummary(pagination) {
    if (!pagination) {
        dom.paginationInfo.textContent = '';
        dom.btnPrev.disabled = true;
        dom.btnNext.disabled = true;
        return;
    }
    const total = Number(pagination.total ?? 0);
    const from = Math.min(((state.page - 1) * state.pageSize) + 1, total);
    const to = Math.min(state.page * state.pageSize, total);
    dom.paginationInfo.textContent = total > 0 ? `${from} - ${to} de ${total}` : '0 resultados';
    dom.btnPrev.disabled = state.page <= 1;
    dom.btnNext.disabled = state.page >= state.totalPages;
}

function openModal() {
    dom.modal.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    dom.modal.classList.remove('show');
    document.body.style.overflow = '';
    state.mode = 'create';
    state.editingId = null;
    dom.modalTitle.textContent = 'Nuevo umbral';
    dom.modalSubmit.textContent = 'Guardar umbral';
    dom.modalSubmit.disabled = false;
    dom.form.reset();
    dom.fields.codigo_indice.disabled = false;
}

function populateSelect(select, items, textKey, valueKey, includeBlank = true, blankLabel = '(Opcional)') {
    select.innerHTML = '';
    if (includeBlank) {
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = blankLabel;
        select.appendChild(opt);
    }
    items.forEach((item) => {
        const opt = document.createElement('option');
        opt.value = item[valueKey];
        opt.textContent = item[textKey];
        select.appendChild(opt);
    });
}

async function loadCombos() {
    try {
        const [indicesResp, regionsResp] = await Promise.all([
            apiClient.get('indices-list.php?page_size=200'),
            apiClient.get('regions-list.php?page_size=200')
        ]);

        if (indicesResp.success) {
            state.indices = (indicesResp.data || []).map((idx) => ({
                codigo: idx.codigo,
                nombre: idx.nombre || idx.codigo
            }));
        }

        if (regionsResp.success) {
            state.regions = (regionsResp.data || []).map((reg) => ({
                id_region: reg.id_region,
                codigo: reg.region_codigo || reg.codigo,
                nombre: reg.nombre || reg.region_nombre || reg.codigo
            }));
        }

        populateSelect(dom.filterIndice, [{ codigo: '', nombre: 'Todos los índices' }, ...state.indices], 'nombre', 'codigo', false);
        populateSelect(dom.filterRegion, [{ codigo: '', nombre: 'Todas las regiones' }, ...state.regions.map((reg) => ({ codigo: reg.codigo, nombre: reg.nombre }))], 'nombre', 'codigo', false);
        populateSelect(dom.fields.codigo_indice, state.indices, 'nombre', 'codigo', false);
        populateSelect(dom.fields.id_region, [{ id_region: '', nombre: '(Opcional)' }, ...state.regions.map((reg) => ({ id_region: reg.id_region, nombre: `${reg.nombre} (${reg.codigo})` }))], 'nombre', 'id_region', false);
    } catch (error) {
        console.warn('No se pudieron cargar combos de índices/regiones', error);
    }
}

function formatJsonTextarea(value) {
    if (value === null || value === undefined) return '';
    if (typeof value === 'string') {
        const trimmed = value.trim();
        if (!trimmed || trimmed === '{}' || trimmed === '[]') return '';
        try {
            const parsed = JSON.parse(trimmed);
            if (parsed && typeof parsed === 'object' && (Array.isArray(parsed) ? parsed.length : Object.keys(parsed).length)) {
                return JSON.stringify(parsed, null, 2);
            }
        } catch (_) {
            return trimmed;
        }
        return '';
    }
    if (typeof value === 'object') {
        if (Array.isArray(value) && !value.length) return '';
        if (!Array.isArray(value) && Object.keys(value).length === 0) return '';
        return JSON.stringify(value, null, 2);
    }
    return '';
}

function fillForm(data) {
    dom.fields.codigo_indice.value = data.codigo_indice || '';
    dom.fields.id_region.value = data.id_region || '';
    dom.fields.temporada.value = data.temporada || '';
    dom.fields.fecha_inicio.value = data.fecha_inicio || '';
    dom.fields.fecha_fin.value = data.fecha_fin || '';
    dom.fields.valor_min.value = data.valor_min ?? '';
    dom.fields.valor_max.value = data.valor_max ?? '';
    dom.fields.nivel_alerta.value = data.nivel_alerta || 'INFO';
    dom.fields.tipo_alerta.value = data.tipo_alerta || '';
    dom.fields.descripcion.value = data.descripcion || '';
    dom.fields.recomendacion_accion.value = data.recomendacion_accion || '';
    dom.fields.metadata.value = formatJsonTextarea(data.metadata ?? null);
}

function parseMetadata(value) {
    if (!value || value.trim() === '') return null;
    try {
        const parsed = JSON.parse(value);
        if (typeof parsed !== 'object' || parsed === null || Array.isArray(parsed)) {
            throw new Error('Debe ser un objeto JSON');
        }
        return parsed;
    } catch (error) {
        throw new Error(`Campo metadata: JSON inválido (${error.message})`);
    }
}

async function handleFormSubmit(event) {
    event.preventDefault();
    dom.modalSubmit.disabled = true;

    try {
        const payload = {
            codigo_indice: dom.fields.codigo_indice.value.trim(),
            id_region: dom.fields.id_region.value.trim() || null,
            temporada: dom.fields.temporada.value.trim() || null,
            fecha_inicio: dom.fields.fecha_inicio.value || null,
            fecha_fin: dom.fields.fecha_fin.value || null,
            valor_min: dom.fields.valor_min.value !== '' ? Number(dom.fields.valor_min.value) : null,
            valor_max: dom.fields.valor_max.value !== '' ? Number(dom.fields.valor_max.value) : null,
            nivel_alerta: dom.fields.nivel_alerta.value,
            tipo_alerta: dom.fields.tipo_alerta.value.trim() || null,
            descripcion: dom.fields.descripcion.value.trim() || null,
            recomendacion_accion: dom.fields.recomendacion_accion.value.trim() || null
        };

        if (!payload.codigo_indice) throw new Error('El índice es obligatorio');

        if (dom.fields.metadata.value.trim() !== '') {
            payload.metadata = parseMetadata(dom.fields.metadata.value);
        }

        const isEdit = state.mode === 'edit' && state.editingId !== null;
        let response;

        if (isEdit) {
            payload.id_umbral = state.editingId;
            response = await apiClient.put('thresholds-update.php', payload);
        } else {
            response = await apiClient.post('thresholds-create.php', payload);
        }

        if (!response.success) {
            throw new Error(response.error || (isEdit ? 'No se pudo actualizar el umbral' : 'No se pudo crear el umbral'));
        }

        const prevId = state.editingId;
        closeModal();
        await loadThresholds(true);

        const targetId = response.data?.id_umbral ?? prevId;
        const row = targetId ? dom.tableBody.querySelector(`tr[data-id="${targetId}"]`) : null;
        animation.flashRow(row);
        showToast(isEdit ? 'Umbral actualizado correctamente' : 'Umbral creado correctamente');
    } catch (error) {
        console.error('Error al guardar umbral', error);
        showToast(error.message || 'No se pudo guardar el umbral', true);
        dom.modalSubmit.disabled = false;
    }
}

function showToast(message, isError = false) {
    const toast = document.createElement('div');
    toast.className = 'toast-message';
    toast.textContent = message;
    toast.style.position = 'fixed';
    toast.style.bottom = '24px';
    toast.style.right = '24px';
    toast.style.padding = '0.75rem 1.2rem';
    toast.style.borderRadius = '12px';
    toast.style.background = isError ? 'rgba(220, 38, 38, 0.9)' : 'rgba(109, 190, 69, 0.92)';
    toast.style.color = '#fff';
    toast.style.fontWeight = '600';
    toast.style.boxShadow = '0 12px 24px rgba(0,0,0,0.15)';
    toast.style.zIndex = '10050';
    toast.style.transition = 'transform 0.3s ease, opacity 0.3s ease';
    toast.style.transform = 'translateY(20px)';
    toast.style.opacity = '0';
    document.body.appendChild(toast);

    requestAnimationFrame(() => {
        toast.style.transform = 'translateY(0)';
        toast.style.opacity = '1';
    });

    setTimeout(() => {
        toast.style.transform = 'translateY(20px)';
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 2600);
}

function startCreate() {
    state.mode = 'create';
    state.editingId = null;
    dom.modalTitle.textContent = 'Nuevo umbral';
    dom.modalSubmit.textContent = 'Guardar umbral';
    dom.modalSubmit.disabled = false;
    dom.form.reset();
    dom.fields.codigo_indice.disabled = false;
    openModal();
}

async function startEdit(id) {
    try {
        state.mode = 'edit';
        state.editingId = id;
        dom.modalTitle.textContent = 'Editar umbral';
        dom.modalSubmit.textContent = 'Guardar cambios';
        dom.modalSubmit.disabled = true;

        const response = await apiClient.get(`thresholds-detail.php?id=${encodeURIComponent(id)}`);
        if (!response.success || !response.data) {
            throw new Error(response.error || 'No se encontraron datos del umbral');
        }

        dom.form.reset();
        fillForm(response.data);
        dom.fields.codigo_indice.disabled = true;
        dom.modalSubmit.disabled = false;
        openModal();
    } catch (error) {
        console.error('Error al cargar umbral', error);
        showToast(error.message || 'No se pudo cargar el umbral', true);
        state.mode = 'create';
        state.editingId = null;
        dom.fields.codigo_indice.disabled = false;
        dom.modalSubmit.disabled = false;
    }
}

function handleTableClick(event) {
    const button = event.target.closest('button[data-action]');
    if (!button) return;

    event.preventDefault();

    const row = button.closest('tr[data-id]');
    if (!row) return;

    const id = row.dataset.id;
    const action = button.dataset.action;

    if (action === 'edit') {
        startEdit(id);
    }
}

let debounceTimer;
function debounceLoad() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => loadThresholds(), 350);
}

function attachListeners() {
    dom.filterSearch.addEventListener('input', (event) => {
        state.search = event.target.value.trim();
        state.page = 1;
        debounceLoad();
    });

    dom.filterIndice.addEventListener('change', (event) => {
        state.indice = event.target.value;
        state.page = 1;
        loadThresholds();
    });

    dom.filterRegion.addEventListener('change', (event) => {
        state.region = event.target.value;
        state.page = 1;
        loadThresholds();
    });

    dom.filterTemporada.addEventListener('change', (event) => {
        state.temporada = event.target.value;
        state.page = 1;
        loadThresholds();
    });

    dom.filterNivel.addEventListener('change', (event) => {
        state.nivel = event.target.value;
        state.page = 1;
        loadThresholds();
    });

    dom.btnClearFilters.addEventListener('click', () => {
        dom.filterSearch.value = '';
        dom.filterIndice.value = '';
        dom.filterRegion.value = '';
        dom.filterTemporada.value = '';
        dom.filterNivel.value = '';
        state.search = '';
        state.indice = '';
        state.region = '';
        state.temporada = '';
        state.nivel = '';
        state.page = 1;
        loadThresholds();
    });

    dom.btnPrev.addEventListener('click', () => {
        if (state.page > 1 && !state.loading) {
            state.page -= 1;
            loadThresholds();
        }
    });

    dom.btnNext.addEventListener('click', () => {
        if (state.page < state.totalPages && !state.loading) {
            state.page += 1;
            loadThresholds();
        }
    });

    dom.btnNewThreshold.addEventListener('click', () => {
        startCreate();
    });

    dom.modalClose.addEventListener('click', closeModal);
    dom.form.addEventListener('submit', handleFormSubmit);
    dom.tableBody.addEventListener('click', handleTableClick);
    document.getElementById('formCancel').addEventListener('click', closeModal);
    dom.modal.addEventListener('click', (event) => {
        if (event.target === dom.modal) {
            closeModal();
        }
    });
}

async function init() {
    attachListeners();
    await loadCombos();
    loadThresholds();
}

document.addEventListener('DOMContentLoaded', init);

const style = document.createElement('style');
style.textContent = `
    .row-flash {
        animation: rowFlash 0.8s ease;
    }
    @keyframes rowFlash {
        from { background-color: rgba(109, 190, 69, 0.25); }
        to { background-color: transparent; }
    }
    .skeleton-row .skeleton-line {
        height: 12px;
        border-radius: 6px;
        background: linear-gradient(90deg, rgba(229,231,235,0.4), rgba(200,200,200,0.4), rgba(229,231,235,0.4));
        background-size: 200% 100%;
        animation: shimmer 1.4s ease infinite;
    }
    @keyframes shimmer {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }
`;
document.head.appendChild(style);
