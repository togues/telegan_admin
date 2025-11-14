import { ApiClient } from '../../js/ApiClient.js';

const turfLib = typeof window !== 'undefined' && window.turf ? window.turf : null;
const apiClient = window.apiClient instanceof ApiClient ? window.apiClient : new ApiClient();

const state = {
    loading: false,
    page: 1,
    pageSize: 20,
    totalPages: 1,
    filters: {
        q: '',
        estado: '',
        tipo: '',
        fechaDesde: '',
        fechaHasta: ''
    },
    selected: new Set(),
    currentCapture: null,
    currentFeature: null,
    mapInstance: null,
    mapLayer: null
};

const elements = {
    tableBody: document.getElementById('capturesTableBody'),
    paginationInfo: document.getElementById('paginationInfo'),
    paginationInfoBottom: document.getElementById('paginationInfoBottom'),
    btnPrevPage: document.getElementById('btnPrevPage'),
    btnNextPage: document.getElementById('btnNextPage'),
    btnPrevPageBottom: document.getElementById('btnPrevPageBottom'),
    btnNextPageBottom: document.getElementById('btnNextPageBottom'),
    selectAll: document.getElementById('selectAll'),
    bulkBanner: document.getElementById('bulkBanner'),
    bulkCount: document.getElementById('bulkCount'),
    bulkApprove: document.getElementById('bulkApprove'),
    bulkReject: document.getElementById('bulkReject'),
    filterSearch: document.getElementById('filterSearch'),
    filterEstado: document.getElementById('filterEstado'),
    filterTipo: document.getElementById('filterTipo'),
    filterFechaDesde: document.getElementById('filterFechaDesde'),
    filterFechaHasta: document.getElementById('filterFechaHasta'),
    btnClearFilters: document.getElementById('btnClearFilters'),
    captureModal: document.getElementById('captureModal'),
    captureModalClose: document.getElementById('captureModalClose'),
    captureModalTitle: document.getElementById('captureModalTitle'),
    captureModalSubtitle: document.getElementById('captureModalSubtitle'),
    infoFincaNombre: document.getElementById('infoFincaNombre'),
    infoFincaCodigo: document.getElementById('infoFincaCodigo'),
    infoFincaEstado: document.getElementById('infoFincaEstado'),
    infoEstado: document.getElementById('infoEstado'),
    infoFechaCaptura: document.getElementById('infoFechaCaptura'),
    infoFechaProcesado: document.getElementById('infoFechaProcesado'),
    infoArea: document.getElementById('infoArea'),
    infoTipoGeom: document.getElementById('infoTipoGeom'),
    infoPerimetro: document.getElementById('infoPerimetro'),
    infoCapturistaNombre: document.getElementById('infoCapturistaNombre'),
    infoCapturistaEmail: document.getElementById('infoCapturistaEmail'),
    infoCapturistaActividad: document.getElementById('infoCapturistaActividad'),
    commentBox: document.getElementById('detailComentario'),
    detailApproveBtn: document.getElementById('detailApproveBtn'),
    detailRejectBtn: document.getElementById('detailRejectBtn'),
    detailVerHistorial: document.getElementById('detailVerHistorial'),
    historyModal: document.getElementById('historyModal'),
    historyModalClose: document.getElementById('historyModalClose'),
    historySubtitle: document.getElementById('historySubtitle'),
    historyContent: document.getElementById('historyContent'),
    mapFallback: document.getElementById('mapFallback'),
    mapWrapper: document.getElementById('captureMap')
};

function showToast(message, type = 'info', duration = 3000) {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
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
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return value;
    return d.toLocaleString();
}

function formatArea(value) {
    if (value === null || value === undefined) return '—';
    return `${value.toFixed(2)} ha`;
}

function formatPerimeter(value) {
    if (value === null || value === undefined) return '—';
    return `${value.toFixed(0)} m`;
}

