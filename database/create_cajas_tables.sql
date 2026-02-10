-- =====================================================
-- TABLAS PARA SISTEMA DE CAJAS
-- =====================================================

-- Tabla: cajas
CREATE TABLE IF NOT EXISTS cajas (
    id SERIAL PRIMARY KEY,
    empresa_id INTEGER NOT NULL REFERENCES empresas(id) ON DELETE RESTRICT,
    sucursal_id INTEGER NOT NULL REFERENCES sucursales(id) ON DELETE RESTRICT,
    usuario_id INTEGER NOT NULL REFERENCES usuarios(id),
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    estado VARCHAR(20) DEFAULT 'CERRADA' CHECK (estado IN ('ABIERTA', 'CERRADA', 'BLOQUEADA')),
    saldo_inicial DECIMAL(15,2) DEFAULT 0,
    saldo_actual DECIMAL(15,2) DEFAULT 0,
    saldo_esperado DECIMAL(15,2) DEFAULT 0,
    diferencia DECIMAL(15,2) DEFAULT 0,
    fecha_apertura TIMESTAMP,
    fecha_cierre TIMESTAMP,
    usuario_apertura INTEGER REFERENCES usuarios(id),
    usuario_cierre INTEGER REFERENCES usuarios(id),
    observaciones_apertura TEXT,
    observaciones_cierre TEXT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT uk_caja_nombre UNIQUE(empresa_id, sucursal_id, nombre)
);

CREATE INDEX IF NOT EXISTS idx_cajas_empresa ON cajas(empresa_id);
CREATE INDEX IF NOT EXISTS idx_cajas_sucursal ON cajas(sucursal_id);
CREATE INDEX IF NOT EXISTS idx_cajas_usuario ON cajas(usuario_id);
CREATE INDEX IF NOT EXISTS idx_cajas_estado ON cajas(estado);

-- Tabla: movimientos_caja
CREATE TABLE IF NOT EXISTS movimientos_caja (
    id SERIAL PRIMARY KEY,
    caja_id INTEGER NOT NULL REFERENCES cajas(id) ON DELETE RESTRICT,
    tipo_movimiento VARCHAR(30) NOT NULL CHECK (tipo_movimiento IN (
        'APERTURA', 'CIERRE', 'VENTA', 'PAGO_CUENTA_COBRAR', 
        'PAGO_CUENTA_PAGAR', 'EGRESO', 'INGRESO', 'TRANSFERENCIA_ENTRADA',
        'TRANSFERENCIA_SALIDA', 'AJUSTE'
    )),
    monto DECIMAL(15,2) NOT NULL,
    moneda VARCHAR(3) DEFAULT 'PYG' CHECK (moneda IN ('PYG', 'USD', 'BRL')),
    tipo_cambio DECIMAL(15,6) DEFAULT 1,
    monto_moneda_principal DECIMAL(15,2),
    concepto VARCHAR(200) NOT NULL,
    referencia VARCHAR(100),
    documento_id INTEGER,
    tipo_documento VARCHAR(50),
    observaciones TEXT,
    fecha_movimiento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    usuario_id INTEGER REFERENCES usuarios(id),
    
    CONSTRAINT chk_monto_positivo CHECK (monto > 0)
);

CREATE INDEX IF NOT EXISTS idx_movimientos_caja ON movimientos_caja(caja_id);
CREATE INDEX IF NOT EXISTS idx_movimientos_tipo ON movimientos_caja(tipo_movimiento);
CREATE INDEX IF NOT EXISTS idx_movimientos_fecha ON movimientos_caja(fecha_movimiento DESC);

