# 🚀 GUÍA DE CONFIGURACIÓN PARA DEPLOY

## 📋 Configuración Necesaria para Producción

Además del archivo `.env`, necesitas verificar/cambiar algunos archivos para que funcione correctamente en producción.

---

## 1. ARCHIVO `.env` (OBLIGATORIO)

### Configuración Mínima Requerida

```env
# =====================================================
# Base de Datos PostgreSQL
# =====================================================
DB_HOST=tu_host_postgresql
DB_PORT=5432
DB_NAME=telegan
DB_USER=tu_usuario
DB_PASSWORD=tu_password_seguro

# =====================================================
# Configuración de la Aplicación
# =====================================================
APP_NAME="Telegan Admin Panel"
APP_URL=https://tu-dominio.com
APP_DOMAIN=tu-dominio.com
APP_ENV=production
APP_SECRET=genera_uno_aleatorio_seguro_aqui

# =====================================================
# Autenticación de APIs
# =====================================================
API_SECRET=genera_otro_secreto_seguro_aqui
```

### ⚠️ Cambios Críticos para Producción

1. **`APP_URL`**: Cambiar de `http://localhost:8000` a `https://tu-dominio.com`
2. **`APP_DOMAIN`**: Cambiar de `localhost` a tu dominio real
3. **`APP_ENV`**: Cambiar de `development` a `production`
4. **`APP_SECRET`**: Generar un secreto aleatorio seguro
5. **`API_SECRET`**: Generar otro secreto aleatorio seguro (debe coincidir con `config.js`)

---

## 2. ARCHIVO `public/js/config.js` (⚠️ IMPORTANTE)

### Problema Actual

En la línea 7, hay una ruta **hardcodeada** que puede causar problemas:

```javascript
// ❌ ACTUAL (puede no funcionar en producción)
apiBaseUrl: window.location.origin + '/TELEGAN_ADMIN/public/api',
```

### ✅ Solución para Producción

**Opción 1: Ruta Relativa (RECOMENDADA)**

```javascript
// ✅ CORRECTO - Ruta relativa que funciona en cualquier dominio
apiBaseUrl: 'api',
```

O si estás usando subdirectorio:

```javascript
// Si el proyecto está en un subdirectorio
apiBaseUrl: window.location.pathname.replace(/\/[^/]*$/, '') + '/api',
```

**Opción 2: Ruta Relativa Simple (MÁS SIMPLE)**

Si el `.htaccess` está bien configurado (que lo está), puedes usar simplemente:

```javascript
apiBaseUrl: 'api',
```

El `.htaccess` redirigirá `/api/*` a `public/api/*` automáticamente.

### Verificar Coincidencia de API_SECRET

El `apiSecret` en `config.js` (línea 11) **DEBE COINCIDIR** con `API_SECRET` en tu archivo `.env`:

```javascript
// En config.js
apiSecret: 'tu_secreto_aqui',  // ⚠️ DEBE COINCIDIR con API_SECRET en .env
```

```env
# En .env
API_SECRET=tu_secreto_aqui  # ⚠️ DEBE COINCIDIR con apiSecret en config.js
```

---

## 3. ARCHIVO `.htaccess` (✅ Ya está bien configurado)

El archivo `.htaccess` ya está correctamente configurado. **NO necesitas cambiarlo** a menos que:

- Tu proyecto esté en un **subdirectorio** (ej: `https://dominio.com/telegan-admin/`)
- Necesites ajustar las rutas de rewrite

### Si el Proyecto está en Subdirectorio

Si tu proyecto está en `https://dominio.com/telegan-admin/`, necesitas agregar al inicio del `.htaccess`:

```apache
# Si está en subdirectorio, ajustar RewriteBase
RewriteBase /telegan-admin/

# O si prefieres rutas absolutas, cambiar las reglas
```

Pero en la mayoría de casos, el `.htaccess` actual funciona bien.

