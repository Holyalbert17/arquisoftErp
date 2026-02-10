-- =====================================================
-- CORREGIR TRIGGER actualizar_credito_cliente
-- =====================================================
-- Este trigger intenta usar el campo 'tipo_pago' que no existe en la tabla 'ventas'
-- Se corrige para usar 'monto_pendiente' en su lugar

-- Eliminar triggers existentes (pueden estar en diferentes tablas)
DROP TRIGGER IF EXISTS trg_actualizar_credito_cliente ON ventas;
DROP TRIGGER IF EXISTS trg_actualizar_credito_cliente ON cuentas_cobrar;

-- Eliminar función existente (CASCADE eliminará también los triggers dependientes)
DROP FUNCTION IF EXISTS actualizar_credito_cliente() CASCADE;

-- Recrear la función corregida
CREATE OR REPLACE FUNCTION actualizar_credito_cliente()
RETURNS TRIGGER AS $$
DECLARE
    diferencia DECIMAL(15,2);
BEGIN
    -- Actualizar crédito del cliente cuando una venta se completa y tiene monto pendiente (crédito)
    -- En lugar de verificar tipo_pago, verificamos si hay monto_pendiente > 0
    IF NEW.estado = 'COMPLETADA' 
       AND NEW.monto_pendiente > 0
       AND (OLD.estado IS NULL OR OLD.estado != 'COMPLETADA') THEN
        
        -- Si hay cliente asociado, actualizar su crédito utilizado
        IF NEW.cliente_id IS NOT NULL THEN
            UPDATE clientes
            SET credito_utilizado = COALESCE(credito_utilizado, 0) + NEW.monto_pendiente,
                fecha_modificacion = CURRENT_TIMESTAMP
            WHERE id = NEW.cliente_id;
        END IF;
    END IF;
    
    -- Si la venta cambia de COMPLETADA a otro estado o se reduce el monto pendiente,
    -- reducir el crédito utilizado del cliente
    IF OLD.estado = 'COMPLETADA' AND NEW.estado != 'COMPLETADA' THEN
        IF NEW.cliente_id IS NOT NULL AND OLD.monto_pendiente IS NOT NULL AND OLD.monto_pendiente > 0 THEN
            UPDATE clientes
            SET credito_utilizado = GREATEST(0, COALESCE(credito_utilizado, 0) - OLD.monto_pendiente),
                fecha_modificacion = CURRENT_TIMESTAMP
            WHERE id = NEW.cliente_id;
        END IF;
    ELSIF OLD.monto_pendiente IS NOT NULL AND NEW.monto_pendiente IS NOT NULL 
          AND OLD.monto_pendiente > NEW.monto_pendiente THEN
        -- Se redujo el monto pendiente (se registró un pago)
        diferencia = OLD.monto_pendiente - NEW.monto_pendiente;
        IF NEW.cliente_id IS NOT NULL AND diferencia > 0 THEN
            UPDATE clientes
            SET credito_utilizado = GREATEST(0, COALESCE(credito_utilizado, 0) - diferencia),
                fecha_modificacion = CURRENT_TIMESTAMP
            WHERE id = NEW.cliente_id;
        END IF;
    END IF;
    
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Recrear el trigger
CREATE TRIGGER trg_actualizar_credito_cliente
AFTER INSERT OR UPDATE ON ventas
FOR EACH ROW
EXECUTE FUNCTION actualizar_credito_cliente();

-- Comentario para documentación
COMMENT ON FUNCTION actualizar_credito_cliente() IS 'Actualiza el crédito utilizado del cliente cuando una venta se completa con monto pendiente (venta a crédito)';
