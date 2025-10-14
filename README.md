# Telegan Admin Panel

Panel administrativo para gestión de base de datos PostgreSQL de agricultores.

## Características

- **Frontend**: HTML/CSS/JS puro con diseño mobile-first iOS style
- **Backend**: PHP 8.1+ VANILLA (sin frameworks, sin Composer, solo PDO nativo)
- **Base de datos**: PostgreSQL 14+ con PostGIS (ya existente)
- **Routing**: Sistema de routing manual sin frameworks
- **Sesiones**: PHP sessions nativas
- **Dashboard**: Estadísticas en tiempo real con conexión directa a BD
- **Despliegue**: Hosting convencional compatible

## Estructura del Proyecto

```
telegan-admin/
├── src/
│   ├── Config/
│   │   ├── Database.php
│   │   └── Session.php
│   └── Models/
│       └── Dashboard.php
├── api/
│   ├── dashboard.php
│   ├── health.php
│   └── router.php
├── public/
│   ├── css/
│   │   └── styles.css
│   ├── js/
│   │   ├── ApiClient.js
│   │   └── dashboard.js
│   └── dashboard.html
├── index.php
├── .htaccess
├── env.example
├── database-schema.sql
├── .cursorrules
└── README.md
```

## Instalación

### 1. Requisitos

- PHP 8.1 o superior
- PostgreSQL con extensión pdo_pgsql
- Servidor web (Apache/Nginx)
- **NO requiere Composer**

### 2. Configuración

1. **Clonar o descargar el proyecto**
   ```bash
   # Si usas Git
   git clone [url-del-repositorio]
   cd telegan-admin
   ```

2. **Sin dependencias externas**
   ```bash
   # ¡No necesitas instalar nada!
   # El proyecto usa solo PHP vanilla
   ```

3. **Configurar variables de entorno**
   ```bash
   cp env.example .env
   ```
   
   Editar el archivo `.env` con tu configuración:
   ```env
   DB_HOST=localhost
   DB_PORT=5432
   DB_NAME=telegan_agricultores
   DB_USER=tu_usuario
   DB_PASSWORD=tu_password
   JWT_SECRET=tu_jwt_secret_muy_seguro
   ```

4. **Configurar base de datos**
   
   La base de datos PostgreSQL ya debe existir con el esquema definido en `database-schema.sql`.
   
   **IMPORTANTE**: NO ejecutar el script de base de datos, solo verificar que las tablas existan:
   - `usuario`
   - `finca` 
   - `potrero`
   - `registro_ganadero`
   - `v_usuarios_fincas` (vista)

5. **Configurar servidor web**
   
   Para Apache, asegúrate de que el módulo `mod_rewrite` esté habilitado.
   
   Para Nginx, configura las reglas de rewrite:
   ```nginx
   location / {
       try_files $uri $uri/ /index.php?$query_string;
   }
   ```

## Uso

### 1. Acceso al Dashboard

1. Navega a tu dominio o `http://localhost:8000`
2. Serás redirigido automáticamente al dashboard principal
3. El dashboard mostrará estadísticas en tiempo real de la base de datos

### 2. Dashboard Principal

El dashboard muestra:
- **Estado de conexión**: Verificación en tiempo real de la conexión a PostgreSQL
- **Usuarios Registrados**: Total de usuarios con fincas asociadas (usando `v_usuarios_fincas`)
- **Fincas Activas**: Total de fincas en estado ACTIVO
- **Potreros**: Total de potreros activos
- **Registros Ganaderos**: Total de registros en el sistema
- **Información del Sistema**: Versión de PostgreSQL y estado de conexión

### 3. Funcionalidades

- **Actualización automática**: Los datos se actualizan cada 30 segundos
- **Botón de actualización manual**: Para refrescar datos inmediatamente
- **Diseño responsive**: Funciona perfectamente en móviles y desktop
- **Notificaciones**: Feedback visual del estado de las operaciones

