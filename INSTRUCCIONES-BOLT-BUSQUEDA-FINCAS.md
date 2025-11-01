# 🎨 INSTRUCCIONES PARA BOLT - MÓDULO DE BÚSQUEDA Y FINCAS

## 📋 Contexto

Este módulo está **integrado en el Dashboard** (`public/dashboard.html`). Permite:
1. **Buscar usuarios/agricultores** (modal de búsqueda)
2. **Ver perfil del usuario** (datos personales)
3. **Listar fincas del usuario** (tarjetas de fincas)
4. **Ver detalles completos de una finca** (modal grande con mapa, potreros, usuarios, etc.)

---

## 📁 ARCHIVOS QUE DEBE MODIFICAR BOLT

### 1. Archivo Principal de Estilos (OBLIGATORIO)
```
public/css/styles.css
```
Contiene TODOS los estilos de este módulo.

### 2. Dashboard HTML (ESTRUCTURA)
```
public/dashboard.html
```
Puede modificar estructura HTML de los modales y componentes visuales.

**NO modificar**:
- IDs de elementos (se usan en JavaScript)
- Atributos `onclick` o funciones JavaScript
- Scripts al final del archivo

---

## 🎯 ÁREAS ESPECÍFICAS PARA MEJORAR

### 1. MODAL DE BÚSQUEDA (`public/dashboard.html` líneas 523-620)

#### Ubicación en HTML
```html
<!-- Search Modal -->
<div id="search-modal" class="search-modal">
    <div class="search-modal-overlay"></div>
    <div class="search-modal-content">
        <!-- Modal Header -->
        <div class="search-modal-header">
            <h2 class="search-modal-title">Buscar Agricultor</h2>
            <!-- ... -->
        </div>
        <!-- Search Input -->
        <div class="search-input-container">
            <!-- Campo de búsqueda -->
        </div>
        <!-- Results -->
        <div class="search-results" id="search-results">
            <!-- Resultados de búsqueda -->
        </div>
    </div>
</div>
```

#### Estilos CSS Relacionados (en `public/css/styles.css`)
**Buscar estas clases**:
- `.search-modal` - Modal completo
- `.search-modal-overlay` - Fondo oscuro con blur
- `.search-modal-content` - Contenedor del modal
- `.search-modal-header` - Header del modal
- `.search-modal-title` - Título "Buscar Agricultor"
- `.search-input-container` - Contenedor del input
- `.search-input` - Campo de búsqueda
- `.search-results` - Contenedor de resultados
- `.search-result-item` - Item individual de resultado

#### Mejoras Sugeridas
- [ ] Mejorar animación de apertura del modal (fade-in + scale)
- [ ] Mejorar diseño del input de búsqueda (focus states, iconos)
- [ ] Añadir efectos hover en resultados de búsqueda
- [ ] Mejorar espaciado y padding
- [ ] Añadir transiciones suaves
- [ ] Mejorar backdrop blur del overlay
- [ ] Añadir animaciones de entrada para resultados (stagger animation)

---

### 2. PERFIL DE USUARIO (dentro del modal de búsqueda)

#### Ubicación en HTML
Dentro del `search-modal-content`, hay una sección de perfil que se muestra cuando seleccionas un usuario.

#### Estilos CSS Relacionados
**Buscar estas clases**:
- `.profile-header` - Header del perfil
- `.profile-avatar` - Avatar del usuario
- `.profile-info` - Información del usuario
- `.profile-section` - Secciones del perfil
- `.profile-item` - Items individuales del perfil

#### Mejoras Sugeridas
- [ ] Mejorar diseño del avatar (más grande, con gradiente)
- [ ] Mejorar layout del header de perfil
- [ ] Añadir efectos visuales a las secciones
- [ ] Mejorar espaciado entre items del perfil

---

### 3. LISTA DE FINCAS (`farms-list`)

#### Ubicación en HTML
```html
<div class="farms-list" id="farms-list">
    <!-- Items de fincas se generan dinámicamente -->
</div>
```

