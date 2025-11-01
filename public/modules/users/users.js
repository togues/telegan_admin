// Users listing with filters and pagination
import { AppConfig } from '../../js/config.js';
import { ApiClient } from '../../js/ApiClient.js';

const state = {
  q: '',
  activo: '',
  codigo: '',
  fechaDesde: '',
  fechaHasta: '',
  page: 1,
  pageSize: 20,
  totalPages: 1,
  selectedIds: new Set()
};

const els = {
  q: document.getElementById('q'),
  activo: document.getElementById('activo'),
  codigo: document.getElementById('codigo'),
  fechaDesde: document.getElementById('fecha_desde'),
  fechaHasta: document.getElementById('fecha_hasta'),
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
  if (state.codigo) params.set('codigo', state.codigo);
  if (state.fechaDesde) params.set('fecha_desde', state.fechaDesde);
  if (state.fechaHasta) params.set('fecha_hasta', state.fechaHasta);
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
    // Generar iniciales para el avatar
    const initials = r.nombre_completo ? r.nombre_completo.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase() : 'U';
    // Determinar estado del usuario
    let estadoUsuario = 'Activo';
    if (!r.activo) {
      estadoUsuario = 'Inactivo';
    }
    
    // Guardar datos del usuario en un atributo data para acceso seguro
    // Usar encodeURIComponent para escapar caracteres especiales en JSON
    const userDataAttr = encodeURIComponent(JSON.stringify(r));
    
    return `
      <tr>
        <td><input type="checkbox" class="user-checkbox" value="${r.id_usuario}" ${isChecked}></td>
        <td>${escapeHtml(r.nombre_completo)}</td>
        <td>${escapeHtml(r.email || '')}</td>
        <td>${escapeHtml(r.telefono || '')}</td>
        <td><span class="status-dot ${dot}"></span>${r.activo ? 'Activo' : 'Inactivo'}</td>
        <td>${fmtDate(r.fecha_registro)}</td>
        <td>${fmtDate(r.ultima_sesion)}</td>
        <td>${escapeHtml(r.codigo_telegan || '')}</td>
        <td>
          <button class="btn-view-user" data-user-id="${r.id_usuario}" data-user-data='${userDataAttr}' title="Ver perfil completo">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
              <circle cx="12" cy="12" r="3"></circle>
            </svg>
          </button>
        </td>
      </tr>`;
  }).join('');
  els.tbody.innerHTML = html;
  
  // Agregar event listeners a los checkboxes
  document.querySelectorAll('.user-checkbox').forEach(cb => {
    cb.addEventListener('change', handleCheckboxChange);
  });
  
  // Agregar event listeners a los botones de ver perfil
  document.querySelectorAll('.btn-view-user').forEach(btn => {
    btn.addEventListener('click', function() {
      const userId = parseInt(this.getAttribute('data-user-id'));
      const userDataStr = this.getAttribute('data-user-data');
      let userData = null;
      try {
        // Decodificar los datos que fueron codificados con encodeURIComponent
        userData = JSON.parse(decodeURIComponent(userDataStr));
      } catch (e) {
        console.error('Error parseando datos del usuario:', e);
      }
      viewUserProfile(userId, userData);
    });
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

// Función para limpiar filtros
function clearFilters() {
  state.q = '';
  state.codigo = '';
  state.fechaDesde = '';
  state.fechaHasta = '';
  state.activo = '';
  state.page = 1;
  
  if (els.q) els.q.value = '';
  if (els.codigo) els.codigo.value = '';
  if (els.fechaDesde) els.fechaDesde.value = '';
  if (els.fechaHasta) els.fechaHasta.value = '';
  if (els.activo) els.activo.value = '';
  
  loadUsers();
}

// Events
els.q.addEventListener('input', debounce(e => { state.q = e.target.value.trim(); state.page = 1; loadUsers(); }, 300));
els.codigo.addEventListener('input', debounce(e => { state.codigo = e.target.value.trim(); state.page = 1; loadUsers(); }, 300));
els.fechaDesde.addEventListener('change', e => { state.fechaDesde = e.target.value; state.page = 1; loadUsers(); });
els.fechaHasta.addEventListener('change', e => { state.fechaHasta = e.target.value; state.page = 1; loadUsers(); });
els.activo.addEventListener('change', e => { state.activo = e.target.value; state.page = 1; loadUsers(); });
els.prev.addEventListener('click', () => { if (state.page > 1) { state.page--; loadUsers(); } });
els.next.addEventListener('click', () => { if (state.page < state.totalPages) { state.page++; loadUsers(); } });

// Botón limpiar filtros
const clearBtn = document.getElementById('clearFilters');
if (clearBtn) {
  clearBtn.addEventListener('click', clearFilters);
}

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

// ===================================
// User Profile Modal Functions
// ===================================

/**
 * Ver perfil de usuario completo
 */
window.viewUserProfile = async function(userId, userData = null) {
  const modal = document.getElementById('user-profile-modal');
  if (!modal) {
    console.error('Modal de perfil no encontrado');
    return;
  }

  // Si no tenemos los datos del usuario, cargarlos
  let user = userData;
  if (!user) {
    try {
      const api = new ApiClient();
      // Obtener datos del usuario desde la lista actual
      const response = await api.get(`api/users-list.php?page=1&page_size=1000&q=&activo=`);
      if (response.success && response.data) {
        user = response.data.find(u => u.id_usuario === userId);
      }
    } catch (error) {
      console.error('Error al cargar datos del usuario:', error);
      alert('Error al cargar datos del usuario');
      return;
    }
  }

  if (!user) {
    alert('Usuario no encontrado');
    return;
  }

  // Preparar datos del usuario para el perfil
  const profileData = {
    id_usuario: user.id_usuario,
    nombre_completo: user.nombre_completo || '',
    email: user.email || '',
    telefono: user.telefono || '',
    ubicacion_general: user.ubicacion_general || '',
    fecha_registro: user.fecha_registro || '',
    ultima_sesion: user.ultima_sesion || '',
    codigo_telegan: user.codigo_telegan || '',
    activo: user.activo || false,
    estado_usuario: user.activo ? 'Activo' : 'Inactivo'
  };

  // Generar iniciales
  profileData.initials = profileData.nombre_completo
    ? profileData.nombre_completo.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase()
    : 'U';

  // Obtener país si hay ubicación
  // Por ahora usamos ubicacion_general como fallback
  profileData.nombre_pais = profileData.ubicacion_general || 'Ubicación no especificada';

  // Poblar el modal con los datos
  populateUserProfile(profileData);

  // Mostrar modal
  modal.style.display = 'flex';
  setTimeout(() => {
    modal.classList.add('show');
  }, 10);
};

/**
 * Cerrar modal de perfil
 */
window.closeUserProfileModal = function() {
  const modal = document.getElementById('user-profile-modal');
  if (modal) {
    modal.classList.remove('show');
    setTimeout(() => {
      modal.style.display = 'none';
    }, 300);
  }
};

/**
 * Poblar datos del perfil en el modal
 */
function populateUserProfile(user) {
  // Avatar e iniciales
  const initialsEl = document.getElementById('profile-initials');
  if (initialsEl) initialsEl.textContent = user.initials || 'U';

  // Información básica
  const nameEl = document.getElementById('profile-name');
  if (nameEl) nameEl.textContent = user.nombre_completo || 'Sin nombre';

  const locationEl = document.getElementById('profile-location');
  if (locationEl) locationEl.textContent = user.nombre_pais || user.ubicacion_general || 'Ubicación no especificada';

  const statusEl = document.getElementById('profile-status');
  if (statusEl) {
    statusEl.textContent = user.estado_usuario || 'Desconocido';
    statusEl.className = `status-badge ${getStatusClass(user.estado_usuario)}`;
  }

  // Detalles personales
  const emailEl = document.getElementById('profile-email');
  if (emailEl) emailEl.textContent = user.email || 'No especificado';

  const phoneEl = document.getElementById('profile-phone');
  if (phoneEl) phoneEl.textContent = user.telefono || 'No especificado';

  const ubicacionEl = document.getElementById('profile-ubicacion');
  if (ubicacionEl) ubicacionEl.textContent = user.ubicacion_general || 'No especificada';

  const fechaRegistroEl = document.getElementById('profile-fecha-registro');
  if (fechaRegistroEl) fechaRegistroEl.textContent = user.fecha_registro ? fmtDate(user.fecha_registro) : 'No especificada';

  const ultimaSesionEl = document.getElementById('profile-ultima-sesion');
  if (ultimaSesionEl) ultimaSesionEl.textContent = user.ultima_sesion ? fmtDate(user.ultima_sesion) : 'Nunca';

  const codigoEl = document.getElementById('profile-codigo');
  if (codigoEl) codigoEl.textContent = user.codigo_telegan || 'No asignado';

  // Cargar fincas del usuario
  loadUserFarms(user.id_usuario);
}

/**
 * Obtener clase CSS para estado
 */
function getStatusClass(status) {
  switch (status) {
    case 'Activo': return 'connected';
    case 'Inactivo': return 'error';
    default: return 'info';
  }
}

/**
 * Cargar fincas del usuario
 */
async function loadUserFarms(userId) {
  const farmsList = document.getElementById('farms-list');
  if (!farmsList) return;

  try {
    farmsList.innerHTML = `
      <div class="loading-farms">
        <div class="loading-spinner"></div>
        <p>Cargando fincas...</p>
      </div>
    `;

    const api = new ApiClient();
    const response = await api.get(`api/user-farms.php?user_id=${userId}`);

    if (response && response.success) {
      displayUserFarms(response.data || []);
    } else {
      showFarmsError('Error al cargar fincas');
    }
  } catch (error) {
    console.error('Error al cargar fincas:', error);
    showFarmsError('Error de conexión');
  }
}

/**
 * Mostrar fincas del usuario
 */
function displayUserFarms(farms) {
  const farmsList = document.getElementById('farms-list');
  if (!farmsList) return;

  if (farms.length === 0) {
    farmsList.innerHTML = `
      <div class="no-farms">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
          <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
        </svg>
        <p>Este agricultor no tiene fincas registradas</p>
      </div>
    `;
    return;
  }

  const farmsHTML = farms.map(farm => `
    <div class="farm-item">
      <div class="farm-info">
        <h5>${escapeHtml(farm.nombre_finca || 'Sin nombre')}</h5>
        <p>${escapeHtml(farm.display_info || '')}</p>
        <small>Rol: ${escapeHtml(farm.rol_text || 'N/A')} • Creada: ${escapeHtml(farm.fecha_creacion || 'N/A')}</small>
      </div>
      <div class="farm-status">
        <span class="status-badge ${farm.estado_class || 'info'}">${escapeHtml(farm.estado_text || 'Desconocido')}</span>
      </div>
    </div>
  `).join('');

  farmsList.innerHTML = farmsHTML;
}

/**
 * Mostrar error al cargar fincas
 */
function showFarmsError(message) {
  const farmsList = document.getElementById('farms-list');
  if (!farmsList) return;

  farmsList.innerHTML = `
    <div class="no-farms">
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
        <circle cx="12" cy="12" r="10"></circle>
        <line x1="15" y1="9" x2="9" y2="15"></line>
        <line x1="9" y1="9" x2="15" y2="15"></line>
      </svg>
      <p>${escapeHtml(message)}</p>
    </div>
  `;
}

// Cerrar modal con Escape
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    const modal = document.getElementById('user-profile-modal');
    if (modal && modal.classList.contains('show')) {
      closeUserProfileModal();
    }
  }
});

// Init
loadUsers();


