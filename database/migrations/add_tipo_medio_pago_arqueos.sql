-- Migración: Agregar soporte para múltiples medios de pago en arqueos de caja
-- Fecha: 2026-01-21

-- 1. Agregar columna tipo_medio_pago a arqueos_caja_detalle
ALTER TABLE arqueos_caja_detalle 
ADD COLUMN IF NOT EXISTS tipo_medio_pago VARCHAR(50) DEFAULT 'EFECTIVO';

-- 2. Agregar columna descripcion para detalles adicionales (ej: número de cheque)
ALTER TABLE arqueos_caja_detalle 
ADD COLUMN IF NOT EXISTS descripcion TEXT;

-- 3. Modificar columna denominacion para que acepte valores descriptivos
ALTER TABLE arqueos_caja_detalle 
ALTER COLUMN denominacion TYPE VARCHAR(100);

-- 4. Agregar índice para mejorar consultas
CREATE INDEX IF NOT EXISTS idx_arqueo_tipo_medio 
ON arqueos_caja_detalle(arqueo_id, tipo_medio_pago);

-- 5. Comentarios para documentación
COMMENT ON COLUMN arqueos_caja_detalle.tipo_medio_pago IS 
'Tipo de medio de pago: EFECTIVO, TARJETA_DEBITO, TARJETA_CREDITO, TRANSFERENCIA, CHEQUE, OTRO';

COMMENT ON COLUMN arqueos_caja_detalle.descripcion IS 
'Descripción adicional del medio de pago (ej: número de cheque, últimos 4 dígitos de tarjeta)';

-- 6. Actualizar registros existentes (todos son efectivo)
UPDATE arqueos_caja_detalle 
SET tipo_medio_pago = 'EFECTIVO' 
WHERE tipo_medio_pago IS NULL;
