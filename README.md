# Telegan Admin Panel

Panel administrativo estático (HTML/CSS/JS) para administrar (CRUD completo) base de datos PostgreSQL de agricultores. Se desplegará en hosting convencional con Apache y PHP.

## Arquitectura del Proyecto

### Stack Tecnológico

- **Frontend**: HTML/CSS/JS puro - Mobile-first
- **Backend**: PHP 8.1+ PURO (SIN frameworks, sin Slim, sin Laravel, sin Composer)
- **Base de datos**: PostgreSQL 14 (ya existe, NO crear tablas, solo cadena de conexión)
- **Servidor**: Apache en hosting convencional
- **ORM**: PDO nativo de PHP para PostgreSQL
- **Sin build tools**: Todo debe funcionar directo sin compilación

### Estructura de Directorios Real

```
TELEGAN_ADMIN/
├── index.php                 # Router principal del sistema
├── index.html                # Página de inicio/navegación
├── .htaccess                 # Rewrite rules para Apache
├── env                       # Variables de entorno (NO subir a Git)
├── env.example               # Ejemplo de variables de entorno
├── database-schema.sql       # Schema de BD (solo referencia, NO ejecutar)
│
├── auth/                     # Módulo de autenticación AUTÓNOMO
│   ├── login.php            # Página de login
│   ├── register.php         # Página de registro
│   ├── confirm.php          # Confirmación de email
│   ├── forgot-password.php  # Recuperación de contraseña
│   ├── config/
│   │   ├── Database.php     # Conexión BD (independiente)
│   │   ├── Security.php     # Seguridad robusta
│   │   └── Email.php        # Sistema de emails
│   ├── assets/
│   │   ├── css/auth.css     # Estilos específicos de auth
│   │   └── js/auth.js       # JavaScript de auth
│   └── templates/emails/    # Templates HTML de emails
│
├── public/                   # Directorio público (servido directamente)
│   ├── dashboard.html        # Dashboard principal (HTML estático)
│   ├── dashboard.php         # Dashboard con sesión PHP (alternativo)
│   ├── css/
│   │   └── styles.css       # Sistema de estilos principal (CSS VANILLA)
│   ├── js/
│   │   ├── config.js        # Configuración del frontend
│   │   ├── ApiClient.js     # Cliente HTTP para APIs
│   │   ├── dashboard.js     # Lógica del dashboard
│   │   └── auth.js          # Lógica de autenticación frontend
│   │
│   ├── api/                 # APIs REST (archivos PHP)
│   │   ├── dashboard.php    # API de estadísticas del dashboard
│   │   ├── users-list.php   # API de lista de usuarios
│   │   ├── user-farms.php   # API de fincas de usuario
│   │   ├── farm-details.php # API de detalles de finca
│   │   ├── search.php       # API de búsqueda
│   │   ├── alerts.php       # API de alertas
│   │   ├── operational.php # API de operaciones
│   │   └── init-session.php # API de inicialización de sesión
│   │
│   └── modules/              # Módulos funcionales del sistema
│       ├── _layout.php      # Layout común (header, sidebar, bottom-nav)
│       ├── users/
│       │   ├── index.php    # Gestión de usuarios (pendiente de migrar al layout común)
│       │   └── users.js     # JavaScript del módulo de usuarios
│       ├── system-users/
│       │   ├── index.php    # Gestión de administradores internos
│       │   └── system-users.js
│       ├── providers/
│       │   ├── index.php    # Gestión de proveedores satelitales/climáticos
│       │   └── providers.js
│       ├── indices/
│       │   ├── index.php    # Catálogo de índices satelitales
│       │   └── indices.js
│       ├── regions/
│       │   ├── index.php    # Gestión de regiones con Leaflet + WKT
│       │   └── regions.js
│       └── thresholds/
│           ├── index.php    # Umbrales por región/índice
│           └── thresholds.js
│
└── src/                      # Código PHP del backend (clases)
    ├── Config/
    │   ├── Database.php     # Clase de conexión a BD
    │   ├── Security.php     # Headers de seguridad
    │   ├── Session.php      # Manejo de sesiones
    │   ├── AppToken.php     # Sistema de tokens de aplicación
    │   └── ApiAuth.php      # Autenticación de APIs
    ├── Middleware/
    │   └── SecurityMiddleware.php  # Middleware de seguridad gradual
    ├── Models/
    │   └── Dashboard.php    # Modelo de datos del dashboard
    └── Services/
        └── ApiMasking.php   # Enmascaramiento de endpoints (opcional)
```

## Sistema de Routing - Cómo Funciona `index.php`

