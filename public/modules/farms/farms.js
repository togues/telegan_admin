import { ApiClient } from '../../js/ApiClient.js';

const apiClient = window.apiClient instanceof ApiClient ? window.apiClient : new ApiClient();

const state = {
    loading: false,
    page: 1,
    pageSize: 20,
    totalPages: 1,
    filters: {
        search: '',
        estado: '',
        pais: '',
        geometry: '',
        creador: '',
    },
    editingId: null,
};

const elements = {
    tableBody: document.getElementById('farmsTableBody'),
    paginationInfo: document.getElementById('paginationInfo'),
    btnPrevPage: document.getElementById('btnPrevPage'),
    btnNextPage: document.getElementById('btnNextPage'),
    filterSearch: document.getElementById('filterSearch'),
    filterEstado: document.getElementById('filterEstado'),
    filterPais: document.getElementById('filterPais'),
    filterGeometry: document.getElementById('filterGeometry'),
    filterCreador: document.getElementById('filterCreador'),
    btnClearFilters: document.getElementById('btnClearFilters'),

    modal: document.getElementById('farmModal'),
    modalClose: document.getElementById('farmModalClose'),
    modalTitle: document.getElementById('farmModalTitle'),
    modalSubtitle: document.getElementById('farmModalSubtitle'),
    form: document.getElementById('farmForm'),
    formSubmit: document.getElementById('formSubmit'),
    formCancel: document.getElementById('formCancel'),

    fieldId: document.getElementById('fieldId'),
    fieldNombre: document.getElementById('fieldNombre'),
    fieldCodigo: document.getElementById('fieldCodigo'),
    fieldEstado: document.getElementById('fieldEstado'),
    fieldPais: document.getElementById('fieldPais'),
    fieldDescripcion: document.getElementById('fieldDescripcion'),

    infoGeometry: document.getElementById('infoGeometry'),
    infoArea: document.getElementById('infoArea'),
    infoPotreros: document.getElementById('infoPotreros'),
    infoCreador: document.getElementById('infoCreador'),
    infoCreadorEmail: document.getElementById('infoCreadorEmail'),
    infoFechas: document.getElementById('infoFechas'),
};

function showToast(message, type = 'info', duration = 3200) {
    const toast = document.createElement('div');
    toast.className = `toast ${type === 'error' ? 'error' : type === 'info' ? 'info' : ''}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('show'));
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

function formatDate(value) {
    if (!value) return '—';
    try {
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return value;
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

function formatArea(value) {
    if (value === null || value === undefined) return '—';
    return `${value.toFixed(2)} ha`;
}

function buildEstadoPill(estado) {
    const span = document.createElement('span');
    const normalized = (estado || '').toLowerCase();
    span.className = `status-pill pill-${normalized}`;
    span.textContent = estado ?? '—';
    return span;
}

function buildGeometryBadge(hasGeometry) {
    const span = document.createElement('span');
    span.className = `badge ${hasGeometry ? '' : 'badge-warning'}`;
    span.innerHTML = hasGeometry
        ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> Con geometría'
        : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg> Sin geometría';
    return span;
}

function buildRow(farm) {
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td></td>
        <td>
            <strong>${farm.nombre_finca ?? '—'}</strong>
            <div style="font-size:0.8rem; color:var(--text-secondary);">ID ${farm.id_finca} · Código ${farm.codigo_telegan ?? '—'}</div>
        </td>
        <td>${farm.pais_nombre ?? '—'}</td>
        <td>
            ${farm.creador_nombre ?? '—'}
        </td>
        <td>${formatDate(farm.fecha_creacion)}</td>
        <td></td>
        <td>${farm.potreros_count}</td>
        <td class="actions-cell">
            <button class="btn-icon" data-action="edit" data-id="${farm.id_finca}" title="Editar">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 20h9"></path>
                    <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"></path>
                </svg>
            </button>
            <button class="btn-icon" data-action="toggle" data-id="${farm.id_finca}" data-state="${farm.estado}" title="${farm.estado === 'ACTIVA' ? 'Desactivar' : 'Activar'}">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M5 12l5 5L20 7"></path>
                </svg>
            </button>
        </td>
    `;

    const estadoCell = tr.children[0];
    estadoCell.appendChild(buildEstadoPill(farm.estado));

    const geometryCell = tr.children[5];
    geometryCell.appendChild(buildGeometryBadge(farm.has_geometry));

    return tr;
}

