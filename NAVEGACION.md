# 🧭 Guía de Navegación - Telegan Admin

## 🚀 Cómo Acceder al Sistema

### Opción 1: Página Principal (Recomendada)
```
http://localhost/TELEGAN_ADMIN/
```
o simplemente:
```
http://localhost/TELEGAN_ADMIN
```

### Opción 2: Acceso Directo al Dashboard
```
http://localhost/TELEGAN_ADMIN/public/dashboard.html
```

### Opción 3: Sistema de Autenticación
```
http://localhost/TELEGAN_ADMIN/auth/login.php
```

## 📋 Estructura de Navegación

### 🏠 Página Principal (`index.html`)
- **URL**: `/` o `/index.html`
- **Descripción**: Menú principal con acceso a todos los módulos
- **Funcionalidades**:
  - Resumen de estadísticas del sistema
  - Navegación a todos los módulos
  - Estado del sistema (activo/inactivo)

### 📊 Dashboard (`public/dashboard.html`)
- **URL**: `/public/dashboard.html`
- **Descripción**: Vista general con métricas y alertas
- **Funcionalidades**:
  - Estadísticas de usuarios, fincas, potreros
  - Alertas del sistema
  - Gráficos y métricas clave

### 👥 Gestión de Usuarios (`public/modules/users/`)
- **URL**: `/public/modules/users/`
- **Descripción**: Administración de usuarios del sistema
- **Estado**: 🚧 En desarrollo
- **Funcionalidades futuras**:
  - Listar y buscar usuarios
  - Crear/editar usuarios
  - Gestionar roles y permisos
  - Datos demográficos

### 🏡 Gestión de Fincas (`public/modules/farms/`)
- **URL**: `/public/modules/farms/`
- **Descripción**: Administración de fincas y potreros
- **Estado**: 🚧 En desarrollo
- **Funcionalidades futuras**:
  - Listar y buscar fincas
  - Crear/editar fincas
  - Gestionar potreros
  - Asociar usuarios con roles

### 🐄 Registros Ganaderos (`public/modules/records/`)
- **URL**: `/public/modules/records/`
- **Descripción**: Gestión de registros de ganado
- **Estado**: 🚧 En desarrollo
- **Funcionalidades futuras**:
  - Listar registros por potrero
  - Crear/editar registros
  - Ver historial de cambios
  - Exportar reportes

### 🔐 Sistema de Autenticación (`auth/`)
- **URL**: `/auth/login.php`
- **Descripción**: Login, registro y gestión de sesiones
- **Estado**: ✅ Funcional
- **Funcionalidades**:
  - Login de usuarios
  - Registro de nuevos usuarios
  - Recuperación de contraseña
  - Confirmación por email

### ⚙️ Configuración (`public/modules/settings/`)
- **URL**: `/public/modules/settings/`
- **Descripción**: Configuración del sistema
- **Estado**: 🚧 En desarrollo
- **Funcionalidades futuras**:
  - Configurar base de datos
  - Variables de entorno
  - Configurar emails
  - Gestionar seguridad

## 🔗 APIs Disponibles

### 📊 API Dashboard
- **URL**: `/public/api/dashboard.php`
- **Método**: GET
- **Descripción**: Obtiene estadísticas del sistema

### 🚨 API Alertas
- **URL**: `/public/api/alerts.php`
- **Método**: GET
- **Descripción**: Obtiene alertas del sistema

### 🔍 API Búsqueda
- **URL**: `/public/api/search.php`
- **Método**: GET
- **Descripción**: Búsqueda general en el sistema

### 🏡 API Fincas
- **URL**: `/public/api/farm-details.php`
- **Método**: GET
- **Descripción**: Detalles de fincas

### 👥 API Usuarios-Fincas
- **URL**: `/public/api/user-farms.php`
- **Método**: GET
- **Descripción**: Asociaciones usuario-finca

### 📈 API Operacional
- **URL**: `/public/api/operational.php`
- **Método**: GET
- **Descripción**: Datos operacionales

## 🎯 Flujo de Navegación Recomendado

### Para Nuevos Usuarios:
1. **Inicio**: `http://localhost/TELEGAN_ADMIN/`
2. **Registro**: Ir a "Sistema de Auth" → "Registro"
3. **Confirmar Email**: Seguir enlace de confirmación
4. **Login**: Iniciar sesión
5. **Dashboard**: Explorar métricas del sistema

### Para Usuarios Existentes:
1. **Inicio**: `http://localhost/TELEGAN_ADMIN/`
2. **Login**: Ir a "Sistema de Auth" → "Login"
3. **Dashboard**: Ver resumen del sistema
4. **Módulos**: Explorar funcionalidades disponibles

### Para Desarrolladores:
1. **APIs**: Probar endpoints en `/public/api/`
2. **Logs**: Revisar logs del sistema
3. **Configuración**: Verificar variables de entorno

## 🛠️ Solución de Problemas

### Error 404 - Página no encontrada
- Verificar que estás usando la URL correcta
- Asegurarte de que el servidor web está funcionando
- Revisar la configuración de Apache/Nginx

### Error de Conexión a Base de Datos
- Verificar archivo `.env`
- Comprobar credenciales de base de datos
- Revisar que PostgreSQL esté funcionando

### APIs no responden
- Verificar que el middleware de seguridad esté configurado
- Revisar logs de PHP
- Comprobar que las APIs estén en `/public/api/`

## 📱 Navegación Móvil

El sistema está diseñado con enfoque **mobile-first**, por lo que:
- Todas las páginas son responsivas
- Los menús se adaptan a pantallas pequeñas
- Los botones tienen tamaño táctil adecuado
- La navegación es intuitiva en dispositivos móviles

## 🔄 Actualizaciones Futuras

- **Módulos en desarrollo** se activarán gradualmente
- **Nuevas funcionalidades** se añadirán según prioridades
- **Mejoras de UX** basadas en feedback de usuarios
- **Optimizaciones de rendimiento** continuas

---

**💡 Tip**: Siempre comienza desde la página principal (`/`) para tener una visión completa del sistema y acceder fácilmente a todas las funcionalidades.