### Punto de Entrada Principal

El archivo `index.php` en la raíz es el **router principal** del sistema. Funciona así:

1. **Captura todas las peticiones** que llegan al servidor
2. **Analiza la ruta** (`$_SERVER['REQUEST_URI']`)
3. **Decide qué hacer** según el patrón de la URL:

#### Rutas que Maneja `index.php`:

```php
// 1. Página principal o index.php → Redirige a index.html
if (empty($path) || $path === 'index.php') {
    header('Location: index.html');
    exit();
}

// 2. Rutas que empiezan con 'api/' → Busca archivos en public/api/
elseif (strpos($path, 'api/') === 0) {
    // Ejemplo: /api/dashboard → public/api/dashboard.php
    // Ejemplo: /api/users-list → public/api/users-list.php
    $apiPath = substr($path, 4); // Remover 'api/'
    include __DIR__ . '/public/api/' . $apiPath . '.php';
}

// 3. Archivos estáticos → Los sirve directamente
else {
    // Ejemplo: /public/dashboard.html → Sirve el archivo HTML
    // Ejemplo: /public/css/styles.css → Sirve el CSS
    // Si no existe → Error 404
}
```

### Flujo de Peticiones

```
Usuario navega a: https://dominio.com/api/dashboard
                    ↓
              index.php captura la petición
                    ↓
        Detecta que empieza con "api/"
                    ↓
    Busca: public/api/dashboard.php
                    ↓
    Incluye y ejecuta el archivo PHP
                    ↓
    El archivo PHP hace consultas a BD y retorna JSON
```

### ¿Por Qué la API está en `public/api/`?

**Razón**: Para que las URLs sean limpias y directas:
- `https://dominio.com/api/dashboard` → Funciona directo
- No necesitas `/public/api/dashboard` (más largo)
- El `.htaccess` + `index.php` redirigen automáticamente

## Módulos en `public/modules/`

### Concepto de Módulos

Los módulos son **páginas funcionales completas** que extienden el dashboard. Cada módulo tiene su propia página HTML/PHP y su propio JavaScript.

### Estructura de un Módulo

```
public/modules/
└── users/
    ├── index.php     # Página principal del módulo (puede ser PHP o HTML)
    └── users.js      # JavaScript específico del módulo
```

### Módulos Actuales

1. **`users/`** - Gestión de usuarios (en proceso de migración al layout común).
2. **`system-users/`** - Administración de personal interno.
3. **`providers/`** - CRUD completo para proveedores satelitales.
4. **`indices/`** - Catálogo maestro de índices satelitales.
5. **`regions/`** - Definición de regiones con geometrías WKT y Leaflet.
6. **`thresholds/`** - Administración de umbrales por región e índice.

### Layout común para módulos (`public/modules/_layout.php`)

Todos los módulos nuevos o migrados deben compartir la misma estructura visual. Para lograrlo:

1. Inicia la sesión y valida permisos como siempre (`session_start`, `Security::init`).
2. Define las variables básicas antes de requerir el layout:
   - `$layoutActive`: identifica qué ítem del menú queda resaltado (ej. `'providers'`).
   - `$moduleTitle` y `$moduleSubtitle`: encabezado principal de la vista.
3. Usa `ob_start()` para capturar fragmentos opcionales:
   - `$moduleHead`: estilos extra, scripts globales o CDNs específicos del módulo.
   - `$moduleContent`: HTML del cuerpo principal (toolbars, tablas, modales, etc.).
   - `$moduleScripts`: scripts al final, normalmente `<script type="module" src="./mi-modulo.js"></script>`.
4. `require_once '../_layout.php';` renderiza toda la página reutilizando header, sidebar, bottom-nav y el botón de “Cerrar sesión”.

Detalles adicionales del layout:
- Tema oscuro por defecto: si no existe preferencia guardada se fuerza `data-theme="dark"`.
- Persistencia de sidebar colapsado: se lee/escribe en `localStorage` (`sidebarCollapsed`).
- El layout ya incluye `theme-common.js`, por lo que los módulos solo deben inyectar el JS particular.
- El botón de “Cerrar sesión” redirige a `auth/logout.php`.

### Cómo crear un módulo nuevo rápido

