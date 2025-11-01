# 🎨 INSTRUCCIONES PARA BOLT - MEJORAS DE ESTÉTICA

## 📋 Contexto

Bolt trabajará **SOLO en estética y layout** de dos vistas principales:
1. **Dashboard** (`public/dashboard.html`)
2. **Módulo de Usuarios** (`public/modules/users/index.php`)

**NO tocar**: Sistema de login/auth (está completo y funcional).

---

## 📁 ARCHIVOS QUE DEBE MODIFICAR BOLT

### 1. Archivo Principal de Estilos (OBLIGATORIO)
```
public/css/styles.css
```
**Este es el archivo MÁS IMPORTANTE**. Contiene todos los estilos del sistema.

### 2. Dashboard - Archivo HTML
```
public/dashboard.html
```
**Puede modificar**: 
- Estructura HTML de componentes (divs, clases)
- Agregar elementos decorativos
- Reorganizar layout visual
**NO modificar**:
- Scripts de JavaScript (solo comentar si es necesario)
- IDs de elementos (se usan en JavaScript)
- Atributos `data-*` que usan los scripts

### 3. Módulo de Usuarios - Archivo PHP
```
public/modules/users/index.php
```
**Puede modificar**:
- Todo el bloque `<style>` (líneas 40-95)
- Estructura HTML del contenido (líneas 98-148)
- Agregar clases CSS
**NO modificar**:
- Líneas 1-39 (PHP de sesión - CRÍTICO)
- Script al final (línea 150) - solo si es necesario

---

## 🎯 ÁREAS ESPECÍFICAS PARA MEJORAR

### DASHBOARD (`public/dashboard.html`)

#### 1. Header (Líneas 16-63)
**Elementos a mejorar**:
- `.header` - Barra superior fija
- `.header-content` - Contenedor del header
- `.logo` - Logo y texto "Telegan Admin"
- `.user-info` - Avatar y nombre de usuario
- `.menu-toggle` - Botón hamburguesa (móvil)
- `.tool-btn` - Botones de herramientas (búsqueda, tema)

**Mejoras sugeridas**:
- Añadir efectos `transform` en hover
- Mejorar espaciado y padding
- Añadir sombras más pronunciadas
- Mejorar transiciones de botones
- Mejorar diseño del avatar del usuario

#### 2. Sidebar (Líneas 65-120)
**Elementos a mejorar**:
- `.sidebar` - Menú lateral
- `.sidebar-menu` - Lista de menú
- `.menu-item` - Items del menú
- `.menu-link` - Enlaces del menú
- `.menu-icon` - Íconos del menú

**Mejoras sugeridas**:
- Añadir animaciones al expandir/colapsar
- Mejorar efecto hover en items
- Añadir transiciones suaves
- Mejorar espaciado entre items
- Añadir indicador visual de item activo más destacado

#### 3. Main Content Area (Líneas 122+)
**Elementos a mejorar**:
- `.main-content` - Área principal de contenido
- `.dashboard-grid` - Grid de widgets
- `.stat-card` - Tarjetas de estadísticas
- `.widget` - Widgets del dashboard
- `.card` - Tarjetas generales

**Mejoras sugeridas**:
- Mejorar layout del grid (más espaciado, mejor distribución)
- Añadir efectos de hover en tarjetas (transform: translateY)
- Mejorar sombras y elevación visual
- Añadir animaciones de entrada (fade-in, slide-in)
- Mejorar diseño de los números/estadísticas

#### 4. Widgets Específicos (Buscar en el HTML)
**Elementos a mejorar**:
- `.stats-grid` - Grid de estadísticas
- `.alert-card` - Tarjetas de alertas
- `.activity-item` - Items de actividad

**Mejoras sugeridas**:
- Añadir efectos de profundidad
- Mejorar jerarquía visual
- Añadir micro-interacciones

---

### MÓDULO DE USUARIOS (`public/modules/users/index.php`)

#### 1. Header del Módulo (Líneas 106-109)
**Elementos a mejorar**:
- `.module-header` - Header con gradiente
- Título y descripción

**Mejoras sugeridas**:
- Mejorar diseño del header (más moderno)
- Añadir efectos visuales al gradiente
- Mejorar tipografía del título

#### 2. Toolbar/Filtros (Líneas 114-126)
**Elementos a mejorar**:
- `.toolbar` - Barra de herramientas
- `.input` - Campo de búsqueda
- `.select` - Selector de filtro
- `.btn` - Botones de paginación

