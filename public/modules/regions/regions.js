import { ApiClient } from '../../js/ApiClient.js';

const apiClient = window.apiClient instanceof ApiClient ? window.apiClient : new ApiClient();

const state = {
    page: 1,
    pageSize: 10,
    totalPages: 1,
    search: '',
    country: '',
    type: '',
    active: '',
    loading: false,
    mode: 'create',
    editingCode: null
};

const dom = {
    tableBody: document.getElementById('regionsTableBody'),
    paginationInfo: document.getElementById('paginationInfo'),
    btnPrev: document.getElementById('btnPrevPage'),
    btnNext: document.getElementById('btnNextPage'),
    filterSearch: document.getElementById('filterSearch'),
    filterCountry: document.getElementById('filterCountry'),
    filterType: document.getElementById('filterType'),
    filterActive: document.getElementById('filterActive'),
    btnClearFilters: document.getElementById('btnClearFilters'),
    btnNewRegion: document.getElementById('btnNewRegion'),
    modal: document.getElementById('regionModal'),
    modalTitle: document.getElementById('modalTitle'),
    modalClose: document.getElementById('modalClose'),
    modalSubmit: document.getElementById('modalSubmit'),
    form: document.getElementById('regionForm'),
    fields: {
        codigo: document.getElementById('codigo'),
        nombre: document.getElementById('nombre'),
        pais_codigo_iso: document.getElementById('pais_codigo_iso'),
        tipo: document.getElementById('tipo'),
        geom_wkt: document.getElementById('geom_wkt'),
        metadata: document.getElementById('metadata'),
        activo: document.getElementById('activo')
    },
    mapContainer: document.getElementById('regionMap'),
    btnLoadWkt: document.getElementById('btnLoadWkt'),
    areaInfo: document.getElementById('areaInfo')
};

const DEFAULT_CENTER = [14.508, -86.241];
const DEFAULT_ZOOM = 6;

let mapInstance = null;
let drawnLayer = null;
let drawControl = null;

function areLibsReady() {
    return typeof turf !== 'undefined' && typeof Terraformer !== 'undefined' && typeof Terraformer.WKT !== 'undefined';
}

function loadScript(src) {
    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = src;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error(`No se pudo cargar ${src}`));
        document.head.appendChild(script);
    });
}

async function init() {
    attachListeners();
    const waitLibs = () => {
        if (!areLibsReady()) {
            setTimeout(waitLibs, 100);
            return;
        }
        loadRegions();
    };
    waitLibs();
}

function initMap() {
    if (!dom.mapContainer) return;

    if (!mapInstance) {
        mapInstance = L.map(dom.mapContainer, {
            center: DEFAULT_CENTER,
            zoom: DEFAULT_ZOOM,
            preferCanvas: true
        });

        const imagery = L.tileLayer.provider('Esri.WorldImagery');
        imagery.addTo(mapInstance);

        mapInstance.createPane('labels');
        mapInstance.getPane('labels').style.zIndex = 650;
        mapInstance.getPane('labels').style.pointerEvents = 'none';
        const labels = L.tileLayer.provider('Esri.WorldReferenceOverlay', { pane: 'labels' });
        labels.addTo(mapInstance);

        const drawOptions = {
            draw: {
                marker: false,
                polyline: false,
                circle: false,
                circlemarker: false,
                rectangle: {
                    shapeOptions: { color: '#4da1d9', weight: 2 }
                },
                polygon: {
                    allowIntersection: false,
                    showArea: true,
                    drawError: { color: '#dc2626', message: 'El polígono no puede cruzarse.' },
                    shapeOptions: { color: '#4da1d9', weight: 2 }
                }
            },
            edit: {
                featureGroup: new L.FeatureGroup(),
                remove: true
            }
        };

        drawControl = new L.Control.Draw(drawOptions);
        mapInstance.addControl(drawControl);
        mapInstance.addLayer(drawOptions.edit.featureGroup);

        mapInstance.on(L.Draw.Event.CREATED, (event) => {
            drawOptions.edit.featureGroup.clearLayers();
            drawOptions.edit.featureGroup.addLayer(event.layer);
            drawnLayer = event.layer;
            syncWktFromMap();
        });

        mapInstance.on(L.Draw.Event.EDITED, () => {
            drawnLayer = drawControl.options.edit.featureGroup.getLayers()[0];
            syncWktFromMap();
        });

        mapInstance.on(L.Draw.Event.DELETED, () => {
            drawnLayer = null;
            dom.fields.geom_wkt.value = '';
            dom.areaInfo.textContent = '';
        });
    }

    setTimeout(() => {
        if (mapInstance) {
            mapInstance.invalidateSize();
        }
    }, 100);
}