#### Estilos CSS Relacionados (líneas 1415-1500)
**Buscar estas clases**:
- `.farms-list` - Contenedor de la lista
- `.farm-item` - Item individual de finca
- `.farm-info` - Información de la finca
- `.farm-status` - Estado de la finca
- `.farm-action-btn` - Botón de acciones
- `.loading-farms` - Estado de carga
- `.no-farms` - Estado sin fincas

#### Código CSS Actual (Referencia)
```css
.farm-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem;
  border: 1px solid var(--border-color);
  border-radius: 12px;
  margin-bottom: 0.75rem;
  cursor: pointer;
  transition: all 0.2s ease;
  background: var(--bg-card);
}

.farm-item:hover {
  background: var(--bg-secondary);
  border-color: var(--accent-primary);
  transform: translateY(-1px);
  box-shadow: var(--shadow-sm);
}
```

#### Mejoras Sugeridas
- [ ] Mejorar efecto hover (más pronunciado, `translateY(-4px)`)
- [ ] Añadir sombras más profundas en hover
- [ ] Mejorar diseño de los botones de acción
- [ ] Añadir animación de entrada (fade-in + slide-up)
- [ ] Mejorar diseño del estado "sin fincas"
- [ ] Mejorar diseño del loader
- [ ] Añadir efectos de profundidad (card elevation)

---

### 4. MODAL DE DETALLES DE FINCA (`farm-main-modal`)

#### Ubicación en HTML (líneas 672-802)
```html
<!-- Farm Main Modal -->
<div id="farm-main-modal" class="farm-main-modal">
    <div class="farm-modal-overlay"></div>
    <div class="farm-modal-content">
        <!-- Header -->
        <div class="farm-modal-header">
            <!-- Título y botones -->
        </div>
        <!-- Body -->
        <div class="farm-modal-body">
            <!-- Información básica -->
            <!-- Grid de detalles -->
            <div class="farm-details-grid">
                <!-- Cards con información, mapa, usuarios, potreros -->
            </div>
        </div>
    </div>
</div>
```

#### Estilos CSS Relacionados (líneas 1600-2100)

##### 4.1. Modal Principal
**Buscar estas clases**:
- `.farm-main-modal` - Modal completo
- `.farm-modal-overlay` - Overlay con blur
- `.farm-modal-content` - Contenedor del modal
- `.farm-modal-header` - Header del modal
- `.farm-modal-body` - Cuerpo del modal (scrollable)

##### 4.2. Header del Modal
**Buscar estas clases**:
- `.farm-header-info` - Info del header
- `.farm-modal-title` - Título de la finca
- `.farm-modal-subtitle` - Subtítulo
- `.farm-header-actions` - Botones del header
- `.farm-back-btn` - Botón "Nueva Búsqueda"
- `.farm-modal-close` - Botón cerrar

##### 4.3. Información Básica
**Buscar estas clases**:
- `.farm-info-section` - Sección de información
- `.farm-basic-info` - Info básica
- `.farm-avatar` - Avatar de la finca
- `.farm-name` - Nombre de la finca
- `.farm-location` - Ubicación
- `.farm-status-row` - Fila de estado
- `.farm-area` - Área de la finca

##### 4.4. Grid de Detalles
**Buscar estas clases**:
- `.farm-details-grid` - Grid principal (responsive)
- `.farm-detail-card` - Cards individuales
- `.card-title` - Títulos de cards
- `.detail-grid` - Grid interno de detalles
- `.detail-item` - Items de detalles
- `.map-card` - Card del mapa (span 2 columnas)

#### Mejoras Sugeridas

##### Modal Principal
- [ ] Mejorar animación de apertura (más suave)
- [ ] Mejorar diseño del overlay (más blur, mejor opacidad)
- [ ] Añadir transiciones al cerrar

##### Header
- [ ] Mejorar diseño del título y subtítulo
- [ ] Mejorar botones (hover, focus states)
- [ ] Añadir efectos visuales

##### Información Básica
- [ ] Mejorar diseño del avatar (más grande, mejor gradiente)
- [ ] Mejorar layout de información
- [ ] Añadir efectos visuales sutiles

##### Grid de Detalles
- [ ] Mejorar hover en cards (más pronunciado)
- [ ] Añadir animaciones de entrada (stagger)
- [ ] Mejorar espaciado del grid
- [ ] Mejorar diseño responsive (móvil)
- [ ] Añadir efectos de profundidad

