-- Script para corregir el trigger actualizar_stock_venta
-- El problema es que al eliminar una venta, el trigger intenta restar cantidad_reservada
-- pero puede resultar en un valor negativo, violando la restricción chk_reservada_positiva
-- Solución: Usar GREATEST(0, ...) para asegurar que cantidad_reservada nunca sea negativa

-- Eliminar el trigger y función existentes
DROP TRIGGER IF EXISTS trg_actualizar_stock_venta ON ventas_detalle;
DROP FUNCTION IF EXISTS actualizar_stock_venta();

-- Recrear la función con la corrección
CREATE OR REPLACE FUNCTION actualizar_stock_venta()
RETURNS TRIGGER AS $$
BEGIN
    -- Si se elimina un detalle de venta, revertir el stock
    IF TG_OP = 'DELETE' THEN
        UPDATE stock s
        SET cantidad = s.cantidad + OLD.cantidad,
            cantidad_reservada = GREATEST(0, s.cantidad_reservada - OLD.cantidad),
            fecha_actualizacion = CURRENT_TIMESTAMP
        WHERE s.producto_id = OLD.producto_id 
          AND s.sucursal_id = (SELECT sucursal_id FROM ventas WHERE id = OLD.venta_id);
    END IF;
    
    RETURN OLD;
END;
$$ LANGUAGE plpgsql;

-- Recrear el trigger
CREATE TRIGGER trg_actualizar_stock_venta
AFTER DELETE ON ventas_detalle
FOR EACH ROW
EXECUTE FUNCTION actualizar_stock_venta();
