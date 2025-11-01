-- =====================================================================
-- Script SQL DDL MINIMALISTA - Proyecto TeleGAN v1.0
-- Enfoque: Flexibilidad y Evolución Iterativa
-- Base de Datos: PostgreSQL 12+ con PostGIS
-- =====================================================================

-- Habilitar extensión PostGIS


-- =====================================================================
-- TABLA: USUARIO
-- Registro básico según TdR 5.1 (campos mínimos)
-- =====================================================================
CREATE TABLE usuario (
    id_usuario SERIAL PRIMARY KEY,
    
    -- Campos básicos de registro (TdR 5.1)
    nombre_completo VARCHAR(200) NOT NULL,
    email VARCHAR(150),
    telefono VARCHAR(50),
    password_hash VARCHAR(255) NOT NULL,
    ubicacion_general VARCHAR(200),
    
    -- Control de cuenta
    activo BOOLEAN DEFAULT TRUE,
    email_verificado BOOLEAN DEFAULT FALSE,
    telefono_verificado BOOLEAN DEFAULT FALSE,
    
    -- Metadatos
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultima_sesion TIMESTAMP,
    
    -- Futuro: codigo_telegan se agregará después
    codigo_telegan VARCHAR(50) UNIQUE,
    
    -- Al menos uno debe estar presente
    CONSTRAINT chk_contacto CHECK (
        email IS NOT NULL OR telefono IS NOT NULL
    )
);

COMMENT ON TABLE usuario IS 'Usuarios del sistema - Ganaderos y colaboradores';
COMMENT ON COLUMN usuario.codigo_telegan IS 'Código único TeleGAN - PENDIENTE DEFINIR formato';

-- =====================================================================
-- TABLA: demografia_usuario
-- Datos demográficos OPCIONALES por ahora (TdR menciona pero cliente decide)
-- =====================================================================
CREATE TABLE demografia_usuario (
    id_demografia SERIAL PRIMARY KEY,
    id_usuario INT UNIQUE NOT NULL,
    
    -- Campos demográficos (todos opcionales por ahora)
    genero VARCHAR(50),
    edad INT,
    grupo_etnico VARCHAR(100),
    
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_usuario_demografia
        FOREIGN KEY (id_usuario)
        REFERENCES usuario(id_usuario)
        ON DELETE CASCADE,
        
    -- Validaciones básicas si se llena
    CONSTRAINT chk_edad_valida 
        CHECK (edad IS NULL OR (edad >= 0 AND edad <= 120))
);

COMMENT ON TABLE demografia_usuario IS 'Datos demográficos opcionales para monitoreo de inclusión';

-- =====================================================================
-- TABLA: pais
-- Catálogo básico de países (Guatemala, México, Costa Rica, Honduras)
-- =====================================================================
CREATE TABLE pais (
    id_pais SERIAL PRIMARY KEY,
    codigo_iso2 CHAR(2) UNIQUE NOT NULL,  -- GT, MX, CR, HN
    codigo_iso3 CHAR(3) UNIQUE,           -- GTM, MEX, CRI, HND
    nombre_pais VARCHAR(100) NOT NULL,
    activo BOOLEAN DEFAULT TRUE
);

COMMENT ON TABLE pais IS 'Catálogo de países del proyecto TeleGAN';

-- Datos iniciales
INSERT INTO pais (codigo_iso2, codigo_iso3, nombre_pais) VALUES
    ('GT', 'GTM', 'Guatemala'),
    ('MX', 'MEX', 'México'),
    ('CR', 'CRI', 'Costa Rica'),
    ('HN', 'HND', 'Honduras');

-- =====================================================================
-- TABLA: finca
-- Almacena finca con geometría flexible
-- =====================================================================
CREATE TABLE finca (
    id_finca SERIAL PRIMARY KEY,
    
    -- Básicos
    nombre_finca VARCHAR(150) NOT NULL,
    id_usuario_creador INT NOT NULL,  -- Quien la creó (no necesariamente admin)
    id_pais INT,
    descripcion TEXT,
    
    -- Geometría FLEXIBLE
    -- Opción 1: String WKT desde Leaflet (temporal)
    geometria_wkt TEXT,
    
    -- Opción 2: Geometría PostGIS (cuando esté lista)
    geometria_postgis GEOMETRY(POLYGON, 4326),
    
    -- Área (se puede calcular después si tienen geometría PostGIS)
    area_hectareas NUMERIC,
    
    -- Estados
    estado VARCHAR(20) DEFAULT 'ACTIVA',
    
    -- Metadatos
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP,
    
    -- Futuro código TeleGAN
    codigo_telegan VARCHAR(50) UNIQUE,
    
    CONSTRAINT fk_usuario_creador
        FOREIGN KEY (id_usuario_creador)
        REFERENCES usuario(id_usuario)
        ON DELETE RESTRICT,
        
    CONSTRAINT fk_pais_finca
        FOREIGN KEY (id_pais)
        REFERENCES pais(id_pais)
        ON DELETE SET NULL,
        
    -- Al menos una geometría debe existir
    CONSTRAINT chk_geometria_presente CHECK (
        geometria_wkt IS NOT NULL OR geometria_postgis IS NOT NULL
    )
);

