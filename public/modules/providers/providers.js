import { ApiClient } from '../../js/ApiClient.js';

const apiClient = window.apiClient instanceof ApiClient ? window.apiClient : new ApiClient();

const state = {
    page: 1,
    pageSize: 10,
    totalPages: 1,
    search: '',
    activo: '',
    loading: false,
    mode: 'create',
    editingId: null
};

const dom = {
    tableBody: document.getElementById('providersTableBody'),
    paginationInfo: document.getElementById('paginationInfo'),
    btnPrev: document.getElementById('btnPrevPage'),
    btnNext: document.getElementById('btnNextPage'),
    filterSearch: document.getElementById('filterSearch'),
    filterActive: document.getElementById('filterActive'),
    btnClearFilters: document.getElementById('btnClearFilters'),
    btnNewProvider: document.getElementById('btnNewProvider'),
    modal: document.getElementById('providerModal'),
    modalTitle: document.getElementById('modalTitle'),
    modalClose: document.getElementById('modalClose'),
    modalCancel: document.getElementById('modalCancel'),
    modalSubmit: document.getElementById('modalSubmit'),
    form: document.getElementById('providerForm'),
    fields: {
        codigo: document.getElementById('codigo'),
        nombre: document.getElementById('nombre'),
        descripcion: document.getElementById('descripcion'),
        url_api: document.getElementById('url_api'),
        requiere_autenticacion: document.getElementById('requiere_autenticacion'),
        api_key_encriptada: document.getElementById('api_key_encriptada'),
        frecuencia_horas: document.getElementById('frecuencia_horas'),
        ventana_temporal_dias: document.getElementById('ventana_temporal_dias'),
        max_nubosidad_pct: document.getElementById('max_nubosidad_pct'),
        contacto: document.getElementById('contacto'),
        metadata: document.getElementById('metadata'),
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
    } catch (err) {
        console.warn('No se pudo formatear fecha', value, err);
        return value;
    }
}

function formatNumber(value, fallback = '—') {
    if (value === null || value === undefined || value === '') return fallback;
    return Number(value).toLocaleString('es-HN', {
        maximumFractionDigits: 2
    });
}

