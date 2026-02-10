-- Script para corregir el trigger completar_stock_venta
-- El problema es que la columna 'cantidad' es ambigua en el UPDATE
-- Necesita especificar explícitamente s.cantidad

-- Eliminar el trigger y función existentes
DROP TRIGGER IF EXISTS trg_completar_stock_venta ON ventas;
DROP FUNCTION IF EXISTS completar_stock_venta();

-- Recrear la función con la corrección
CREATE OR REPLACE FUNCTION completar_stock_venta()
RETURNS TRIGGER AS $$
BEGIN
    -- Solo procesar si el estado es COMPLETADA
    IF NEW.estado = 'COMPLETADA' AND (OLD.estado IS NULL OR OLD.estado != 'COMPLETADA') THEN
        -- Actualizar stock restando las cantidades vendidas
        UPDATE stock s
        SET cantidad = s.cantidad - vd.cantidad,
            cantidad_reservada = s.cantidad_reservada - vd.cantidad,
            fecha_actualizacion = CURRENT_TIMESTAMP
        FROM ventas_detalle vd
        WHERE s.producto_id = vd.producto_id
          AND s.sucursal_id = NEW.sucursal_id
          AND vd.venta_id = NEW.id;
    END IF;
    
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Recrear el trigger
CREATE TRIGGER trg_completar_stock_venta
AFTER UPDATE ON ventas
FOR EACH ROW
WHEN (NEW.estado = 'COMPLETADA' AND (OLD.estado IS NULL OR OLD.estado != 'COMPLETADA'))
EXECUTE FUNCTION completar_stock_venta();
