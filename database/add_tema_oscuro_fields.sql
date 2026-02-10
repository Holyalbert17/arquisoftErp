-- =====================================================
-- AGREGAR CAMPO tema_oscuro A EMPRESAS, SUCURSALES Y USUARIOS
-- =====================================================

-- Agregar campo tema_oscuro a empresas
ALTER TABLE empresas 
ADD COLUMN IF NOT EXISTS tema_oscuro BOOLEAN DEFAULT false;

-- Agregar campo tema_oscuro a sucursales
ALTER TABLE sucursales 
ADD COLUMN IF NOT EXISTS tema_oscuro BOOLEAN DEFAULT false;

-- Agregar campo tema_oscuro a usuarios (si no existe ya en preferencias)
-- Nota: Los usuarios ya tienen el campo 'preferencias' JSON, pero agregamos un campo directo para facilitar consultas
ALTER TABLE usuarios 
ADD COLUMN IF NOT EXISTS tema_oscuro BOOLEAN DEFAULT NULL;

-- Crear índices para mejorar rendimiento
CREATE INDEX IF NOT EXISTS idx_empresas_tema_oscuro ON empresas(tema_oscuro);
CREATE INDEX IF NOT EXISTS idx_sucursales_tema_oscuro ON sucursales(tema_oscuro);
CREATE INDEX IF NOT EXISTS idx_usuarios_tema_oscuro ON usuarios(tema_oscuro);

-- Comentarios para documentación
COMMENT ON COLUMN empresas.tema_oscuro IS 'Indica si la empresa usa tema oscuro por defecto (true) o claro (false)';
COMMENT ON COLUMN sucursales.tema_oscuro IS 'Indica si la sucursal usa tema oscuro por defecto (true) o claro (false). Si es NULL, hereda de la empresa';
COMMENT ON COLUMN usuarios.tema_oscuro IS 'Indica si el usuario prefiere tema oscuro (true) o claro (false). Si es NULL, hereda de sucursal/empresa. También se puede guardar en preferencias JSON';