async function loadSupport() {
    try {
        const response = await apiClient.get('farms-support.php');
        if (!response.success) {
            throw new Error(response.error || 'No se pudieron cargar los catálogos');
        }
        const { countries = [], creators = [], states = [] } = response.data || {};

        if (states.length) {
            elements.filterEstado.innerHTML = '<option value="">Todos los estados</option>';
            states.forEach((stateItem) => {
                const option = document.createElement('option');
                option.value = stateItem.value;
                option.textContent = stateItem.label;
                elements.filterEstado.appendChild(option);
            });
        }

        if (countries.length) {
            elements.filterPais.innerHTML = '<option value="">Todos los países</option>';
            elements.fieldPais.innerHTML = '<option value="">Sin asignar</option>';
            countries.forEach((country) => {
                const optionFilter = document.createElement('option');
                optionFilter.value = country.codigo_iso2;
                optionFilter.textContent = country.nombre_pais;
                elements.filterPais.appendChild(optionFilter);

                const optionField = document.createElement('option');
                optionField.value = country.id_pais;
                optionField.textContent = `${country.nombre_pais} (${country.codigo_iso2})`;
                elements.fieldPais.appendChild(optionField);
            });
        }

        if (creators.length) {
            elements.filterCreador.innerHTML = '<option value="">Todos los creadores</option>';
            creators.forEach((creator) => {
                const option = document.createElement('option');
                option.value = creator.id_usuario;
                option.textContent = creator.nombre_completo;
                elements.filterCreador.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error cargando datos de soporte', error);
        showToast(error.message || 'Error cargando catálogos', 'error');
    }
}

async function loadFarms() {
    if (state.loading) return;
    state.loading = true;

    const params = {
        page: state.page,
        page_size: state.pageSize,
        q: state.filters.search,
        estado: state.filters.estado,
        pais: state.filters.pais,
        geometry: state.filters.geometry,
        creador: state.filters.creador,
    };

    try {
        const response = await apiClient.get('farms-list.php', params);
        if (!response.success) {
            throw new Error(response.error || 'No se pudo obtener el listado');
        }
        renderTable(response.data || []);
        updatePagination(response.pagination || {});
    } catch (error) {
        console.error(error);
        elements.tableBody.innerHTML = `
            <tr><td colspan="8" class="empty-state">
                <strong>Error cargando fincas</strong>
                ${(error && error.message) || 'Intenta nuevamente más tarde.'}
            </td></tr>
        `;
        showToast(error.message || 'Error cargando fincas', 'error');
    } finally {
        state.loading = false;
    }
}

function renderTable(rows) {
    if (!rows.length) {
        elements.tableBody.innerHTML = `
            <tr><td colspan="8" class="empty-state">
                <strong>Sin registros</strong>
                Aún no hay fincas que coincidan con los filtros seleccionados.
            </td></tr>
        `;
        return;
    }

    elements.tableBody.innerHTML = '';
    rows.forEach((row) => {
        const tr = buildRow(row);
        elements.tableBody.appendChild(tr);
    });

    elements.tableBody.querySelectorAll('button[data-action]').forEach((button) => {
        button.addEventListener('click', handleTableAction);
    });
}

function updatePagination(pagination = {}) {
    state.totalPages = pagination.total_pages || 1;
    const current = pagination.page || state.page;
    const total = pagination.total || 0;

    elements.paginationInfo.textContent = `Página ${current} de ${state.totalPages} · ${total} fincas`;

    elements.btnPrevPage.disabled = current <= 1;
    elements.btnNextPage.disabled = current >= state.totalPages;
}

function handleTableAction(event) {
    const button = event.currentTarget;
    const id = Number.parseInt(button.dataset.id, 10);
    if (Number.isNaN(id)) return;

    const action = button.dataset.action;
    if (action === 'edit') {
        startEdit(id);
    } else if (action === 'toggle') {
        const currentState = button.dataset.state || 'ACTIVA';
        toggleFarm(id, currentState);
    }
}

async function startEdit(id) {
    try {
        state.editingId = id;
        elements.modalTitle.textContent = 'Editar finca';
        elements.formSubmit.disabled = true;

        const response = await apiClient.get('farms-detail.php', { id });
        if (!response.success || !response.data) {
            throw new Error(response.error || 'No se encontraron datos de la finca');
        }

        fillForm(response.data);
        openModal();
        elements.formSubmit.disabled = false;
    } catch (error) {
        console.error('Error al cargar finca', error);
        showToast(error.message || 'No se pudo cargar la finca', 'error');
        state.editingId = null;
    }
}

function fillForm(data) {
    elements.fieldId.value = data.id_finca;
    elements.fieldNombre.value = data.nombre_finca ?? '';
    elements.fieldCodigo.value = data.codigo_telegan ?? '';
    elements.fieldEstado.value = data.estado ?? 'ACTIVA';
    elements.fieldPais.value = data.id_pais ?? '';
    elements.fieldDescripcion.value = data.descripcion ?? '';

    elements.modalSubtitle.textContent = `ID ${data.id_finca} · Código ${data.codigo_telegan ?? '—'}`;
    elements.infoGeometry.textContent = data.has_geometry ? 'Con geometría registrada' : 'Sin geometría registrada';
    elements.infoArea.textContent = `Área oficial: ${formatArea(data.area_hectareas)}`;
    elements.infoPotreros.textContent = `${data.potreros_count ?? 0} potreros`;
    elements.infoCreador.textContent = data.creador_nombre ?? '—';
    elements.infoCreadorEmail.textContent = data.creador_email ?? '';
    elements.infoFechas.textContent = `Creada: ${formatDate(data.fecha_creacion)} · Actualizada: ${formatDate(data.fecha_actualizacion)}`;
}

function openModal() {
    elements.modal.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    elements.modal.classList.remove('show');
    document.body.style.overflow = '';
    state.editingId = null;
    elements.form.reset();
}

async function handleFormSubmit(event) {
    event.preventDefault();
    if (!state.editingId) return;

    elements.formSubmit.disabled = true;

    const payload = {
        id_finca: state.editingId,
        nombre_finca: elements.fieldNombre.value.trim(),
        codigo_telegan: elements.fieldCodigo.value.trim() || null,
        estado: elements.fieldEstado.value,
        id_pais: elements.fieldPais.value ? Number.parseInt(elements.fieldPais.value, 10) : null,
        descripcion: elements.fieldDescripcion.value.trim() || null,
    };

    try {
        const response = await apiClient.put('farms-update.php', payload);
        if (!response.success) {
            throw new Error(response.error || 'No se pudo actualizar la finca');
        }
        showToast(response.message || 'Finca actualizada');
        closeModal();
        await loadFarms();
    } catch (error) {
        console.error('Error al actualizar finca', error);
        showToast(error.message || 'Error actualizando finca', 'error');
        elements.formSubmit.disabled = false;
    }
}

async function toggleFarm(id, currentState) {
    const nextState = currentState === 'ACTIVA' ? 'INACTIVA' : 'ACTIVA';
    const confirmed = window.confirm(
        nextState === 'ACTIVA'
            ? '¿Deseas activar nuevamente esta finca?'
            : '¿Deseas desactivar esta finca? Los procesos automatizados la ignorarán.'
    );
    if (!confirmed) return;

    try {
        const response = await apiClient.post('farms-toggle.php', {
            id_finca: id,
            estado: nextState,
        });
        if (!response.success) {
            throw new Error(response.error || 'No se pudo cambiar el estado');
        }
        showToast(response.message || 'Estado actualizado');
        await loadFarms();
    } catch (error) {
        console.error('Error al cambiar estado de finca', error);
        showToast(error.message || 'Error cambiando estado', 'error');
    }
}

function attachListeners() {
    const debounce = (fn, delay) => {
        let timer;
        return (...args) => {
            clearTimeout(timer);
            timer = setTimeout(() => fn.apply(null, args), delay);
        };
    };

    elements.filterSearch.addEventListener('input', debounce((event) => {
        state.filters.search = event.target.value.trim();
        state.page = 1;
        loadFarms();
    }, 350));

    elements.filterEstado.addEventListener('change', (event) => {
        state.filters.estado = event.target.value;
        state.page = 1;
        loadFarms();
    });

    elements.filterPais.addEventListener('change', (event) => {
        state.filters.pais = event.target.value;
        state.page = 1;
        loadFarms();
    });

    elements.filterGeometry.addEventListener('change', (event) => {
        state.filters.geometry = event.target.value;
        state.page = 1;
        loadFarms();
    });

    elements.filterCreador.addEventListener('change', (event) => {
        state.filters.creador = event.target.value;
        state.page = 1;
        loadFarms();
    });

    elements.btnClearFilters.addEventListener('click', () => {
        state.filters = { search: '', estado: '', pais: '', geometry: '', creador: '' };
        state.page = 1;
        elements.filterSearch.value = '';
        elements.filterEstado.value = '';
        elements.filterPais.value = '';
        elements.filterGeometry.value = '';
        elements.filterCreador.value = '';
        loadFarms();
    });

    elements.btnPrevPage.addEventListener('click', () => {
        if (state.page > 1 && !state.loading) {
            state.page -= 1;
            loadFarms();
        }
    });

    elements.btnNextPage.addEventListener('click', () => {
        if (state.page < state.totalPages && !state.loading) {
            state.page += 1;
            loadFarms();
        }
    });

    elements.modalClose.addEventListener('click', closeModal);
    elements.formCancel.addEventListener('click', closeModal);
    elements.form.addEventListener('submit', handleFormSubmit);
    elements.modal.addEventListener('click', (event) => {
        if (event.target === elements.modal) {
            closeModal();
        }
    });
}

async function init() {
    attachListeners();
    await loadSupport();
    await loadFarms();
}

document.addEventListener('DOMContentLoaded', init);

