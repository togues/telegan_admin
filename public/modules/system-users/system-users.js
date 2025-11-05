// System Users (Admin Users) CRUD - Listado con filtros, paginación y CRUD completo
import { AppConfig } from '../../js/config.js';
import { ApiClient } from '../../js/ApiClient.js';

const state = {
  q: '',
  activo: '',
  rol: '',
  page: 1,
  pageSize: 20,
  totalPages: 1,
  editingId: null,
  sortBy: 'fecha_registro',
  sortOrder: 'DESC'
};

const els = {
  q: document.getElementById('q'),
  activo: document.getElementById('activo'),
  rol: document.getElementById('rol'),
  clearFilters: document.getElementById('clearFilters'),
  prev: document.getElementById('prev'),
  next: document.getElementById('next'),
  pageInfo: document.getElementById('pageInfo'),
  tbody: document.getElementById('tbody'),
  btnCreate: document.getElementById('btnCreate'),
  downloadAdmins: document.getElementById('downloadAdmins'),
  // Modal form
  modalForm: document.getElementById('modalForm'),
  modalTitle: document.getElementById('modalTitle'),
  modalClose: document.getElementById('modalClose'),
  formAdmin: document.getElementById('formAdmin'),
  formId: document.getElementById('formId'),
  formNombre: document.getElementById('formNombre'),
  formEmail: document.getElementById('formEmail'),
  formTelefono: document.getElementById('formTelefono'),
  formPassword: document.getElementById('formPassword'),
  passwordRequired: document.getElementById('passwordRequired'),
  formRol: document.getElementById('formRol'),
  formActivo: document.getElementById('formActivo'),
  formEmailVerificado: document.getElementById('formEmailVerificado'),
  formTelefonoVerificado: document.getElementById('formTelefonoVerificado'),
  formCancel: document.getElementById('formCancel'),
  formSave: document.getElementById('formSave'),
  // Modal confirmación
  modalConfirm: document.getElementById('modalConfirm'),
  confirmMessage: document.getElementById('confirmMessage'),
  confirmClose: document.getElementById('confirmClose'),
  confirmCancel: document.getElementById('confirmCancel'),
  confirmOk: document.getElementById('confirmOk')
};

let confirmCallback = null;

function buildUrl(includePagination = true) {
  const params = new URLSearchParams();
  if (state.q) params.set('q', state.q);
  if (state.activo !== '') params.set('activo', state.activo);
  if (state.rol) params.set('rol', state.rol);
  
  // Parámetros de ordenamiento
  params.set('sort_by', state.sortBy);
  params.set('sort_order', state.sortOrder);
  
  if (includePagination) {
    params.set('page', String(state.page));
    params.set('page_size', String(state.pageSize));
  } else {
    params.set('page', '1');
    params.set('page_size', '10000');
  }
  
  return `api/system-users-list.php?${params.toString()}`;
}

async function loadAdmins() {
  try {
    els.tbody.innerHTML = `<tr><td colspan="10" style="text-align:center; color: var(--text-secondary); padding: 16px;">Cargando...</td></tr>`;
    const url = buildUrl();
    
    const api = new ApiClient();
    const response = await api.get(url);
    
    if (!response.success) {
      throw new Error(response.error || 'Error al cargar administradores');
    }
    
    renderRows(response.data || []);
    const pg = response.pagination || {};
    state.totalPages = pg.total_pages || 1;
    els.pageInfo.textContent = `Página ${pg.page || state.page} de ${state.totalPages} • ${pg.total || 0} administradores`;
    els.prev.disabled = state.page <= 1;
    els.next.disabled = state.page >= state.totalPages;
  } catch (e) {
    console.error('Error loading admins:', e);
    els.tbody.innerHTML = `<tr><td colspan="9" style="text-align:center; color: var(--error-color); padding: 16px;">Error al cargar administradores: ${e.message || 'Error desconocido'}</td></tr>`;
  }
}