```php
<?php
session_start();
require_once '../../../src/Config/Security.php';
Security::init();

if (!($_SESSION['admin_logged_in'] ?? false)) {
    header('Location: ../../../auth/login.php');
    exit;
}

$layoutActive = 'mi-modulo';
$moduleTitle = 'Título del módulo';
$moduleSubtitle = 'Resumen o descripción';

ob_start();
?>
<style>
/* Tus estilos específicos */
</style>
<?php $moduleHead = ob_get_clean();

ob_start();
?>
<div class="mi-contenido">Hola Telegan</div>
<?php $moduleContent = ob_get_clean();

ob_start();
?>
<script type="module" src="./mi-modulo.js"></script>
<?php $moduleScripts = ob_get_clean();

require_once '../_layout.php';
```

Con este patrón garantizamos consistencia visual y reducimos código duplicado en cada módulo.

### Cómo Funcionan los Módulos

```html
<!-- public/modules/users/index.php -->
<?php
// 1. Inicia sesión PHP
session_start();

// 2. Verifica autenticación
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: ../../../auth/login.php');
    exit;
}

// 3. Incluye headers de seguridad
require_once '../../../src/Config/Security.php';
Security::init();

// 4. Pasa token de sesión al frontend
$sessionToken = $_SESSION['session_token'] ?? null;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Usuarios</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
    <!-- HTML del módulo -->
    <script>
        // Token disponible para ApiClient
        window.sessionToken = '<?php echo $sessionToken; ?>';
    </script>
    <script type="module" src="../../js/ApiClient.js"></script>
    <script src="users.js"></script>
</body>
</html>
```

## APIs en `public/api/`

### Concepto de APIs

Los archivos en `public/api/` son **endpoints REST** que retornan JSON. Cada archivo PHP maneja una funcionalidad específica.

### APIs Actuales

1. **`dashboard.php`** - Estadísticas generales
   - `GET /api/dashboard`
   - Retorna: total usuarios, fincas, potreros, registros
   - Usa: `src/Models/Dashboard.php`

2. **`users-list.php`** - Lista de usuarios
   - `GET /api/users-list`
   - Parámetros: `?page=1&limit=10&search=nombre`
   - Retorna: Array de usuarios con paginación

3. **`user-farms.php`** - Fincas de un usuario
   - `GET /api/user-farms?id_usuario=123`
   - Retorna: Array de fincas asociadas

4. **`farm-details.php`** - Detalles de finca
   - `GET /api/farm-details?id_finca=456`
   - Retorna: Datos completos de finca con potreros

5. **`search.php`** - Búsqueda global
   - `GET /api/search?q=termino`
   - Retorna: Resultados de usuarios, fincas, potreros

6. **`alerts.php`** - Alertas del sistema
   - `GET /api/alerts`
   - Retorna: Alertas (usuarios sin finca, inactivos, etc.)

7. **`operational.php`** - Operaciones CRUD
   - `POST /api/operational` - Crear/Actualizar
   - `DELETE /api/operational` - Eliminar (soft delete)

8. **`init-session.php`** - Inicializar sesión
   - `POST /api/init-session`
   - Retorna: Token de sesión para usar en otras APIs

### Estructura Típica de una API

```php
<?php
// public/api/dashboard.php

// 1. Incluir middleware de seguridad
require_once '../../src/Middleware/SecurityMiddleware.php';
SecurityMiddleware::init();

// 2. Configurar headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// 3. Manejar preflight (CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 4. Validar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

// 5. Lógica de negocio
require_once '../../src/Config/Database.php';
require_once '../../src/Models/Dashboard.php';

try {
    $db = Database::getConnection();
    $dashboard = new Dashboard($db);
    $stats = $dashboard->getStats();
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener estadísticas'
    ]);
}
?>
```

## Sistema de Estilos - CSS Vanilla con Variables

### ⚠️ IMPORTANTE PARA DESARROLLADORES DE ESTÉTICA

**Este proyecto NO usa Tailwind CSS, NO usa Bootstrap, NO usa ningún framework CSS.**

**Usa CSS VANILLA puro con variables CSS nativas.**

### Ubicación de Estilos

- **Archivo principal**: `public/css/styles.css`
- **Archivo auth específico**: `auth/assets/css/auth.css`

### Sistema de Variables CSS

El proyecto usa **variables CSS nativas** (`:root`) para el sistema de temas:

```css
/* public/css/styles.css */
:root {
  /* Colores de fondo */
  --bg-primary: #ffffff;
  --bg-secondary: #f8f9fa;
  --bg-card: #ffffff;
  
  /* Colores de texto */
  --text-primary: #1a1a1a;
  --text-secondary: #6b7280;
  --text-tertiary: #9ca3af;
  
  /* Colores de marca Telegan */
  --accent-primary: #6dbe45;    /* Verde menta */
  --accent-secondary: #4da1d9;  /* Azul cielo */
  --accent-tertiary: #a4d65e;   /* Verde lima */
  --accent-warm: #ffd166;        /* Amarillo suave */
  
  /* Gradientes */
  --gradient-primary: linear-gradient(135deg, #6dbe45 0%, #4da1d9 100%);
  
  /* Sombras */
  --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.04);
  --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.07);
  --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
}

/* Tema oscuro */
[data-theme="dark"] {
  --bg-primary: #0a0a0a;
  --bg-secondary: #141414;
  --bg-card: #1a1a1a;
  --text-primary: #ffffff;
  /* ... */
}
```