function formatCoordinate(value) {
    if (value === null || value === undefined) return '—';
    return value.toFixed(6);
}

function updateBulkBanner() {
    const count = state.selected.size;
    if (count === 0) {
        state.bulkMode = false;
        elements.bulkBanner.style.display = 'none';
        elements.bulkApprove.disabled = true;
        elements.bulkReject.disabled = true;
        elements.bulkCount.textContent = '0 capturas seleccionadas';
        return;
    }
    state.bulkMode = true;
    elements.bulkBanner.style.display = 'flex';
    elements.bulkCount.textContent = `${count} captura${count === 1 ? '' : 's'} seleccionada${count === 1 ? '' : 's'}`;
    elements.bulkApprove.disabled = false;
    elements.bulkReject.disabled = false;
}

function buildStatusPill(capture) {
    const span = document.createElement('span');
    const estado = (capture.estado || '').toLowerCase();
    span.className = `status-pill pill-${estado}`;
    span.textContent = capture.estado ?? '—';
    return span;
}

function buildRow(capture) {
    const tr = document.createElement('tr');
    const checked = state.selected.has(capture.id_captura);

    tr.innerHTML = `
        <td style="text-align:center">
            <input type="checkbox" class="row-select" data-id="${capture.id_captura}" ${checked ? 'checked' : ''}>
        </td>
        <td></td>
        <td>
            <strong>${capture.nombre_finca ?? '—'}</strong>
            <div style="font-size:0.8rem; color:var(--text-secondary);">ID ${capture.id_finca} · Código ${capture.codigo_telegan ?? '—'}</div>
        </td>
        <td>${capture.codigo_telegan ?? '—'}</td>
        <td>
            ${capture.capturista_nombre ?? '—'}
            <div style="font-size:0.8rem; color:var(--text-secondary);">${capture.capturista_email ?? ''}</div>
        </td>
        <td>${formatDate(capture.fecha_captura)}</td>
        <td>${capture.tipo_geometria ?? '—'}</td>
        <td>${formatArea(capture.area_estimado)}</td>
        <td class="actions-cell">
            <button class="btn-icon" data-action="view" data-id="${capture.id_captura}" title="Ver detalle">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="3"></circle>
                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"></path>
                </svg>
            </button>
            <button class="btn-icon" data-action="approve" data-id="${capture.id_captura}" title="Aprobar">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
            </button>
            <button class="btn-icon" data-action="reject" data-id="${capture.id_captura}" title="Rechazar">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </td>
    `;

    const statusCell = tr.children[1];
    statusCell.appendChild(buildStatusPill(capture));

    return tr;
}

async function loadCaptures() {
    state.loading = true;
    const params = {
        page: state.page,
        page_size: state.pageSize,
        q: state.filters.q,
        estado: state.filters.estado,
        tipo: state.filters.tipo,
        fecha_desde: state.filters.fechaDesde,
        fecha_hasta: state.filters.fechaHasta
    };

    try {
        const response = await apiClient.get('fincas-geom/captures-list.php', params);
        if (!response.success) {
            throw new Error(response.error || 'No se pudo obtener el listado');
        }

        renderTable(response.data || []);
        updatePagination(response.pagination);
    } catch (error) {
        console.error(error);
        elements.tableBody.innerHTML = `
            <tr><td colspan="9" class="empty-state">
                <strong>Error cargando capturas</strong>
                ${(error && error.message) || 'Intenta nuevamente más tarde.'}
            </td></tr>
        `;
        showToast(error.message || 'Error cargando capturas', 'error');
    } finally {
        state.loading = false;
    }
}