**Mejoras sugeridas**:
- Mejorar diseño de inputs (bordes, sombras, focus states)
- Añadir iconos a los inputs
- Mejorar diseño de botones
- Mejorar espaciado y alineación
- Añadir efectos hover más pronunciados

#### 3. Tabla de Usuarios (Líneas 129-147)
**Elementos a mejorar**:
- `.table-wrap` - Contenedor de tabla con scroll
- `.users` - Tabla de usuarios
- `.sticky` - Header de tabla fijo
- `th, td` - Celdas de tabla
- `.status-dot` - Indicador de estado

**Mejoras sugeridas**:
- Mejorar diseño de la tabla (más espaciado, mejor lectura)
- Añadir efecto hover más sutil en filas
- Mejorar diseño de headers de tabla
- Añadir efectos visuales al scroll
- Mejorar diseño de estados (activo/inactivo)
- Añadir alternancia de colores en filas (zebra striping)
- Mejorar diseño responsive (tabla en móvil)

#### 4. Paginación (Líneas 121-125)
**Elementos a mejorar**:
- `.pagination` - Contenedor de paginación
- Botones anterior/siguiente

**Mejoras sugeridas**:
- Mejorar diseño de botones de paginación
- Añadir números de página (si es necesario)
- Mejorar espaciado y alineación

---

## 🎨 ESTILOS ACTUALES A MEJORAR EN `public/css/styles.css`

### Variables CSS (Líneas 4-37)
**Mantener estas variables**, pero puedes:
- Ajustar valores de colores (dentro del rango de marca Telegan)
- Añadir nuevas variables si es necesario
- Ajustar valores de espaciado

### Componentes Clave para Mejorar

#### 1. Cards y Widgets
Buscar en CSS:
- `.card` - Tarjetas generales
- `.stat-card` - Tarjetas de estadísticas
- `.widget` - Widgets del dashboard

**Mejoras con transform**:
```css
.stat-card {
  transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.stat-card:hover {
  transform: translateY(-4px);
  box-shadow: var(--shadow-lg);
}
```

#### 2. Botones
Buscar en CSS:
- `.btn` - Botones generales
- `.button` - Botones alternativos
- `.tool-btn` - Botones de herramientas

**Mejoras sugeridas**:
- Añadir efectos de escala y sombra en hover
- Mejorar estados activos/focus
- Añadir transiciones suaves

#### 3. Inputs y Formularios
Buscar en CSS:
- `.input` - Campos de texto
- `.select` - Selectores

**Mejoras sugeridas**:
- Mejorar estados focus (border color, shadow)
- Añadir animaciones sutiles
- Mejorar diseño general

#### 4. Tablas
Buscar en CSS:
- `table` - Tablas
- `th, td` - Celdas

**Mejoras sugeridas**:
- Mejorar hover en filas
- Añadir zebra striping
- Mejorar diseño de headers

---

## ✨ TÉCNICAS DE ESTÉTICA DISPONIBLES

### 1. Transform (Ya implementado, puedes mejorar)
```css
/* Ejemplo: Efecto hover en tarjetas */
.card {
  transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.card:hover {
  transform: translateY(-4px) scale(1.02);
  box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15);
}
```

### 2. Animaciones CSS
```css
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.stat-card {
  animation: fadeIn 0.5s ease-out;
}
```

### 3. Gradientes y Sombras
```css
/* Mejorar profundidad visual */
.card {
  background: var(--bg-card);
  box-shadow: 
    0 2px 4px rgba(0, 0, 0, 0.1),
    0 8px 16px rgba(0, 0, 0, 0.05);
}
```

### 4. Transiciones Suaves
```css
/* Aplicar en todos los elementos interactivos */
.interactive {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
```

---

## 📐 PRINCIPIOS DE DISEÑO A SEGUIR

### 1. Mobile-First
- Diseñar primero para móviles (< 768px)
- Luego adaptar para desktop con `@media (min-width: 768px)`

### 2. iOS/Apple Style
- Espacios en blanco generosos
- Bordes redondeados sutiles (12px, 16px)
- Sombras suaves y naturales
- Transiciones suaves (0.3s ease)
- Tipografía clara (Inter)

### 3. Consistencia de Marca
- Usar colores Telegan definidos:
  - `--accent-primary: #6dbe45` (Verde menta)
  - `--accent-secondary: #4da1d9` (Azul cielo)
  - `--accent-tertiary: #a4d65e` (Verde lima)
  - `--accent-warm: #ffd166` (Amarillo suave)

### 4. Jerarquía Visual
- Usar sombras para profundidad
- Usar tamaños de fuente para importancia
- Usar color para destacar elementos clave

---