function renderTable(providers) {
    if (!Array.isArray(providers) || providers.length === 0) {
        dom.tableBody.innerHTML = `
            <tr>
                <td colspan="9" class="empty-state">
                    <strong>No encontramos proveedores con esos filtros</strong>
                    Ajusta la búsqueda o crea uno nuevo.
                </td>
            </tr>
        `;
        return;
    }

    const rows = providers.map((item) => {
        const estadoClass = item.activo ? 'status-active' : 'status-inactive';
        const estadoLabel = item.activo ? 'Activo' : 'Inactivo';
        const frecuencia = item.frecuencia_horas ? `${formatNumber(item.frecuencia_horas)} h` : '—';
        const ventana = item.ventana_temporal_dias ? `${formatNumber(item.ventana_temporal_dias)} días` : '—';
        const nubosidad = item.max_nubosidad_pct !== null && item.max_nubosidad_pct !== undefined
            ? `${formatNumber(item.max_nubosidad_pct)} %`
            : '—';
        const url = item.url_api ? `<a href="${item.url_api}" target="_blank" rel="noopener" style="color:var(--accent-secondary);">${item.url_api}</a>` : '—';

        return `
            <tr data-id="${item.id_proveedor}" data-active="${item.activo ? '1' : '0'}">
                <td>
                    <span class="status-pill ${estadoClass}">
                        <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background: currentColor;"></span>
                        ${estadoLabel}
                    </span>
                </td>
                <td>
                    <strong style="letter-spacing:0.03em;">${item.codigo}</strong>
                </td>
                <td>
                    <div style="display:flex; flex-direction:column; gap:0.15rem;">
                        <span style="font-weight:600;">${item.nombre}</span>
                        ${item.descripcion ? `<span style="font-size:0.8rem; color:var(--text-secondary);">${item.descripcion}</span>` : ''}
                    </div>
                </td>
                <td>${frecuencia}</td>
                <td>${ventana}</td>
                <td>${nubosidad}</td>
                <td>${url}</td>
                <td>${formatDate(item.fecha_ultima_consulta)}</td>
                <td>
                    <div class="actions-cell">
                        <button class="btn-icon btn-edit" data-action="edit" title="Editar proveedor" aria-label="Editar proveedor">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 20h9"></path>
                                <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"></path>
                            </svg>
                        </button>
                        <button class="btn-icon btn-toggle" data-action="toggle" data-active="${item.activo ? '1' : '0'}" title="${item.activo ? 'Inactivar proveedor' : 'Activar proveedor'}" aria-label="${item.activo ? 'Inactivar proveedor' : 'Activar proveedor'}">
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

async function loadProviders(force = false) {
    if (state.loading && !force) return;
    setLoading(true);
    try {
        const params = new URLSearchParams({
            page: state.page.toString(),
            page_size: state.pageSize.toString()
        });
        if (state.search) params.append('q', state.search);
        if (state.activo !== '') params.append('activo', state.activo);

        console.log('Cargando proveedores con params:', params.toString());
        const response = await apiClient.get(`providers-list.php?${params.toString()}`);
        if (!response.success) {
            throw new Error(response.error || 'No se pudo cargar la lista');
        }

        const { pagination, data } = response;
        state.totalPages = pagination?.total_pages ? Number(pagination.total_pages) : 1;
        state.page = pagination?.page ? Number(pagination.page) : 1;

        renderTable(data);
        updatePaginationSummary(pagination);
    } catch (error) {
        console.error('Error al cargar proveedores', error);
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
    dom.paginationInfo.textContent = total > 0
        ? `${from} - ${to} de ${total}`
        : '0 resultados';
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
    state.editingId = null;
    dom.modalTitle.textContent = 'Nuevo proveedor';
    dom.modalSubmit.textContent = 'Guardar proveedor';
    dom.form.reset();
    dom.fields.requiere_autenticacion.checked = true;
    dom.fields.activo.checked = true;
    dom.modalSubmit.disabled = false;
}

function parseJsonField(value, fieldName) {
    if (!value || value.trim() === '') return null;
    try {
        const parsed = JSON.parse(value);
        if (typeof parsed !== 'object' || Array.isArray(parsed)) {
            throw new Error('Debe ser un objeto JSON');
        }
        return parsed;
    } catch (error) {
        throw new Error(`Campo ${fieldName}: JSON inválido (${error.message})`);
    }
}

function formatJsonField(value) {
    if (value === null || value === undefined) return '';
    if (Array.isArray(value)) {
        if (value.length === 0) return '';
        return JSON.stringify(value, null, 2);
    }
    if (typeof value === 'object') {
        if (Object.keys(value).length === 0) return '';
        return JSON.stringify(value, null, 2);
    }
    if (typeof value === 'string') {
        const trimmed = value.trim();
        if (trimmed === '' || trimmed === '{}' || trimmed === '[]') return '';
        try {
            const parsed = JSON.parse(trimmed);
            if (typeof parsed === 'object' && parsed !== null) {
                if (Array.isArray(parsed) && parsed.length === 0) return '';
                if (!Array.isArray(parsed) && Object.keys(parsed).length === 0) return '';
                return JSON.stringify(parsed, null, 2);
            }
        } catch (_) {
            return trimmed;
        }
        return trimmed;
    }
    return '';
}

function fillFormFromData(data) {
    dom.fields.codigo.value = data.codigo ?? '';
    dom.fields.nombre.value = data.nombre ?? '';
    dom.fields.descripcion.value = data.descripcion ?? '';
    dom.fields.url_api.value = data.url_api ?? '';
    dom.fields.requiere_autenticacion.checked = data.requiere_autenticacion !== false;
    dom.fields.api_key_encriptada.value = data.api_key_encriptada ?? '';
    dom.fields.frecuencia_horas.value = data.frecuencia_horas ?? '';
    dom.fields.ventana_temporal_dias.value = data.ventana_temporal_dias ?? '';
    dom.fields.max_nubosidad_pct.value = data.max_nubosidad_pct ?? '';
    dom.fields.activo.checked = data.activo !== false;
    dom.fields.contacto.value = formatJsonField(data.contacto ?? null);
    dom.fields.metadata.value = formatJsonField(data.metadata ?? null);
}

async function handleFormSubmit(event) {
    event.preventDefault();
    dom.modalSubmit.disabled = true;

    try {
        const payload = {
            codigo: dom.fields.codigo.value.trim().toUpperCase(),
            nombre: dom.fields.nombre.value.trim(),
            descripcion: dom.fields.descripcion.value.trim(),
            url_api: dom.fields.url_api.value.trim(),
            requiere_autenticacion: dom.fields.requiere_autenticacion.checked,
            api_key_encriptada: dom.fields.api_key_encriptada.value.trim(),
            frecuencia_horas: dom.fields.frecuencia_horas.value ? Number(dom.fields.frecuencia_horas.value) : null,
            ventana_temporal_dias: dom.fields.ventana_temporal_dias.value ? Number(dom.fields.ventana_temporal_dias.value) : null,
            max_nubosidad_pct: dom.fields.max_nubosidad_pct.value ? Number(dom.fields.max_nubosidad_pct.value) : null,
            activo: dom.fields.activo.checked
        };

        if (!payload.codigo) {
            throw new Error('El código es obligatorio');
        }
        if (!payload.nombre) {
            throw new Error('El nombre es obligatorio');
        }

        if (dom.fields.contacto.value.trim() !== '') {
            payload.contacto = parseJsonField(dom.fields.contacto.value, 'contacto');
        }

        if (dom.fields.metadata.value.trim() !== '') {
            payload.metadata = parseJsonField(dom.fields.metadata.value, 'metadata');
        }

        const isEdit = state.mode === 'edit' && state.editingId !== null;
        let response;

        if (isEdit) {
            payload.id_proveedor = state.editingId;
            response = await apiClient.put('providers-update.php', payload);
        } else {
            response = await apiClient.post('providers-create.php', payload);
        }

        if (!response.success) {
            throw new Error(response.error || (isEdit ? 'No se pudo actualizar el proveedor' : 'No se pudo crear el proveedor'));
        }

        const previousEditingId = state.editingId;
        closeModal();
        await loadProviders(true);

        const rowId = response.data?.id_proveedor ?? previousEditingId;
        const targetRow = rowId ? dom.tableBody.querySelector(`tr[data-id="${rowId}"]`) : null;
        animation.flashRow(targetRow);

        showToast(isEdit ? 'Proveedor actualizado correctamente' : 'Proveedor creado correctamente');
    } catch (error) {
        console.error('Error al guardar proveedor', error);
        showToast(error.message || 'No se pudo guardar el proveedor', true);
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

function startCreateProvider() {
    state.mode = 'create';
    state.editingId = null;
    dom.modalTitle.textContent = 'Nuevo proveedor';
    dom.modalSubmit.textContent = 'Guardar proveedor';
    dom.modalSubmit.disabled = false;
    dom.form.reset();
    dom.fields.requiere_autenticacion.checked = true;
    dom.fields.activo.checked = true;
    openModal(dom.fields.codigo);
}

async function startEditProvider(id) {
    try {
        state.mode = 'edit';
        state.editingId = id;
        dom.modalTitle.textContent = 'Editar proveedor';
        dom.modalSubmit.textContent = 'Guardar cambios';
        dom.modalSubmit.disabled = true;

        const response = await apiClient.get(`providers-detail.php?id=${id}`);
        if (!response.success || !response.data) {
            throw new Error(response.error || 'No se encontraron datos del proveedor');
        }

        dom.form.reset();
        fillFormFromData(response.data);
        dom.modalSubmit.disabled = false;
        openModal(dom.fields.nombre);
    } catch (error) {
        console.error('Error al cargar proveedor', error);
        showToast(error.message || 'No se pudo cargar el proveedor', true);
        state.mode = 'create';
        state.editingId = null;
        dom.modalSubmit.disabled = false;
    }
}

async function toggleProvider(id, isActive, button) {
    const nextState = !isActive;
    const confirmation = window.confirm(nextState ? '¿Activar este proveedor?' : '¿Inactivar este proveedor?');
    if (!confirmation) return;

    try {
        if (button) {
            button.disabled = true;
        }
        const response = await apiClient.post('providers-toggle.php', {
            id_proveedor: id,
            activo: nextState
        });

        if (!response.success) {
            throw new Error(response.error || 'No se pudo actualizar el estado');
        }

        await loadProviders(true);
        const row = dom.tableBody.querySelector(`tr[data-id="${id}"]`);
        animation.flashRow(row);
        showToast(nextState ? 'Proveedor activado' : 'Proveedor inactivado');
        if (button && row) {
            button.disabled = false;
            const refreshedButton = row.querySelector('button[data-action="toggle"]');
            if (refreshedButton) {
                refreshedButton.dataset.active = nextState ? '1' : '0';
            }
        }
    } catch (error) {
        console.error('Error al cambiar estado de proveedor', error);
        showToast(error.message || 'No se pudo cambiar el estado', true);
        if (button) {
            button.disabled = false;
        }
    }
}

function handleTableClick(event) {
    const button = event.target.closest('button[data-action]');
    if (!button) return;

    event.preventDefault();

    const row = button.closest('tr[data-id]');
    if (!row) return;

    const id = Number(row.dataset.id);
    if (!id) return;

    const action = button.dataset.action;

    if (action === 'edit') {
        startEditProvider(id);
    } else if (action === 'toggle') {
        const isActive = button.dataset.active === '1';
        toggleProvider(id, isActive, button);
    }
}

function attachListeners() {
    dom.filterSearch.addEventListener('input', (event) => {
        state.search = event.target.value.trim();
        state.page = 1;
        debounceLoad();
    });

    dom.filterActive.addEventListener('change', (event) => {
        state.activo = event.target.value;
        state.page = 1;
        loadProviders();
    });

    dom.btnClearFilters.addEventListener('click', () => {
        dom.filterSearch.value = '';
        dom.filterActive.value = '';
        state.search = '';
        state.activo = '';
        state.page = 1;
        loadProviders();
    });

    dom.btnPrev.addEventListener('click', () => {
        if (state.page > 1 && !state.loading) {
            state.page -= 1;
            loadProviders();
        }
    });

    dom.btnNext.addEventListener('click', () => {
        if (state.page < state.totalPages && !state.loading) {
            state.page += 1;
            loadProviders();
        }
    });

    dom.btnNewProvider.addEventListener('click', startCreateProvider);
    dom.tableBody.addEventListener('click', handleTableClick);

    dom.modalClose.addEventListener('click', closeModal);
    dom.modalCancel.addEventListener('click', closeModal);
    dom.modal.addEventListener('click', (event) => {
        if (event.target === dom.modal) {
            closeModal();
        }
    });

    dom.form.addEventListener('submit', handleFormSubmit);
}

let debounceTimer;
function debounceLoad() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => loadProviders(), 350);
}

function init() {
    attachListeners();
    loadProviders();
}

document.addEventListener('DOMContentLoaded', init);

// Estilos adicionales para microinteracciones
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
        background: linear-gradient(90deg, rgba(229, 231, 235, 0.4), rgba(200, 200, 200, 0.4), rgba(229, 231, 235, 0.4));
        background-size: 200% 100%;
        animation: shimmer 1.4s ease infinite;
    }
    @keyframes shimmer {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }
`;
document.head.appendChild(style);

