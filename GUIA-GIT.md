# 🚀 Guía de Vinculación con Git/GitHub

## Hoja de Ruta Completa

### 📋 Paso 1: Crear el Repositorio en GitHub/GitLab (PRIMERO)
1. Ve a tu plataforma (GitHub, GitLab, etc.)
2. Crea un **repositorio NUEVO** (vacío, sin README, sin .gitignore)
3. **Copia la URL** del repositorio (ejemplo: `https://github.com/tu-usuario/TELEGAN_ADMIN.git`)

### 📋 Paso 2: Vincular el Repositorio Local con el Remoto
Ejecuta estos comandos en la terminal de Cursor:

```bash
# Agregar el remoto (reemplaza con tu URL)
git remote add origin https://github.com/tu-usuario/TELEGAN_ADMIN.git

# Verificar que se agregó correctamente
git remote -v
```

### 📋 Paso 3: Preparar los Cambios (Commit)
```bash
# Agregar todos los archivos nuevos y modificados
git add .

# Ver qué se va a commitear (opcional, para revisar)
git status

# Hacer el commit con un mensaje descriptivo
git commit -m "Initial commit: Panel administrativo Telegan completo"
```

### 📋 Paso 4: Subir al Repositorio (Push)
```bash
# Subir los cambios al repositorio remoto
git push -u origin main
```

## ⚠️ Si Tienes Errores

### Error: "origin already exists"
```bash
# Eliminar el remoto existente
git remote remove origin

# Agregar nuevamente con la URL correcta
git remote add origin https://github.com/tu-usuario/TELEGAN_ADMIN.git
```

### Error: "failed to push some refs"
```bash
# Si el repositorio remoto tiene contenido, hacer pull primero
git pull origin main --allow-unrelated-histories

# Luego hacer push
git push -u origin main
```

## 🔐 Importante: Seguridad

El archivo `.gitignore` ya está configurado para **NO subir**:
- ✅ Archivos `.env` (credenciales)
- ✅ Logs y archivos temporales
- ✅ Archivos sensibles

**NUNCA subas** tu archivo `.env` con credenciales reales.

## 📝 Comandos Útiles para el Futuro

```bash
# Ver estado de cambios
git status

# Agregar archivos específicos
git add ruta/al/archivo.php

# Hacer commit
git commit -m "Descripción del cambio"

# Subir cambios
git push

# Bajar cambios del remoto
git pull

# Ver remotos configurados
git remote -v
```