COMMENT ON TABLE finca IS 'Fincas registradas - Geometría flexible WKT o PostGIS';
COMMENT ON COLUMN finca.geometria_wkt IS 'WKT temporal desde Leaflet - migrar a PostGIS después';
COMMENT ON COLUMN finca.geometria_postgis IS 'Geometría PostGIS definitiva';

-- =====================================================================
-- TABLA: usuario_finca
-- Relación entre usuarios y fincas con roles
-- =====================================================================
CREATE TABLE usuario_finca (
    id_usuario_finca SERIAL PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_finca INT NOT NULL,
    
    -- Rol flexible (ADMIN, COLABORADOR, u otros futuros)
    rol VARCHAR(30) NOT NULL DEFAULT 'COLABORADOR',
    
    fecha_asociacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Un usuario solo puede tener 1 rol por finca
    UNIQUE (id_usuario, id_finca),
    
    CONSTRAINT fk_usuario_relacion
        FOREIGN KEY (id_usuario)
        REFERENCES usuario(id_usuario)
        ON DELETE CASCADE,
        
    CONSTRAINT fk_finca_relacion
        FOREIGN KEY (id_finca)
        REFERENCES finca(id_finca)
        ON DELETE CASCADE
);

COMMENT ON TABLE usuario_finca IS 'Relación usuarios-fincas con roles flexibles';
COMMENT ON COLUMN usuario_finca.rol IS 'Valores sugeridos: ADMIN, COLABORADOR (expandible)';

-- =====================================================================
-- TABLA: potrero
-- Subdivisiones de finca con geometría flexible
-- =====================================================================
CREATE TABLE potrero (
    id_potrero SERIAL PRIMARY KEY,
    id_finca INT NOT NULL,
    
    nombre_potrero VARCHAR(150) NOT NULL,
    descripcion TEXT,
    
    -- Geometría FLEXIBLE
    geometria_wkt TEXT,
    geometria_postgis GEOMETRY(POLYGON, 4326),
    
    area_hectareas NUMERIC,
    
    -- Estado (para soft delete futuro)
    estado VARCHAR(20) DEFAULT 'ACTIVO',
    
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP,
    
    -- Futuro código TeleGAN
    codigo_telegan VARCHAR(50) UNIQUE,
    
    CONSTRAINT fk_finca_potrero
        FOREIGN KEY (id_finca)
        REFERENCES finca(id_finca)
        ON DELETE CASCADE,
        
    CONSTRAINT chk_geometria_potrero CHECK (
        geometria_wkt IS NOT NULL OR geometria_postgis IS NOT NULL
    )
);

COMMENT ON TABLE potrero IS 'Potreros dentro de fincas - Geometría flexible';
COMMENT ON COLUMN potrero.estado IS 'ACTIVO, INACTIVO, ARCHIVADO';

-- =====================================================================
-- TABLA: registro_ganadero
-- Registros del módulo ganadero (TdR 5.3)
-- =====================================================================
CREATE TABLE registro_ganadero (
    id_registro SERIAL PRIMARY KEY,
    id_potrero INT NOT NULL,
    id_usuario INT NOT NULL,  -- Quien hizo el registro
    
    -- Campos según TdR 5.3 (todos opcionales por ahora)
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    numero_animales INT,
    tipo_pasto VARCHAR(100),
    estado_pasto VARCHAR(50),
    disponibilidad_agua VARCHAR(20),
    
    -- Ubicación de fuente de agua (opcional)
    ubicacion_agua_wkt TEXT,
    ubicacion_agua_postgis GEOMETRY(POINT, 4326),
    
    -- Observaciones adicionales
    notas TEXT,
    
    CONSTRAINT fk_potrero_registro
        FOREIGN KEY (id_potrero)
        REFERENCES potrero(id_potrero)
        ON DELETE CASCADE,
        
    CONSTRAINT fk_usuario_registro
        FOREIGN KEY (id_usuario)
        REFERENCES usuario(id_usuario)
        ON DELETE RESTRICT
);

COMMENT ON TABLE registro_ganadero IS 'Registros del módulo ganadero - Campos flexibles';