function escapeHtml(s) {
  if (s == null) return '';
  return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

function fmtDate(d) {
  if (!d) return 'Nunca';
  try { 
    const date = new Date(d);
    return date.toLocaleString('es-ES', { 
      year: 'numeric', 
      month: '2-digit', 
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit'
    });
  } catch (_) { 
    return d; 
  }
}

function fmtDateOnly(d) {
  if (!d) return '';
  try { 
    const date = new Date(d);
    return date.toLocaleDateString('es-ES', { year: 'numeric', month: '2-digit', day: '2-digit' });
  } catch (_) { 
    if (typeof d === 'string' && d.includes('T')) {
      return d.split('T')[0].split('-').reverse().join('/');
    }
    return d; 
  }
}

function getRoleClass(rol) {
  const roleMap = {
    'SUPER_ADMIN': 'role-super_admin',
    'TECNICO': 'role-tecnico',
    'ADMIN_FINCA': 'role-admin_finca'
  };
  return roleMap[rol] || '';
}

function getRoleLabel(rol) {
  const roleMap = {
    'SUPER_ADMIN': 'Super Admin',
    'TECNICO': 'Técnico',
    'ADMIN_FINCA': 'Admin Finca'
  };
  return roleMap[rol] || rol;
}

function renderRows(rows) {
  if (!rows.length) {
    els.tbody.innerHTML = `<tr><td colspan="9" style="text-align:center; color: var(--text-secondary); padding: 16px;">Sin resultados</td></tr>`;
    return;
  }
  const html = rows.map(r => {
    const dot = r.activo ? 'dot-on' : 'dot-off';
    const roleClass = getRoleClass(r.rol);
    const roleLabel = getRoleLabel(r.rol);
    
    return `
      <tr>
        <td style="display: flex; gap: 0.25rem;">
          <button class="btn-edit" data-id="${r.id_admin}" title="Editar">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
              <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
            </svg>
          </button>
          <button class="btn-delete" data-id="${r.id_admin}" data-nombre="${escapeHtml(r.nombre_completo)}" title="Desactivar">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="3 6 5 6 21 6"></polyline>
              <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
            </svg>
          </button>
        </td>
        <td>${escapeHtml(r.nombre_completo)}</td>
        <td>${escapeHtml(r.email || '')}</td>
        <td>${escapeHtml(r.telefono || '-')}</td>
        <td><span class="role-badge ${roleClass}">${roleLabel}</span></td>
        <td><span class="status-dot ${dot}"></span>${r.activo ? 'Activo' : 'Inactivo'}</td>
        <td>${r.email_verificado ? '✓' : '✗'}</td>
        <td>${fmtDate(r.ultima_sesion)}</td>
        <td>${fmtDateOnly(r.fecha_registro)}</td>
      </tr>`;
  }).join('');
  els.tbody.innerHTML = html;
  
  // Agregar event listeners a botones de editar y eliminar
  document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', () => editAdmin(parseInt(btn.dataset.id)));
  });
  
  document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = parseInt(btn.dataset.id);
      const nombre = btn.dataset.nombre;
      showConfirmDelete(id, nombre);
    });
  });
}

function clearFilters() {
  state.q = '';
  state.activo = '';
  state.rol = '';
  state.page = 1;
  
  els.q.value = '';
  els.activo.value = '';
  els.rol.value = '';
  
  loadAdmins();
}

function openCreateModal() {
  state.editingId = null;
  els.modalTitle.textContent = 'Nuevo Administrador';
  els.formAdmin.reset();
  els.formId.value = '';
  els.passwordRequired.style.display = 'inline';
  els.formPassword.required = true;
  els.modalForm.classList.add('show');
}

function closeModal() {
  els.modalForm.classList.remove('show');
  state.editingId = null;
  els.formAdmin.reset();
}

async function editAdmin(id) {
  try {
    const api = new ApiClient();
    const response = await api.get(`api/system-users-detail.php?id=${id}`);
    
    if (!response.success) {
      throw new Error(response.error || 'Error al cargar administrador');
    }
    
    const admin = response.data;
    state.editingId = id;
    
    els.modalTitle.textContent = 'Editar Administrador';
    els.formId.value = admin.id_admin;
    els.formNombre.value = admin.nombre_completo;
    els.formEmail.value = admin.email;
    els.formTelefono.value = admin.telefono || '';
    els.formPassword.value = '';
    els.passwordRequired.style.display = 'none';
    els.formPassword.required = false;
    els.formRol.value = admin.rol;
    els.formActivo.checked = admin.activo;
    els.formEmailVerificado.checked = admin.email_verificado;
    els.formTelefonoVerificado.checked = admin.telefono_verificado;
    
    els.modalForm.classList.add('show');
  } catch (e) {
    console.error('Error loading admin:', e);
    alert('Error al cargar administrador: ' + e.message);
  }
}