function syncWktFromMap() {
    if (!drawnLayer || !areLibsReady()) return;
    const geojson = drawnLayer.toGeoJSON();
    const wkt = Terraformer.WKT.convert(geojson.geometry);
    dom.fields.geom_wkt.value = wkt;
    updateAreaInfo(geojson.geometry);
}

function updateAreaInfo(geometry) {
    if (!geometry || !areLibsReady() || typeof turf === 'undefined') {
        dom.areaInfo.textContent = '';
        return;
    }
    const areaMeters = turf.area({ type: 'Feature', geometry });
    if (!Number.isFinite(areaMeters)) {
        dom.areaInfo.textContent = '';
        return;
    }
    const areaKm2 = areaMeters / 1_000_000;
    dom.areaInfo.textContent = `Área estimada: ${areaKm2.toFixed(2)} km²`;
}

function drawPolygonFromWkt(wkt) {
    if (!drawControl || !areLibsReady()) return;
    const featureGroup = drawControl.options.edit.featureGroup;
    featureGroup.clearLayers();
    drawnLayer = null;
    dom.areaInfo.textContent = '';

    if (!wkt || !wkt.trim()) {
        if (mapInstance) {
            mapInstance.setView(DEFAULT_CENTER, DEFAULT_ZOOM);
            mapInstance.invalidateSize();
        }
        return;
    }

    try {
        const geometry = Terraformer.WKT.parse(wkt);
        const layer = L.geoJSON(geometry).getLayers()[0];
        if (!layer) throw new Error('No se pudo interpretar la geometría');
        featureGroup.addLayer(layer);
        drawnLayer = layer;
        mapInstance.fitBounds(layer.getBounds(), { padding: [20, 20] });
        mapInstance.invalidateSize();
        updateAreaInfo(layer.toGeoJSON().geometry);
    } catch (error) {
        console.error('Error al dibujar WKT', error);
        showToast('No se pudo interpretar el WKT proporcionado', true);
    }
}

function validatePolygon() {
    if (dom.fields.geom_wkt.value.trim() === '') {
        return true; // opcional
    }

    if (!areLibsReady()) {
        showToast('Librerías de geometría no disponibles', true);
        return false;
    }

    try {
        const geometry = Terraformer.WKT.parse(dom.fields.geom_wkt.value);
        if (!geometry || (geometry.type !== 'Polygon' && geometry.type !== 'MultiPolygon')) {
            throw new Error('La geometría debe ser Polígono o MultiPolígono');
        }
        if (geometry.coordinates && geometry.coordinates.length === 0) {
            throw new Error('El polígono no tiene coordenadas válidas');
        }
        return true;
    } catch (error) {
        showToast(`Geometría inválida: ${error.message}`, true);
        return false;
    }
}

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
                    <div class="skeleton-line" style="width:95%;"></div>
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
    } catch (_) {
        return value;
    }
}

function formatMetadata(meta) {
    if (!meta || typeof meta !== 'object') {
        return '<span style="color:var(--text-tertiary);">Sin metadata</span>';
    }
    const entries = Object.entries(meta);
    if (!entries.length) {
        return '<span style="color:var(--text-tertiary);">Sin metadata</span>';
    }
    return `<span style="font-size:0.78rem;color:var(--text-secondary);">${entries.slice(0,3).map(([k,v]) => `<strong>${k}</strong>: ${v}`).join(' · ')}` + (entries.length > 3 ? '…' : '') + '</span>';
}

