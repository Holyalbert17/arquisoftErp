-- =====================================================
-- TABLA DE SESIONES DE USUARIO
-- =====================================================

CREATE TABLE IF NOT EXISTS sesiones (
    id SERIAL PRIMARY KEY,
    usuario_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    session_id VARCHAR(128) NOT NULL,
    ip_address VARCHAR(50),
    user_agent TEXT,
    fecha_inicio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_fin TIMESTAMP,
    activa BOOLEAN DEFAULT true,
    ultima_actividad TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_sesiones_usuario ON sesiones(usuario_id);
CREATE INDEX IF NOT EXISTS idx_sesiones_activa ON sesiones(activa);
CREATE INDEX IF NOT EXISTS idx_sesiones_session_id ON sesiones(session_id);

COMMENT ON TABLE sesiones IS 'Registro de sesiones activas de usuarios';