async function saveAdmin(e) {
  e.preventDefault();
  
  const formData = {
    nombre_completo: els.formNombre.value.trim(),
    email: els.formEmail.value.trim(),
    telefono: els.formTelefono.value.trim() || null,
    rol: els.formRol.value,
    activo: els.formActivo.checked,
    email_verificado: els.formEmailVerificado.checked,
    telefono_verificado: els.formTelefonoVerificado.checked
  };
  
  // Validaciones
  if (!formData.nombre_completo || formData.nombre_completo.length > 255) {
    alert('Nombre completo es requerido y debe tener máximo 255 caracteres');
    return;
  }
  
  if (!formData.email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
    alert('Email inválido');
    return;
  }
  
  if (!formData.rol) {
    alert('Rol es requerido');
    return;
  }
  
  // Si es creación, password es requerido
  if (!state.editingId) {
    if (!els.formPassword.value || els.formPassword.value.length < 8) {
      alert('La contraseña es requerida y debe tener al menos 8 caracteres');
      return;
    }
    formData.password = els.formPassword.value;
  } else {
    // Si es edición y se proporcionó password, incluirlo
    if (els.formPassword.value && els.formPassword.value.length >= 8) {
      formData.password = els.formPassword.value;
    }
    formData.id_admin = state.editingId;
  }
  
  try {
    const api = new ApiClient();
    let response;
    
    if (state.editingId) {
      response = await api.put('api/system-users-update.php', formData);
    } else {
      response = await api.post('api/system-users-create.php', formData);
    }
    
    if (!response.success) {
      throw new Error(response.error || 'Error al guardar administrador');
    }
    
    closeModal();
    loadAdmins();
    
    // Mostrar notificación (puedes implementar tu sistema de notificaciones)
    const message = state.editingId ? 'Administrador actualizado exitosamente' : 'Administrador creado exitosamente';
    console.log(message);
  } catch (e) {
    console.error('Error saving admin:', e);
    alert('Error al guardar administrador: ' + e.message);
  }
}

function showConfirmDelete(id, nombre) {
  confirmCallback = () => deleteAdmin(id);
  els.confirmMessage.textContent = `¿Estás seguro de que deseas desactivar al administrador "${nombre}"? Esta acción no se puede deshacer.`;
  els.modalConfirm.classList.add('show');
}

function closeConfirmModal() {
  els.modalConfirm.classList.remove('show');
  confirmCallback = null;
}

async function deleteAdmin(id) {
  try {
    const api = new ApiClient();
    const response = await api.post('api/system-users-deactivate.php', { id_admin: id });
    
    if (!response.success) {
      throw new Error(response.error || 'Error al desactivar administrador');
    }
    
    closeConfirmModal();
    loadAdmins();
    console.log('Administrador desactivado exitosamente');
  } catch (e) {
    console.error('Error deleting admin:', e);
    alert('Error al desactivar administrador: ' + e.message);
  }
}