function renderTable(regions) {
    if (!Array.isArray(regions) || regions.length === 0) {
        dom.tableBody.innerHTML = `
            <tr>
                <td colspan="8" class="empty-state">
                    <strong>No encontramos regiones con esos filtros</strong>
                    Ajusta la búsqueda o crea una nueva.
                </td>
            </tr>
        `;
        return;
    }

    const rows = regions.map((item) => {
        const estado = item.activo
            ? '<span class="badge" style="background:rgba(109,190,69,0.18);color:var(--accent-primary);">Activa</span>'
            : '<span class="badge" style="background:rgba(229,231,235,0.6);color:var(--text-secondary);">Inactiva</span>';

        return `
            <tr data-code="${item.codigo}" data-active="${item.activo ? '1' : '0'}">
                <td><strong>${item.codigo}</strong></td>
                <td>
                    <div style="display:flex;flex-direction:column;gap:0.15rem;">
                        <span style="font-weight:600;">${item.nombre}</span>
                        ${item.tipo ? `<span style="font-size:0.78rem;color:var(--text-secondary);">${item.tipo}</span>` : ''}
                    </div>
                </td>
                <td>${item.pais_codigo_iso || '—'}</td>
                <td>${item.tipo || '—'}</td>
                <td>${formatMetadata(item.metadata)}</td>
                <td>${estado}</td>
                <td>${formatDate(item.fecha_creacion)}</td>
                <td>
                    <div class="actions-cell">
                        <button class="btn-icon" data-action="edit" title="Editar región" aria-label="Editar región">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 20h9"></path>
                                <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"></path>
                            </svg>
                        </button>
                        <button class="btn-icon" data-action="toggle" data-active="${item.activo ? '1' : '0'}" title="${item.activo ? 'Inactivar región' : 'Activar región'}" aria-label="${item.activo ? 'Inactivar región' : 'Activar región'}">
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

async function loadRegions(force = false) {
    if (state.loading && !force) return;
    setLoading(true);
    try {
        const params = new URLSearchParams({
            page: state.page.toString(),
            page_size: state.pageSize.toString()
        });
        if (state.search) params.append('q', state.search);
        if (state.country) params.append('pais', state.country);
        if (state.type) params.append('tipo', state.type);
        if (state.active !== '') params.append('activo', state.active);

        const response = await apiClient.get(`regions-list.php?${params.toString()}`);
        if (!response.success) {
            throw new Error(response.error || 'No se pudo cargar el listado');
        }

        const { pagination, data } = response;
        state.totalPages = pagination?.total_pages ? Number(pagination.total_pages) : 1;
        state.page = pagination?.page ? Number(pagination.page) : 1;

        renderTable(data);
        updatePaginationSummary(pagination);
    } catch (error) {
        console.error('Error al cargar regiones', error);
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
    state.editingCode = null;
    dom.modalTitle.textContent = 'Nueva región';
    dom.modalSubmit.textContent = 'Guardar región';
    dom.modalSubmit.disabled = false;
    dom.form.reset();
    dom.fields.activo.checked = true;
    dom.fields.codigo.disabled = false;
}

function formatJsonTextarea(value) {
    if (value === null || value === undefined) return '';
    if (typeof value === 'string') {
        const trimmed = value.trim();
        if (!trimmed || trimmed === '{}' || trimmed === '[]') return '';
        try {
            const parsed = JSON.parse(trimmed);
            if (typeof parsed === 'object' && parsed !== null) {
                if (!Array.isArray(parsed) && Object.keys(parsed).length === 0) return '';
                if (Array.isArray(parsed) && !parsed.length) return '';
                return JSON.stringify(parsed, null, 2);
            }
        } catch (_) {
            return trimmed;
        }
        return trimmed;
    }
    if (typeof value === 'object') {
        if (Array.isArray(value) && !value.length) return '';
        if (!Array.isArray(value) && Object.keys(value).length === 0) return '';
        return JSON.stringify(value, null, 2);
    }
    return '';
}

function fillFormFromData(data) {
    dom.fields.codigo.value = data.codigo ?? '';
    dom.fields.nombre.value = data.nombre ?? '';
    dom.fields.pais_codigo_iso.value = data.pais_codigo_iso ?? '';
    dom.fields.tipo.value = data.tipo ?? '';
    dom.fields.geom_wkt.value = data.geom_wkt ?? '';
    dom.fields.metadata.value = formatJsonTextarea(data.metadata ?? null);
    dom.fields.activo.checked = data.activo !== false;
}

function parseMetadata(value) {
    if (!value || value.trim() === '') return null;
    try {
        const parsed = JSON.parse(value);
        if (typeof parsed !== 'object' || Array.isArray(parsed)) {
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
        if (!validatePolygon()) {
            dom.modalSubmit.disabled = false;
            return;
        }
        const payload = {
            codigo: dom.fields.codigo.value.trim().toUpperCase(),
            nombre: dom.fields.nombre.value.trim(),
            pais_codigo_iso: dom.fields.pais_codigo_iso.value.trim().toUpperCase(),
            tipo: dom.fields.tipo.value.trim(),
            geom_wkt: dom.fields.geom_wkt.value.trim() || null,
            activo: dom.fields.activo.checked
        };

        if (!payload.codigo) throw new Error('El código es obligatorio');
        if (!payload.nombre) throw new Error('El nombre es obligatorio');

        if (dom.fields.metadata.value.trim() !== '') {
            payload.metadata = parseMetadata(dom.fields.metadata.value);
        }

        const isEdit = state.mode === 'edit' && state.editingCode !== null;
        let response;

        if (isEdit) {
            response = await apiClient.put('regions-update.php', payload);
        } else {
            response = await apiClient.post('regions-create.php', payload);
        }

        if (!response.success) {
            throw new Error(response.error || (isEdit ? 'No se pudo actualizar la región' : 'No se pudo crear la región'));
        }

        const prevCode = state.editingCode;
        closeModal();
        await loadRegions(true);

        const targetCode = response.data?.codigo ?? prevCode;
        const targetRow = targetCode ? dom.tableBody.querySelector(`tr[data-code="${targetCode}"]`) : null;
        animation.flashRow(targetRow);
        showToast(isEdit ? 'Región actualizada correctamente' : 'Región creada correctamente');
    } catch (error) {
        console.error('Error al guardar región', error);
        showToast(error.message || 'No se pudo guardar la región', true);
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

function startCreateRegion() {
    state.mode = 'create';
    state.editingCode = null;
    dom.modalTitle.textContent = 'Nueva región';
    dom.modalSubmit.textContent = 'Guardar región';
    dom.modalSubmit.disabled = false;
    dom.form.reset();
    dom.fields.activo.checked = true;
    dom.fields.codigo.disabled = false;
    openModal(dom.fields.codigo);
    setTimeout(() => {
        initMap();
        drawPolygonFromWkt('');
    }, 100);
}

async function startEditRegion(codigo) {
    try {
        state.mode = 'edit';
        state.editingCode = codigo;
        dom.modalTitle.textContent = 'Editar región';
        dom.modalSubmit.textContent = 'Guardar cambios';
        dom.modalSubmit.disabled = true;

        const response = await apiClient.get(`regions-detail.php?codigo=${encodeURIComponent(codigo)}`);
        if (!response.success || !response.data) {
            throw new Error(response.error || 'No se encontraron datos de la región');
        }

        dom.form.reset();
        fillFormFromData(response.data);
        dom.fields.codigo.disabled = true;
        dom.modalSubmit.disabled = false;
        openModal(dom.fields.nombre);
        setTimeout(() => {
            initMap();
            drawPolygonFromWkt(response.data.geom_wkt || '');
        }, 120);
    } catch (error) {
        console.error('Error al cargar región', error);
        showToast(error.message || 'No se pudo cargar la región', true);
        state.mode = 'create';
        state.editingCode = null;
        dom.fields.codigo.disabled = false;
        dom.modalSubmit.disabled = false;
    }
}

async function toggleRegion(codigo, isActive, button) {
    const nextState = !isActive;
    const confirmation = window.confirm(nextState ? '¿Activar esta región?' : '¿Inactivar esta región?');
    if (!confirmation) return;

    try {
        if (button) button.disabled = true;
        const response = await apiClient.post('regions-toggle.php', {
            codigo,
            activo: nextState
        });

        if (!response.success) {
            throw new Error(response.error || 'No se pudo actualizar el estado');
        }

        await loadRegions(true);
        const row = dom.tableBody.querySelector(`tr[data-code="${codigo}"]`);
        animation.flashRow(row);
        showToast(nextState ? 'Región activada' : 'Región inactivada');
    } catch (error) {
        console.error('Error al cambiar estado de región', error);
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
        startEditRegion(codigo);
    } else if (action === 'toggle') {
        const isActive = button.dataset.active === '1';
        toggleRegion(codigo, isActive, button);
    }
}

let debounceTimer;
function debounceLoad() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => loadRegions(), 350);
}

function attachListeners() {
    dom.filterSearch.addEventListener('input', (event) => {
        state.search = event.target.value.trim();
        state.page = 1;
        debounceLoad();
    });

    dom.filterCountry.addEventListener('input', (event) => {
        state.country = event.target.value.trim().toUpperCase();
        state.page = 1;
        loadRegions();
    });

    dom.filterType.addEventListener('change', (event) => {
        state.type = event.target.value.trim();
        state.page = 1;
        loadRegions();
    });

    dom.filterActive.addEventListener('change', (event) => {
        state.active = event.target.value;
        state.page = 1;
        loadRegions();
    });

    dom.btnClearFilters.addEventListener('click', () => {
        dom.filterSearch.value = '';
        dom.filterCountry.value = '';
        dom.filterType.value = '';
        dom.filterActive.value = '';
        state.search = '';
        state.country = '';
        state.type = '';
        state.active = '';
        state.page = 1;
        loadRegions();
    });

    dom.btnPrev.addEventListener('click', () => {
        if (state.page > 1 && !state.loading) {
            state.page -= 1;
            loadRegions();
        }
    });

    dom.btnNext.addEventListener('click', () => {
        if (state.page < state.totalPages && !state.loading) {
            state.page += 1;
            loadRegions();
        }
    });

    dom.btnNewRegion.addEventListener('click', () => {
        startCreateRegion();
    });

    dom.modalClose.addEventListener('click', closeModal);
    document.getElementById('formCancel').addEventListener('click', closeModal);
    dom.form.addEventListener('submit', handleFormSubmit);
    dom.tableBody.addEventListener('click', handleTableClick);
    dom.modal.addEventListener('click', (event) => {
        if (event.target === dom.modal) {
            closeModal();
        }
    });
    dom.btnLoadWkt.addEventListener('click', () => {
        initMap();
        drawPolygonFromWkt(dom.fields.geom_wkt.value);
    });
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
