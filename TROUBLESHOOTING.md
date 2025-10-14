# Troubleshooting - Telegan Admin Panel

## Problemas Comunes y Soluciones

### 1. Error 404 - Página no encontrada

**Síntomas:**
- Todas las rutas devuelven 404
- El dashboard no carga
- Error "Endpoint no encontrado"

**Soluciones:**

#### A. Verificar configuración del servidor
```bash
# Verificar que mod_rewrite esté habilitado en Apache
sudo a2enmod rewrite
sudo systemctl restart apache2
```

#### B. Verificar archivo .htaccess
- El archivo `.htaccess` debe estar en la raíz del proyecto
- Verificar que Apache tenga permisos para leerlo

#### C. Verificar estructura de archivos
```
telegan-admin/
├── .htaccess          ← Debe existir
├── index.php          ← Punto de entrada
├── api/
│   ├── dashboard.php
│   ├── health.php
│   └── router.php
└── public/
    └── dashboard.html
```

### 2. Error de conexión a base de datos

**Síntomas:**
- Error "Error de conexión a la base de datos"
- Dashboard muestra "Error de Conexión"

**Soluciones:**

#### A. Verificar archivo .env
```bash
# Copiar archivo de ejemplo
cp env.example .env

# Editar con tus datos
nano .env
```

#### B. Verificar configuración de PostgreSQL
```env
DB_HOST=localhost
DB_PORT=5432
DB_NAME=telegan_agricultores
DB_USER=postgres
DB_PASSWORD=tu_password
```

#### C. Verificar extensión PDO PostgreSQL
```php
<?php
// Verificar extensiones
phpinfo();
// Buscar: pdo_pgsql
```

### 3. Debug del sistema

#### A. Archivos de debug incluidos
- `test-routing.php` - Test de routing
- `debug-env.php` - Debug de variables de entorno
- `api/test.php` - Test simple de API

#### B. Verificar logs de error
```bash
# Logs de Apache
tail -f /var/log/apache2/error.log

# Logs de PHP
tail -f /var/log/php/error.log
```

### 4. URLs de prueba

Para verificar que todo funciona:

1. **Dashboard HTML**: `http://tu-dominio.com/public/dashboard.html`
2. **Test Routing**: `http://tu-dominio.com/test-routing.php`
3. **Debug ENV**: `http://tu-dominio.com/debug-env.php`
4. **API Test**: `http://tu-dominio.com/api/test`
5. **API Dashboard**: `http://tu-dominio.com/api/dashboard`
6. **API Health**: `http://tu-dominio.com/api/health`

### 5. Verificación paso a paso

#### Paso 1: Verificar archivos
```bash
ls -la
# Debe mostrar: .htaccess, index.php, api/, public/
```

#### Paso 2: Verificar .env
```bash
cat .env
# Debe mostrar variables DB_*
```

#### Paso 3: Test de conexión BD
```bash
php debug-env.php
# Debe mostrar "✅ Conexión exitosa"
```

#### Paso 4: Test de routing
```bash
php test-routing.php
# Debe mostrar información del servidor
```

#### Paso 5: Test de API
```bash
curl http://localhost/api/test
# Debe devolver JSON con success: true
```

### 6. Problemas específicos

#### A. Si mod_rewrite no funciona
Crear archivo `.htaccess` alternativo:
```apache
RewriteEngine On
RewriteRule ^api/(.*)$ api/$1.php [L]
RewriteRule ^(.*)$ index.php [QSA,L]
```

#### B. Si el .env no se carga
Verificar permisos:
```bash
chmod 644 .env
chown www-data:www-data .env
```

#### C. Si PostgreSQL no conecta
Verificar que el servicio esté corriendo:
```bash
sudo systemctl status postgresql
sudo systemctl start postgresql
```

### 7. Contacto

Si los problemas persisten:
1. Verificar logs de error
2. Ejecutar archivos de debug
3. Verificar configuración del servidor
4. Revisar permisos de archivos