function renderTable(rows) {
    if (!rows.length) {
        elements.tableBody.innerHTML = `
            <tr><td colspan="9" class="empty-state">
                <strong>Sin capturas por el momento</strong>
                Cuando la PWA sincronice un envío aparecerá aquí.
            </td></tr>
        `;
        return;
    }

    elements.tableBody.innerHTML = '';
    rows.forEach((row) => {
        const tr = buildRow(row);
        elements.tableBody.appendChild(tr);
    });

    elements.tableBody.querySelectorAll('.row-select').forEach((checkbox) => {
        checkbox.addEventListener('change', onRowSelectChange);
    });
    elements.tableBody.querySelectorAll('button[data-action]').forEach((btn) => {
        btn.addEventListener('click', handleRowAction);
    });
}

function updatePagination(pagination = {}) {
    state.totalPages = pagination.total_pages || 1;
    const current = pagination.page || state.page;
    const total = pagination.total || 0;

    elements.paginationInfo.textContent = `Página ${current} de ${state.totalPages} · ${total} capturas`;
    elements.paginationInfoBottom.textContent = elements.paginationInfo.textContent;

    const disablePrev = current <= 1;
    const disableNext = current >= state.totalPages;

    [elements.btnPrevPage, elements.btnPrevPageBottom].forEach((btn) => { btn.disabled = disablePrev; });
    [elements.btnNextPage, elements.btnNextPageBottom].forEach((btn) => { btn.disabled = disableNext; });
}

function onRowSelectChange(event) {
    const checkbox = event.currentTarget;
    const id = Number.parseInt(checkbox.dataset.id, 10);
    if (Number.isNaN(id)) return;

    if (checkbox.checked) {
        state.selected.add(id);
    } else {
        state.selected.delete(id);
    }
    updateBulkBanner();
}

function handleRowAction(event) {
    const button = event.currentTarget;
    const id = Number.parseInt(button.dataset.id, 10);
    const action = button.dataset.action;
    if (Number.isNaN(id)) return;

    if (action === 'view') {
        openCaptureDetail(id);
    } else if (action === 'approve') {
        approveCapture(id);
    } else if (action === 'reject') {
        rejectCapture(id);
    }
}

async function openCaptureDetail(id) {
    try {
        const response = await apiClient.get('fincas-geom/capture-detail.php', { id });
        if (!response.success) {
            throw new Error(response.error || 'No se pudo obtener el detalle');
        }
        state.currentCapture = response.data;
        fillDetailModal(response.data);
        toggleModal(elements.captureModal, true);
    } catch (error) {
        console.error(error);
        showToast(error.message || 'No se pudo cargar la captura', 'error');
    }
}