---

## 4. VERIFICACIÓN DE RUTAS

### Rutas que Deben Funcionar

Después del deploy, estas rutas deben funcionar:

- `https://tu-dominio.com/` → Redirige a `index.html`
- `https://tu-dominio.com/dashboard` → Muestra `public/dashboard.html`
- `https://tu-dominio.com/api/dashboard` → Ejecuta `public/api/dashboard.php`
- `https://tu-dominio.com/auth/login` → Muestra `auth/login.php`
- `https://tu-dominio.com/modules/users/` → Muestra `public/modules/users/`

### Cómo Verificar

1. Abrir navegador en modo incógnito
2. Ir a `https://tu-dominio.com`
3. Abrir consola del navegador (F12)
4. Verificar que las peticiones a `/api/*` funcionen sin errores 404

---

## 5. CHECKLIST DE DEPLOY

### Antes de Subir Archivos

- [ ] Configurar archivo `.env` con credenciales reales
- [ ] Cambiar `APP_ENV=production`
- [ ] Cambiar `APP_URL` a tu dominio real
- [ ] Cambiar `APP_DOMAIN` a tu dominio real
- [ ] Generar `APP_SECRET` aleatorio seguro
- [ ] Generar `API_SECRET` aleatorio seguro

### Modificar Archivos

- [ ] **Modificar `public/js/config.js`**:
  - Cambiar `apiBaseUrl` a `'api'` (ruta relativa)
  - Verificar que `apiSecret` coincida con `API_SECRET` en `.env`

### Después de Subir

- [ ] Verificar que `.env` NO se subió a Git (está en `.gitignore`)
- [ ] Verificar permisos del servidor (chmod 644 para archivos, 755 para directorios)
- [ ] Verificar que `mod_rewrite` esté habilitado en Apache
- [ ] Verificar extensión `pdo_pgsql` esté instalada en PHP
- [ ] Probar acceso a `https://tu-dominio.com`
- [ ] Probar login en `https://tu-dominio.com/auth/login`
- [ ] Verificar que APIs respondan correctamente (consola del navegador)

---

## 6. CONFIGURACIÓN ESPECÍFICA POR ESCENARIO

### Escenario 1: Dominio Raíz (Recomendado)

**URL**: `https://telegan.com`

**`.env`**:
```env
APP_URL=https://telegan.com
APP_DOMAIN=telegan.com
```

**`config.js`**:
```javascript
apiBaseUrl: 'api',
```

**`.htaccess`**: No necesita cambios

---

### Escenario 2: Subdominio

**URL**: `https://admin.telegan.com`

**`.env`**:
```env
APP_URL=https://admin.telegan.com
APP_DOMAIN=admin.telegan.com
```

**`config.js`**:
```javascript
apiBaseUrl: 'api',
```

**`.htaccess`**: No necesita cambios

---

### Escenario 3: Subdirectorio

**URL**: `https://telegan.com/admin`

**`.env`**:
```env
APP_URL=https://telegan.com/admin
APP_DOMAIN=telegan.com
```

**`config.js`**:
```javascript
// Opción 1: Ruta relativa (mejor)
apiBaseUrl: 'api',

// Opción 2: Ruta con subdirectorio
apiBaseUrl: window.location.pathname.replace(/\/[^/]*$/, '') + '/api',
```

**`.htaccess`**: Agregar al inicio:
```apache
RewriteBase /admin/
```

---

## 7. SEGURIDAD EN PRODUCCIÓN

### Headers de Seguridad

El `.htaccess` ya incluye headers de seguridad. Verifica que estén activos.

### Variables Sensibles

- [ ] **NUNCA** subir `.env` a Git (ya está en `.gitignore`)
- [ ] Usar contraseñas fuertes en `.env`
- [ ] Cambiar `API_SECRET` y `APP_SECRET` en producción
- [ ] Verificar permisos del archivo `.env` (chmod 600)

