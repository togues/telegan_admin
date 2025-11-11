-- =====================================================================
-- Script SQL - Módulo Monitoreo Territorial (Tablas base independientes)
-- Proyecto TeleGAN - noviembre 2025
-- =====================================================================

-- Nota: este script asume que la base principal ya existe y que la extensión
-- PostGIS está habilitada cuando se quieran usar columnas GEOMETRY.

-- =====================================================================
-- 1. TABLA: proveedor (Catálogo de fuentes satelitales / climáticas)
-- =====================================================================
CREATE TABLE proveedor (
    id_proveedor SERIAL PRIMARY KEY,
    codigo TEXT UNIQUE NOT NULL,          -- Ej. 'SENTINEL2', 'GEE'
    nombre TEXT NOT NULL,
    descripcion TEXT,
    url_api TEXT,

    -- Control de acceso y operación
    requiere_autenticacion BOOLEAN DEFAULT TRUE,
    api_key_encriptada TEXT,
    frecuencia_horas INT DEFAULT 24,
    ventana_temporal_dias INT DEFAULT 7,
    max_nubosidad_pct NUMERIC(5,2) DEFAULT 20.00,

    -- Metadatos y control
    contacto JSONB DEFAULT '{}'::jsonb,
    metadata JSONB DEFAULT '{}'::jsonb,
    activo BOOLEAN DEFAULT TRUE,
    fecha_ultima_consulta TIMESTAMPTZ,
    fecha_creacion TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE proveedor IS 'Catálogo de proveedores de datos satelitales/climáticos utilizados por el ETL territorial';

-- =====================================================================
-- 2. TABLA: indice_satelital (Catálogo de índices e indicadores)
-- =====================================================================
CREATE TABLE indice_satelital (
    codigo TEXT PRIMARY KEY,              -- Ej. 'NDVI', 'NDMI'
    nombre TEXT NOT NULL,
    categoria TEXT,                       -- 'VEGETACION', 'CLIMA', etc.
    descripcion TEXT,
    formula TEXT,
    unidad TEXT,

    valor_min NUMERIC(10,4),
    valor_max NUMERIC(10,4),
    interpretacion_bueno TEXT,
    interpretacion_malo TEXT,
    color_escala JSONB DEFAULT '[]'::jsonb,

    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE indice_satelital IS 'Catálogo maestro de índices satelitales que se pueden monitorear';

-- =====================================================================
-- 3. TABLA: region_umbral (Regiones geográficas con reglas propias)
-- =====================================================================
CREATE TABLE region_umbral (
    id_region SERIAL PRIMARY KEY,
    codigo TEXT UNIQUE NOT NULL,          -- Ej. 'HN_OLANCHO'
    nombre TEXT NOT NULL,
    pais_codigo_iso CHAR(2),              -- Referencia al catálogo de países existente
    tipo TEXT,                            -- 'PAIS', 'DEPARTAMENTO', etc.

    geom GEOMETRY(MultiPolygon, 4326),
    metadata JSONB DEFAULT '{}'::jsonb,
    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_region_umbral_geom ON region_umbral USING GIST(geom);
CREATE INDEX idx_region_umbral_pais ON region_umbral(pais_codigo_iso);

COMMENT ON TABLE region_umbral IS 'Regiones geográficas para aplicar umbrales diferenciados en el monitoreo';

-- =====================================================================
-- 4. TABLA: umbral_indice (Umbrales y reglas por región / temporada)
-- =====================================================================
CREATE TABLE umbral_indice (
    id_umbral SERIAL PRIMARY KEY,
    id_region INT,
    codigo_indice TEXT NOT NULL,

    temporada TEXT,                       -- Ej. 'SEQUIA', 'LLUVIAS'
    fecha_inicio DATE,                    -- Inicio de temporada (opcional)
    fecha_fin DATE,                       -- Fin de temporada (opcional)

    valor_min NUMERIC(10,4),
    valor_max NUMERIC(10,4),
    nivel_alerta TEXT NOT NULL CHECK (nivel_alerta IN ('INFO', 'BAJO', 'MODERADO', 'ALTO', 'CRITICO')),
    tipo_alerta TEXT,
    descripcion TEXT,
    recomendacion_accion TEXT,

    metadata JSONB DEFAULT '{}'::jsonb,
    fecha_creacion TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    creado_por INT,                       -- FK opcional a usuario.id_usuario (se enlazará en una iteración posterior)

    CONSTRAINT fk_umbral_region
        FOREIGN KEY (id_region)
        REFERENCES region_umbral(id_region)
        ON DELETE SET NULL,

    CONSTRAINT fk_umbral_indice
        FOREIGN KEY (codigo_indice)
        REFERENCES indice_satelital(codigo)
        ON DELETE RESTRICT
);

CREATE INDEX idx_umbral_region_indice ON umbral_indice(id_region, codigo_indice);
CREATE INDEX idx_umbral_temporada ON umbral_indice(temporada);
CREATE INDEX idx_umbral_nivel ON umbral_indice(nivel_alerta);

COMMENT ON TABLE umbral_indice IS 'Configuración de umbrales y niveles de alerta por índice, región y temporada';

-- =====================================================================
-- 5. DATOS SEMILLA (Opcional - comentar en producción)
-- =====================================================================
INSERT INTO proveedor (codigo, nombre, descripcion, frecuencia_horas) VALUES
    ('SENTINEL2', 'Sentinel-2 (ESA)', 'Copernicus Sentinel-2 MSI - 10m resolución', 120),
    ('COPERNICUS', 'Copernicus Data Space', 'Copernicus Climate Data Store', 24),
    ('GEE', 'Google Earth Engine', 'Google Earth Engine API', 168),
    ('MODIS', 'MODIS Terra/Aqua', 'NASA MODIS - 250m resolución', 24)
ON CONFLICT (codigo) DO NOTHING;

INSERT INTO indice_satelital (codigo, nombre, categoria, formula, unidad, valor_min, valor_max) VALUES
    ('NDVI', 'Normalized Difference Vegetation Index', 'VEGETACION', '(NIR - RED) / (NIR + RED)', 'adimensional', -1, 1),
    ('NDMI', 'Normalized Difference Moisture Index', 'HUMEDAD', '(NIR - SWIR) / (NIR + SWIR)', 'adimensional', -1, 1),
    ('SAVI', 'Soil Adjusted Vegetation Index', 'VEGETACION', '((NIR - RED) / (NIR + RED + L)) * (1 + L)', 'adimensional', -1, 1),
    ('EVI', 'Enhanced Vegetation Index', 'VEGETACION', '2.5 * ((NIR - RED) / (NIR + 6*RED - 7.5*BLUE + 1))', 'adimensional', -1, 1),
    ('PRECIPITATION', 'Precipitación Acumulada', 'CLIMA', 'N/A', 'mm', 0, 1000),
    ('TEMPERATURE', 'Temperatura Superficial', 'CLIMA', 'N/A', '°C', -50, 60),
    ('BIOMASA', 'Biomasa Forrajera Estimada', 'BIOMASA', 'f(NDVI, SAVI, región)', 'kg MS/ha', 0, 10000)
ON CONFLICT (codigo) DO NOTHING;

INSERT INTO region_umbral (codigo, nombre, pais_codigo_iso, tipo) VALUES
    ('HN_OLANCHO', 'Olancho', 'HN', 'DEPARTAMENTO'),
    ('GT_COBAN', 'Cobán, Alta Verapaz', 'GT', 'MUNICIPIO'),
    ('MX_CHIAPAS', 'Chiapas', 'MX', 'ESTADO'),
    ('CR_GENERAL', 'Costa Rica General', 'CR', 'PAIS')
ON CONFLICT (codigo) DO NOTHING;

INSERT INTO umbral_indice (
    id_region, codigo_indice, temporada,
    valor_min, valor_max, nivel_alerta, tipo_alerta,
    descripcion, recomendacion_accion
) VALUES
    (
        (SELECT id_region FROM region_umbral WHERE codigo = 'HN_OLANCHO'),
        'NDVI',
        'SEQUIA',
        NULL,
        0.30,
        'ALTO',
        'SEQUIA',
        'NDVI por debajo de 0.30 indica estrés severo de vegetación.',
        'Reduce carga animal, verifica agua y considera suplementación.'
    ),
    (
        (SELECT id_region FROM region_umbral WHERE codigo = 'GT_COBAN'),
        'NDMI',
        'LLUVIAS',
        0.60,
        NULL,
        'MODERADO',
        'INUNDACION',
        'Alta humedad en suelo puede indicar riesgo de inundación.',
        'Mueve animales a zonas altas y monitorea drenajes.'
    )
ON CONFLICT DO NOTHING;

-- =====================================================================
-- FIN DEL SCRIPT
-- =====================================================================