### Diseño Mobile-First

- **Enfoque**: Diseñar primero para móviles, luego para desktop
- **Breakpoints**: Usar `@media (min-width: 768px)` para desktop
- **Touch targets**: Mínimo 44x44px para elementos interactivos

### Estilos iOS/Apple Style

- Espacios en blanco generosos
- Bordes redondeados sutiles (border-radius: 12px, 16px)
- Sombras suaves y naturales
- Transiciones suaves (0.3s ease)
- Tipografía Inter (Google Fonts)

### Cómo Modificar Estilos

**SOLO modificar `public/css/styles.css`** para cambios de estética:

1. **Colores**: Cambiar valores de variables en `:root`
2. **Espaciado**: Ajustar padding, margin, gap
3. **Tipografía**: Cambiar font-size, font-weight, line-height
4. **Componentes**: Modificar clases existentes (.card, .button, etc.)
5. **Layout**: Ajustar grid, flexbox, positioning

**NO modificar**:
- Estructura HTML (excepto si es necesario para estilos)
- Lógica JavaScript (excepto si afecta estilos dinámicos)
- Lógica PHP (nunca)

## Frontend JavaScript - Cómo Funciona

### Archivos JavaScript Principales

1. **`public/js/config.js`** - Configuración global
   ```javascript
   export class AppConfig {
       static apiBaseUrl = 'api'; // Rutas relativas
   }
   ```

2. **`public/js/ApiClient.js`** - Cliente HTTP
   - Maneja todas las peticiones a las APIs
   - Gestiona tokens de sesión
   - Maneja errores y respuestas JSON

3. **`public/js/dashboard.js`** - Lógica del dashboard
   - Carga estadísticas cada 30 segundos
   - Actualiza widgets con datos de la API
   - Maneja eventos del dashboard

4. **`public/js/auth.js`** - Autenticación frontend
   - Maneja formularios de login/registro
   - Validación de inputs
   - Redirecciones después de auth

### Módulos ES6

**Todos los JS usan módulos ES6** (`import`/`export`):

```html
<script type="module" src="js/ApiClient.js"></script>
<script type="module" src="js/dashboard.js"></script>
```

### Sin localStorage

**IMPORTANTE**: El proyecto NO usa `localStorage` ni `sessionStorage` (restricción del proyecto).

Usa:
- Variables en memoria (`window.adminToken`)
- Cookies (si es necesario)
- Variables desde PHP (inyectadas en el HTML)

## Base de Datos - PostgreSQL

### ⚠️ IMPORTANTE

**NO crear, modificar o eliminar tablas.** La base de datos ya existe. Solo hacer SELECT, INSERT, UPDATE (soft delete).

### Tablas Principales

- `usuario` - Usuarios del sistema
- `finca` - Fincas de los agricultores
- `potrero` - Potreros dentro de fincas
- `registro_ganadero` - Registros de ganado
- `demografia_usuario` - Datos demográficos
- `usuario_finca` - Relación muchos a muchos

### Conexión a BD

```php
// src/Config/Database.php
class Database {
    public static function getConnection() {
        // Lee variables de .env
        // Crea conexión PDO a PostgreSQL
        // Retorna instancia PDO
    }
}
```

## Autenticación - Módulo Separado

### Ubicación

El módulo de autenticación está en `/auth/` y es **completamente independiente**.

### Flujo de Autenticación

1. Usuario va a `auth/login.php`
2. Ingresa credenciales
3. PHP valida contra tabla `admin_users` (en BD)
4. Si es válido, crea sesión PHP (`$_SESSION`)
5. Genera token de sesión
6. Redirige a `public/dashboard.html` o `public/modules/users/`

### Sesiones PHP

- Las sesiones se manejan con PHP nativo (`session_start()`)
- El token se inyecta en el HTML para que JavaScript lo use
- Los módulos PHP verifican `$_SESSION['admin_logged_in']`

## Contexto para Desarrolladores de Estética

### ✅ LO QUE PUEDES MODIFICAR (SOLO ESTÉTICA)