---

### 5. LISTAS DENTRO DEL MODAL DE FINCA

#### Usuarios (Administradores y Colaboradores)
**Buscar estas clases**:
- `.users-list` - Lista de usuarios
- `.user-item` - Item de usuario (buscar en CSS o crear)

#### Potreros
**Buscar estas clases**:
- `.paddocks-list` - Lista de potreros
- `.paddock-item` - Item de potrero (buscar en CSS o crear)

#### Mapa
**Buscar estas clases**:
- `.map-container` - Contenedor del mapa
- `.map-loading` - Estado de carga del mapa

#### Mejoras Sugeridas
- [ ] Crear/mejorar estilos para `.user-item` y `.paddock-item`
- [ ] Añadir efectos hover en items
- [ ] Mejorar diseño del loader del mapa
- [ ] Añadir animaciones de entrada

---

## ✨ EJEMPLOS DE MEJORAS ESPECÍFICAS

### Ejemplo 1: Mejorar Items de Fincas
```css
/* En public/css/styles.css */
.farm-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1.25rem;
  border: 1px solid var(--border-color);
  border-radius: 16px;
  margin-bottom: 1rem;
  cursor: pointer;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  background: var(--bg-card);
  box-shadow: var(--shadow-sm);
}

.farm-item:hover {
  background: var(--bg-secondary);
  border-color: var(--accent-primary);
  transform: translateY(-4px) scale(1.01);
  box-shadow: 0 8px 24px rgba(109, 190, 69, 0.2);
}

.farm-item:active {
  transform: translateY(-2px) scale(0.99);
}
```

### Ejemplo 2: Mejorar Modal de Finca
```css
.farm-modal-content {
  /* ... estilos existentes ... */
  animation: modalSlideIn 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes modalSlideIn {
  from {
    opacity: 0;
    transform: translateY(20px) scale(0.95);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

.farm-modal-overlay {
  /* ... estilos existentes ... */
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  animation: fadeIn 0.3s ease-out;
}
```

### Ejemplo 3: Mejorar Cards de Detalles
```css
.farm-detail-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 16px;
  padding: 1.5rem;
  box-shadow: var(--shadow-sm);
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  animation: cardFadeIn 0.5s ease-out backwards;
}

.farm-detail-card:hover {
  box-shadow: 0 12px 32px rgba(0, 0, 0, 0.12);
  transform: translateY(-4px);
  border-color: var(--accent-primary);
}

@keyframes cardFadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Stagger animation para múltiples cards */
.farm-detail-card:nth-child(1) { animation-delay: 0.1s; }
.farm-detail-card:nth-child(2) { animation-delay: 0.2s; }
.farm-detail-card:nth-child(3) { animation-delay: 0.3s; }
.farm-detail-card:nth-child(4) { animation-delay: 0.4s; }
```

### Ejemplo 4: Mejorar Input de Búsqueda
```css
.search-input {
  /* ... estilos existentes ... */
  transition: all 0.3s ease;
  border: 2px solid var(--border-color);
}

.search-input:focus {
  border-color: var(--accent-primary);
  box-shadow: 0 0 0 4px rgba(109, 190, 69, 0.1);
  transform: translateY(-1px);
  outline: none;
}
```

### Ejemplo 5: Mejorar Items de Resultados de Búsqueda
```css
.search-result-item {
  /* Si existe o crear nuevo */
  padding: 1rem;
  border-radius: 12px;
  margin-bottom: 0.5rem;
  cursor: pointer;
  transition: all 0.2s ease;
  border: 1px solid transparent;
}

.search-result-item:hover {
  background: var(--bg-secondary);
  border-color: var(--accent-primary);
  transform: translateX(4px);
  box-shadow: var(--shadow-sm);
}
```

---

## 📐 LÍNEAS ESPECÍFICAS EN CSS

### Estilos de Búsqueda y Fincas
- **Líneas 1415-1500**: Estilos de `.farms-list`, `.farm-item`
- **Líneas 1600-1800**: Estilos del modal de finca principal
- **Líneas 1800-2100**: Estilos del grid de detalles, cards, mapa