function fillDetailModal(data) {
    elements.captureModalTitle.textContent = `Captura #${data.id_captura}`;
    elements.captureModalSubtitle.textContent = `Finca #${data.id_finca} · ${data.nombre_finca ?? '—'}`;

    elements.infoFincaNombre.textContent = data.nombre_finca ?? '—';
    elements.infoFincaCodigo.textContent = data.codigo_telegan ?? '—';
    elements.infoFincaEstado.textContent = data.estado_finca ?? '—';

    elements.infoEstado.textContent = data.estado_captura ?? '—';
    elements.infoFechaCaptura.textContent = formatDate(data.fecha_captura);
    elements.infoFechaProcesado.textContent = formatDate(data.fecha_procesado);

    elements.infoCapturistaNombre.textContent = data.capturista_nombre ?? '—';
    elements.infoCapturistaEmail.textContent = data.capturista_email ?? '';
    elements.infoCapturistaActividad.textContent = formatDate(data.capturista_ultima_sesion);

    elements.commentBox.value = data.comentario ?? '';

    state.currentCapture = data;
    state.currentFeature = null;

    const rawGeojson = data.geometria_geojson || null;

    if (rawGeojson) {
        try {
            const geometry = typeof rawGeojson === 'string' ? JSON.parse(rawGeojson) : rawGeojson;
            const feature = geometry && geometry.type
                ? { type: 'Feature', properties: {}, geometry }
                : null;

            if (feature) {
                state.currentFeature = feature;
                const geomType = feature.geometry.type;
                let areaHa = data.area_hectareas_calculada ?? null;
                let perimeter = null;
                let centroid = null;

                if (turfLib) {
                    try {
                        if (geomType === 'Polygon' || geomType === 'MultiPolygon') {
                            areaHa = turfLib.area(feature) / 10000;
                            const lines = turfLib.polygonToLine(feature);
                            perimeter = turfLib.length(lines, { units: 'kilometers' }) * 1000;
                            centroid = turfLib.center(feature);
                        } else if (geomType === 'Point') {
                            centroid = feature;
                        }
                    } catch (error) {
                        console.warn('No se pudieron calcular métricas Turf:', error);
                    }
                }

                if (areaHa === null && data.area_hectareas_calculada !== null && data.area_hectareas_calculada !== undefined) {
                    areaHa = data.area_hectareas_calculada;
                }

                elements.infoArea.textContent = areaHa !== null ? formatArea(areaHa) : '—';
                elements.infoTipoGeom.textContent = geomType;
                elements.infoPerimetro.textContent = perimeter !== null ? formatPerimeter(perimeter) : '—';

                if (centroid && centroid.geometry && centroid.geometry.type === 'Point') {
                    const [lng, lat] = centroid.geometry.coordinates;
                    elements.infoCapturistaActividad.textContent = `${formatCoordinate(lat)}, ${formatCoordinate(lng)}`;
                }

                renderMap(feature);
                return;
            }
        } catch (error) {
            console.warn('No se pudo interpretar la geometría GeoJSON:', error);
        }
    }

    const fallbackArea = data.area_hectareas_calculada ?? data.validaciones?.area_oficial ?? null;
    elements.infoArea.textContent = formatArea(fallbackArea);
    elements.infoTipoGeom.textContent = data.tipo_geometria ?? '—';
    elements.infoPerimetro.textContent = '—';
    renderMap(null);
}

function renderMap(feature) {
    if (!feature) {
        elements.mapFallback.hidden = false;
        elements.mapFallback.style.display = 'flex';
        if (state.mapInstance) {
            state.mapInstance.remove();
            state.mapInstance = null;
            state.mapLayer = null;
        }
        return;
    }

    elements.mapFallback.hidden = true;
    elements.mapFallback.style.display = 'none';

    if (!state.mapInstance) {
        state.mapInstance = L.map('captureMap');

        const baseLayer = L.tileLayer.provider('Esri.WorldImagery');
        baseLayer.addTo(state.mapInstance);

        state.mapInstance.createPane('labels');
        const labelsPane = state.mapInstance.getPane('labels');
        if (labelsPane) {
            labelsPane.style.zIndex = 650;
            labelsPane.style.pointerEvents = 'none';
        }
        try {
            L.tileLayer.provider('Esri.WorldBoundariesAndPlaces', { pane: 'labels' }).addTo(state.mapInstance);
        } catch (error) {
            console.warn('No se pudo cargar el overlay de etiquetas Esri.WorldBoundariesAndPlaces', error);
        }

        state.mapInstance.addControl(new L.Control.Draw({ edit: false, draw: false }));
    }

    if (state.mapLayer) {
        state.mapInstance.removeLayer(state.mapLayer);
    }

    state.mapLayer = L.geoJSON(feature).addTo(state.mapInstance);
    const bounds = state.mapLayer.getBounds();
    if (bounds.isValid()) {
        state.mapInstance.fitBounds(bounds, { padding: [20, 20] });
    } else if (feature.geometry.type === 'Point') {
        const coords = feature.geometry.coordinates;
        state.mapInstance.setView([coords[1], coords[0]], 16);
    }

    state.mapInstance.invalidateSize();
    setTimeout(() => {
        if (state.mapInstance) {
            state.mapInstance.invalidateSize();
        }
    }, 250);
}