1. **Archivo CSS principal**: `public/css/styles.css`
   - Variables de color, tipografía, espaciado
   - Estilos de componentes (.card, .button, .header, etc.)
   - Animaciones y transiciones
   - Layout responsive (grid, flexbox)

2. **HTML (solo estructura visual)**:
   - Agregar clases CSS
   - Reorganizar elementos (si no afecta funcionalidad)
   - Agregar elementos decorativos (íconos, imágenes)

3. **JavaScript (solo para efectos visuales)**:
   - Animaciones
   - Toggle de temas
   - Efectos hover/focus
   - Validación visual de formularios

### ❌ LO QUE NO DEBES MODIFICAR

1. **Lógica PHP**: Nada en `/src/` ni `/public/api/`
2. **Estructura de APIs**: No cambiar endpoints ni respuestas JSON
3. **Lógica de negocio**: No modificar `ApiClient.js`, `dashboard.js` (lógica)
4. **Routing**: No modificar `index.php` ni `.htaccess`
5. **Autenticación**: No modificar módulo `/auth/`

### Páginas Principales para Estilizar

1. **Dashboard**: `public/dashboard.html`
2. **Módulo de Usuarios**: `public/modules/users/index.php`
3. **Login**: `auth/login.php`
4. **Registro**: `auth/register.php`
5. **Página de Inicio**: `index.html`

### Colores de Marca Telegan (Definidos)

```css
--accent-primary: #6dbe45;    /* Verde menta - Principal */
--accent-secondary: #4da1d9;  /* Azul cielo - Secundario */
--accent-tertiary: #a4d65e;   /* Verde lima - Terciario */
--accent-warm: #ffd166;        /* Amarillo suave - Acento */
```

**Usa estos colores** para mantener consistencia de marca.

## Instalación y Configuración

### Requisitos

- PHP 8.1+
- PostgreSQL 14+ (con extensión `pdo_pgsql`)
- Apache (con `mod_rewrite`)
- **NO requiere**: Composer, Node.js, npm, frameworks

### Configuración Inicial

1. **Clonar repositorio**
   ```bash
   git clone https://github.com/togues/telegan_admin.git
   cd telegan_admin
   ```

2. **Configurar variables de entorno**
   ```bash
   cp env.example .env
   # Editar .env con credenciales de BD
   ```

3. **Verificar base de datos**
   - La BD ya debe existir
   - Solo verificar que las tablas estén creadas
   - **NO ejecutar** `database-schema.sql` (solo referencia)

4. **Configurar Apache**
   - El `.htaccess` ya está configurado
   - Asegurar que `mod_rewrite` esté habilitado

5. **¡Listo!**
   - Navegar a `http://localhost/index.html`
   - O a `http://localhost/public/dashboard.html`

## Flujo de Desarrollo

### Agregar Nueva Funcionalidad

1. **Backend (API)**:
   - Crear archivo en `public/api/nueva-funcion.php`
   - Usar clases de `src/` para lógica
   - Retornar JSON

2. **Frontend (Página)**:
   - Crear HTML en `public/modules/` o modificar existente
   - Agregar JavaScript para llamar a la API
   - Usar `ApiClient.js` para hacer peticiones

3. **Estilos**:
   - Modificar `public/css/styles.css`
   - Usar variables CSS existentes
   - Mantener diseño mobile-first

## Troubleshooting

### Problemas Comunes

1. **404 en rutas de API**
   - Verificar que `index.php` esté en la raíz
   - Verificar que `.htaccess` esté configurado
   - Verificar que `mod_rewrite` esté habilitado

2. **Error de conexión a BD**
   - Verificar variables en `.env`
   - Verificar que PostgreSQL esté corriendo
   - Verificar que extensión `pdo_pgsql` esté instalada

3. **CORS Error**
   - Verificar headers en APIs (`Access-Control-Allow-Origin`)
   - Verificar que las rutas sean relativas (`api` no `/api`)

4. **Sesiones no funcionan**
   - Verificar que `session_start()` esté al inicio del PHP
   - Verificar permisos de escritura en directorio de sesiones

## Referencias

- **Schema de BD**: Ver `database-schema.sql` (solo referencia)
- **Variables de entorno**: Ver `env.example`
- **Reglas de routing**: Ver `index.php`
- **Sistema de seguridad**: Ver `src/Middleware/SecurityMiddleware.php`

---

**Telegan Admin Panel** - Panel administrativo para gestión ganadera.

**Desarrollado con**: PHP Vanilla + PostgreSQL + HTML/CSS/JS puro.

**Sin frameworks, sin dependencias externas, sin build tools.**
