// Users listing with filters, pagination and bulk operations
import { AppConfig } from '../../js/config.js';
import { ApiClient } from '../../js/ApiClient.js';

const state = {
  q: '',
  activo: '',
  page: 1,
  pageSize: 20,
  totalPages: 1,
  currentUsers: [],
  selectedIds: new Set()
};

const els = {
  q: document.getElementById('q'),
  activo: document.getElementById('activo'),
  prev: document.getElementById('prev'),
  next: document.getElementById('next'),
  pageInfo: document.getElementById('pageInfo'),
  tbody: document.getElementById('tbody'),
  bulkActionsBar: document.getElementById('bulkActionsBar'),
  selectAll: document.getElementById('selectAll'),
  headerCheckbox: document.getElementById('headerCheckbox'),
  selectedCount: document.getElementById('selectedCount'),
  bulkAction: document.getElementById('bulkAction'),
  applyBulkAction: document.getElementById('applyBulkAction')
};

function buildUrl() {
  const params = new URLSearchParams();
  if (state.q) params.set('q', state.q);
  if (state.activo !== '') params.set('activo', state.activo);
  params.set('page', String(state.page));
  params.set('page_size', String(state.pageSize));
  return `api/users-list.php?${params.toString()}`;
}

async function loadUsers() {
  try {
    els.tbody.innerHTML = `<tr><td colspan="9" style="text-align:center; color: var(--text-secondary); padding: 16px;">Cargando...</td></tr>`;
    const url = buildUrl();

    const api = new ApiClient();
    const response = await api.get(url);

    if (!response.success) {
      throw new Error(response.error || 'Error al cargar usuarios');
    }

    state.currentUsers = response.data || [];

    // Limpiar selección al cambiar de página o filtros
    state.selectedIds.clear();
    updateBulkUI();

    renderRows(state.currentUsers);
    const pg = response.pagination || {};
    state.totalPages = pg.total_pages || 1;
    els.pageInfo.textContent = `Página ${pg.page || state.page} de ${state.totalPages} • ${pg.total || 0} usuarios`;
    els.prev.disabled = state.page <= 1;
    els.next.disabled = state.page >= state.totalPages;
  } catch (e) {
    console.error('Error loading users:', e);
    els.tbody.innerHTML = `<tr><td colspan="9" style="text-align:center; color: var(--error-color); padding: 16px;">Error al cargar usuarios: ${e.message || 'Error desconocido'}</td></tr>`;
  }
}

