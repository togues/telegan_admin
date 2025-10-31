// Users listing with filters and pagination
import { AppConfig } from '../../js/config.js';
import { ApiClient } from '../../js/ApiClient.js';

const state = {
  q: '',
  activo: '',
  page: 1,
  pageSize: 20,
  totalPages: 1
};

const els = {
  q: document.getElementById('q'),
  activo: document.getElementById('activo'),
  prev: document.getElementById('prev'),
  next: document.getElementById('next'),
  pageInfo: document.getElementById('pageInfo'),
  tbody: document.getElementById('tbody')
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
    els.tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; color: var(--text-secondary); padding: 16px;">Cargando...</td></tr>`;
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
  } catch (e) {
    console.error('Error loading users:', e);
    els.tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; color: var(--error-color); padding: 16px;">Error al cargar usuarios: ${e.message || 'Error desconocido'}</td></tr>`;
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
    els.tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; color: var(--text-secondary); padding: 16px;">Sin resultados</td></tr>`;
    return;
  }
  const html = rows.map(r => {
    const dot = r.activo ? 'dot-on' : 'dot-off';
    return `
      <tr>
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
}

function debounce(fn, ms) {
  let t; return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

// Events
els.q.addEventListener('input', debounce(e => { state.q = e.target.value.trim(); state.page = 1; loadUsers(); }, 300));
els.activo.addEventListener('change', e => { state.activo = e.target.value; state.page = 1; loadUsers(); });
els.prev.addEventListener('click', () => { if (state.page > 1) { state.page--; loadUsers(); } });
els.next.addEventListener('click', () => { if (state.page < state.totalPages) { state.page++; loadUsers(); } });

// Init
loadUsers();