-- =====================================================================
-- TABLA: historial_cambios
-- Auditoría básica de cambios importantes
-- =====================================================================
CREATE TABLE historial_cambios (
    id_historial SERIAL PRIMARY KEY,
    
    -- ¿Qué se modificó?
    tabla_afectada VARCHAR(50) NOT NULL,
    id_registro_afectado INT NOT NULL,
    
    -- ¿Quién?
    id_usuario INT,
    
    -- ¿Qué hizo?
    tipo_accion VARCHAR(50) NOT NULL,  -- CREAR, EDITAR, ELIMINAR, etc.
    
    -- Detalles (JSON flexible)
    datos_anteriores JSONB,
    datos_nuevos JSONB,
    
    fecha_accion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_usuario_historial
        FOREIGN KEY (id_usuario)
        REFERENCES usuario(id_usuario)
        ON DELETE SET NULL
);

COMMENT ON TABLE historial_cambios IS 'Auditoría flexible de cambios - Expandible según necesidades';

-- =====================================================================
-- ÍNDICES BÁSICOS
-- =====================================================================

-- Usuarios
CREATE INDEX idx_usuario_email ON usuario(email) WHERE email IS NOT NULL;
CREATE INDEX idx_usuario_telefono ON usuario(telefono) WHERE telefono IS NOT NULL;
CREATE INDEX idx_usuario_activo ON usuario(activo) WHERE activo = TRUE;

-- Fincas
CREATE INDEX idx_finca_usuario_creador ON finca(id_usuario_creador);
CREATE INDEX idx_finca_pais ON finca(id_pais);
CREATE INDEX idx_finca_estado ON finca(estado);

-- Índices espaciales (solo si usan PostGIS)
CREATE INDEX idx_finca_geometria_postgis ON finca USING GIST(geometria_postgis) 
    WHERE geometria_postgis IS NOT NULL;

CREATE INDEX idx_potrero_geometria_postgis ON potrero USING GIST(geometria_postgis)
    WHERE geometria_postgis IS NOT NULL;

-- Usuario-Finca
CREATE INDEX idx_usuario_finca_usuario ON usuario_finca(id_usuario);
CREATE INDEX idx_usuario_finca_finca ON usuario_finca(id_finca);
CREATE INDEX idx_usuario_finca_rol ON usuario_finca(rol);

-- Potreros
CREATE INDEX idx_potrero_finca ON potrero(id_finca);
CREATE INDEX idx_potrero_estado ON potrero(estado);

-- Registros ganaderos
CREATE INDEX idx_registro_potrero ON registro_ganadero(id_potrero);
CREATE INDEX idx_registro_usuario ON registro_ganadero(id_usuario);
CREATE INDEX idx_registro_fecha ON registro_ganadero(fecha_registro);

-- Historial
CREATE INDEX idx_historial_tabla ON historial_cambios(tabla_afectada, id_registro_afectado);
CREATE INDEX idx_historial_fecha ON historial_cambios(fecha_accion DESC);

-- =====================================================================
-- FUNCIONES AUXILIARES OPCIONALES
-- =====================================================================

-- Función: Migrar WKT a PostGIS (cuando estén listos)
CREATE OR REPLACE FUNCTION migrar_wkt_a_postgis_finca(p_id_finca INT)
RETURNS BOOLEAN AS $$
BEGIN
    UPDATE finca
    SET geometria_postgis = ST_GeomFromText(geometria_wkt, 4326),
        fecha_actualizacion = CURRENT_TIMESTAMP
    WHERE id_finca = p_id_finca
      AND geometria_wkt IS NOT NULL
      AND geometria_postgis IS NULL;
    
    RETURN FOUND;
END;
$$ LANGUAGE plpgsql;

COMMENT ON FUNCTION migrar_wkt_a_postgis_finca IS 'Migra geometría WKT a PostGIS cuando esté lista';

-- Función: Calcular área de PostGIS (cuando migren)
CREATE OR REPLACE FUNCTION calcular_area_finca(p_id_finca INT)
RETURNS NUMERIC AS $$
DECLARE
    v_area NUMERIC;
BEGIN
    SELECT ST_Area(geometria_postgis::geography) / 10000 INTO v_area
    FROM finca
    WHERE id_finca = p_id_finca
      AND geometria_postgis IS NOT NULL;
    
    RETURN v_area;
END;
$$ LANGUAGE plpgsql;

COMMENT ON FUNCTION calcular_area_finca IS 'Calcula área en hectáreas desde geometría PostGIS';

-- =====================================================================
-- VISTAS ÚTILES
-- =====================================================================

-- Vista: Usuarios con sus fincas y roles
CREATE OR REPLACE VIEW v_usuarios_fincas AS
SELECT 
    u.id_usuario,
    u.nombre_completo,
    u.email,
    u.telefono,
    u.activo AS usuario_activo,
    f.id_finca,
    f.nombre_finca,
    f.estado AS finca_estado,
    uf.rol,
    uf.fecha_asociacion
