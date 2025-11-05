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
  selectedIds: new Set(),
  sortBy: 'fecha_registro',
  sortOrder: 'DESC'
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
  bulkBtnDeactivate: document.getElementById('bulkBtnDeactivate'),
  downloadUsers: document.getElementById('downloadUsers')
};

function buildUrl(includePagination = true) {
  const params = new URLSearchParams();
  if (state.q) params.set('q', state.q);
  if (state.codigo) params.set('codigo', state.codigo);
  if (state.fechaDesde) params.set('fecha_desde', state.fechaDesde);
  if (state.fechaHasta) params.set('fecha_hasta', state.fechaHasta);
  if (state.activo !== '') params.set('activo', state.activo);
  
  // Parámetros de ordenamiento
  params.set('sort_by', state.sortBy);
  params.set('sort_order', state.sortOrder);
  
  if (includePagination) {
    params.set('page', String(state.page));
    params.set('page_size', String(state.pageSize));
  } else {
    // Para descarga, obtener todos los resultados (sin paginación)
    params.set('page', '1');
    params.set('page_size', '10000'); // Número grande para obtener todos
  }
  
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

function fmtDateOnly(d) {
  if (!d) return '';
  try { 
    const date = new Date(d);
    return date.toLocaleDateString('es-ES', { year: 'numeric', month: '2-digit', day: '2-digit' });
  } catch (_) { 
    // Si falla, intentar solo la parte de fecha si viene en formato ISO
    if (typeof d === 'string' && d.includes('T')) {
      return d.split('T')[0].split('-').reverse().join('/');
    }
    return d; 
  }
}

function renderRows(rows) {
  if (!rows.length) {
    els.tbody.innerHTML = `<tr><td colspan="10" style="text-align:center; color: var(--text-secondary); padding: 16px;">Sin resultados</td></tr>`;
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
        <td style="text-align: center;">
          <input type="checkbox" class="user-checkbox" value="${r.id_usuario}" ${isChecked}>
        </td>
        <td style="text-align: center;">
          <button class="btn-view-user" data-user-id="${r.id_usuario}" data-user-data='${userDataAttr}' title="Ver perfil completo">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
              <circle cx="12" cy="12" r="3"></circle>
            </svg>
          </button>
        </td>
        <td style="text-align: center;">
          <button class="btn-edit-user" data-user-id="${r.id_usuario}" data-user-data='${userDataAttr}' title="Editar usuario">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
              <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
            </svg>
          </button>
        </td>
        <td>${escapeHtml(r.nombre_completo)}</td>
        <td>${escapeHtml(r.email || '')}</td>
        <td>${escapeHtml(r.telefono || '')}</td>
        <td><span class="status-dot ${dot}"></span>${r.activo ? 'Activo' : 'Inactivo'}</td>
        <td>${fmtDateOnly(r.fecha_registro)}</td>
        <td>${fmtDate(r.ultima_sesion)}</td>
        <td>${escapeHtml(r.codigo_telegan || '')}</td>
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
  
  // Agregar event listeners a los botones de editar
  document.querySelectorAll('.btn-edit-user').forEach(btn => {
    btn.addEventListener('click', function() {
      const userId = parseInt(this.getAttribute('data-user-id'));
      const userDataStr = this.getAttribute('data-user-data');
      let userData = null;
      try {
        userData = JSON.parse(decodeURIComponent(userDataStr));
      } catch (e) {
        console.error('Error parseando datos del usuario:', e);
      }
      editUser(userId, userData);
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

/**
 * Descargar usuarios filtrados en CSV/Excel
 */
async function downloadUsers() {
  // Mostrar advertencia sobre qué se descargará
  const activeFilters = [];
  if (state.q) activeFilters.push(`Búsqueda: "${state.q}"`);
  if (state.codigo) activeFilters.push(`Código: "${state.codigo}"`);
  if (state.fechaDesde) activeFilters.push(`Desde: ${state.fechaDesde}`);
  if (state.fechaHasta) activeFilters.push(`Hasta: ${state.fechaHasta}`);
  if (state.activo === '1') activeFilters.push('Estado: Activos');
  if (state.activo === '0') activeFilters.push('Estado: Inactivos');
  
  const filterInfo = activeFilters.length > 0 
    ? `\n\nFiltros aplicados:\n${activeFilters.join('\n')}` 
    : '\n\n⚠️ Se descargarán TODOS los usuarios (sin filtros aplicados)';
  
  if (!confirm(`¿Descargar usuarios filtrados?${filterInfo}\n\nEl archivo contendrá los datos actualmente visibles según los filtros aplicados.`)) {
    return;
  }

  try {
    // Mostrar loading
    if (els.downloadUsers) {
      els.downloadUsers.disabled = true;
      els.downloadUsers.innerHTML = `
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation: spin 1s linear infinite;">
          <circle cx="12" cy="12" r="10" stroke-dasharray="32" stroke-dashoffset="32">
            <animate attributeName="stroke-dasharray" dur="2s" values="0 32;16 16;0 32;0 32" repeatCount="indefinite"/>
            <animate attributeName="stroke-dashoffset" dur="2s" values="0;-16;-32;-32" repeatCount="indefinite"/>
          </circle>
        </svg>
        Descargando...
      `;
    }

    // Obtener todos los datos filtrados (sin paginación)
    const api = new ApiClient();
    const url = buildUrl(false); // Sin paginación
    const response = await api.get(url);
    
    if (!response.success) {
      throw new Error(response.error || 'Error al obtener datos para descarga');
    }

    const users = response.data || [];
    
    if (users.length === 0) {
      alert('No hay usuarios para descargar con los filtros aplicados.');
      return;
    }

    // Preparar datos para exportación
    const exportData = users.map(user => ({
      'ID': user.id_usuario,
      'Nombre Completo': user.nombre_completo || '',
      'Email': user.email || '',
      'Teléfono': user.telefono || '',
      'Estado': user.activo ? 'Activo' : 'Inactivo',
      'Email Verificado': user.email_verificado ? 'Sí' : 'No',
      'Teléfono Verificado': user.telefono_verificado ? 'Sí' : 'No',
      'Fecha Registro': user.fecha_registro ? fmtDateOnly(user.fecha_registro) : '',
      'Última Sesión': user.ultima_sesion ? fmtDate(user.ultima_sesion) : 'Nunca',
      'Código Telegan': user.codigo_telegan || '',
      'Ubicación General': user.ubicacion_general || ''
    }));

    // Crear libro de trabajo Excel
    const ws = XLSX.utils.json_to_sheet(exportData);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Usuarios');

    // Agregar información de filtros como segunda hoja (opcional)
    if (activeFilters.length > 0) {
      const filterData = [{ 'Filtros Aplicados': activeFilters.join('; ') }];
      const filterWs = XLSX.utils.json_to_sheet(filterData);
      XLSX.utils.book_append_sheet(wb, filterWs, 'Filtros');
    }

    // Generar nombre de archivo con fecha y filtros
    const dateStr = new Date().toISOString().split('T')[0];
    const fileName = `usuarios_telegan_${dateStr}.xlsx`;

    // Descargar archivo Excel
    XLSX.writeFile(wb, fileName);

    // También generar CSV (alternativo)
    // const csv = XLSX.utils.sheet_to_csv(ws);
    // const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    // const csvFileName = `usuarios_telegan_${dateStr}.csv`;
    // const link = document.createElement('a');
    // link.href = URL.createObjectURL(blob);
    // link.download = csvFileName;
    // link.click();

    alert(`✅ ${users.length} usuario(s) descargado(s) exitosamente.\n\nArchivo: ${fileName}`);
  } catch (error) {
    console.error('Error al descargar usuarios:', error);
    alert('Error al descargar usuarios: ' + error.message);
  } finally {
    // Restaurar botón
    if (els.downloadUsers) {
      els.downloadUsers.disabled = false;
      els.downloadUsers.innerHTML = `
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
          <polyline points="7 10 12 15 17 10"></polyline>
          <line x1="12" y1="15" x2="12" y2="3"></line>
        </svg>
        Descargar Usuarios
      `;
    }
  }
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

// Botón descargar usuarios
if (els.downloadUsers) {
  els.downloadUsers.addEventListener('click', downloadUsers);
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
 * Mostrar fincas del usuario (clickeables para abrir modal)
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

  const farmsHTML = farms.map(farm => {
    const farmDataAttr = encodeURIComponent(JSON.stringify(farm));
    return `
      <div class="farm-item" style="cursor: pointer;" data-farm-id="${farm.id_finca}" data-farm-data='${farmDataAttr}'>
        <div class="farm-info">
          <h5>${escapeHtml(farm.nombre_finca || 'Sin nombre')}</h5>
          <p>${escapeHtml(farm.display_info || '')}</p>
          <small>Rol: ${escapeHtml(farm.rol_text || 'N/A')} • Creada: ${escapeHtml(farm.fecha_creacion || 'N/A')}</small>
        </div>
        <div class="farm-status">
          <span class="status-badge ${farm.estado_class || 'info'}">${escapeHtml(farm.estado_text || 'Desconocido')}</span>
        </div>
      </div>
    `;
  }).join('');

  farmsList.innerHTML = farmsHTML;

  // Agregar event listeners a las fincas para abrir modal
  document.querySelectorAll('.farm-item').forEach(item => {
    item.addEventListener('click', function() {
      const farmId = parseInt(this.getAttribute('data-farm-id'));
      const farmDataStr = this.getAttribute('data-farm-data');
      let farmData = null;
      try {
        farmData = JSON.parse(decodeURIComponent(farmDataStr));
      } catch (e) {
        console.error('Error parseando datos de finca:', e);
      }
      if (farmData) {
        showFarmModalFromUserProfile(farmId, farmData);
      }
    });
  });
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

// ===================================
// Farm Modal Functions (Anidado)
// ===================================

/**
 * Mostrar modal de finca desde el perfil de usuario
 */
window.showFarmModalFromUserProfile = async function(farmId, farmDataBasic = null) {
  const farmModal = document.getElementById('farm-modal-nested');
  if (!farmModal) {
    console.error('Modal de finca no encontrado');
    return;
  }

  // Mostrar loading
  document.getElementById('farm-nested-name').textContent = 'Cargando...';
  document.getElementById('farm-nested-location').textContent = 'Obteniendo datos...';
  
  // Mostrar modal PRIMERO
  farmModal.style.display = 'flex';
  
  // Esperar a que el modal esté visible antes de continuar
  await new Promise(resolve => {
    requestAnimationFrame(() => {
      farmModal.classList.add('show');
      // Dar tiempo adicional para que el navegador renderice el modal
      setTimeout(resolve, 300);
    });
  });

  // Cargar datos completos de la finca
  try {
    const api = new ApiClient();
    const response = await api.get(`api/farm-details.php?farm_id=${farmId}`);

    if (response && response.success) {
      // Poblar datos del modal
      populateFarmModalNested(response.data);
      
      // IMPORTANTE: Inicializar mapa DESPUÉS de que el modal esté visible
      // Esperar un frame más para asegurar que el contenedor tiene dimensiones
      requestAnimationFrame(() => {
        requestAnimationFrame(() => {
          if (response.data.farm) {
            initializeFarmMapNested(response.data.farm);
          }
        });
      });
    } else {
      throw new Error(response.error || 'Error al cargar datos de finca');
    }
  } catch (error) {
    console.error('Error al cargar finca:', error);
    document.getElementById('farm-nested-name').textContent = 'Error al cargar';
    document.getElementById('farm-nested-location').textContent = 'No se pudieron cargar los datos';
  }
};

/**
 * Cerrar modal de finca anidado
 */
window.closeFarmModalNested = function() {
  const farmModal = document.getElementById('farm-modal-nested');
  if (farmModal) {
    farmModal.classList.remove('show');
    setTimeout(() => {
      farmModal.style.display = 'none';
      // Limpiar mapa si existe
      cleanupNestedFarmMap();
    }, 300);
  }
};

/**
 * Poblar datos en el modal de finca anidado
 */
function populateFarmModalNested(data) {
  const { farm, administrators, collaborators, paddocks, stats } = data;

  // Información básica
  document.getElementById('farm-nested-title').textContent = farm.nombre_finca || 'Finca';
  document.getElementById('farm-nested-subtitle').textContent = (farm.display_info?.pais || '') + ' • ' + (farm.display_info?.area || '');
  document.getElementById('farm-nested-name').textContent = farm.nombre_finca || '-';
  document.getElementById('farm-nested-location').textContent = farm.nombre_pais || 'Ubicación no especificada';
  document.getElementById('farm-nested-status').textContent = farm.estado_text || '-';
  document.getElementById('farm-nested-status').className = `status-badge ${farm.estado_class || 'info'}`;
  document.getElementById('farm-nested-area').textContent = farm.display_info?.area || '-';

  // Detalles de la finca
  const detailsContainer = document.getElementById('farm-nested-details');
  if (detailsContainer) {
    detailsContainer.innerHTML = `
      <div class="detail-item">
        <label>Descripción</label>
        <span>${escapeHtml(farm.descripcion || 'No especificada')}</span>
      </div>
      <div class="detail-item">
        <label>Área Total</label>
        <span>${escapeHtml(farm.display_info?.area || 'N/A')}</span>
      </div>
      <div class="detail-item">
        <label>Estado</label>
        <span>${escapeHtml(farm.estado_text || 'N/A')}</span>
      </div>
      <div class="detail-item">
        <label>Fecha Creación</label>
        <span>${escapeHtml(farm.fecha_creacion || 'N/A')}</span>
      </div>
      <div class="detail-item">
        <label>Código Telegan</label>
        <span>${escapeHtml(farm.codigo_telegan || 'No asignado')}</span>
      </div>
      <div class="detail-item">
        <label>Creador</label>
        <span>${escapeHtml(farm.creador_nombre || 'No especificado')}</span>
      </div>
    `;
  }

  // Administradores
  populateFarmUsersList('farm-nested-admins', administrators || []);

  // Colaboradores
  populateFarmUsersList('farm-nested-collaborators', collaborators || []);

  // Potreros
  populateFarmPaddocksList('farm-nested-paddocks', paddocks || []);

  // NOTA: El mapa se inicializa DESPUÉS desde showFarmModalFromUserProfile
  // para asegurar que el modal esté visible antes de inicializar Leaflet
}

/**
 * Poblar lista de usuarios en el modal de finca
 */
function populateFarmUsersList(containerId, users) {
  const container = document.getElementById(containerId);
  if (!container) return;

  if (users.length === 0) {
    container.innerHTML = '<div class="no-users"><p>No hay usuarios registrados</p></div>';
    return;
  }

  const usersHTML = users.map(user => `
    <div class="user-item">
      <div class="user-avatar">${escapeHtml(user.initials || 'U')}</div>
      <div class="user-info">
        <h5>${escapeHtml(user.nombre_completo || 'Sin nombre')}</h5>
        <p>${escapeHtml(user.email || 'No especificado')} • ${escapeHtml(user.nombre_pais || user.ubicacion_general || 'Sin ubicación')}</p>
        <small>Asociado: ${escapeHtml(user.fecha_asociacion || 'N/A')}</small>
      </div>
    </div>
  `).join('');

  container.innerHTML = usersHTML;
}

/**
 * Poblar lista de potreros en el modal de finca
 */
function populateFarmPaddocksList(containerId, paddocks) {
  const container = document.getElementById(containerId);
  if (!container) return;

  if (paddocks.length === 0) {
    container.innerHTML = '<div class="no-paddocks"><p>No hay potreros registrados</p></div>';
    return;
  }

  const paddocksHTML = paddocks.map(paddock => `
    <div class="paddock-item">
      <div class="paddock-info">
        <h5>${escapeHtml(paddock.nombre_potrero || 'Sin nombre')}</h5>
        <p>${escapeHtml(paddock.display_info || '')}</p>
        <small>Creado: ${escapeHtml(paddock.fecha_creacion || 'N/A')} • Último registro: ${escapeHtml(paddock.ultimo_registro || 'N/A')}</small>
      </div>
      <div class="paddock-status">
        <span class="status-badge ${escapeHtml(paddock.estado_class || 'info')}">${escapeHtml(paddock.estado_text || 'Desconocido')}</span>
      </div>
    </div>
  `).join('');

  container.innerHTML = paddocksHTML;
}

// Variable global para el mapa anidado
let nestedFarmMap = null;

/**
 * Inicializar mapa de finca en modal anidado
 * IMPORTANTE: Esta función debe llamarse DESPUÉS de que el modal esté visible
 */
function initializeFarmMapNested(farm) {
  const mapContainer = document.getElementById('farm-nested-map');
  if (!mapContainer) {
    console.warn('Contenedor de mapa no encontrado');
    return;
  }

  // Verificar que el contenedor esté visible y tenga dimensiones
  const containerRect = mapContainer.getBoundingClientRect();
  if (containerRect.width === 0 || containerRect.height === 0) {
    console.warn('Contenedor de mapa no tiene dimensiones, reintentando...');
    // Reintentar después de un breve delay
    setTimeout(() => initializeFarmMapNested(farm), 100);
    return;
  }

  // Limpiar mapa anterior
  if (nestedFarmMap) {
    try {
      nestedFarmMap.remove();
      nestedFarmMap = null;
    } catch (e) {
      console.warn('Error limpiando mapa anterior:', e);
      nestedFarmMap = null;
    }
  }

  mapContainer.innerHTML = '';

  // Verificar si tiene geometría
  if (!farm.geometria_wkt && !farm.geometria_postgis && !farm.geojson) {
    mapContainer.innerHTML = `
      <div class="map-no-geometry">
        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
          <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
          <circle cx="12" cy="10" r="3"></circle>
        </svg>
        <h4>Sin Ubicación Geográfica</h4>
        <p>Esta finca no tiene coordenadas registradas</p>
      </div>
    `;
    return;
  }

  try {
    // Asegurar que el contenedor tenga altura mínima
    if (!mapContainer.style.height || mapContainer.style.height === '0px') {
      mapContainer.style.height = '400px';
      mapContainer.style.minHeight = '400px';
    }

    // Inicializar mapa SOLO cuando el contenedor está visible
    nestedFarmMap = L.map('farm-nested-map', {
      center: [15.45, -90.35],
      zoom: 15,
      zoomControl: true,
      attributionControl: true,
      // Configuraciones adicionales para mapas en modales
      fadeAnimation: true,
      zoomAnimation: true
    });

    // CRÍTICO: Forzar recálculo de tamaño después de inicializar
    setTimeout(() => {
      if (nestedFarmMap) {
        nestedFarmMap.invalidateSize();
      }
    }, 100);

    // Capa satelital con mejor configuración
    const tileLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
      attribution: '© Esri &mdash; Source: Esri, DigitalGlobe, GeoEye, Earthstar Geographics, CNES/Airbus DS, USDA, USGS, AeroGRID, IGN, and the GIS User Community',
      maxZoom: 18,
      crossOrigin: true,
      // Forzar recarga de tiles si hay problemas
      updateWhenIdle: false,
      updateWhenZooming: true
    });
    
    tileLayer.addTo(nestedFarmMap);
    
    // Forzar actualización de tiles después de agregar la capa
    setTimeout(() => {
      if (nestedFarmMap) {
        nestedFarmMap.invalidateSize(true); // true = recalcular inmediatamente
        tileLayer.redraw();
      }
    }, 200);

    // Parsear y agregar geometría
    if (farm.geojson) {
      // Si es un Geometry object, envolverlo en Feature
      let geoJSONData = farm.geojson;
      if (geoJSONData.type === 'Point' || geoJSONData.type === 'Polygon' || geoJSONData.type === 'MultiPolygon') {
        geoJSONData = {
          type: 'Feature',
          geometry: geoJSONData,
          properties: {}
        };
      }

      const geoJSONLayer = L.geoJSON(geoJSONData, {
        style: {
          color: '#6dbe45',
          weight: 3,
          opacity: 0.8,
          fillColor: '#6dbe45',
          fillOpacity: 0.2
        }
      }).addTo(nestedFarmMap);

      // Ajustar vista con invalidateSize primero
      setTimeout(() => {
        if (nestedFarmMap && geoJSONLayer) {
          try {
            nestedFarmMap.invalidateSize(true);
            nestedFarmMap.fitBounds(geoJSONLayer.getBounds(), { padding: [20, 20] });
            // Forzar redraw de tiles después de ajustar vista
            setTimeout(() => {
              if (nestedFarmMap) {
                nestedFarmMap.invalidateSize(true);
              }
            }, 100);
          } catch (e) {
            console.warn('Error ajustando vista del mapa:', e);
          }
        }
      }, 150);
    } else if (farm.geometria_wkt || farm.geometria_postgis) {
      // Intentar parsear WKT si no hay GeoJSON
      try {
        const wktString = farm.geometria_wkt || farm.geometria_postgis;
        if (wktString && wktString.trim() !== '' && wktString.toLowerCase() !== 'null') {
          // Parseo básico de WKT POLYGON
          const coordMatch = wktString.match(/POLYGON\(\(([^)]+)\)\)/);
          if (coordMatch) {
            const coordString = coordMatch[1];
            const coords = coordString.split(',').map(coord => {
              const parts = coord.trim().split(/\s+/);
              if (parts.length === 2) {
                const lng = parseFloat(parts[0]);
                const lat = parseFloat(parts[1]);
                if (!isNaN(lng) && !isNaN(lat)) {
                  return [lat, lng];
                }
              }
              return null;
            }).filter(c => c !== null);

            if (coords.length >= 3) {
              const polygonLayer = L.polygon(coords, {
                color: '#6dbe45',
                weight: 3,
                opacity: 0.8,
                fillColor: '#6dbe45',
                fillOpacity: 0.2
              }).addTo(nestedFarmMap);

              // Ajustar vista con invalidateSize primero
              setTimeout(() => {
                if (nestedFarmMap && polygonLayer) {
                  try {
                    nestedFarmMap.invalidateSize(true);
                    nestedFarmMap.fitBounds(polygonLayer.getBounds(), { padding: [20, 20] });
                    // Forzar redraw de tiles después de ajustar vista
                    setTimeout(() => {
                      if (nestedFarmMap) {
                        nestedFarmMap.invalidateSize(true);
                      }
                    }, 100);
                  } catch (e) {
                    console.warn('Error ajustando vista del mapa:', e);
                  }
                }
              }, 150);
            }
          }
        }
      } catch (e) {
        console.warn('Error parseando WKT:', e);
      }
    }
  } catch (error) {
    console.error('Error inicializando mapa:', error);
    mapContainer.innerHTML = `
      <div class="map-error">
        <p>Error al cargar el mapa</p>
      </div>
    `;
  }
}

/**
 * Limpiar mapa anidado
 */
function cleanupNestedFarmMap() {
  if (nestedFarmMap) {
    try {
      nestedFarmMap.remove();
      nestedFarmMap = null;
    } catch (e) {
      console.warn('Error limpiando mapa:', e);
      nestedFarmMap = null;
    }
  }
}

// Funciones de edición de usuario
function editUser(userId, userData) {
  // Si tenemos datos del usuario, usarlos directamente
  if (userData) {
    openEditModal(userData);
  } else {
    // Si no, cargar desde la API
    loadUserForEdit(userId);
  }
}

async function loadUserForEdit(userId) {
  try {
    const api = new ApiClient();
    const response = await api.get(`api/users-list.php?page=1&page_size=1&q=&id_usuario=${userId}`);
    
    if (!response.success || !response.data || response.data.length === 0) {
      throw new Error(response.error || 'Usuario no encontrado');
    }
    
    openEditModal(response.data[0]);
  } catch (e) {
    console.error('Error loading user for edit:', e);
    alert('Error al cargar usuario: ' + e.message);
  }
}

function openEditModal(user) {
  document.getElementById('editUserId').value = user.id_usuario;
  document.getElementById('editNombre').value = user.nombre_completo || '';
  document.getElementById('editEmail').value = user.email || '';
  document.getElementById('editTelefono').value = user.telefono || '';
  document.getElementById('editUbicacion').value = user.ubicacion_general || '';
  document.getElementById('editCodigo').value = user.codigo_telegan || '';
  document.getElementById('editActivo').checked = user.activo === true;
  document.getElementById('editEmailVerificado').checked = user.email_verificado === true;
  document.getElementById('editTelefonoVerificado').checked = user.telefono_verificado === true;
  
  document.getElementById('user-edit-modal').classList.add('show');
}

function closeEditModal() {
  document.getElementById('user-edit-modal').classList.remove('show');
  document.getElementById('formEditUser').reset();
}

async function saveUserEdit(e) {
  e.preventDefault();
  
  const userId = parseInt(document.getElementById('editUserId').value);
  if (!userId) {
    alert('ID de usuario inválido');
    return;
  }
  
  const formData = {
    id_usuario: userId,
    nombre_completo: document.getElementById('editNombre').value.trim(),
    email: document.getElementById('editEmail').value.trim(),
    telefono: document.getElementById('editTelefono').value.trim() || null,
    ubicacion_general: document.getElementById('editUbicacion').value.trim() || null,
    codigo_telegan: document.getElementById('editCodigo').value.trim() || null,
    // Siempre enviar booleanos explícitos (nunca null/undefined) para campos NOT NULL
    activo: document.getElementById('editActivo').checked === true,
    email_verificado: document.getElementById('editEmailVerificado').checked === true,
    telefono_verificado: document.getElementById('editTelefonoVerificado').checked === true
  };
  
  // Validaciones básicas
  if (!formData.nombre_completo || formData.nombre_completo.length > 255) {
    alert('Nombre completo es requerido y debe tener máximo 255 caracteres');
    return;
  }
  
  if (!formData.email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
    alert('Email inválido');
    return;
  }
  
  try {
    const api = new ApiClient();
    const response = await api.put('api/users-update.php', formData);
    
    if (!response.success) {
      throw new Error(response.error || 'Error al actualizar usuario');
    }
    
    closeEditModal();
    loadUsers();
    
    console.log('Usuario actualizado exitosamente');
  } catch (e) {
    console.error('Error saving user:', e);
    alert('Error al actualizar usuario: ' + e.message);
  }
}

// Event listeners para el modal de edición
document.addEventListener('DOMContentLoaded', function() {
  const editModal = document.getElementById('user-edit-modal');
  const editModalClose = document.getElementById('editModalClose');
  const editCancel = document.getElementById('editCancel');
  const formEditUser = document.getElementById('formEditUser');
  
  if (editModalClose) {
    editModalClose.addEventListener('click', closeEditModal);
  }
  
  if (editCancel) {
    editCancel.addEventListener('click', closeEditModal);
  }
  
  if (formEditUser) {
    formEditUser.addEventListener('submit', saveUserEdit);
  }
  
  // Cerrar modal al hacer click fuera
  if (editModal) {
    editModal.addEventListener('click', (e) => {
      if (e.target === editModal) {
        closeEditModal();
      }
    });
  }
});

// Cerrar modales con Escape
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    // Cerrar modal de finca primero (si está abierto)
    const farmModal = document.getElementById('farm-modal-nested');
    if (farmModal && farmModal.classList.contains('show')) {
      closeFarmModalNested();
      return;
    }
    
    // Cerrar modal de edición
    const editModal = document.getElementById('user-edit-modal');
    if (editModal && editModal.classList.contains('show')) {
      closeEditModal();
      return;
    }
    
    // Luego cerrar modal de usuario
    const userModal = document.getElementById('user-profile-modal');
    if (userModal && userModal.classList.contains('show')) {
      closeUserProfileModal();
    }
  }
});

// Función para manejar el ordenamiento
function handleSort(column) {
  if (state.sortBy === column) {
    // Si ya está ordenando por esta columna, cambiar la dirección
    state.sortOrder = state.sortOrder === 'ASC' ? 'DESC' : 'ASC';
  } else {
    // Si es una nueva columna, ordenar ASC por defecto
    state.sortBy = column;
    state.sortOrder = 'ASC';
  }
  
  // Resetear a página 1
  state.page = 1;
  
  // Actualizar indicadores visuales
  updateSortIndicators();
  
  // Recargar datos
  loadUsers();
}

// Función para actualizar indicadores visuales de ordenamiento
function updateSortIndicators() {
  // Remover todas las clases de ordenamiento
  document.querySelectorAll('th.sortable').forEach(th => {
    th.classList.remove('sort-asc', 'sort-desc');
  });
  
  // Agregar clase al header activo
  const activeHeader = document.querySelector(`th.sortable[data-sort="${state.sortBy}"]`);
  if (activeHeader) {
    activeHeader.classList.add(state.sortOrder === 'ASC' ? 'sort-asc' : 'sort-desc');
  }
}

// Inicializar event listeners para ordenamiento
document.addEventListener('DOMContentLoaded', function() {
  // Agregar listeners a los headers clickeables
  document.querySelectorAll('th.sortable').forEach(th => {
    th.addEventListener('click', function() {
      const column = this.getAttribute('data-sort');
      if (column) {
        handleSort(column);
      }
    });
  });
  
  // Actualizar indicadores iniciales
  updateSortIndicators();
});

// Init
loadUsers();


