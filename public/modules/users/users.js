// Users listing with filters and pagination
import { AppConfig } from '../../js/config.js';
import { ApiClient } from '../../js/ApiClient.js';

const state = {
  q: '',
  activo: '',
  page: 1,
  pageSize: 20,
  totalPages: 1,
  selectedIds: new Set()
};

const els = {
  q: document.getElementById('q'),
  activo: document.getElementById('activo'),
  prev: document.getElementById('prev'),
  next: document.getElementById('next'),
  pageInfo: document.getElementById('pageInfo'),
  tbody: document.getElementById('tbody'),
  selectAll: document.getElementById('selectAll'),
  bulkActions: document.getElementById('bulkActions'),
  bulkBtnActivate: document.getElementById('bulkBtnActivate'),
  bulkBtnDeactivate: document.getElementById('bulkBtnDeactivate')
};

function buildUrl() {
  const params = new URLSearchParams();
  if (state.q) params.set('q', state.q);
  if (state.activo !== '') params.set('activo', state.activo);
  params.set('page', String(state.page));
  params.set('page_size', String(state.pageSize));
  
  // URL relativa que ApiClient convertirá a la ruta completa
  return `api/users-list.php?${params.toString()}`;
}

async function loadUsers() {
  try {
    els.tbody.innerHTML = `<tr><td colspan="9" style="text-align:center; color: var(--text-secondary); padding: 16px;">Cargando...</td></tr>`;
    const url = buildUrl();
    
    // Usar ApiClient que maneja URLs y autenticación correctamente
    const api = new ApiClient();
    const response = await api.get(url);
    
    if (!response.success) {
      throw new Error(response.error || 'Error al cargar usuarios');
    }
    
    renderRows(response.data || []);
    const pg = response.pagination || {};
    state.totalPages = pg.total_pages || 1;
    els.pageInfo.textContent = `Página ${pg.page || state.page} de ${state.totalPages} • ${pg.total || 0} usuarios`;
    els.prev.disabled = state.page <= 1;
    els.next.disabled = state.page >= state.totalPages;
    
    // Limpiar selección al cambiar de página
    state.selectedIds.clear();
    updateBulkUI();
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
    return;
  }
  const html = rows.map(r => {
    const dot = r.activo ? 'dot-on' : 'dot-off';
    const isChecked = state.selectedIds.has(r.id_usuario) ? 'checked' : '';
    return `
      <tr>
        <td><input type="checkbox" class="user-checkbox" value="${r.id_usuario}" ${isChecked}></td>
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
  
  // Agregar event listeners a los checkboxes
  document.querySelectorAll('.user-checkbox').forEach(cb => {
    cb.addEventListener('change', handleCheckboxChange);
  });
  
  updateSelectAllState();
}

function debounce(fn, ms) {
  let t; return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

// Bulk operations functions
function handleCheckboxChange(e) {
  const id = parseInt(e.target.value);
  if (e.target.checked) {
    state.selectedIds.add(id);
  } else {
    state.selectedIds.delete(id);
  }
  updateBulkUI();
  updateSelectAllState();
}

function updateSelectAllState() {
  if (!els.selectAll) return;
  const checkboxes = document.querySelectorAll('.user-checkbox');
  const checkedCount = state.selectedIds.size;
  // Actualizar texto del botón según estado
  if (checkboxes.length === 0) {
    els.selectAll.textContent = 'Marcar todos';
  } else if (checkedCount === checkboxes.length) {
    els.selectAll.textContent = 'Desmarcar todos';
  } else if (checkedCount > 0) {
    els.selectAll.textContent = `Marcar todos (${checkedCount} seleccionados)`;
  } else {
    els.selectAll.textContent = 'Marcar todos';
  }
}

function handleSelectAll(e) {
  const checkboxes = document.querySelectorAll('.user-checkbox');
  const allChecked = checkboxes.length > 0 && Array.from(checkboxes).every(cb => cb.checked);
  
  checkboxes.forEach(cb => {
    const id = parseInt(cb.value);
    cb.checked = !allChecked;
    if (!allChecked) {
      state.selectedIds.add(id);
    } else {
      state.selectedIds.delete(id);
    }
  });
  updateBulkUI();
}

function updateBulkUI() {
  if (!els.bulkActions) return;
  const count = state.selectedIds.size;
  els.bulkActions.style.display = count > 0 ? 'flex' : 'none';
  updateSelectAllState();
}

async function performBulkOperation(action) {
  if (state.selectedIds.size === 0) {
    alert('No hay usuarios seleccionados');
    return;
  }
  
  if (!confirm(`¿Estás seguro de ${action === 'activate' ? 'activar' : 'desactivar'} ${state.selectedIds.size} usuario(s)?`)) {
    return;
  }
  
  try {
    const api = new ApiClient();
    const response = await api.post('api/users-bulk-operations.php', {
      ids: Array.from(state.selectedIds),
      action: action
    });
    
    if (response.success) {
      alert(response.message || 'Operación realizada correctamente');
      state.selectedIds.clear();
      updateBulkUI();
      loadUsers();
    } else {
      throw new Error(response.error || 'Error en la operación');
    }
  } catch (e) {
    console.error('Error in bulk operation:', e);
    alert('Error al realizar la operación: ' + e.message);
  }
}

// Events
els.q.addEventListener('input', debounce(e => { state.q = e.target.value.trim(); state.page = 1; loadUsers(); }, 300));
els.activo.addEventListener('change', e => { state.activo = e.target.value; state.page = 1; loadUsers(); });
els.prev.addEventListener('click', () => { if (state.page > 1) { state.page--; loadUsers(); } });
els.next.addEventListener('click', () => { if (state.page < state.totalPages) { state.page++; loadUsers(); } });

// Bulk operation events
if (els.selectAll) {
  els.selectAll.addEventListener('click', handleSelectAll);
}
if (els.bulkBtnActivate) {
  els.bulkBtnActivate.addEventListener('click', () => performBulkOperation('activate'));
}
if (els.bulkBtnDeactivate) {
  els.bulkBtnDeactivate.addEventListener('click', () => performBulkOperation('deactivate'));
}

// Init
loadUsers();