async function approveCapture(id, comentario = '') {
    try {
        const response = await apiClient.post('fincas-geom/capture-approve.php', {
            id_captura: id,
            comentario
        });
        if (!response.success) {
            throw new Error(response.error || 'No se pudo aprobar');
        }
        showToast(response.message || 'Captura aprobada', 'success');
        state.selected.delete(id);
        updateBulkBanner();
        await loadCaptures();
    } catch (error) {
        console.error(error);
        showToast(error.message || 'Error aprobando captura', 'error');
    }
}

async function rejectCapture(id, comment) {
    const comentario = comment ?? window.prompt('Describe la razón del rechazo');
    if (comentario === null) return; // cancelado
    if (comentario.trim() === '') {
        showToast('Es necesario agregar un comentario al rechazar', 'info');
        return;
    }
    try {
        const response = await apiClient.post('fincas-geom/capture-reject.php', {
            id_captura: id,
            comentario
        });
        if (!response.success) {
            throw new Error(response.error || 'No se pudo rechazar');
        }
        showToast(response.message || 'Captura rechazada', 'success');
        state.selected.delete(id);
        updateBulkBanner();
        await loadCaptures();
    } catch (error) {
        console.error(error);
        showToast(error.message || 'Error rechazando captura', 'error');
    }
}

async function bulkAction(fn) {
    if (!state.selected.size) return;
    const ids = Array.from(state.selected);
    for (const id of ids) {
        await fn(id); // procesamos secuencial para simplificar
    }
}

function toggleModal(modal, show) {
    if (!modal) return;
    modal.style.display = show ? 'flex' : 'none';
    if (!show) {
        if (state.mapInstance) {
            state.mapInstance.remove();
            state.mapInstance = null;
            state.mapLayer = null;
        }
        state.currentCapture = null;
    }
}

function bindEvents() {
    elements.btnPrevPage.addEventListener('click', () => {
        if (state.page > 1) {
            state.page -= 1;
            loadCaptures();
        }
    });
    elements.btnPrevPageBottom.addEventListener('click', () => elements.btnPrevPage.click());

    elements.btnNextPage.addEventListener('click', () => {
        if (state.page < state.totalPages) {
            state.page += 1;
            loadCaptures();
        }
    });
    elements.btnNextPageBottom.addEventListener('click', () => elements.btnNextPage.click());

    elements.filterSearch.addEventListener('input', debounce(() => {
        state.filters.q = elements.filterSearch.value.trim();
        state.page = 1;
        loadCaptures();
    }, 350));
    elements.filterEstado.addEventListener('change', () => {
        state.filters.estado = elements.filterEstado.value;
        state.page = 1;
        loadCaptures();
    });
    elements.filterTipo.addEventListener('change', () => {
        state.filters.tipo = elements.filterTipo.value;
        state.page = 1;
        loadCaptures();
    });
    elements.filterFechaDesde.addEventListener('change', () => {
        state.filters.fechaDesde = elements.filterFechaDesde.value;
        state.page = 1;
        loadCaptures();
    });
    elements.filterFechaHasta.addEventListener('change', () => {
        state.filters.fechaHasta = elements.filterFechaHasta.value;
        state.page = 1;
        loadCaptures();
    });
    elements.btnClearFilters.addEventListener('click', () => {
        state.filters = { q: '', estado: '', tipo: '', fechaDesde: '', fechaHasta: '' };
        state.page = 1;
        elements.filterSearch.value = '';
        elements.filterEstado.value = '';
        elements.filterTipo.value = '';
        elements.filterFechaDesde.value = '';
        elements.filterFechaHasta.value = '';
        loadCaptures();
    });

    elements.selectAll.addEventListener('change', (event) => {
        const checked = event.currentTarget.checked;
        if (checked) {
            document.querySelectorAll('.row-select').forEach((item) => {
                const id = Number.parseInt(item.dataset.id, 10);
                if (!Number.isNaN(id)) {
                    state.selected.add(id);
                    item.checked = true;
                }
            });
        } else {
            state.selected.clear();
            document.querySelectorAll('.row-select').forEach((item) => { item.checked = false; });
        }
        updateBulkBanner();
    });

    elements.bulkApprove.addEventListener('click', async () => {
        elements.bulkApprove.disabled = true;
        await bulkAction((id) => approveCapture(id, 'Aprobada en lote'));
    });

    elements.bulkReject.addEventListener('click', async () => {
        const comentario = window.prompt('Describe brevemente el motivo del rechazo masivo');
        if (comentario === null || comentario.trim() === '') {
            showToast('No se ejecutó el rechazo masivo (se requiere comentario).', 'info');
            return;
        }
        elements.bulkReject.disabled = true;
        await bulkAction((id) => rejectCapture(id, comentario));
    });

    elements.captureModalClose.addEventListener('click', () => toggleModal(elements.captureModal, false));
    elements.detailApproveBtn.addEventListener('click', () => {
        if (!state.currentCapture) return;
        approveCapture(state.currentCapture.id_captura, elements.commentBox.value.trim());
        toggleModal(elements.captureModal, false);
    });
    elements.detailRejectBtn.addEventListener('click', () => {
        if (!state.currentCapture) return;
        const comentario = elements.commentBox.value.trim() || window.prompt('Describe brevemente el motivo del rechazo');
        if (comentario === null || comentario.trim() === '') {
            showToast('Se requiere comentario para rechazar.', 'info');
            return;
        }
        rejectCapture(state.currentCapture.id_captura, comentario);
        toggleModal(elements.captureModal, false);
    });

    elements.detailVerHistorial.addEventListener('click', () => {
        if (!state.currentCapture) return;
        loadHistory(state.currentCapture.id_finca, state.currentCapture.nombre_finca);
    });
    elements.historyModalClose.addEventListener('click', () => toggleModal(elements.historyModal, false));

    window.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            toggleModal(elements.captureModal, false);
            toggleModal(elements.historyModal, false);
        }
    });
}