FROM usuario u
INNER JOIN usuario_finca uf ON u.id_usuario = uf.id_usuario
INNER JOIN finca f ON uf.id_finca = f.id_finca;

COMMENT ON VIEW v_usuarios_fincas IS 'Vista de usuarios con sus fincas y roles asignados';

-- Vista: Fincas con conteo de potreros
CREATE OR REPLACE VIEW v_fincas_resumen AS
SELECT 
    f.id_finca,
    f.nombre_finca,
    f.estado,
    p.nombre_pais,
    f.area_hectareas,
    COUNT(pt.id_potrero) AS total_potreros,
    f.fecha_creacion
FROM finca f
LEFT JOIN pais p ON f.id_pais = p.id_pais
LEFT JOIN potrero pt ON f.id_finca = pt.id_finca AND pt.estado = 'ACTIVO'
GROUP BY f.id_finca, p.nombre_pais;

COMMENT ON VIEW v_fincas_resumen IS 'Resumen de fincas con conteo de potreros activos';

-- =====================================================================
-- DATOS DE EJEMPLO (OPCIONAL - COMENTAR EN PRODUCCIÓN)
-- =====================================================================

-- Usuario de prueba
INSERT INTO usuario (nombre_completo, email, password_hash, ubicacion_general)
VALUES 
    ('Juan Pérez López', 'juan.perez@ejemplo.com', '$2b$12$ejemplo_hash_seguro', 'Tegucigalpa, Honduras'),
    ('María González', 'maria.gonzalez@ejemplo.com', '$2b$12$otro_hash_seguro', 'Guatemala, Guatemala');

-- Demografía opcional
INSERT INTO demografia_usuario (id_usuario, genero, edad, grupo_etnico)
VALUES 
    (1, 'Masculino', 45, 'Mestizo'),
    (2, 'Femenino', 38, 'Maya');

-- Finca de ejemplo (usando WKT desde Leaflet)
INSERT INTO finca (nombre_finca, id_usuario_creador, id_pais, geometria_wkt)
VALUES (
    'Finca La Esperanza',
    1,
    4, -- Honduras
    'POLYGON((-87.2 14.1, -87.2 14.105, -87.195 14.105, -87.195 14.1, -87.2 14.1))'
);

-- Asociar usuario a finca como ADMIN
INSERT INTO usuario_finca (id_usuario, id_finca, rol)
VALUES (1, 1, 'ADMIN');

-- Potrero de ejemplo
INSERT INTO potrero (id_finca, nombre_potrero, geometria_wkt)
VALUES (
    1,
    'Potrero Norte',
    'POLYGON((-87.199 14.101, -87.199 14.104, -87.196 14.104, -87.196 14.101, -87.199 14.101))'
);

-- Registro ganadero de ejemplo
INSERT INTO registro_ganadero (id_potrero, id_usuario, numero_animales, tipo_pasto, estado_pasto, disponibilidad_agua)
VALUES (1, 1, 25, 'Brachiaria', 'Bueno', 'Sí');

-- =====================================================================
-- SCRIPT DE MIGRACIÓN FUTURA (cuando definan PostGIS)
-- =====================================================================

-- Para migrar todas las geometrías WKT a PostGIS de una vez:
/*
UPDATE finca 
SET geometria_postgis = ST_GeomFromText(geometria_wkt, 4326),
    area_hectareas = ST_Area(ST_GeomFromText(geometria_wkt, 4326)::geography) / 10000
WHERE geometria_wkt IS NOT NULL 
  AND geometria_postgis IS NULL;

UPDATE potrero
SET geometria_postgis = ST_GeomFromText(geometria_wkt, 4326),
    area_hectareas = ST_Area(ST_GeomFromText(geometria_wkt, 4326)::geography) / 10000
WHERE geometria_wkt IS NOT NULL
  AND geometria_postgis IS NULL;
*/

-- =====================================================================
-- CONSULTAS ÚTILES PARA DESARROLLO
-- =====================================================================

-- Ver usuarios con sus fincas
-- SELECT * FROM v_usuarios_fincas;

-- Ver resumen de fincas
-- SELECT * FROM v_fincas_resumen;

-- Ver potreros de una finca específica
-- SELECT * FROM potrero WHERE id_finca = 1;

-- Ver registros ganaderos de un potrero
-- SELECT * FROM registro_ganadero WHERE id_potrero = 1 ORDER BY fecha_registro DESC;

-- Buscar usuario por email o teléfono
-- SELECT * FROM usuario WHERE email = 'juan.perez@ejemplo.com' OR telefono = '+504-1234-5678';

-- =====================================================================
-- NOTAS IMPORTANTES
-- =====================================================================


