async function downloadAdmins() {
  try {
    // Mostrar mensaje de confirmación
    const confirmMsg = `¿Descargar todos los administradores con los filtros actuales?`;
    if (!confirm(confirmMsg)) return;
    
    // Mostrar loading
    els.downloadAdmins.disabled = true;
    els.downloadAdmins.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation: spin 1s linear infinite;"><circle cx="12" cy="12" r="10"></circle></svg> Descargando...';
    
    const url = buildUrl(false);
    const api = new ApiClient();
    const response = await api.get(url);
    
    if (!response.success) {
      throw new Error(response.error || 'Error al descargar administradores');
    }
    
    const admins = response.data || [];
    
    if (admins.length === 0) {
      alert('No hay administradores para descargar');
      els.downloadAdmins.disabled = false;
      els.downloadAdmins.innerHTML = `
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
          <polyline points="7 10 12 15 17 10"></polyline>
          <line x1="12" y1="15" x2="12" y2="3"></line>
        </svg>
        Descargar Administradores
      `;
      return;
    }
    
    // Preparar datos para Excel
    const excelData = admins.map(admin => ({
      'ID': admin.id_admin,
      'Nombre Completo': admin.nombre_completo,
      'Email': admin.email,
      'Teléfono': admin.telefono || '',
      'Rol': getRoleLabel(admin.rol),
      'Activo': admin.activo ? 'Sí' : 'No',
      'Email Verificado': admin.email_verificado ? 'Sí' : 'No',
      'Teléfono Verificado': admin.telefono_verificado ? 'Sí' : 'No',
      'Última Sesión': fmtDate(admin.ultima_sesion),
      'Fecha Registro': fmtDateOnly(admin.fecha_registro),
      'Creado Por': admin.creado_por_nombre || ''
    }));
    
    // Crear workbook
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.json_to_sheet(excelData);
    
    // Ajustar anchos de columna
    const colWidths = [
      { wch: 6 },   // ID
      { wch: 25 },  // Nombre
      { wch: 30 },  // Email
      { wch: 15 },  // Teléfono
      { wch: 15 },  // Rol
      { wch: 10 },  // Activo
      { wch: 15 },  // Email Verificado
      { wch: 18 },  // Teléfono Verificado
      { wch: 20 },  // Última Sesión
      { wch: 15 },  // Fecha Registro
      { wch: 20 }   // Creado Por
    ];
    ws['!cols'] = colWidths;
    
    XLSX.utils.book_append_sheet(wb, ws, 'Administradores');
    
    // Generar nombre de archivo con fecha
    const fecha = new Date().toISOString().split('T')[0];
    const filename = `administradores_${fecha}.xlsx`;
    
    // Descargar
    XLSX.writeFile(wb, filename);
    
    // Restaurar botón
    els.downloadAdmins.disabled = false;
    els.downloadAdmins.innerHTML = `
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
        <polyline points="7 10 12 15 17 10"></polyline>
        <line x1="12" y1="15" x2="12" y2="3"></line>
      </svg>
      Descargar Administradores
    `;
  } catch (e) {
    console.error('Error downloading admins:', e);
    alert('Error al descargar administradores: ' + e.message);
    els.downloadAdmins.disabled = false;
    els.downloadAdmins.innerHTML = `
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
        <polyline points="7 10 12 15 17 10"></polyline>
        <line x1="12" y1="15" x2="12" y2="3"></line>
      </svg>
      Descargar Administradores
    `;
  }
}

// Event Listeners
if (els.q) {
  let debounceTimer;
  els.q.addEventListener('input', (e) => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      state.q = e.target.value;
      state.page = 1;
      loadAdmins();
    }, 300);
  });
}

if (els.activo) {
  els.activo.addEventListener('change', (e) => {
    state.activo = e.target.value;
    state.page = 1;
    loadAdmins();
  });
}

if (els.rol) {
  els.rol.addEventListener('change', (e) => {
    state.rol = e.target.value;
    state.page = 1;
    loadAdmins();
  });
}

if (els.clearFilters) {
  els.clearFilters.addEventListener('click', clearFilters);
}

if (els.prev) {
  els.prev.addEventListener('click', () => {
    if (state.page > 1) {
      state.page--;
      loadAdmins();
    }
  });
}

if (els.next) {
  els.next.addEventListener('click', () => {
    if (state.page < state.totalPages) {
      state.page++;
      loadAdmins();
    }
  });
}

if (els.btnCreate) {
  els.btnCreate.addEventListener('click', openCreateModal);
}

if (els.modalClose) {
  els.modalClose.addEventListener('click', closeModal);
}

if (els.formCancel) {
  els.formCancel.addEventListener('click', closeModal);
}

if (els.formAdmin) {
  els.formAdmin.addEventListener('submit', saveAdmin);
}

if (els.confirmClose) {
  els.confirmClose.addEventListener('click', closeConfirmModal);
}

if (els.confirmCancel) {
  els.confirmCancel.addEventListener('click', closeConfirmModal);
}

if (els.confirmOk) {
  els.confirmOk.addEventListener('click', () => {
    if (confirmCallback) {
      confirmCallback();
    }
  });
}

if (els.downloadAdmins) {
  els.downloadAdmins.addEventListener('click', downloadAdmins);
}

// Cerrar modales al hacer click fuera
if (els.modalForm) {
  els.modalForm.addEventListener('click', (e) => {
    if (e.target === els.modalForm) {
      closeModal();
    }
  });
}

if (els.modalConfirm) {
  els.modalConfirm.addEventListener('click', (e) => {
    if (e.target === els.modalConfirm) {
      closeConfirmModal();
    }
  });
}

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
  loadAdmins();
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

// Cargar datos iniciales
loadAdmins();

