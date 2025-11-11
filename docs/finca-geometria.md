# Módulo de Geometrías de Fincas

Este documento resume los scripts SQL necesarios para habilitar el flujo de captura → validación → aprobación de geometrías provenientes de la PWA TeleGAN.

## 1. Tablas de Captura e Historial

```sql
-- Tabla de capturas provenientes de campo / PWA
CREATE TABLE IF NOT EXISTS finca_geometria_captura (
    id_captura SERIAL PRIMARY KEY,
    id_finca INT NOT NULL REFERENCES finca(id_finca),
    tipo_geometria VARCHAR(20) NOT NULL DEFAULT 'POLYGON', -- POLYGON / POINT / MULTIPOLYGON
    geometria_wkt TEXT NOT NULL,
    metadata JSONB DEFAULT '{}'::jsonb,
    capturado_por INT,             -- id_admin o brigadista (opcional)
    fuente VARCHAR(50) DEFAULT 'PWA',
    estado VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE', -- PENDIENTE, VALIDADA, RECHAZADA
    comentario TEXT,
    fecha_captura TIMESTAMP NOT NULL DEFAULT NOW(),
    fecha_procesado TIMESTAMP
);

COMMENT ON TABLE finca_geometria_captura IS 'Capturas de geometría enviadas por PWA para validar/aprobar en el panel administrativo.';
COMMENT ON COLUMN finca_geometria_captura.estado IS 'PENDIENTE = en revisión, VALIDADA = aprobada y migrada a PostGIS, RECHAZADA = descartada con observación.';


-- Tabla de historial de geometrías aprobadas
CREATE TABLE IF NOT EXISTS finca_geometria_historial (
    id_historial SERIAL PRIMARY KEY,
    id_finca INT NOT NULL REFERENCES finca(id_finca),
    geometria_wkt TEXT NOT NULL,
    geometria_postgis GEOMETRY,
    area_hectareas NUMERIC,
    aprobado_por INT,
    comentario TEXT,
    fecha_aprobacion TIMESTAMP NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE finca_geometria_historial IS 'Versionado de geometrías oficiales de finca (cada aprobación crea un registro).';
CREATE INDEX IF NOT EXISTS idx_finca_geometria_historial_finca ON finca_geometria_historial(id_finca);
```

## 2. Funciones auxiliares (PL/pgSQL)

### 2.1 Validar geometría WKT

```sql
CREATE OR REPLACE FUNCTION validar_geometria_wkt(p_wkt TEXT)
RETURNS TABLE(
    es_valida BOOLEAN,
    mensaje TEXT,
    geom_geom GEOMETRY
) AS $$
DECLARE
    v_geom GEOMETRY;
BEGIN
    BEGIN
        v_geom := ST_GeomFromText(p_wkt, 4326);
    EXCEPTION WHEN OTHERS THEN
        RETURN QUERY SELECT FALSE, 'WKT inválido: ' || SQLERRM, NULL::GEOMETRY;
        RETURN;
    END;

    IF NOT ST_IsValid(v_geom) THEN
        RETURN QUERY SELECT FALSE, 'La geometría no es válida según ST_IsValid.', v_geom;
        RETURN;
    END IF;

    IF ST_IsEmpty(v_geom) THEN
        RETURN QUERY SELECT FALSE, 'La geometría está vacía (ST_IsEmpty).', v_geom;
        RETURN;
    END IF;

    RETURN QUERY SELECT TRUE, 'OK', v_geom;
END;
$$ LANGUAGE plpgsql;
```

### 2.2 Aprobar geométrica de finca

```sql
CREATE OR REPLACE FUNCTION aprobar_geometria_finca(p_id_captura INT, p_id_admin INT, p_comentario TEXT DEFAULT NULL)
RETURNS TABLE(
    exito BOOLEAN,
    mensaje TEXT
) AS $$
DECLARE
    v_record finca_geometria_captura%ROWTYPE;
    v_geom GEOMETRY;
    v_valido BOOLEAN;
    v_msg TEXT;
    v_area NUMERIC;
BEGIN
    SELECT * INTO v_record FROM finca_geometria_captura WHERE id_captura = p_id_captura;
    IF NOT FOUND THEN
        RETURN QUERY SELECT FALSE, 'Captura no encontrada.';
        RETURN;
    END IF;

    SELECT es_valida, mensaje, geom_geom INTO v_valido, v_msg, v_geom
    FROM validar_geometria_wkt(v_record.geometria_wkt);

    IF v_valido IS DISTINCT FROM TRUE THEN
        UPDATE finca_geometria_captura
        SET estado = 'RECHAZADA', comentario = COALESCE(p_comentario, v_msg), fecha_procesado = NOW()
        WHERE id_captura = p_id_captura;
        RETURN QUERY SELECT FALSE, COALESCE(p_comentario, v_msg);
        RETURN;
    END IF;

    v_area := ST_Area(v_geom::geography) / 10000;

    INSERT INTO finca_geometria_historial (
        id_finca, geometria_wkt, geometria_postgis, area_hectareas,
        aprobado_por, comentario, fecha_aprobacion
    ) VALUES (
        v_record.id_finca, v_record.geometria_wkt, v_geom, v_area,
        p_id_admin, COALESCE(p_comentario, 'Aprobado automáticamente'), NOW()
    );

    UPDATE finca
    SET geometria_wkt = v_record.geometria_wkt,
        geometria_postgis = v_geom,
        area_hectareas = v_area,
        fecha_actualizacion = NOW()
    WHERE id_finca = v_record.id_finca;

    UPDATE finca_geometria_captura
    SET estado = 'VALIDADA', comentario = COALESCE(p_comentario, 'Aprobada'), fecha_procesado = NOW()
    WHERE id_captura = p_id_captura;

    RETURN QUERY SELECT TRUE, 'Geometría aprobada y migrada correctamente.';
END;
$$ LANGUAGE plpgsql;
```

> **Nota:** este procedimiento mínimo puede ampliarse con validaciones de solapamiento, restricciones por área, etc.

## 3. Flujo sugerido de ejecución

1. Ejecutar las sentencias DDL de las tablas (`finca_geometria_captura`, `finca_geometria_historial`).
2. Crear las funciones `validar_geometria_wkt` y `aprobar_geometria_finca` (u otras que se diseñen).
3. Implementar API/backend que invoque `aprobar_geometria_finca` y `rechazar_geometria_finca` según corresponda.
4. Registrar las nuevas sentencias en el script principal (`territorial-base.sql`) para mantener un único origen de verdad.

## 4. Próximos pasos

- Añadir funciones para rechazar capturas, detectar solapamientos y generar reportes masivos.
- Exponer endpoints REST (`captures-list`, `capture-detail`, `capture-approve`, `capture-reject`).
- Construir el módulo frontend `public/modules/fincas-geom/` reutilizando el layout común y Leaflet para la revisión gráfica.
