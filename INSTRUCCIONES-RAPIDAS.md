# 🚀 Instrucciones Rápidas - Telegan Admin

## ✅ ¿Cómo Acceder al Sistema?

### Opción 1: Página Principal (RECOMENDADA)
```
http://localhost/TELEGAN_ADMIN/
```
**¿Qué verás?**
- Menú principal con todas las secciones
- Estadísticas del sistema
- Navegación clara a todos los módulos

### Opción 2: Dashboard Directo
```
http://localhost/TELEGAN_ADMIN/dashboard
```
**¿Qué verás?**
- Vista detallada con métricas
- Gráficos y estadísticas
- Alertas del sistema

### Opción 3: Sistema de Login
```
http://localhost/TELEGAN_ADMIN/auth/login.php
```
**¿Qué verás?**
- Formulario de login
- Opción de registro
- Recuperación de contraseña

## 🧭 Navegación Principal

### Desde la Página Principal (`/`)
1. **📊 Dashboard** → Vista general con estadísticas
2. **👥 Gestión de Usuarios** → Administrar usuarios (en desarrollo)
3. **🏡 Gestión de Fincas** → Administrar fincas (en desarrollo)
4. **🐄 Registros Ganaderos** → Gestionar registros (en desarrollo)
5. **🔐 Sistema de Auth** → Login y registro
6. **⚙️ Configuración** → Configuración del sistema (en desarrollo)

### Desde el Dashboard
- **Sidebar izquierdo** → Navegación a módulos
- **Navegación móvil** → Menú inferior en móviles
- **Pestañas** → Cambiar entre "Datos Operativos" y "Alertas"

## 🔗 URLs Importantes

| Función | URL | Estado |
|---------|-----|--------|
| **Página Principal** | `/` | ✅ Funcional |
| **Dashboard** | `/dashboard` | ✅ Funcional |
| **APIs** | `/api/dashboard.php` | ✅ Funcional |
| **Login** | `/auth/login.php` | ✅ Funcional |
| **Usuarios** | `/modules/users/` | 🚧 En desarrollo |
| **Fincas** | `/modules/farms/` | 🚧 En desarrollo |
| **Registros** | `/modules/records/` | 🚧 En desarrollo |

## 🛠️ Solución de Problemas

### ❌ Error 404 - Página no encontrada
**Solución:**
1. Verifica que estés usando la URL correcta
2. Asegúrate de que el servidor web esté funcionando
3. Revisa que el archivo `.htaccess` esté presente

### ❌ APIs no responden
**Solución:**
1. Verifica que las APIs estén en `/public/api/`
2. Revisa los logs de PHP
3. Comprueba la conexión a la base de datos

### ❌ No se cargan las estadísticas
**Solución:**
1. Verifica que la base de datos esté funcionando
2. Revisa el archivo `.env` con las credenciales
3. Comprueba que las APIs respondan correctamente

## 📱 Navegación Móvil

El sistema está optimizado para móviles:
- **Menú hamburguesa** en la esquina superior izquierda
- **Navegación inferior** con iconos
- **Diseño responsive** que se adapta a cualquier pantalla

## 🔄 Flujo de Trabajo Recomendado

### Para Explorar el Sistema:
1. **Inicio**: Ve a `http://localhost/TELEGAN_ADMIN/`
2. **Explorar**: Haz clic en "Ir al Dashboard"
3. **Navegar**: Usa el sidebar para explorar módulos
4. **Probar APIs**: Visita `/api/dashboard.php` para ver datos JSON

### Para Usar el Sistema de Auth:
1. **Registro**: Ve a `/auth/register.php`
2. **Confirmar**: Revisa tu email para confirmar cuenta
3. **Login**: Usa `/auth/login.php` para iniciar sesión
4. **Dashboard**: Después del login, serás redirigido al dashboard

## 📊 APIs Disponibles

### Dashboard API
```
GET /api/dashboard.php
```
**Respuesta:** Estadísticas del sistema en JSON

### Alertas API
```
GET /api/alerts.php
```
**Respuesta:** Alertas críticas del sistema

### Búsqueda API
```
GET /api/search.php?q=nombre
```
**Respuesta:** Resultados de búsqueda de usuarios

## 🎯 Próximos Pasos

1. **Explora** la página principal y el dashboard
2. **Prueba** el sistema de autenticación
3. **Revisa** las APIs disponibles
4. **Espera** a que se desarrollen los módulos restantes

## 💡 Tips Útiles

- **Siempre comienza** desde la página principal (`/`)
- **Usa el breadcrumb** para navegar entre secciones
- **Revisa la consola** del navegador para ver logs
- **Mantén abierto** el archivo `NAVEGACION.md` para referencia completa

---

**¿Necesitas ayuda?** Revisa el archivo `NAVEGACION.md` para una guía completa del sistema.