async function loadHistory(idFinca, nombreFinca) {
    elements.historySubtitle.textContent = `Finca #${idFinca} · ${nombreFinca ?? '—'}`;
    elements.historyContent.innerHTML = '<div class="empty-state">Cargando historial...</div>';
    toggleModal(elements.historyModal, true);
    try {
        const response = await apiClient.get('fincas-geom/finca-history.php', { id_finca: idFinca });
        if (!response.success) {
            throw new Error(response.error || 'No se pudo obtener el historial');
        }
        const items = response.data || [];
        if (!items.length) {
            elements.historyContent.innerHTML = '<div class="empty-state"><strong>Sin historial</strong>Esta finca aún no tiene geometrías aprobadas.</div>';
            return;
        }
        elements.historyContent.innerHTML = '';
        items.forEach((item) => {
            const div = document.createElement('div');
            div.className = 'history-item';
            div.innerHTML = `
                <div>
                    <strong>${formatArea(item.area_hectareas ?? null)}</strong>
                    <div style="font-size:0.8rem; color:var(--text-secondary);">${item.geometry_type ?? '—'}</div>
                    <div style="font-size:0.8rem; color:var(--text-secondary);">${item.comentario ?? ''}</div>
                </div>
                <time>${formatDate(item.fecha_aprobacion)}</time>
            `;
            elements.historyContent.appendChild(div);
        });
    } catch (error) {
        console.error(error);
        elements.historyContent.innerHTML = `<div class="empty-state"><strong>Error</strong>${error.message || 'No fue posible cargar el historial.'}</div>`;
    }
}

function debounce(fn, delay = 300) {
    let handle;
    return (...args) => {
        clearTimeout(handle);
        handle = setTimeout(() => fn(...args), delay);
    };
}

function init() {
    bindEvents();
    loadCaptures();
}

init();