-- Tabla: arqueos_caja
CREATE TABLE IF NOT EXISTS arqueos_caja (
    id SERIAL PRIMARY KEY,
    caja_id INTEGER NOT NULL REFERENCES cajas(id) ON DELETE RESTRICT,
    fecha_arqueo DATE NOT NULL,
    hora_arqueo TIME NOT NULL,
    saldo_sistema DECIMAL(15,2) NOT NULL,
    saldo_fisico DECIMAL(15,2) NOT NULL,
    diferencia DECIMAL(15,2) DEFAULT 0,
    observaciones TEXT,
    usuario_id INTEGER REFERENCES usuarios(id),
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT uk_arqueo_caja_fecha UNIQUE(caja_id, fecha_arqueo, hora_arqueo)
);

CREATE INDEX IF NOT EXISTS idx_arqueos_caja ON arqueos_caja(caja_id);
CREATE INDEX IF NOT EXISTS idx_arqueos_fecha ON arqueos_caja(fecha_arqueo DESC);

-- Tabla: arqueos_caja_detalle
CREATE TABLE IF NOT EXISTS arqueos_caja_detalle (
    id SERIAL PRIMARY KEY,
    arqueo_id INTEGER NOT NULL REFERENCES arqueos_caja(id) ON DELETE CASCADE,
    denominacion VARCHAR(50) NOT NULL,
    cantidad INTEGER NOT NULL DEFAULT 0,
    subtotal DECIMAL(15,2) NOT NULL,
    orden INTEGER DEFAULT 0
);

CREATE INDEX IF NOT EXISTS idx_arqueos_detalle_arqueo ON arqueos_caja_detalle(arqueo_id);

-- Trigger para calcular diferencia en arqueos
CREATE OR REPLACE FUNCTION calcular_diferencia_arqueo()
RETURNS TRIGGER AS $$
BEGIN
    NEW.diferencia = NEW.saldo_fisico - NEW.saldo_sistema;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_calcular_diferencia_arqueo ON arqueos_caja;
CREATE TRIGGER trg_calcular_diferencia_arqueo
BEFORE INSERT OR UPDATE ON arqueos_caja
FOR EACH ROW EXECUTE FUNCTION calcular_diferencia_arqueo();

-- Trigger para actualizar saldo_actual de caja
CREATE OR REPLACE FUNCTION actualizar_saldo_caja()
RETURNS TRIGGER AS $$
DECLARE
    v_tipo VARCHAR;
    v_monto DECIMAL;
BEGIN
    IF TG_OP = 'INSERT' THEN
        v_tipo = NEW.tipo_movimiento;
        v_monto = NEW.monto_moneda_principal;
        
        -- Actualizar saldo según tipo de movimiento
        IF v_tipo IN ('APERTURA', 'VENTA', 'PAGO_CUENTA_COBRAR', 'INGRESO', 'TRANSFERENCIA_ENTRADA', 'AJUSTE') THEN
            UPDATE cajas 
            SET saldo_actual = saldo_actual + v_monto,
                fecha_modificacion = CURRENT_TIMESTAMP
            WHERE id = NEW.caja_id;
        ELSIF v_tipo IN ('CIERRE', 'PAGO_CUENTA_PAGAR', 'EGRESO', 'TRANSFERENCIA_SALIDA') THEN
            UPDATE cajas 
            SET saldo_actual = saldo_actual - v_monto,
                fecha_modificacion = CURRENT_TIMESTAMP
            WHERE id = NEW.caja_id;
        END IF;
    END IF;
    
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_actualizar_saldo_caja ON movimientos_caja;
CREATE TRIGGER trg_actualizar_saldo_caja
AFTER INSERT ON movimientos_caja
FOR EACH ROW EXECUTE FUNCTION actualizar_saldo_caja();

COMMENT ON TABLE cajas IS 'Cajas registradoras por sucursal';
COMMENT ON TABLE movimientos_caja IS 'Movimientos de efectivo en las cajas';
COMMENT ON TABLE arqueos_caja IS 'Arqueos de caja realizados';
COMMENT ON TABLE arqueos_caja_detalle IS 'Detalle de denominaciones en arqueos';