function escapeHtml(s) {
  if (s == null) return '';
  return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

function fmtDate(d) {
  if (!d) return '';
  try { return new Date(d).toLocaleString(); } catch (_) { return d; }
}

function renderRows(rows) {
  if (!rows.length) {
    els.tbody.innerHTML = `<tr><td colspan="9" style="text-align:center; color: var(--text-secondary); padding: 16px;">Sin resultados</td></tr>`;
    els.bulkActionsBar.style.display = 'none';
    return;
  }

  els.bulkActionsBar.style.display = 'flex';

  const html = rows.map(r => {
    const dot = r.activo ? 'dot-on' : 'dot-off';
    const checked = state.selectedIds.has(r.id_usuario) ? 'checked' : '';
    return `
      <tr>
        <td>
          <input
            type="checkbox"
            class="user-checkbox"
            data-user-id="${r.id_usuario}"
            ${checked}
          />
        </td>
        <td>${r.id_usuario}</td>
        <td>${escapeHtml(r.nombre_completo)}</td>
        <td>${escapeHtml(r.email || '')}</td>
        <td>${escapeHtml(r.telefono || '')}</td>
        <td><span class="status-dot ${dot}"></span>${r.activo ? 'Activo' : 'Inactivo'}</td>
        <td>${fmtDate(r.fecha_registro)}</td>
        <td>${fmtDate(r.ultima_sesion)}</td>
        <td>${escapeHtml(r.codigo_telegan || '')}</td>
      </tr>`;
  }).join('');
  els.tbody.innerHTML = html;

  // Agregar event listeners a los checkboxes individuales
  attachCheckboxListeners();
}

function attachCheckboxListeners() {
  const checkboxes = document.querySelectorAll('.user-checkbox');
  checkboxes.forEach(cb => {
    cb.addEventListener('change', handleCheckboxChange);
  });
}

function handleCheckboxChange(e) {
  const userId = parseInt(e.target.dataset.userId);

  if (e.target.checked) {
    state.selectedIds.add(userId);
  } else {
    state.selectedIds.delete(userId);
  }

  updateBulkUI();
}

function updateBulkUI() {
  const count = state.selectedIds.size;
  els.selectedCount.textContent = `${count} seleccionado${count !== 1 ? 's' : ''}`;

  // Actualizar estado del checkbox "Seleccionar todos"
  const currentPageIds = state.currentUsers.map(u => u.id_usuario);
  const allCurrentSelected = currentPageIds.length > 0 &&
    currentPageIds.every(id => state.selectedIds.has(id));

  if (els.selectAll) els.selectAll.checked = allCurrentSelected;
  if (els.headerCheckbox) els.headerCheckbox.checked = allCurrentSelected;

  // Habilitar/deshabilitar botón de aplicar
  els.applyBulkAction.disabled = count === 0;
}

function handleSelectAll(checked) {
  const currentPageIds = state.currentUsers.map(u => u.id_usuario);

  if (checked) {
    // Seleccionar SOLO los usuarios visibles en pantalla
    currentPageIds.forEach(id => state.selectedIds.add(id));
  } else {
    // Deseleccionar SOLO los usuarios visibles en pantalla
    currentPageIds.forEach(id => state.selectedIds.delete(id));
  }

  updateBulkUI();

  // Actualizar checkboxes en la tabla
  const checkboxes = document.querySelectorAll('.user-checkbox');
  checkboxes.forEach(cb => {
    const userId = parseInt(cb.dataset.userId);
    cb.checked = state.selectedIds.has(userId);
  });
}

async function applyBulkAction() {
  const action = els.bulkAction.value;

  if (!action) {
    alert('Por favor selecciona una acción');
    return;
  }

  if (state.selectedIds.size === 0) {
    alert('No hay usuarios seleccionados');
    return;
  }

  const actionText = action === 'activate' ? 'activar' : 'desactivar';
  const confirmed = confirm(
    `¿Estás seguro de que deseas ${actionText} ${state.selectedIds.size} usuario(s)?\n\n` +
    `Esta acción afectará únicamente a los usuarios seleccionados.`
  );

  if (!confirmed) return;

  // Deshabilitar controles durante la operación
  els.applyBulkAction.disabled = true;
  els.applyBulkAction.textContent = 'Procesando...';
  els.bulkAction.disabled = true;

  try {
    const api = new ApiClient();
    const response = await api.post('api/bulk-update-users.php', {
      user_ids: Array.from(state.selectedIds),
      action: action
    });

    if (!response.success) {
      throw new Error(response.error || 'Error al procesar la operación');
    }

    // Mostrar resultado
    alert(response.message || 'Operación completada exitosamente');

    // Limpiar selección y recargar
    state.selectedIds.clear();
    els.bulkAction.value = '';
    await loadUsers();

  } catch (e) {
    console.error('Error applying bulk action:', e);
    alert(`Error: ${e.message || 'No se pudo completar la operación'}`);
  } finally {
    // Re-habilitar controles
    els.applyBulkAction.disabled = false;
    els.applyBulkAction.textContent = 'Aplicar';
    els.bulkAction.disabled = false;
  }
}

function debounce(fn, ms) {
  let t; return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

// Events - Filters
els.q.addEventListener('input', debounce(e => {
  state.q = e.target.value.trim();
  state.page = 1;
  loadUsers();
}, 300));

els.activo.addEventListener('change', e => {
  state.activo = e.target.value;
  state.page = 1;
  loadUsers();
});

// Events - Pagination
els.prev.addEventListener('click', () => {
  if (state.page > 1) {
    state.page--;
    loadUsers();
  }
});

els.next.addEventListener('click', () => {
  if (state.page < state.totalPages) {
    state.page++;
    loadUsers();
  }
});

// Events - Bulk Operations
els.selectAll?.addEventListener('change', e => {
  handleSelectAll(e.target.checked);
});

els.headerCheckbox?.addEventListener('change', e => {
  handleSelectAll(e.target.checked);
});

els.applyBulkAction.addEventListener('click', applyBulkAction);

// Habilitar botón cuando se selecciona una acción
els.bulkAction.addEventListener('change', () => {
  if (els.bulkAction.value && state.selectedIds.size > 0) {
    els.applyBulkAction.disabled = false;
  }
});

// Init
loadUsers();