## ⚠️ REGLAS CRÍTICAS - LO QUE NO DEBE TOCAR

### 1. JavaScript (NO MODIFICAR)
- **NO** modificar archivos `.js` (ApiClient.js, dashboard.js, users.js)
- **NO** cambiar IDs de elementos en HTML (se usan en JS)
- **NO** cambiar nombres de clases que JavaScript busca

### 2. PHP (NO MODIFICAR en users/index.php)
- **NO** modificar líneas 1-39 (sesión PHP, seguridad)
- **NO** cambiar la estructura de verificación de autenticación
- **NO** modificar el token de sesión

### 3. Estructura de Datos (NO MODIFICAR)
- **NO** cambiar atributos `data-*` que usan los scripts
- **NO** cambiar IDs de elementos que se referencian en JS
- **NO** cambiar estructura de APIs

### 4. Sistema de Auth (NO TOCAR)
- **NO** modificar ningún archivo en `/auth/`
- El sistema de login está completo y funcional

---

## 📝 CHECKLIST PARA BOLT

### Dashboard
- [ ] Mejorar estilos del header
- [ ] Mejorar estilos del sidebar
- [ ] Mejorar tarjetas de estadísticas (hover, sombras)
- [ ] Añadir animaciones de entrada
- [ ] Mejorar diseño responsive (móvil)
- [ ] Añadir efectos transform en elementos interactivos
- [ ] Mejorar widgets y cards

### Módulo de Usuarios
- [ ] Mejorar header del módulo
- [ ] Mejorar toolbar/filtros
- [ ] Mejorar diseño de tabla (hover, zebra striping)
- [ ] Mejorar inputs y selects
- [ ] Mejorar botones de paginación
- [ ] Añadir efectos visuales sutiles
- [ ] Mejorar diseño responsive de tabla

### General
- [ ] Verificar que todas las mejoras funcionan en móvil
- [ ] Verificar que el tema oscuro funciona correctamente
- [ ] Verificar que las transiciones son suaves
- [ ] Mantener consistencia de colores Telegan
- [ ] No romper funcionalidad JavaScript

---

## 🔍 CÓMO ENCONTRAR ELEMENTOS EN EL CSS

### Para Dashboard
1. Abrir `public/css/styles.css`
2. Buscar: `.header`, `.sidebar`, `.card`, `.widget`, `.stat-card`
3. Modificar estilos existentes o añadir nuevos

### Para Módulo de Usuarios
1. Abrir `public/modules/users/index.php`
2. Modificar el bloque `<style>` (líneas 40-95)
3. O añadir estilos en `public/css/styles.css` con clases específicas

---

## 💡 EJEMPLOS DE MEJORAS ESPECÍFICAS

### Ejemplo 1: Mejorar Tarjetas del Dashboard
```css
/* En public/css/styles.css */
.stat-card {
  background: var(--bg-card);
  border-radius: 16px;
  padding: 1.5rem;
  border: 1px solid var(--border-color);
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: var(--shadow-sm);
}

.stat-card:hover {
  transform: translateY(-6px) scale(1.01);
  box-shadow: 0 12px 24px rgba(109, 190, 69, 0.15);
  border-color: var(--accent-primary);
}
```

### Ejemplo 2: Mejorar Tabla de Usuarios
```css
/* En public/modules/users/index.php - bloque <style> */
tbody tr {
  transition: background-color 0.2s ease, transform 0.1s ease;
}

tbody tr:hover {
  background: var(--bg-secondary);
  transform: translateX(4px);
}

tbody tr:nth-child(even) {
  background: rgba(109, 190, 69, 0.03);
}
```

### Ejemplo 3: Mejorar Inputs
```css
.input, .select {
  transition: all 0.3s ease;
  border: 2px solid var(--border-color);
}

.input:focus, .select:focus {
  border-color: var(--accent-primary);
  box-shadow: 0 0 0 3px rgba(109, 190, 69, 0.1);
  transform: translateY(-1px);
}
```

---

## 🚀 COMANDOS ÚTILES PARA PROBAR

1. **Ver cambios en tiempo real**: Abrir `public/dashboard.html` en navegador
2. **Ver módulo usuarios**: Abrir `public/modules/users/index.php` (requiere sesión)
3. **Probar responsive**: Usar herramientas de desarrollador (F12)

---

## 📞 CONTACTO

Si Bolt tiene dudas sobre qué puede o no modificar, consultar este documento primero.

**Regla de oro**: Si no está seguro, mejor preguntar antes de modificar.

---

**¡Listo para mejorar la estética!** 🎨✨

