-- =====================================================
-- ESQUEMA DE BASE DE DATOS - SISTEMA DE AUTENTICACIÓN
-- =====================================================

-- Tabla de usuarios del sistema (administradores, técnicos)
CREATE TABLE IF NOT EXISTS admin_users (
    id_admin SERIAL PRIMARY KEY,
    nombre_completo VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    telefono VARCHAR(20),
    rol VARCHAR(50) NOT NULL DEFAULT 'TECNICO', -- SUPER_ADMIN, TECNICO, ADMIN_FINCA
    activo BOOLEAN DEFAULT FALSE, -- Solo activo después de confirmar email
    email_verificado BOOLEAN DEFAULT FALSE,
    telefono_verificado BOOLEAN DEFAULT FALSE,
    ultima_sesion TIMESTAMP,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    codigo_confirmacion VARCHAR(6), -- PIN de 6 dígitos
    token_confirmacion VARCHAR(255), -- Token para confirmación
    expiracion_confirmacion TIMESTAMP, -- Expiración del token
    intentos_login INTEGER DEFAULT 0, -- Para rate limiting
    bloqueado_hasta TIMESTAMP, -- Bloqueo temporal por intentos
    created_by INTEGER REFERENCES admin_users(id_admin), -- Quién creó la cuenta
    metadata JSONB -- Datos adicionales
);

-- Índices para optimización
CREATE INDEX IF NOT EXISTS idx_admin_users_email ON admin_users(email);
CREATE INDEX IF NOT EXISTS idx_admin_users_rol ON admin_users(rol);
CREATE INDEX IF NOT EXISTS idx_admin_users_activo ON admin_users(activo);
CREATE INDEX IF NOT EXISTS idx_admin_users_codigo_confirmacion ON admin_users(codigo_confirmacion);
CREATE INDEX IF NOT EXISTS idx_admin_users_token_confirmacion ON admin_users(token_confirmacion);

-- Tabla de sesiones activas
CREATE TABLE IF NOT EXISTS admin_sessions (
    id_sesion SERIAL PRIMARY KEY,
    id_admin INTEGER NOT NULL REFERENCES admin_users(id_admin),
    token_sesion VARCHAR(255) UNIQUE NOT NULL,
    ip_address INET,
    user_agent TEXT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_expiracion TIMESTAMP NOT NULL,
    activa BOOLEAN DEFAULT TRUE,
    metadata JSONB
);

-- Índices para sesiones
CREATE INDEX IF NOT EXISTS idx_admin_sessions_token ON admin_sessions(token_sesion);
CREATE INDEX IF NOT EXISTS idx_admin_sessions_admin ON admin_sessions(id_admin);
CREATE INDEX IF NOT EXISTS idx_admin_sessions_activa ON admin_sessions(activa);

-- Tabla de tokens de aplicación (para comunicación entre apps)
CREATE TABLE IF NOT EXISTS app_tokens (
    id_token SERIAL PRIMARY KEY,
    token_hash VARCHAR(255) UNIQUE NOT NULL,
    nombre_aplicacion VARCHAR(100) NOT NULL,
    dominio_autorizado VARCHAR(255) NOT NULL,
    permisos JSONB, -- Array de permisos
    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_expiracion TIMESTAMP,
    ultimo_uso TIMESTAMP,
    created_by INTEGER REFERENCES admin_users(id_admin)
);

-- Índices para tokens de aplicación
CREATE INDEX IF NOT EXISTS idx_app_tokens_hash ON app_tokens(token_hash);
CREATE INDEX IF NOT EXISTS idx_app_tokens_dominio ON app_tokens(dominio_autorizado);
CREATE INDEX IF NOT EXISTS idx_app_tokens_activo ON app_tokens(activo);

-- Tabla de logs de seguridad
CREATE TABLE IF NOT EXISTS security_logs (
    id_log SERIAL PRIMARY KEY,
    id_admin INTEGER REFERENCES admin_users(id_admin),
    tipo_evento VARCHAR(50) NOT NULL, -- LOGIN, LOGOUT, REGISTER, CONFIRM, FAILED_LOGIN, etc.
    ip_address INET,
    user_agent TEXT,
    detalles JSONB,
    fecha_evento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    severidad VARCHAR(20) DEFAULT 'INFO' -- INFO, WARNING, ERROR, CRITICAL
);

-- Índices para logs
CREATE INDEX IF NOT EXISTS idx_security_logs_admin ON security_logs(id_admin);
CREATE INDEX IF NOT EXISTS idx_security_logs_tipo ON security_logs(tipo_evento);
CREATE INDEX IF NOT EXISTS idx_security_logs_fecha ON security_logs(fecha_evento);
CREATE INDEX IF NOT EXISTS idx_security_logs_severidad ON security_logs(severidad);

-- Tabla de confirmaciones pendientes
CREATE TABLE IF NOT EXISTS pending_confirmations (
    id_confirmacion SERIAL PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    codigo_confirmacion VARCHAR(6) NOT NULL,
    token_confirmacion VARCHAR(255) UNIQUE NOT NULL,
    tipo_confirmacion VARCHAR(50) NOT NULL, -- REGISTER, RESET_PASSWORD, CHANGE_EMAIL
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_expiracion TIMESTAMP NOT NULL,
    intentos INTEGER DEFAULT 0,
    completada BOOLEAN DEFAULT FALSE,
    metadata JSONB
);

-- Índices para confirmaciones
CREATE INDEX IF NOT EXISTS idx_pending_confirmations_email ON pending_confirmations(email);
CREATE INDEX IF NOT EXISTS idx_pending_confirmations_token ON pending_confirmations(token_confirmacion);
CREATE INDEX IF NOT EXISTS idx_pending_confirmations_codigo ON pending_confirmations(codigo_confirmacion);
CREATE INDEX IF NOT EXISTS idx_pending_confirmations_expiracion ON pending_confirmations(fecha_expiracion);

-- Función para actualizar fecha_actualizacion automáticamente
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.fecha_actualizacion = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Triggers para actualizar fecha_actualizacion
CREATE TRIGGER update_admin_users_updated_at 
    BEFORE UPDATE ON admin_users 
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Vista para usuarios activos
CREATE OR REPLACE VIEW v_admin_users_activos AS
SELECT 
    id_admin,
    nombre_completo,
    email,
    telefono,
    rol,
    email_verificado,
    telefono_verificado,
    ultima_sesion,
    fecha_registro,
    CASE 
        WHEN ultima_sesion IS NULL THEN 'Nunca inició sesión'
        WHEN ultima_sesion < CURRENT_DATE - INTERVAL '30 days' THEN 'Inactivo 30+ días'
        WHEN ultima_sesion < CURRENT_DATE - INTERVAL '7 days' THEN 'Inactivo 7+ días'
        ELSE 'Activo'
    END as estado_sesion
FROM admin_users 
WHERE activo = TRUE;

-- Comentarios en las tablas
COMMENT ON TABLE admin_users IS 'Usuarios del sistema (administradores, técnicos)';
COMMENT ON TABLE admin_sessions IS 'Sesiones activas de usuarios del sistema';
COMMENT ON TABLE app_tokens IS 'Tokens de aplicación para comunicación entre apps';
COMMENT ON TABLE security_logs IS 'Logs de seguridad y auditoría';
COMMENT ON TABLE pending_confirmations IS 'Confirmaciones pendientes de email';


