import { ApiClient } from '../../js/ApiClient.js';

const apiClient = window.apiClient instanceof ApiClient ? window.apiClient : new ApiClient();

const state = {
    page: 1,
    pageSize: 10,
    totalPages: 1,
    search: '',
    categoria: '',
    activo: '',
    loading: false,
    mode: 'create',
    editingCodigo: null
};

const dom = {
    tableBody: document.getElementById('indicesTableBody'),
    paginationInfo: document.getElementById('paginationInfo'),
    btnPrev: document.getElementById('btnPrevPage'),
    btnNext: document.getElementById('btnNextPage'),
    filterSearch: document.getElementById('filterSearch'),
    filterCategoria: document.getElementById('filterCategoria'),
    filterActivo: document.getElementById('filterActivo'),
    btnClearFilters: document.getElementById('btnClearFilters'),
    btnNewIndex: document.getElementById('btnNewIndex'),
    modal: document.getElementById('indexModal'),
    modalTitle: document.getElementById('modalTitle'),
    modalClose: document.getElementById('modalClose'),
    modalSubmit: document.getElementById('modalSubmit'),
    form: document.getElementById('indexForm'),
    fields: {
        codigo: document.getElementById('codigo'),
        nombre: document.getElementById('nombre'),
        categoria: document.getElementById('categoria'),
        descripcion: document.getElementById('descripcion'),
        formula: document.getElementById('formula'),
        unidad: document.getElementById('unidad'),
        valor_min: document.getElementById('valor_min'),
        valor_max: document.getElementById('valor_max'),
        interpretacion_bueno: document.getElementById('interpretacion_bueno'),
        interpretacion_malo: document.getElementById('interpretacion_malo'),
        color_escala: document.getElementById('color_escala'),
        activo: document.getElementById('activo')
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
                <td colspan="9">
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
    return Number(value).toLocaleString('es-HN', {
        maximumFractionDigits: 4
    });
}

function formatDate(value) {
    if (!value) return '—';
    try {
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return '—';
        return date.toLocaleString('es-HN', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    } catch (_) {
        return value;
    }
}

function formatColorScale(colorScale) {
    if (!Array.isArray(colorScale) || colorScale.length === 0) {
        return '<span style="color:var(--text-tertiary);">Sin definir</span>';
    }
    const stops = colorScale.slice(0, 3).map((stop) => {
        const color = stop.color || '#999999';
        const valor = stop.valor ?? stop.value ?? '';
        return `<span style="display:inline-flex;align-items:center;gap:0.35rem;padding:0.2rem 0.5rem;border-radius:999px;background:${color}1A;color:var(--text-primary);">
                    <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${color};"></span>
                    ${valor !== '' ? formatNumber(valor) : '—'}
                </span>`;
    }).join(' ');
    if (colorScale.length > 3) {
        return stops + ' <span style="color:var(--text-tertiary);font-size:0.75rem;">+' + (colorScale.length - 3) + '</span>';
    }
    return stops;
}

function renderTable(indices) {
    if (!Array.isArray(indices) || indices.length === 0) {
        dom.tableBody.innerHTML = `
            <tr>
                <td colspan="9" class="empty-state">
                    <strong>No encontramos índices con esos filtros</strong>
                    Ajusta la búsqueda o crea uno nuevo.
                </td>
            </tr>
        `;
        return;
    }

    const rows = indices.map((item) => {
        const categoria = item.categoria || '—';
        const unidad = item.unidad || '—';
        const rango = item.valor_min !== null || item.valor_max !== null
            ? `<span class="range-chip">${formatNumber(item.valor_min, '—')} → ${formatNumber(item.valor_max, '—')}</span>`
            : '<span style="color:var(--text-tertiary);">Sin rango</span>';
        const estado = item.activo
            ? '<span class="badge" style="background:rgba(109,190,69,0.18);color:var(--accent-primary);">Activo</span>'
            : '<span class="badge" style="background:rgba(229,231,235,0.6);color:var(--text-secondary);">Inactivo</span>';
        const interpretacion = item.interpretacion_bueno
            ? `<div style="display:flex;flex-direction:column;gap:0.2rem;">
                    <span style="color:var(--accent-primary);font-weight:500;">${item.interpretacion_bueno}</span>
                    ${item.interpretacion_malo ? `<span style="color:var(--text-secondary);font-size:0.8rem;">${item.interpretacion_malo}</span>` : ''}
               </div>`
            : (item.interpretacion_malo || '—');

        return `
            <tr data-code="${item.codigo}" data-active="${item.activo ? '1' : '0'}">
                <td><strong>${item.codigo}</strong></td>
                <td>
                    <div style="display:flex;flex-direction:column;gap:0.15rem;">
                        <span style="font-weight:600;">${item.nombre}</span>
                        ${item.descripcion ? `<span style="font-size:0.8rem;color:var(--text-secondary);">${item.descripcion}</span>` : ''}
                    </div>
                </td>
                <td>${categoria}</td>
                <td>${unidad}</td>
                <td>${rango}</td>
                <td>${interpretacion}</td>
                <td>${estado}</td>
                <td>${formatColorScale(item.color_escala)}</td>
                <td>
                    <div class="actions-cell">
                        <button class="btn-icon" data-action="edit" title="Editar índice" aria-label="Editar índice">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 20h9"></path>
                                <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"></path>
                            </svg>
                        </button>
                        <button class="btn-icon" data-action="toggle" data-active="${item.activo ? '1' : '0'}" title="${item.activo ? 'Inactivar índice' : 'Activar índice'}" aria-label="${item.activo ? 'Inactivar índice' : 'Activar índice'}">
                            ${item.activo
                                ? `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M12 2v10"></path>
                                        <path d="M5.5 8.5a7 7 0 1 0 13 0"></path>
                                        <path d="M19 5L5 19"></path>
                                    </svg>`
                                : `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M12 2v10"></path>
                                        <path d="M5.5 8.5a7 7 0 1 0 13 0"></path>
                                    </svg>`}
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');

    dom.tableBody.innerHTML = rows;
}

async function loadIndices(force = false) {
    if (state.loading && !force) return;
    setLoading(true);
    try {
        const params = new URLSearchParams({
            page: state.page.toString(),
            page_size: state.pageSize.toString()
        });
        if (state.search) params.append('q', state.search);
        if (state.categoria) params.append('categoria', state.categoria);
        if (state.activo !== '') params.append('activo', state.activo);

        const response = await apiClient.get(`indices-list.php?${params.toString()}`);
        if (!response.success) {
            throw new Error(response.error || 'No se pudo cargar el listado');
        }

        const { pagination, data } = response;
        state.totalPages = pagination?.total_pages ? Number(pagination.total_pages) : 1;
        state.page = pagination?.page ? Number(pagination.page) : 1;

        renderTable(data);
        updatePaginationSummary(pagination);
    } catch (error) {
        console.error('Error al cargar índices', error);
        dom.tableBody.innerHTML = `
            <tr>
                <td colspan="9" class="empty-state">
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

function openModal(focusField = dom.fields.codigo) {
    dom.modal.classList.add('show');
    document.body.style.overflow = 'hidden';
    if (focusField && typeof focusField.focus === 'function') {
        setTimeout(() => focusField.focus(), 50);
    }
}

function closeModal() {
    dom.modal.classList.remove('show');
    document.body.style.overflow = '';
    state.mode = 'create';
    state.editingCodigo = null;
    dom.modalTitle.textContent = 'Nuevo índice';
    dom.modalSubmit.textContent = 'Guardar índice';
    dom.modalSubmit.disabled = false;
    dom.form.reset();
    dom.fields.activo.checked = true;
    dom.fields.codigo.disabled = false;
}

function formatJsonTextarea(value) {
    if (value === null || value === undefined) return '';
    if (typeof value === 'string') {
        const trimmed = value.trim();
        if (!trimmed || trimmed === '[]' || trimmed === '{}') return '';
        try {
            const parsed = JSON.parse(trimmed);
            if (Array.isArray(parsed) && parsed.length === 0) return '';
            return JSON.stringify(parsed, null, 2);
        } catch (_) {
            return trimmed;
        }
    }
    if (Array.isArray(value)) {
        if (value.length === 0) return '';
        return JSON.stringify(value, null, 2);
    }
    if (typeof value === 'object') {
        if (Object.keys(value).length === 0) return '';
        return JSON.stringify(value, null, 2);
    }
    return '';
}

function fillFormFromData(data) {
    dom.fields.codigo.value = data.codigo ?? '';
    dom.fields.nombre.value = data.nombre ?? '';
    dom.fields.categoria.value = data.categoria ?? '';
    dom.fields.descripcion.value = data.descripcion ?? '';
    dom.fields.formula.value = data.formula ?? '';
    dom.fields.unidad.value = data.unidad ?? '';
    dom.fields.valor_min.value = data.valor_min ?? '';
    dom.fields.valor_max.value = data.valor_max ?? '';
    dom.fields.interpretacion_bueno.value = data.interpretacion_bueno ?? '';
    dom.fields.interpretacion_malo.value = data.interpretacion_malo ?? '';
    dom.fields.color_escala.value = formatJsonTextarea(data.color_escala ?? null);
    dom.fields.activo.checked = data.activo !== false;
}

function parseJsonArray(value, fieldName) {
    if (!value || value.trim() === '') return null;
    try {
        const parsed = JSON.parse(value);
        if (!Array.isArray(parsed)) {
            throw new Error('Debe ser un arreglo JSON');
        }
        return parsed;
    } catch (error) {
        throw new Error(`Campo ${fieldName}: JSON inválido (${error.message})`);
    }
}

async function handleFormSubmit(event) {
    event.preventDefault();
    dom.modalSubmit.disabled = true;

    try {
        const payload = {
            codigo: dom.fields.codigo.value.trim().toUpperCase(),
            nombre: dom.fields.nombre.value.trim(),
            categoria: dom.fields.categoria.value.trim(),
            descripcion: dom.fields.descripcion.value.trim(),
            formula: dom.fields.formula.value.trim(),
            unidad: dom.fields.unidad.value.trim(),
            valor_min: dom.fields.valor_min.value !== '' ? Number(dom.fields.valor_min.value) : null,
            valor_max: dom.fields.valor_max.value !== '' ? Number(dom.fields.valor_max.value) : null,
            interpretacion_bueno: dom.fields.interpretacion_bueno.value.trim(),
            interpretacion_malo: dom.fields.interpretacion_malo.value.trim(),
            activo: dom.fields.activo.checked
        };

        if (!payload.codigo) {
            throw new Error('El código es obligatorio');
        }
        if (!payload.nombre) {
            throw new Error('El nombre es obligatorio');
        }

        if (dom.fields.color_escala.value.trim() !== '') {
            payload.color_escala = parseJsonArray(dom.fields.color_escala.value, 'color_escala');
        }

        const isEdit = state.mode === 'edit' && state.editingCodigo !== null;
        let response;

        if (isEdit) {
            response = await apiClient.put('indices-update.php', payload);
        } else {
            response = await apiClient.post('indices-create.php', payload);
        }

        if (!response.success) {
            throw new Error(response.error || (isEdit ? 'No se pudo actualizar el índice' : 'No se pudo crear el índice'));
        }

        const previousCode = state.editingCodigo;
        closeModal();
        await loadIndices(true);

        const targetCode = response.data?.codigo ?? previousCode;
        const targetRow = targetCode ? dom.tableBody.querySelector(`tr[data-code="${targetCode}"]`) : null;
        animation.flashRow(targetRow);
        showToast(isEdit ? 'Índice actualizado correctamente' : 'Índice creado correctamente');
    } catch (error) {
        console.error('Error al guardar índice', error);
        showToast(error.message || 'No se pudo guardar el índice', true);
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

function startCreateIndex() {
    state.mode = 'create';
    state.editingCodigo = null;
    dom.modalTitle.textContent = 'Nuevo índice';
    dom.modalSubmit.textContent = 'Guardar índice';
    dom.modalSubmit.disabled = false;
    dom.form.reset();
    dom.fields.activo.checked = true;
    dom.fields.codigo.disabled = false;
    openModal(dom.fields.codigo);
}

async function startEditIndex(codigo) {
    try {
        state.mode = 'edit';
        state.editingCodigo = codigo;
        dom.modalTitle.textContent = 'Editar índice';
        dom.modalSubmit.textContent = 'Guardar cambios';
        dom.modalSubmit.disabled = true;

        const response = await apiClient.get(`indices-detail.php?codigo=${encodeURIComponent(codigo)}`);
        if (!response.success || !response.data) {
            throw new Error(response.error || 'No se encontraron datos del índice');
        }

        dom.form.reset();
        fillFormFromData(response.data);
        dom.fields.codigo.disabled = true;
        dom.modalSubmit.disabled = false;
        openModal(dom.fields.nombre);
    } catch (error) {
        console.error('Error al cargar índice', error);
        showToast(error.message || 'No se pudo cargar el índice', true);
        state.mode = 'create';
        state.editingCodigo = null;
        dom.fields.codigo.disabled = false;
        dom.modalSubmit.disabled = false;
    }
}

async function toggleIndex(codigo, isActive, button) {
    const nextState = !isActive;
    const confirmation = window.confirm(nextState ? '¿Activar este índice?' : '¿Inactivar este índice?');
    if (!confirmation) return;

    try {
        if (button) button.disabled = true;
        const response = await apiClient.post('indices-toggle.php', {
            codigo,
            activo: nextState
        });

        if (!response.success) {
            throw new Error(response.error || 'No se pudo actualizar el estado');
        }

        await loadIndices(true);
        const row = dom.tableBody.querySelector(`tr[data-code="${codigo}"]`);
        animation.flashRow(row);
        showToast(nextState ? 'Índice activado' : 'Índice inactivado');
    } catch (error) {
        console.error('Error al cambiar estado de índice', error);
        showToast(error.message || 'No se pudo cambiar el estado', true);
    } finally {
        if (button) button.disabled = false;
    }
}

function handleTableClick(event) {
    const button = event.target.closest('button[data-action]');
    if (!button) return;

    event.preventDefault();

    const row = button.closest('tr[data-code]');
    if (!row) return;

    const codigo = row.dataset.code;
    if (!codigo) return;

    const action = button.dataset.action;

    if (action === 'edit') {
        startEditIndex(codigo);
    } else if (action === 'toggle') {
        const isActive = button.dataset.active === '1';
        toggleIndex(codigo, isActive, button);
    }
}

let debounceTimer;
function debounceLoad() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => loadIndices(), 350);
}

function attachListeners() {
    dom.filterSearch.addEventListener('input', (event) => {
        state.search = event.target.value.trim();
        state.page = 1;
        debounceLoad();
    });

    dom.filterCategoria.addEventListener('change', (event) => {
        state.categoria = event.target.value.trim();
        state.page = 1;
        loadIndices();
    });

    dom.filterActivo.addEventListener('change', (event) => {
        state.activo = event.target.value;
        state.page = 1;
        loadIndices();
    });

    dom.btnClearFilters.addEventListener('click', () => {
        dom.filterSearch.value = '';
        dom.filterCategoria.value = '';
        dom.filterActivo.value = '';
        state.search = '';
        state.categoria = '';
        state.activo = '';
        state.page = 1;
        loadIndices();
    });

    dom.btnPrev.addEventListener('click', () => {
        if (state.page > 1 && !state.loading) {
            state.page -= 1;
            loadIndices();
        }
    });

    dom.btnNext.addEventListener('click', () => {
        if (state.page < state.totalPages && !state.loading) {
            state.page += 1;
            loadIndices();
        }
    });

    dom.btnNewIndex.addEventListener('click', () => {
        dom.fields.codigo.disabled = false;
        startCreateIndex();
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

function init() {
    attachListeners();
    loadIndices();
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