### Buscar en CSS (usar Ctrl+F):
- `search-modal` - Modal de búsqueda
- `farm-main-modal` - Modal de detalles de finca
- `farm-item` - Items de lista de fincas
- `farm-detail-card` - Cards dentro del modal
- `farm-details-grid` - Grid de detalles

---

## 🎨 PRINCIPIOS DE DISEÑO

### 1. Jerarquía Visual
- Modal principal: más destacado (sombras profundas)
- Cards: nivel intermedio (sombras medias)
- Items de lista: nivel básico (sombras sutiles)

### 2. Transiciones
- Usar `cubic-bezier(0.4, 0, 0.2, 1)` para transiciones naturales
- Duración: 0.2s-0.4s
- Hover: transform + shadow + color

### 3. Animaciones
- Entrada: fade-in + slide-up
- Stagger: delay progresivo en múltiples elementos
- Hover: translateY + scale sutil

### 4. Colores
- Usar `var(--accent-primary)` para acentos
- Usar `var(--bg-secondary)` para hover
- Mantener consistencia con el resto del dashboard

---

## ⚠️ REGLAS CRÍTICAS

### NO Modificar
- [ ] IDs de elementos (se usan en JavaScript)
  - `search-modal`, `search-results`, `farms-list`
  - `farm-main-modal`, `farm-details`, etc.
- [ ] Atributos `onclick` (ej: `onclick="showFarmDetails(...)"`)
- [ ] Estructura de datos que JavaScript espera
- [ ] Funcionalidad de JavaScript (solo estética)

### SÍ Puede Modificar
- [ ] Todas las clases CSS y sus estilos
- [ ] Estructura HTML visual (añadir divs, cambiar clases)
- [ ] Animaciones y transiciones
- [ ] Layout y espaciado
- [ ] Colores y sombras
- [ ] Tipografía visual

---

## 📝 CHECKLIST PARA BOLT

### Modal de Búsqueda
- [ ] Mejorar animación de apertura
- [ ] Mejorar diseño del input
- [ ] Añadir efectos hover en resultados
- [ ] Mejorar overlay con blur

### Lista de Fincas
- [ ] Mejorar hover de `.farm-item`
- [ ] Añadir animaciones de entrada
- [ ] Mejorar diseño de botones de acción
- [ ] Mejorar estados (loading, sin fincas)

### Modal de Detalles de Finca
- [ ] Mejorar animación de apertura
- [ ] Mejorar header del modal
- [ ] Mejorar información básica (avatar, layout)
- [ ] Mejorar grid de detalles (hover, animaciones)
- [ ] Mejorar diseño responsive

### Elementos Internos
- [ ] Crear/mejorar estilos de `.user-item`
- [ ] Crear/mejorar estilos de `.paddock-item`
- [ ] Mejorar diseño del mapa
- [ ] Añadir animaciones stagger

### General
- [ ] Verificar que funciona en móvil
- [ ] Verificar tema oscuro
- [ ] Verificar transiciones suaves
- [ ] No romper funcionalidad JavaScript

---

## 🔍 CÓMO ENCONTRAR ELEMENTOS

### En CSS (`public/css/styles.css`)
1. Buscar: `.farm-item` (línea ~1429)
2. Buscar: `.farm-main-modal` (línea ~1602)
3. Buscar: `.farm-detail-card` (línea ~1778)
4. Buscar: `.search-modal` (buscar en archivo)

### En HTML (`public/dashboard.html`)
1. Buscar: `id="search-modal"` (línea ~523)
2. Buscar: `id="farms-list"` (línea ~644)
3. Buscar: `id="farm-main-modal"` (línea ~673)

---

## 💡 TIPS ADICIONALES

1. **Usar transform con cuidado**: No exagerar las transformaciones
2. **Sombras graduales**: Más profundidad = más sombra
3. **Animaciones sutiles**: No sobrecargar con animaciones
4. **Mobile-first**: Probar en móvil primero
5. **Performance**: Usar `transform` y `opacity` para animaciones (mejor performance)

---

**¡Listo para mejorar la estética del módulo de búsqueda y fincas!** 🎨✨