## API Endpoints

### Dashboard (Públicos)

- `GET /api/dashboard` - Obtener estadísticas del dashboard
  - Retorna: total de usuarios, fincas, potreros, registros ganaderos
  - Incluye: información de conexión a base de datos
  - Formato: JSON con success/error

- `GET /api/health` - Health check del sistema
  - Retorna: estado de conexión a BD, info PHP, memoria
  - Formato: JSON con status healthy/unhealthy

### Sistema de Routing

- **Routing Manual**: Sin frameworks, manejo directo en `index.php`
- **Archivos específicos**: `/api/dashboard.php`, `/api/health.php`
- **Router general**: `/api/router.php` para endpoints dinámicos
- **404 handling**: Páginas no encontradas con redirección al dashboard

## Desarrollo

### Estructura de Archivos

#### Backend (PHP Vanilla)

- **index.php**: Punto de entrada y routing manual
- **Database.php**: Conexión a PostgreSQL con PDO nativo
- **Dashboard.php**: Modelo para estadísticas del dashboard
- **Session.php**: Manejo de sesiones PHP nativas
- **router.php**: Sistema de routing manual
- **health.php**: Endpoint de health check

#### Frontend (JavaScript)

- **ApiClient.js**: Cliente HTTP para comunicación con la API
- **auth.js**: Manejo de formularios de autenticación
- **dashboard.js**: Funcionalidad del dashboard

#### Estilos (CSS)

- **styles.css**: Estilos mobile-first con variables CSS
- **Variables Telegan**: Colores y gradientes de marca
- **Responsive**: Adaptación a diferentes tamaños de pantalla

### Personalización

#### Colores y Marca

Edita las variables CSS en `public/css/styles.css`:

```css
:root {
  --accent-primary: #6dbe45; /* Verde menta */
  --accent-secondary: #4da1d9; /* Azul cielo */
  --accent-tertiary: #a4d65e; /* Verde lima */
  --accent-warm: #ffd166; /* Amarillo suave */
}
```

#### Funcionalidades

Para agregar nuevas funcionalidades:

1. **Backend**: Agregar modelos, servicios y rutas en `/api/src/`
2. **Frontend**: Extender `ApiClient.js` y crear nuevos archivos JS
3. **UI**: Agregar páginas HTML y estilos CSS

## Despliegue

### Hosting Convencional

1. Sube todos los archivos al servidor
2. Configura las variables de entorno en `.env`
3. **¡No necesitas instalar dependencias!** (PHP vanilla)
4. Asegúrate de que el servidor web apunte al directorio raíz
5. Configura SSL si es necesario

### Configuración de Producción

En producción, cambia en `.env`:
```env
APP_ENV=production
```

Y considera:
- Usar un JWT_SECRET más seguro
- Configurar backup de base de datos
- Implementar logging de errores
- Configurar rate limiting

## Seguridad

- **Sesiones PHP nativas** para autenticación
- **Validación de entrada** en frontend y backend
- **Headers de seguridad** configurados
- **CORS configurado** apropiadamente
- **PDO preparado** para prevenir SQL injection
- **Manejo de errores** sin exposición de información sensible

## Troubleshooting

### Problemas Comunes

1. **Error 500**: Verifica la configuración de PHP y base de datos
2. **CORS Error**: Verifica la configuración del servidor web
3. **Token expirado**: El token expira en 1 hora por defecto
4. **Base de datos**: Verifica que la tabla `admins` exista

### Logs

Para debugging, habilita logs en PHP y revisa los logs del servidor web.

## Próximas Funcionalidades

- Gestión de agricultores
- Gestión de parcelas
- Gestión de cultivos
- Reportes y estadísticas
- Exportación de datos
- Sistema de roles y permisos

## Soporte

Para soporte técnico o consultas sobre el proyecto, contacta al equipo de desarrollo.

---

**Telegan Admin Panel** - Desarrollado para la gestión eficiente de datos agrícolas.