### Verificación de Seguridad

```bash
# Verificar que .env no está en Git
git ls-files | grep .env

# Si aparece, removerlo
git rm --cached .env
```

---

## 8. TROUBLESHOOTING

### Error: "API not found" o 404 en `/api/*`

**Causa**: El `.htaccess` no está funcionando o rutas incorrectas.

**Solución**:
1. Verificar que `mod_rewrite` esté habilitado
2. Verificar que el `.htaccess` esté en la raíz del proyecto
3. Verificar permisos del archivo (644)
4. Revisar logs de Apache para errores

### Error: "CORS" o "Access-Control-Allow-Origin"

**Causa**: Headers CORS no están configurados o dominio incorrecto.

**Solución**:
1. Verificar que `APP_DOMAIN` en `.env` sea correcto
2. Verificar headers en archivos PHP de API
3. En desarrollo puede estar abierto (`*`), en producción debe ser específico

### Error: "Token inválido" o autenticación falla

**Causa**: `API_SECRET` en `config.js` no coincide con `API_SECRET` en `.env`.

**Solución**:
1. Verificar que ambos valores sean **exactamente iguales**
2. Sin espacios, sin comillas adicionales
3. Regenerar ambos si es necesario

### Error: Sesiones no funcionan

**Causa**: Permisos de escritura en directorio de sesiones PHP.

**Solución**:
```bash
# Dar permisos de escritura al directorio de sesiones
chmod 777 /tmp  # Temporal (mejor usar directorio específico)

# O configurar directorio personalizado en php.ini
session.save_path = "/ruta/a/directorio/sesiones"
chmod 755 /ruta/a/directorio/sesiones
```

---

## 9. ARCHIVOS A MODIFICAR ANTES DE DEPLOY

### Resumen Rápido

1. **`.env`** (crear desde `env.example`):
   - Cambiar todas las URLs a producción
   - Cambiar `APP_ENV=production`
   - Configurar credenciales de BD reales
   - Generar secretos seguros

2. **`public/js/config.js`**:
   - Cambiar `apiBaseUrl: 'api'` (ruta relativa)
   - Verificar que `apiSecret` coincida con `.env`

3. **`.htaccess`**:
   - No necesita cambios (a menos que sea subdirectorio)

---

## 10. COMANDOS ÚTILES PARA DEPLOY

### Verificar conexión a BD
```bash
php -r "
require 'src/Config/Database.php';
try {
    \$db = Database::getConnection();
    echo 'Conexión OK';
} catch (Exception \$e) {
    echo 'Error: ' . \$e->getMessage();
}
"
```

### Verificar que .env no esté en Git
```bash
git check-ignore .env
# Debe mostrar: .env (si está ignorado correctamente)
```

### Verificar permisos
```bash
# Archivos normales
find . -type f -exec chmod 644 {} \;

# Directorios
find . -type d -exec chmod 755 {} \;

# .env (más restrictivo)
chmod 600 .env
```

---

## ✅ RESUMEN FINAL

### Archivos a Configurar:

1. ✅ **`.env`** (OBLIGATORIO)
   - Credenciales BD
   - URLs de producción
   - Secretos seguros
   - `APP_ENV=production`

2. ✅ **`public/js/config.js`** (IMPORTANTE)
   - Cambiar `apiBaseUrl` a `'api'`
   - Verificar `apiSecret` coincide con `.env`

3. ⚠️ **`.htaccess`** (Opcional)
   - Solo si está en subdirectorio
   - Agregar `RewriteBase`

### No Necesitas Configurar:

- ❌ Rutas en PHP (ya están relativas)
- ❌ Rutas en HTML (ya están relativas)
- ❌ Estructura de directorios
- ❌ Base de datos (solo conexión en `.env`)

---

**¡Listo para deploy!** 🚀

Si tienes dudas sobre algún paso específico, revisa esta guía o consulta los logs del servidor.

