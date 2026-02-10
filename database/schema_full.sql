--
-- PostgreSQL database dump
--

\restrict O870L5eLnZXkHZCsI5cmwldj63buhg7HdWbNGhFd5ukB1aovjHeNgUjR5C0qUzS

-- Dumped from database version 17.7
-- Dumped by pg_dump version 17.7

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: pgcrypto; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS pgcrypto WITH SCHEMA public;


--
-- Name: EXTENSION pgcrypto; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION pgcrypto IS 'cryptographic functions';


--
-- Name: uuid-ossp; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS "uuid-ossp" WITH SCHEMA public;


--
-- Name: EXTENSION "uuid-ossp"; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION "uuid-ossp" IS 'generate universally unique identifiers (UUIDs)';


--
-- Name: activar_modulos_base_empresa(integer, integer); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.activar_modulos_base_empresa(p_empresa_id integer, p_usuario_id integer DEFAULT NULL::integer) RETURNS integer
    LANGUAGE plpgsql
    AS $$
DECLARE
    v_modulo RECORD;
    v_contador INTEGER := 0;
BEGIN
    -- Activar todos los módulos base
    FOR v_modulo IN
        SELECT id FROM modulos WHERE es_modulo_base = true AND estado = true
    LOOP
        PERFORM asignar_modulo_empresa(
            p_empresa_id,
            v_modulo.id,
            'PERPETUA',
            NULL,
            p_usuario_id
        );
        v_contador := v_contador + 1;
    END LOOP;

    RETURN v_contador;
END;
$$;


--
-- Name: FUNCTION activar_modulos_base_empresa(p_empresa_id integer, p_usuario_id integer); Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON FUNCTION public.activar_modulos_base_empresa(p_empresa_id integer, p_usuario_id integer) IS 'Activa automáticamente todos los módulos base para una nueva empresa';


--
-- Name: activar_modulos_todas_sucursales(integer, integer); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.activar_modulos_todas_sucursales(p_empresa_id integer, p_usuario_id integer DEFAULT NULL::integer) RETURNS integer
    LANGUAGE plpgsql
    AS $$
DECLARE
    v_sucursal RECORD;
    v_modulo RECORD;
    v_contador INTEGER := 0;
BEGIN
    -- Para cada sucursal de la empresa
    FOR v_sucursal IN
        SELECT id FROM sucursales WHERE empresa_id = p_empresa_id AND estado = true
    LOOP
        -- Activar todos los módulos activos de la empresa
        FOR v_modulo IN
            SELECT modulo_id
            FROM empresa_modulos
            WHERE empresa_id = p_empresa_id AND activo = true
        LOOP
            BEGIN
                PERFORM asignar_modulo_sucursal(
                    v_sucursal.id,
                    v_modulo.modulo_id,
                    p_usuario_id
                );
                v_contador := v_contador + 1;
            EXCEPTION
                WHEN OTHERS THEN
                    -- Ignorar errores y continuar
                    NULL;
            END;
        END LOOP;
    END LOOP;

    RETURN v_contador;
END;
$$;


--
-- Name: FUNCTION activar_modulos_todas_sucursales(p_empresa_id integer, p_usuario_id integer); Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON FUNCTION public.activar_modulos_todas_sucursales(p_empresa_id integer, p_usuario_id integer) IS 'Activa los módulos de la empresa en todas sus sucursales';


--
-- Name: actualizar_credito_cliente(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.actualizar_credito_cliente() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
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
$$;


--
-- Name: FUNCTION actualizar_credito_cliente(); Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON FUNCTION public.actualizar_credito_cliente() IS 'Actualiza el crédito utilizado del cliente cuando una venta se completa con monto pendiente (venta a crédito)';


--
-- Name: actualizar_fecha_modificacion(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.actualizar_fecha_modificacion() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.fecha_modificacion = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;


--
-- Name: actualizar_saldo_caja(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.actualizar_saldo_caja() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE
    v_tipo VARCHAR;
    v_monto DECIMAL;
BEGIN
    IF TG_OP = 'INSERT' THEN
        v_tipo = NEW.tipo_movimiento;
        v_monto = NEW.monto_moneda_principal;
        
        -- CIERRE no afecta saldo (es solo cambio de estado)
        IF v_tipo = 'CIERRE' THEN
            RETURN NEW;
        END IF;
        
        -- Actualizar saldo segun tipo de movimiento
        IF v_tipo IN ('APERTURA', 'VENTA', 'PAGO_CUENTA_COBRAR', 'INGRESO', 'COBRO', 'TRANSFERENCIA_ENTRADA', 'AJUSTE') THEN
            UPDATE cajas 
            SET saldo_actual = saldo_actual + v_monto,
                fecha_modificacion = CURRENT_TIMESTAMP
            WHERE id = NEW.caja_id;
        ELSIF v_tipo IN ('PAGO_CUENTA_PAGAR', 'EGRESO', 'GASTO', 'DEVOLUCION', 'TRANSFERENCIA_SALIDA') THEN
            UPDATE cajas 
            SET saldo_actual = saldo_actual - v_monto,
                fecha_modificacion = CURRENT_TIMESTAMP
            WHERE id = NEW.caja_id;
        END IF;
    END IF;
    
    RETURN NEW;
END;
$$;


--
-- Name: actualizar_stock_venta(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.actualizar_stock_venta() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
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
$$;


--
-- Name: asignar_modulo_empresa(integer, integer, character varying, integer, integer); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.asignar_modulo_empresa(p_empresa_id integer, p_modulo_id integer, p_tipo_licencia character varying DEFAULT 'MENSUAL'::character varying, p_dias_vigencia integer DEFAULT 30, p_usuario_id integer DEFAULT NULL::integer) RETURNS integer
    LANGUAGE plpgsql
    AS $$
DECLARE
    v_empresa_modulo_id INTEGER;
    v_fecha_vencimiento TIMESTAMP;
BEGIN
    -- Calcular fecha de vencimiento
    IF p_tipo_licencia = 'PERPETUA' THEN
        v_fecha_vencimiento := NULL;
    ELSE
        v_fecha_vencimiento := CURRENT_TIMESTAMP + (p_dias_vigencia || ' days')::INTERVAL;
    END IF;

    -- Insertar o actualizar
    INSERT INTO empresa_modulos (
        empresa_id,
        modulo_id,
        activo,
        tipo_licencia,
        fecha_inicio_licencia,
        fecha_fin_licencia,
        fecha_vencimiento,
        usuario_asigna
    ) VALUES (
        p_empresa_id,
        p_modulo_id,
        true,
        p_tipo_licencia,
        CURRENT_DATE,
        CASE WHEN v_fecha_vencimiento IS NOT NULL THEN v_fecha_vencimiento::DATE ELSE NULL END,
        v_fecha_vencimiento,
        p_usuario_id
    )
    ON CONFLICT (empresa_id, modulo_id)
    DO UPDATE SET
        activo = true,
        tipo_licencia = p_tipo_licencia,
        fecha_vencimiento = v_fecha_vencimiento,
        fecha_modificacion = CURRENT_TIMESTAMP
    RETURNING id INTO v_empresa_modulo_id;

    RETURN v_empresa_modulo_id;
END;
$$;


--
-- Name: FUNCTION asignar_modulo_empresa(p_empresa_id integer, p_modulo_id integer, p_tipo_licencia character varying, p_dias_vigencia integer, p_usuario_id integer); Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON FUNCTION public.asignar_modulo_empresa(p_empresa_id integer, p_modulo_id integer, p_tipo_licencia character varying, p_dias_vigencia integer, p_usuario_id integer) IS 'Asigna un módulo a una empresa con su licencia';


--
-- Name: asignar_modulo_sucursal(integer, integer, integer); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.asignar_modulo_sucursal(p_sucursal_id integer, p_modulo_id integer, p_usuario_id integer DEFAULT NULL::integer) RETURNS integer
    LANGUAGE plpgsql
    AS $$
DECLARE
    v_sucursal_modulo_id INTEGER;
    v_empresa_id INTEGER;
    v_empresa_modulo_id INTEGER;
BEGIN
    -- Obtener empresa_id de la sucursal
    SELECT empresa_id INTO v_empresa_id
    FROM sucursales
    WHERE id = p_sucursal_id;

    -- Verificar que la empresa tenga el módulo
    SELECT id INTO v_empresa_modulo_id
    FROM empresa_modulos
    WHERE empresa_id = v_empresa_id
    AND modulo_id = p_modulo_id
    AND activo = true;

    IF v_empresa_modulo_id IS NULL THEN
        RAISE EXCEPTION 'La empresa no tiene este módulo activo';
    END IF;

    -- Insertar o actualizar
    INSERT INTO sucursal_modulos (
        sucursal_id,
        modulo_id,
        empresa_modulo_id,
        activo,
        usuario_asigna
    ) VALUES (
        p_sucursal_id,
        p_modulo_id,
        v_empresa_modulo_id,
        true,
        p_usuario_id
    )
    ON CONFLICT (sucursal_id, modulo_id)
    DO UPDATE SET
        activo = true,
        fecha_modificacion = CURRENT_TIMESTAMP
    RETURNING id INTO v_sucursal_modulo_id;

    RETURN v_sucursal_modulo_id;
END;
$$;


--
-- Name: FUNCTION asignar_modulo_sucursal(p_sucursal_id integer, p_modulo_id integer, p_usuario_id integer); Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON FUNCTION public.asignar_modulo_sucursal(p_sucursal_id integer, p_modulo_id integer, p_usuario_id integer) IS 'Asigna un módulo a una sucursal (debe estar en la empresa primero)';


--
-- Name: calcular_cantidad_disponible(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.calcular_cantidad_disponible() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.cantidad_disponible = NEW.cantidad - NEW.cantidad_reservada;
    RETURN NEW;
END;
$$;


--
-- Name: calcular_cantidad_pendiente_compra(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.calcular_cantidad_pendiente_compra() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.cantidad_pendiente = NEW.cantidad_solicitada - NEW.cantidad_recibida;
    RETURN NEW;
END;
$$;


--
-- Name: calcular_cantidad_pendiente_transferencia(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.calcular_cantidad_pendiente_transferencia() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.cantidad_pendiente = NEW.cantidad_solicitada - COALESCE(NEW.cantidad_recibida, 0);
    RETURN NEW;
END;
$$;


--
-- Name: calcular_credito_disponible_cliente(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.calcular_credito_disponible_cliente() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.credito_disponible = NEW.limite_credito - NEW.credito_utilizado;
    RETURN NEW;
END;
$$;


--
-- Name: calcular_cuenta_cobrar(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.calcular_cuenta_cobrar() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    -- Calcular monto pendiente
    NEW.monto_pendiente = NEW.monto_total - NEW.monto_pagado;
    
    -- Calcular días vencido
    IF NEW.monto_pendiente > 0 AND CURRENT_DATE > NEW.fecha_vencimiento THEN
        NEW.dias_vencido = CURRENT_DATE - NEW.fecha_vencimiento;
    ELSE
        NEW.dias_vencido = 0;
    END IF;
    
    -- Actualizar estado
    IF NEW.monto_pendiente <= 0 THEN
        NEW.estado = 'PAGADO';
    ELSIF NEW.dias_vencido > 0 THEN
        NEW.estado = 'VENCIDO';
    ELSE
        NEW.estado = 'PENDIENTE';
    END IF;
    
    RETURN NEW;
END;
$$;


--
-- Name: calcular_cuenta_pagar(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.calcular_cuenta_pagar() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    -- Calcular monto pendiente
    NEW.monto_pendiente = NEW.monto_total - NEW.monto_pagado;
    
    -- Calcular días vencido
    IF NEW.monto_pendiente > 0 AND CURRENT_DATE > NEW.fecha_vencimiento THEN
        NEW.dias_vencido = CURRENT_DATE - NEW.fecha_vencimiento;
    ELSE
        NEW.dias_vencido = 0;
    END IF;
    
    -- Actualizar estado
    IF NEW.monto_pendiente <= 0 THEN
        NEW.estado = 'PAGADO';
    ELSIF NEW.dias_vencido > 0 THEN
        NEW.estado = 'VENCIDO';
    ELSE
        NEW.estado = 'PENDIENTE';
    END IF;
    
    RETURN NEW;
END;
$$;


--
-- Name: calcular_dias_habiles(date, date); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.calcular_dias_habiles(fecha_inicio date, fecha_fin date) RETURNS integer
    LANGUAGE plpgsql
    AS $$
DECLARE
    dias_totales INTEGER;
    dias_festivos INTEGER;
BEGIN
    -- Calcular días totales entre las fechas
    dias_totales := fecha_fin - fecha_inicio + 1;
    
    -- Restar sábados y domingos
    dias_totales := dias_totales - (
        SELECT COUNT(*)
        FROM generate_series(fecha_inicio, fecha_fin, '1 day') AS fecha
        WHERE EXTRACT(DOW FROM fecha) IN (0, 6)
    );
    
    -- Restar feriados (aquí se podría agregar una tabla de feriados)
    -- Por ahora no restamos feriados específicos
    
    RETURN GREATEST(dias_totales, 0);
END;
$$;


--
-- Name: calcular_diferencia_ajuste(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.calcular_diferencia_ajuste() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.diferencia = NEW.cantidad_nueva - NEW.cantidad_existente;
    RETURN NEW;
END;
$$;


--
-- Name: calcular_diferencia_arqueo(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.calcular_diferencia_arqueo() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.diferencia = NEW.saldo_fisico - NEW.saldo_sistema;
    RETURN NEW;
END;
$$;


--
-- Name: calcular_edad(date); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.calcular_edad(fecha_nacimiento date) RETURNS integer
    LANGUAGE plpgsql
    AS $$
BEGIN
    RETURN EXTRACT(YEAR FROM AGE(fecha_nacimiento));
END;
$$;


--
-- Name: calcular_monto_pendiente_compra(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.calcular_monto_pendiente_compra() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.monto_pendiente = NEW.total - NEW.monto_pagado;
    RETURN NEW;
END;
$$;


--
-- Name: calcular_monto_pendiente_venta(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.calcular_monto_pendiente_venta() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.monto_pendiente = NEW.total - NEW.monto_pagado;
    RETURN NEW;
END;
$$;


--
-- Name: desactivar_modulo_con_dependientes(integer, integer); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.desactivar_modulo_con_dependientes(p_empresa_id integer, p_modulo_id integer) RETURNS integer
    LANGUAGE plpgsql
    AS $$
DECLARE
    v_modulo_dependiente RECORD;
    v_contador INTEGER := 0;
BEGIN
    -- Desactivar módulos que dependen de este
    FOR v_modulo_dependiente IN
        SELECT DISTINCT em.id, m.nombre
        FROM modulos_dependencias md
        INNER JOIN empresa_modulos em ON em.modulo_id = md.modulo_id
        INNER JOIN modulos m ON m.id = md.modulo_id
        WHERE md.modulo_requerido_id = p_modulo_id
        AND em.empresa_id = p_empresa_id
        AND em.activo = true
        AND md.es_obligatorio = true
    LOOP
        UPDATE empresa_modulos
        SET activo = false,
            fecha_modificacion = CURRENT_TIMESTAMP
        WHERE id = v_modulo_dependiente.id;

        v_contador := v_contador + 1;

        -- Crear notificación
        INSERT INTO notificaciones (
            empresa_id,
            tipo,
            titulo,
            mensaje,
            fecha_creacion
        ) VALUES (
            p_empresa_id,
            'INFO',
            'Módulo desactivado automáticamente',
            'El módulo "' || v_modulo_dependiente.nombre || '" fue desactivado porque depende de otro módulo que fue desactivado',
            CURRENT_TIMESTAMP
        );
    END LOOP;

    -- Desactivar el módulo principal
    UPDATE empresa_modulos
    SET activo = false,
        fecha_modificacion = CURRENT_TIMESTAMP
    WHERE empresa_id = p_empresa_id
    AND modulo_id = p_modulo_id;

    RETURN v_contador;
END;
$$;


--
-- Name: FUNCTION desactivar_modulo_con_dependientes(p_empresa_id integer, p_modulo_id integer); Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON FUNCTION public.desactivar_modulo_con_dependientes(p_empresa_id integer, p_modulo_id integer) IS 'Desactiva un módulo y todos los que dependen de él';


--
-- Name: desactivar_modulos_sucursales(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.desactivar_modulos_sucursales() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    IF NEW.activo = false AND OLD.activo = true THEN
        -- Desactivar en todas las sucursales
        UPDATE sucursal_modulos
        SET activo = false,
            fecha_modificacion = CURRENT_TIMESTAMP
        WHERE empresa_modulo_id = NEW.id;
    END IF;

    RETURN NEW;
END;
$$;


--
-- Name: desactivar_modulos_vencidos_automaticamente(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.desactivar_modulos_vencidos_automaticamente() RETURNS integer
    LANGUAGE plpgsql
    AS $$
DECLARE
    v_contador INTEGER := 0;
    v_registro RECORD;
BEGIN
    FOR v_registro IN
        SELECT em.id, e.nombre as empresa, m.nombre as modulo
        FROM empresa_modulos em
        INNER JOIN empresas e ON e.id = em.empresa_id
        INNER JOIN modulos m ON m.id = em.modulo_id
        WHERE em.activo = true
        AND em.fecha_vencimiento IS NOT NULL
        AND em.fecha_vencimiento < CURRENT_TIMESTAMP
    LOOP
        -- Desactivar el módulo
        UPDATE empresa_modulos
        SET activo = false,
            fecha_modificacion = CURRENT_TIMESTAMP
        WHERE id = v_registro.id;

        -- Crear notificación
        INSERT INTO notificaciones (
            empresa_id,
            tipo,
            titulo,
            mensaje,
            fecha_creacion
        ) VALUES (
            (SELECT empresa_id FROM empresa_modulos WHERE id = v_registro.id),
            'ERROR',
            'Módulo desactivado por vencimiento',
            'El módulo "' || v_registro.modulo || '" ha sido desactivado porque su licencia venció',
            CURRENT_TIMESTAMP
        );

        v_contador := v_contador + 1;
    END LOOP;

    RETURN v_contador;
END;
$$;


--
-- Name: FUNCTION desactivar_modulos_vencidos_automaticamente(); Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON FUNCTION public.desactivar_modulos_vencidos_automaticamente() IS 'Desactiva automáticamente módulos cuya licencia ha vencido';


--
-- Name: empresa_tiene_modulo(integer, character varying); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.empresa_tiene_modulo(p_empresa_id integer, p_codigo_modulo character varying) RETURNS boolean
    LANGUAGE plpgsql
    AS $$
DECLARE
    v_tiene_modulo BOOLEAN;
BEGIN
    SELECT EXISTS(
        SELECT 1
        FROM empresa_modulos em
        INNER JOIN modulos m ON m.id = em.modulo_id
        WHERE em.empresa_id = p_empresa_id
        AND m.codigo = p_codigo_modulo
        AND em.activo = true
        AND (em.fecha_vencimiento IS NULL OR em.fecha_vencimiento > CURRENT_TIMESTAMP)
    ) INTO v_tiene_modulo;

    RETURN v_tiene_modulo;
END;
$$;


--
-- Name: FUNCTION empresa_tiene_modulo(p_empresa_id integer, p_codigo_modulo character varying); Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON FUNCTION public.empresa_tiene_modulo(p_empresa_id integer, p_codigo_modulo character varying) IS 'Verifica si una empresa tiene un módulo activo y vigente';


--
-- Name: formatear_telefono_paraguayo(character varying); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.formatear_telefono_paraguayo(telefono character varying) RETURNS character varying
    LANGUAGE plpgsql
    AS $$
BEGIN
    -- Limpiar el número
    telefono := REGEXP_REPLACE(telefono, '[^0-9]', '', 'g');
    
    -- Formatear según la longitud
    IF LENGTH(telefono) = 9 THEN
        RETURN '(' || SUBSTRING(telefono, 1, 2) || ') ' || 
               SUBSTRING(telefono, 3, 3) || ' ' || 
               SUBSTRING(telefono, 6, 3) || '-' || 
               SUBSTRING(telefono, 9, 1);
    ELSIF LENGTH(telefono) = 10 THEN
        RETURN '(' || SUBSTRING(telefono, 1, 3) || ') ' || 
               SUBSTRING(telefono, 4, 3) || ' ' || 
               SUBSTRING(telefono, 7, 3) || '-' || 
               SUBSTRING(telefono, 10, 1);
    ELSE
        RETURN telefono;
    END IF;
END;
$$;


--
-- Name: generar_nombre_empresa(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.generar_nombre_empresa() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    IF NEW.tipo_cliente = 'EMPRESA' AND NEW.nombre IS NULL AND NEW.razon_social IS NOT NULL THEN
        NEW.nombre = NEW.razon_social;
    END IF;
    RETURN NEW;
END;
$$;


--
-- Name: notificar_vencimiento_modulo(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.notificar_vencimiento_modulo() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE
    v_dias_restantes INTEGER;
    v_modulo_nombre VARCHAR;
BEGIN
    IF NEW.fecha_vencimiento IS NOT NULL THEN
        v_dias_restantes := EXTRACT(DAY FROM NEW.fecha_vencimiento - CURRENT_TIMESTAMP)::INTEGER;

        -- Notificar si quedan 7 días o menos
        IF v_dias_restantes <= 7 AND v_dias_restantes > 0 THEN
            SELECT nombre INTO v_modulo_nombre FROM modulos WHERE id = NEW.modulo_id;

            INSERT INTO notificaciones (
                empresa_id,
                tipo,
                titulo,
                mensaje,
                fecha_creacion
            ) VALUES (
                NEW.empresa_id,
                'WARNING',
                'Módulo próximo a vencer',
                'El módulo "' || v_modulo_nombre || '" vencerá en ' || v_dias_restantes || ' días',
                CURRENT_TIMESTAMP
            );
        END IF;
    END IF;

    RETURN NEW;
END;
$$;


--
-- Name: obtener_estadisticas_modulos(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.obtener_estadisticas_modulos() RETURNS TABLE(modulo_id integer, modulo_codigo character varying, modulo_nombre character varying, categoria character varying, total_empresas_activas integer, total_empresas_inactivas integer, total_sucursales_activas integer, porcentaje_uso numeric)
    LANGUAGE plpgsql
    AS $$
DECLARE
    v_total_empresas INTEGER;
BEGIN
    -- Obtener total de empresas activas
    SELECT COUNT(*) INTO v_total_empresas FROM empresas WHERE estado = true;

    RETURN QUERY
    SELECT
        m.id,
        m.codigo,
        m.nombre,
        m.categoria,
        COUNT(DISTINCT CASE WHEN em.activo = true THEN em.empresa_id END)::INTEGER,
        COUNT(DISTINCT CASE WHEN em.activo = false THEN em.empresa_id END)::INTEGER,
        COUNT(DISTINCT CASE WHEN sm.activo = true THEN sm.sucursal_id END)::INTEGER,
        CASE
            WHEN v_total_empresas > 0 THEN
                (COUNT(DISTINCT CASE WHEN em.activo = true THEN em.empresa_id END)::DECIMAL / v_total_empresas * 100)
            ELSE 0
        END
    FROM modulos m
    LEFT JOIN empresa_modulos em ON em.modulo_id = m.id
    LEFT JOIN sucursal_modulos sm ON sm.modulo_id = m.id
    WHERE m.estado = true
    GROUP BY m.id, m.codigo, m.nombre, m.categoria
    ORDER BY COUNT(DISTINCT CASE WHEN em.activo = true THEN em.empresa_id END) DESC;
END;
$$;


--
-- Name: FUNCTION obtener_estadisticas_modulos(); Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON FUNCTION public.obtener_estadisticas_modulos() IS 'Obtiene estadísticas de uso de cada módulo';


--
-- Name: obtener_modulos_empresa(integer); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.obtener_modulos_empresa(p_empresa_id integer) RETURNS TABLE(modulo_id integer, codigo character varying, nombre character varying, categoria character varying, activo boolean, fecha_vencimiento timestamp without time zone, dias_restantes integer)
    LANGUAGE plpgsql
    AS $$
BEGIN
    RETURN QUERY
    SELECT
        m.id,
        m.codigo,
        m.nombre,
        m.categoria,
        em.activo,
        em.fecha_vencimiento,
        CASE
            WHEN em.fecha_vencimiento IS NULL THEN NULL
            ELSE EXTRACT(DAY FROM em.fecha_vencimiento - CURRENT_TIMESTAMP)::INTEGER
        END AS dias_restantes
    FROM empresa_modulos em
    INNER JOIN modulos m ON m.id = em.modulo_id
    WHERE em.empresa_id = p_empresa_id
    ORDER BY m.categoria, m.orden, m.nombre;
END;
$$;


--
-- Name: FUNCTION obtener_modulos_empresa(p_empresa_id integer); Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON FUNCTION public.obtener_modulos_empresa(p_empresa_id integer) IS 'Obtiene todos los módulos asignados a una empresa';


--
-- Name: obtener_modulos_sucursal(integer); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.obtener_modulos_sucursal(p_sucursal_id integer) RETURNS TABLE(modulo_id integer, codigo character varying, nombre character varying, categoria character varying, activo boolean, permite_crear boolean, permite_editar boolean, permite_eliminar boolean)
    LANGUAGE plpgsql
    AS $$
BEGIN
    RETURN QUERY
    SELECT
        m.id,
        m.codigo,
        m.nombre,
        m.categoria,
        sm.activo,
        sm.permite_crear,
        sm.permite_editar,
        sm.permite_eliminar
    FROM sucursal_modulos sm
    INNER JOIN modulos m ON m.id = sm.modulo_id
    INNER JOIN empresa_modulos em ON em.id = sm.empresa_modulo_id
    WHERE sm.sucursal_id = p_sucursal_id
    AND em.activo = true
    ORDER BY m.categoria, m.orden, m.nombre;
END;
$$;


--
-- Name: FUNCTION obtener_modulos_sucursal(p_sucursal_id integer); Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON FUNCTION public.obtener_modulos_sucursal(p_sucursal_id integer) IS 'Obtiene todos los módulos habilitados para una sucursal';


--
-- Name: obtener_nombre_cliente(integer); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.obtener_nombre_cliente(cliente_id integer) RETURNS character varying
    LANGUAGE plpgsql
    AS $$
DECLARE
    v_nombre VARCHAR;
BEGIN
    SELECT 
        CASE 
            WHEN tipo_cliente = 'PERSONA' THEN nombre || ' ' || COALESCE(apellido, '')
            WHEN tipo_cliente = 'EMPRESA' THEN COALESCE(nombre_fantasia, razon_social)
            ELSE nombre
        END
    INTO v_nombre
    FROM clientes
    WHERE id = cliente_id;
    
    RETURN v_nombre;
END;
$$;


--
-- Name: obtener_siguiente_numero(integer, character varying); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.obtener_siguiente_numero(p_empresa_id integer, p_tipo_documento character varying) RETURNS character varying
    LANGUAGE plpgsql
    AS $$
DECLARE
    v_prefijo VARCHAR(10);
    v_numero_actual INTEGER;
    v_numero_siguiente VARCHAR(20);
BEGIN
    -- Asignar prefijo según tipo de documento
    CASE p_tipo_documento
        WHEN 'FACTURA' THEN v_prefijo := 'FAC-';
        WHEN 'TICKET' THEN v_prefijo := 'TICK-';
        WHEN 'COMPRA' THEN v_prefijo := 'COMP-';
        WHEN 'COTIZACION' THEN v_prefijo := 'COT-';
        WHEN 'ORDEN_SERVICIO' THEN v_prefijo := 'OS-';
        WHEN 'TRANSFERENCIA' THEN v_prefijo := 'TRANSF-';
        WHEN 'AJUSTE' THEN v_prefijo := 'AJUS-';
        ELSE v_prefijo := 'DOC-';
    END CASE;
    
    -- Obtener el último número
    IF p_tipo_documento = 'FACTURA' THEN
        SELECT COALESCE(MAX(CAST(REPLACE(numero_venta, v_prefijo, '') AS INTEGER)), 0) + 1
        INTO v_numero_actual
        FROM ventas
        WHERE empresa_id = p_empresa_id
          AND tipo_comprobante = 'FACTURA'
          AND numero_venta LIKE v_prefijo || '%';
    ELSIF p_tipo_documento = 'ORDEN_SERVICIO' THEN
        SELECT COALESCE(MAX(CAST(REPLACE(numero_orden, v_prefijo, '') AS INTEGER)), 0) + 1
        INTO v_numero_actual
        FROM ordenes_servicio
        WHERE empresa_id = p_empresa_id
          AND numero_orden LIKE v_prefijo || '%';
    ELSE
        v_numero_actual := 1;
    END IF;
    
    -- Formatear número con ceros a la izquierda
    v_numero_siguiente := v_prefijo || LPAD(v_numero_actual::TEXT, 6, '0');
    
    RETURN v_numero_siguiente;
END;
$$;


--
-- Name: registrar_auditoria(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.registrar_auditoria() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    IF TG_OP = 'INSERT' THEN
        INSERT INTO auditoria (
            tabla_afectada,
            accion,
            registro_id,
            datos_nuevos,
            fecha_evento
        ) VALUES (
            TG_TABLE_NAME,
            'INSERT',
            NEW.id,
            row_to_json(NEW),
            CURRENT_TIMESTAMP
        );
        RETURN NEW;
    ELSIF TG_OP = 'UPDATE' THEN
        INSERT INTO auditoria (
            tabla_afectada,
            accion,
            registro_id,
            datos_anteriores,
            datos_nuevos,
            fecha_evento
        ) VALUES (
            TG_TABLE_NAME,
            'UPDATE',
            NEW.id,
            row_to_json(OLD),
            row_to_json(NEW),
            CURRENT_TIMESTAMP
        );
        RETURN NEW;
    ELSIF TG_OP = 'DELETE' THEN
        INSERT INTO auditoria (
            tabla_afectada,
            accion,
            registro_id,
            datos_anteriores,
            fecha_evento
        ) VALUES (
            TG_TABLE_NAME,
            'DELETE',
            OLD.id,
            row_to_json(OLD),
            CURRENT_TIMESTAMP
        );
        RETURN OLD;
    END IF;
    
    RETURN NULL;
END;
$$;


--
-- Name: renovar_licencia_modulo(integer, character varying, integer); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.renovar_licencia_modulo(p_empresa_modulo_id integer, p_tipo_licencia character varying DEFAULT 'MENSUAL'::character varying, p_dias_vigencia integer DEFAULT 30) RETURNS boolean
    LANGUAGE plpgsql
    AS $$
DECLARE
    v_fecha_vencimiento TIMESTAMP;
    v_fecha_actual TIMESTAMP;
BEGIN
    v_fecha_actual := CURRENT_TIMESTAMP;

    -- Calcular nueva fecha de vencimiento
    IF p_tipo_licencia = 'PERPETUA' THEN
        v_fecha_vencimiento := NULL;
    ELSE
        -- Obtener la fecha de vencimiento actual
        SELECT fecha_vencimiento INTO v_fecha_actual
        FROM empresa_modulos
        WHERE id = p_empresa_modulo_id;

        -- Si ya venció, contar desde hoy, sino desde la fecha de vencimiento
        IF v_fecha_actual IS NULL OR v_fecha_actual < CURRENT_TIMESTAMP THEN
            v_fecha_vencimiento := CURRENT_TIMESTAMP + (p_dias_vigencia || ' days')::INTERVAL;
        ELSE
            v_fecha_vencimiento := v_fecha_actual + (p_dias_vigencia || ' days')::INTERVAL;
        END IF;
    END IF;

    -- Actualizar la licencia
    UPDATE empresa_modulos
    SET tipo_licencia = p_tipo_licencia,
        fecha_vencimiento = v_fecha_vencimiento,
        fecha_fin_licencia = CASE WHEN v_fecha_vencimiento IS NOT NULL THEN v_fecha_vencimiento::DATE ELSE NULL END,
        activo = true,
        fecha_modificacion = CURRENT_TIMESTAMP
    WHERE id = p_empresa_modulo_id;

    RETURN FOUND;
END;
$$;


--
-- Name: FUNCTION renovar_licencia_modulo(p_empresa_modulo_id integer, p_tipo_licencia character varying, p_dias_vigencia integer); Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON FUNCTION public.renovar_licencia_modulo(p_empresa_modulo_id integer, p_tipo_licencia character varying, p_dias_vigencia integer) IS 'Renueva la licencia de un módulo extendiendo su vigencia';


--
-- Name: sucursal_tiene_modulo(integer, character varying); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.sucursal_tiene_modulo(p_sucursal_id integer, p_codigo_modulo character varying) RETURNS boolean
    LANGUAGE plpgsql
    AS $$
DECLARE
    v_tiene_modulo BOOLEAN;
BEGIN
    SELECT EXISTS(
        SELECT 1
        FROM sucursal_modulos sm
        INNER JOIN modulos m ON m.id = sm.modulo_id
        INNER JOIN empresa_modulos em ON em.id = sm.empresa_modulo_id
        WHERE sm.sucursal_id = p_sucursal_id
        AND m.codigo = p_codigo_modulo
        AND sm.activo = true
        AND em.activo = true
        AND (em.fecha_vencimiento IS NULL OR em.fecha_vencimiento > CURRENT_TIMESTAMP)
    ) INTO v_tiene_modulo;

    RETURN v_tiene_modulo;
END;
$$;


--
-- Name: FUNCTION sucursal_tiene_modulo(p_sucursal_id integer, p_codigo_modulo character varying); Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON FUNCTION public.sucursal_tiene_modulo(p_sucursal_id integer, p_codigo_modulo character varying) IS 'Verifica si una sucursal tiene un módulo activo';


--
-- Name: validar_empresa_modulo_valido(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.validar_empresa_modulo_valido() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE
    v_empresa_id INTEGER;
BEGIN
    -- Obtener el empresa_id de la sucursal
    SELECT empresa_id INTO v_empresa_id FROM sucursales WHERE id = NEW.sucursal_id;

    -- Verificar que el empresa_modulo_id corresponda a un módulo de la misma empresa
    IF NOT EXISTS (
        SELECT 1 FROM empresa_modulos em
        INNER JOIN sucursales s ON s.empresa_id = em.empresa_id
        WHERE em.id = NEW.empresa_modulo_id
        AND s.id = NEW.sucursal_id
    ) THEN
        RAISE EXCEPTION 'El módulo de empresa no es válido para esta sucursal';
    END IF;

    RETURN NEW;
END;
$$;


--
-- Name: validar_ruc_paraguayo(character varying); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.validar_ruc_paraguayo(ruc character varying) RETURNS boolean
    LANGUAGE plpgsql
    AS $_$
DECLARE
    ruc_limpio VARCHAR;
    digito_verificador INTEGER;
    suma INTEGER := 0;
    factores INTEGER[] := ARRAY[9,8,7,6,5,4,3,2];
BEGIN
    -- Limpiar y verificar formato
    ruc_limpio := REGEXP_REPLACE(ruc, '[^0-9-]', '', 'g');
    
    IF ruc_limpio !~ '^[0-9]{8}-[0-9]{1}$' THEN
        RETURN false;
    END IF;
    
    -- Extraer dígitos
    ruc_limpio := REPLACE(ruc_limpio, '-', '');
    digito_verificador := CAST(SUBSTRING(ruc_limpio, 9, 1) AS INTEGER);
    
    -- Calcular dígito verificador
    FOR i IN 1..8 LOOP
        suma := suma + CAST(SUBSTRING(ruc_limpio, i, 1) AS INTEGER) * factores[i];
    END LOOP;
    
    -- Cálculo del dígito verificador
    suma := 11 - (suma % 11);
    IF suma = 10 THEN
        suma := 0;
    ELSIF suma = 11 THEN
        suma := 1;
    END IF;
    
    RETURN suma = digito_verificador;
END;
$_$;


--
-- Name: validar_stock_minimo(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.validar_stock_minimo() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE
    v_stock_minimo INTEGER;
    v_producto_nombre VARCHAR;
BEGIN
    IF NEW.cantidad_disponible <= 0 THEN
        SELECT p.stock_minimo, p.nombre
        INTO v_stock_minimo, v_producto_nombre
        FROM productos p
        WHERE p.id = NEW.producto_id;
        
        IF NEW.cantidad_disponible <= v_stock_minimo THEN
            INSERT INTO notificaciones (
                empresa_id,
                tipo,
                titulo,
                mensaje,
                fecha_creacion
            ) VALUES (
                (SELECT empresa_id FROM sucursales WHERE id = NEW.sucursal_id),
                'WARNING',
                'Stock Mínimo Alcanzado',
                'El producto ' || v_producto_nombre || ' ha alcanzado el stock mínimo. Stock actual: ' || NEW.cantidad_disponible,
                CURRENT_TIMESTAMP
            );
        END IF;
    END IF;
    
    RETURN NEW;
END;
$$;


--
-- Name: verificar_dependencias_modulo(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.verificar_dependencias_modulo() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE
    v_dependencia RECORD;
    v_modulo_nombre VARCHAR;
    v_dependencia_nombre VARCHAR;
BEGIN
    IF NEW.activo = true THEN
        -- Verificar dependencias obligatorias
        FOR v_dependencia IN
            SELECT md.modulo_requerido_id, m.nombre as modulo_nombre
            FROM modulos_dependencias md
            INNER JOIN modulos m ON m.id = md.modulo_requerido_id
            WHERE md.modulo_id = NEW.modulo_id
            AND md.es_obligatorio = true
        LOOP
            -- Verificar si la dependencia está activa
            IF NOT EXISTS(
                SELECT 1
                FROM empresa_modulos
                WHERE empresa_id = NEW.empresa_id
                AND modulo_id = v_dependencia.modulo_requerido_id
                AND activo = true
            ) THEN
                SELECT nombre INTO v_modulo_nombre FROM modulos WHERE id = NEW.modulo_id;
                RAISE EXCEPTION 'El módulo "%" requiere que el módulo "%" esté activo',
                    v_modulo_nombre, v_dependencia.modulo_nombre;
            END IF;
        END LOOP;
    END IF;

    RETURN NEW;
END;
$$;


--
-- Name: verificar_modulos_vencidos(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.verificar_modulos_vencidos() RETURNS TABLE(empresa_id integer, empresa_nombre character varying, modulo_id integer, modulo_nombre character varying, fecha_vencimiento timestamp without time zone, dias_vencido integer)
    LANGUAGE plpgsql
    AS $$
BEGIN
    RETURN QUERY
    SELECT
        e.id,
        e.nombre,
        m.id,
        m.nombre,
        em.fecha_vencimiento,
        EXTRACT(DAY FROM CURRENT_TIMESTAMP - em.fecha_vencimiento)::INTEGER
    FROM empresa_modulos em
    INNER JOIN empresas e ON e.id = em.empresa_id
    INNER JOIN modulos m ON m.id = em.modulo_id
    WHERE em.activo = true
    AND em.fecha_vencimiento IS NOT NULL
    AND em.fecha_vencimiento < CURRENT_TIMESTAMP
    ORDER BY em.fecha_vencimiento;
END;
$$;


--
-- Name: FUNCTION verificar_modulos_vencidos(); Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON FUNCTION public.verificar_modulos_vencidos() IS 'Retorna todos los módulos que han vencido';


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: ajustes_inventario; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ajustes_inventario (
    id integer NOT NULL,
    empresa_id integer NOT NULL,
    sucursal_id integer NOT NULL,
    numero_ajuste character varying(20) NOT NULL,
    tipo_ajuste character varying(30) NOT NULL,
    motivo character varying(200) NOT NULL,
    observaciones text,
    estado character varying(20) DEFAULT 'PENDIENTE'::character varying,
    usuario_solicita integer,
    usuario_aprueba integer,
    fecha_solicitud timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    fecha_aprobacion timestamp without time zone,
    fecha_procesamiento timestamp without time zone,
    CONSTRAINT ajustes_inventario_estado_check CHECK (((estado)::text = ANY (ARRAY[('PENDIENTE'::character varying)::text, ('APROBADO'::character varying)::text, ('PROCESADO'::character varying)::text, ('CANCELADO'::character varying)::text]))),
    CONSTRAINT ajustes_inventario_tipo_ajuste_check CHECK (((tipo_ajuste)::text = ANY (ARRAY[('ENTRADA'::character varying)::text, ('SALIDA'::character varying)::text, ('FISICO'::character varying)::text, ('MERMA'::character varying)::text])))
);


--
-- Name: ajustes_inventario_detalle; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ajustes_inventario_detalle (
    id integer NOT NULL,
    ajuste_id integer NOT NULL,
    producto_id integer NOT NULL,
    cantidad_existente numeric(15,3) NOT NULL,
    cantidad_nueva numeric(15,3) NOT NULL,
    diferencia numeric(15,3) DEFAULT 0,
    costo_unitario numeric(15,2),
    observaciones text,
    CONSTRAINT chk_cantidad_nueva_positiva CHECK ((cantidad_nueva >= (0)::numeric))
);


--
-- Name: ajustes_inventario_detalle_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ajustes_inventario_detalle_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ajustes_inventario_detalle_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ajustes_inventario_detalle_id_seq OWNED BY public.ajustes_inventario_detalle.id;


--
-- Name: ajustes_inventario_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ajustes_inventario_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ajustes_inventario_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ajustes_inventario_id_seq OWNED BY public.ajustes_inventario.id;


--
-- Name: arqueos_caja; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.arqueos_caja (
    id integer NOT NULL,
    caja_id integer NOT NULL,
    fecha_arqueo date NOT NULL,
    hora_arqueo time without time zone NOT NULL,
    saldo_sistema numeric(15,2) NOT NULL,
    saldo_fisico numeric(15,2) NOT NULL,
    diferencia numeric(15,2) DEFAULT 0,
    observaciones text,
    usuario_id integer,
    fecha_creacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: TABLE arqueos_caja; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.arqueos_caja IS 'Arqueos de caja realizados';


--
-- Name: arqueos_caja_detalle; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.arqueos_caja_detalle (
    id integer NOT NULL,
    arqueo_id integer NOT NULL,
    denominacion character varying(100) NOT NULL,
    cantidad integer DEFAULT 0 NOT NULL,
    subtotal numeric(15,2) NOT NULL,
    orden integer DEFAULT 0,
    tipo_medio_pago character varying(50) DEFAULT 'EFECTIVO'::character varying,
    descripcion text
);


--
-- Name: TABLE arqueos_caja_detalle; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.arqueos_caja_detalle IS 'Detalle de denominaciones en arqueos';


--
-- Name: COLUMN arqueos_caja_detalle.tipo_medio_pago; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.arqueos_caja_detalle.tipo_medio_pago IS 'Tipo de medio de pago: EFECTIVO, TARJETA_DEBITO, TARJETA_CREDITO, TRANSFERENCIA, CHEQUE, OTRO';


--
-- Name: COLUMN arqueos_caja_detalle.descripcion; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.arqueos_caja_detalle.descripcion IS 'Descripción adicional del medio de pago (ej: número de cheque, últimos 4 dígitos de tarjeta)';


--
-- Name: arqueos_caja_detalle_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.arqueos_caja_detalle_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: arqueos_caja_detalle_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.arqueos_caja_detalle_id_seq OWNED BY public.arqueos_caja_detalle.id;


--
-- Name: arqueos_caja_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.arqueos_caja_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: arqueos_caja_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.arqueos_caja_id_seq OWNED BY public.arqueos_caja.id;


--
-- Name: auditoria; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.auditoria (
    id integer NOT NULL,
    empresa_id integer,
    usuario_id integer,
    tabla_afectada character varying(100) NOT NULL,
    accion character varying(20) NOT NULL,
    registro_id integer,
    datos_anteriores jsonb,
    datos_nuevos jsonb,
    ip_address character varying(50),
    user_agent text,
    observaciones text,
    fecha_evento timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT auditoria_accion_check CHECK (((accion)::text = ANY (ARRAY[('INSERT'::character varying)::text, ('UPDATE'::character varying)::text, ('DELETE'::character varying)::text, ('LOGIN'::character varying)::text, ('LOGOUT'::character varying)::text, ('ANULACION'::character varying)::text, ('FACTURACION'::character varying)::text])))
);


--
-- Name: TABLE auditoria; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.auditoria IS 'Registro de auditoría de todas las operaciones';


--
-- Name: auditoria_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.auditoria_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: auditoria_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.auditoria_id_seq OWNED BY public.auditoria.id;


--
-- Name: cajas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cajas (
    id integer NOT NULL,
    empresa_id integer NOT NULL,
    sucursal_id integer NOT NULL,
    usuario_id integer NOT NULL,
    nombre character varying(100) NOT NULL,
    descripcion text,
    estado character varying(20) DEFAULT 'CERRADA'::character varying,
    saldo_inicial numeric(15,2) DEFAULT 0,
    saldo_actual numeric(15,2) DEFAULT 0,
    saldo_esperado numeric(15,2) DEFAULT 0,
    diferencia numeric(15,2) DEFAULT 0,
    fecha_apertura timestamp without time zone,
    fecha_cierre timestamp without time zone,
    usuario_apertura integer,
    usuario_cierre integer,
    observaciones_apertura text,
    observaciones_cierre text,
    fecha_creacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT cajas_estado_check CHECK (((estado)::text = ANY (ARRAY[('ABIERTA'::character varying)::text, ('CERRADA'::character varying)::text, ('BLOQUEADA'::character varying)::text])))
);


--
-- Name: TABLE cajas; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.cajas IS 'Cajas registradoras por sucursal';


--
-- Name: cajas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cajas_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cajas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cajas_id_seq OWNED BY public.cajas.id;


--
-- Name: categorias; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.categorias (
    id integer NOT NULL,
    empresa_id integer NOT NULL,
    nombre character varying(100) NOT NULL,
    descripcion text,
    categoria_padre_id integer,
    orden integer DEFAULT 0,
    imagen character varying(255),
    icono character varying(100),
    color character varying(20),
    estado boolean DEFAULT true,
    fecha_creacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    usuario_creacion integer
);


--
-- Name: categorias_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.categorias_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: categorias_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.categorias_id_seq OWNED BY public.categorias.id;


--
-- Name: chat_participantes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.chat_participantes (
    id integer NOT NULL,
    chat_id integer NOT NULL,
    usuario_id integer NOT NULL,
    es_admin boolean DEFAULT false NOT NULL,
    unread_count integer DEFAULT 0 NOT NULL,
    fecha_unido timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    activo boolean DEFAULT true NOT NULL
);


--
-- Name: chat_participantes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.chat_participantes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: chat_participantes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.chat_participantes_id_seq OWNED BY public.chat_participantes.id;


--
-- Name: chats; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.chats (
    id integer NOT NULL,
    empresa_id integer NOT NULL,
    tipo character varying(20) DEFAULT 'PRIVATE'::character varying NOT NULL,
    nombre character varying(100),
    foto character varying(255),
    creador_id integer,
    ultimo_mensaje_at timestamp without time zone,
    estado boolean DEFAULT true NOT NULL,
    fecha_creacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    fecha_modificacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: chats_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.chats_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: chats_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.chats_id_seq OWNED BY public.chats.id;


--
-- Name: clientes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.clientes (
    id integer NOT NULL,
    empresa_id integer NOT NULL,
    codigo character varying(20),
    tipo_cliente character varying(20) DEFAULT 'PERSONA'::character varying,
    ruc character varying(20),
    ci character varying(20),
    nombre character varying(200),
    apellido character varying(100),
    razon_social character varying(200),
    nombre_fantasia character varying(200),
    direccion text,
    ciudad character varying(100),
    departamento character varying(100),
    barrio character varying(100),
    referencia_direccion text,
    telefono character varying(50),
    celular character varying(50),
    email character varying(150),
    fecha_nacimiento date,
    sexo character varying(10),
    tipo_contribuyente character varying(30),
    categoria_cliente character varying(30) DEFAULT 'NORMAL'::character varying,
    lista_precio_id integer,
    limite_credito numeric(15,2) DEFAULT 0,
    credito_utilizado numeric(15,2) DEFAULT 0,
    credito_disponible numeric(15,2) DEFAULT 0,
    dias_credito integer DEFAULT 0,
    descuento_porcentaje numeric(5,2) DEFAULT 0,
    vendedor_asignado_id integer,
    foto character varying(255),
    notas_internas text,
    observaciones text,
    etiquetas text[],
    estado boolean DEFAULT true,
    fecha_creacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    fecha_ultima_compra timestamp without time zone,
    total_compras numeric(15,2) DEFAULT 0,
    cantidad_compras integer DEFAULT 0,
    usuario_creacion integer,
    CONSTRAINT chk_nombre_razon_social CHECK (((((tipo_cliente)::text = 'PERSONA'::text) AND (nombre IS NOT NULL)) OR (((tipo_cliente)::text = 'EMPRESA'::text) AND (razon_social IS NOT NULL)) OR (nombre IS NOT NULL) OR (razon_social IS NOT NULL))),
    CONSTRAINT clientes_categoria_cliente_check CHECK (((categoria_cliente)::text = ANY (ARRAY[('VIP'::character varying)::text, ('NORMAL'::character varying)::text, ('MAYORISTA'::character varying)::text, ('DISTRIBUIDOR'::character varying)::text]))),
    CONSTRAINT clientes_sexo_check CHECK (((sexo)::text = ANY (ARRAY[('M'::character varying)::text, ('F'::character varying)::text, ('OTRO'::character varying)::text]))),
    CONSTRAINT clientes_tipo_cliente_check CHECK (((tipo_cliente)::text = ANY (ARRAY[('PERSONA'::character varying)::text, ('EMPRESA'::character varying)::text]))),
    CONSTRAINT clientes_tipo_contribuyente_check CHECK (((tipo_contribuyente)::text = ANY (ARRAY[('CONTRIBUYENTE'::character varying)::text, ('NO_CONTRIBUYENTE'::character varying)::text, ('EXONERADO'::character varying)::text])))
);


--
-- Name: TABLE clientes; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.clientes IS 'Clientes de las empresas';


--
-- Name: clientes_contactos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.clientes_contactos (
    id integer NOT NULL,
    cliente_id integer NOT NULL,
    nombre character varying(150) NOT NULL,
    cargo character varying(100),
    telefono character varying(50),
    celular character varying(50),
    email character varying(150),
    es_contacto_principal boolean DEFAULT false,
    observaciones text,
    fecha_creacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: clientes_contactos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.clientes_contactos_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: clientes_contactos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.clientes_contactos_id_seq OWNED BY public.clientes_contactos.id;


--
-- Name: clientes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.clientes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: clientes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.clientes_id_seq OWNED BY public.clientes.id;


--
-- Name: clientes_imagenes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.clientes_imagenes (
    id integer NOT NULL,
    cliente_id integer NOT NULL,
    url character varying(500) NOT NULL,
    tipo character varying(30),
    descripcion text,
    fecha_creacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT clientes_imagenes_tipo_check CHECK (((tipo)::text = ANY (ARRAY[('FOTO_PERFIL'::character varying)::text, ('CI_FRENTE'::character varying)::text, ('CI_DORSO'::character varying)::text, ('RUC'::character varying)::text, ('COMPROBANTE_DOMICILIO'::character varying)::text, ('OTRO'::character varying)::text])))
);


--
-- Name: clientes_imagenes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.clientes_imagenes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: clientes_imagenes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.clientes_imagenes_id_seq OWNED BY public.clientes_imagenes.id;


--
-- Name: compras; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.compras (
    id integer NOT NULL,
    empresa_id integer NOT NULL,
    sucursal_id integer NOT NULL,
    proveedor_id integer NOT NULL,
    usuario_id integer NOT NULL,
    numero_compra character varying(20) NOT NULL,
    numero_factura_proveedor character varying(50),
    timbrado_proveedor character varying(20),
    tipo_compra character varying(20) DEFAULT 'LOCAL'::character varying,
    condicion_pago character varying(20) DEFAULT 'CONTADO'::character varying,
    estado character varying(20) DEFAULT 'PENDIENTE'::character varying,
    moneda character varying(3) DEFAULT 'PYG'::character varying,
    tipo_cambio numeric(15,6) DEFAULT 1,
    subtotal numeric(15,2) DEFAULT 0 NOT NULL,
    descuento_porcentaje numeric(5,2) DEFAULT 0,
    descuento_monto numeric(15,2) DEFAULT 0,
    total_iva numeric(15,2) DEFAULT 0,
    otros_gastos numeric(15,2) DEFAULT 0,
    total numeric(15,2) NOT NULL,
    total_moneda_principal numeric(15,2),
    monto_pagado numeric(15,2) DEFAULT 0,
    monto_pendiente numeric(15,2) DEFAULT 0,
    fecha_compra timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    fecha_vencimiento date,
    fecha_recepcion timestamp without time zone,
    fecha_factura_proveedor date,
    forma_pago character varying(30),
    dias_credito integer DEFAULT 0,
    observaciones text,
    notas_internas text,
    numero_despacho character varying(50),
    gastos_importacion numeric(15,2) DEFAULT 0,
    fecha_creacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT compras_condicion_pago_check CHECK (((condicion_pago)::text = ANY (ARRAY[('CONTADO'::character varying)::text, ('CREDITO'::character varying)::text, ('MIXTO'::character varying)::text]))),
    CONSTRAINT compras_estado_check CHECK (((estado)::text = ANY (ARRAY[('PENDIENTE'::character varying)::text, ('PARCIALMENTE_RECIBIDA'::character varying)::text, ('RECIBIDA'::character varying)::text, ('FACTURADA'::character varying)::text, ('PAGADA'::character varying)::text, ('ANULADA'::character varying)::text]))),
    CONSTRAINT compras_moneda_check CHECK (((moneda)::text = ANY (ARRAY[('PYG'::character varying)::text, ('USD'::character varying)::text, ('BRL'::character varying)::text]))),
    CONSTRAINT compras_tipo_compra_check CHECK (((tipo_compra)::text = ANY (ARRAY[('LOCAL'::character varying)::text, ('IMPORTACION'::character varying)::text, ('CONSIGNACION'::character varying)::text])))
);


--
-- Name: compras_detalle; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.compras_detalle (
    id integer NOT NULL,
    compra_id integer NOT NULL,
    producto_id integer NOT NULL,
    descripcion text,
    cantidad_solicitada numeric(15,3) NOT NULL,
    cantidad_recibida numeric(15,3) DEFAULT 0,
    cantidad_pendiente numeric(15,3) DEFAULT 0,
    precio_unitario numeric(15,2) NOT NULL,
    descuento_porcentaje numeric(5,2) DEFAULT 0,
    descuento_monto numeric(15,2) DEFAULT 0,
    porcentaje_iva numeric(5,2),
    subtotal numeric(15,2) NOT NULL,
    monto_iva numeric(15,2) DEFAULT 0,
    total numeric(15,2) NOT NULL,
    lote character varying(50),
    fecha_vencimiento date,
    numero_serie character varying(100),
    observaciones text,
    linea_orden integer DEFAULT 0,
    CONSTRAINT chk_cantidad_compra_positiva CHECK ((cantidad_solicitada > (0)::numeric)),
    CONSTRAINT chk_precio_compra_positivo CHECK ((precio_unitario >= (0)::numeric)),
    CONSTRAINT chk_recibida_menor_solicitada CHECK ((cantidad_recibida <= cantidad_solicitada))
);


--
-- Name: compras_detalle_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.compras_detalle_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: compras_detalle_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.compras_detalle_id_seq OWNED BY public.compras_detalle.id;


--
-- Name: compras_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.compras_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: compras_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.compras_id_seq OWNED BY public.compras.id;


--
-- Name: configuraciones; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.configuraciones (
    id integer NOT NULL,
    empresa_id integer,
    modulo character varying(100) NOT NULL,
    clave character varying(100) NOT NULL,
    valor text,
    tipo character varying(50) DEFAULT 'STRING'::character varying,
    descripcion text,
    editable boolean DEFAULT true,
    fecha_creacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: TABLE configuraciones; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.configuraciones IS 'Configuraciones del sistema por empresa';


--
-- Name: configuraciones_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.configuraciones_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: configuraciones_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.configuraciones_id_seq OWNED BY public.configuraciones.id;


--
-- Name: cotizaciones; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cotizaciones (
    id integer NOT NULL,
    empresa_id integer NOT NULL,
    sucursal_id integer NOT NULL,
    cliente_id integer NOT NULL,
    usuario_id integer NOT NULL,
    lista_precio_id integer,
    numero_cotizacion character varying(20) NOT NULL,
    estado character varying(20) DEFAULT 'PENDIENTE'::character varying,
    subtotal numeric(15,2) DEFAULT 0 NOT NULL,
    descuento_porcentaje numeric(5,2) DEFAULT 0,
    descuento_monto numeric(15,2) DEFAULT 0,
    iva_10 numeric(15,2) DEFAULT 0,
    iva_5 numeric(15,2) DEFAULT 0,
    total numeric(15,2) NOT NULL,
    fecha_emision timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    fecha_vencimiento date NOT NULL,
    venta_id integer,
    fecha_conversion timestamp without time zone,
    observaciones text,
    notas_internas text,
    terminos_condiciones text,
    fecha_creacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT cotizaciones_estado_check CHECK (((estado)::text = ANY (ARRAY[('PENDIENTE'::character varying)::text, ('ENVIADA'::character varying)::text, ('VENCIDA'::character varying)::text, ('APROBADA'::character varying)::text, ('RECHAZADA'::character varying)::text, ('CONVERTIDA_VENTA'::character varying)::text])))
);


--
-- Name: cotizaciones_detalle; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cotizaciones_detalle (
    id integer NOT NULL,
    cotizacion_id integer NOT NULL,
    producto_id integer NOT NULL,
    descripcion text,
    cantidad numeric(15,3) NOT NULL,
    precio_unitario numeric(15,2) NOT NULL,
    descuento_porcentaje numeric(5,2) DEFAULT 0,
    descuento_monto numeric(15,2) DEFAULT 0,
    porcentaje_iva numeric(5,2),
    subtotal numeric(15,2) NOT NULL,
    monto_iva numeric(15,2) DEFAULT 0,
    total numeric(15,2) NOT NULL,
    observaciones text,
    linea_orden integer DEFAULT 0,
    CONSTRAINT chk_cantidad_cotizacion_positiva CHECK ((cantidad > (0)::numeric)),
    CONSTRAINT chk_precio_cotizacion_positivo CHECK ((precio_unitario >= (0)::numeric))
);


--
-- Name: cotizaciones_detalle_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cotizaciones_detalle_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cotizaciones_detalle_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cotizaciones_detalle_id_seq OWNED BY public.cotizaciones_detalle.id;


--
-- Name: cotizaciones_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cotizaciones_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cotizaciones_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cotizaciones_id_seq OWNED BY public.cotizaciones.id;


--
-- Name: crm_chats; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.crm_chats (
    id integer NOT NULL,
    empresa_id integer NOT NULL,
    sucursal_id integer,
    cliente_id integer,
    telefono character varying(50) NOT NULL,
    nombre_contacto character varying(150),
    foto_perfil character varying(500),
    ultimo_mensaje text,
    fecha_ultimo_mensaje timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    mensajes_no_leidos integer DEFAULT 0 NOT NULL,
    etiquetas jsonb,
    asignado_a integer,
    estado boolean DEFAULT true NOT NULL,
    fecha_creacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: crm_chats_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.crm_chats_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: crm_chats_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.crm_chats_id_seq OWNED BY public.crm_chats.id;


--
-- Name: crm_mensajes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.crm_mensajes (
    id integer NOT NULL,
    chat_id integer NOT NULL,
    empresa_id integer NOT NULL,
    usuario_id integer,
    direccion character varying(20) NOT NULL,
    tipo character varying(20) DEFAULT 'TEXTO'::character varying NOT NULL,
    contenido text,
    url_adjunto character varying(500),
    nombre_archivo character varying(255),
    estado character varying(20) DEFAULT 'ENVIADO'::character varying NOT NULL,
    id_mensaje_externo character varying(100),
    orden_servicio_id integer,
    venta_id integer,
    fecha_creacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: crm_mensajes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.crm_mensajes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: crm_mensajes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.crm_mensajes_id_seq OWNED BY public.crm_mensajes.id;


--
-- Name: crm_plantillas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.crm_plantillas (
    id integer NOT NULL,
    empresa_id integer NOT NULL,
    nombre character varying(100) NOT NULL,
    contenido text NOT NULL,
    tipo character varying(50) DEFAULT 'RESPUESTA_RAPIDA'::character varying NOT NULL,
    trigger_evento character varying(50),
    archivo_adjunto character varying(500),
    estado boolean DEFAULT true NOT NULL,
    fecha_creacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    usuario_creacion integer
);


--
-- Name: crm_plantillas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.crm_plantillas_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: crm_plantillas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.crm_plantillas_id_seq OWNED BY public.crm_plantillas.id;


--
-- Name: cuentas_cobrar; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cuentas_cobrar (
    id integer NOT NULL,
    empresa_id integer NOT NULL,
    sucursal_id integer NOT NULL,
    cliente_id integer NOT NULL,
    venta_id integer NOT NULL,
    numero_documento character varying(50) NOT NULL,
    tipo_documento character varying(30) NOT NULL,
    fecha_emision date NOT NULL,
    fecha_vencimiento date NOT NULL,
    monto_total numeric(15,2) NOT NULL,
    monto_pagado numeric(15,2) DEFAULT 0,
    monto_pendiente numeric(15,2) DEFAULT 0,
    dias_vencido integer DEFAULT 0,
    estado character varying(20) DEFAULT 'PENDIENTE'::character varying,
    observaciones text,
    fecha_creacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT cuentas_cobrar_estado_check CHECK (((estado)::text = ANY (ARRAY[('PENDIENTE'::character varying)::text, ('PAGADO'::character varying)::text, ('VENCIDO'::character varying)::text, ('CANCELADO'::character varying)::text, ('EN_MORA'::character varying)::text])))
);


--
-- Name: TABLE cuentas_cobrar; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.cuentas_cobrar IS 'Cuentas por cobrar a clientes';


--
-- Name: cuentas_cobrar_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cuentas_cobrar_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cuentas_cobrar_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cuentas_cobrar_id_seq OWNED BY public.cuentas_cobrar.id;


--
-- Name: cuentas_pagar; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cuentas_pagar (
    id integer NOT NULL,
    empresa_id integer NOT NULL,
    sucursal_id integer NOT NULL,
    proveedor_id integer NOT NULL,
    compra_id integer NOT NULL,
    numero_documento character varying(50) NOT NULL,
    tipo_documento character varying(30) NOT NULL,
    fecha_emision date NOT NULL,
    fecha_vencimiento date NOT NULL,
    monto_total numeric(15,2) NOT NULL,
    monto_pagado numeric(15,2) DEFAULT 0,
    monto_pendiente numeric(15,2) DEFAULT 0,
    dias_vencido integer DEFAULT 0,
    estado character varying(20) DEFAULT 'PENDIENTE'::character varying,
    observaciones text,
    fecha_creacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT cuentas_pagar_estado_check CHECK (((estado)::text = ANY (ARRAY[('PENDIENTE'::character varying)::text, ('PAGADO'::character varying)::text, ('VENCIDO'::character varying)::text, ('CANCELADO'::character varying)::text])))
);


--
-- Name: TABLE cuentas_pagar; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.cuentas_pagar IS 'Cuentas por pagar a proveedores';


--
-- Name: cuentas_pagar_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cuentas_pagar_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cuentas_pagar_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cuentas_pagar_id_seq OWNED BY public.cuentas_pagar.id;


--
-- Name: descuentos_especiales; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.descuentos_especiales (
    id integer NOT NULL,
    empresa_id integer NOT NULL,
    nombre character varying(100) NOT NULL,
    descripcion text,
    tipo_descuento character varying(20) DEFAULT 'PORCENTAJE'::character varying,
    valor_descuento numeric(10,2) NOT NULL,
    aplica_a character varying(30) DEFAULT 'PRODUCTO'::character varying,
    id_aplicable integer,
    fecha_inicio date NOT NULL,
    fecha_fin date NOT NULL,
    minimo_compra numeric(15,2),
    maximo_descuento numeric(15,2),
    max_usos integer,
    usos_actuales integer DEFAULT 0,
    activo boolean DEFAULT true,
    observaciones text,
    fecha_creacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    usuario_creacion integer,
    CONSTRAINT chk_fechas_descuento CHECK ((fecha_inicio <= fecha_fin)),
    CONSTRAINT chk_valor_descuento_positivo CHECK ((valor_descuento > (0)::numeric)),
    CONSTRAINT descuentos_especiales_aplica_a_check CHECK (((aplica_a)::text = ANY (ARRAY[('PRODUCTO'::character varying)::text, ('CATEGORIA'::character varying)::text, ('CLIENTE'::character varying)::text, ('VENTA_TOTAL'::character varying)::text]))),
    CONSTRAINT descuentos_especiales_tipo_descuento_check CHECK (((tipo_descuento)::text = ANY (ARRAY[('PORCENTAJE'::character varying)::text, ('MONTO_FIJO'::character varying)::text, ('PRECIO_ESPECIAL'::character varying)::text])))
);


--
-- Name: descuentos_especiales_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.descuentos_especiales_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: descuentos_especiales_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.descuentos_especiales_id_seq OWNED BY public.descuentos_especiales.id;


--
-- Name: empresa_modulos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.empresa_modulos (
    id integer NOT NULL,
    empresa_id integer NOT NULL,
    modulo_id integer NOT NULL,
    activo boolean DEFAULT true,
    fecha_activacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    fecha_vencimiento timestamp without time zone,
    configuracion jsonb,
    limite_usuarios integer,
    limite_registros integer,
    limite_sucursales integer,
    tipo_licencia character varying(30),
    clave_licencia character varying(255),
    fecha_inicio_licencia date,
    fecha_fin_licencia date,
    observaciones text,
    usuario_asigna integer,
    fecha_creacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT empresa_modulos_tipo_licencia_check CHECK (((tipo_licencia)::text = ANY (ARRAY[('PRUEBA'::character varying)::text, ('MENSUAL'::character varying)::text, ('ANUAL'::character varying)::text, ('PERPETUA'::character varying)::text])))
);


--
-- Name: TABLE empresa_modulos; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.empresa_modulos IS 'Módulos asignados a cada empresa';


--
-- Name: COLUMN empresa_modulos.fecha_vencimiento; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.empresa_modulos.fecha_vencimiento IS 'Fecha de vencimiento del módulo (si aplica)';


--
-- Name: COLUMN empresa_modulos.configuracion; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.empresa_modulos.configuracion IS 'Configuración específica del módulo en formato JSON';


--
-- Name: empresa_modulos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.empresa_modulos_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: empresa_modulos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.empresa_modulos_id_seq OWNED BY public.empresa_modulos.id;


--
-- Name: empresas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.empresas (
    id integer NOT NULL,
    nombre character varying(200) NOT NULL,
    ruc character varying(20) NOT NULL,
    direccion text,
    ciudad character varying(100),
    telefono character varying(50),
    email character varying(150),
    logo character varying(255),
    protector_pantalla character varying(255),
    aplica_iva boolean DEFAULT true,
    porcentaje_iva numeric(5,2) DEFAULT 10.00,
    usa_facturacion_electronica boolean DEFAULT false,
    timbrado character varying(20),
    establecimiento character varying(3),
    punto_expedicion character varying(3),
    dias_plazo_retiro integer DEFAULT 90,
    moneda_principal character varying(3) DEFAULT 'PYG'::character varying,
    terminos_condiciones text,
    politica_garantia text,
    estado boolean DEFAULT true,
    fecha_creacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    usuario_creacion integer,
    tema_oscuro boolean DEFAULT false,
    CONSTRAINT chk_ruc_formato CHECK (((ruc)::text ~ '^[0-9]{8}-[0-9]{1}$'::text)),
    CONSTRAINT empresas_moneda_principal_check CHECK (((moneda_principal)::text = ANY (ARRAY[('PYG'::character varying)::text, ('USD'::character varying)::text, ('BRL'::character varying)::text]))),
    CONSTRAINT empresas_porcentaje_iva_check CHECK ((porcentaje_iva = ANY (ARRAY[5.00, 10.00])))
);


--
-- Name: TABLE empresas; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.empresas IS 'Empresas registradas en el sistema multiempresa';


--
-- Name: COLUMN empresas.tema_oscuro; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.empresas.tema_oscuro IS 'Indica si la empresa usa tema oscuro por defecto (true) o claro (false)';


--
-- Name: empresas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.empresas_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: empresas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.empresas_id_seq OWNED BY public.empresas.id;


--
-- Name: estados_orden; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.estados_orden (
    id integer NOT NULL,
    nombre character varying(50) NOT NULL,
    descripcion text,
    color character varying(20),
    icono character varying(50),
    orden integer DEFAULT 0,
    es_final boolean DEFAULT false,
    permite_edicion boolean DEFAULT true,
    permite_facturacion boolean DEFAULT false,
    notifica_cliente boolean DEFAULT false,
    plantilla_email text,
    plantilla_sms text,
    estado boolean DEFAULT true
);


--
-- Name: estados_orden_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.estados_orden_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: estados_orden_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.estados_orden_id_seq OWNED BY public.estados_orden.id;


--
-- Name: facturacion_electronica; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.facturacion_electronica (
    id integer NOT NULL,
    venta_id integer NOT NULL,
    cdc character varying(50) NOT NULL,
    numero_factura character varying(20) NOT NULL,
    timbrado character varying(20) NOT NULL,
    fecha_emision timestamp without time zone NOT NULL,
    tipo_documento character varying(10),
    tipo_transaccion character varying(10),
    tipo_emision character varying(10),
    estado_sifen character varying(30),
    codigo_seguridad character varying(50),
    xml_enviado text,
    xml_respuesta text,
    xml_firmado text,
    qr_code text,
    url_consulta character varying(255),
    url_kude character varying(255),
    fecha_envio timestamp without time zone,
    fecha_aprobacion timestamp without time zone,
    fecha_rechazo timestamp without time zone,
    fecha_cancelacion timestamp without time zone,
    mensaje_aprobacion text,
    mensaje_error text,
    codigo_error character varying(20),
    intentos_envio integer DEFAULT 0,
    fecha_ultimo_intento timestamp without time zone,
    fecha_creacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: TABLE facturacion_electronica; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.facturacion_electronica IS 'Registro de facturación electrónica SIFEN';


--
-- Name: facturacion_electronica_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.facturacion_electronica_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: facturacion_electronica_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.facturacion_electronica_id_seq OWNED BY public.facturacion_electronica.id;


--
-- Name: listas_precios; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.listas_precios (
    id integer NOT NULL,
    empresa_id integer NOT NULL,
    nombre character varying(100) NOT NULL,
    descripcion text,
    tipo_lista character varying(20) DEFAULT 'GENERAL'::character varying,
    tipo_descuento character varying(20) DEFAULT 'PORCENTAJE'::character varying,
    descuento_general numeric(5,2) DEFAULT 0,
    margen_porcentaje numeric(5,2),
    es_predeterminada boolean DEFAULT false,
    moneda character varying(3) DEFAULT 'PYG'::character varying,
    fecha_vigencia_desde date,
    fecha_vigencia_hasta date,
    aplica_lunes boolean DEFAULT true,
    aplica_martes boolean DEFAULT true,
    aplica_miercoles boolean DEFAULT true,
    aplica_jueves boolean DEFAULT true,
    aplica_viernes boolean DEFAULT true,
    aplica_sabado boolean DEFAULT true,
    aplica_domingo boolean DEFAULT true,
    hora_desde time without time zone,
    hora_hasta time without time zone,
    sucursales_aplicables integer[],
    categorias_aplicables integer[],
    clientes_aplicables integer[],
    cantidad_minima integer DEFAULT 1,
    estado boolean DEFAULT true,
    fecha_creacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    usuario_creacion integer,
    CONSTRAINT listas_precios_tipo_descuento_check CHECK (((tipo_descuento)::text = ANY (ARRAY[('PORCENTAJE'::character varying)::text, ('MONTO_FIJO'::character varying)::text, ('PRECIO_ESPECIAL'::character varying)::text]))),
    CONSTRAINT listas_precios_tipo_lista_check CHECK (((tipo_lista)::text = ANY (ARRAY[('GENERAL'::character varying)::text, ('MAYORISTA'::character varying)::text, ('MINORISTA'::character varying)::text, ('ESPECIAL'::character varying)::text, ('PROMOCIONAL'::character varying)::text])))
);


--
-- Name: TABLE listas_precios; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.listas_precios IS 'Listas de precios para diferentes tipos de clientes';


--
-- Name: listas_precios_detalle; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.listas_precios_detalle (
    id integer NOT NULL,
    lista_id integer NOT NULL,
    producto_id integer NOT NULL,
    tipo_aplicacion character varying(20) DEFAULT 'PORCENTAJE'::character varying,
    descuento_porcentaje numeric(5,2),
    descuento_monto numeric(15,2),
    precio_especial numeric(15,2),
    precio_final numeric(15,2),
    precio_oferta numeric(15,2),
    es_oferta boolean DEFAULT false,
    fecha_inicio_oferta timestamp without time zone,
    fecha_fin_oferta timestamp without time zone,
    cantidad_minima integer DEFAULT 1,
    cantidad_maxima integer,
    observaciones text,
    fecha_creacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT listas_precios_detalle_tipo_aplicacion_check CHECK (((tipo_aplicacion)::text = ANY (ARRAY[('PORCENTAJE'::character varying)::text, ('MONTO_FIJO'::character varying)::text, ('PRECIO_ESPECIAL'::character varying)::text])))
);


--
-- Name: listas_precios_detalle_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.listas_precios_detalle_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: listas_precios_detalle_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.listas_precios_detalle_id_seq OWNED BY public.listas_precios_detalle.id;


--
-- Name: listas_precios_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.listas_precios_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: listas_precios_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.listas_precios_id_seq OWNED BY public.listas_precios.id;


--
-- Name: logs_sistema; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.logs_sistema (
    id integer NOT NULL,
    empresa_id integer,
    usuario_id integer,
    nivel character varying(20) NOT NULL,
    modulo character varying(100) NOT NULL,
    mensaje text NOT NULL,
    datos jsonb,
    ip_address character varying(50),
    fecha_creacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT logs_sistema_nivel_check CHECK (((nivel)::text = ANY (ARRAY[('INFO'::character varying)::text, ('WARNING'::character varying)::text, ('ERROR'::character varying)::text, ('DEBUG'::character varying)::text, ('CRITICAL'::character varying)::text])))
);


--
-- Name: logs_sistema_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.logs_sistema_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: logs_sistema_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.logs_sistema_id_seq OWNED BY public.logs_sistema.id;


--
-- Name: marcas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.marcas (
    id integer NOT NULL,
    empresa_id integer NOT NULL,
    nombre character varying(100) NOT NULL,
    descripcion text,
    logo character varying(255),
    pais_origen character varying(100),
    sitio_web character varying(200),
    estado boolean DEFAULT true,
    fecha_creacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: marcas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.marcas_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: marcas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.marcas_id_seq OWNED BY public.marcas.id;


--
-- Name: mensajes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.mensajes (
    id integer NOT NULL,
    chat_id integer NOT NULL,
    usuario_id integer NOT NULL,
    mensaje text,
    tipo character varying(20) DEFAULT 'TEXT'::character varying NOT NULL,
    adjunto_url character varying(500),
    leido_por_todos boolean DEFAULT false NOT NULL,
    fecha_envio timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: mensajes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.mensajes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: mensajes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.mensajes_id_seq OWNED BY public.mensajes.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id bigint NOT NULL,
    version character varying(255) NOT NULL,
    class character varying(255) NOT NULL,
    "group" character varying(255) NOT NULL,
    namespace character varying(255) NOT NULL,
    "time" integer NOT NULL,
    batch integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: modulos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.modulos (
    id integer NOT NULL,
    nombre character varying(100) NOT NULL,
    codigo character varying(50) NOT NULL,
    descripcion text,
    icono character varying(100),
    color character varying(20),
    categoria character varying(50),
    orden integer DEFAULT 0,
    requiere_licencia boolean DEFAULT false,
    precio_mensual numeric(15,2) DEFAULT 0,
    es_modulo_base boolean DEFAULT false,
    url_documentacion character varying(500),
    version character varying(20),
    estado boolean DEFAULT true,
    fecha_creacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT modulos_categoria_check CHECK (((categoria)::text = ANY (ARRAY[('VENTAS'::character varying)::text, ('INVENTARIO'::character varying)::text, ('SERVICIOS'::character varying)::text, ('COMPRAS'::character varying)::text, ('FINANZAS'::character varying)::text, ('REPORTES'::character varying)::text, ('CONFIGURACION'::character varying)::text, ('OTROS'::character varying)::text])))
);


--
-- Name: TABLE modulos; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.modulos IS 'Módulos disponibles en el sistema';


--
-- Name: COLUMN modulos.codigo; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.modulos.codigo IS 'Código único para identificar el módulo en el código';


--
-- Name: COLUMN modulos.requiere_licencia; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.modulos.requiere_licencia IS 'Indica si el módulo requiere licencia adicional';


--
-- Name: COLUMN modulos.es_modulo_base; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.modulos.es_modulo_base IS 'Módulos que vienen por defecto en toda instalación';


--
-- Name: modulos_dependencias; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.modulos_dependencias (
    id integer NOT NULL,
    modulo_id integer NOT NULL,
    modulo_requerido_id integer NOT NULL,
    es_obligatorio boolean DEFAULT true,
    descripcion text,
    fecha_creacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_modulo_diferente CHECK ((modulo_id <> modulo_requerido_id))
);


--
-- Name: TABLE modulos_dependencias; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.modulos_dependencias IS 'Define las dependencias entre módulos';


--
-- Name: COLUMN modulos_dependencias.es_obligatorio; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.modulos_dependencias.es_obligatorio IS 'Si es true, el módulo requerido debe estar activo';


--
-- Name: modulos_dependencias_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.modulos_dependencias_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: modulos_dependencias_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.modulos_dependencias_id_seq OWNED BY public.modulos_dependencias.id;


--
-- Name: modulos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.modulos_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: modulos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.modulos_id_seq OWNED BY public.modulos.id;


--
-- Name: movimientos_caja; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.movimientos_caja (
    id integer NOT NULL,
    caja_id integer NOT NULL,
    tipo_movimiento character varying(30) NOT NULL,
    monto numeric(15,2) NOT NULL,
    moneda character varying(3) DEFAULT 'PYG'::character varying,
    tipo_cambio numeric(15,6) DEFAULT 1,
    monto_moneda_principal numeric(15,2),
    concepto character varying(200) NOT NULL,
    referencia character varying(100),
    documento_id integer,
    tipo_documento character varying(50),
    observaciones text,
    fecha_movimiento timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    usuario_id integer,
    CONSTRAINT chk_monto_positivo CHECK ((monto > (0)::numeric)),
    CONSTRAINT movimientos_caja_moneda_check CHECK (((moneda)::text = ANY (ARRAY[('PYG'::character varying)::text, ('USD'::character varying)::text, ('BRL'::character varying)::text]))),
    CONSTRAINT movimientos_caja_tipo_movimiento_check CHECK (((tipo_movimiento)::text = ANY (ARRAY[('APERTURA'::character varying)::text, ('CIERRE'::character varying)::text, ('VENTA'::character varying)::text, ('PAGO_CUENTA_COBRAR'::character varying)::text, ('PAGO_CUENTA_PAGAR'::character varying)::text, ('EGRESO'::character varying)::text, ('INGRESO'::character varying)::text, ('TRANSFERENCIA_ENTRADA'::character varying)::text, ('TRANSFERENCIA_SALIDA'::character varying)::text, ('AJUSTE'::character varying)::text])))
);


--
-- Name: TABLE movimientos_caja; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.movimientos_caja IS 'Movimientos de efectivo en las cajas';


--
-- Name: movimientos_caja_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.movimientos_caja_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: movimientos_caja_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.movimientos_caja_id_seq OWNED BY public.movimientos_caja.id;


--
-- Name: movimientos_stock; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.movimientos_stock (
    id integer NOT NULL,
    empresa_id integer NOT NULL,
    sucursal_id integer NOT NULL,
    producto_id integer NOT NULL,
    tipo_movimiento character varying(30) NOT NULL,
    cantidad numeric(15,3) NOT NULL,
    cantidad_anterior numeric(15,3),
    cantidad_nueva numeric(15,3),
    costo_unitario numeric(15,2),
    precio_venta_unitario numeric(15,2),
    moneda character varying(3) DEFAULT 'PYG'::character varying,
    documento_referencia character varying(50),
    tipo_documento character varying(30),
    lote character varying(50),
    motivo text,
    fecha_movimiento timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    usuario_id integer,
    CONSTRAINT movimientos_stock_tipo_movimiento_check CHECK (((tipo_movimiento)::text = ANY (ARRAY[('COMPRA'::character varying)::text, ('VENTA'::character varying)::text, ('AJUSTE_ENTRADA'::character varying)::text, ('AJUSTE_SALIDA'::character varying)::text, ('TRANSFERENCIA_ENTRADA'::character varying)::text, ('TRANSFERENCIA_SALIDA'::character varying)::text, ('DEVOLUCION_COMPRA'::character varying)::text, ('DEVOLUCION_VENTA'::character varying)::text, ('MERMA'::character varying)::text, ('INVENTARIO_INICIAL'::character varying)::text, ('USO_SERVICIO'::character varying)::text, ('PRODUCCION'::character varying)::text, ('CONSUMO_INTERNO'::character varying)::text, ('REGALO'::character varying)::text, ('MUESTRA'::character varying)::text])))
);


--
-- Name: movimientos_stock_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.movimientos_stock_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: movimientos_stock_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.movimientos_stock_id_seq OWNED BY public.movimientos_stock.id;


--
-- Name: notificaciones; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.notificaciones (
    id integer NOT NULL,
    empresa_id integer NOT NULL,
    usuario_id integer,
    tipo character varying(30) NOT NULL,
    titulo character varying(200) NOT NULL,
    mensaje text NOT NULL,
    leida boolean DEFAULT false,
    fecha_lectura timestamp without time zone,
    accion_url character varying(500),
    icono character varying(100),
    estado boolean DEFAULT true,
    fecha_creacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    fecha_expiracion timestamp without time zone,
    CONSTRAINT notificaciones_tipo_check CHECK (((tipo)::text = ANY (ARRAY[('INFO'::character varying)::text, ('WARNING'::character varying)::text, ('SUCCESS'::character varying)::text, ('ERROR'::character varying)::text, ('ALERT'::character varying)::text])))
);


--
-- Name: TABLE notificaciones; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.notificaciones IS 'Notificaciones del sistema para usuarios';


--
-- Name: notificaciones_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.notificaciones_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: notificaciones_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.notificaciones_id_seq OWNED BY public.notificaciones.id;


--
-- Name: ordenes_servicio; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ordenes_servicio (
    id integer NOT NULL,
    empresa_id integer NOT NULL,
    sucursal_id integer NOT NULL,
    cliente_id integer NOT NULL,
    usuario_recepcion integer NOT NULL,
    tecnico_asignado integer,
    numero_orden character varying(20) NOT NULL,
    estado_id integer NOT NULL,
    prioridad character varying(20) DEFAULT 'NORMAL'::character varying,
    tipo_equipo character varying(100) NOT NULL,
    marca character varying(100),
    modelo character varying(100),
    numero_serie character varying(100),
    imei character varying(50),
    color character varying(50),
    estado_fisico text,
    accesorios text,
    patron_desbloqueo character varying(100),
    clave_equipo character varying(100),
    problema_reportado text NOT NULL,
    diagnostico text,
    solucion_aplicada text,
    observaciones_tecnico text,
    observaciones_internas text,
    observaciones_cliente text,
    fecha_recepcion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    fecha_diagnostico timestamp without time zone,
    fecha_aprobacion timestamp without time zone,
    fecha_inicio_reparacion timestamp without time zone,
    fecha_finalizacion timestamp without time zone,
    fecha_entrega timestamp without time zone,
    fecha_limite_retiro date,
    fecha_vencimiento_presupuesto date,
    moneda character varying(3) DEFAULT 'PYG'::character varying,
    tipo_cambio numeric(15,6) DEFAULT 1,
    presupuesto_diagnostico numeric(15,2) DEFAULT 0,
    presupuesto_repuestos numeric(15,2) DEFAULT 0,
    presupuesto_mano_obra numeric(15,2) DEFAULT 0,
    presupuesto_otros numeric(15,2) DEFAULT 0,
    presupuesto_subtotal numeric(15,2) DEFAULT 0,
    presupuesto_descuento numeric(15,2) DEFAULT 0,
    presupuesto_iva numeric(15,2) DEFAULT 0,
    presupuesto_total numeric(15,2) DEFAULT 0,
    costo_real_repuestos numeric(15,2) DEFAULT 0,
    costo_real_mano_obra numeric(15,2) DEFAULT 0,
    costo_real_otros numeric(15,2) DEFAULT 0,
    costo_real_subtotal numeric(15,2) DEFAULT 0,
    costo_real_iva numeric(15,2) DEFAULT 0,
    costo_real_total numeric(15,2) DEFAULT 0,
    anticipo_monto numeric(15,2) DEFAULT 0,
    saldo_pendiente numeric(15,2) DEFAULT 0,
    venta_id integer,
    fecha_facturacion timestamp without time zone,
    garantia_dias integer DEFAULT 0,
    fecha_vencimiento_garantia date,
    terminos_garantia text,
    presupuesto_aprobado boolean DEFAULT false,
    cliente_contactado boolean DEFAULT false,
    cliente_notificado_listo boolean DEFAULT false,
    requiere_autorizacion boolean DEFAULT false,
    autorizado_por integer,
    fecha_autorizacion timestamp without time zone,
    estado_activo boolean DEFAULT true,
    fecha_creacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    usuario_creacion integer,
    CONSTRAINT ordenes_servicio_prioridad_check CHECK (((prioridad)::text = ANY (ARRAY[('BAJA'::character varying)::text, ('NORMAL'::character varying)::text, ('ALTA'::character varying)::text, ('URGENTE'::character varying)::text])))
);


--
-- Name: TABLE ordenes_servicio; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.ordenes_servicio IS 'Órdenes de servicio para reparación de equipos';


--
-- Name: ordenes_servicio_comentarios; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ordenes_servicio_comentarios (
    id integer NOT NULL,
    orden_id integer NOT NULL,
    usuario_id integer NOT NULL,
    comentario text NOT NULL,
    es_interno boolean DEFAULT true,
    visible_cliente boolean DEFAULT false,
    fecha_creacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: ordenes_servicio_comentarios_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ordenes_servicio_comentarios_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ordenes_servicio_comentarios_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ordenes_servicio_comentarios_id_seq OWNED BY public.ordenes_servicio_comentarios.id;


--
-- Name: ordenes_servicio_documentos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ordenes_servicio_documentos (
    id integer NOT NULL,
    orden_id integer NOT NULL,
    nombre_archivo character varying(255) NOT NULL,
    url character varying(500) NOT NULL,
    tipo_documento character varying(50),
    descripcion text,
    "tamaño_bytes" bigint,
    formato character varying(10),
    fecha_creacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    usuario_creacion integer,
    CONSTRAINT ordenes_servicio_documentos_tipo_documento_check CHECK (((tipo_documento)::text = ANY (ARRAY[('PRESUPUESTO'::character varying)::text, ('FACTURA'::character varying)::text, ('GARANTIA'::character varying)::text, ('COMPROBANTE'::character varying)::text, ('MANUAL'::character varying)::text, ('OTRO'::character varying)::text])))
);


--
-- Name: ordenes_servicio_documentos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ordenes_servicio_documentos_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ordenes_servicio_documentos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ordenes_servicio_documentos_id_seq OWNED BY public.ordenes_servicio_documentos.id;


--
-- Name: ordenes_servicio_historial; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ordenes_servicio_historial (
    id integer NOT NULL,
    orden_id integer NOT NULL,
    estado_anterior_id integer,
    estado_nuevo_id integer NOT NULL,
    observaciones text,
    duracion_minutos integer,
    fecha_cambio timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    usuario_id integer,
    ip_address character varying(50)
);


--
-- Name: ordenes_servicio_historial_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ordenes_servicio_historial_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ordenes_servicio_historial_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ordenes_servicio_historial_id_seq OWNED BY public.ordenes_servicio_historial.id;


--
-- Name: ordenes_servicio_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ordenes_servicio_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ordenes_servicio_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ordenes_servicio_id_seq OWNED BY public.ordenes_servicio.id;


--
-- Name: ordenes_servicio_imagenes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ordenes_servicio_imagenes (
    id integer NOT NULL,
    orden_id integer NOT NULL,
    url character varying(500) NOT NULL,
    url_thumbnail character varying(500),
    titulo character varying(200),
    descripcion text,
    tipo character varying(20),
    categoria character varying(50),
    orden integer DEFAULT 0,
    "tamaño_bytes" bigint,
    ancho_px integer,
    alto_px integer,
    formato character varying(10),
    es_publica boolean DEFAULT false,
    visible_cliente boolean DEFAULT true,
    fecha_creacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    usuario_creacion integer,
    CONSTRAINT ordenes_servicio_imagenes_tipo_check CHECK (((tipo)::text = ANY (ARRAY[('ANTES'::character varying)::text, ('DURANTE'::character varying)::text, ('DESPUES'::character varying)::text, ('DIAGNOSTICO'::character varying)::text, ('DANO'::character varying)::text, ('COMPROBANTE'::character varying)::text, ('OTRO'::character varying)::text])))
);


--
-- Name: TABLE ordenes_servicio_imagenes; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.ordenes_servicio_imagenes IS 'Imágenes asociadas a órdenes de servicio';


--
-- Name: ordenes_servicio_imagenes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ordenes_servicio_imagenes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ordenes_servicio_imagenes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ordenes_servicio_imagenes_id_seq OWNED BY public.ordenes_servicio_imagenes.id;


--
-- Name: ordenes_servicio_mano_obra; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ordenes_servicio_mano_obra (
    id integer NOT NULL,
    orden_id integer NOT NULL,
    descripcion text NOT NULL,
    tipo_servicio character varying(50),
    horas_estimadas numeric(5,2),
    horas_reales numeric(5,2),
    precio_hora numeric(15,2),
    precio_fijo numeric(15,2),
    descuento_porcentaje numeric(5,2) DEFAULT 0,
    descuento_monto numeric(15,2) DEFAULT 0,
    tipo_iva character varying(20) DEFAULT 'GRAVADO_10'::character varying,
    porcentaje_iva numeric(5,2) DEFAULT 10,
    subtotal numeric(15,2) NOT NULL,
    monto_iva numeric(15,2) DEFAULT 0,
    total numeric(15,2) NOT NULL,
    tecnico_id integer,
    es_presupuesto boolean DEFAULT true,
    fue_realizado boolean DEFAULT false,
    observaciones text,
    fecha_agregado timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    usuario_agrega integer,
    CONSTRAINT chk_precio_mano_obra_positivo CHECK (((precio_fijo >= (0)::numeric) OR (precio_hora >= (0)::numeric)))
);


--
-- Name: ordenes_servicio_mano_obra_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ordenes_servicio_mano_obra_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ordenes_servicio_mano_obra_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ordenes_servicio_mano_obra_id_seq OWNED BY public.ordenes_servicio_mano_obra.id;


--
-- Name: ordenes_servicio_notificaciones; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ordenes_servicio_notificaciones (
    id integer NOT NULL,
    orden_id integer NOT NULL,
    tipo_notificacion character varying(50),
    destinatario character varying(200) NOT NULL,
    asunto character varying(200),
    mensaje text NOT NULL,
    estado character varying(20) DEFAULT 'PENDIENTE'::character varying,
    fecha_programada timestamp without time zone,
    fecha_envio timestamp without time zone,
    fecha_entrega timestamp without time zone,
    fecha_lectura timestamp without time zone,
    respuesta text,
    error text,
    costo numeric(10,2),
    proveedor character varying(50),
    mensaje_id character varying(100),
    fecha_creacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT ordenes_servicio_notificaciones_estado_check CHECK (((estado)::text = ANY (ARRAY[('PENDIENTE'::character varying)::text, ('ENVIADA'::character varying)::text, ('ENTREGADA'::character varying)::text, ('FALLIDA'::character varying)::text, ('LEIDA'::character varying)::text]))),
    CONSTRAINT ordenes_servicio_notificaciones_tipo_notificacion_check CHECK (((tipo_notificacion)::text = ANY (ARRAY[('SMS'::character varying)::text, ('EMAIL'::character varying)::text, ('WHATSAPP'::character varying)::text, ('LLAMADA'::character varying)::text])))
);


--
-- Name: ordenes_servicio_notificaciones_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ordenes_servicio_notificaciones_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ordenes_servicio_notificaciones_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ordenes_servicio_notificaciones_id_seq OWNED BY public.ordenes_servicio_notificaciones.id;


--
-- Name: ordenes_servicio_repuestos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ordenes_servicio_repuestos (
    id integer NOT NULL,
    orden_id integer NOT NULL,
    producto_id integer NOT NULL,
    cantidad numeric(15,3) NOT NULL,
    precio_unitario numeric(15,2) NOT NULL,
    descuento_porcentaje numeric(5,2) DEFAULT 0,
    descuento_monto numeric(15,2) DEFAULT 0,
    tipo_iva character varying(20) DEFAULT 'GRAVADO_10'::character varying,
    porcentaje_iva numeric(5,2) DEFAULT 10,
    subtotal numeric(15,2) NOT NULL,
    monto_iva numeric(15,2) DEFAULT 0,
    total numeric(15,2) NOT NULL,
    es_presupuesto boolean DEFAULT true,
    fue_utilizado boolean DEFAULT false,
    costo_unitario numeric(15,2),
    observaciones text,
    fecha_agregado timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    usuario_agrega integer,
    CONSTRAINT chk_cantidad_repuesto_positiva CHECK ((cantidad > (0)::numeric)),
    CONSTRAINT chk_precio_repuesto_positivo CHECK ((precio_unitario >= (0)::numeric))
);


--
-- Name: TABLE ordenes_servicio_repuestos; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.ordenes_servicio_repuestos IS 'Repuestos utilizados en órdenes de servicio';


--
-- Name: ordenes_servicio_repuestos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ordenes_servicio_repuestos_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ordenes_servicio_repuestos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ordenes_servicio_repuestos_id_seq OWNED BY public.ordenes_servicio_repuestos.id;


--
-- Name: pagos_cuentas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.pagos_cuentas (
    id integer NOT NULL,
    empresa_id integer NOT NULL,
    sucursal_id integer NOT NULL,
    cuenta_cobrar_id integer,
    cuenta_pagar_id integer,
    forma_pago character varying(30) NOT NULL,
    monto numeric(15,2) NOT NULL,
    moneda character varying(3) DEFAULT 'PYG'::character varying,
    tipo_cambio numeric(15,6) DEFAULT 1,
    referencia character varying(100),
    fecha_pago date NOT NULL,
    fecha_acreditacion date,
    observaciones text,
    usuario_id integer,
    caja_id integer,
    fecha_creacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_monto_cuenta_positivo CHECK ((monto > (0)::numeric)),
    CONSTRAINT chk_tipo_cuenta CHECK ((((cuenta_cobrar_id IS NOT NULL) AND (cuenta_pagar_id IS NULL)) OR ((cuenta_cobrar_id IS NULL) AND (cuenta_pagar_id IS NOT NULL))))
);


--
-- Name: pagos_cuentas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.pagos_cuentas_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: pagos_cuentas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.pagos_cuentas_id_seq OWNED BY public.pagos_cuentas.id;


--
-- Name: pagos_ventas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.pagos_ventas (
    id integer NOT NULL,
    venta_id integer NOT NULL,
    forma_pago character varying(30) NOT NULL,
    monto numeric(15,2) NOT NULL,
    moneda character varying(3) DEFAULT 'PYG'::character varying,
    tipo_cambio numeric(15,6) DEFAULT 1,
    monto_moneda_principal numeric(15,2),
    referencia character varying(100),
    numero_transaccion character varying(100),
    numero_autorizacion character varying(50),
    numero_tarjeta_ultimos character varying(4),
    tipo_tarjeta character varying(20),
    banco_emisor character varying(100),
    numero_cheque character varying(50),
    banco_cheque character varying(100),
    fecha_cheque date,
    cuenta_destino character varying(100),
    comision_porcentaje numeric(5,2) DEFAULT 0,
    comision_monto numeric(15,2) DEFAULT 0,
    monto_neto numeric(15,2),
    fecha_pago timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    fecha_acreditacion date,
    estado character varying(20) DEFAULT 'APROBADO'::character varying,
    observaciones text,
    usuario_id integer,
    caja_id integer,
    CONSTRAINT chk_monto_pago_positivo CHECK ((monto > (0)::numeric)),
    CONSTRAINT pagos_ventas_estado_check CHECK (((estado)::text = ANY (ARRAY[('PENDIENTE'::character varying)::text, ('APROBADO'::character varying)::text, ('RECHAZADO'::character varying)::text, ('ANULADO'::character varying)::text]))),
    CONSTRAINT pagos_ventas_forma_pago_check CHECK (((forma_pago)::text = ANY (ARRAY[('EFECTIVO'::character varying)::text, ('TARJETA_DEBITO'::character varying)::text, ('TARJETA_CREDITO'::character varying)::text, ('TRANSFERENCIA'::character varying)::text, ('CHEQUE'::character varying)::text, ('QR'::character varying)::text, ('BILLETERA_DIGITAL'::character varying)::text, ('CREDITO_CLIENTE'::character varying)::text, ('GIRO'::character varying)::text, ('DEPOSITO'::character varying)::text]))),
    CONSTRAINT pagos_ventas_tipo_tarjeta_check CHECK (((tipo_tarjeta)::text = ANY (ARRAY[('DEBITO'::character varying)::text, ('CREDITO'::character varying)::text])))
);


--
-- Name: pagos_ventas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.pagos_ventas_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: pagos_ventas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.pagos_ventas_id_seq OWNED BY public.pagos_ventas.id;


--
-- Name: permisos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.permisos (
    id integer NOT NULL,
    rol_id integer NOT NULL,
    modulo character varying(100) NOT NULL,
    ver boolean DEFAULT false,
    crear boolean DEFAULT false,
    editar boolean DEFAULT false,
    eliminar boolean DEFAULT false,
    anular boolean DEFAULT false,
    fecha_creacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: permisos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.permisos_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: permisos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.permisos_id_seq OWNED BY public.permisos.id;


--
-- Name: producto_imagenes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.producto_imagenes (
    id integer NOT NULL,
    producto_id integer NOT NULL,
    url character varying(500) NOT NULL,
    url_thumbnail character varying(500),
    titulo character varying(200),
    descripcion text,
    es_principal boolean DEFAULT false,
    orden integer DEFAULT 0,
    tipo character varying(20) DEFAULT 'PRODUCTO'::character varying,
    "tamaño_bytes" bigint,
    ancho_px integer,
    alto_px integer,
    formato character varying(10),
    fecha_creacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    usuario_creacion integer,
    CONSTRAINT producto_imagenes_tipo_check CHECK (((tipo)::text = ANY (ARRAY[('PRODUCTO'::character varying)::text, ('GALERIA'::character varying)::text, ('TECNICA'::character varying)::text, ('360'::character varying)::text])))
);


--
-- Name: producto_imagenes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.producto_imagenes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: producto_imagenes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.producto_imagenes_id_seq OWNED BY public.producto_imagenes.id;


--
-- Name: productos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.productos (
    id integer NOT NULL,
    empresa_id integer NOT NULL,
    categoria_id integer,
    marca_id integer,
    unidad_medida_id integer,
    codigo character varying(50) NOT NULL,
    codigo_barra character varying(100),
    codigo_fabricante character varying(100),
    nombre character varying(200) NOT NULL,
    descripcion text,
    descripcion_corta character varying(500),
    especificaciones_tecnicas text,
    tipo_producto character varying(20) DEFAULT 'PRODUCTO'::character varying,
    tipo_iva character varying(20) DEFAULT 'GRAVADO_10'::character varying NOT NULL,
    porcentaje_iva numeric(5,2),
    precio_compra numeric(15,2) DEFAULT 0,
    precio_venta numeric(15,2) NOT NULL,
    precio_mayorista numeric(15,2),
    precio_minimo numeric(15,2),
    margen_utilidad numeric(5,2),
    moneda character varying(3) DEFAULT 'PYG'::character varying,
    stock_minimo integer DEFAULT 0,
    stock_maximo integer DEFAULT 0,
    punto_reorden integer DEFAULT 0,
    controla_stock boolean DEFAULT true,
    permite_venta_sin_stock boolean DEFAULT false,
    usa_lote boolean DEFAULT false,
    usa_vencimiento boolean DEFAULT false,
    usa_serie boolean DEFAULT false,
    imagen_principal character varying(255),
    peso numeric(10,3),
    dimensiones character varying(100),
    garantia_meses integer DEFAULT 0,
    observaciones text,
    tags text[],
    es_destacado boolean DEFAULT false,
    es_novedad boolean DEFAULT false,
    es_oferta boolean DEFAULT false,
    estado boolean DEFAULT true,
    fecha_creacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    usuario_creacion integer,
    CONSTRAINT productos_moneda_check CHECK (((moneda)::text = ANY (ARRAY[('PYG'::character varying)::text, ('USD'::character varying)::text, ('BRL'::character varying)::text]))),
    CONSTRAINT productos_tipo_iva_check CHECK (((tipo_iva)::text = ANY (ARRAY[('GRAVADO_10'::character varying)::text, ('GRAVADO_5'::character varying)::text, ('EXENTO'::character varying)::text]))),
    CONSTRAINT productos_tipo_producto_check CHECK (((tipo_producto)::text = ANY (ARRAY[('PRODUCTO'::character varying)::text, ('SERVICIO'::character varying)::text, ('COMBO'::character varying)::text, ('REPUESTO'::character varying)::text])))
);


--
-- Name: TABLE productos; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.productos IS 'Productos y servicios ofrecidos por las empresas';


--
-- Name: productos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.productos_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: productos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.productos_id_seq OWNED BY public.productos.id;


--
-- Name: proveedores; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.proveedores (
    id integer NOT NULL,
    empresa_id integer NOT NULL,
    codigo character varying(20),
    ruc character varying(20) NOT NULL,
    razon_social character varying(200) NOT NULL,
    nombre_comercial character varying(200),
    direccion text,
    ciudad character varying(100),
    pais character varying(100) DEFAULT 'Paraguay'::character varying,
    telefono character varying(50),
    celular character varying(50),
    email character varying(150),
    sitio_web character varying(200),
    contacto_nombre character varying(150),
    contacto_cargo character varying(100),
    contacto_telefono character varying(50),
    contacto_email character varying(150),
    dias_pago integer DEFAULT 0,
    condiciones_pago text,
    moneda_preferida character varying(3) DEFAULT 'PYG'::character varying,
    cuenta_bancaria character varying(100),
    banco character varying(100),
    observaciones text,
    calificacion integer,
    estado boolean DEFAULT true,
    fecha_creacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    usuario_creacion integer,
    CONSTRAINT proveedores_calificacion_check CHECK (((calificacion >= 1) AND (calificacion <= 5)))
);


--
-- Name: TABLE proveedores; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.proveedores IS 'Proveedores de productos y servicios';


--
-- Name: proveedores_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.proveedores_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: proveedores_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.proveedores_id_seq OWNED BY public.proveedores.id;


--
-- Name: roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.roles (
    id integer NOT NULL,
    nombre character varying(100) NOT NULL,
    descripcion text,
    nivel integer NOT NULL,
    es_rol_sistema boolean DEFAULT false,
    estado boolean DEFAULT true,
    fecha_creacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT roles_nivel_check CHECK (((nivel >= 1) AND (nivel <= 5)))
);


--
-- Name: roles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.roles_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: roles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.roles_id_seq OWNED BY public.roles.id;


--
-- Name: stock; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.stock (
    id integer NOT NULL,
    producto_id integer NOT NULL,
    sucursal_id integer NOT NULL,
    cantidad numeric(15,3) DEFAULT 0,
    cantidad_reservada numeric(15,3) DEFAULT 0,
    cantidad_disponible numeric(15,3) DEFAULT 0,
    ubicacion character varying(100),
    lote character varying(50),
    fecha_vencimiento date,
    costo_promedio numeric(15,2),
    fecha_actualizacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_cantidad_positiva CHECK ((cantidad >= (0)::numeric)),
    CONSTRAINT chk_reservada_menor CHECK ((cantidad_reservada <= cantidad)),
    CONSTRAINT chk_reservada_positiva CHECK ((cantidad_reservada >= (0)::numeric))
);


--
-- Name: TABLE stock; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.stock IS 'Stock de productos por sucursal';


--
-- Name: stock_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.stock_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: stock_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.stock_id_seq OWNED BY public.stock.id;


--
-- Name: sucursal_modulos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sucursal_modulos (
    id integer NOT NULL,
    sucursal_id integer NOT NULL,
    modulo_id integer NOT NULL,
    empresa_modulo_id integer NOT NULL,
    activo boolean DEFAULT true,
    fecha_activacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    configuracion jsonb,
    limite_usuarios integer,
    limite_registros_diarios integer,
    permite_crear boolean DEFAULT true,
    permite_editar boolean DEFAULT true,
    permite_eliminar boolean DEFAULT true,
    permite_anular boolean DEFAULT true,
    permite_reportes boolean DEFAULT true,
    observaciones text,
    usuario_asigna integer,
    fecha_creacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: TABLE sucursal_modulos; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.sucursal_modulos IS 'Módulos habilitados para cada sucursal';


--
-- Name: COLUMN sucursal_modulos.empresa_modulo_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.sucursal_modulos.empresa_modulo_id IS 'Referencia al módulo de la empresa (hereda configuración base)';


--
-- Name: COLUMN sucursal_modulos.configuracion; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.sucursal_modulos.configuracion IS 'Configuración específica que sobrescribe la de empresa';


--
-- Name: sucursal_modulos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.sucursal_modulos_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: sucursal_modulos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.sucursal_modulos_id_seq OWNED BY public.sucursal_modulos.id;


--
-- Name: sucursales; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sucursales (
    id integer NOT NULL,
    empresa_id integer NOT NULL,
    nombre character varying(200) NOT NULL,
    codigo character varying(20),
    direccion text,
    ciudad character varying(100),
    departamento character varying(100),
    telefono character varying(50),
    email character varying(150),
    latitud numeric(10,8),
    longitud numeric(11,8),
    horario_apertura time without time zone,
    horario_cierre time without time zone,
    es_matriz boolean DEFAULT false,
    codigo_establecimiento character varying(3),
    responsable character varying(150),
    estado boolean DEFAULT true,
    fecha_creacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    tema_oscuro boolean DEFAULT false,
    CONSTRAINT chk_departamento CHECK (((departamento)::text = ANY (ARRAY[('CAPITAL'::character varying)::text, ('CONCEPCIÓN'::character varying)::text, ('SAN PEDRO'::character varying)::text, ('CORDILLERA'::character varying)::text, ('GUAIRÁ'::character varying)::text, ('CAAGUAZÚ'::character varying)::text, ('CAAZAPÁ'::character varying)::text, ('ITAPÚA'::character varying)::text, ('MISIONES'::character varying)::text, ('PARAGUARÍ'::character varying)::text, ('ALTO PARANÁ'::character varying)::text, ('CENTRAL'::character varying)::text, ('ÑEEMBUCÚ'::character varying)::text, ('AMAMBAY'::character varying)::text, ('CANINDEYÚ'::character varying)::text, ('PRESIDENTE HAYES'::character varying)::text, ('BOQUERÓN'::character varying)::text, ('ALTO PARAGUAY'::character varying)::text])))
);


--
-- Name: TABLE sucursales; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.sucursales IS 'Sucursales de cada empresa';


--
-- Name: COLUMN sucursales.tema_oscuro; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.sucursales.tema_oscuro IS 'Indica si la sucursal usa tema oscuro por defecto (true) o claro (false). Si es NULL, hereda de la empresa';


--
-- Name: sucursales_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.sucursales_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: sucursales_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.sucursales_id_seq OWNED BY public.sucursales.id;


--
-- Name: talonarios; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.talonarios (
    id integer NOT NULL,
    empresa_id integer NOT NULL,
    sucursal_id integer NOT NULL,
    tipo_comprobante character varying(30) NOT NULL,
    timbrado character varying(20) NOT NULL,
    establecimiento character varying(3) NOT NULL,
    punto_expedicion character varying(3) NOT NULL,
    numero_desde integer NOT NULL,
    numero_hasta integer NOT NULL,
    numero_actual integer NOT NULL,
    fecha_inicio_vigencia date NOT NULL,
    fecha_fin_vigencia date NOT NULL,
    estado character varying(20) DEFAULT 'ACTIVO'::character varying,
    observaciones text,
    fecha_creacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    usuario_creacion integer,
    CONSTRAINT chk_fechas_vigencia CHECK ((fecha_inicio_vigencia < fecha_fin_vigencia)),
    CONSTRAINT chk_numero_actual_rango CHECK (((numero_actual >= numero_desde) AND (numero_actual <= numero_hasta))),
    CONSTRAINT chk_rango_numeros CHECK ((numero_desde <= numero_hasta)),
    CONSTRAINT talonarios_estado_check CHECK (((estado)::text = ANY (ARRAY[('ACTIVO'::character varying)::text, ('VENCIDO'::character varying)::text, ('AGOTADO'::character varying)::text, ('ANULADO'::character varying)::text])))
);


--
-- Name: TABLE talonarios; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.talonarios IS 'Talonarios de facturación SET';


--
-- Name: talonarios_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.talonarios_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: talonarios_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.talonarios_id_seq OWNED BY public.talonarios.id;


--
-- Name: tipos_cambio; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tipos_cambio (
    id integer NOT NULL,
    empresa_id integer NOT NULL,
    fecha date NOT NULL,
    moneda_origen character varying(3) DEFAULT 'PYG'::character varying NOT NULL,
    moneda_destino character varying(3) NOT NULL,
    compra numeric(15,6) NOT NULL,
    venta numeric(15,6) NOT NULL,
    promedio numeric(15,6) NOT NULL,
    fuente character varying(50) DEFAULT 'BCP'::character varying,
    observaciones text,
    fecha_creacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    usuario_creacion integer,
    CONSTRAINT chk_monedas_diferentes CHECK (((moneda_origen)::text <> (moneda_destino)::text)),
    CONSTRAINT chk_tasas_positivas CHECK (((compra > (0)::numeric) AND (venta > (0)::numeric)))
);


--
-- Name: TABLE tipos_cambio; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.tipos_cambio IS 'Tipos de cambio para diferentes monedas';


--
-- Name: tipos_cambio_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.tipos_cambio_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tipos_cambio_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.tipos_cambio_id_seq OWNED BY public.tipos_cambio.id;


--
-- Name: transferencias_stock; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.transferencias_stock (
    id integer NOT NULL,
    empresa_id integer NOT NULL,
    numero_transferencia character varying(20) NOT NULL,
    sucursal_origen_id integer NOT NULL,
    sucursal_destino_id integer NOT NULL,
    estado character varying(20) DEFAULT 'PENDIENTE'::character varying,
    fecha_solicitud timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    fecha_envio timestamp without time zone,
    fecha_recepcion timestamp without time zone,
    usuario_solicita integer,
    usuario_autoriza integer,
    usuario_recibe integer,
    observaciones text,
    motivo_rechazo text,
    CONSTRAINT chk_sucursales_diferentes CHECK ((sucursal_origen_id <> sucursal_destino_id)),
    CONSTRAINT transferencias_stock_estado_check CHECK (((estado)::text = ANY (ARRAY[('PENDIENTE'::character varying)::text, ('EN_TRANSITO'::character varying)::text, ('RECIBIDA'::character varying)::text, ('RECHAZADA'::character varying)::text, ('CANCELADA'::character varying)::text])))
);


--
-- Name: transferencias_stock_detalle; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.transferencias_stock_detalle (
    id integer NOT NULL,
    transferencia_id integer NOT NULL,
    producto_id integer NOT NULL,
    cantidad_solicitada numeric(15,3) NOT NULL,
    cantidad_enviada numeric(15,3),
    cantidad_recibida numeric(15,3),
    cantidad_pendiente numeric(15,3) DEFAULT 0,
    lote character varying(50),
    observaciones text,
    CONSTRAINT chk_cantidad_solicitada_positiva CHECK ((cantidad_solicitada > (0)::numeric))
);


--
-- Name: transferencias_stock_detalle_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.transferencias_stock_detalle_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: transferencias_stock_detalle_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.transferencias_stock_detalle_id_seq OWNED BY public.transferencias_stock_detalle.id;


--
-- Name: transferencias_stock_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.transferencias_stock_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: transferencias_stock_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.transferencias_stock_id_seq OWNED BY public.transferencias_stock.id;


--
-- Name: unidades_medida; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.unidades_medida (
    id integer NOT NULL,
    nombre character varying(50) NOT NULL,
    abreviatura character varying(10) NOT NULL,
    tipo character varying(20),
    estado boolean DEFAULT true,
    CONSTRAINT unidades_medida_tipo_check CHECK (((tipo)::text = ANY (ARRAY[('UNIDAD'::character varying)::text, ('PESO'::character varying)::text, ('VOLUMEN'::character varying)::text, ('LONGITUD'::character varying)::text, ('AREA'::character varying)::text])))
);


--
-- Name: unidades_medida_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.unidades_medida_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: unidades_medida_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.unidades_medida_id_seq OWNED BY public.unidades_medida.id;


--
-- Name: usuario_sucursales; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.usuario_sucursales (
    id integer NOT NULL,
    usuario_id integer NOT NULL,
    sucursal_id integer NOT NULL,
    fecha_asignacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    usuario_asignacion integer
);


--
-- Name: usuario_sucursales_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.usuario_sucursales_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: usuario_sucursales_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.usuario_sucursales_id_seq OWNED BY public.usuario_sucursales.id;


--
-- Name: usuarios; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.usuarios (
    id integer NOT NULL,
    empresa_id integer NOT NULL,
    sucursal_id integer,
    rol_id integer NOT NULL,
    nombre character varying(100) NOT NULL,
    apellido character varying(100) NOT NULL,
    ci character varying(20) NOT NULL,
    email character varying(150) NOT NULL,
    telefono character varying(50),
    direccion text,
    username character varying(50) NOT NULL,
    password character varying(255) NOT NULL,
    foto_perfil character varying(255),
    firma_digital character varying(255),
    estado boolean DEFAULT true,
    ultimo_acceso timestamp without time zone,
    fecha_creacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    usuario_creacion integer,
    preferencias jsonb,
    tema_oscuro boolean,
    CONSTRAINT chk_ci_formato CHECK (((ci)::text ~ '^[0-9]{1,8}$'::text)),
    CONSTRAINT chk_password_length CHECK ((length((password)::text) >= 8))
);


--
-- Name: TABLE usuarios; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.usuarios IS 'Usuarios del sistema con sus roles y permisos';


--
-- Name: COLUMN usuarios.preferencias; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.usuarios.preferencias IS 'Preferencias del usuario en formato JSON';


--
-- Name: COLUMN usuarios.tema_oscuro; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.usuarios.tema_oscuro IS 'Indica si el usuario prefiere tema oscuro (true) o claro (false). Si es NULL, hereda de sucursal/empresa. También se puede guardar en preferencias JSON';


--
-- Name: usuarios_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.usuarios_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: usuarios_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.usuarios_id_seq OWNED BY public.usuarios.id;


--
-- Name: ventas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ventas (
    id integer NOT NULL,
    empresa_id integer NOT NULL,
    sucursal_id integer NOT NULL,
    cliente_id integer,
    usuario_id integer NOT NULL,
    vendedor_id integer,
    lista_precio_id integer,
    orden_servicio_id integer,
    numero_venta character varying(20) NOT NULL,
    tipo_comprobante character varying(30) DEFAULT 'FACTURA'::character varying,
    numero_factura character varying(20),
    timbrado character varying(20),
    tipo_venta character varying(20) DEFAULT 'PRODUCTO'::character varying,
    tipo_pago character varying(20) DEFAULT 'CONTADO'::character varying,
    estado character varying(20) DEFAULT 'PENDIENTE'::character varying,
    moneda character varying(3) DEFAULT 'PYG'::character varying,
    tipo_cambio numeric(15,6) DEFAULT 1,
    subtotal numeric(15,2) DEFAULT 0 NOT NULL,
    descuento_porcentaje numeric(5,2) DEFAULT 0,
    descuento_monto numeric(15,2) DEFAULT 0,
    recargo_porcentaje numeric(5,2) DEFAULT 0,
    recargo_monto numeric(15,2) DEFAULT 0,
    subtotal_gravado_10 numeric(15,2) DEFAULT 0,
    iva_10 numeric(15,2) DEFAULT 0,
    subtotal_gravado_5 numeric(15,2) DEFAULT 0,
    iva_5 numeric(15,2) DEFAULT 0,
    subtotal_exento numeric(15,2) DEFAULT 0,
    total_iva numeric(15,2) DEFAULT 0,
    total numeric(15,2) NOT NULL,
    total_moneda_principal numeric(15,2),
    monto_pagado numeric(15,2) DEFAULT 0,
    monto_pendiente numeric(15,2) DEFAULT 0,
    vuelto numeric(15,2) DEFAULT 0,
    observaciones text,
    notas_internas text,
    origen_venta character varying(50) DEFAULT 'SUCURSAL'::character varying,
    fecha_venta timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    fecha_vencimiento date,
    fecha_anulacion timestamp without time zone,
    fecha_facturacion timestamp without time zone,
    motivo_anulacion text,
    usuario_anula integer,
    requiere_entrega boolean DEFAULT false,
    direccion_entrega text,
    fecha_entrega_programada date,
    fecha_entrega_real timestamp without time zone,
    estado_entrega character varying(20),
    es_borrador boolean DEFAULT false,
    factura_impresa boolean DEFAULT false,
    cantidad_impresiones integer DEFAULT 0,
    fecha_creacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT ventas_estado_check CHECK (((estado)::text = ANY (ARRAY[('PENDIENTE'::character varying)::text, ('COMPLETADA'::character varying)::text, ('ANULADA'::character varying)::text, ('DEVUELTA_PARCIAL'::character varying)::text, ('DEVUELTA_TOTAL'::character varying)::text, ('EN_PROCESO'::character varying)::text, ('FACTURADA'::character varying)::text]))),
    CONSTRAINT ventas_estado_entrega_check CHECK (((estado_entrega)::text = ANY (ARRAY[('PENDIENTE'::character varying)::text, ('EN_CAMINO'::character varying)::text, ('ENTREGADA'::character varying)::text, ('RECHAZADA'::character varying)::text]))),
    CONSTRAINT ventas_moneda_check CHECK (((moneda)::text = ANY (ARRAY[('PYG'::character varying)::text, ('USD'::character varying)::text, ('BRL'::character varying)::text]))),
    CONSTRAINT ventas_origen_venta_check CHECK (((origen_venta)::text = ANY (ARRAY[('SUCURSAL'::character varying)::text, ('WEB'::character varying)::text, ('TELEFONO'::character varying)::text, ('WHATSAPP'::character varying)::text, ('ORDEN_SERVICIO'::character varying)::text]))),
    CONSTRAINT ventas_tipo_comprobante_check CHECK (((tipo_comprobante)::text = ANY (ARRAY[('FACTURA'::character varying)::text, ('FACTURA_ELECTRONICA'::character varying)::text, ('TICKET'::character varying)::text, ('NOTA_CREDITO'::character varying)::text, ('NOTA_DEBITO'::character varying)::text, ('REMISION'::character varying)::text, ('PRESUPUESTO'::character varying)::text, ('PROFORMA'::character varying)::text]))),
    CONSTRAINT ventas_tipo_pago_check CHECK (((tipo_pago)::text = ANY (ARRAY[('CONTADO'::character varying)::text, ('CREDITO'::character varying)::text, ('MIXTO'::character varying)::text]))),
    CONSTRAINT ventas_tipo_venta_check CHECK (((tipo_venta)::text = ANY (ARRAY[('PRODUCTO'::character varying)::text, ('SERVICIO'::character varying)::text, ('MIXTA'::character varying)::text])))
);


--
-- Name: TABLE ventas; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.ventas IS 'Ventas realizadas a clientes';


--
-- Name: ventas_detalle; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ventas_detalle (
    id integer NOT NULL,
    venta_id integer NOT NULL,
    producto_id integer NOT NULL,
    orden_servicio_id integer,
    descripcion text,
    cantidad numeric(15,3) NOT NULL,
    precio_unitario numeric(15,2) NOT NULL,
    precio_lista numeric(15,2),
    descuento_lista_porcentaje numeric(5,2) DEFAULT 0,
    descuento_lista_monto numeric(15,2) DEFAULT 0,
    descuento_adicional_porcentaje numeric(5,2) DEFAULT 0,
    descuento_adicional_monto numeric(15,2) DEFAULT 0,
    descuento_total_porcentaje numeric(5,2) DEFAULT 0,
    descuento_total_monto numeric(15,2) DEFAULT 0,
    tipo_iva character varying(20) DEFAULT 'GRAVADO_10'::character varying NOT NULL,
    porcentaje_iva numeric(5,2),
    subtotal numeric(15,2) NOT NULL,
    monto_iva numeric(15,2) DEFAULT 0,
    total numeric(15,2) NOT NULL,
    costo_unitario numeric(15,2),
    costo_total numeric(15,2),
    utilidad numeric(15,2),
    margen_porcentaje numeric(5,2),
    lote character varying(50),
    numero_serie character varying(100),
    fecha_vencimiento date,
    es_servicio boolean DEFAULT false,
    es_repuesto_orden boolean DEFAULT false,
    observaciones text,
    linea_orden integer DEFAULT 0,
    CONSTRAINT chk_cantidad_positiva_venta CHECK ((cantidad > (0)::numeric)),
    CONSTRAINT chk_precio_positivo CHECK ((precio_unitario >= (0)::numeric))
);


--
-- Name: TABLE ventas_detalle; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.ventas_detalle IS 'Detalle de productos en cada venta';


--
-- Name: ventas_detalle_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ventas_detalle_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ventas_detalle_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ventas_detalle_id_seq OWNED BY public.ventas_detalle.id;


--
-- Name: ventas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ventas_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ventas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ventas_id_seq OWNED BY public.ventas.id;


--
-- Name: vista_clientes_frecuentes; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.vista_clientes_frecuentes AS
 SELECT c.id,
    COALESCE(
        CASE
            WHEN ((c.tipo_cliente)::text = 'PERSONA'::text) THEN ((((c.nombre)::text || ' '::text) || (COALESCE(c.apellido, ''::character varying))::text))::character varying
            WHEN ((c.tipo_cliente)::text = 'EMPRESA'::text) THEN COALESCE(c.nombre_fantasia, c.razon_social)
            ELSE c.nombre
        END, c.nombre) AS nombre_completo,
    c.celular,
    c.email,
    c.categoria_cliente,
    count(v.id) AS total_compras,
    sum(v.total) AS monto_total_compras,
    max(v.fecha_venta) AS ultima_compra,
    avg(v.total) AS promedio_compra,
    c.limite_credito,
    c.credito_utilizado,
    c.credito_disponible
   FROM (public.clientes c
     LEFT JOIN public.ventas v ON (((v.cliente_id = c.id) AND ((v.estado)::text = 'COMPLETADA'::text))))
  GROUP BY c.id, c.nombre, c.apellido, c.razon_social, c.nombre_fantasia, c.tipo_cliente, c.celular, c.email, c.categoria_cliente, c.limite_credito, c.credito_utilizado, c.credito_disponible;


--
-- Name: vista_cuentas_vencidas; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.vista_cuentas_vencidas AS
 SELECT cc.id,
    COALESCE(
        CASE
            WHEN ((c.tipo_cliente)::text = 'PERSONA'::text) THEN ((((c.nombre)::text || ' '::text) || (COALESCE(c.apellido, ''::character varying))::text))::character varying
            WHEN ((c.tipo_cliente)::text = 'EMPRESA'::text) THEN COALESCE(c.nombre_fantasia, c.razon_social)
            ELSE c.nombre
        END, c.nombre) AS cliente,
    cc.numero_documento,
    cc.tipo_documento,
    cc.fecha_emision,
    cc.fecha_vencimiento,
    cc.monto_total,
    cc.monto_pagado,
    cc.monto_pendiente,
    cc.dias_vencido,
    cc.estado,
    v.numero_venta
   FROM ((public.cuentas_cobrar cc
     JOIN public.clientes c ON ((c.id = cc.cliente_id)))
     JOIN public.ventas v ON ((v.id = cc.venta_id)))
  WHERE (((cc.estado)::text = 'VENCIDO'::text) OR (cc.dias_vencido > 0));


--
-- Name: vista_inventario_valorizado; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.vista_inventario_valorizado AS
 SELECT s.sucursal_id,
    suc.nombre AS sucursal,
    s.producto_id,
    p.codigo,
    p.nombre AS producto,
    s.cantidad,
    s.costo_promedio,
    (s.cantidad * s.costo_promedio) AS valor_total,
    p.precio_venta,
    (s.cantidad * p.precio_venta) AS valor_venta,
    ((p.precio_venta - s.costo_promedio) * s.cantidad) AS utilidad_potencial
   FROM ((public.stock s
     JOIN public.productos p ON ((p.id = s.producto_id)))
     JOIN public.sucursales suc ON ((suc.id = s.sucursal_id)))
  WHERE (p.controla_stock = true);


--
-- Name: vista_modulos_disponibles; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.vista_modulos_disponibles AS
SELECT
    NULL::integer AS id,
    NULL::character varying(50) AS codigo,
    NULL::character varying(100) AS nombre,
    NULL::text AS descripcion,
    NULL::character varying(50) AS categoria,
    NULL::character varying(100) AS icono,
    NULL::character varying(20) AS color,
    NULL::boolean AS requiere_licencia,
    NULL::numeric(15,2) AS precio_mensual,
    NULL::boolean AS es_modulo_base,
    NULL::bigint AS cantidad_dependencias,
    NULL::text AS modulos_requeridos;


--
-- Name: VIEW vista_modulos_disponibles; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON VIEW public.vista_modulos_disponibles IS 'Vista de módulos disponibles con sus dependencias';


--
-- Name: vista_modulos_empresa; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.vista_modulos_empresa AS
 SELECT e.id AS empresa_id,
    e.nombre AS empresa,
    m.id AS modulo_id,
    m.codigo AS modulo_codigo,
    m.nombre AS modulo_nombre,
    m.categoria AS modulo_categoria,
    em.activo,
    em.tipo_licencia,
    em.fecha_inicio_licencia,
    em.fecha_fin_licencia,
    em.fecha_vencimiento,
        CASE
            WHEN (em.fecha_vencimiento IS NULL) THEN NULL::integer
            ELSE (EXTRACT(day FROM ((em.fecha_vencimiento)::timestamp with time zone - CURRENT_TIMESTAMP)))::integer
        END AS dias_restantes,
        CASE
            WHEN (em.activo = false) THEN 'INACTIVO'::text
            WHEN (em.fecha_vencimiento IS NULL) THEN 'VIGENTE'::text
            WHEN (em.fecha_vencimiento > CURRENT_TIMESTAMP) THEN 'VIGENTE'::text
            ELSE 'VENCIDO'::text
        END AS estado_licencia
   FROM ((public.empresas e
     JOIN public.empresa_modulos em ON ((em.empresa_id = e.id)))
     JOIN public.modulos m ON ((m.id = em.modulo_id)))
  WHERE (e.estado = true);


--
-- Name: VIEW vista_modulos_empresa; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON VIEW public.vista_modulos_empresa IS 'Vista de módulos activos por empresa con estado de licencia';


--
-- Name: vista_modulos_sucursal; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.vista_modulos_sucursal AS
 SELECT s.id AS sucursal_id,
    s.nombre AS sucursal,
    s.empresa_id,
    e.nombre AS empresa,
    m.id AS modulo_id,
    m.codigo AS modulo_codigo,
    m.nombre AS modulo_nombre,
    m.categoria AS modulo_categoria,
    sm.activo,
    sm.permite_crear,
    sm.permite_editar,
    sm.permite_eliminar,
    sm.permite_anular,
    sm.permite_reportes,
    em.fecha_vencimiento AS fecha_vencimiento_empresa
   FROM ((((public.sucursales s
     JOIN public.empresas e ON ((e.id = s.empresa_id)))
     JOIN public.sucursal_modulos sm ON ((sm.sucursal_id = s.id)))
     JOIN public.modulos m ON ((m.id = sm.modulo_id)))
     JOIN public.empresa_modulos em ON ((em.id = sm.empresa_modulo_id)))
  WHERE ((s.estado = true) AND (em.activo = true));


--
-- Name: VIEW vista_modulos_sucursal; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON VIEW public.vista_modulos_sucursal IS 'Vista de módulos activos por sucursal con permisos';


--
-- Name: vista_ordenes_pendientes; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.vista_ordenes_pendientes AS
 SELECT o.id,
    o.numero_orden,
    COALESCE(
        CASE
            WHEN ((c.tipo_cliente)::text = 'PERSONA'::text) THEN ((((c.nombre)::text || ' '::text) || (COALESCE(c.apellido, ''::character varying))::text))::character varying
            WHEN ((c.tipo_cliente)::text = 'EMPRESA'::text) THEN COALESCE(c.nombre_fantasia, c.razon_social)
            ELSE c.nombre
        END, c.nombre) AS cliente,
    c.celular,
    o.tipo_equipo,
    o.marca,
    o.modelo,
    eo.nombre AS estado,
    eo.color AS color_estado,
    o.fecha_recepcion,
    o.fecha_limite_retiro,
    o.prioridad,
    o.presupuesto_total,
    o.anticipo_monto,
    o.saldo_pendiente
   FROM ((public.ordenes_servicio o
     JOIN public.clientes c ON ((c.id = o.cliente_id)))
     JOIN public.estados_orden eo ON ((eo.id = o.estado_id)))
  WHERE ((o.estado_activo = true) AND (NOT (o.estado_id IN ( SELECT estados_orden.id
           FROM public.estados_orden
          WHERE (estados_orden.es_final = true)))));


--
-- Name: vista_productos_mas_vendidos; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.vista_productos_mas_vendidos AS
 SELECT vd.producto_id,
    p.codigo,
    p.nombre,
    c.nombre AS categoria,
    sum(vd.cantidad) AS total_vendido,
    sum(vd.total) AS monto_total,
    count(DISTINCT vd.venta_id) AS veces_vendido,
    avg(vd.precio_unitario) AS precio_promedio
   FROM (((public.ventas_detalle vd
     JOIN public.productos p ON ((p.id = vd.producto_id)))
     LEFT JOIN public.categorias c ON ((c.id = p.categoria_id)))
     JOIN public.ventas v ON (((v.id = vd.venta_id) AND ((v.estado)::text = 'COMPLETADA'::text))))
  GROUP BY vd.producto_id, p.codigo, p.nombre, c.nombre
  ORDER BY (sum(vd.cantidad)) DESC;


--
-- Name: vista_stock_bajo; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.vista_stock_bajo AS
 SELECT s.sucursal_id,
    s.producto_id,
    p.codigo,
    p.nombre AS producto,
    c.nombre AS categoria,
    m.nombre AS marca,
    s.cantidad_disponible,
    p.stock_minimo,
    p.stock_maximo,
    p.punto_reorden,
        CASE
            WHEN (s.cantidad_disponible <= (p.stock_minimo)::numeric) THEN 'CRITICO'::text
            WHEN (s.cantidad_disponible <= (p.punto_reorden)::numeric) THEN 'BAJO'::text
            ELSE 'NORMAL'::text
        END AS nivel_alerta
   FROM (((public.stock s
     JOIN public.productos p ON ((p.id = s.producto_id)))
     LEFT JOIN public.categorias c ON ((c.id = p.categoria_id)))
     LEFT JOIN public.marcas m ON ((m.id = p.marca_id)))
  WHERE ((p.controla_stock = true) AND (s.cantidad_disponible <= (p.punto_reorden)::numeric));


--
-- Name: vista_ventas_diarias; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.vista_ventas_diarias AS
 SELECT empresa_id,
    sucursal_id,
    date(fecha_venta) AS fecha,
    count(*) AS total_ventas,
    sum(total) AS monto_total,
    sum(monto_pagado) AS monto_pagado,
    sum(monto_pendiente) AS monto_pendiente,
    avg(total) AS promedio_venta
   FROM public.ventas v
  WHERE ((estado)::text <> ALL (ARRAY[('ANULADA'::character varying)::text, ('DEVUELTA_TOTAL'::character varying)::text]))
  GROUP BY empresa_id, sucursal_id, (date(fecha_venta));


--
-- Name: ajustes_inventario id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ajustes_inventario ALTER COLUMN id SET DEFAULT nextval('public.ajustes_inventario_id_seq'::regclass);


--
-- Name: ajustes_inventario_detalle id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ajustes_inventario_detalle ALTER COLUMN id SET DEFAULT nextval('public.ajustes_inventario_detalle_id_seq'::regclass);


--
-- Name: arqueos_caja id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.arqueos_caja ALTER COLUMN id SET DEFAULT nextval('public.arqueos_caja_id_seq'::regclass);


--
-- Name: arqueos_caja_detalle id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.arqueos_caja_detalle ALTER COLUMN id SET DEFAULT nextval('public.arqueos_caja_detalle_id_seq'::regclass);


--
-- Name: auditoria id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.auditoria ALTER COLUMN id SET DEFAULT nextval('public.auditoria_id_seq'::regclass);


--
-- Name: cajas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cajas ALTER COLUMN id SET DEFAULT nextval('public.cajas_id_seq'::regclass);


--
-- Name: categorias id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias ALTER COLUMN id SET DEFAULT nextval('public.categorias_id_seq'::regclass);


--
-- Name: chat_participantes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chat_participantes ALTER COLUMN id SET DEFAULT nextval('public.chat_participantes_id_seq'::regclass);


--
-- Name: chats id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chats ALTER COLUMN id SET DEFAULT nextval('public.chats_id_seq'::regclass);


--
-- Name: clientes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clientes ALTER COLUMN id SET DEFAULT nextval('public.clientes_id_seq'::regclass);


--
-- Name: clientes_contactos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clientes_contactos ALTER COLUMN id SET DEFAULT nextval('public.clientes_contactos_id_seq'::regclass);


--
-- Name: clientes_imagenes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clientes_imagenes ALTER COLUMN id SET DEFAULT nextval('public.clientes_imagenes_id_seq'::regclass);


--
-- Name: compras id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.compras ALTER COLUMN id SET DEFAULT nextval('public.compras_id_seq'::regclass);


--
-- Name: compras_detalle id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.compras_detalle ALTER COLUMN id SET DEFAULT nextval('public.compras_detalle_id_seq'::regclass);


--
-- Name: configuraciones id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configuraciones ALTER COLUMN id SET DEFAULT nextval('public.configuraciones_id_seq'::regclass);


--
-- Name: cotizaciones id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cotizaciones ALTER COLUMN id SET DEFAULT nextval('public.cotizaciones_id_seq'::regclass);


--
-- Name: cotizaciones_detalle id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cotizaciones_detalle ALTER COLUMN id SET DEFAULT nextval('public.cotizaciones_detalle_id_seq'::regclass);


--
-- Name: crm_chats id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_chats ALTER COLUMN id SET DEFAULT nextval('public.crm_chats_id_seq'::regclass);


--
-- Name: crm_mensajes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_mensajes ALTER COLUMN id SET DEFAULT nextval('public.crm_mensajes_id_seq'::regclass);


--
-- Name: crm_plantillas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_plantillas ALTER COLUMN id SET DEFAULT nextval('public.crm_plantillas_id_seq'::regclass);


--
-- Name: cuentas_cobrar id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cuentas_cobrar ALTER COLUMN id SET DEFAULT nextval('public.cuentas_cobrar_id_seq'::regclass);


--
-- Name: cuentas_pagar id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cuentas_pagar ALTER COLUMN id SET DEFAULT nextval('public.cuentas_pagar_id_seq'::regclass);


--
-- Name: descuentos_especiales id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.descuentos_especiales ALTER COLUMN id SET DEFAULT nextval('public.descuentos_especiales_id_seq'::regclass);


--
-- Name: empresa_modulos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.empresa_modulos ALTER COLUMN id SET DEFAULT nextval('public.empresa_modulos_id_seq'::regclass);


--
-- Name: empresas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.empresas ALTER COLUMN id SET DEFAULT nextval('public.empresas_id_seq'::regclass);


--
-- Name: estados_orden id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.estados_orden ALTER COLUMN id SET DEFAULT nextval('public.estados_orden_id_seq'::regclass);


--
-- Name: facturacion_electronica id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.facturacion_electronica ALTER COLUMN id SET DEFAULT nextval('public.facturacion_electronica_id_seq'::regclass);


--
-- Name: listas_precios id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.listas_precios ALTER COLUMN id SET DEFAULT nextval('public.listas_precios_id_seq'::regclass);


--
-- Name: listas_precios_detalle id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.listas_precios_detalle ALTER COLUMN id SET DEFAULT nextval('public.listas_precios_detalle_id_seq'::regclass);


--
-- Name: logs_sistema id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.logs_sistema ALTER COLUMN id SET DEFAULT nextval('public.logs_sistema_id_seq'::regclass);


--
-- Name: marcas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.marcas ALTER COLUMN id SET DEFAULT nextval('public.marcas_id_seq'::regclass);


--
-- Name: mensajes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mensajes ALTER COLUMN id SET DEFAULT nextval('public.mensajes_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: modulos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.modulos ALTER COLUMN id SET DEFAULT nextval('public.modulos_id_seq'::regclass);


--
-- Name: modulos_dependencias id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.modulos_dependencias ALTER COLUMN id SET DEFAULT nextval('public.modulos_dependencias_id_seq'::regclass);


--
-- Name: movimientos_caja id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimientos_caja ALTER COLUMN id SET DEFAULT nextval('public.movimientos_caja_id_seq'::regclass);


--
-- Name: movimientos_stock id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimientos_stock ALTER COLUMN id SET DEFAULT nextval('public.movimientos_stock_id_seq'::regclass);


--
-- Name: notificaciones id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notificaciones ALTER COLUMN id SET DEFAULT nextval('public.notificaciones_id_seq'::regclass);


--
-- Name: ordenes_servicio id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_servicio ALTER COLUMN id SET DEFAULT nextval('public.ordenes_servicio_id_seq'::regclass);


--
-- Name: ordenes_servicio_comentarios id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_servicio_comentarios ALTER COLUMN id SET DEFAULT nextval('public.ordenes_servicio_comentarios_id_seq'::regclass);


--
-- Name: ordenes_servicio_documentos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_servicio_documentos ALTER COLUMN id SET DEFAULT nextval('public.ordenes_servicio_documentos_id_seq'::regclass);


--
-- Name: ordenes_servicio_historial id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_servicio_historial ALTER COLUMN id SET DEFAULT nextval('public.ordenes_servicio_historial_id_seq'::regclass);


--
-- Name: ordenes_servicio_imagenes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_servicio_imagenes ALTER COLUMN id SET DEFAULT nextval('public.ordenes_servicio_imagenes_id_seq'::regclass);


--
-- Name: ordenes_servicio_mano_obra id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_servicio_mano_obra ALTER COLUMN id SET DEFAULT nextval('public.ordenes_servicio_mano_obra_id_seq'::regclass);


--
-- Name: ordenes_servicio_notificaciones id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_servicio_notificaciones ALTER COLUMN id SET DEFAULT nextval('public.ordenes_servicio_notificaciones_id_seq'::regclass);


--
-- Name: ordenes_servicio_repuestos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_servicio_repuestos ALTER COLUMN id SET DEFAULT nextval('public.ordenes_servicio_repuestos_id_seq'::regclass);


--
-- Name: pagos_cuentas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pagos_cuentas ALTER COLUMN id SET DEFAULT nextval('public.pagos_cuentas_id_seq'::regclass);


--
-- Name: pagos_ventas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pagos_ventas ALTER COLUMN id SET DEFAULT nextval('public.pagos_ventas_id_seq'::regclass);


--
-- Name: permisos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permisos ALTER COLUMN id SET DEFAULT nextval('public.permisos_id_seq'::regclass);


--
-- Name: producto_imagenes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.producto_imagenes ALTER COLUMN id SET DEFAULT nextval('public.producto_imagenes_id_seq'::regclass);


--
-- Name: productos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.productos ALTER COLUMN id SET DEFAULT nextval('public.productos_id_seq'::regclass);


--
-- Name: proveedores id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proveedores ALTER COLUMN id SET DEFAULT nextval('public.proveedores_id_seq'::regclass);


--
-- Name: roles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles ALTER COLUMN id SET DEFAULT nextval('public.roles_id_seq'::regclass);


--
-- Name: stock id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock ALTER COLUMN id SET DEFAULT nextval('public.stock_id_seq'::regclass);


--
-- Name: sucursal_modulos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sucursal_modulos ALTER COLUMN id SET DEFAULT nextval('public.sucursal_modulos_id_seq'::regclass);


--
-- Name: sucursales id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sucursales ALTER COLUMN id SET DEFAULT nextval('public.sucursales_id_seq'::regclass);


--
-- Name: talonarios id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.talonarios ALTER COLUMN id SET DEFAULT nextval('public.talonarios_id_seq'::regclass);


--
-- Name: tipos_cambio id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tipos_cambio ALTER COLUMN id SET DEFAULT nextval('public.tipos_cambio_id_seq'::regclass);


--
-- Name: transferencias_stock id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transferencias_stock ALTER COLUMN id SET DEFAULT nextval('public.transferencias_stock_id_seq'::regclass);


--
-- Name: transferencias_stock_detalle id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transferencias_stock_detalle ALTER COLUMN id SET DEFAULT nextval('public.transferencias_stock_detalle_id_seq'::regclass);


--
-- Name: unidades_medida id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.unidades_medida ALTER COLUMN id SET DEFAULT nextval('public.unidades_medida_id_seq'::regclass);


--
-- Name: usuario_sucursales id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuario_sucursales ALTER COLUMN id SET DEFAULT nextval('public.usuario_sucursales_id_seq'::regclass);


--
-- Name: usuarios id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios ALTER COLUMN id SET DEFAULT nextval('public.usuarios_id_seq'::regclass);


--
-- Name: ventas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ventas ALTER COLUMN id SET DEFAULT nextval('public.ventas_id_seq'::regclass);


--
-- Name: ventas_detalle id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ventas_detalle ALTER COLUMN id SET DEFAULT nextval('public.ventas_detalle_id_seq'::regclass);


--
-- Data for Name: ajustes_inventario; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ajustes_inventario (id, empresa_id, sucursal_id, numero_ajuste, tipo_ajuste, motivo, observaciones, estado, usuario_solicita, usuario_aprueba, fecha_solicitud, fecha_aprobacion, fecha_procesamiento) FROM stdin;
1	1	1	AJUS-00000001	ENTRADA	Ingreso de mercaderia	10 cajas	PROCESADO	1	1	2026-01-17 01:11:01.470056	2026-01-16 21:11:33	2026-01-16 21:11:33
2	1	1	AJUS-00000002	ENTRADA	Ingreso de mercaderia	Comprando mercaderia	PROCESADO	1	1	2026-01-20 01:34:47.505978	2026-01-19 21:35:07	2026-01-19 21:35:07
3	1	1	AJUS-00000003	ENTRADA	Ingreso de mercaderia	hola	PENDIENTE	1	\N	2026-01-25 05:38:16.868196	\N	\N
4	1	1	AJUS-00000004	ENTRADA	conteo fisico	conteo fisico	PROCESADO	1	1	2026-02-08 22:15:45.240177	2026-02-09 01:16:02	2026-02-09 01:16:02
\.


--
-- Data for Name: ajustes_inventario_detalle; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ajustes_inventario_detalle (id, ajuste_id, producto_id, cantidad_existente, cantidad_nueva, diferencia, costo_unitario, observaciones) FROM stdin;
1	1	2	0.000	10.000	10.000	5000000.00	
2	2	5	0.000	100.000	100.000	0.00	
3	3	1	11.000	2.000	-9.000	2500000.00	
4	4	4	0.000	100.000	100.000	0.00	
\.


--
-- Data for Name: arqueos_caja; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.arqueos_caja (id, caja_id, fecha_arqueo, hora_arqueo, saldo_sistema, saldo_fisico, diferencia, observaciones, usuario_id, fecha_creacion) FROM stdin;
1	1	2026-01-21	22:01:34	3665200.00	4110000.00	444800.00		1	2026-01-22 02:01:34.678388
2	2	2026-02-09	17:46:22	2817000.00	2817000.00	0.00		1	2026-02-09 14:46:22.01034
3	2	2026-02-09	18:16:31	2717000.00	3000000.00	283000.00		1	2026-02-09 15:16:31.348999
4	2	2026-02-09	18:31:18	2717000.00	2717000.00	0.00		1	2026-02-09 15:31:18.703365
\.


--
-- Data for Name: arqueos_caja_detalle; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.arqueos_caja_detalle (id, arqueo_id, denominacion, cantidad, subtotal, orden, tipo_medio_pago, descripcion) FROM stdin;
1	1	100000	11	1100000.00	1	EFECTIVO	\N
2	1	50000	8	400000.00	2	EFECTIVO	\N
3	1	20000	56	1120000.00	3	EFECTIVO	\N
4	1	10000	15	150000.00	4	EFECTIVO	\N
5	1	TARJETA_DEBITO	1	90000.00	5	TARJETA_DEBITO	\N
6	1	TRANSFERENCIA	1	1250000.00	6	TRANSFERENCIA	\N
7	2	100000	5	500000.00	1	EFECTIVO	\N
8	2	TARJETA_DEBITO	1	150000.00	2	TARJETA_DEBITO	\N
9	2	TARJETA_CREDITO	1	167000.00	3	TARJETA_CREDITO	\N
10	2	TRANSFERENCIA	1	1000000.00	4	TRANSFERENCIA	\N
11	2	CHEQUE	1	1000000.00	5	CHEQUE	\N
12	3	TARJETA_DEBITO	1	2000000.00	1	TARJETA_DEBITO	\N
13	3	TRANSFERENCIA	1	1000000.00	2	TRANSFERENCIA	\N
14	4	100000	10	1000000.00	1	EFECTIVO	\N
15	4	TARJETA_DEBITO	1	717000.00	2	TARJETA_DEBITO	\N
16	4	TRANSFERENCIA	1	1000000.00	3	TRANSFERENCIA	\N
\.


--
-- Data for Name: auditoria; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.auditoria (id, empresa_id, usuario_id, tabla_afectada, accion, registro_id, datos_anteriores, datos_nuevos, ip_address, user_agent, observaciones, fecha_evento) FROM stdin;
1	\N	\N	usuarios	INSERT	1	\N	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": null, "sucursal_id": 1, "firma_digital": null, "ultimo_acceso": null, "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-16T01:34:21.74548"}	\N	\N	\N	2026-01-16 01:34:21.74548
2	\N	\N	usuarios	INSERT	2	\N	{"ci": "7654321", "id": 2, "email": "maria@electronica.com.py", "estado": true, "nombre": "María", "rol_id": 3, "apellido": "González", "password": "$2a$06$nC1E.KABrqPt0ojj9d8bB.o7NzaDPkWWuVp2nylwJl.IMWJbyDg/C", "telefono": "(0982) 654-321", "username": "maria", "direccion": null, "empresa_id": 1, "foto_perfil": null, "sucursal_id": 1, "firma_digital": null, "ultimo_acceso": null, "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-16T01:34:21.74548"}	\N	\N	\N	2026-01-16 01:34:21.74548
3	\N	\N	usuarios	INSERT	3	\N	{"ci": "9876543", "id": 3, "email": "carlos@electronica.com.py", "estado": true, "nombre": "Carlos", "rol_id": 5, "apellido": "Rodríguez", "password": "$2a$06$1vqenScBJztwANFB22UOTOay/1QAE41k./M3MhzDvWCZperKiPSi.", "telefono": "(0983) 987-654", "username": "carlos", "direccion": null, "empresa_id": 1, "foto_perfil": null, "sucursal_id": 2, "firma_digital": null, "ultimo_acceso": null, "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-16T01:34:21.74548"}	\N	\N	\N	2026-01-16 01:34:21.74548
4	\N	\N	productos	INSERT	1	\N	{"id": 1, "peso": null, "tags": null, "codigo": "SM-G998", "estado": true, "moneda": "PYG", "nombre": "Samsung Galaxy S21 Ultra", "marca_id": 1, "tipo_iva": "GRAVADO_10", "usa_lote": false, "es_oferta": false, "usa_serie": false, "empresa_id": 1, "es_novedad": false, "descripcion": "Smartphone gama alta", "dimensiones": null, "categoria_id": 1, "codigo_barra": null, "es_destacado": false, "precio_venta": 3500000.00, "stock_maximo": 50, "stock_minimo": 5, "observaciones": null, "precio_compra": 2500000.00, "precio_minimo": null, "punto_reorden": 10, "tipo_producto": "PRODUCTO", "controla_stock": true, "fecha_creacion": "2026-01-16T01:34:21.74548", "garantia_meses": 0, "porcentaje_iva": null, "margen_utilidad": 40.00, "usa_vencimiento": false, "imagen_principal": null, "precio_mayorista": null, "unidad_medida_id": 1, "usuario_creacion": null, "codigo_fabricante": null, "descripcion_corta": null, "fecha_modificacion": "2026-01-16T01:34:21.74548", "permite_venta_sin_stock": false, "especificaciones_tecnicas": null}	\N	\N	\N	2026-01-16 01:34:21.74548
5	\N	\N	productos	INSERT	2	\N	{"id": 2, "peso": null, "tags": null, "codigo": "IPH-13", "estado": true, "moneda": "PYG", "nombre": "iPhone 13 Pro Max", "marca_id": 2, "tipo_iva": "GRAVADO_10", "usa_lote": false, "es_oferta": false, "usa_serie": false, "empresa_id": 1, "es_novedad": false, "descripcion": "iPhone 13 Pro Max 256GB", "dimensiones": null, "categoria_id": 1, "codigo_barra": null, "es_destacado": false, "precio_venta": 6500000.00, "stock_maximo": 30, "stock_minimo": 3, "observaciones": null, "precio_compra": 5000000.00, "precio_minimo": null, "punto_reorden": 5, "tipo_producto": "PRODUCTO", "controla_stock": true, "fecha_creacion": "2026-01-16T01:34:21.74548", "garantia_meses": 0, "porcentaje_iva": null, "margen_utilidad": 30.00, "usa_vencimiento": false, "imagen_principal": null, "precio_mayorista": null, "unidad_medida_id": 1, "usuario_creacion": null, "codigo_fabricante": null, "descripcion_corta": null, "fecha_modificacion": "2026-01-16T01:34:21.74548", "permite_venta_sin_stock": false, "especificaciones_tecnicas": null}	\N	\N	\N	2026-01-16 01:34:21.74548
6	\N	\N	productos	INSERT	3	\N	{"id": 3, "peso": null, "tags": null, "codigo": "SERV-001", "estado": true, "moneda": "PYG", "nombre": "Reparación pantalla", "marca_id": null, "tipo_iva": "GRAVADO_10", "usa_lote": false, "es_oferta": false, "usa_serie": false, "empresa_id": 1, "es_novedad": false, "descripcion": "Reemplazo de pantalla", "dimensiones": null, "categoria_id": 3, "codigo_barra": null, "es_destacado": false, "precio_venta": 150000.00, "stock_maximo": 0, "stock_minimo": 0, "observaciones": null, "precio_compra": 0.00, "precio_minimo": null, "punto_reorden": 0, "tipo_producto": "SERVICIO", "controla_stock": false, "fecha_creacion": "2026-01-16T01:34:21.74548", "garantia_meses": 0, "porcentaje_iva": null, "margen_utilidad": 0.00, "usa_vencimiento": false, "imagen_principal": null, "precio_mayorista": null, "unidad_medida_id": 10, "usuario_creacion": null, "codigo_fabricante": null, "descripcion_corta": null, "fecha_modificacion": "2026-01-16T01:34:21.74548", "permite_venta_sin_stock": false, "especificaciones_tecnicas": null}	\N	\N	\N	2026-01-16 01:34:21.74548
7	\N	\N	clientes	INSERT	1	\N	{"ci": null, "id": 1, "ruc": "80123456-1", "foto": null, "sexo": null, "email": "otra@empresa.com.py", "barrio": null, "ciudad": null, "codigo": "CLI-003", "estado": true, "nombre": "Empresa Ejemplo Dos S.A.", "celular": "(0983) 555-666", "apellido": null, "telefono": "(021) 555-666", "direccion": "Av. General Santos 123", "etiquetas": null, "empresa_id": 1, "departamento": null, "dias_credito": 0, "razon_social": "Empresa Ejemplo Dos S.A.", "tipo_cliente": "EMPRESA", "observaciones": null, "total_compras": 0.00, "fecha_creacion": "2026-01-16T01:34:21.74548", "limite_credito": 10000000.00, "notas_internas": null, "lista_precio_id": null, "nombre_fantasia": null, "cantidad_compras": 0, "fecha_nacimiento": null, "usuario_creacion": null, "categoria_cliente": "NORMAL", "credito_utilizado": 0.00, "credito_disponible": 10000000.00, "fecha_modificacion": "2026-01-16T01:34:21.74548", "tipo_contribuyente": null, "fecha_ultima_compra": null, "descuento_porcentaje": 0.00, "referencia_direccion": null, "vendedor_asignado_id": null}	\N	\N	\N	2026-01-16 01:34:21.74548
8	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": null, "sucursal_id": 1, "firma_digital": null, "ultimo_acceso": null, "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-16T01:34:21.74548"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": null, "sucursal_id": 1, "firma_digital": null, "ultimo_acceso": "2026-01-15T22:38:13", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-16T02:38:13.499612"}	\N	\N	\N	2026-01-16 02:38:13.499612
9	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36	\N	2026-01-15 22:38:13
472	1	1	usuarios	LOGOUT	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 OPR/126.0.0.0	\N	2026-02-09 14:57:24
10	\N	\N	productos	INSERT	4	\N	{"id": 4, "peso": null, "tags": null, "codigo": "lkjalfja", "estado": true, "moneda": "PYG", "nombre": "iPhone 13 Pro Max", "marca_id": 1, "tipo_iva": "GRAVADO_10", "usa_lote": false, "es_oferta": false, "usa_serie": false, "empresa_id": 1, "es_novedad": false, "descripcion": "asdasdadsa", "dimensiones": null, "categoria_id": 1, "codigo_barra": "jafdjajdio", "es_destacado": false, "precio_venta": 400000.00, "stock_maximo": 100, "stock_minimo": 5, "observaciones": null, "precio_compra": 40000.00, "precio_minimo": 4000.00, "punto_reorden": 4, "tipo_producto": "PRODUCTO", "controla_stock": true, "fecha_creacion": "2026-01-15T23:31:02", "garantia_meses": 0, "porcentaje_iva": 10.00, "margen_utilidad": 900.00, "usa_vencimiento": false, "imagen_principal": "1768530662_270fa593bc2dd6585ec6.jpg", "precio_mayorista": 4000.00, "unidad_medida_id": 1, "usuario_creacion": 1, "codigo_fabricante": null, "descripcion_corta": null, "fecha_modificacion": "2026-01-15T23:31:02", "permite_venta_sin_stock": false, "especificaciones_tecnicas": null}	\N	\N	\N	2026-01-16 03:31:02.106829
11	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": null, "sucursal_id": 1, "firma_digital": null, "ultimo_acceso": "2026-01-15T22:38:13", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-16T02:38:13.499612"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": null, "sucursal_id": 1, "firma_digital": null, "ultimo_acceso": "2026-01-16T09:16:45", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-16T13:16:45.879601"}	\N	\N	\N	2026-01-16 13:16:45.879601
12	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36	\N	2026-01-16 09:16:45
13	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": null, "sucursal_id": 1, "firma_digital": null, "ultimo_acceso": "2026-01-16T09:16:45", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-16T13:16:45.879601"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768568658_acbf85f3c8d6fa0f70b5.jpg", "sucursal_id": 1, "firma_digital": null, "ultimo_acceso": "2026-01-16T09:16:45", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-16T14:04:18.64259"}	\N	\N	\N	2026-01-16 14:04:18.64259
14	1	1	usuarios	UPDATE	1	\N	{"foto_perfil": "1768568658_acbf85f3c8d6fa0f70b5.jpg"}	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36	\N	2026-01-16 10:04:18
15	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768568658_acbf85f3c8d6fa0f70b5.jpg", "sucursal_id": 1, "preferencias": null, "firma_digital": null, "ultimo_acceso": "2026-01-16T09:16:45", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-16T14:04:18.64259"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768574478_06fd4c1c41f8150af07a.png", "sucursal_id": 1, "preferencias": null, "firma_digital": null, "ultimo_acceso": "2026-01-16T09:16:45", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-16T15:41:18.460375"}	\N	\N	\N	2026-01-16 15:41:18.460375
16	1	1	usuarios	UPDATE	1	\N	{"foto_perfil": "1768574478_06fd4c1c41f8150af07a.png"}	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36	\N	2026-01-16 11:41:18
17	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768574478_06fd4c1c41f8150af07a.png", "sucursal_id": 1, "preferencias": null, "firma_digital": null, "ultimo_acceso": "2026-01-16T09:16:45", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-16T15:41:18.460375"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768574478_06fd4c1c41f8150af07a.png", "sucursal_id": 1, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-16T09:16:45", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-16T15:41:23.861985"}	\N	\N	\N	2026-01-16 15:41:23.861985
18	1	1	usuarios	UPDATE	1	\N	{"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36	\N	2026-01-16 11:41:23
19	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768574478_06fd4c1c41f8150af07a.png", "sucursal_id": 1, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-16T09:16:45", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-16T15:41:23.861985"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768574497_4e5b0864a5ec7095d06f.png", "sucursal_id": 1, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-16T09:16:45", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-16T15:41:37.840217"}	\N	\N	\N	2026-01-16 15:41:37.840217
20	1	1	usuarios	UPDATE	1	\N	{"foto_perfil": "1768574497_4e5b0864a5ec7095d06f.png"}	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36	\N	2026-01-16 11:41:37
21	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768574497_4e5b0864a5ec7095d06f.png", "sucursal_id": 1, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-16T09:16:45", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-16T15:41:37.840217"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768574497_4e5b0864a5ec7095d06f.png", "sucursal_id": 1, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-16T09:16:45", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-16T15:41:45.885288"}	\N	\N	\N	2026-01-16 15:41:45.885288
22	1	1	usuarios	UPDATE	1	{"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}	{"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36	\N	2026-01-16 11:41:45
23	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768574497_4e5b0864a5ec7095d06f.png", "sucursal_id": 1, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-16T09:16:45", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-16T15:41:45.885288"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768574497_4e5b0864a5ec7095d06f.png", "sucursal_id": 1, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-16T09:16:45", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-16T15:42:02.614962"}	\N	\N	\N	2026-01-16 15:42:02.614962
24	1	1	usuarios	UPDATE	1	{"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}	{"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36	\N	2026-01-16 11:42:02
25	1	1	empresas	UPDATE	1	{"id": "1", "ruc": "80012345-1", "logo": null, "email": "ventas@electronica.com.py", "ciudad": "Asunción", "estado": "t", "nombre": "Electrónica Paraguay S.A.", "telefono": "(021) 123-456", "timbrado": "1234567", "direccion": "Av. República Argentina 123", "aplica_iva": "t", "fecha_creacion": "2026-01-16 01:34:21.74548", "porcentaje_iva": "10.00", "establecimiento": "001", "moneda_principal": "PYG", "punto_expedicion": "001", "usuario_creacion": null, "dias_plazo_retiro": "90", "politica_garantia": null, "fecha_modificacion": "2026-01-16 01:34:21.74548", "protector_pantalla": null, "terminos_condiciones": null, "usa_facturacion_electronica": "f"}	{"ruc": "80027345-1", "logo": "1768575782_70d442d35457be4fa688.png", "email": "ventas@electronica.com.py", "ciudad": "Asunción", "nombre": "Electrónica Paraguay S.A.", "telefono": "(021) 123-456", "timbrado": "1234567", "direccion": "Av. República Argentina 123", "aplica_iva": true, "porcentaje_iva": "10.00", "establecimiento": "001", "moneda_principal": "PYG", "punto_expedicion": "001", "dias_plazo_retiro": "90"}	::1	\N	\N	2026-01-16 12:03:02
27	\N	\N	productos	INSERT	5	\N	{"id": 5, "peso": null, "tags": null, "codigo": "001", "estado": true, "moneda": "PYG", "nombre": "Samsung Gala", "marca_id": 1, "tipo_iva": "GRAVADO_10", "usa_lote": false, "es_oferta": false, "usa_serie": false, "empresa_id": 1, "es_novedad": false, "descripcion": "sfsfsfsfddd", "dimensiones": null, "categoria_id": 1, "codigo_barra": "dgh", "es_destacado": false, "precio_venta": 10000.00, "stock_maximo": 50, "stock_minimo": 5, "observaciones": null, "precio_compra": 10000.00, "precio_minimo": 10000.00, "punto_reorden": 10, "tipo_producto": "PRODUCTO", "controla_stock": true, "fecha_creacion": "2026-01-16T14:19:24", "garantia_meses": 0, "porcentaje_iva": 10.00, "margen_utilidad": 0.00, "usa_vencimiento": false, "imagen_principal": "1768583964_420a7e0282cac39cc7bb.jpg", "precio_mayorista": 10000.00, "unidad_medida_id": 1, "usuario_creacion": 1, "codigo_fabricante": null, "descripcion_corta": null, "fecha_modificacion": "2026-01-16T14:19:24", "permite_venta_sin_stock": false, "especificaciones_tecnicas": null}	\N	\N	\N	2026-01-16 18:19:24.26827
28	\N	\N	productos	INSERT	6	\N	{"id": 6, "peso": null, "tags": null, "codigo": "0012", "estado": true, "moneda": "PYG", "nombre": "Samsung Gala", "marca_id": 1, "tipo_iva": "GRAVADO_10", "usa_lote": false, "es_oferta": false, "usa_serie": false, "empresa_id": 1, "es_novedad": false, "descripcion": "sfsfsfsfddd", "dimensiones": null, "categoria_id": 1, "codigo_barra": "dgh", "es_destacado": false, "precio_venta": 10000.00, "stock_maximo": 50, "stock_minimo": 5, "observaciones": null, "precio_compra": 10000.00, "precio_minimo": 10000.00, "punto_reorden": 10, "tipo_producto": "PRODUCTO", "controla_stock": true, "fecha_creacion": "2026-01-16T14:20:32", "garantia_meses": 0, "porcentaje_iva": 10.00, "margen_utilidad": 0.00, "usa_vencimiento": false, "imagen_principal": "1768584032_582e004bd9c82d0bb2ae.png", "precio_mayorista": 10000.00, "unidad_medida_id": 1, "usuario_creacion": 1, "codigo_fabricante": null, "descripcion_corta": null, "fecha_modificacion": "2026-01-16T14:20:32", "permite_venta_sin_stock": false, "especificaciones_tecnicas": null}	\N	\N	\N	2026-01-16 18:20:32.329063
30	\N	\N	clientes	UPDATE	1	{"ci": null, "id": 1, "ruc": "80123456-1", "foto": null, "sexo": null, "email": "otra@empresa.com.py", "barrio": null, "ciudad": null, "codigo": "CLI-003", "estado": true, "nombre": "Empresa Ejemplo Dos S.A.", "celular": "(0983) 555-666", "apellido": null, "telefono": "(021) 555-666", "direccion": "Av. General Santos 123", "etiquetas": null, "empresa_id": 1, "departamento": null, "dias_credito": 0, "razon_social": "Empresa Ejemplo Dos S.A.", "tipo_cliente": "EMPRESA", "observaciones": null, "total_compras": 0.00, "fecha_creacion": "2026-01-16T01:34:21.74548", "limite_credito": 10000000.00, "notas_internas": null, "lista_precio_id": null, "nombre_fantasia": null, "cantidad_compras": 0, "fecha_nacimiento": null, "usuario_creacion": null, "categoria_cliente": "NORMAL", "credito_utilizado": 0.00, "credito_disponible": 10000000.00, "fecha_modificacion": "2026-01-16T01:34:21.74548", "tipo_contribuyente": null, "fecha_ultima_compra": null, "descuento_porcentaje": 0.00, "referencia_direccion": null, "vendedor_asignado_id": null}	{"ci": null, "id": 1, "ruc": "80123456-1", "foto": null, "sexo": null, "email": "otra@empresa.com.py", "barrio": null, "ciudad": null, "codigo": "CLI-003", "estado": true, "nombre": "Empresa Ejemplo Dos S.A.", "celular": "(0983) 555-666", "apellido": null, "telefono": "(021) 555-666", "direccion": "Av. General Santos 123", "etiquetas": null, "empresa_id": 1, "departamento": null, "dias_credito": 0, "razon_social": "Empresa Ejemplo Dos S.A.", "tipo_cliente": "EMPRESA", "observaciones": null, "total_compras": 0.00, "fecha_creacion": "2026-01-16T01:34:21.74548", "limite_credito": 10000000.00, "notas_internas": null, "lista_precio_id": null, "nombre_fantasia": null, "cantidad_compras": 0, "fecha_nacimiento": null, "usuario_creacion": null, "categoria_cliente": "VIP", "credito_utilizado": 0.00, "credito_disponible": 10000000.00, "fecha_modificacion": "2026-01-16T19:40:30.58784", "tipo_contribuyente": "CONTRIBUYENTE", "fecha_ultima_compra": null, "descuento_porcentaje": 0.00, "referencia_direccion": null, "vendedor_asignado_id": null}	\N	\N	\N	2026-01-16 19:40:30.58784
96	2	1	empresas	DELETE	2	{"id": "2", "ruc": "11156766-6", "logo": "1768598459_a7d67cc9bad19fa1bce0.jpg", "email": "albert.salo.654@gmail.com", "ciudad": "Asunción", "estado": "f", "nombre": "Arquisoft", "telefono": "(021) 555-666", "timbrado": "1234567", "direccion": "Av. República Argentina 123", "aplica_iva": "t", "fecha_creacion": "2026-01-16 22:20:59.285386", "porcentaje_iva": "10.00", "establecimiento": "1", "moneda_principal": "PYG", "punto_expedicion": "1", "usuario_creacion": "1", "dias_plazo_retiro": "90", "politica_garantia": null, "fecha_modificacion": "2026-01-18 03:13:56.015165", "protector_pantalla": null, "terminos_condiciones": null, "usa_facturacion_electronica": "f"}	\N	::1	\N	\N	2026-01-17 23:17:20
31	1	1	clientes	UPDATE	1	{"ci": null, "id": "1", "ruc": "80123456-1", "foto": null, "sexo": null, "email": "otra@empresa.com.py", "barrio": null, "ciudad": null, "codigo": "CLI-003", "estado": "t", "nombre": "Empresa Ejemplo Dos S.A.", "celular": "(0983) 555-666", "apellido": null, "telefono": "(021) 555-666", "direccion": "Av. General Santos 123", "etiquetas": null, "empresa_id": "1", "departamento": null, "dias_credito": "0", "razon_social": "Empresa Ejemplo Dos S.A.", "tipo_cliente": "EMPRESA", "observaciones": null, "total_compras": "0.00", "fecha_creacion": "2026-01-16 01:34:21.74548", "limite_credito": "10000000.00", "notas_internas": null, "lista_precio_id": null, "nombre_fantasia": null, "cantidad_compras": "0", "fecha_nacimiento": null, "usuario_creacion": null, "categoria_cliente": "NORMAL", "credito_utilizado": "0.00", "credito_disponible": "10000000.00", "fecha_modificacion": "2026-01-16 01:34:21.74548", "tipo_contribuyente": null, "fecha_ultima_compra": null, "descuento_porcentaje": "0.00", "referencia_direccion": null, "vendedor_asignado_id": null}	{"ci": null, "ruc": "80123456-1", "sexo": null, "email": "otra@empresa.com.py", "barrio": null, "ciudad": null, "nombre": "Empresa Ejemplo Dos S.A.", "celular": "(0983) 555-666", "apellido": null, "telefono": "(021) 555-666", "direccion": "Av. General Santos 123", "departamento": null, "dias_credito": 0, "razon_social": "Empresa Ejemplo Dos S.A.", "tipo_cliente": "EMPRESA", "observaciones": null, "limite_credito": 10000000, "lista_precio_id": null, "nombre_fantasia": null, "fecha_nacimiento": null, "categoria_cliente": "VIP", "tipo_contribuyente": "CONTRIBUYENTE", "descuento_porcentaje": 0}	::1	\N	\N	2026-01-16 19:40:30.60744
32	\N	\N	clientes	UPDATE	1	{"ci": null, "id": 1, "ruc": "80123456-1", "foto": null, "sexo": null, "email": "otra@empresa.com.py", "barrio": null, "ciudad": null, "codigo": "CLI-003", "estado": true, "nombre": "Empresa Ejemplo Dos S.A.", "celular": "(0983) 555-666", "apellido": null, "telefono": "(021) 555-666", "direccion": "Av. General Santos 123", "etiquetas": null, "empresa_id": 1, "departamento": null, "dias_credito": 0, "razon_social": "Empresa Ejemplo Dos S.A.", "tipo_cliente": "EMPRESA", "observaciones": null, "total_compras": 0.00, "fecha_creacion": "2026-01-16T01:34:21.74548", "limite_credito": 10000000.00, "notas_internas": null, "lista_precio_id": null, "nombre_fantasia": null, "cantidad_compras": 0, "fecha_nacimiento": null, "usuario_creacion": null, "categoria_cliente": "VIP", "credito_utilizado": 0.00, "credito_disponible": 10000000.00, "fecha_modificacion": "2026-01-16T19:40:30.58784", "tipo_contribuyente": "CONTRIBUYENTE", "fecha_ultima_compra": null, "descuento_porcentaje": 0.00, "referencia_direccion": null, "vendedor_asignado_id": null}	{"ci": null, "id": 1, "ruc": "80123456-1", "foto": null, "sexo": null, "email": "otra@empresa.com.py", "barrio": null, "ciudad": null, "codigo": "CLI-003", "estado": true, "nombre": "Empresa Ejemplo Dos S.A.", "celular": "(0983) 555-666", "apellido": null, "telefono": "(021) 555-666", "direccion": "Av. General Santos 123", "etiquetas": null, "empresa_id": 1, "departamento": null, "dias_credito": 0, "razon_social": "Empresa Ejemplo Dos S.A.", "tipo_cliente": "EMPRESA", "observaciones": null, "total_compras": 0.00, "fecha_creacion": "2026-01-16T01:34:21.74548", "limite_credito": 10000000.00, "notas_internas": null, "lista_precio_id": null, "nombre_fantasia": null, "cantidad_compras": 0, "fecha_nacimiento": null, "usuario_creacion": null, "categoria_cliente": "VIP", "credito_utilizado": 0.00, "credito_disponible": 10000000.00, "fecha_modificacion": "2026-01-16T19:51:20.214061", "tipo_contribuyente": "CONTRIBUYENTE", "fecha_ultima_compra": null, "descuento_porcentaje": 0.00, "referencia_direccion": null, "vendedor_asignado_id": null}	\N	\N	\N	2026-01-16 19:51:20.214061
33	1	1	clientes	UPDATE	1	{"ci": null, "id": "1", "ruc": "80123456-1", "foto": null, "sexo": null, "email": "otra@empresa.com.py", "barrio": null, "ciudad": null, "codigo": "CLI-003", "estado": "t", "nombre": "Empresa Ejemplo Dos S.A.", "celular": "(0983) 555-666", "apellido": null, "telefono": "(021) 555-666", "direccion": "Av. General Santos 123", "etiquetas": null, "empresa_id": "1", "departamento": null, "dias_credito": "0", "razon_social": "Empresa Ejemplo Dos S.A.", "tipo_cliente": "EMPRESA", "observaciones": null, "total_compras": "0.00", "fecha_creacion": "2026-01-16 01:34:21.74548", "limite_credito": "10000000.00", "notas_internas": null, "lista_precio_id": null, "nombre_fantasia": null, "cantidad_compras": "0", "fecha_nacimiento": null, "usuario_creacion": null, "categoria_cliente": "VIP", "credito_utilizado": "0.00", "credito_disponible": "10000000.00", "fecha_modificacion": "2026-01-16 19:40:30.58784", "tipo_contribuyente": "CONTRIBUYENTE", "fecha_ultima_compra": null, "descuento_porcentaje": "0.00", "referencia_direccion": null, "vendedor_asignado_id": null}	{"ci": null, "ruc": "80123456-1", "sexo": null, "email": "otra@empresa.com.py", "barrio": null, "ciudad": null, "nombre": "Empresa Ejemplo Dos S.A.", "celular": "(0983) 555-666", "apellido": null, "telefono": "(021) 555-666", "direccion": "Av. General Santos 123", "departamento": null, "dias_credito": 0, "razon_social": "Empresa Ejemplo Dos S.A.", "tipo_cliente": "EMPRESA", "observaciones": null, "limite_credito": 10000000, "lista_precio_id": null, "nombre_fantasia": null, "fecha_nacimiento": null, "categoria_cliente": "VIP", "tipo_contribuyente": "CONTRIBUYENTE", "descuento_porcentaje": 0}	::1	\N	\N	2026-01-16 19:51:20.229305
34	\N	\N	ordenes_servicio	INSERT	1	\N	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 1, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 0.00, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 0.00, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 0.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 0.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-16T16:56:13", "problema_reportado": "cvbnm,", "costo_real_subtotal": 0.00, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 0.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 0.00, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 0.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	\N	\N	\N	2026-01-16 20:56:13.304637
35	\N	\N	usuarios	UPDATE	3	{"ci": "9876543", "id": 3, "email": "carlos@electronica.com.py", "estado": true, "nombre": "Carlos", "rol_id": 5, "apellido": "Rodríguez", "password": "$2a$06$1vqenScBJztwANFB22UOTOay/1QAE41k./M3MhzDvWCZperKiPSi.", "telefono": "(0983) 987-654", "username": "carlos", "direccion": null, "empresa_id": 1, "foto_perfil": null, "sucursal_id": 2, "preferencias": null, "firma_digital": null, "ultimo_acceso": null, "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-16T01:34:21.74548"}	{"ci": "9876543", "id": 3, "email": "carlos@electronica.com.py", "estado": true, "nombre": "Carlos", "rol_id": 5, "apellido": "Rodríguez", "password": "$2a$06$1vqenScBJztwANFB22UOTOay/1QAE41k./M3MhzDvWCZperKiPSi.", "telefono": "(0983) 987-654", "username": "carlos", "direccion": "ww", "empresa_id": 1, "foto_perfil": null, "sucursal_id": 2, "preferencias": null, "firma_digital": null, "ultimo_acceso": null, "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-16T21:28:55.121913"}	\N	\N	\N	2026-01-16 21:28:55.121913
36	1	1	usuarios	UPDATE	3	{"ci": "9876543", "id": "3", "email": "carlos@electronica.com.py", "estado": "t", "nombre": "Carlos", "rol_id": "5", "apellido": "Rodríguez", "password": "$2a$06$1vqenScBJztwANFB22UOTOay/1QAE41k./M3MhzDvWCZperKiPSi.", "telefono": "(0983) 987-654", "username": "carlos", "direccion": null, "empresa_id": "1", "foto_perfil": null, "sucursal_id": "2", "preferencias": null, "firma_digital": null, "ultimo_acceso": null, "fecha_creacion": "2026-01-16 01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-16 01:34:21.74548"}	{"ci": "9876543", "email": "carlos@electronica.com.py", "nombre": "Carlos", "rol_id": 5, "apellido": "Rodríguez", "telefono": "(0983) 987-654", "username": "carlos", "direccion": "ww", "empresa_id": 1, "sucursal_id": 2}	::1	\N	\N	2026-01-16 17:28:55
37	\N	\N	usuarios	UPDATE	3	{"ci": "9876543", "id": 3, "email": "carlos@electronica.com.py", "estado": true, "nombre": "Carlos", "rol_id": 5, "apellido": "Rodríguez", "password": "$2a$06$1vqenScBJztwANFB22UOTOay/1QAE41k./M3MhzDvWCZperKiPSi.", "telefono": "(0983) 987-654", "username": "carlos", "direccion": "ww", "empresa_id": 1, "foto_perfil": null, "sucursal_id": 2, "preferencias": null, "firma_digital": null, "ultimo_acceso": null, "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-16T21:28:55.121913"}	{"ci": "9876543", "id": 3, "email": "carlos@electronica.com.py", "estado": true, "nombre": "Carlos", "rol_id": 5, "apellido": "Rodríguez", "password": "$2a$06$1vqenScBJztwANFB22UOTOay/1QAE41k./M3MhzDvWCZperKiPSi.", "telefono": "(0983) 987-654", "username": "carlos", "direccion": "ww", "empresa_id": 1, "foto_perfil": "1768595479_26979c22f1826ff4a2c8.jpeg", "sucursal_id": 2, "preferencias": null, "firma_digital": null, "ultimo_acceso": null, "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-16T21:31:19.037993"}	\N	\N	\N	2026-01-16 21:31:19.037993
38	1	1	usuarios	UPDATE	3	{"ci": "9876543", "id": "3", "email": "carlos@electronica.com.py", "estado": "t", "nombre": "Carlos", "rol_id": "5", "apellido": "Rodríguez", "password": "$2a$06$1vqenScBJztwANFB22UOTOay/1QAE41k./M3MhzDvWCZperKiPSi.", "telefono": "(0983) 987-654", "username": "carlos", "direccion": "ww", "empresa_id": "1", "foto_perfil": null, "sucursal_id": "2", "preferencias": null, "firma_digital": null, "ultimo_acceso": null, "fecha_creacion": "2026-01-16 01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-16 21:28:55.121913"}	{"ci": "9876543", "email": "carlos@electronica.com.py", "nombre": "Carlos", "rol_id": 5, "apellido": "Rodríguez", "telefono": "(0983) 987-654", "username": "carlos", "direccion": "ww", "empresa_id": 1, "foto_perfil": "1768595479_26979c22f1826ff4a2c8.jpeg", "sucursal_id": 2}	::1	\N	\N	2026-01-16 17:31:19
39	\N	\N	usuarios	INSERT	4	\N	{"ci": "5355147", "id": 4, "email": "manager@demo.com", "estado": true, "nombre": "datemex", "rol_id": 4, "apellido": "Rodríguez", "password": "$2y$10$HQimm97SJkyjyx.bS0psyOa7HCqmc4f9S6PIYSTFeGcsYgZkIc5TC", "telefono": "(0983) 987-654", "username": "manager", "direccion": "erty", "empresa_id": 1, "foto_perfil": "1768596576_582b077595d4de164353.jpeg", "sucursal_id": 2, "preferencias": null, "firma_digital": null, "ultimo_acceso": null, "fecha_creacion": "2026-01-16T21:49:36.816209", "usuario_creacion": 1, "fecha_modificacion": "2026-01-16T21:49:36.816209"}	\N	\N	\N	2026-01-16 21:49:36.816209
40	1	1	usuarios	INSERT	\N	\N	{"ci": "5355147", "email": "manager@demo.com", "estado": true, "nombre": "datemex", "rol_id": 4, "apellido": "Rodríguez", "password": "@cabron654", "telefono": "(0983) 987-654", "username": "manager", "direccion": "erty", "empresa_id": 1, "foto_perfil": "1768596576_582b077595d4de164353.jpeg", "sucursal_id": 2, "preferencias": null, "firma_digital": null, "ultimo_acceso": null, "usuario_creacion": 1}	::1	\N	\N	2026-01-16 17:49:36
41	1	1	empresas	UPDATE	1	{"id": "1", "ruc": "80027345-1", "logo": "1768575782_70d442d35457be4fa688.png", "email": "ventas@electronica.com.py", "ciudad": "Asunción", "estado": "t", "nombre": "Electrónica Paraguay S.A.", "telefono": "(021) 123-456", "timbrado": "1234567", "direccion": "Av. República Argentina 123", "aplica_iva": "t", "fecha_creacion": "2026-01-16 01:34:21.74548", "porcentaje_iva": "10.00", "establecimiento": "001", "moneda_principal": "PYG", "punto_expedicion": "001", "usuario_creacion": null, "dias_plazo_retiro": "90", "politica_garantia": null, "fecha_modificacion": "2026-01-16 16:03:02.143646", "protector_pantalla": null, "terminos_condiciones": null, "usa_facturacion_electronica": "f"}	{"ruc": "80027345-1", "email": "compusur@gmail.com.py", "ciudad": "Nemby", "nombre": "Compusur py", "telefono": "(021) 123-456", "timbrado": 1234567, "direccion": "ssjs", "aplica_iva": true, "porcentaje_iva": 10, "establecimiento": 1, "moneda_principal": "PYG", "punto_expedicion": 1, "dias_plazo_retiro": 30, "politica_garantia": null, "terminos_condiciones": null, "usa_facturacion_electronica": false}	::1	\N	\N	2026-01-16 18:02:42
42	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768574497_4e5b0864a5ec7095d06f.png", "sucursal_id": 1, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-16T09:16:45", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-16T15:42:02.614962"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768597896_d46b5713018d4d84f2fa.jpg", "sucursal_id": 1, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-16T09:16:45", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-16T22:11:36.864073"}	\N	\N	\N	2026-01-16 22:11:36.864073
43	1	1	usuarios	UPDATE	1	\N	{"foto_perfil": "1768597896_d46b5713018d4d84f2fa.jpg"}	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36	\N	2026-01-16 18:11:36
44	2	1	empresas	INSERT	\N	\N	{"ruc": "11156766-6", "logo": "1768598459_a7d67cc9bad19fa1bce0.jpg", "email": "albert.salo.654@gmail.com", "ciudad": "Asunción", "nombre": "Arquisoft", "telefono": "(021) 555-666", "timbrado": 1234567, "direccion": "Av. República Argentina 123", "aplica_iva": true, "porcentaje_iva": 10, "establecimiento": 1, "moneda_principal": "PYG", "punto_expedicion": 1, "usuario_creacion": 1, "dias_plazo_retiro": 90, "politica_garantia": null, "terminos_condiciones": null, "usa_facturacion_electronica": false}	::1	\N	\N	2026-01-16 18:20:59
45	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768597896_d46b5713018d4d84f2fa.jpg", "sucursal_id": 1, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-16T09:16:45", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-16T22:11:36.864073"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768597896_d46b5713018d4d84f2fa.jpg", "sucursal_id": 1, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-16T20:39:15", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-17T00:39:15.378995"}	\N	\N	\N	2026-01-17 00:39:15.378995
46	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36	\N	2026-01-16 20:39:15
307	\N	\N	clientes	UPDATE	2	{"ci": "5355147", "id": 2, "ruc": null, "foto": "1768654934_f80381ca6f5f78a5aec8.jpg", "sexo": "M", "email": "albert.salo.654@gmail.com", "barrio": null, "ciudad": "Asunción", "codigo": "CLI-000004", "estado": true, "nombre": "Datamex", "celular": "0727784415", "apellido": "comermex", "telefono": "(021) 555-666", "direccion": "Av. República Argentina 123", "etiquetas": null, "empresa_id": 1, "departamento": "CAPITAL", "dias_credito": 0, "razon_social": null, "tipo_cliente": "PERSONA", "observaciones": "sdfghj", "total_compras": 0.00, "fecha_creacion": "2026-01-17T10:02:14", "limite_credito": 1000000000.00, "notas_internas": null, "lista_precio_id": 1, "nombre_fantasia": null, "cantidad_compras": 0, "fecha_nacimiento": "2003-11-06", "usuario_creacion": 1, "categoria_cliente": "NORMAL", "credito_utilizado": 0.00, "credito_disponible": 1000000000.00, "fecha_modificacion": "2026-01-18T17:57:32.032632", "tipo_contribuyente": "CONTRIBUYENTE", "fecha_ultima_compra": null, "descuento_porcentaje": 0.00, "referencia_direccion": null, "vendedor_asignado_id": null}	{"ci": "5355147", "id": 2, "ruc": null, "foto": "1768654934_f80381ca6f5f78a5aec8.jpg", "sexo": "M", "email": "albert.salo.654@gmail.com", "barrio": null, "ciudad": "Asunción", "codigo": "CLI-000004", "estado": true, "nombre": "Datamex", "celular": "0727784415", "apellido": "comermex", "telefono": "(021) 555-666", "direccion": "Av. República Argentina 123", "etiquetas": null, "empresa_id": 1, "departamento": "CAPITAL", "dias_credito": 0, "razon_social": null, "tipo_cliente": "PERSONA", "observaciones": "sdfghj", "total_compras": 0.00, "fecha_creacion": "2026-01-17T10:02:14", "limite_credito": 1000000000.00, "notas_internas": null, "lista_precio_id": 1, "nombre_fantasia": null, "cantidad_compras": 0, "fecha_nacimiento": "2003-11-06", "usuario_creacion": 1, "categoria_cliente": "NORMAL", "credito_utilizado": 0.00, "credito_disponible": 1000000000.00, "fecha_modificacion": "2026-01-22T01:23:32.457175", "tipo_contribuyente": "CONTRIBUYENTE", "fecha_ultima_compra": null, "descuento_porcentaje": 0.00, "referencia_direccion": null, "vendedor_asignado_id": null}	\N	\N	\N	2026-01-22 01:23:32.457175
53	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768597896_d46b5713018d4d84f2fa.jpg", "sucursal_id": 1, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-16T20:39:15", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-17T00:39:15.378995"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768597896_d46b5713018d4d84f2fa.jpg", "sucursal_id": 1, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-17T09:26:00", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-17T13:26:00.621317"}	\N	\N	\N	2026-01-17 13:26:00.621317
54	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36	\N	2026-01-17 09:26:00
55	\N	\N	clientes	INSERT	2	\N	{"ci": "5355147", "id": 2, "ruc": null, "foto": "1768654934_f80381ca6f5f78a5aec8.jpg", "sexo": "M", "email": "albert.salo.654@gmail.com", "barrio": null, "ciudad": "Asunción", "codigo": "CLI-000004", "estado": true, "nombre": "Datamex", "celular": "0727784415", "apellido": "comermex", "telefono": "(021) 555-666", "direccion": "Av. República Argentina 123", "etiquetas": null, "empresa_id": 1, "departamento": "CAPITAL", "dias_credito": 0, "razon_social": null, "tipo_cliente": "PERSONA", "observaciones": "sdfghj", "total_compras": 0.00, "fecha_creacion": "2026-01-17T10:02:14", "limite_credito": 0.00, "notas_internas": null, "lista_precio_id": 1, "nombre_fantasia": null, "cantidad_compras": 0, "fecha_nacimiento": "2003-11-06", "usuario_creacion": 1, "categoria_cliente": "NORMAL", "credito_utilizado": 0.00, "credito_disponible": 0.00, "fecha_modificacion": "2026-01-17T10:02:14", "tipo_contribuyente": "CONTRIBUYENTE", "fecha_ultima_compra": null, "descuento_porcentaje": 0.00, "referencia_direccion": null, "vendedor_asignado_id": null}	\N	\N	\N	2026-01-17 14:02:14.589228
56	1	1	clientes	INSERT	55	\N	{"ci": "5355147", "ruc": null, "foto": "1768654934_f80381ca6f5f78a5aec8.jpg", "sexo": "M", "email": "albert.salo.654@gmail.com", "barrio": null, "ciudad": "Asunción", "codigo": "CLI-000004", "estado": true, "nombre": "Datamex", "celular": "0727784415", "apellido": "comermex", "telefono": "(021) 555-666", "direccion": "Av. República Argentina 123", "empresa_id": 1, "departamento": "CAPITAL", "dias_credito": 0, "razon_social": null, "tipo_cliente": "PERSONA", "observaciones": "sdfghj", "limite_credito": 0, "lista_precio_id": 1, "nombre_fantasia": null, "fecha_nacimiento": "2003-11-06", "usuario_creacion": 1, "categoria_cliente": "NORMAL", "tipo_contribuyente": "CONTRIBUYENTE", "descuento_porcentaje": 0}	::1	\N	\N	2026-01-17 14:02:14.619445
97	1	1	empresas	UPDATE	1	{"id": "1", "ruc": "80027345-1", "logo": "1768575782_70d442d35457be4fa688.png", "email": "compusur@gmail.com.py", "ciudad": "Nemby", "estado": "t", "nombre": "Compusur py", "telefono": "(021) 123-456", "timbrado": "1234567", "direccion": "ssjs", "aplica_iva": "t", "fecha_creacion": "2026-01-16 01:34:21.74548", "porcentaje_iva": "10.00", "establecimiento": "1", "moneda_principal": "PYG", "punto_expedicion": "1", "usuario_creacion": null, "dias_plazo_retiro": "30", "politica_garantia": null, "fecha_modificacion": "2026-01-16 22:02:42.658284", "protector_pantalla": null, "terminos_condiciones": null, "usa_facturacion_electronica": "f"}	{"ruc": "80027345-1", "logo": "1768702763_fd651597cdf06d12b10e.jpg", "email": "Arquisoft@gmail.com.py", "ciudad": "capiata", "nombre": "Arquisoft", "telefono": "(021) 123-456", "timbrado": 1234567, "direccion": "ssjs", "aplica_iva": true, "porcentaje_iva": 10, "establecimiento": 1, "moneda_principal": "PYG", "punto_expedicion": 1, "dias_plazo_retiro": 30, "politica_garantia": null, "terminos_condiciones": null, "usa_facturacion_electronica": false}	::1	\N	\N	2026-01-17 23:19:23
98	2	1	empresas	UPDATE	2	{"id": "2", "ruc": "11156766-6", "logo": "1768598459_a7d67cc9bad19fa1bce0.jpg", "email": "albert.salo.654@gmail.com", "ciudad": "Asunción", "estado": "f", "nombre": "Arquisoft", "telefono": "(021) 555-666", "timbrado": "1234567", "direccion": "Av. República Argentina 123", "aplica_iva": "t", "fecha_creacion": "2026-01-16 22:20:59.285386", "porcentaje_iva": "10.00", "establecimiento": "1", "moneda_principal": "PYG", "punto_expedicion": "1", "usuario_creacion": "1", "dias_plazo_retiro": "90", "politica_garantia": null, "fecha_modificacion": "2026-01-18 03:17:20.111886", "protector_pantalla": null, "terminos_condiciones": null, "usa_facturacion_electronica": "f"}	{"ruc": "11156766-6", "email": "albert.salo.654@gmail.com", "ciudad": "Asunción", "nombre": "xxx", "telefono": "(021) 555-666", "timbrado": 1234567, "direccion": "Av. República Argentina 123", "aplica_iva": true, "porcentaje_iva": 10, "establecimiento": 1, "moneda_principal": "PYG", "punto_expedicion": 1, "dias_plazo_retiro": 90, "politica_garantia": null, "terminos_condiciones": null, "usa_facturacion_electronica": false}	::1	\N	\N	2026-01-17 23:19:45
72	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768597896_d46b5713018d4d84f2fa.jpg", "sucursal_id": 1, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-17T09:26:00", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-17T13:26:00.621317"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768597896_d46b5713018d4d84f2fa.jpg", "sucursal_id": 1, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-17T12:00:56", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-17T16:00:56.60658"}	\N	\N	\N	2026-01-17 16:00:56.60658
73	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Cursor/2.3.40 Chrome/138.0.7204.251 Electron/37.7.0 Safari/537.36	\N	2026-01-17 12:00:56
74	1	1	usuarios	LOGOUT	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36	\N	2026-01-17 12:13:13
75	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768597896_d46b5713018d4d84f2fa.jpg", "sucursal_id": 1, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-17T12:00:56", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-17T16:00:56.60658"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768597896_d46b5713018d4d84f2fa.jpg", "sucursal_id": 1, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-17T12:15:38", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-17T16:15:38.25763"}	\N	\N	\N	2026-01-17 16:15:38.25763
76	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 OPR/126.0.0.0	\N	2026-01-17 12:15:38
79	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768597896_d46b5713018d4d84f2fa.jpg", "sucursal_id": 1, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-17T12:15:38", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-17T16:15:38.25763"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768597896_d46b5713018d4d84f2fa.jpg", "sucursal_id": 1, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-17T20:10:29", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-18T00:10:29.25558"}	\N	\N	\N	2026-01-18 00:10:29.25558
80	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Cursor/2.3.41 Chrome/138.0.7204.251 Electron/37.7.0 Safari/537.36	\N	2026-01-17 20:10:29
81	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768597896_d46b5713018d4d84f2fa.jpg", "sucursal_id": 1, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-17T20:10:29", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-18T00:10:29.25558"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768597896_d46b5713018d4d84f2fa.jpg", "sucursal_id": 1, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-17T20:11:31", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-18T00:11:31.445092"}	\N	\N	\N	2026-01-18 00:11:31.445092
82	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 OPR/126.0.0.0	\N	2026-01-17 20:11:31
83	\N	\N	ventas	INSERT	21	\N	{"id": 21, "iva_5": 0.00, "total": 6586300.00, "estado": "PENDIENTE", "iva_10": 598754.55, "moneda": "PYG", "vuelto": 0.00, "subtotal": 6586300.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 598754.55, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-17T20:12:30", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000001", "origen_venta": "SUCURSAL", "observaciones": "", "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-18T00:12:30.415711", "notas_internas": null, "numero_factura": null, "descuento_monto": 203700.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": 1, "monto_pendiente": 6586300.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-01-18T00:12:30.415711", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 6586300.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": 6586300.00, "fecha_entrega_programada": null}	\N	\N	\N	2026-01-18 00:12:30.415711
84	\N	\N	ventas	UPDATE	21	{"id": 21, "iva_5": 0.00, "total": 6586300.00, "estado": "PENDIENTE", "iva_10": 598754.55, "moneda": "PYG", "vuelto": 0.00, "subtotal": 6586300.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 598754.55, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-17T20:12:30", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000001", "origen_venta": "SUCURSAL", "observaciones": "", "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-18T00:12:30.415711", "notas_internas": null, "numero_factura": null, "descuento_monto": 203700.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": 1, "monto_pendiente": 6586300.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-01-18T00:12:30.415711", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 6586300.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": 6586300.00, "fecha_entrega_programada": null}	{"id": 21, "iva_5": 0.00, "total": 6586300.00, "estado": "COMPLETADA", "iva_10": 598754.55, "moneda": "PYG", "vuelto": 598755.00, "subtotal": 6586300.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 598754.55, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-17T20:12:30", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 7185055.00, "numero_venta": "VTA-00000001", "origen_venta": "SUCURSAL", "observaciones": "", "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-18T00:12:30.415711", "notas_internas": null, "numero_factura": null, "descuento_monto": 203700.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": 1, "monto_pendiente": -598755.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-01-18T00:12:30.415711", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 6586300.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": 6586300.00, "fecha_entrega_programada": null}	\N	\N	\N	2026-01-18 00:12:30.415711
85	1	1	ventas	INSERT	21	\N	{"iva_5": 0, "total": 6586300, "estado": "PENDIENTE", "iva_10": 598754.55, "moneda": "PYG", "subtotal": 6586300, "tipo_pago": "CONTADO", "total_iva": 598754.55, "cliente_id": null, "empresa_id": "1", "tipo_venta": "PRODUCTO", "usuario_id": "1", "fecha_venta": "2026-01-17 20:12:30", "sucursal_id": "1", "tipo_cambio": 1, "vendedor_id": "1", "monto_pagado": 0, "numero_venta": "VTA-00000001", "observaciones": "", "descuento_monto": 203700, "lista_precio_id": "1", "monto_pendiente": 6586300, "subtotal_exento": 0, "tipo_comprobante": "FACTURA", "subtotal_gravado_5": 0, "subtotal_gravado_10": 6586300, "total_moneda_principal": 6586300}	::1	\N	\N	2026-01-18 00:12:30.477453
86	1	1	compras	INSERT	1	\N	{"total": 490939090.9090909, "estado": "PENDIENTE", "moneda": "PYG", "subtotal": 450000000, "total_iva": 40909090.90909091, "empresa_id": "1", "usuario_id": "1", "sucursal_id": "1", "tipo_cambio": 1, "tipo_compra": "LOCAL", "dias_credito": "30", "monto_pagado": 0, "otros_gastos": "30000", "proveedor_id": "1", "numero_compra": "COMP-00000001", "observaciones": "compra", "condicion_pago": "CONTADO", "descuento_monto": 0, "monto_pendiente": 490939090.9090909, "timbrado_proveedor": "3443344", "total_moneda_principal": 490939090.9090909, "numero_factura_proveedor": "001001565776"}	::1	\N	\N	2026-01-17 22:03:26
87	\N	\N	ventas	INSERT	22	\N	{"id": 22, "iva_5": 0.00, "total": 3818181.82, "estado": "PENDIENTE", "iva_10": 318181.82, "moneda": "PYG", "vuelto": 0.00, "subtotal": 3500000.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 318181.82, "cliente_id": 2, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-17T22:31:59", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000002", "origen_venta": "SUCURSAL", "observaciones": "", "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-18T02:31:59.048324", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 3818181.82, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-01-18T02:31:59.048324", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 3500000.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": 3818181.82, "fecha_entrega_programada": null}	\N	\N	\N	2026-01-18 02:31:59.048324
88	\N	\N	ventas	UPDATE	22	{"id": 22, "iva_5": 0.00, "total": 3818181.82, "estado": "PENDIENTE", "iva_10": 318181.82, "moneda": "PYG", "vuelto": 0.00, "subtotal": 3500000.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 318181.82, "cliente_id": 2, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-17T22:31:59", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000002", "origen_venta": "SUCURSAL", "observaciones": "", "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-18T02:31:59.048324", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 3818181.82, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-01-18T02:31:59.048324", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 3500000.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": 3818181.82, "fecha_entrega_programada": null}	{"id": 22, "iva_5": 0.00, "total": 3818181.82, "estado": "COMPLETADA", "iva_10": 318181.82, "moneda": "PYG", "vuelto": 0.18, "subtotal": 3500000.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 318181.82, "cliente_id": 2, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-17T22:31:59", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 3818182.00, "numero_venta": "VTA-00000002", "origen_venta": "SUCURSAL", "observaciones": "", "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-18T02:31:59.048324", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": -0.18, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-01-18T02:31:59.048324", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 3500000.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": 3818181.82, "fecha_entrega_programada": null}	\N	\N	\N	2026-01-18 02:31:59.048324
89	1	1	ventas	INSERT	22	\N	{"iva_5": 0, "total": 3818181.82, "estado": "PENDIENTE", "iva_10": 318181.82, "moneda": "PYG", "subtotal": 3500000, "tipo_pago": "CONTADO", "total_iva": 318181.82, "cliente_id": "2", "empresa_id": "1", "tipo_venta": "PRODUCTO", "usuario_id": "1", "fecha_venta": "2026-01-17 22:31:59", "sucursal_id": "1", "tipo_cambio": 1, "vendedor_id": "1", "monto_pagado": 0, "numero_venta": "VTA-00000002", "observaciones": "", "descuento_monto": 0, "lista_precio_id": null, "monto_pendiente": 3818181.82, "subtotal_exento": 0, "tipo_comprobante": "TICKET", "subtotal_gravado_5": 0, "subtotal_gravado_10": 3500000, "total_moneda_principal": 3818181.82}	::1	\N	\N	2026-01-18 02:31:59.103628
92	1	1	ventas	DELETE	21	{"id": "21", "iva_5": 0, "total": 6586300, "estado": "COMPLETADA", "iva_10": 598754.55, "moneda": "PYG", "vuelto": 598755, "subtotal": 6586300, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 598754.55, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-17 20:12:30", "sucursal_id": 1, "tipo_cambio": 1, "vendedor_id": 1, "monto_pagado": 7185055, "numero_venta": "VTA-00000001", "origen_venta": "SUCURSAL", "observaciones": "", "recargo_monto": 0, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-18 00:12:30.415711", "notas_internas": null, "numero_factura": null, "descuento_monto": 203700, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": 1, "monto_pendiente": -598755, "subtotal_exento": 0, "motivo_anulacion": null, "requiere_entrega": "f", "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-01-18 00:12:30.415711", "recargo_porcentaje": 0, "subtotal_gravado_5": 0, "subtotal_gravado_10": 6586300, "cantidad_impresiones": 0, "descuento_porcentaje": 0, "total_moneda_principal": 6586300, "fecha_entrega_programada": null}	\N	::1	\N	Anulación: producto vencido (Admin)	2026-01-18 02:50:33.116733
93	\N	\N	ventas	DELETE	21	{"id": 21, "iva_5": 0.00, "total": 6586300.00, "estado": "COMPLETADA", "iva_10": 598754.55, "moneda": "PYG", "vuelto": 598755.00, "subtotal": 6586300.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 598754.55, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-17T20:12:30", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 7185055.00, "numero_venta": "VTA-00000001", "origen_venta": "SUCURSAL", "observaciones": "", "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-18T00:12:30.415711", "notas_internas": null, "numero_factura": null, "descuento_monto": 203700.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": 1, "monto_pendiente": -598755.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-01-18T00:12:30.415711", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 6586300.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": 6586300.00, "fecha_entrega_programada": null}	\N	\N	\N	\N	2026-01-18 02:50:33.116733
94	2	1	empresas	DELETE	2	{"id": "2", "ruc": "11156766-6", "logo": "1768598459_a7d67cc9bad19fa1bce0.jpg", "email": "albert.salo.654@gmail.com", "ciudad": "Asunción", "estado": "t", "nombre": "Arquisoft", "telefono": "(021) 555-666", "timbrado": "1234567", "direccion": "Av. República Argentina 123", "aplica_iva": "t", "fecha_creacion": "2026-01-16 22:20:59.285386", "porcentaje_iva": "10.00", "establecimiento": "1", "moneda_principal": "PYG", "punto_expedicion": "1", "usuario_creacion": "1", "dias_plazo_retiro": "90", "politica_garantia": null, "fecha_modificacion": "2026-01-16 22:20:59.285386", "protector_pantalla": null, "terminos_condiciones": null, "usa_facturacion_electronica": "f"}	\N	::1	\N	\N	2026-01-17 23:08:41
95	2	1	empresas	DELETE	2	{"id": "2", "ruc": "11156766-6", "logo": "1768598459_a7d67cc9bad19fa1bce0.jpg", "email": "albert.salo.654@gmail.com", "ciudad": "Asunción", "estado": "f", "nombre": "Arquisoft", "telefono": "(021) 555-666", "timbrado": "1234567", "direccion": "Av. República Argentina 123", "aplica_iva": "t", "fecha_creacion": "2026-01-16 22:20:59.285386", "porcentaje_iva": "10.00", "establecimiento": "1", "moneda_principal": "PYG", "punto_expedicion": "1", "usuario_creacion": "1", "dias_plazo_retiro": "90", "politica_garantia": null, "fecha_modificacion": "2026-01-18 03:08:41.115013", "protector_pantalla": null, "terminos_condiciones": null, "usa_facturacion_electronica": "f"}	\N	::1	\N	\N	2026-01-17 23:08:56
99	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768597896_d46b5713018d4d84f2fa.jpg", "sucursal_id": 1, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-17T20:11:31", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-18T00:11:31.445092"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768597896_d46b5713018d4d84f2fa.jpg", "sucursal_id": 1, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-17T20:11:31", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-18T03:51:37.080945"}	\N	\N	\N	2026-01-18 03:51:37.080945
100	1	1	usuarios	UPDATE	1	{"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}	{"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 OPR/126.0.0.0	\N	2026-01-17 23:51:37
101	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768597896_d46b5713018d4d84f2fa.jpg", "sucursal_id": 1, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-17T20:11:31", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-18T03:51:37.080945"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768597896_d46b5713018d4d84f2fa.jpg", "sucursal_id": 1, "preferencias": {"idioma": "en", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-17T20:11:31", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-18T03:51:46.366615"}	\N	\N	\N	2026-01-18 03:51:46.366615
102	1	1	usuarios	UPDATE	1	{"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}	{"idioma": "en", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 OPR/126.0.0.0	\N	2026-01-17 23:51:46
103	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768597896_d46b5713018d4d84f2fa.jpg", "sucursal_id": 1, "preferencias": {"idioma": "en", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-17T20:11:31", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-18T03:51:46.366615"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768597896_d46b5713018d4d84f2fa.jpg", "sucursal_id": 1, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-17T20:11:31", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-18T03:51:53.94102"}	\N	\N	\N	2026-01-18 03:51:53.94102
104	1	1	usuarios	UPDATE	1	{"idioma": "en", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}	{"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 OPR/126.0.0.0	\N	2026-01-17 23:51:53
145	\N	\N	clientes	UPDATE	2	{"ci": "5355147", "id": 2, "ruc": null, "foto": "1768654934_f80381ca6f5f78a5aec8.jpg", "sexo": "M", "email": "albert.salo.654@gmail.com", "barrio": null, "ciudad": "Asunción", "codigo": "CLI-000004", "estado": true, "nombre": "Datamex", "celular": "0727784415", "apellido": "comermex", "telefono": "(021) 555-666", "direccion": "Av. República Argentina 123", "etiquetas": null, "empresa_id": 1, "departamento": "CAPITAL", "dias_credito": 0, "razon_social": null, "tipo_cliente": "PERSONA", "observaciones": "sdfghj", "total_compras": 0.00, "fecha_creacion": "2026-01-17T10:02:14", "limite_credito": 1000000000.00, "notas_internas": null, "lista_precio_id": 1, "nombre_fantasia": null, "cantidad_compras": 0, "fecha_nacimiento": "2003-11-06", "usuario_creacion": 1, "categoria_cliente": "NORMAL", "credito_utilizado": 0.00, "credito_disponible": 1000000000.00, "fecha_modificacion": "2026-01-18T04:36:36.832548", "tipo_contribuyente": "CONTRIBUYENTE", "fecha_ultima_compra": null, "descuento_porcentaje": 0.00, "referencia_direccion": null, "vendedor_asignado_id": null}	{"ci": "5355147", "id": 2, "ruc": null, "foto": "1768654934_f80381ca6f5f78a5aec8.jpg", "sexo": "M", "email": "albert.salo.654@gmail.com", "barrio": null, "ciudad": "Asunción", "codigo": "CLI-000004", "estado": true, "nombre": "Datamex", "celular": "0727784415", "apellido": "comermex", "telefono": "(021) 555-666", "direccion": "Av. República Argentina 123", "etiquetas": null, "empresa_id": 1, "departamento": "CAPITAL", "dias_credito": 0, "razon_social": null, "tipo_cliente": "PERSONA", "observaciones": "sdfghj", "total_compras": 0.00, "fecha_creacion": "2026-01-17T10:02:14", "limite_credito": 1000000000.00, "notas_internas": null, "lista_precio_id": 1, "nombre_fantasia": null, "cantidad_compras": 0, "fecha_nacimiento": "2003-11-06", "usuario_creacion": 1, "categoria_cliente": "NORMAL", "credito_utilizado": 0.00, "credito_disponible": 1000000000.00, "fecha_modificacion": "2026-01-18T17:57:32.032632", "tipo_contribuyente": "CONTRIBUYENTE", "fecha_ultima_compra": null, "descuento_porcentaje": 0.00, "referencia_direccion": null, "vendedor_asignado_id": null}	\N	\N	\N	2026-01-18 17:57:32.032632
105	\N	\N	ventas	INSERT	23	\N	{"id": 23, "iva_5": 0.00, "total": 3592527.27, "estado": "PENDIENTE", "iva_10": 299377.27, "moneda": "PYG", "vuelto": 0.00, "subtotal": 3293150.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 299377.27, "cliente_id": 1, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-17T23:52:57", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000003", "origen_venta": "SUCURSAL", "observaciones": "", "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-18T03:52:57.426578", "notas_internas": null, "numero_factura": null, "descuento_monto": 101850.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": 1, "monto_pendiente": 3592527.27, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-01-18T03:52:57.426578", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 3293150.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": 3592527.27, "fecha_entrega_programada": null}	\N	\N	\N	2026-01-18 03:52:57.426578
106	\N	\N	ventas	UPDATE	23	{"id": 23, "iva_5": 0.00, "total": 3592527.27, "estado": "PENDIENTE", "iva_10": 299377.27, "moneda": "PYG", "vuelto": 0.00, "subtotal": 3293150.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 299377.27, "cliente_id": 1, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-17T23:52:57", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000003", "origen_venta": "SUCURSAL", "observaciones": "", "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-18T03:52:57.426578", "notas_internas": null, "numero_factura": null, "descuento_monto": 101850.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": 1, "monto_pendiente": 3592527.27, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-01-18T03:52:57.426578", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 3293150.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": 3592527.27, "fecha_entrega_programada": null}	{"id": 23, "iva_5": 0.00, "total": 3592527.27, "estado": "PENDIENTE", "iva_10": 299377.27, "moneda": "PYG", "vuelto": 0.00, "subtotal": 3293150.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 299377.27, "cliente_id": 1, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-17T23:52:57", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 3592527.00, "numero_venta": "VTA-00000003", "origen_venta": "SUCURSAL", "observaciones": "", "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-18T03:52:57.426578", "notas_internas": null, "numero_factura": null, "descuento_monto": 101850.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": 1, "monto_pendiente": 0.27, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-01-18T03:52:57.426578", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 3293150.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": 3592527.27, "fecha_entrega_programada": null}	\N	\N	\N	2026-01-18 03:52:57.426578
107	1	1	ventas	INSERT	23	\N	{"iva_5": 0, "total": 3592527.27, "estado": "PENDIENTE", "iva_10": 299377.27, "moneda": "PYG", "subtotal": 3293150, "tipo_pago": "CONTADO", "total_iva": 299377.27, "cliente_id": "1", "empresa_id": "1", "tipo_venta": "PRODUCTO", "usuario_id": "1", "fecha_venta": "2026-01-17 23:52:57", "sucursal_id": "1", "tipo_cambio": 1, "vendedor_id": "1", "monto_pagado": 0, "numero_venta": "VTA-00000003", "observaciones": "", "descuento_monto": 101850, "lista_precio_id": "1", "monto_pendiente": 3592527.27, "subtotal_exento": 0, "tipo_comprobante": "FACTURA", "subtotal_gravado_5": 0, "subtotal_gravado_10": 3293150, "total_moneda_principal": 3592527.27}	::1	\N	\N	2026-01-18 03:52:57.490057
108	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768597896_d46b5713018d4d84f2fa.jpg", "sucursal_id": 1, "tema_oscuro": null, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-17T20:11:31", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-18T03:51:53.94102"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768597896_d46b5713018d4d84f2fa.jpg", "sucursal_id": 1, "tema_oscuro": null, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-17T20:11:31", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-18T03:56:56.662436"}	\N	\N	\N	2026-01-18 03:56:56.662436
109	1	1	usuarios	UPDATE	1	{"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}	{"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 OPR/126.0.0.0	\N	2026-01-17 23:56:56
110	\N	\N	ventas	INSERT	24	\N	{"id": 24, "iva_5": 0.00, "total": 38181818.18, "estado": "PENDIENTE", "iva_10": 3181818.18, "moneda": "PYG", "vuelto": 0.00, "subtotal": 35000000.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 3181818.18, "cliente_id": 2, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-17T23:57:48", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000004", "origen_venta": "SUCURSAL", "observaciones": "", "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-18T03:57:48.973976", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 38181818.18, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-01-18T03:57:48.973976", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 35000000.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": 38181818.18, "fecha_entrega_programada": null}	\N	\N	\N	2026-01-18 03:57:48.973976
152	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768597896_d46b5713018d4d84f2fa.jpg", "sucursal_id": 1, "tema_oscuro": null, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T13:33:16", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-18T17:33:16.060418"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768597896_d46b5713018d4d84f2fa.jpg", "sucursal_id": 1, "tema_oscuro": null, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T13:33:16", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-18T18:33:38.693415"}	\N	\N	\N	2026-01-18 18:33:38.693415
111	\N	\N	ventas	UPDATE	24	{"id": 24, "iva_5": 0.00, "total": 38181818.18, "estado": "PENDIENTE", "iva_10": 3181818.18, "moneda": "PYG", "vuelto": 0.00, "subtotal": 35000000.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 3181818.18, "cliente_id": 2, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-17T23:57:48", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000004", "origen_venta": "SUCURSAL", "observaciones": "", "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-18T03:57:48.973976", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 38181818.18, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-01-18T03:57:48.973976", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 35000000.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": 38181818.18, "fecha_entrega_programada": null}	{"id": 24, "iva_5": 0.00, "total": 38181818.18, "estado": "PENDIENTE", "iva_10": 3181818.18, "moneda": "PYG", "vuelto": 0.00, "subtotal": 35000000.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 3181818.18, "cliente_id": 2, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-17T23:57:48", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 38181818.00, "numero_venta": "VTA-00000004", "origen_venta": "SUCURSAL", "observaciones": "", "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-18T03:57:48.973976", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 0.18, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-01-18T03:57:48.973976", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 35000000.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": 38181818.18, "fecha_entrega_programada": null}	\N	\N	\N	2026-01-18 03:57:48.973976
112	1	1	ventas	INSERT	24	\N	{"iva_5": 0, "total": 38181818.18, "estado": "PENDIENTE", "iva_10": 3181818.18, "moneda": "PYG", "subtotal": 35000000, "tipo_pago": "CONTADO", "total_iva": 3181818.18, "cliente_id": "2", "empresa_id": "1", "tipo_venta": "PRODUCTO", "usuario_id": "1", "fecha_venta": "2026-01-17 23:57:48", "sucursal_id": "1", "tipo_cambio": 1, "vendedor_id": "1", "monto_pagado": 0, "numero_venta": "VTA-00000004", "observaciones": "", "descuento_monto": 0, "lista_precio_id": null, "monto_pendiente": 38181818.18, "subtotal_exento": 0, "tipo_comprobante": "FACTURA", "subtotal_gravado_5": 0, "subtotal_gravado_10": 35000000, "total_moneda_principal": 38181818.18}	::1	\N	\N	2026-01-18 03:57:49.023054
113	\N	\N	clientes	UPDATE	1	{"ci": null, "id": 1, "ruc": "80123456-1", "foto": null, "sexo": null, "email": "otra@empresa.com.py", "barrio": null, "ciudad": null, "codigo": "CLI-003", "estado": true, "nombre": "Empresa Ejemplo Dos S.A.", "celular": "(0983) 555-666", "apellido": null, "telefono": "(021) 555-666", "direccion": "Av. General Santos 123", "etiquetas": null, "empresa_id": 1, "departamento": null, "dias_credito": 0, "razon_social": "Empresa Ejemplo Dos S.A.", "tipo_cliente": "EMPRESA", "observaciones": null, "total_compras": 0.00, "fecha_creacion": "2026-01-16T01:34:21.74548", "limite_credito": 10000000.00, "notas_internas": null, "lista_precio_id": null, "nombre_fantasia": null, "cantidad_compras": 0, "fecha_nacimiento": null, "usuario_creacion": null, "categoria_cliente": "VIP", "credito_utilizado": 0.00, "credito_disponible": 10000000.00, "fecha_modificacion": "2026-01-16T19:51:20.214061", "tipo_contribuyente": "CONTRIBUYENTE", "fecha_ultima_compra": null, "descuento_porcentaje": 0.00, "referencia_direccion": null, "vendedor_asignado_id": null}	{"ci": null, "id": 1, "ruc": "80123456-1", "foto": null, "sexo": null, "email": "otra@empresa.com.py", "barrio": null, "ciudad": null, "codigo": "CLI-003", "estado": true, "nombre": "Empresa Ejemplo Dos S.A.", "celular": "(0983) 555-666", "apellido": null, "telefono": "(021) 555-666", "direccion": "Av. General Santos 123", "etiquetas": null, "empresa_id": 1, "departamento": null, "dias_credito": 10, "razon_social": "Empresa Ejemplo Dos S.A.", "tipo_cliente": "EMPRESA", "observaciones": null, "total_compras": 0.00, "fecha_creacion": "2026-01-16T01:34:21.74548", "limite_credito": 10000000.00, "notas_internas": null, "lista_precio_id": null, "nombre_fantasia": null, "cantidad_compras": 0, "fecha_nacimiento": null, "usuario_creacion": null, "categoria_cliente": "VIP", "credito_utilizado": 0.00, "credito_disponible": 10000000.00, "fecha_modificacion": "2026-01-18T04:23:33.573046", "tipo_contribuyente": "CONTRIBUYENTE", "fecha_ultima_compra": null, "descuento_porcentaje": 0.00, "referencia_direccion": null, "vendedor_asignado_id": null}	\N	\N	\N	2026-01-18 04:23:33.573046
114	1	1	clientes	UPDATE	1	{"ci": null, "id": "1", "ruc": "80123456-1", "foto": null, "sexo": null, "email": "otra@empresa.com.py", "barrio": null, "ciudad": null, "codigo": "CLI-003", "estado": "t", "nombre": "Empresa Ejemplo Dos S.A.", "celular": "(0983) 555-666", "apellido": null, "telefono": "(021) 555-666", "direccion": "Av. General Santos 123", "etiquetas": null, "empresa_id": "1", "departamento": null, "dias_credito": "0", "razon_social": "Empresa Ejemplo Dos S.A.", "tipo_cliente": "EMPRESA", "observaciones": null, "total_compras": "0.00", "fecha_creacion": "2026-01-16 01:34:21.74548", "limite_credito": "10000000.00", "notas_internas": null, "lista_precio_id": null, "nombre_fantasia": null, "cantidad_compras": "0", "fecha_nacimiento": null, "usuario_creacion": null, "categoria_cliente": "VIP", "credito_utilizado": "0.00", "credito_disponible": "10000000.00", "fecha_modificacion": "2026-01-16 19:51:20.214061", "tipo_contribuyente": "CONTRIBUYENTE", "fecha_ultima_compra": null, "descuento_porcentaje": "0.00", "referencia_direccion": null, "vendedor_asignado_id": null}	{"ci": null, "ruc": "80123456-1", "sexo": null, "email": "otra@empresa.com.py", "barrio": null, "ciudad": null, "nombre": "Empresa Ejemplo Dos S.A.", "celular": "(0983) 555-666", "apellido": null, "telefono": "(021) 555-666", "direccion": "Av. General Santos 123", "departamento": null, "dias_credito": 10, "razon_social": "Empresa Ejemplo Dos S.A.", "tipo_cliente": "EMPRESA", "observaciones": null, "limite_credito": 10000000, "lista_precio_id": null, "nombre_fantasia": null, "fecha_nacimiento": null, "categoria_cliente": "VIP", "tipo_contribuyente": "CONTRIBUYENTE", "descuento_porcentaje": 0}	::1	\N	\N	2026-01-18 04:23:33.587868
115	\N	\N	clientes	UPDATE	2	{"ci": "5355147", "id": 2, "ruc": null, "foto": "1768654934_f80381ca6f5f78a5aec8.jpg", "sexo": "M", "email": "albert.salo.654@gmail.com", "barrio": null, "ciudad": "Asunción", "codigo": "CLI-000004", "estado": true, "nombre": "Datamex", "celular": "0727784415", "apellido": "comermex", "telefono": "(021) 555-666", "direccion": "Av. República Argentina 123", "etiquetas": null, "empresa_id": 1, "departamento": "CAPITAL", "dias_credito": 0, "razon_social": null, "tipo_cliente": "PERSONA", "observaciones": "sdfghj", "total_compras": 0.00, "fecha_creacion": "2026-01-17T10:02:14", "limite_credito": 0.00, "notas_internas": null, "lista_precio_id": 1, "nombre_fantasia": null, "cantidad_compras": 0, "fecha_nacimiento": "2003-11-06", "usuario_creacion": 1, "categoria_cliente": "NORMAL", "credito_utilizado": 0.00, "credito_disponible": 0.00, "fecha_modificacion": "2026-01-17T10:02:14", "tipo_contribuyente": "CONTRIBUYENTE", "fecha_ultima_compra": null, "descuento_porcentaje": 0.00, "referencia_direccion": null, "vendedor_asignado_id": null}	{"ci": "5355147", "id": 2, "ruc": null, "foto": "1768654934_f80381ca6f5f78a5aec8.jpg", "sexo": "M", "email": "albert.salo.654@gmail.com", "barrio": null, "ciudad": "Asunción", "codigo": "CLI-000004", "estado": true, "nombre": "Datamex", "celular": "0727784415", "apellido": "comermex", "telefono": "(021) 555-666", "direccion": "Av. República Argentina 123", "etiquetas": null, "empresa_id": 1, "departamento": "CAPITAL", "dias_credito": 0, "razon_social": null, "tipo_cliente": "PERSONA", "observaciones": "sdfghj", "total_compras": 0.00, "fecha_creacion": "2026-01-17T10:02:14", "limite_credito": 1000000000.00, "notas_internas": null, "lista_precio_id": 1, "nombre_fantasia": null, "cantidad_compras": 0, "fecha_nacimiento": "2003-11-06", "usuario_creacion": 1, "categoria_cliente": "NORMAL", "credito_utilizado": 0.00, "credito_disponible": 1000000000.00, "fecha_modificacion": "2026-01-18T04:24:05.096938", "tipo_contribuyente": "CONTRIBUYENTE", "fecha_ultima_compra": null, "descuento_porcentaje": 0.00, "referencia_direccion": null, "vendedor_asignado_id": null}	\N	\N	\N	2026-01-18 04:24:05.096938
205	3	5	usuarios	LOGOUT	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 OPR/126.0.0.0	\N	2026-01-18 23:36:56
274	\N	\N	ordenes_servicio	UPDATE	1	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 9, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 100000.10, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 100000.10, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 1100000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 1100000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T20:08:49.320675", "problema_reportado": "cvbnm,", "costo_real_subtotal": 999999.90, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 1100000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 999999.90, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 1100000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 9, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 109091.01, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 109091.01, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 1200000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 1200000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T20:08:50.333621", "problema_reportado": "cvbnm,", "costo_real_subtotal": 1090908.99, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 1200000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 1090908.99, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 1200000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	\N	\N	\N	2026-01-20 20:08:50.333621
116	1	1	clientes	UPDATE	2	{"ci": "5355147", "id": "2", "ruc": null, "foto": "1768654934_f80381ca6f5f78a5aec8.jpg", "sexo": "M", "email": "albert.salo.654@gmail.com", "barrio": null, "ciudad": "Asunción", "codigo": "CLI-000004", "estado": "t", "nombre": "Datamex", "celular": "0727784415", "apellido": "comermex", "telefono": "(021) 555-666", "direccion": "Av. República Argentina 123", "etiquetas": null, "empresa_id": "1", "departamento": "CAPITAL", "dias_credito": "0", "razon_social": null, "tipo_cliente": "PERSONA", "observaciones": "sdfghj", "total_compras": "0.00", "fecha_creacion": "2026-01-17 10:02:14", "limite_credito": "0.00", "notas_internas": null, "lista_precio_id": "1", "nombre_fantasia": null, "cantidad_compras": "0", "fecha_nacimiento": "2003-11-06", "usuario_creacion": "1", "categoria_cliente": "NORMAL", "credito_utilizado": "0.00", "credito_disponible": "0.00", "fecha_modificacion": "2026-01-17 10:02:14", "tipo_contribuyente": "CONTRIBUYENTE", "fecha_ultima_compra": null, "descuento_porcentaje": "0.00", "referencia_direccion": null, "vendedor_asignado_id": null}	{"ci": "5355147", "ruc": null, "sexo": "M", "email": "albert.salo.654@gmail.com", "barrio": null, "ciudad": "Asunción", "nombre": "Datamex", "celular": "0727784415", "apellido": "comermex", "telefono": "(021) 555-666", "direccion": "Av. República Argentina 123", "departamento": "CAPITAL", "dias_credito": 0, "razon_social": null, "tipo_cliente": "PERSONA", "observaciones": "sdfghj", "limite_credito": 1000000000, "lista_precio_id": 1, "nombre_fantasia": null, "fecha_nacimiento": "2003-11-06", "categoria_cliente": "NORMAL", "tipo_contribuyente": "CONTRIBUYENTE", "descuento_porcentaje": 0}	::1	\N	\N	2026-01-18 04:24:05.11199
117	\N	\N	clientes	UPDATE	1	{"ci": null, "id": 1, "ruc": "80123456-1", "foto": null, "sexo": null, "email": "otra@empresa.com.py", "barrio": null, "ciudad": null, "codigo": "CLI-003", "estado": true, "nombre": "Empresa Ejemplo Dos S.A.", "celular": "(0983) 555-666", "apellido": null, "telefono": "(021) 555-666", "direccion": "Av. General Santos 123", "etiquetas": null, "empresa_id": 1, "departamento": null, "dias_credito": 10, "razon_social": "Empresa Ejemplo Dos S.A.", "tipo_cliente": "EMPRESA", "observaciones": null, "total_compras": 0.00, "fecha_creacion": "2026-01-16T01:34:21.74548", "limite_credito": 10000000.00, "notas_internas": null, "lista_precio_id": null, "nombre_fantasia": null, "cantidad_compras": 0, "fecha_nacimiento": null, "usuario_creacion": null, "categoria_cliente": "VIP", "credito_utilizado": 0.00, "credito_disponible": 10000000.00, "fecha_modificacion": "2026-01-18T04:23:33.573046", "tipo_contribuyente": "CONTRIBUYENTE", "fecha_ultima_compra": null, "descuento_porcentaje": 0.00, "referencia_direccion": null, "vendedor_asignado_id": null}	{"ci": null, "id": 1, "ruc": "80123456-1", "foto": null, "sexo": null, "email": "otra@empresa.com.py", "barrio": null, "ciudad": null, "codigo": "CLI-003", "estado": true, "nombre": "Empresa Ejemplo Dos S.A.", "celular": "(0983) 555-666", "apellido": null, "telefono": "(021) 555-666", "direccion": "Av. General Santos 123", "etiquetas": null, "empresa_id": 1, "departamento": null, "dias_credito": 10, "razon_social": "Empresa Ejemplo Dos S.A.", "tipo_cliente": "EMPRESA", "observaciones": null, "total_compras": 0.00, "fecha_creacion": "2026-01-16T01:34:21.74548", "limite_credito": 10000000.00, "notas_internas": null, "lista_precio_id": null, "nombre_fantasia": null, "cantidad_compras": 0, "fecha_nacimiento": null, "usuario_creacion": null, "categoria_cliente": "VIP", "credito_utilizado": 0.00, "credito_disponible": 10000000.00, "fecha_modificacion": "2026-01-18T04:30:07.881517", "tipo_contribuyente": "CONTRIBUYENTE", "fecha_ultima_compra": null, "descuento_porcentaje": 0.00, "referencia_direccion": null, "vendedor_asignado_id": null}	\N	\N	\N	2026-01-18 04:30:07.881517
118	1	1	clientes	UPDATE	1	{"ci": null, "id": "1", "ruc": "80123456-1", "foto": null, "sexo": null, "email": "otra@empresa.com.py", "barrio": null, "ciudad": null, "codigo": "CLI-003", "estado": "t", "nombre": "Empresa Ejemplo Dos S.A.", "celular": "(0983) 555-666", "apellido": null, "telefono": "(021) 555-666", "direccion": "Av. General Santos 123", "etiquetas": null, "empresa_id": "1", "departamento": null, "dias_credito": "10", "razon_social": "Empresa Ejemplo Dos S.A.", "tipo_cliente": "EMPRESA", "observaciones": null, "total_compras": "0.00", "fecha_creacion": "2026-01-16 01:34:21.74548", "limite_credito": "10000000.00", "notas_internas": null, "lista_precio_id": null, "nombre_fantasia": null, "cantidad_compras": "0", "fecha_nacimiento": null, "usuario_creacion": null, "categoria_cliente": "VIP", "credito_utilizado": "0.00", "credito_disponible": "10000000.00", "fecha_modificacion": "2026-01-18 04:23:33.573046", "tipo_contribuyente": "CONTRIBUYENTE", "fecha_ultima_compra": null, "descuento_porcentaje": "0.00", "referencia_direccion": null, "vendedor_asignado_id": null}	{"ci": null, "ruc": "80123456-1", "sexo": null, "email": "otra@empresa.com.py", "barrio": null, "ciudad": null, "nombre": "Empresa Ejemplo Dos S.A.", "celular": "(0983) 555-666", "apellido": null, "telefono": "(021) 555-666", "direccion": "Av. General Santos 123", "departamento": null, "dias_credito": 10, "razon_social": "Empresa Ejemplo Dos S.A.", "tipo_cliente": "EMPRESA", "observaciones": null, "limite_credito": 10000000, "lista_precio_id": null, "nombre_fantasia": null, "fecha_nacimiento": null, "categoria_cliente": "VIP", "tipo_contribuyente": "CONTRIBUYENTE", "descuento_porcentaje": 0}	::1	\N	\N	2026-01-18 04:30:07.919883
119	\N	\N	ventas	INSERT	25	\N	{"id": 25, "iva_5": 0.00, "total": 3818181.82, "estado": "PENDIENTE", "iva_10": 318181.82, "moneda": "PYG", "vuelto": 0.00, "subtotal": 3500000.00, "timbrado": null, "tipo_pago": "CREDITO", "total_iva": 318181.82, "cliente_id": 2, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-18T00:36:36", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000005", "origen_venta": "SUCURSAL", "observaciones": "", "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-18T04:36:36.832548", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 3818181.82, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-01-18T04:36:36.832548", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 3500000.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": 3818181.82, "fecha_entrega_programada": null}	\N	\N	\N	2026-01-18 04:36:36.832548
120	\N	\N	clientes	UPDATE	2	{"ci": "5355147", "id": 2, "ruc": null, "foto": "1768654934_f80381ca6f5f78a5aec8.jpg", "sexo": "M", "email": "albert.salo.654@gmail.com", "barrio": null, "ciudad": "Asunción", "codigo": "CLI-000004", "estado": true, "nombre": "Datamex", "celular": "0727784415", "apellido": "comermex", "telefono": "(021) 555-666", "direccion": "Av. República Argentina 123", "etiquetas": null, "empresa_id": 1, "departamento": "CAPITAL", "dias_credito": 0, "razon_social": null, "tipo_cliente": "PERSONA", "observaciones": "sdfghj", "total_compras": 0.00, "fecha_creacion": "2026-01-17T10:02:14", "limite_credito": 1000000000.00, "notas_internas": null, "lista_precio_id": 1, "nombre_fantasia": null, "cantidad_compras": 0, "fecha_nacimiento": "2003-11-06", "usuario_creacion": 1, "categoria_cliente": "NORMAL", "credito_utilizado": 0.00, "credito_disponible": 1000000000.00, "fecha_modificacion": "2026-01-18T04:24:05.096938", "tipo_contribuyente": "CONTRIBUYENTE", "fecha_ultima_compra": null, "descuento_porcentaje": 0.00, "referencia_direccion": null, "vendedor_asignado_id": null}	{"ci": "5355147", "id": 2, "ruc": null, "foto": "1768654934_f80381ca6f5f78a5aec8.jpg", "sexo": "M", "email": "albert.salo.654@gmail.com", "barrio": null, "ciudad": "Asunción", "codigo": "CLI-000004", "estado": true, "nombre": "Datamex", "celular": "0727784415", "apellido": "comermex", "telefono": "(021) 555-666", "direccion": "Av. República Argentina 123", "etiquetas": null, "empresa_id": 1, "departamento": "CAPITAL", "dias_credito": 0, "razon_social": null, "tipo_cliente": "PERSONA", "observaciones": "sdfghj", "total_compras": 0.00, "fecha_creacion": "2026-01-17T10:02:14", "limite_credito": 1000000000.00, "notas_internas": null, "lista_precio_id": 1, "nombre_fantasia": null, "cantidad_compras": 0, "fecha_nacimiento": "2003-11-06", "usuario_creacion": 1, "categoria_cliente": "NORMAL", "credito_utilizado": 0.00, "credito_disponible": 1000000000.00, "fecha_modificacion": "2026-01-18T04:36:36.832548", "tipo_contribuyente": "CONTRIBUYENTE", "fecha_ultima_compra": null, "descuento_porcentaje": 0.00, "referencia_direccion": null, "vendedor_asignado_id": null}	\N	\N	\N	2026-01-18 04:36:36.832548
153	1	1	usuarios	UPDATE	1	{"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}	{"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36	\N	2026-01-18 14:33:38
121	\N	\N	ventas	UPDATE	25	{"id": 25, "iva_5": 0.00, "total": 3818181.82, "estado": "PENDIENTE", "iva_10": 318181.82, "moneda": "PYG", "vuelto": 0.00, "subtotal": 3500000.00, "timbrado": null, "tipo_pago": "CREDITO", "total_iva": 318181.82, "cliente_id": 2, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-18T00:36:36", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000005", "origen_venta": "SUCURSAL", "observaciones": "", "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-18T04:36:36.832548", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 3818181.82, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-01-18T04:36:36.832548", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 3500000.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": 3818181.82, "fecha_entrega_programada": null}	{"id": 25, "iva_5": 0.00, "total": 3818181.82, "estado": "COMPLETADA", "iva_10": 318181.82, "moneda": "PYG", "vuelto": 0.18, "subtotal": 3500000.00, "timbrado": null, "tipo_pago": "CREDITO", "total_iva": 318181.82, "cliente_id": 2, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-18T00:36:36", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 3818182.00, "numero_venta": "VTA-00000005", "origen_venta": "SUCURSAL", "observaciones": "", "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-18T04:36:36.832548", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": -0.18, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-01-18T04:36:36.832548", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 3500000.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": 3818181.82, "fecha_entrega_programada": null}	\N	\N	\N	2026-01-18 04:36:36.832548
122	1	1	ventas	INSERT	25	\N	{"iva_5": 0, "total": 3818181.82, "estado": "PENDIENTE", "iva_10": 318181.82, "moneda": "PYG", "subtotal": 3500000, "tipo_pago": "CREDITO", "total_iva": 318181.82, "cliente_id": "2", "empresa_id": "1", "tipo_venta": "PRODUCTO", "usuario_id": "1", "fecha_venta": "2026-01-18 00:36:36", "sucursal_id": "1", "tipo_cambio": 1, "vendedor_id": "1", "monto_pagado": 0, "numero_venta": "VTA-00000005", "observaciones": "", "descuento_monto": 0, "lista_precio_id": null, "monto_pendiente": 3818181.82, "subtotal_exento": 0, "tipo_comprobante": "TICKET", "subtotal_gravado_5": 0, "subtotal_gravado_10": 3500000, "total_moneda_principal": 3818181.82}	::1	\N	\N	2026-01-18 04:36:36.884724
123	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768597896_d46b5713018d4d84f2fa.jpg", "sucursal_id": 1, "tema_oscuro": null, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-17T20:11:31", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-18T03:56:56.662436"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768597896_d46b5713018d4d84f2fa.jpg", "sucursal_id": 1, "tema_oscuro": null, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T10:09:26", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-18T14:09:26.326178"}	\N	\N	\N	2026-01-18 14:09:26.326178
124	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 OPR/126.0.0.0	\N	2026-01-18 10:09:26
125	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768597896_d46b5713018d4d84f2fa.jpg", "sucursal_id": 1, "tema_oscuro": null, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T10:09:26", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-18T14:09:26.326178"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768597896_d46b5713018d4d84f2fa.jpg", "sucursal_id": 1, "tema_oscuro": null, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T10:09:26", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-18T14:13:10.706"}	\N	\N	\N	2026-01-18 14:13:10.706
126	1	1	usuarios	UPDATE	1	{"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}	{"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 OPR/126.0.0.0	\N	2026-01-18 10:13:10
206	\N	\N	usuarios	UPDATE	6	{"ci": "87664647", "id": 6, "email": "canterol888@gmail.com", "estado": true, "nombre": "Luis Alberto", "rol_id": 3, "apellido": "Cantero Britez", "password": "$2y$10$008tyi7aX8YBCkZl1jZ6Eu6jPWha6DkDmBDA/hXdhC7HiwQCOGXa.", "telefono": "+595971414482", "username": "LuiyiCantero", "direccion": null, "empresa_id": 3, "foto_perfil": null, "sucursal_id": 4, "tema_oscuro": null, "preferencias": null, "firma_digital": null, "ultimo_acceso": null, "fecha_creacion": "2026-01-19T03:36:37.495024", "usuario_creacion": 1, "fecha_modificacion": "2026-01-19T03:36:37.495024"}	{"ci": "87664647", "id": 6, "email": "canterol888@gmail.com", "estado": true, "nombre": "Luis Alberto", "rol_id": 3, "apellido": "Cantero Britez", "password": "$2y$10$008tyi7aX8YBCkZl1jZ6Eu6jPWha6DkDmBDA/hXdhC7HiwQCOGXa.", "telefono": "+595971414482", "username": "LuiyiCantero", "direccion": null, "empresa_id": 3, "foto_perfil": null, "sucursal_id": 4, "tema_oscuro": null, "preferencias": null, "firma_digital": null, "ultimo_acceso": "2026-01-18T23:37:11", "fecha_creacion": "2026-01-19T03:36:37.495024", "usuario_creacion": 1, "fecha_modificacion": "2026-01-19T03:37:11.796763"}	\N	\N	\N	2026-01-19 03:37:11.796763
127	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768597896_d46b5713018d4d84f2fa.jpg", "sucursal_id": 1, "tema_oscuro": null, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T10:09:26", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-18T14:13:10.706"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768597896_d46b5713018d4d84f2fa.jpg", "sucursal_id": 1, "tema_oscuro": null, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T10:09:26", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-18T14:27:54.192442"}	\N	\N	\N	2026-01-18 14:27:54.192442
128	1	1	usuarios	UPDATE	1	{"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}	{"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 OPR/126.0.0.0	\N	2026-01-18 10:27:54
129	1	1	usuarios	LOGOUT	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 OPR/126.0.0.0	\N	2026-01-18 10:33:53
130	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768597896_d46b5713018d4d84f2fa.jpg", "sucursal_id": 1, "tema_oscuro": null, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T10:09:26", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-18T14:27:54.192442"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768597896_d46b5713018d4d84f2fa.jpg", "sucursal_id": 1, "tema_oscuro": null, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T10:34:03", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-18T14:34:03.069276"}	\N	\N	\N	2026-01-18 14:34:03.069276
131	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 OPR/126.0.0.0	\N	2026-01-18 10:34:03
132	1	1	usuarios	LOGOUT	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 OPR/126.0.0.0	\N	2026-01-18 10:40:58
133	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768597896_d46b5713018d4d84f2fa.jpg", "sucursal_id": 1, "tema_oscuro": null, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T10:34:03", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-18T14:34:03.069276"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768597896_d46b5713018d4d84f2fa.jpg", "sucursal_id": 1, "tema_oscuro": null, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T10:41:02", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-18T14:41:02.299625"}	\N	\N	\N	2026-01-18 14:41:02.299625
134	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 OPR/126.0.0.0	\N	2026-01-18 10:41:02
144	\N	\N	ventas	INSERT	26	\N	{"id": 26, "iva_5": 0.00, "total": 3592527.27, "estado": "PENDIENTE", "iva_10": 299377.27, "moneda": "PYG", "vuelto": 0.00, "subtotal": 3293150.00, "timbrado": null, "tipo_pago": "CREDITO", "total_iva": 299377.27, "cliente_id": 2, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-18T13:57:32", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000006", "origen_venta": "SUCURSAL", "observaciones": "", "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-18T17:57:32.032632", "notas_internas": null, "numero_factura": null, "descuento_monto": 101850.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": 1, "monto_pendiente": 3592527.27, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-01-18T17:57:32.032632", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 3293150.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": 3592527.27, "fecha_entrega_programada": null}	\N	\N	\N	2026-01-18 17:57:32.032632
135	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768597896_d46b5713018d4d84f2fa.jpg", "sucursal_id": 1, "tema_oscuro": null, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T10:41:02", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-18T14:41:02.299625"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768597896_d46b5713018d4d84f2fa.jpg", "sucursal_id": 1, "tema_oscuro": null, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T10:47:51", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-18T14:47:51.16632"}	\N	\N	\N	2026-01-18 14:47:51.16632
136	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36	\N	2026-01-18 10:47:51
137	1	1	usuarios	LOGOUT	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 OPR/126.0.0.0	\N	2026-01-18 10:51:58
138	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768597896_d46b5713018d4d84f2fa.jpg", "sucursal_id": 1, "tema_oscuro": null, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T10:47:51", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-18T14:47:51.16632"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768597896_d46b5713018d4d84f2fa.jpg", "sucursal_id": 1, "tema_oscuro": null, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T10:52:01", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-18T14:52:01.738309"}	\N	\N	\N	2026-01-18 14:52:01.738309
139	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 OPR/126.0.0.0	\N	2026-01-18 10:52:01
140	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768597896_d46b5713018d4d84f2fa.jpg", "sucursal_id": 1, "tema_oscuro": null, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T10:52:01", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-18T14:52:01.738309"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768597896_d46b5713018d4d84f2fa.jpg", "sucursal_id": 1, "tema_oscuro": null, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T13:05:54", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-18T17:05:54.9671"}	\N	\N	\N	2026-01-18 17:05:54.9671
141	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36	\N	2026-01-18 13:05:54
142	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768597896_d46b5713018d4d84f2fa.jpg", "sucursal_id": 1, "tema_oscuro": null, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T13:05:54", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-18T17:05:54.9671"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768597896_d46b5713018d4d84f2fa.jpg", "sucursal_id": 1, "tema_oscuro": null, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T13:33:16", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-18T17:33:16.060418"}	\N	\N	\N	2026-01-18 17:33:16.060418
143	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 OPR/126.0.0.0	\N	2026-01-18 13:33:16
207	3	6	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 OPR/126.0.0.0	\N	2026-01-18 23:37:11
146	\N	\N	ventas	UPDATE	26	{"id": 26, "iva_5": 0.00, "total": 3592527.27, "estado": "PENDIENTE", "iva_10": 299377.27, "moneda": "PYG", "vuelto": 0.00, "subtotal": 3293150.00, "timbrado": null, "tipo_pago": "CREDITO", "total_iva": 299377.27, "cliente_id": 2, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-18T13:57:32", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000006", "origen_venta": "SUCURSAL", "observaciones": "", "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-18T17:57:32.032632", "notas_internas": null, "numero_factura": null, "descuento_monto": 101850.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": 1, "monto_pendiente": 3592527.27, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-01-18T17:57:32.032632", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 3293150.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": 3592527.27, "fecha_entrega_programada": null}	{"id": 26, "iva_5": 0.00, "total": 3592527.27, "estado": "PENDIENTE", "iva_10": 299377.27, "moneda": "PYG", "vuelto": 0.00, "subtotal": 3293150.00, "timbrado": null, "tipo_pago": "CREDITO", "total_iva": 299377.27, "cliente_id": 2, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-18T13:57:32", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 3592527.00, "numero_venta": "VTA-00000006", "origen_venta": "SUCURSAL", "observaciones": "", "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-18T17:57:32.032632", "notas_internas": null, "numero_factura": null, "descuento_monto": 101850.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": 1, "monto_pendiente": 0.27, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-01-18T17:57:32.032632", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 3293150.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": 3592527.27, "fecha_entrega_programada": null}	\N	\N	\N	2026-01-18 17:57:32.032632
147	1	1	ventas	INSERT	26	\N	{"iva_5": 0, "total": 3592527.27, "estado": "PENDIENTE", "iva_10": 299377.27, "moneda": "PYG", "subtotal": 3293150, "tipo_pago": "CREDITO", "total_iva": 299377.27, "cliente_id": "2", "empresa_id": "1", "tipo_venta": "PRODUCTO", "usuario_id": "1", "fecha_venta": "2026-01-18 13:57:32", "sucursal_id": "1", "tipo_cambio": 1, "vendedor_id": "1", "monto_pagado": 0, "numero_venta": "VTA-00000006", "observaciones": "", "descuento_monto": 101850, "lista_precio_id": "1", "monto_pendiente": 3592527.27, "subtotal_exento": 0, "tipo_comprobante": "TICKET", "subtotal_gravado_5": 0, "subtotal_gravado_10": 3293150, "total_moneda_principal": 3592527.27}	::1	\N	\N	2026-01-18 17:57:32.080956
148	\N	\N	ventas	INSERT	27	\N	{"id": 27, "iva_5": 0.00, "total": 3818181.82, "estado": "PENDIENTE", "iva_10": 318181.82, "moneda": "PYG", "vuelto": 0.00, "subtotal": 3500000.00, "timbrado": null, "tipo_pago": "CREDITO", "total_iva": 318181.82, "cliente_id": 1, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-18T14:21:30", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000007", "origen_venta": "SUCURSAL", "observaciones": "", "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-18T18:21:30.591639", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 3818181.82, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-01-18T18:21:30.591639", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 3500000.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": 3818181.82, "fecha_entrega_programada": null}	\N	\N	\N	2026-01-18 18:21:30.591639
149	\N	\N	clientes	UPDATE	1	{"ci": null, "id": 1, "ruc": "80123456-1", "foto": null, "sexo": null, "email": "otra@empresa.com.py", "barrio": null, "ciudad": null, "codigo": "CLI-003", "estado": true, "nombre": "Empresa Ejemplo Dos S.A.", "celular": "(0983) 555-666", "apellido": null, "telefono": "(021) 555-666", "direccion": "Av. General Santos 123", "etiquetas": null, "empresa_id": 1, "departamento": null, "dias_credito": 10, "razon_social": "Empresa Ejemplo Dos S.A.", "tipo_cliente": "EMPRESA", "observaciones": null, "total_compras": 0.00, "fecha_creacion": "2026-01-16T01:34:21.74548", "limite_credito": 10000000.00, "notas_internas": null, "lista_precio_id": null, "nombre_fantasia": null, "cantidad_compras": 0, "fecha_nacimiento": null, "usuario_creacion": null, "categoria_cliente": "VIP", "credito_utilizado": 0.00, "credito_disponible": 10000000.00, "fecha_modificacion": "2026-01-18T04:30:07.881517", "tipo_contribuyente": "CONTRIBUYENTE", "fecha_ultima_compra": null, "descuento_porcentaje": 0.00, "referencia_direccion": null, "vendedor_asignado_id": null}	{"ci": null, "id": 1, "ruc": "80123456-1", "foto": null, "sexo": null, "email": "otra@empresa.com.py", "barrio": null, "ciudad": null, "codigo": "CLI-003", "estado": true, "nombre": "Empresa Ejemplo Dos S.A.", "celular": "(0983) 555-666", "apellido": null, "telefono": "(021) 555-666", "direccion": "Av. General Santos 123", "etiquetas": null, "empresa_id": 1, "departamento": null, "dias_credito": 10, "razon_social": "Empresa Ejemplo Dos S.A.", "tipo_cliente": "EMPRESA", "observaciones": null, "total_compras": 0.00, "fecha_creacion": "2026-01-16T01:34:21.74548", "limite_credito": 10000000.00, "notas_internas": null, "lista_precio_id": null, "nombre_fantasia": null, "cantidad_compras": 0, "fecha_nacimiento": null, "usuario_creacion": null, "categoria_cliente": "VIP", "credito_utilizado": 0.00, "credito_disponible": 10000000.00, "fecha_modificacion": "2026-01-18T18:21:30.591639", "tipo_contribuyente": "CONTRIBUYENTE", "fecha_ultima_compra": null, "descuento_porcentaje": 0.00, "referencia_direccion": null, "vendedor_asignado_id": null}	\N	\N	\N	2026-01-18 18:21:30.591639
150	\N	\N	ventas	UPDATE	27	{"id": 27, "iva_5": 0.00, "total": 3818181.82, "estado": "PENDIENTE", "iva_10": 318181.82, "moneda": "PYG", "vuelto": 0.00, "subtotal": 3500000.00, "timbrado": null, "tipo_pago": "CREDITO", "total_iva": 318181.82, "cliente_id": 1, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-18T14:21:30", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000007", "origen_venta": "SUCURSAL", "observaciones": "", "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-18T18:21:30.591639", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 3818181.82, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-01-18T18:21:30.591639", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 3500000.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": 3818181.82, "fecha_entrega_programada": null}	{"id": 27, "iva_5": 0.00, "total": 3818181.82, "estado": "COMPLETADA", "iva_10": 318181.82, "moneda": "PYG", "vuelto": 0.18, "subtotal": 3500000.00, "timbrado": null, "tipo_pago": "CREDITO", "total_iva": 318181.82, "cliente_id": 1, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-18T14:21:30", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 3818182.00, "numero_venta": "VTA-00000007", "origen_venta": "SUCURSAL", "observaciones": "", "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-18T18:21:30.591639", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": -0.18, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-01-18T18:21:30.591639", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 3500000.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": 3818181.82, "fecha_entrega_programada": null}	\N	\N	\N	2026-01-18 18:21:30.591639
151	1	1	ventas	INSERT	27	\N	{"iva_5": 0, "total": 3818181.82, "estado": "PENDIENTE", "iva_10": 318181.82, "moneda": "PYG", "subtotal": 3500000, "tipo_pago": "CREDITO", "total_iva": 318181.82, "cliente_id": "1", "empresa_id": "1", "tipo_venta": "PRODUCTO", "usuario_id": "1", "fecha_venta": "2026-01-18 14:21:30", "sucursal_id": "1", "tipo_cambio": 1, "vendedor_id": "1", "monto_pagado": 0, "numero_venta": "VTA-00000007", "observaciones": "", "descuento_monto": 0, "lista_precio_id": null, "monto_pendiente": 3818181.82, "subtotal_exento": 0, "tipo_comprobante": "TICKET", "subtotal_gravado_5": 0, "subtotal_gravado_10": 3500000, "total_moneda_principal": 3818181.82}	::1	\N	\N	2026-01-18 18:21:30.654355
154	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768597896_d46b5713018d4d84f2fa.jpg", "sucursal_id": 1, "tema_oscuro": null, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T13:33:16", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-18T18:33:38.693415"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768757660_3d887a38fa34b4218399.jpeg", "sucursal_id": 1, "tema_oscuro": null, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T13:33:16", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-18T18:34:20.62878"}	\N	\N	\N	2026-01-18 18:34:20.62878
155	1	1	usuarios	UPDATE	1	\N	{"foto_perfil": "1768757660_3d887a38fa34b4218399.jpeg"}	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36	\N	2026-01-18 14:34:20
156	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768757660_3d887a38fa34b4218399.jpeg", "sucursal_id": 1, "tema_oscuro": null, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T13:33:16", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-18T18:34:20.62878"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768757660_3d887a38fa34b4218399.jpeg", "sucursal_id": 1, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T13:33:16", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-18T18:34:57.332949"}	\N	\N	\N	2026-01-18 18:34:57.332949
157	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768757660_3d887a38fa34b4218399.jpeg", "sucursal_id": 1, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T13:33:16", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-18T18:34:57.332949"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768757660_3d887a38fa34b4218399.jpeg", "sucursal_id": 1, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T13:33:16", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-18T18:34:57.349034"}	\N	\N	\N	2026-01-18 18:34:57.349034
158	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768757660_3d887a38fa34b4218399.jpeg", "sucursal_id": 1, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T13:33:16", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-18T18:34:57.349034"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768757660_3d887a38fa34b4218399.jpeg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T13:33:16", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-18T18:35:16.289657"}	\N	\N	\N	2026-01-18 18:35:16.289657
159	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768757660_3d887a38fa34b4218399.jpeg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T13:33:16", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-18T18:35:16.289657"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768757660_3d887a38fa34b4218399.jpeg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T13:33:16", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-18T18:35:16.301051"}	\N	\N	\N	2026-01-18 18:35:16.301051
160	\N	\N	productos	UPDATE	2	{"id": 2, "peso": null, "tags": null, "codigo": "IPH-13", "estado": true, "moneda": "PYG", "nombre": "iPhone 13 Pro Max", "marca_id": 2, "tipo_iva": "GRAVADO_10", "usa_lote": false, "es_oferta": false, "usa_serie": false, "empresa_id": 1, "es_novedad": false, "descripcion": "iPhone 13 Pro Max 256GB", "dimensiones": null, "categoria_id": 1, "codigo_barra": null, "es_destacado": false, "precio_venta": 6500000.00, "stock_maximo": 30, "stock_minimo": 3, "observaciones": null, "precio_compra": 5000000.00, "precio_minimo": null, "punto_reorden": 5, "tipo_producto": "PRODUCTO", "controla_stock": true, "fecha_creacion": "2026-01-16T01:34:21.74548", "garantia_meses": 0, "porcentaje_iva": null, "margen_utilidad": 30.00, "usa_vencimiento": false, "imagen_principal": null, "precio_mayorista": null, "unidad_medida_id": 1, "usuario_creacion": null, "codigo_fabricante": null, "descripcion_corta": null, "fecha_modificacion": "2026-01-16T01:34:21.74548", "permite_venta_sin_stock": false, "especificaciones_tecnicas": null}	{"id": 2, "peso": null, "tags": null, "codigo": "IPH-13", "estado": true, "moneda": "PYG", "nombre": "iPhone 13 Pro Max", "marca_id": 2, "tipo_iva": "GRAVADO_10", "usa_lote": true, "es_oferta": false, "usa_serie": false, "empresa_id": 1, "es_novedad": false, "descripcion": "iPhone 13 Pro Max 256GB", "dimensiones": null, "categoria_id": 1, "codigo_barra": "", "es_destacado": false, "precio_venta": 6500000.00, "stock_maximo": 30, "stock_minimo": 3, "observaciones": null, "precio_compra": 5000000.00, "precio_minimo": null, "punto_reorden": 5, "tipo_producto": "PRODUCTO", "controla_stock": true, "fecha_creacion": "2026-01-16T01:34:21.74548", "garantia_meses": 0, "porcentaje_iva": 10.00, "margen_utilidad": 30.00, "usa_vencimiento": true, "imagen_principal": "1768757756_e86e34db5248c3a9855b.jpg", "precio_mayorista": null, "unidad_medida_id": 1, "usuario_creacion": null, "codigo_fabricante": null, "descripcion_corta": null, "fecha_modificacion": "2026-01-18T18:35:56.738489", "permite_venta_sin_stock": true, "especificaciones_tecnicas": null}	\N	\N	\N	2026-01-18 18:35:56.738489
161	1	1	productos	UPDATE	2	{"id": "2", "peso": null, "tags": null, "codigo": "IPH-13", "estado": "t", "moneda": "PYG", "nombre": "iPhone 13 Pro Max", "marca_id": "2", "tipo_iva": "GRAVADO_10", "usa_lote": "f", "es_oferta": "f", "usa_serie": "f", "empresa_id": "1", "es_novedad": "f", "descripcion": "iPhone 13 Pro Max 256GB", "dimensiones": null, "categoria_id": "1", "codigo_barra": null, "es_destacado": "f", "precio_venta": "6500000.00", "stock_maximo": "30", "stock_minimo": "3", "observaciones": null, "precio_compra": "5000000.00", "precio_minimo": null, "punto_reorden": "5", "tipo_producto": "PRODUCTO", "controla_stock": "t", "fecha_creacion": "2026-01-16 01:34:21.74548", "garantia_meses": "0", "porcentaje_iva": null, "margen_utilidad": "30.00", "usa_vencimiento": "f", "imagen_principal": null, "precio_mayorista": null, "unidad_medida_id": "1", "usuario_creacion": null, "codigo_fabricante": null, "descripcion_corta": null, "fecha_modificacion": "2026-01-16 01:34:21.74548", "permite_venta_sin_stock": "f", "especificaciones_tecnicas": null}	{"peso": null, "codigo": "IPH-13", "moneda": "PYG", "nombre": "iPhone 13 Pro Max", "marca_id": "2", "tipo_iva": "GRAVADO_10", "usa_lote": true, "usa_serie": false, "descripcion": "iPhone 13 Pro Max 256GB", "dimensiones": null, "categoria_id": "1", "codigo_barra": "", "precio_venta": 6500000, "stock_maximo": 30, "stock_minimo": 3, "observaciones": null, "precio_compra": 5000000, "precio_minimo": null, "punto_reorden": 5, "tipo_producto": "PRODUCTO", "controla_stock": true, "garantia_meses": 0, "porcentaje_iva": 10, "margen_utilidad": 30, "usa_vencimiento": true, "imagen_principal": "1768757756_e86e34db5248c3a9855b.jpg", "precio_mayorista": null, "unidad_medida_id": "1", "codigo_fabricante": null, "descripcion_corta": null, "permite_venta_sin_stock": true}	::1	\N	\N	2026-01-18 18:35:56.777578
162	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768757660_3d887a38fa34b4218399.jpeg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T13:33:16", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-18T18:35:16.301051"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768757660_3d887a38fa34b4218399.jpeg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-18T23:17:35.724234"}	\N	\N	\N	2026-01-18 23:17:35.724234
163	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36	\N	2026-01-18 19:17:35
164	\N	\N	ordenes_servicio	UPDATE	1	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 1, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 0.00, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 0.00, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 0.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 0.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-16T16:56:13", "problema_reportado": "cvbnm,", "costo_real_subtotal": 0.00, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 0.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 0.00, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 0.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 2, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 0.00, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 0.00, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 0.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 0.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-19T00:52:07.825927", "problema_reportado": "cvbnm,", "costo_real_subtotal": 0.00, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 0.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 0.00, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 0.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	\N	\N	\N	2026-01-19 00:52:07.825927
165	\N	\N	ordenes_servicio	UPDATE	1	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 2, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 0.00, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 0.00, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 0.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 0.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-19T00:52:07.825927", "problema_reportado": "cvbnm,", "costo_real_subtotal": 0.00, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 0.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 0.00, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 0.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 2, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 0.00, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 9091.00, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 0.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 100000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-19T00:54:27.827147", "problema_reportado": "cvbnm,", "costo_real_subtotal": 0.00, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 0.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 100000.00, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 100000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	\N	\N	\N	2026-01-19 00:54:27.827147
166	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768757660_3d887a38fa34b4218399.jpeg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-18T23:17:35.724234"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768782353_12e87595f085ed420f53.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:25:53.943421"}	\N	\N	\N	2026-01-19 01:25:53.943421
167	1	1	usuarios	UPDATE	1	\N	{"foto_perfil": "1768782353_12e87595f085ed420f53.jpg"}	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36	\N	2026-01-18 21:25:53
168	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768782353_12e87595f085ed420f53.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:25:53.943421"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:37:37.243755"}	\N	\N	\N	2026-01-19 01:37:37.243755
169	1	1	usuarios	UPDATE	1	\N	{"foto_perfil": "1768783057_454588b317d3943cd1cf.jpg"}	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36	\N	2026-01-18 21:37:37
170	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:37:37.243755"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:37:54.410119"}	\N	\N	\N	2026-01-19 01:37:54.410119
171	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:37:54.410119"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:37:54.421604"}	\N	\N	\N	2026-01-19 01:37:54.421604
172	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:37:54.421604"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:37:56.918054"}	\N	\N	\N	2026-01-19 01:37:56.918054
173	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:37:56.918054"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:37:56.929371"}	\N	\N	\N	2026-01-19 01:37:56.929371
266	\N	\N	ordenes_servicio	UPDATE	1	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 2, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 36363.73, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 36363.73, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 400000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 400000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T19:25:52.30679", "problema_reportado": "cvbnm,", "costo_real_subtotal": 363636.27, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 400000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 363636.27, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 400000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 2, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 45454.64, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 45454.64, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 500000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 500000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T19:25:52.63542", "problema_reportado": "cvbnm,", "costo_real_subtotal": 454545.36, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 500000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 454545.36, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 500000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	\N	\N	\N	2026-01-20 19:25:52.63542
174	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:37:56.929371"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:37:57.806492"}	\N	\N	\N	2026-01-19 01:37:57.806492
175	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:37:57.806492"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:37:57.820918"}	\N	\N	\N	2026-01-19 01:37:57.820918
176	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:37:57.820918"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:37:58.124165"}	\N	\N	\N	2026-01-19 01:37:58.124165
177	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:37:58.124165"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:37:58.133623"}	\N	\N	\N	2026-01-19 01:37:58.133623
267	\N	\N	ordenes_servicio	UPDATE	1	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 2, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 45454.64, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 45454.64, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 500000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 500000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T19:25:52.63542", "problema_reportado": "cvbnm,", "costo_real_subtotal": 454545.36, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 500000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 454545.36, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 500000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 2, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 54545.55, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 54545.55, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 600000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 600000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T19:25:52.912262", "problema_reportado": "cvbnm,", "costo_real_subtotal": 545454.45, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 600000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 545454.45, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 600000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	\N	\N	\N	2026-01-20 19:25:52.912262
178	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:37:58.133623"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:37:58.950706"}	\N	\N	\N	2026-01-19 01:37:58.950706
179	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:37:58.950706"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:37:58.963868"}	\N	\N	\N	2026-01-19 01:37:58.963868
180	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:37:58.963868"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:37:59.931186"}	\N	\N	\N	2026-01-19 01:37:59.931186
181	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:37:59.931186"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:37:59.943759"}	\N	\N	\N	2026-01-19 01:37:59.943759
268	\N	\N	ordenes_servicio	UPDATE	1	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 2, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 54545.55, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 54545.55, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 600000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 600000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T19:25:52.912262", "problema_reportado": "cvbnm,", "costo_real_subtotal": 545454.45, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 600000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 545454.45, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 600000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 2, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 63636.46, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 63636.46, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 700000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 700000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T19:25:53.19188", "problema_reportado": "cvbnm,", "costo_real_subtotal": 636363.54, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 700000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 636363.54, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 700000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	\N	\N	\N	2026-01-20 19:25:53.19188
182	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:37:59.943759"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:55:59.481834"}	\N	\N	\N	2026-01-19 01:55:59.481834
183	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:55:59.481834"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:55:59.493474"}	\N	\N	\N	2026-01-19 01:55:59.493474
184	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:55:59.493474"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:56:00.667205"}	\N	\N	\N	2026-01-19 01:56:00.667205
185	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:56:00.667205"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:56:00.679771"}	\N	\N	\N	2026-01-19 01:56:00.679771
269	\N	\N	ordenes_servicio	UPDATE	1	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 2, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 63636.46, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 63636.46, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 700000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 700000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T19:25:53.19188", "problema_reportado": "cvbnm,", "costo_real_subtotal": 636363.54, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 700000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 636363.54, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 700000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 2, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 72727.37, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 72727.37, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 800000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 800000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T19:25:53.526147", "problema_reportado": "cvbnm,", "costo_real_subtotal": 727272.63, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 800000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 727272.63, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 800000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	\N	\N	\N	2026-01-20 19:25:53.526147
186	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:56:00.679771"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:56:00.975964"}	\N	\N	\N	2026-01-19 01:56:00.975964
187	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:56:00.975964"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:56:00.991363"}	\N	\N	\N	2026-01-19 01:56:00.991363
188	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:56:00.991363"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:56:01.423963"}	\N	\N	\N	2026-01-19 01:56:01.423963
189	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:56:01.423963"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:56:01.504619"}	\N	\N	\N	2026-01-19 01:56:01.504619
270	\N	\N	ordenes_servicio	UPDATE	1	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 2, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 72727.37, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 72727.37, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 800000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 800000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T19:25:53.526147", "problema_reportado": "cvbnm,", "costo_real_subtotal": 727272.63, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 800000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 727272.63, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 800000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 2, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 81818.28, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 81818.28, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 900000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 900000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T19:25:53.748675", "problema_reportado": "cvbnm,", "costo_real_subtotal": 818181.72, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 900000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 818181.72, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 900000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	\N	\N	\N	2026-01-20 19:25:53.748675
190	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:56:01.504619"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:56:01.810501"}	\N	\N	\N	2026-01-19 01:56:01.810501
191	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:56:01.810501"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:56:01.817726"}	\N	\N	\N	2026-01-19 01:56:01.817726
192	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:56:01.817726"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:56:02.355038"}	\N	\N	\N	2026-01-19 01:56:02.355038
193	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:56:02.355038"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:56:02.365788"}	\N	\N	\N	2026-01-19 01:56:02.365788
271	\N	\N	ordenes_servicio	UPDATE	1	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 2, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 81818.28, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 81818.28, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 900000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 900000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T19:25:53.748675", "problema_reportado": "cvbnm,", "costo_real_subtotal": 818181.72, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 900000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 818181.72, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 900000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 2, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 90909.19, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 90909.19, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 1000000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 1000000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T19:25:54.058915", "problema_reportado": "cvbnm,", "costo_real_subtotal": 909090.81, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 1000000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 909090.81, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 1000000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	\N	\N	\N	2026-01-20 19:25:54.058915
194	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:56:02.365788"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:56:11.691779"}	\N	\N	\N	2026-01-19 01:56:11.691779
195	1	1	usuarios	UPDATE	1	{"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}	{"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36	\N	2026-01-18 21:56:11
196	3	1	empresas	INSERT	\N	\N	{"ruc": "07282811-0", "logo": "1768784920_08df6ccac94683766d71.jpg", "email": "compusurvillarrica@gmail.com", "ciudad": "ÑEMBY", "nombre": "COMPUSUR PY", "telefono": "+595971414482", "timbrado": null, "direccion": "Sta. Rosa, Ñemby 111217", "aplica_iva": true, "porcentaje_iva": 10, "establecimiento": 1, "moneda_principal": "PYG", "punto_expedicion": 1, "usuario_creacion": 1, "dias_plazo_retiro": 30, "politica_garantia": null, "terminos_condiciones": null, "usa_facturacion_electronica": false}	::1	\N	\N	2026-01-18 22:08:40
197	3	1	sucursales	INSERT	\N	\N	{"email": "compusurvillarrica@gmail.com", "ciudad": "ÑEMBY", "codigo": "DEP-001", "estado": true, "nombre": "DEPOSITO-CENTRAL", "latitud": "-25.393277958118517", "longitud": " -57.531319015862096", "telefono": "+595971414482", "direccion": "Av. General Santos 123", "es_matriz": true, "empresa_id": "3", "responsable": "Luis Alberto (Dueño)", "departamento": "CAPITAL", "horario_cierre": "19:00", "horario_apertura": "08:00", "codigo_establecimiento": "DEP"}	::1	\N	\N	2026-01-18 22:17:20
198	3	1	sucursales	INSERT	\N	\N	{"email": "", "ciudad": "ÑEMBY", "codigo": "SURC-001", "estado": true, "nombre": "SUCURSAL N°1", "latitud": "-25.391420598884913", "longitud": " -57.54583069460894", "telefono": "0987480507", "direccion": "ACCESO SUR ,SOBRE, Independencia Nacional, Ñemby", "es_matriz": false, "empresa_id": "3", "responsable": "Luis Alberto(Hijo)", "departamento": "CAPITAL", "horario_cierre": "18:00", "horario_apertura": "08:00", "codigo_establecimiento": "SUR"}	::1	\N	\N	2026-01-18 22:21:49
199	\N	\N	usuarios	INSERT	5	\N	{"ci": "123456", "id": 5, "email": "compusurvillarrica@gmail.com", "estado": true, "nombre": "Luis Alberto", "rol_id": 2, "apellido": "Cantero", "password": "$2y$10$WlsOsU03MVru2v1Ae9y8ieKwx/eHfQd7GdNyqBaupXimjK9hh8Vp.", "telefono": "+595971414482", "username": "LuisCantero", "direccion": null, "empresa_id": 3, "foto_perfil": "1768785860_e3d49f26dfc9b56c5657.jpg", "sucursal_id": 3, "tema_oscuro": null, "preferencias": null, "firma_digital": null, "ultimo_acceso": null, "fecha_creacion": "2026-01-19T02:24:21.032868", "usuario_creacion": 1, "fecha_modificacion": "2026-01-19T02:24:21.032868"}	\N	\N	\N	2026-01-19 02:24:21.032868
200	3	1	usuarios	INSERT	\N	\N	{"ci": "123456", "email": "compusurvillarrica@gmail.com", "estado": true, "nombre": "Luis Alberto", "rol_id": 2, "apellido": "Cantero", "password": "Luis1234", "telefono": "+595971414482", "username": "LuisCantero", "direccion": null, "empresa_id": 3, "foto_perfil": "1768785860_e3d49f26dfc9b56c5657.jpg", "sucursal_id": 3, "preferencias": null, "firma_digital": null, "ultimo_acceso": null, "usuario_creacion": 1}	::1	\N	\N	2026-01-18 22:24:21
201	\N	\N	usuarios	UPDATE	5	{"ci": "123456", "id": 5, "email": "compusurvillarrica@gmail.com", "estado": true, "nombre": "Luis Alberto", "rol_id": 2, "apellido": "Cantero", "password": "$2y$10$WlsOsU03MVru2v1Ae9y8ieKwx/eHfQd7GdNyqBaupXimjK9hh8Vp.", "telefono": "+595971414482", "username": "LuisCantero", "direccion": null, "empresa_id": 3, "foto_perfil": "1768785860_e3d49f26dfc9b56c5657.jpg", "sucursal_id": 3, "tema_oscuro": null, "preferencias": null, "firma_digital": null, "ultimo_acceso": null, "fecha_creacion": "2026-01-19T02:24:21.032868", "usuario_creacion": 1, "fecha_modificacion": "2026-01-19T02:24:21.032868"}	{"ci": "123456", "id": 5, "email": "compusurvillarrica@gmail.com", "estado": true, "nombre": "Luis Alberto", "rol_id": 2, "apellido": "Cantero", "password": "$2y$10$WlsOsU03MVru2v1Ae9y8ieKwx/eHfQd7GdNyqBaupXimjK9hh8Vp.", "telefono": "+595971414482", "username": "LuisCantero", "direccion": null, "empresa_id": 3, "foto_perfil": "1768785860_e3d49f26dfc9b56c5657.jpg", "sucursal_id": 3, "tema_oscuro": null, "preferencias": null, "firma_digital": null, "ultimo_acceso": "2026-01-18T22:25:12", "fecha_creacion": "2026-01-19T02:24:21.032868", "usuario_creacion": 1, "fecha_modificacion": "2026-01-19T02:25:12.189003"}	\N	\N	\N	2026-01-19 02:25:12.189003
202	3	5	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 OPR/126.0.0.0	\N	2026-01-18 22:25:12
203	\N	\N	usuarios	INSERT	6	\N	{"ci": "87664647", "id": 6, "email": "canterol888@gmail.com", "estado": true, "nombre": "Luis Alberto", "rol_id": 3, "apellido": "Cantero Britez", "password": "$2y$10$008tyi7aX8YBCkZl1jZ6Eu6jPWha6DkDmBDA/hXdhC7HiwQCOGXa.", "telefono": "+595971414482", "username": "LuiyiCantero", "direccion": null, "empresa_id": 3, "foto_perfil": null, "sucursal_id": 4, "tema_oscuro": null, "preferencias": null, "firma_digital": null, "ultimo_acceso": null, "fecha_creacion": "2026-01-19T03:36:37.495024", "usuario_creacion": 1, "fecha_modificacion": "2026-01-19T03:36:37.495024"}	\N	\N	\N	2026-01-19 03:36:37.495024
204	3	1	usuarios	INSERT	\N	\N	{"ci": "87664647", "email": "canterol888@gmail.com", "estado": true, "nombre": "Luis Alberto", "rol_id": 3, "apellido": "Cantero Britez", "password": "Luis1234", "telefono": "+595971414482", "username": "LuiyiCantero", "direccion": null, "empresa_id": 3, "foto_perfil": null, "sucursal_id": 4, "preferencias": null, "firma_digital": null, "ultimo_acceso": null, "usuario_creacion": 1}	::1	\N	\N	2026-01-18 23:36:37
208	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-18T19:17:35", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T01:56:11.691779"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-19T09:28:42", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T13:28:42.883424"}	\N	\N	\N	2026-01-19 13:28:42.883424
209	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36	\N	2026-01-19 09:28:42
210	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-19T09:28:42", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T13:28:42.883424"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-19T14:31:25", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T18:31:25.295307"}	\N	\N	\N	2026-01-19 18:31:25.295307
211	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 OPR/126.0.0.0	\N	2026-01-19 14:31:25
272	\N	\N	ordenes_servicio	UPDATE	1	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 2, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 90909.19, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 90909.19, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 1000000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 1000000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T19:25:54.058915", "problema_reportado": "cvbnm,", "costo_real_subtotal": 909090.81, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 1000000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 909090.81, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 1000000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 9, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 90909.19, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 90909.19, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 1000000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 1000000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T20:07:36.476129", "problema_reportado": "cvbnm,", "costo_real_subtotal": 909090.81, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 1000000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 909090.81, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 1000000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	\N	\N	\N	2026-01-20 20:07:36.476129
221	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-19T14:31:25", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-19T18:31:25.295307"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-19T21:23:20", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-20T01:23:20.035447"}	\N	\N	\N	2026-01-20 01:23:20.035447
222	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36	\N	2026-01-19 21:23:20
223	\N	\N	clientes	UPDATE	1	{"ci": null, "id": 1, "ruc": "80123456-1", "foto": null, "sexo": null, "email": "otra@empresa.com.py", "barrio": null, "ciudad": null, "codigo": "CLI-003", "estado": true, "nombre": "Empresa Ejemplo Dos S.A.", "celular": "(0983) 555-666", "apellido": null, "telefono": "(021) 555-666", "direccion": "Av. General Santos 123", "etiquetas": null, "empresa_id": 1, "departamento": null, "dias_credito": 10, "razon_social": "Empresa Ejemplo Dos S.A.", "tipo_cliente": "EMPRESA", "observaciones": null, "total_compras": 0.00, "fecha_creacion": "2026-01-16T01:34:21.74548", "limite_credito": 10000000.00, "notas_internas": null, "lista_precio_id": null, "nombre_fantasia": null, "cantidad_compras": 0, "fecha_nacimiento": null, "usuario_creacion": null, "categoria_cliente": "VIP", "credito_utilizado": 0.00, "credito_disponible": 10000000.00, "fecha_modificacion": "2026-01-18T18:21:30.591639", "tipo_contribuyente": "CONTRIBUYENTE", "fecha_ultima_compra": null, "descuento_porcentaje": 0.00, "referencia_direccion": null, "vendedor_asignado_id": null}	{"ci": null, "id": 1, "ruc": "80123456-1", "foto": null, "sexo": null, "email": "otra@empresa.com.py", "barrio": null, "ciudad": null, "codigo": "CLI-003", "estado": true, "nombre": "Empresa Ejemplo Dos S.A.", "celular": "0972778415", "apellido": null, "telefono": "0972778415", "direccion": "Av. General Santos 123", "etiquetas": null, "empresa_id": 1, "departamento": null, "dias_credito": 10, "razon_social": "Empresa Ejemplo Dos S.A.", "tipo_cliente": "EMPRESA", "observaciones": null, "total_compras": 0.00, "fecha_creacion": "2026-01-16T01:34:21.74548", "limite_credito": 10000000.00, "notas_internas": null, "lista_precio_id": null, "nombre_fantasia": null, "cantidad_compras": 0, "fecha_nacimiento": null, "usuario_creacion": null, "categoria_cliente": "VIP", "credito_utilizado": 0.00, "credito_disponible": 10000000.00, "fecha_modificacion": "2026-01-20T01:25:14.284264", "tipo_contribuyente": "CONTRIBUYENTE", "fecha_ultima_compra": null, "descuento_porcentaje": 0.00, "referencia_direccion": null, "vendedor_asignado_id": null}	\N	\N	\N	2026-01-20 01:25:14.284264
224	1	1	clientes	UPDATE	1	{"ci": null, "id": "1", "ruc": "80123456-1", "foto": null, "sexo": null, "email": "otra@empresa.com.py", "barrio": null, "ciudad": null, "codigo": "CLI-003", "estado": "t", "nombre": "Empresa Ejemplo Dos S.A.", "celular": "(0983) 555-666", "apellido": null, "telefono": "(021) 555-666", "direccion": "Av. General Santos 123", "etiquetas": null, "empresa_id": "1", "departamento": null, "dias_credito": "10", "razon_social": "Empresa Ejemplo Dos S.A.", "tipo_cliente": "EMPRESA", "observaciones": null, "total_compras": "0.00", "fecha_creacion": "2026-01-16 01:34:21.74548", "limite_credito": "10000000.00", "notas_internas": null, "lista_precio_id": null, "nombre_fantasia": null, "cantidad_compras": "0", "fecha_nacimiento": null, "usuario_creacion": null, "categoria_cliente": "VIP", "credito_utilizado": "0.00", "credito_disponible": "10000000.00", "fecha_modificacion": "2026-01-18 18:21:30.591639", "tipo_contribuyente": "CONTRIBUYENTE", "fecha_ultima_compra": null, "descuento_porcentaje": "0.00", "referencia_direccion": null, "vendedor_asignado_id": null}	{"ci": null, "ruc": "80123456-1", "sexo": null, "email": "otra@empresa.com.py", "barrio": null, "ciudad": null, "nombre": "Empresa Ejemplo Dos S.A.", "celular": "0972778415", "apellido": null, "telefono": "0972778415", "direccion": "Av. General Santos 123", "departamento": null, "dias_credito": 10, "razon_social": "Empresa Ejemplo Dos S.A.", "tipo_cliente": "EMPRESA", "observaciones": null, "limite_credito": 10000000, "lista_precio_id": null, "nombre_fantasia": null, "fecha_nacimiento": null, "categoria_cliente": "VIP", "tipo_contribuyente": "CONTRIBUYENTE", "descuento_porcentaje": 0}	::1	\N	\N	2026-01-20 01:25:14.310166
248	\N	\N	ventas	INSERT	28	\N	{"id": 28, "iva_5": 0.00, "total": 3500000.00, "estado": "PENDIENTE", "iva_10": 318182.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 3500000.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 318182.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-20T00:29:08", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000008", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-20T04:29:08.374319", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 3500000.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-01-20T04:29:08.374319", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-01-20 04:29:08.374319
249	\N	\N	ventas	UPDATE	28	{"id": 28, "iva_5": 0.00, "total": 3500000.00, "estado": "PENDIENTE", "iva_10": 318182.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 3500000.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 318182.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-20T00:29:08", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000008", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-20T04:29:08.374319", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 3500000.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-01-20T04:29:08.374319", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	{"id": 28, "iva_5": 0.00, "total": 3500000.00, "estado": "COMPLETADA", "iva_10": 318182.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 3500000.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 318182.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-20T00:29:08", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 3500000.00, "numero_venta": "VTA-00000008", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-20T04:29:08.374319", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 0.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-01-20T04:29:08.374319", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-01-20 04:29:08.374319
256	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-19T21:23:20", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-20T01:23:20.035447"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-20T09:17:00", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-20T13:17:00.288314"}	\N	\N	\N	2026-01-20 13:17:00.288314
257	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36	\N	2026-01-20 09:17:00
258	\N	\N	ventas	INSERT	29	\N	{"id": 29, "iva_5": 0.00, "total": 10000.00, "estado": "PENDIENTE", "iva_10": 909.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 10000.00, "timbrado": "1234567", "tipo_pago": "CONTADO", "total_iva": 909.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-20T09:17:25", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000009", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-20T13:17:25.89267", "notas_internas": null, "numero_factura": "1000002", "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 10000.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-01-20T13:17:25.89267", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-01-20 13:17:25.89267
306	\N	\N	ventas	INSERT	31	\N	{"id": 31, "iva_5": 0.00, "total": 9700.00, "estado": "PENDIENTE", "iva_10": 882.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 9700.00, "timbrado": "1234567", "tipo_pago": "CONTADO", "total_iva": 882.00, "cliente_id": 2, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-21T21:23:32", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000011", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-22T01:23:32.457175", "notas_internas": null, "numero_factura": "1000004", "descuento_monto": 300.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 9700.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-01-22T01:23:32.457175", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-01-22 01:23:32.457175
259	\N	\N	ventas	UPDATE	29	{"id": 29, "iva_5": 0.00, "total": 10000.00, "estado": "PENDIENTE", "iva_10": 909.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 10000.00, "timbrado": "1234567", "tipo_pago": "CONTADO", "total_iva": 909.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-20T09:17:25", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000009", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-20T13:17:25.89267", "notas_internas": null, "numero_factura": "1000002", "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 10000.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-01-20T13:17:25.89267", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	{"id": 29, "iva_5": 0.00, "total": 10000.00, "estado": "COMPLETADA", "iva_10": 909.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 10000.00, "timbrado": "1234567", "tipo_pago": "CONTADO", "total_iva": 909.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-20T09:17:25", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 10000.00, "numero_venta": "VTA-00000009", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-20T13:17:25.89267", "notas_internas": null, "numero_factura": "1000002", "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 0.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-01-20T13:17:25.89267", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-01-20 13:17:25.89267
273	\N	\N	ordenes_servicio	UPDATE	1	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 9, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 90909.19, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 90909.19, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 1000000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 1000000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T20:07:36.476129", "problema_reportado": "cvbnm,", "costo_real_subtotal": 909090.81, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 1000000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 909090.81, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 1000000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 9, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 100000.10, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 100000.10, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 1100000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 1100000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T20:08:49.320675", "problema_reportado": "cvbnm,", "costo_real_subtotal": 999999.90, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 1100000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 999999.90, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 1100000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	\N	\N	\N	2026-01-20 20:08:49.320675
260	\N	\N	ventas	INSERT	30	\N	{"id": 30, "iva_5": 0.00, "total": 155200.00, "estado": "PENDIENTE", "iva_10": 14109.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 155200.00, "timbrado": "1234567", "tipo_pago": "CONTADO", "total_iva": 14109.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-20T11:50:34", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000010", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-20T15:50:34.952161", "notas_internas": null, "numero_factura": "1000003", "descuento_monto": 4800.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 155200.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-01-20T15:50:34.952161", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-01-20 15:50:34.952161
261	\N	\N	ventas	UPDATE	30	{"id": 30, "iva_5": 0.00, "total": 155200.00, "estado": "PENDIENTE", "iva_10": 14109.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 155200.00, "timbrado": "1234567", "tipo_pago": "CONTADO", "total_iva": 14109.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-20T11:50:34", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000010", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-20T15:50:34.952161", "notas_internas": null, "numero_factura": "1000003", "descuento_monto": 4800.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 155200.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-01-20T15:50:34.952161", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	{"id": 30, "iva_5": 0.00, "total": 155200.00, "estado": "COMPLETADA", "iva_10": 14109.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 155200.00, "timbrado": "1234567", "tipo_pago": "CONTADO", "total_iva": 14109.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-20T11:50:34", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 155200.00, "numero_venta": "VTA-00000010", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-20T15:50:34.952161", "notas_internas": null, "numero_factura": "1000003", "descuento_monto": 4800.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 0.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-01-20T15:50:34.952161", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-01-20 15:50:34.952161
262	1	1	empresas	UPDATE	1	{"id": "1", "ruc": "80027345-1", "logo": "1768702763_fd651597cdf06d12b10e.jpg", "email": "Arquisoft@gmail.com.py", "ciudad": "capiata", "estado": "t", "nombre": "Arquisoft", "telefono": "(021) 123-456", "timbrado": "1234567", "direccion": "ssjs", "aplica_iva": "t", "tema_oscuro": "f", "fecha_creacion": "2026-01-16 01:34:21.74548", "porcentaje_iva": "10.00", "establecimiento": "1", "moneda_principal": "PYG", "punto_expedicion": "1", "usuario_creacion": null, "dias_plazo_retiro": "30", "politica_garantia": null, "fecha_modificacion": "2026-01-18 03:19:23.675857", "protector_pantalla": null, "terminos_condiciones": null, "usa_facturacion_electronica": "f"}	{"ruc": "80027345-1", "logo": "1768922380_be83a78e92103133c926.jpg", "email": "Arquisoft@gmail.com.py", "ciudad": "capiata", "nombre": "Arquisoft", "telefono": "(021) 123-456", "timbrado": 1234567, "direccion": "ssjs", "aplica_iva": true, "porcentaje_iva": 10, "establecimiento": 1, "moneda_principal": "PYG", "punto_expedicion": 1, "dias_plazo_retiro": 30, "politica_garantia": null, "terminos_condiciones": null, "usa_facturacion_electronica": false}	::1	\N	\N	2026-01-20 12:19:40
263	\N	\N	ordenes_servicio	UPDATE	1	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 2, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 0.00, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 9091.00, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 0.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 100000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-19T00:54:27.827147", "problema_reportado": "cvbnm,", "costo_real_subtotal": 0.00, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 0.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 100000.00, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 100000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 2, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 18181.91, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 18181.91, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 200000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 200000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T19:25:50.667057", "problema_reportado": "cvbnm,", "costo_real_subtotal": 181818.09, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 200000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 181818.09, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 200000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	\N	\N	\N	2026-01-20 19:25:50.667057
264	\N	\N	ordenes_servicio	UPDATE	1	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 2, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 18181.91, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 18181.91, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 200000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 200000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T19:25:50.667057", "problema_reportado": "cvbnm,", "costo_real_subtotal": 181818.09, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 200000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 181818.09, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 200000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 2, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 27272.82, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 27272.82, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 300000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 300000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T19:25:51.483141", "problema_reportado": "cvbnm,", "costo_real_subtotal": 272727.18, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 300000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 272727.18, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 300000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	\N	\N	\N	2026-01-20 19:25:51.483141
265	\N	\N	ordenes_servicio	UPDATE	1	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 2, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 27272.82, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 27272.82, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 300000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 300000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T19:25:51.483141", "problema_reportado": "cvbnm,", "costo_real_subtotal": 272727.18, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 300000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 272727.18, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 300000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 2, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 36363.73, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 36363.73, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 400000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 400000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T19:25:52.30679", "problema_reportado": "cvbnm,", "costo_real_subtotal": 363636.27, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 400000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 363636.27, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 400000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	\N	\N	\N	2026-01-20 19:25:52.30679
275	\N	\N	ordenes_servicio	UPDATE	1	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 9, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 109091.01, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 109091.01, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 1200000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 1200000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T20:08:50.333621", "problema_reportado": "cvbnm,", "costo_real_subtotal": 1090908.99, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 1200000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 1090908.99, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 1200000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 9, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 100000.01, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 100000.01, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 1100000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 1100000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T20:42:38.484581", "problema_reportado": "cvbnm,", "costo_real_subtotal": 999999.99, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 1100000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 999999.99, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 1100000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	\N	\N	\N	2026-01-20 20:42:38.484581
276	\N	\N	ordenes_servicio	UPDATE	1	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 9, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 100000.01, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 100000.01, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 1100000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 1100000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T20:42:38.484581", "problema_reportado": "cvbnm,", "costo_real_subtotal": 999999.99, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 1100000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 999999.99, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 1100000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 9, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 90909.10, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 90909.10, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 1000000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 1000000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T20:42:49.858892", "problema_reportado": "cvbnm,", "costo_real_subtotal": 909090.90, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 1000000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 909090.90, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 1000000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	\N	\N	\N	2026-01-20 20:42:49.858892
277	\N	\N	ordenes_servicio	UPDATE	1	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 9, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 90909.10, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 90909.10, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 1000000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 1000000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T20:42:49.858892", "problema_reportado": "cvbnm,", "costo_real_subtotal": 909090.90, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 1000000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 909090.90, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 1000000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 9, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 81818.19, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 81818.19, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 900000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 900000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T21:35:39.088199", "problema_reportado": "cvbnm,", "costo_real_subtotal": 818181.81, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 900000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 818181.81, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 900000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	\N	\N	\N	2026-01-20 21:35:39.088199
278	\N	\N	ordenes_servicio	UPDATE	1	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 9, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 81818.19, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 81818.19, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 900000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 900000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T21:35:39.088199", "problema_reportado": "cvbnm,", "costo_real_subtotal": 818181.81, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 900000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 818181.81, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 900000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 9, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 72727.28, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 72727.28, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 800000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 800000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T21:35:39.768876", "problema_reportado": "cvbnm,", "costo_real_subtotal": 727272.72, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 800000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 727272.72, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 800000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	\N	\N	\N	2026-01-20 21:35:39.768876
279	\N	\N	ordenes_servicio	UPDATE	1	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 9, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 72727.28, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 72727.28, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 800000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 800000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T21:35:39.768876", "problema_reportado": "cvbnm,", "costo_real_subtotal": 727272.72, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 800000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 727272.72, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 800000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 9, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 63636.37, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 63636.37, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 700000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 700000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T21:35:42.171335", "problema_reportado": "cvbnm,", "costo_real_subtotal": 636363.63, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 700000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 636363.63, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 700000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	\N	\N	\N	2026-01-20 21:35:42.171335
280	\N	\N	ordenes_servicio	UPDATE	1	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 9, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 63636.37, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 63636.37, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 700000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 700000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T21:35:42.171335", "problema_reportado": "cvbnm,", "costo_real_subtotal": 636363.63, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 700000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 636363.63, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 700000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 9, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 54545.46, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 54545.46, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 600000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 600000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T21:35:47.205838", "problema_reportado": "cvbnm,", "costo_real_subtotal": 545454.54, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 600000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 545454.54, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 600000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	\N	\N	\N	2026-01-20 21:35:47.205838
281	\N	\N	ordenes_servicio	UPDATE	1	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 9, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 54545.46, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 54545.46, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 600000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 600000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T21:35:47.205838", "problema_reportado": "cvbnm,", "costo_real_subtotal": 545454.54, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 600000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 545454.54, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 600000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 9, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 45454.55, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 45454.55, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 500000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 500000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T21:35:59.96506", "problema_reportado": "cvbnm,", "costo_real_subtotal": 454545.45, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 500000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 454545.45, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 500000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	\N	\N	\N	2026-01-20 21:35:59.96506
282	\N	\N	ordenes_servicio	UPDATE	1	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 9, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 45454.55, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 45454.55, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 500000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 500000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T21:35:59.96506", "problema_reportado": "cvbnm,", "costo_real_subtotal": 454545.45, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 500000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 454545.45, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 500000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 9, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 36363.64, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 36363.64, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 400000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 400000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T21:36:03.448038", "problema_reportado": "cvbnm,", "costo_real_subtotal": 363636.36, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 400000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 363636.36, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 400000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	\N	\N	\N	2026-01-20 21:36:03.448038
283	\N	\N	ordenes_servicio	UPDATE	1	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 9, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 36363.64, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 36363.64, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 400000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 400000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T21:36:03.448038", "problema_reportado": "cvbnm,", "costo_real_subtotal": 363636.36, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 400000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 363636.36, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 400000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 9, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 27272.73, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 27272.73, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 300000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 300000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T21:36:08.127067", "problema_reportado": "cvbnm,", "costo_real_subtotal": 272727.27, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 300000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 272727.27, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 300000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	\N	\N	\N	2026-01-20 21:36:08.127067
284	\N	\N	ordenes_servicio	UPDATE	1	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 9, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 27272.73, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 27272.73, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 300000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 300000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T21:36:08.127067", "problema_reportado": "cvbnm,", "costo_real_subtotal": 272727.27, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 300000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 272727.27, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 300000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 9, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 18181.82, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 18181.82, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 200000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 200000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T21:36:13.203412", "problema_reportado": "cvbnm,", "costo_real_subtotal": 181818.18, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 200000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 181818.18, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 200000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	\N	\N	\N	2026-01-20 21:36:13.203412
285	\N	\N	ordenes_servicio	UPDATE	1	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 9, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 18181.82, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 18181.82, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 200000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 200000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T21:36:13.203412", "problema_reportado": "cvbnm,", "costo_real_subtotal": 181818.18, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 200000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 181818.18, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 200000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 9, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 9090.91, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 9090.91, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 100000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 100000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T21:36:17.808134", "problema_reportado": "cvbnm,", "costo_real_subtotal": 90909.09, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 100000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 90909.09, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 100000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	\N	\N	\N	2026-01-20 21:36:17.808134
286	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-20T09:17:00", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-20T13:17:00.288314"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-20T21:40:28", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-21T01:40:28.648394"}	\N	\N	\N	2026-01-21 01:40:28.648394
287	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36	\N	2026-01-20 21:40:28
288	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-20T21:40:28", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-21T01:40:28.648394"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-21T11:13:29", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-21T15:13:29.815925"}	\N	\N	\N	2026-01-21 15:13:29.815925
289	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36	\N	2026-01-21 11:13:29
290	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-21T11:13:29", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-21T15:13:29.815925"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-21T11:13:30", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-21T15:13:30.361902"}	\N	\N	\N	2026-01-21 15:13:30.361902
291	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36	\N	2026-01-21 11:13:30
292	1	1	ventas	DELETE	22	{"id": "22", "iva_5": 0, "total": 3818181.82, "estado": "COMPLETADA", "iva_10": 318181.82, "moneda": "PYG", "vuelto": 0.18, "subtotal": 3500000, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 318181.82, "cliente_id": 2, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-17 22:31:59", "sucursal_id": 1, "tipo_cambio": 1, "vendedor_id": 1, "monto_pagado": 3818182, "numero_venta": "VTA-00000002", "origen_venta": "SUCURSAL", "observaciones": "", "recargo_monto": 0, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-18 02:31:59.048324", "notas_internas": null, "numero_factura": null, "descuento_monto": 0, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": -0.18, "subtotal_exento": 0, "motivo_anulacion": null, "requiere_entrega": "f", "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-01-18 02:31:59.048324", "recargo_porcentaje": 0, "subtotal_gravado_5": 0, "subtotal_gravado_10": 3500000, "cantidad_impresiones": 0, "descuento_porcentaje": 0, "total_moneda_principal": 3818181.82, "fecha_entrega_programada": null}	\N	::1	\N	Anulación: gjhgygu (Admin)	2026-01-21 15:23:52.733079
293	\N	\N	ventas	DELETE	22	{"id": 22, "iva_5": 0.00, "total": 3818181.82, "estado": "COMPLETADA", "iva_10": 318181.82, "moneda": "PYG", "vuelto": 0.18, "subtotal": 3500000.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 318181.82, "cliente_id": 2, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-17T22:31:59", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 3818182.00, "numero_venta": "VTA-00000002", "origen_venta": "SUCURSAL", "observaciones": "", "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-18T02:31:59.048324", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": -0.18, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-01-18T02:31:59.048324", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 3500000.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": 3818181.82, "fecha_entrega_programada": null}	\N	\N	\N	\N	2026-01-21 15:23:52.733079
318	\N	\N	ordenes_servicio	UPDATE	1	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 6, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": "sdfghjkl;dfghjkl", "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 9090.91, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 9090.91, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 100000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 100000.00, "solucion_aplicada": "dfghjkldfghjk", "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-22T02:45:21.482895", "problema_reportado": "cvbnm,", "costo_real_subtotal": 90909.09, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 100000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 90909.09, "observaciones_cliente": "dfghj", "observaciones_tecnico": "erthjkdfghj", "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 100000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 6, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": "sdfghjkl;dfghjkl", "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 9090.91, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 9090.91, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 100000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 100000.00, "solucion_aplicada": "dfghjkldfghjk", "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-22T02:45:21.482895", "problema_reportado": "cvbnm,", "costo_real_subtotal": 90909.09, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 100000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 90909.09, "observaciones_cliente": "dfghj", "observaciones_tecnico": "erthjkdfghj", "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 100000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	\N	\N	\N	2026-01-22 02:45:21.482895
294	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-21T11:13:30", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-21T15:13:30.361902"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-21T11:39:40", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-21T15:39:40.950601"}	\N	\N	\N	2026-01-21 15:39:40.950601
295	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 OPR/126.0.0.0	\N	2026-01-21 11:39:40
296	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-21T11:39:40", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-21T15:39:40.950601"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-21T14:09:55", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-21T18:09:55.5949"}	\N	\N	\N	2026-01-21 18:09:55.5949
297	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 OPR/126.0.0.0	\N	2026-01-21 14:09:55
298	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-21T14:09:55", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-21T18:09:55.5949"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-21T14:41:32", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-21T18:41:32.040451"}	\N	\N	\N	2026-01-21 18:41:32.040451
299	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0	\N	2026-01-21 14:41:32
300	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-21T14:41:32", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-21T18:41:32.040451"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-21T14:44:44", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-21T18:44:44.648556"}	\N	\N	\N	2026-01-21 18:44:44.648556
301	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36	\N	2026-01-21 14:44:44
302	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-21T14:44:44", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-21T18:44:44.648556"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-21T15:08:24", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-21T19:08:24.934997"}	\N	\N	\N	2026-01-21 19:08:24.934997
303	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0	\N	2026-01-21 15:08:24
304	\N	\N	ordenes_servicio	UPDATE	1	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 9, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 9090.91, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 9090.91, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 100000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 100000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-20T21:36:17.808134", "problema_reportado": "cvbnm,", "costo_real_subtotal": 90909.09, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 100000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 90909.09, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 100000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 2, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 9090.91, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 9090.91, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 100000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 100000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-21T21:01:53.329499", "problema_reportado": "cvbnm,", "costo_real_subtotal": 90909.09, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 100000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 90909.09, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 100000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	\N	\N	\N	2026-01-21 21:01:53.329499
305	\N	\N	ordenes_servicio	UPDATE	1	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 2, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 9090.91, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 9090.91, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 100000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 100000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-21T21:01:53.329499", "problema_reportado": "cvbnm,", "costo_real_subtotal": 90909.09, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 100000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 90909.09, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 100000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 6, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 9090.91, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 9090.91, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 100000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 100000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-21T21:02:10.113618", "problema_reportado": "cvbnm,", "costo_real_subtotal": 90909.09, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 100000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 90909.09, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 100000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	\N	\N	\N	2026-01-21 21:02:10.113618
308	\N	\N	ventas	UPDATE	31	{"id": 31, "iva_5": 0.00, "total": 9700.00, "estado": "PENDIENTE", "iva_10": 882.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 9700.00, "timbrado": "1234567", "tipo_pago": "CONTADO", "total_iva": 882.00, "cliente_id": 2, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-21T21:23:32", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000011", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-22T01:23:32.457175", "notas_internas": null, "numero_factura": "1000004", "descuento_monto": 300.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 9700.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-01-22T01:23:32.457175", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	{"id": 31, "iva_5": 0.00, "total": 9700.00, "estado": "COMPLETADA", "iva_10": 882.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 9700.00, "timbrado": "1234567", "tipo_pago": "CONTADO", "total_iva": 882.00, "cliente_id": 2, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-21T21:23:32", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 9700.00, "numero_venta": "VTA-00000011", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-22T01:23:32.457175", "notas_internas": null, "numero_factura": "1000004", "descuento_monto": 300.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 0.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-01-22T01:23:32.457175", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-01-22 01:23:32.457175
312	\N	\N	ventas	INSERT	35	\N	{"id": 35, "iva_5": 0.00, "total": 10000.00, "estado": "COMPLETADA", "iva_10": 0.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 9090.91, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 909.09, "cliente_id": 2, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-21T21:48:19", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": null, "monto_pagado": 10000.00, "numero_venta": "NC-00000001", "origen_venta": "SUCURSAL", "observaciones": "Devolución de venta #VTA-00000011. Motivo: asdfghjklqsdfghjkkasd", "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-21T21:48:19", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 0.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "NOTA_CREDITO", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-01-21T21:48:19", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-01-22 01:48:19.636804
313	\N	\N	ventas	UPDATE	31	{"id": 31, "iva_5": 0.00, "total": 9700.00, "estado": "COMPLETADA", "iva_10": 882.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 9700.00, "timbrado": "1234567", "tipo_pago": "CONTADO", "total_iva": 882.00, "cliente_id": 2, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-21T21:23:32", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 9700.00, "numero_venta": "VTA-00000011", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-22T01:23:32.457175", "notas_internas": null, "numero_factura": "1000004", "descuento_monto": 300.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 0.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-01-22T01:23:32.457175", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	{"id": 31, "iva_5": 0.00, "total": 9700.00, "estado": "DEVUELTA_TOTAL", "iva_10": 882.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 9700.00, "timbrado": "1234567", "tipo_pago": "CONTADO", "total_iva": 882.00, "cliente_id": 2, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-21T21:23:32", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 9700.00, "numero_venta": "VTA-00000011", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-22T01:23:32.457175", "notas_internas": null, "numero_factura": "1000004", "descuento_monto": 300.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 0.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-01-21T21:48:19", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-01-22 01:48:19.636804
314	\N	\N	ventas	INSERT	36	\N	{"id": 36, "iva_5": 0.00, "total": 20000.00, "estado": "PENDIENTE", "iva_10": 1818.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 20000.00, "timbrado": "1234567", "tipo_pago": "CONTADO", "total_iva": 1818.00, "cliente_id": 1, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-21T22:36:03", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000001", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-22T02:36:03.385359", "notas_internas": null, "numero_factura": "1000005", "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 20000.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-01-22T02:36:03.385359", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-01-22 02:36:03.385359
315	\N	\N	clientes	UPDATE	1	{"ci": null, "id": 1, "ruc": "80123456-1", "foto": null, "sexo": null, "email": "otra@empresa.com.py", "barrio": null, "ciudad": null, "codigo": "CLI-003", "estado": true, "nombre": "Empresa Ejemplo Dos S.A.", "celular": "0972778415", "apellido": null, "telefono": "0972778415", "direccion": "Av. General Santos 123", "etiquetas": null, "empresa_id": 1, "departamento": null, "dias_credito": 10, "razon_social": "Empresa Ejemplo Dos S.A.", "tipo_cliente": "EMPRESA", "observaciones": null, "total_compras": 0.00, "fecha_creacion": "2026-01-16T01:34:21.74548", "limite_credito": 10000000.00, "notas_internas": null, "lista_precio_id": null, "nombre_fantasia": null, "cantidad_compras": 0, "fecha_nacimiento": null, "usuario_creacion": null, "categoria_cliente": "VIP", "credito_utilizado": 0.00, "credito_disponible": 10000000.00, "fecha_modificacion": "2026-01-20T01:25:14.284264", "tipo_contribuyente": "CONTRIBUYENTE", "fecha_ultima_compra": null, "descuento_porcentaje": 0.00, "referencia_direccion": null, "vendedor_asignado_id": null}	{"ci": null, "id": 1, "ruc": "80123456-1", "foto": null, "sexo": null, "email": "otra@empresa.com.py", "barrio": null, "ciudad": null, "codigo": "CLI-003", "estado": true, "nombre": "Empresa Ejemplo Dos S.A.", "celular": "0972778415", "apellido": null, "telefono": "0972778415", "direccion": "Av. General Santos 123", "etiquetas": null, "empresa_id": 1, "departamento": null, "dias_credito": 10, "razon_social": "Empresa Ejemplo Dos S.A.", "tipo_cliente": "EMPRESA", "observaciones": null, "total_compras": 0.00, "fecha_creacion": "2026-01-16T01:34:21.74548", "limite_credito": 10000000.00, "notas_internas": null, "lista_precio_id": null, "nombre_fantasia": null, "cantidad_compras": 0, "fecha_nacimiento": null, "usuario_creacion": null, "categoria_cliente": "VIP", "credito_utilizado": 0.00, "credito_disponible": 10000000.00, "fecha_modificacion": "2026-01-22T02:36:03.385359", "tipo_contribuyente": "CONTRIBUYENTE", "fecha_ultima_compra": null, "descuento_porcentaje": 0.00, "referencia_direccion": null, "vendedor_asignado_id": null}	\N	\N	\N	2026-01-22 02:36:03.385359
316	\N	\N	ventas	UPDATE	36	{"id": 36, "iva_5": 0.00, "total": 20000.00, "estado": "PENDIENTE", "iva_10": 1818.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 20000.00, "timbrado": "1234567", "tipo_pago": "CONTADO", "total_iva": 1818.00, "cliente_id": 1, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-21T22:36:03", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000001", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-22T02:36:03.385359", "notas_internas": null, "numero_factura": "1000005", "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 20000.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-01-22T02:36:03.385359", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	{"id": 36, "iva_5": 0.00, "total": 20000.00, "estado": "COMPLETADA", "iva_10": 1818.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 20000.00, "timbrado": "1234567", "tipo_pago": "CONTADO", "total_iva": 1818.00, "cliente_id": 1, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-21T22:36:03", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 20000.00, "numero_venta": "VTA-00000001", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-22T02:36:03.385359", "notas_internas": null, "numero_factura": "1000005", "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 0.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-01-22T02:36:03.385359", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-01-22 02:36:03.385359
317	\N	\N	ordenes_servicio	UPDATE	1	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 6, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": null, "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 9090.91, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 9090.91, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 100000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 100000.00, "solucion_aplicada": null, "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-21T21:02:10.113618", "problema_reportado": "cvbnm,", "costo_real_subtotal": 90909.09, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 100000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 90909.09, "observaciones_cliente": "dfghj", "observaciones_tecnico": null, "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 100000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 6, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": "sdfghjkl;dfghjkl", "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 9090.91, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 9090.91, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 100000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 100000.00, "solucion_aplicada": "dfghjkldfghjk", "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-22T02:45:21.482895", "problema_reportado": "cvbnm,", "costo_real_subtotal": 90909.09, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 100000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 90909.09, "observaciones_cliente": "dfghj", "observaciones_tecnico": "erthjkdfghj", "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 100000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	\N	\N	\N	2026-01-22 02:45:21.482895
320	\N	\N	ordenes_servicio	UPDATE	1	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 6, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": "sdfghjkl;dfghjkl", "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 9090.91, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 9090.91, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 100000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 100000.00, "solucion_aplicada": "dfghjkldfghjk", "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-22T02:45:21.482895", "problema_reportado": "cvbnm,", "costo_real_subtotal": 90909.09, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 100000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 90909.09, "observaciones_cliente": "dfghj", "observaciones_tecnico": "erthjkdfghj", "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 100000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 7, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": "sdfghjkl;dfghjkl", "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 9090.91, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 9090.91, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 100000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 100000.00, "solucion_aplicada": "dfghjkldfghjk", "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-22T02:47:43.783367", "problema_reportado": "cvbnm,", "costo_real_subtotal": 90909.09, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 100000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 90909.09, "observaciones_cliente": "dfghj", "observaciones_tecnico": "erthjkdfghj", "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 100000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	\N	\N	\N	2026-01-22 02:47:43.783367
321	\N	\N	ordenes_servicio	UPDATE	1	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 7, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": "sdfghjkl;dfghjkl", "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 9090.91, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 9090.91, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 100000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 100000.00, "solucion_aplicada": "dfghjkldfghjk", "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-22T02:47:43.783367", "problema_reportado": "cvbnm,", "costo_real_subtotal": 90909.09, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 100000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 90909.09, "observaciones_cliente": "dfghj", "observaciones_tecnico": "erthjkdfghj", "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 100000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 7, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": "sdfghjkl;dfghjkl", "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 54545.46, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 54545.46, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 600000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 600000.00, "solucion_aplicada": "dfghjkldfghjk", "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-22T02:48:14.211903", "problema_reportado": "cvbnm,", "costo_real_subtotal": 545454.54, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 600000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 545454.54, "observaciones_cliente": "dfghj", "observaciones_tecnico": "erthjkdfghj", "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 600000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	\N	\N	\N	2026-01-22 02:48:14.211903
322	\N	\N	usuarios	UPDATE	6	{"ci": "87664647", "id": 6, "email": "canterol888@gmail.com", "estado": true, "nombre": "Luis Alberto", "rol_id": 3, "apellido": "Cantero Britez", "password": "$2y$10$008tyi7aX8YBCkZl1jZ6Eu6jPWha6DkDmBDA/hXdhC7HiwQCOGXa.", "telefono": "+595971414482", "username": "LuiyiCantero", "direccion": null, "empresa_id": 3, "foto_perfil": null, "sucursal_id": 4, "tema_oscuro": null, "preferencias": null, "firma_digital": null, "ultimo_acceso": "2026-01-18T23:37:11", "fecha_creacion": "2026-01-19T03:36:37.495024", "usuario_creacion": 1, "fecha_modificacion": "2026-01-19T03:37:11.796763"}	{"ci": "87664647", "id": 6, "email": "canterol888@gmail.com", "estado": true, "nombre": "Luis Alberto", "rol_id": 3, "apellido": "Cantero Britez", "password": "$2y$10$WiOFDwIcHdrMu4MXEM5ZwOX50.7kwpk4c1psNWl4udCvCjfp5kEe2", "telefono": "+595971414482", "username": "LuiyiCantero", "direccion": null, "empresa_id": 3, "foto_perfil": null, "sucursal_id": 4, "tema_oscuro": null, "preferencias": null, "firma_digital": null, "ultimo_acceso": "2026-01-18T23:37:11", "fecha_creacion": "2026-01-19T03:36:37.495024", "usuario_creacion": 1, "fecha_modificacion": "2026-01-22T03:05:06.100884"}	\N	\N	\N	2026-01-22 03:05:06.100884
323	3	1	usuarios	UPDATE	6	{"ci": "87664647", "id": "6", "email": "canterol888@gmail.com", "estado": "t", "nombre": "Luis Alberto", "rol_id": "3", "apellido": "Cantero Britez", "password": "$2y$10$008tyi7aX8YBCkZl1jZ6Eu6jPWha6DkDmBDA/hXdhC7HiwQCOGXa.", "telefono": "+595971414482", "username": "LuiyiCantero", "direccion": null, "empresa_id": "3", "foto_perfil": null, "sucursal_id": "4", "tema_oscuro": null, "preferencias": null, "firma_digital": null, "ultimo_acceso": "2026-01-18 23:37:11", "fecha_creacion": "2026-01-19 03:36:37.495024", "usuario_creacion": "1", "fecha_modificacion": "2026-01-19 03:37:11.796763"}	{"ci": "87664647", "email": "canterol888@gmail.com", "nombre": "Luis Alberto", "rol_id": 3, "apellido": "Cantero Britez", "password": "Luis123456", "telefono": "+595971414482", "username": "LuiyiCantero", "direccion": null, "empresa_id": 3, "sucursal_id": 4}	::1	\N	\N	2026-01-21 23:05:06
324	\N	\N	usuarios	UPDATE	6	{"ci": "87664647", "id": 6, "email": "canterol888@gmail.com", "estado": true, "nombre": "Luis Alberto", "rol_id": 3, "apellido": "Cantero Britez", "password": "$2y$10$WiOFDwIcHdrMu4MXEM5ZwOX50.7kwpk4c1psNWl4udCvCjfp5kEe2", "telefono": "+595971414482", "username": "LuiyiCantero", "direccion": null, "empresa_id": 3, "foto_perfil": null, "sucursal_id": 4, "tema_oscuro": null, "preferencias": null, "firma_digital": null, "ultimo_acceso": "2026-01-18T23:37:11", "fecha_creacion": "2026-01-19T03:36:37.495024", "usuario_creacion": 1, "fecha_modificacion": "2026-01-22T03:05:06.100884"}	{"ci": "87664647", "id": 6, "email": "canterol888@gmail.com", "estado": true, "nombre": "Luis Alberto", "rol_id": 3, "apellido": "Cantero Britez", "password": "$2y$10$WiOFDwIcHdrMu4MXEM5ZwOX50.7kwpk4c1psNWl4udCvCjfp5kEe2", "telefono": "+595971414482", "username": "LuiyiCantero", "direccion": null, "empresa_id": 3, "foto_perfil": null, "sucursal_id": 4, "tema_oscuro": null, "preferencias": null, "firma_digital": null, "ultimo_acceso": "2026-01-21T23:05:29", "fecha_creacion": "2026-01-19T03:36:37.495024", "usuario_creacion": 1, "fecha_modificacion": "2026-01-22T03:05:29.473784"}	\N	\N	\N	2026-01-22 03:05:29.473784
325	3	6	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0	\N	2026-01-21 23:05:29
326	\N	\N	usuarios	UPDATE	6	{"ci": "87664647", "id": 6, "email": "canterol888@gmail.com", "estado": true, "nombre": "Luis Alberto", "rol_id": 3, "apellido": "Cantero Britez", "password": "$2y$10$WiOFDwIcHdrMu4MXEM5ZwOX50.7kwpk4c1psNWl4udCvCjfp5kEe2", "telefono": "+595971414482", "username": "LuiyiCantero", "direccion": null, "empresa_id": 3, "foto_perfil": null, "sucursal_id": 4, "tema_oscuro": null, "preferencias": null, "firma_digital": null, "ultimo_acceso": "2026-01-21T23:05:29", "fecha_creacion": "2026-01-19T03:36:37.495024", "usuario_creacion": 1, "fecha_modificacion": "2026-01-22T03:05:29.473784"}	{"ci": "87664647", "id": 6, "email": "canterol888@gmail.com", "estado": true, "nombre": "Luis Alberto", "rol_id": 3, "apellido": "Cantero Britez", "password": "$2y$10$WiOFDwIcHdrMu4MXEM5ZwOX50.7kwpk4c1psNWl4udCvCjfp5kEe2", "telefono": "+595971414482", "username": "LuiyiCantero", "direccion": null, "empresa_id": 3, "foto_perfil": "1769047807_0d0e1deb4262832de5ae.jpg", "sucursal_id": 4, "tema_oscuro": null, "preferencias": null, "firma_digital": null, "ultimo_acceso": "2026-01-21T23:05:29", "fecha_creacion": "2026-01-19T03:36:37.495024", "usuario_creacion": 1, "fecha_modificacion": "2026-01-22T03:10:07.743571"}	\N	\N	\N	2026-01-22 03:10:07.743571
327	3	6	usuarios	UPDATE	6	\N	{"foto_perfil": "1769047807_0d0e1deb4262832de5ae.jpg"}	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0	\N	2026-01-21 23:10:07
328	\N	\N	usuarios	UPDATE	6	{"ci": "87664647", "id": 6, "email": "canterol888@gmail.com", "estado": true, "nombre": "Luis Alberto", "rol_id": 3, "apellido": "Cantero Britez", "password": "$2y$10$WiOFDwIcHdrMu4MXEM5ZwOX50.7kwpk4c1psNWl4udCvCjfp5kEe2", "telefono": "+595971414482", "username": "LuiyiCantero", "direccion": null, "empresa_id": 3, "foto_perfil": "1769047807_0d0e1deb4262832de5ae.jpg", "sucursal_id": 4, "tema_oscuro": null, "preferencias": null, "firma_digital": null, "ultimo_acceso": "2026-01-21T23:05:29", "fecha_creacion": "2026-01-19T03:36:37.495024", "usuario_creacion": 1, "fecha_modificacion": "2026-01-22T03:10:07.743571"}	{"ci": "87664647", "id": 6, "email": "canterol888@gmail.com", "estado": true, "nombre": "Luis Alberto", "rol_id": 3, "apellido": "Cantero Britez", "password": "$2y$10$WiOFDwIcHdrMu4MXEM5ZwOX50.7kwpk4c1psNWl4udCvCjfp5kEe2", "telefono": "+595971414482", "username": "LuiyiCantero", "direccion": null, "empresa_id": 3, "foto_perfil": "1769047807_0d0e1deb4262832de5ae.jpg", "sucursal_id": 4, "tema_oscuro": null, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-21T23:05:29", "fecha_creacion": "2026-01-19T03:36:37.495024", "usuario_creacion": 1, "fecha_modificacion": "2026-01-22T03:10:21.727319"}	\N	\N	\N	2026-01-22 03:10:21.727319
329	3	6	usuarios	UPDATE	6	\N	{"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0	\N	2026-01-21 23:10:21
330	3	6	usuarios	UPDATE	6	\N	{"ci": "87664647", "email": "canterol888@gmail.com", "nombre": "Luis Alberto", "apellido": "Cantero Britez", "telefono": "+595971414482", "direccion": "nemby", "fecha_modificacion": "2026-01-21 23:11:03"}	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0	\N	2026-01-21 23:11:03
331	\N	\N	usuarios	UPDATE	6	{"ci": "87664647", "id": 6, "email": "canterol888@gmail.com", "estado": true, "nombre": "Luis Alberto", "rol_id": 3, "apellido": "Cantero Britez", "password": "$2y$10$WiOFDwIcHdrMu4MXEM5ZwOX50.7kwpk4c1psNWl4udCvCjfp5kEe2", "telefono": "+595971414482", "username": "LuiyiCantero", "direccion": null, "empresa_id": 3, "foto_perfil": "1769047807_0d0e1deb4262832de5ae.jpg", "sucursal_id": 4, "tema_oscuro": null, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-21T23:05:29", "fecha_creacion": "2026-01-19T03:36:37.495024", "usuario_creacion": 1, "fecha_modificacion": "2026-01-22T03:10:21.727319"}	{"ci": "87664647", "id": 6, "email": "canterol888@gmail.com", "estado": true, "nombre": "Luis Alberto", "rol_id": 3, "apellido": "Cantero Britez", "password": "$2y$10$WiOFDwIcHdrMu4MXEM5ZwOX50.7kwpk4c1psNWl4udCvCjfp5kEe2", "telefono": "+595971414482", "username": "LuiyiCantero", "direccion": null, "empresa_id": 3, "foto_perfil": "1769047807_0d0e1deb4262832de5ae.jpg", "sucursal_id": 4, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-21T23:05:29", "fecha_creacion": "2026-01-19T03:36:37.495024", "usuario_creacion": 1, "fecha_modificacion": "2026-01-22T03:11:28.225459"}	\N	\N	\N	2026-01-22 03:11:28.225459
332	\N	\N	usuarios	UPDATE	6	{"ci": "87664647", "id": 6, "email": "canterol888@gmail.com", "estado": true, "nombre": "Luis Alberto", "rol_id": 3, "apellido": "Cantero Britez", "password": "$2y$10$WiOFDwIcHdrMu4MXEM5ZwOX50.7kwpk4c1psNWl4udCvCjfp5kEe2", "telefono": "+595971414482", "username": "LuiyiCantero", "direccion": null, "empresa_id": 3, "foto_perfil": "1769047807_0d0e1deb4262832de5ae.jpg", "sucursal_id": 4, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-21T23:05:29", "fecha_creacion": "2026-01-19T03:36:37.495024", "usuario_creacion": 1, "fecha_modificacion": "2026-01-22T03:11:28.225459"}	{"ci": "87664647", "id": 6, "email": "canterol888@gmail.com", "estado": true, "nombre": "Luis Alberto", "rol_id": 3, "apellido": "Cantero Britez", "password": "$2y$10$WiOFDwIcHdrMu4MXEM5ZwOX50.7kwpk4c1psNWl4udCvCjfp5kEe2", "telefono": "+595971414482", "username": "LuiyiCantero", "direccion": null, "empresa_id": 3, "foto_perfil": "1769047807_0d0e1deb4262832de5ae.jpg", "sucursal_id": 4, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-21T23:05:29", "fecha_creacion": "2026-01-19T03:36:37.495024", "usuario_creacion": 1, "fecha_modificacion": "2026-01-22T03:11:28.238711"}	\N	\N	\N	2026-01-22 03:11:28.238711
333	\N	\N	usuarios	UPDATE	6	{"ci": "87664647", "id": 6, "email": "canterol888@gmail.com", "estado": true, "nombre": "Luis Alberto", "rol_id": 3, "apellido": "Cantero Britez", "password": "$2y$10$WiOFDwIcHdrMu4MXEM5ZwOX50.7kwpk4c1psNWl4udCvCjfp5kEe2", "telefono": "+595971414482", "username": "LuiyiCantero", "direccion": null, "empresa_id": 3, "foto_perfil": "1769047807_0d0e1deb4262832de5ae.jpg", "sucursal_id": 4, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-21T23:05:29", "fecha_creacion": "2026-01-19T03:36:37.495024", "usuario_creacion": 1, "fecha_modificacion": "2026-01-22T03:11:28.238711"}	{"ci": "87664647", "id": 6, "email": "canterol888@gmail.com", "estado": true, "nombre": "Luis Alberto", "rol_id": 3, "apellido": "Cantero Britez", "password": "$2y$10$WiOFDwIcHdrMu4MXEM5ZwOX50.7kwpk4c1psNWl4udCvCjfp5kEe2", "telefono": "+595971414482", "username": "LuiyiCantero", "direccion": null, "empresa_id": 3, "foto_perfil": "1769047807_0d0e1deb4262832de5ae.jpg", "sucursal_id": 4, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-21T23:05:29", "fecha_creacion": "2026-01-19T03:36:37.495024", "usuario_creacion": 1, "fecha_modificacion": "2026-01-22T03:11:29.93279"}	\N	\N	\N	2026-01-22 03:11:29.93279
334	\N	\N	usuarios	UPDATE	6	{"ci": "87664647", "id": 6, "email": "canterol888@gmail.com", "estado": true, "nombre": "Luis Alberto", "rol_id": 3, "apellido": "Cantero Britez", "password": "$2y$10$WiOFDwIcHdrMu4MXEM5ZwOX50.7kwpk4c1psNWl4udCvCjfp5kEe2", "telefono": "+595971414482", "username": "LuiyiCantero", "direccion": null, "empresa_id": 3, "foto_perfil": "1769047807_0d0e1deb4262832de5ae.jpg", "sucursal_id": 4, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-21T23:05:29", "fecha_creacion": "2026-01-19T03:36:37.495024", "usuario_creacion": 1, "fecha_modificacion": "2026-01-22T03:11:29.93279"}	{"ci": "87664647", "id": 6, "email": "canterol888@gmail.com", "estado": true, "nombre": "Luis Alberto", "rol_id": 3, "apellido": "Cantero Britez", "password": "$2y$10$WiOFDwIcHdrMu4MXEM5ZwOX50.7kwpk4c1psNWl4udCvCjfp5kEe2", "telefono": "+595971414482", "username": "LuiyiCantero", "direccion": null, "empresa_id": 3, "foto_perfil": "1769047807_0d0e1deb4262832de5ae.jpg", "sucursal_id": 4, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-21T23:05:29", "fecha_creacion": "2026-01-19T03:36:37.495024", "usuario_creacion": 1, "fecha_modificacion": "2026-01-22T03:11:29.943727"}	\N	\N	\N	2026-01-22 03:11:29.943727
335	\N	\N	productos	INSERT	7	\N	{"id": 7, "peso": null, "tags": null, "codigo": "001", "estado": true, "moneda": "PYG", "nombre": "Placa Madre", "marca_id": 5, "tipo_iva": "GRAVADO_10", "usa_lote": false, "es_oferta": false, "usa_serie": false, "empresa_id": 3, "es_novedad": false, "descripcion": "Placa madre asus de 10 generacion", "dimensiones": null, "categoria_id": 5, "codigo_barra": "001", "es_destacado": false, "precio_venta": 900000.00, "stock_maximo": 20, "stock_minimo": 5, "observaciones": null, "precio_compra": 500000.00, "precio_minimo": 750000.00, "punto_reorden": 7, "tipo_producto": "PRODUCTO", "controla_stock": true, "fecha_creacion": "2026-01-21T23:22:04", "garantia_meses": 0, "porcentaje_iva": 10.00, "margen_utilidad": 80.00, "usa_vencimiento": false, "imagen_principal": "1769048524_af9e94e71185138264d6.jpg", "precio_mayorista": 750000.00, "unidad_medida_id": 1, "usuario_creacion": 6, "codigo_fabricante": null, "descripcion_corta": null, "fecha_modificacion": "2026-01-21T23:22:04", "permite_venta_sin_stock": false, "especificaciones_tecnicas": null}	\N	\N	\N	2026-01-22 03:22:04.081028
336	\N	\N	productos	INSERT	8	\N	{"id": 8, "peso": null, "tags": null, "codigo": "cpy-001", "estado": true, "moneda": "PYG", "nombre": "Placa Madre", "marca_id": 5, "tipo_iva": "GRAVADO_10", "usa_lote": false, "es_oferta": false, "usa_serie": false, "empresa_id": 3, "es_novedad": false, "descripcion": "Placa madre asus de 10 generacion", "dimensiones": null, "categoria_id": 5, "codigo_barra": "001", "es_destacado": false, "precio_venta": 900000.00, "stock_maximo": 20, "stock_minimo": 5, "observaciones": null, "precio_compra": 500000.00, "precio_minimo": 750000.00, "punto_reorden": 7, "tipo_producto": "PRODUCTO", "controla_stock": true, "fecha_creacion": "2026-01-21T23:28:51", "garantia_meses": 0, "porcentaje_iva": 10.00, "margen_utilidad": 80.00, "usa_vencimiento": false, "imagen_principal": "1769048931_56499ec130ec00308160.jpg", "precio_mayorista": 750000.00, "unidad_medida_id": 1, "usuario_creacion": 6, "codigo_fabricante": null, "descripcion_corta": null, "fecha_modificacion": "2026-01-21T23:28:51", "permite_venta_sin_stock": false, "especificaciones_tecnicas": null}	\N	\N	\N	2026-01-22 03:28:51.334949
342	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-21T15:08:24", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-21T19:08:24.934997"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-22T15:41:50", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-22T19:41:50.739124"}	\N	\N	\N	2026-01-22 19:41:50.739124
343	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0	\N	2026-01-22 15:41:50
344	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-22T15:41:50", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-22T19:41:50.739124"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-25T01:11:33", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-25T05:11:33.978423"}	\N	\N	\N	2026-01-25 05:11:33.978423
345	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36	\N	2026-01-25 01:11:34
346	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-25T01:11:33", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-25T05:11:33.978423"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-28T15:08:54", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-28T19:08:54.6448"}	\N	\N	\N	2026-01-28 19:08:54.6448
347	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36	\N	2026-01-28 15:08:54
348	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-28T15:08:54", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-28T19:08:54.6448"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-28T15:40:45", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-28T19:40:45.740055"}	\N	\N	\N	2026-01-28 19:40:45.740055
349	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 OPR/126.0.0.0	\N	2026-01-28 15:40:45
350	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-28T15:40:45", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-28T19:40:45.740055"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-28T18:14:13", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-28T22:14:13.877637"}	\N	\N	\N	2026-01-28 22:14:13.877637
351	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36	\N	2026-01-28 18:14:13
352	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-28T18:14:13", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-28T22:14:13.877637"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-28T18:33:52", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-28T22:33:52.026845"}	\N	\N	\N	2026-01-28 22:33:52.026845
353	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 OPR/126.0.0.0	\N	2026-01-28 18:33:52
354	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-28T18:33:52", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-28T22:33:52.026845"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-28T18:33:52", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-28T22:54:28.334232"}	\N	\N	\N	2026-01-28 22:54:28.334232
355	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-28T18:33:52", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-28T22:54:28.334232"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-28T18:33:52", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-28T22:54:28.348354"}	\N	\N	\N	2026-01-28 22:54:28.348354
356	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-28T18:33:52", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-28T22:54:28.348354"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-28T18:33:52", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-28T22:54:29.79611"}	\N	\N	\N	2026-01-28 22:54:29.79611
357	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-28T18:33:52", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-28T22:54:29.79611"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-28T18:33:52", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-28T22:54:29.805394"}	\N	\N	\N	2026-01-28 22:54:29.805394
358	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-28T18:33:52", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-28T22:54:29.805394"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-28T18:33:52", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-28T22:54:30.871007"}	\N	\N	\N	2026-01-28 22:54:30.871007
359	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-28T18:33:52", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-28T22:54:30.871007"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-28T18:33:52", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-28T22:54:30.882021"}	\N	\N	\N	2026-01-28 22:54:30.882021
360	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-28T18:33:52", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-28T22:54:30.882021"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-28T18:33:52", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-28T22:54:32.381115"}	\N	\N	\N	2026-01-28 22:54:32.381115
361	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-28T18:33:52", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-28T22:54:32.381115"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-28T18:33:52", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-28T22:54:32.394253"}	\N	\N	\N	2026-01-28 22:54:32.394253
362	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-28T18:33:52", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-28T22:54:32.394253"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-29T09:36:20", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-29T13:36:20.516577"}	\N	\N	\N	2026-01-29 13:36:20.516577
363	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36	\N	2026-01-29 09:36:20
364	\N	\N	productos	UPDATE	6	{"id": 6, "peso": null, "tags": null, "codigo": "0012", "estado": true, "moneda": "PYG", "nombre": "Samsung Gala", "marca_id": 1, "tipo_iva": "GRAVADO_10", "usa_lote": false, "es_oferta": false, "usa_serie": false, "empresa_id": 1, "es_novedad": false, "descripcion": "sfsfsfsfddd", "dimensiones": null, "categoria_id": 1, "codigo_barra": "dgh", "es_destacado": false, "precio_venta": 10000.00, "stock_maximo": 50, "stock_minimo": 5, "observaciones": null, "precio_compra": 10000.00, "precio_minimo": 10000.00, "punto_reorden": 10, "tipo_producto": "PRODUCTO", "controla_stock": true, "fecha_creacion": "2026-01-16T14:20:32", "garantia_meses": 0, "porcentaje_iva": 10.00, "margen_utilidad": 0.00, "usa_vencimiento": false, "imagen_principal": "1768584032_582e004bd9c82d0bb2ae.png", "precio_mayorista": 10000.00, "unidad_medida_id": 1, "usuario_creacion": 1, "codigo_fabricante": null, "descripcion_corta": null, "fecha_modificacion": "2026-01-16T14:20:32", "permite_venta_sin_stock": false, "especificaciones_tecnicas": null}	{"id": 6, "peso": null, "tags": null, "codigo": "0012", "estado": true, "moneda": "PYG", "nombre": "Samsung Gala", "marca_id": 1, "tipo_iva": "GRAVADO_10", "usa_lote": true, "es_oferta": false, "usa_serie": false, "empresa_id": 1, "es_novedad": false, "descripcion": "sfsfsfsfddd", "dimensiones": null, "categoria_id": 1, "codigo_barra": "2327054410383", "es_destacado": false, "precio_venta": 1000000.00, "stock_maximo": 50, "stock_minimo": 5, "observaciones": null, "precio_compra": 1000000.00, "precio_minimo": 1000000.00, "punto_reorden": 10, "tipo_producto": "PRODUCTO", "controla_stock": true, "fecha_creacion": "2026-01-16T14:20:32", "garantia_meses": 0, "porcentaje_iva": 10.00, "margen_utilidad": 0.00, "usa_vencimiento": true, "imagen_principal": "1768584032_582e004bd9c82d0bb2ae.png", "precio_mayorista": 1000000.00, "unidad_medida_id": 1, "usuario_creacion": 1, "codigo_fabricante": null, "descripcion_corta": null, "fecha_modificacion": "2026-01-29T14:17:49.921213", "permite_venta_sin_stock": true, "especificaciones_tecnicas": null}	\N	\N	\N	2026-01-29 14:17:49.921213
365	1	1	productos	UPDATE	6	{"id": "6", "peso": null, "tags": null, "codigo": "0012", "estado": "t", "moneda": "PYG", "nombre": "Samsung Gala", "marca_id": "1", "tipo_iva": "GRAVADO_10", "usa_lote": "f", "es_oferta": "f", "usa_serie": "f", "empresa_id": "1", "es_novedad": "f", "descripcion": "sfsfsfsfddd", "dimensiones": null, "categoria_id": "1", "codigo_barra": "dgh", "es_destacado": "f", "precio_venta": "10000.00", "stock_maximo": "50", "stock_minimo": "5", "observaciones": null, "precio_compra": "10000.00", "precio_minimo": "10000.00", "punto_reorden": "10", "tipo_producto": "PRODUCTO", "controla_stock": "t", "fecha_creacion": "2026-01-16 14:20:32", "garantia_meses": "0", "porcentaje_iva": "10.00", "margen_utilidad": "0.00", "usa_vencimiento": "f", "imagen_principal": "1768584032_582e004bd9c82d0bb2ae.png", "precio_mayorista": "10000.00", "unidad_medida_id": "1", "usuario_creacion": "1", "codigo_fabricante": null, "descripcion_corta": null, "fecha_modificacion": "2026-01-16 14:20:32", "permite_venta_sin_stock": "f", "especificaciones_tecnicas": null}	{"codigo": "0012", "moneda": "PYG", "nombre": "Samsung Gala", "marca_id": "1", "tipo_iva": "GRAVADO_10", "usa_lote": "t", "usa_serie": "f", "empresa_id": "1", "descripcion": "sfsfsfsfddd", "categoria_id": "1", "codigo_barra": "2327054410383", "precio_venta": 1000000, "stock_maximo": 50, "stock_minimo": 5, "precio_compra": 1000000, "precio_minimo": 1000000, "punto_reorden": 10, "tipo_producto": "PRODUCTO", "controla_stock": "t", "margen_utilidad": 0, "usa_vencimiento": "t", "precio_mayorista": 1000000, "unidad_medida_id": "1", "permite_venta_sin_stock": "t"}	::1	\N	\N	2026-01-29 14:17:49.921213
366	2	1	empresas	DELETE	2	{"id": "2", "ruc": "11156766-6", "logo": "1768598459_a7d67cc9bad19fa1bce0.jpg", "email": "albert.salo.654@gmail.com", "ciudad": "Asunción", "estado": "f", "nombre": "xxx", "telefono": "(021) 555-666", "timbrado": "1234567", "direccion": "Av. República Argentina 123", "aplica_iva": "t", "tema_oscuro": "f", "fecha_creacion": "2026-01-16 22:20:59.285386", "porcentaje_iva": "10.00", "establecimiento": "1", "moneda_principal": "PYG", "punto_expedicion": "1", "usuario_creacion": "1", "dias_plazo_retiro": "90", "politica_garantia": null, "fecha_modificacion": "2026-01-18 03:19:45.399939", "protector_pantalla": null, "terminos_condiciones": null, "usa_facturacion_electronica": "f"}	\N	::1	\N	\N	2026-01-29 10:23:24
367	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-29T09:36:20", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-29T13:36:20.516577"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-29T14:13:36", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-29T18:13:36.558966"}	\N	\N	\N	2026-01-29 18:13:36.558966
368	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-29T14:13:36", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-29T18:13:36.558966"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-29T14:13:36", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-29T18:13:36.559291"}	\N	\N	\N	2026-01-29 18:13:36.559291
369	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 OPR/126.0.0.0	\N	2026-01-29 14:13:36
370	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 OPR/126.0.0.0	\N	2026-01-29 14:13:36
371	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-29T14:13:36", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-29T18:13:36.559291"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-29T14:13:36", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-29T18:13:36.956303"}	\N	\N	\N	2026-01-29 18:13:36.956303
372	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 OPR/126.0.0.0	\N	2026-01-29 14:13:36
373	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-29T14:13:36", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-29T18:13:36.956303"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-29T14:13:37", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-29T18:13:37.548354"}	\N	\N	\N	2026-01-29 18:13:37.548354
374	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 OPR/126.0.0.0	\N	2026-01-29 14:13:37
375	\N	\N	productos	UPDATE	5	{"id": 5, "peso": null, "tags": null, "codigo": "001", "estado": true, "moneda": "PYG", "nombre": "Samsung Gala", "marca_id": 1, "tipo_iva": "GRAVADO_10", "usa_lote": false, "es_oferta": false, "usa_serie": false, "empresa_id": 1, "es_novedad": false, "descripcion": "sfsfsfsfddd", "dimensiones": null, "categoria_id": 1, "codigo_barra": "dgh", "es_destacado": false, "precio_venta": 10000.00, "stock_maximo": 50, "stock_minimo": 5, "observaciones": null, "precio_compra": 10000.00, "precio_minimo": 10000.00, "punto_reorden": 10, "tipo_producto": "PRODUCTO", "controla_stock": true, "fecha_creacion": "2026-01-16T14:19:24", "garantia_meses": 0, "porcentaje_iva": 10.00, "margen_utilidad": 0.00, "usa_vencimiento": false, "imagen_principal": "1768583964_420a7e0282cac39cc7bb.jpg", "precio_mayorista": 10000.00, "unidad_medida_id": 1, "usuario_creacion": 1, "codigo_fabricante": null, "descripcion_corta": null, "fecha_modificacion": "2026-01-16T14:19:24", "permite_venta_sin_stock": false, "especificaciones_tecnicas": null}	{"id": 5, "peso": null, "tags": null, "codigo": "001", "estado": true, "moneda": "PYG", "nombre": "Samsung Gala", "marca_id": 1, "tipo_iva": "GRAVADO_10", "usa_lote": true, "es_oferta": false, "usa_serie": false, "empresa_id": 1, "es_novedad": false, "descripcion": "sfsfsfsfddd", "dimensiones": null, "categoria_id": 1, "codigo_barra": "2770989328009", "es_destacado": false, "precio_venta": 1000000.00, "stock_maximo": 50, "stock_minimo": 5, "observaciones": null, "precio_compra": 1000000.00, "precio_minimo": 1000000.00, "punto_reorden": 10, "tipo_producto": "PRODUCTO", "controla_stock": true, "fecha_creacion": "2026-01-16T14:19:24", "garantia_meses": 0, "porcentaje_iva": 10.00, "margen_utilidad": 0.00, "usa_vencimiento": true, "imagen_principal": "1768583964_420a7e0282cac39cc7bb.jpg", "precio_mayorista": 1000000.00, "unidad_medida_id": 1, "usuario_creacion": 1, "codigo_fabricante": null, "descripcion_corta": null, "fecha_modificacion": "2026-01-29T18:14:08.177208", "permite_venta_sin_stock": true, "especificaciones_tecnicas": null}	\N	\N	\N	2026-01-29 18:14:08.177208
376	1	1	productos	UPDATE	5	{"id": "5", "peso": null, "tags": null, "codigo": "001", "estado": "t", "moneda": "PYG", "nombre": "Samsung Gala", "marca_id": "1", "tipo_iva": "GRAVADO_10", "usa_lote": "f", "es_oferta": "f", "usa_serie": "f", "empresa_id": "1", "es_novedad": "f", "descripcion": "sfsfsfsfddd", "dimensiones": null, "categoria_id": "1", "codigo_barra": "dgh", "es_destacado": "f", "precio_venta": "10000.00", "stock_maximo": "50", "stock_minimo": "5", "observaciones": null, "precio_compra": "10000.00", "precio_minimo": "10000.00", "punto_reorden": "10", "tipo_producto": "PRODUCTO", "controla_stock": "t", "fecha_creacion": "2026-01-16 14:19:24", "garantia_meses": "0", "porcentaje_iva": "10.00", "margen_utilidad": "0.00", "usa_vencimiento": "f", "imagen_principal": "1768583964_420a7e0282cac39cc7bb.jpg", "precio_mayorista": "10000.00", "unidad_medida_id": "1", "usuario_creacion": "1", "codigo_fabricante": null, "descripcion_corta": null, "fecha_modificacion": "2026-01-16 14:19:24", "permite_venta_sin_stock": "f", "especificaciones_tecnicas": null}	{"codigo": "001", "moneda": "PYG", "nombre": "Samsung Gala", "marca_id": "1", "tipo_iva": "GRAVADO_10", "usa_lote": "t", "usa_serie": "f", "empresa_id": "1", "descripcion": "sfsfsfsfddd", "categoria_id": "1", "codigo_barra": "2770989328009", "precio_venta": 1000000, "stock_maximo": 50, "stock_minimo": 5, "precio_compra": 1000000, "precio_minimo": 1000000, "punto_reorden": 10, "tipo_producto": "PRODUCTO", "controla_stock": "t", "margen_utilidad": 0, "usa_vencimiento": "t", "precio_mayorista": 1000000, "unidad_medida_id": "1", "permite_venta_sin_stock": "t"}	::1	\N	\N	2026-01-29 18:14:08.177208
377	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-29T14:13:37", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-29T18:13:37.548354"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-31T21:02:20", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-01T01:02:20.245335"}	\N	\N	\N	2026-02-01 01:02:20.245335
378	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36	\N	2026-01-31 21:02:20
379	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-31T21:02:20", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-01T01:02:20.245335"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-31T21:32:49", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-01T01:32:49.807145"}	\N	\N	\N	2026-02-01 01:32:49.807145
380	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 OPR/126.0.0.0	\N	2026-01-31 21:32:49
381	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-01-31T21:32:49", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-01T01:32:49.807145"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-01T09:57:49", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-01T13:57:49.347609"}	\N	\N	\N	2026-02-01 13:57:49.347609
382	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Cursor/2.4.22 Chrome/142.0.7444.235 Electron/39.2.7 Safari/537.36	\N	2026-02-01 09:57:49
383	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-01T09:57:49", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-01T13:57:49.347609"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-01T10:04:44", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-01T14:04:44.225419"}	\N	\N	\N	2026-02-01 14:04:44.225419
384	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36	\N	2026-02-01 10:04:44
385	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-01T10:04:44", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-01T14:04:44.225419"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-05T21:37:51", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-06T01:37:51.030199"}	\N	\N	\N	2026-02-06 01:37:51.030199
386	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36	\N	2026-02-05 21:37:51
387	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-05T21:37:51", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-06T01:37:51.030199"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-07T19:58:07", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-07T23:58:07.079353"}	\N	\N	\N	2026-02-07 23:58:07.079353
388	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36	\N	2026-02-07 19:58:07
389	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-07T19:58:07", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-07T23:58:07.079353"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-08T17:34:23", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-08T21:34:23.026303"}	\N	\N	\N	2026-02-08 21:34:23.026303
390	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36	\N	2026-02-08 17:34:23
391	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1768783057_454588b317d3943cd1cf.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-08T17:34:23", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-08T21:34:23.026303"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1770583533_dccd1abbac817a82e016.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-08T17:34:23", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-08T21:45:33.338705"}	\N	\N	\N	2026-02-08 21:45:33.338705
392	1	1	usuarios	UPDATE	1	\N	{"foto_perfil": "1770583533_dccd1abbac817a82e016.jpg"}	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36	\N	2026-02-08 17:45:33
393	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1770583533_dccd1abbac817a82e016.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-08T17:34:23", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-08T21:45:33.338705"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1770583533_dccd1abbac817a82e016.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-08T17:34:23", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-08T21:46:26.005348"}	\N	\N	\N	2026-02-08 21:46:26.005348
394	1	1	usuarios	UPDATE	1	{"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}	{"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36	\N	2026-02-08 17:46:26
395	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1770583533_dccd1abbac817a82e016.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-08T17:34:23", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-08T21:46:26.005348"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1770583533_dccd1abbac817a82e016.jpg", "sucursal_id": 1, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-08T17:34:23", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-08T21:46:38.980055"}	\N	\N	\N	2026-02-08 21:46:38.980055
396	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1770583533_dccd1abbac817a82e016.jpg", "sucursal_id": 1, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-08T17:34:23", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-08T21:46:38.980055"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1770583533_dccd1abbac817a82e016.jpg", "sucursal_id": 1, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-08T17:34:23", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-08T21:46:38.988341"}	\N	\N	\N	2026-02-08 21:46:38.988341
397	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1770583533_dccd1abbac817a82e016.jpg", "sucursal_id": 1, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-08T17:34:23", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-08T21:46:38.988341"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1770583533_dccd1abbac817a82e016.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-08T17:34:23", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-08T21:46:41.50334"}	\N	\N	\N	2026-02-08 21:46:41.50334
398	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1770583533_dccd1abbac817a82e016.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-08T17:34:23", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-08T21:46:41.50334"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1770583533_dccd1abbac817a82e016.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-08T17:34:23", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-08T21:46:41.511899"}	\N	\N	\N	2026-02-08 21:46:41.511899
399	1	1	usuarios	LOGOUT	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36	\N	2026-02-08 18:51:42
400	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1770583533_dccd1abbac817a82e016.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-08T17:34:23", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-08T21:46:41.511899"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1770583533_dccd1abbac817a82e016.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-08T18:51:46", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-08T22:51:46.650736"}	\N	\N	\N	2026-02-08 22:51:46.650736
401	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36	\N	2026-02-08 18:51:46
402	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1770583533_dccd1abbac817a82e016.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-08T18:51:46", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-08T22:51:46.650736"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1770583533_dccd1abbac817a82e016.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-09T00:56:11", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-08T21:56:11.556375"}	\N	\N	\N	2026-02-08 21:56:11.556375
403	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 OPR/126.0.0.0	\N	2026-02-09 00:56:11
441	\N	\N	ventas	INSERT	52	\N	{"id": 52, "iva_5": 0.00, "total": 400000.00, "estado": "PENDIENTE", "iva_10": 36364.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 400000.00, "timbrado": "1234567", "tipo_pago": "CONTADO", "total_iva": 36364.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T11:45:07", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000020", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T08:45:07.608553", "notas_internas": null, "numero_factura": "1000007", "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 400000.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T08:45:07.608553", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-02-09 08:45:07.608553
442	\N	\N	ventas	UPDATE	52	{"id": 52, "iva_5": 0.00, "total": 400000.00, "estado": "PENDIENTE", "iva_10": 36364.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 400000.00, "timbrado": "1234567", "tipo_pago": "CONTADO", "total_iva": 36364.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T11:45:07", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000020", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T08:45:07.608553", "notas_internas": null, "numero_factura": "1000007", "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 400000.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T08:45:07.608553", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	{"id": 52, "iva_5": 0.00, "total": 400000.00, "estado": "COMPLETADA", "iva_10": 36364.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 400000.00, "timbrado": "1234567", "tipo_pago": "CONTADO", "total_iva": 36364.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T11:45:07", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 400000.00, "numero_venta": "VTA-00000020", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T08:45:07.608553", "notas_internas": null, "numero_factura": "1000007", "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 0.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T08:45:07.608553", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-02-09 08:45:07.608553
404	\N	\N	ventas	INSERT	37	\N	{"id": 37, "iva_5": 0.00, "total": 1000000.00, "estado": "PENDIENTE", "iva_10": 90909.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 1000000.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 90909.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T00:56:56", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000002", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-08T21:56:56.036544", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 1000000.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-08T21:56:56.036544", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-02-08 21:56:56.036544
405	\N	\N	ventas	UPDATE	37	{"id": 37, "iva_5": 0.00, "total": 1000000.00, "estado": "PENDIENTE", "iva_10": 90909.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 1000000.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 90909.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T00:56:56", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000002", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-08T21:56:56.036544", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 1000000.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-08T21:56:56.036544", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	{"id": 37, "iva_5": 0.00, "total": 1000000.00, "estado": "COMPLETADA", "iva_10": 90909.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 1000000.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 90909.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T00:56:56", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 1000000.00, "numero_venta": "VTA-00000002", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-08T21:56:56.036544", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 0.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-08T21:56:56.036544", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-02-08 21:56:56.036544
443	\N	\N	ventas	INSERT	53	\N	{"id": 53, "iva_5": 0.00, "total": 400000.00, "estado": "PENDIENTE", "iva_10": 36364.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 400000.00, "timbrado": "1234567", "tipo_pago": "CONTADO", "total_iva": 36364.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T11:47:00", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000021", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T08:47:00.6594", "notas_internas": null, "numero_factura": "1000008", "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 400000.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T08:47:00.6594", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-02-09 08:47:00.6594
444	\N	\N	ventas	UPDATE	53	{"id": 53, "iva_5": 0.00, "total": 400000.00, "estado": "PENDIENTE", "iva_10": 36364.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 400000.00, "timbrado": "1234567", "tipo_pago": "CONTADO", "total_iva": 36364.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T11:47:00", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000021", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T08:47:00.6594", "notas_internas": null, "numero_factura": "1000008", "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 400000.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T08:47:00.6594", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	{"id": 53, "iva_5": 0.00, "total": 400000.00, "estado": "COMPLETADA", "iva_10": 36364.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 400000.00, "timbrado": "1234567", "tipo_pago": "CONTADO", "total_iva": 36364.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T11:47:00", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 400000.00, "numero_venta": "VTA-00000021", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T08:47:00.6594", "notas_internas": null, "numero_factura": "1000008", "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 0.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T08:47:00.6594", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-02-09 08:47:00.6594
473	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2y$10$brmGqqTAvQoVt1Psd/WYmOxhJvf16mNPVI7HEHY5yvbniKNwtOfdW", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1770583533_dccd1abbac817a82e016.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-09T12:00:59", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-09T09:00:59.668046"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2y$10$brmGqqTAvQoVt1Psd/WYmOxhJvf16mNPVI7HEHY5yvbniKNwtOfdW", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1770583533_dccd1abbac817a82e016.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-09T14:57:26", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-09T11:57:26.504527"}	\N	\N	\N	2026-02-09 11:57:26.504527
445	1	1	empresas	UPDATE	1	{"id": "1", "ruc": "80027345-1", "logo": "1768922380_be83a78e92103133c926.jpg", "email": "Arquisoft@gmail.com.py", "ciudad": "capiata", "estado": "t", "nombre": "Arquisoft", "telefono": "(021) 123-456", "timbrado": "1234567", "direccion": "ssjs", "aplica_iva": "t", "tema_oscuro": "f", "fecha_creacion": "2026-01-16 01:34:21.74548", "porcentaje_iva": "10.00", "establecimiento": "1", "moneda_principal": "PYG", "punto_expedicion": "1", "usuario_creacion": null, "dias_plazo_retiro": "30", "politica_garantia": null, "fecha_modificacion": "2026-01-20 16:19:40.032843", "protector_pantalla": null, "terminos_condiciones": null, "usa_facturacion_electronica": "f"}	{"ruc": "80027345-1", "email": "Arquisoft@gmail.com.py", "ciudad": "Ñemby", "nombre": "COMPUSURPY", "telefono": "(021) 123-456", "timbrado": 1234567, "direccion": "ACCESO SUR ,SOBRE, Independencia Nacional, Ñemby", "aplica_iva": true, "porcentaje_iva": 10, "establecimiento": 1, "moneda_principal": "PYG", "punto_expedicion": 1, "dias_plazo_retiro": 30, "politica_garantia": null, "terminos_condiciones": null, "usa_facturacion_electronica": false}	::1	\N	\N	2026-02-09 11:56:33
474	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 OPR/126.0.0.0	\N	2026-02-09 14:57:26
446	1	1	usuarios	LOGOUT	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 OPR/126.0.0.0	\N	2026-02-09 11:59:32
475	1	1	productos	INSERT	19	\N	{"codigo": "Pro-004", "estado": true, "moneda": "PYG", "nombre": "ford to on", "marca_id": "2", "tipo_iva": "GRAVADO_10", "usa_lote": "f", "usa_serie": "f", "empresa_id": "1", "descripcion": "sfsfs", "sucursal_id": "1", "categoria_id": "3", "codigo_barra": "2475373593270", "precio_venta": 2000000, "stock_maximo": 10, "stock_minimo": 2, "precio_compra": 1000000, "precio_minimo": 1750000, "punto_reorden": 5, "stock_inicial": 1, "tipo_producto": "PRODUCTO", "controla_stock": "t", "margen_utilidad": 100, "usa_vencimiento": "f", "imagen_principal": "1770660066_fa309aa89c1bf27a0bd4.png", "precio_mayorista": 1500000, "unidad_medida_id": "1", "usuario_creacion": "1", "permite_venta_sin_stock": "f"}	::1	\N	\N	2026-02-09 12:01:06.770921
512	\N	\N	ventas	INSERT	57	\N	{"id": 57, "iva_5": 0.00, "total": 400000.00, "estado": "PENDIENTE", "iva_10": 36364.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 400000.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 36364.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T16:31:53", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000025", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T13:31:53.451582", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 400000.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T13:31:53.451582", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-02-09 13:31:53.451582
513	\N	\N	ventas	UPDATE	57	{"id": 57, "iva_5": 0.00, "total": 400000.00, "estado": "PENDIENTE", "iva_10": 36364.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 400000.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 36364.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T16:31:53", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000025", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T13:31:53.451582", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 400000.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T13:31:53.451582", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	{"id": 57, "iva_5": 0.00, "total": 400000.00, "estado": "COMPLETADA", "iva_10": 36364.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 400000.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 36364.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T16:31:53", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 400000.00, "numero_venta": "VTA-00000025", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T13:31:53.451582", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 0.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T13:31:53.451582", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-02-09 13:31:53.451582
408	\N	\N	productos	UPDATE	6	{"id": 6, "peso": null, "tags": null, "codigo": "0012", "estado": true, "moneda": "PYG", "nombre": "Samsung Gala", "marca_id": 1, "tipo_iva": "GRAVADO_10", "usa_lote": true, "es_oferta": false, "usa_serie": false, "empresa_id": 1, "es_novedad": false, "descripcion": "sfsfsfsfddd", "dimensiones": null, "categoria_id": 1, "codigo_barra": "2327054410383", "es_destacado": false, "precio_venta": 1000000.00, "stock_maximo": 50, "stock_minimo": 5, "observaciones": null, "precio_compra": 1000000.00, "precio_minimo": 1000000.00, "punto_reorden": 10, "tipo_producto": "PRODUCTO", "controla_stock": true, "fecha_creacion": "2026-01-16T14:20:32", "garantia_meses": 0, "porcentaje_iva": 10.00, "margen_utilidad": 0.00, "usa_vencimiento": true, "imagen_principal": "1768584032_582e004bd9c82d0bb2ae.png", "precio_mayorista": 1000000.00, "unidad_medida_id": 1, "usuario_creacion": 1, "codigo_fabricante": null, "descripcion_corta": null, "fecha_modificacion": "2026-01-29T14:17:49.921213", "permite_venta_sin_stock": true, "especificaciones_tecnicas": null}	{"id": 6, "peso": null, "tags": null, "codigo": "0012", "estado": true, "moneda": "PYG", "nombre": "Samsung Gala", "marca_id": 1, "tipo_iva": "GRAVADO_10", "usa_lote": true, "es_oferta": false, "usa_serie": false, "empresa_id": 1, "es_novedad": false, "descripcion": "Es un gran producto todos los deberiamos poder usar", "dimensiones": null, "categoria_id": 2, "codigo_barra": "2327054410383", "es_destacado": false, "precio_venta": 100000000.00, "stock_maximo": 50, "stock_minimo": 5, "observaciones": null, "precio_compra": 100000000.00, "precio_minimo": 100000000.00, "punto_reorden": 7, "tipo_producto": "PRODUCTO", "controla_stock": true, "fecha_creacion": "2026-01-16T14:20:32", "garantia_meses": 0, "porcentaje_iva": 10.00, "margen_utilidad": 0.00, "usa_vencimiento": true, "imagen_principal": "1768584032_582e004bd9c82d0bb2ae.png", "precio_mayorista": 100000000.00, "unidad_medida_id": 1, "usuario_creacion": 1, "codigo_fabricante": null, "descripcion_corta": null, "fecha_modificacion": "2026-02-08T22:04:17.758022", "permite_venta_sin_stock": true, "especificaciones_tecnicas": null}	\N	\N	\N	2026-02-08 22:04:17.758022
409	1	1	productos	UPDATE	6	{"id": "6", "peso": null, "tags": null, "codigo": "0012", "estado": "t", "moneda": "PYG", "nombre": "Samsung Gala", "marca_id": "1", "tipo_iva": "GRAVADO_10", "usa_lote": "t", "es_oferta": "f", "usa_serie": "f", "empresa_id": "1", "es_novedad": "f", "descripcion": "sfsfsfsfddd", "dimensiones": null, "categoria_id": "1", "codigo_barra": "2327054410383", "es_destacado": "f", "precio_venta": "1000000.00", "stock_maximo": "50", "stock_minimo": "5", "observaciones": null, "precio_compra": "1000000.00", "precio_minimo": "1000000.00", "punto_reorden": "10", "tipo_producto": "PRODUCTO", "controla_stock": "t", "fecha_creacion": "2026-01-16 14:20:32", "garantia_meses": "0", "porcentaje_iva": "10.00", "margen_utilidad": "0.00", "usa_vencimiento": "t", "imagen_principal": "1768584032_582e004bd9c82d0bb2ae.png", "precio_mayorista": "1000000.00", "unidad_medida_id": "1", "usuario_creacion": "1", "codigo_fabricante": null, "descripcion_corta": null, "fecha_modificacion": "2026-01-29 14:17:49.921213", "permite_venta_sin_stock": "t", "especificaciones_tecnicas": null}	{"codigo": "0012", "moneda": "PYG", "nombre": "Samsung Gala", "marca_id": "1", "tipo_iva": "GRAVADO_10", "usa_lote": "t", "usa_serie": "f", "empresa_id": "1", "descripcion": "Es un gran producto todos los deberiamos poder usar", "categoria_id": "2", "codigo_barra": "2327054410383", "precio_venta": 100000000, "stock_maximo": 50, "stock_minimo": 5, "precio_compra": 100000000, "precio_minimo": 100000000, "punto_reorden": 7, "tipo_producto": "PRODUCTO", "controla_stock": "t", "margen_utilidad": 0, "usa_vencimiento": "t", "precio_mayorista": 100000000, "unidad_medida_id": "1", "permite_venta_sin_stock": "t"}	::1	\N	\N	2026-02-08 22:04:17.758022
447	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1770583533_dccd1abbac817a82e016.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-09T10:46:27", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-09T07:46:27.270272"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1770583533_dccd1abbac817a82e016.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-09T12:00:01", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-09T09:00:01.795743"}	\N	\N	\N	2026-02-09 09:00:01.795743
448	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 OPR/126.0.0.0	\N	2026-02-09 12:00:01
476	1	1	productos	DELETE	3	{"id": "3", "peso": null, "tags": null, "codigo": "SERV-001", "estado": "t", "moneda": "PYG", "nombre": "Reparación pantalla", "marca_id": null, "tipo_iva": "GRAVADO_10", "usa_lote": "f", "es_oferta": "f", "usa_serie": "f", "empresa_id": "1", "es_novedad": "f", "descripcion": "Reemplazo de pantalla", "dimensiones": null, "categoria_id": "3", "codigo_barra": null, "es_destacado": "f", "precio_venta": "150000.00", "stock_maximo": "0", "stock_minimo": "0", "observaciones": null, "precio_compra": "0.00", "precio_minimo": null, "punto_reorden": "0", "tipo_producto": "SERVICIO", "controla_stock": "f", "fecha_creacion": "2026-01-16 01:34:21.74548", "garantia_meses": "0", "porcentaje_iva": null, "margen_utilidad": "0.00", "usa_vencimiento": "f", "imagen_principal": null, "precio_mayorista": null, "unidad_medida_id": "10", "usuario_creacion": null, "codigo_fabricante": null, "descripcion_corta": null, "fecha_modificacion": "2026-01-16 01:34:21.74548", "permite_venta_sin_stock": "f", "especificaciones_tecnicas": null}	{"estado": false}	::1	\N	\N	2026-02-09 12:01:59.876078
410	\N	\N	productos	UPDATE	1	{"id": 1, "peso": null, "tags": null, "codigo": "SM-G998", "estado": true, "moneda": "PYG", "nombre": "Samsung Galaxy S21 Ultra", "marca_id": 1, "tipo_iva": "GRAVADO_10", "usa_lote": false, "es_oferta": false, "usa_serie": false, "empresa_id": 1, "es_novedad": false, "descripcion": "Smartphone gama alta", "dimensiones": null, "categoria_id": 1, "codigo_barra": null, "es_destacado": false, "precio_venta": 3500000.00, "stock_maximo": 50, "stock_minimo": 5, "observaciones": null, "precio_compra": 2500000.00, "precio_minimo": null, "punto_reorden": 10, "tipo_producto": "PRODUCTO", "controla_stock": true, "fecha_creacion": "2026-01-16T01:34:21.74548", "garantia_meses": 0, "porcentaje_iva": null, "margen_utilidad": 40.00, "usa_vencimiento": false, "imagen_principal": null, "precio_mayorista": null, "unidad_medida_id": 1, "usuario_creacion": null, "codigo_fabricante": null, "descripcion_corta": null, "fecha_modificacion": "2026-01-16T01:34:21.74548", "permite_venta_sin_stock": false, "especificaciones_tecnicas": null}	{"id": 1, "peso": null, "tags": null, "codigo": "SM-G998", "estado": false, "moneda": "PYG", "nombre": "Samsung Galaxy S21 Ultra", "marca_id": 1, "tipo_iva": "GRAVADO_10", "usa_lote": false, "es_oferta": false, "usa_serie": false, "empresa_id": 1, "es_novedad": false, "descripcion": "Smartphone gama alta", "dimensiones": null, "categoria_id": 1, "codigo_barra": null, "es_destacado": false, "precio_venta": 3500000.00, "stock_maximo": 50, "stock_minimo": 5, "observaciones": null, "precio_compra": 2500000.00, "precio_minimo": null, "punto_reorden": 10, "tipo_producto": "PRODUCTO", "controla_stock": true, "fecha_creacion": "2026-01-16T01:34:21.74548", "garantia_meses": 0, "porcentaje_iva": null, "margen_utilidad": 40.00, "usa_vencimiento": false, "imagen_principal": null, "precio_mayorista": null, "unidad_medida_id": 1, "usuario_creacion": null, "codigo_fabricante": null, "descripcion_corta": null, "fecha_modificacion": "2026-02-08T22:04:39.795167", "permite_venta_sin_stock": false, "especificaciones_tecnicas": null}	\N	\N	\N	2026-02-08 22:04:39.795167
411	1	1	productos	DELETE	1	{"id": "1", "peso": null, "tags": null, "codigo": "SM-G998", "estado": "t", "moneda": "PYG", "nombre": "Samsung Galaxy S21 Ultra", "marca_id": "1", "tipo_iva": "GRAVADO_10", "usa_lote": "f", "es_oferta": "f", "usa_serie": "f", "empresa_id": "1", "es_novedad": "f", "descripcion": "Smartphone gama alta", "dimensiones": null, "categoria_id": "1", "codigo_barra": null, "es_destacado": "f", "precio_venta": "3500000.00", "stock_maximo": "50", "stock_minimo": "5", "observaciones": null, "precio_compra": "2500000.00", "precio_minimo": null, "punto_reorden": "10", "tipo_producto": "PRODUCTO", "controla_stock": "t", "fecha_creacion": "2026-01-16 01:34:21.74548", "garantia_meses": "0", "porcentaje_iva": null, "margen_utilidad": "40.00", "usa_vencimiento": "f", "imagen_principal": null, "precio_mayorista": null, "unidad_medida_id": "1", "usuario_creacion": null, "codigo_fabricante": null, "descripcion_corta": null, "fecha_modificacion": "2026-01-16 01:34:21.74548", "permite_venta_sin_stock": "f", "especificaciones_tecnicas": null}	{"estado": false}	::1	\N	\N	2026-02-08 22:04:39.795167
449	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1770583533_dccd1abbac817a82e016.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-09T12:00:01", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-09T09:00:01.795743"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2y$10$brmGqqTAvQoVt1Psd/WYmOxhJvf16mNPVI7HEHY5yvbniKNwtOfdW", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1770583533_dccd1abbac817a82e016.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-09T12:00:01", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-09T09:00:45.29091"}	\N	\N	\N	2026-02-09 09:00:45.29091
450	1	1	usuarios	UPDATE	1	{"ci": "1234567", "id": "1", "email": "admin@electronica.com.py", "estado": "t", "nombre": "Juan", "rol_id": "1", "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": "1", "foto_perfil": "1770583533_dccd1abbac817a82e016.jpg", "sucursal_id": "1", "tema_oscuro": "f", "preferencias": "{\\"idioma\\": \\"es\\", \\"tema_oscuro\\": false, \\"formato_hora\\": \\"H:i:s\\", \\"zona_horaria\\": \\"America/Asuncion\\", \\"formato_fecha\\": \\"d/m/Y\\", \\"notificaciones_push\\": true, \\"notificaciones_email\\": true}", "firma_digital": null, "ultimo_acceso": "2026-02-09 12:00:01", "fecha_creacion": "2026-01-16 01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-09 09:00:01.795743"}	{"ci": "1234567", "email": "admin@electronica.com.py", "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "@cabron654", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "sucursal_id": 1}	::1	\N	\N	2026-02-09 12:00:45
477	1	1	productos	UPDATE	2	{"id": "2", "peso": null, "tags": null, "codigo": "IPH-13", "estado": "t", "moneda": "PYG", "nombre": "iPhone 13 Pro Max", "marca_id": "2", "tipo_iva": "GRAVADO_10", "usa_lote": "t", "es_oferta": "f", "usa_serie": "f", "empresa_id": "1", "es_novedad": "f", "descripcion": "iPhone 13 Pro Max 256GB", "dimensiones": null, "categoria_id": "1", "codigo_barra": "", "es_destacado": "f", "precio_venta": "6500000.00", "stock_maximo": "30", "stock_minimo": "3", "observaciones": null, "precio_compra": "5000000.00", "precio_minimo": null, "punto_reorden": "5", "tipo_producto": "PRODUCTO", "controla_stock": "t", "fecha_creacion": "2026-01-16 01:34:21.74548", "garantia_meses": "0", "porcentaje_iva": "10.00", "margen_utilidad": "30.00", "usa_vencimiento": "t", "imagen_principal": "1768757756_e86e34db5248c3a9855b.jpg", "precio_mayorista": null, "unidad_medida_id": "1", "usuario_creacion": null, "codigo_fabricante": null, "descripcion_corta": null, "fecha_modificacion": "2026-01-18 18:35:56.738489", "permite_venta_sin_stock": "t", "especificaciones_tecnicas": null}	{"codigo": "IPH-13", "moneda": "PYG", "nombre": "iPhone 13 Pro Maxdd", "marca_id": "2", "tipo_iva": "GRAVADO_10", "usa_lote": "t", "usa_serie": "f", "empresa_id": "1", "descripcion": "iPhone 13 Pro Max 256GB", "categoria_id": "2", "codigo_barra": "", "precio_venta": 650000000, "stock_maximo": 30, "stock_minimo": 3, "precio_compra": 500000000, "precio_minimo": 0, "punto_reorden": 5, "tipo_producto": "PRODUCTO", "controla_stock": "t", "margen_utilidad": 30, "usa_vencimiento": "t", "precio_mayorista": 0, "unidad_medida_id": "1", "permite_venta_sin_stock": "t"}	::1	\N	\N	2026-02-09 12:02:21.535807
412	\N	\N	clientes	INSERT	3	\N	{"ci": "5351447", "id": 3, "ruc": null, "foto": "1770610770_f1e326f526dc7b367c74.jpg", "sexo": "F", "email": "paolamorel03@gmail.com", "barrio": null, "ciudad": "capiata", "codigo": "CLI-000005", "estado": true, "nombre": "Paola", "celular": "0997258356", "apellido": "Morel", "telefono": "0997258356", "direccion": "Capiata ruta 2 km 21", "etiquetas": null, "empresa_id": 1, "departamento": "CENTRAL", "dias_credito": 30, "razon_social": null, "tipo_cliente": "PERSONA", "observaciones": null, "total_compras": 0.00, "fecha_creacion": "2026-02-09T01:19:30", "limite_credito": 1000000000.00, "notas_internas": null, "lista_precio_id": null, "nombre_fantasia": null, "cantidad_compras": 0, "fecha_nacimiento": "2003-07-01", "usuario_creacion": 1, "categoria_cliente": "VIP", "credito_utilizado": 0.00, "credito_disponible": 1000000000.00, "fecha_modificacion": "2026-02-09T01:19:30", "tipo_contribuyente": "CONTRIBUYENTE", "fecha_ultima_compra": null, "descuento_porcentaje": 5.00, "referencia_direccion": null, "vendedor_asignado_id": null}	\N	\N	\N	2026-02-08 22:19:30.435215
413	1	1	clientes	INSERT	412	\N	{"ci": "5351447", "ruc": null, "foto": "1770610770_f1e326f526dc7b367c74.jpg", "sexo": "F", "email": "paolamorel03@gmail.com", "barrio": null, "ciudad": "capiata", "codigo": "CLI-000005", "estado": true, "nombre": "Paola", "celular": "0997258356", "apellido": "Morel", "telefono": "0997258356", "direccion": "Capiata ruta 2 km 21", "empresa_id": 1, "departamento": "CENTRAL", "dias_credito": 30, "razon_social": null, "tipo_cliente": "PERSONA", "observaciones": null, "limite_credito": 1000000000, "lista_precio_id": null, "nombre_fantasia": null, "fecha_nacimiento": "2003-07-01", "usuario_creacion": 1, "categoria_cliente": "VIP", "tipo_contribuyente": "CONTRIBUYENTE", "descuento_porcentaje": 5}	::1	\N	\N	2026-02-08 22:19:30.441703
451	1	1	usuarios	LOGOUT	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 OPR/126.0.0.0	\N	2026-02-09 12:00:48
478	1	1	productos	INSERT	20	\N	{"codigo": "pro-006", "estado": true, "moneda": "PYG", "nombre": "prueba", "marca_id": "2", "tipo_iva": "GRAVADO_10", "usa_lote": "f", "usa_serie": "f", "empresa_id": "1", "descripcion": "ssfs", "sucursal_id": "1", "categoria_id": "2", "codigo_barra": "2342669243353", "precio_venta": 4000000, "stock_maximo": 10, "stock_minimo": 2, "precio_compra": 0, "precio_minimo": 0, "punto_reorden": 5, "stock_inicial": 0, "tipo_producto": "PRODUCTO", "controla_stock": "t", "usa_vencimiento": "f", "imagen_principal": "1770663970_89613549c874e4de6f2d.png", "precio_mayorista": 0, "unidad_medida_id": "1", "usuario_creacion": "1", "permite_venta_sin_stock": "f"}	::1	\N	\N	2026-02-09 13:06:10.922616
514	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2y$10$brmGqqTAvQoVt1Psd/WYmOxhJvf16mNPVI7HEHY5yvbniKNwtOfdW", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1770583533_dccd1abbac817a82e016.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-09T16:26:44", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-09T13:26:44.360463"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2y$10$brmGqqTAvQoVt1Psd/WYmOxhJvf16mNPVI7HEHY5yvbniKNwtOfdW", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1770583533_dccd1abbac817a82e016.jpg", "sucursal_id": 1, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-09T16:26:44", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-09T13:32:49.680887"}	\N	\N	\N	2026-02-09 13:32:49.680887
515	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2y$10$brmGqqTAvQoVt1Psd/WYmOxhJvf16mNPVI7HEHY5yvbniKNwtOfdW", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1770583533_dccd1abbac817a82e016.jpg", "sucursal_id": 1, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-09T16:26:44", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-09T13:32:49.680887"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2y$10$brmGqqTAvQoVt1Psd/WYmOxhJvf16mNPVI7HEHY5yvbniKNwtOfdW", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1770583533_dccd1abbac817a82e016.jpg", "sucursal_id": 1, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-09T16:26:44", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-09T13:32:49.730817"}	\N	\N	\N	2026-02-09 13:32:49.730817
414	\N	\N	clientes	UPDATE	3	{"ci": "5351447", "id": 3, "ruc": null, "foto": "1770610770_f1e326f526dc7b367c74.jpg", "sexo": "F", "email": "paolamorel03@gmail.com", "barrio": null, "ciudad": "capiata", "codigo": "CLI-000005", "estado": true, "nombre": "Paola", "celular": "0997258356", "apellido": "Morel", "telefono": "0997258356", "direccion": "Capiata ruta 2 km 21", "etiquetas": null, "empresa_id": 1, "departamento": "CENTRAL", "dias_credito": 30, "razon_social": null, "tipo_cliente": "PERSONA", "observaciones": null, "total_compras": 0.00, "fecha_creacion": "2026-02-09T01:19:30", "limite_credito": 1000000000.00, "notas_internas": null, "lista_precio_id": null, "nombre_fantasia": null, "cantidad_compras": 0, "fecha_nacimiento": "2003-07-01", "usuario_creacion": 1, "categoria_cliente": "VIP", "credito_utilizado": 0.00, "credito_disponible": 1000000000.00, "fecha_modificacion": "2026-02-09T01:19:30", "tipo_contribuyente": "CONTRIBUYENTE", "fecha_ultima_compra": null, "descuento_porcentaje": 5.00, "referencia_direccion": null, "vendedor_asignado_id": null}	{"ci": "5351447", "id": 3, "ruc": null, "foto": "1770610770_f1e326f526dc7b367c74.jpg", "sexo": "F", "email": "paolamorel03@gmail.com", "barrio": null, "ciudad": "capiata", "codigo": "CLI-000005", "estado": true, "nombre": "Paola", "celular": "0997258356", "apellido": "Morel", "telefono": "0997258356", "direccion": "ruta 2 km 21", "etiquetas": null, "empresa_id": 1, "departamento": "CENTRAL", "dias_credito": 30, "razon_social": null, "tipo_cliente": "PERSONA", "observaciones": null, "total_compras": 0.00, "fecha_creacion": "2026-02-09T01:19:30", "limite_credito": 1000000000.00, "notas_internas": null, "lista_precio_id": null, "nombre_fantasia": null, "cantidad_compras": 0, "fecha_nacimiento": "2003-07-01", "usuario_creacion": 1, "categoria_cliente": "VIP", "credito_utilizado": 0.00, "credito_disponible": 1000000000.00, "fecha_modificacion": "2026-02-08T22:20:01.472699", "tipo_contribuyente": "CONTRIBUYENTE", "fecha_ultima_compra": null, "descuento_porcentaje": 5.00, "referencia_direccion": null, "vendedor_asignado_id": null}	\N	\N	\N	2026-02-08 22:20:01.472699
415	1	1	clientes	UPDATE	3	{"ci": "5351447", "id": "3", "ruc": null, "foto": "1770610770_f1e326f526dc7b367c74.jpg", "sexo": "F", "email": "paolamorel03@gmail.com", "barrio": null, "ciudad": "capiata", "codigo": "CLI-000005", "estado": "t", "nombre": "Paola", "celular": "0997258356", "apellido": "Morel", "telefono": "0997258356", "direccion": "Capiata ruta 2 km 21", "etiquetas": null, "empresa_id": "1", "departamento": "CENTRAL", "dias_credito": "30", "razon_social": null, "tipo_cliente": "PERSONA", "observaciones": null, "total_compras": "0.00", "fecha_creacion": "2026-02-09 01:19:30", "limite_credito": "1000000000.00", "notas_internas": null, "lista_precio_id": null, "nombre_fantasia": null, "cantidad_compras": "0", "fecha_nacimiento": "2003-07-01", "usuario_creacion": "1", "categoria_cliente": "VIP", "credito_utilizado": "0.00", "credito_disponible": "1000000000.00", "fecha_modificacion": "2026-02-09 01:19:30", "tipo_contribuyente": "CONTRIBUYENTE", "fecha_ultima_compra": null, "descuento_porcentaje": "5.00", "referencia_direccion": null, "vendedor_asignado_id": null}	{"ci": "5351447", "ruc": null, "sexo": "F", "email": "paolamorel03@gmail.com", "barrio": null, "ciudad": "capiata", "nombre": "Paola", "celular": "0997258356", "apellido": "Morel", "telefono": "0997258356", "direccion": "ruta 2 km 21", "departamento": "CENTRAL", "dias_credito": 30, "razon_social": null, "tipo_cliente": "PERSONA", "observaciones": null, "limite_credito": 1000000000, "lista_precio_id": null, "nombre_fantasia": null, "fecha_nacimiento": "2003-07-01", "categoria_cliente": "VIP", "tipo_contribuyente": "CONTRIBUYENTE", "descuento_porcentaje": 5}	::1	\N	\N	2026-02-08 22:20:01.489385
452	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2y$10$brmGqqTAvQoVt1Psd/WYmOxhJvf16mNPVI7HEHY5yvbniKNwtOfdW", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1770583533_dccd1abbac817a82e016.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-09T12:00:01", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-09T09:00:45.29091"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2y$10$brmGqqTAvQoVt1Psd/WYmOxhJvf16mNPVI7HEHY5yvbniKNwtOfdW", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1770583533_dccd1abbac817a82e016.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-09T12:00:59", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-09T09:00:59.668046"}	\N	\N	\N	2026-02-09 09:00:59.668046
453	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 OPR/126.0.0.0	\N	2026-02-09 12:00:59
479	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2y$10$brmGqqTAvQoVt1Psd/WYmOxhJvf16mNPVI7HEHY5yvbniKNwtOfdW", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1770583533_dccd1abbac817a82e016.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-09T14:57:26", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-09T11:57:26.504527"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2y$10$brmGqqTAvQoVt1Psd/WYmOxhJvf16mNPVI7HEHY5yvbniKNwtOfdW", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1770583533_dccd1abbac817a82e016.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-09T16:26:44", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-09T13:26:44.360463"}	\N	\N	\N	2026-02-09 13:26:44.360463
480	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.131 Safari/537.36 Edg/92.0.902.67	\N	2026-02-09 16:26:44
416	\N	\N	ordenes_servicio	UPDATE	1	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 7, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": "sdfghjkl;dfghjkl", "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 54545.46, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 54545.46, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 600000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 600000.00, "solucion_aplicada": "dfghjkldfghjk", "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-01-22T02:48:14.211903", "problema_reportado": "cvbnm,", "costo_real_subtotal": 545454.54, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 600000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 545454.54, "observaciones_cliente": "dfghj", "observaciones_tecnico": "erthjkdfghj", "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 600000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 7, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": "sdfghjkl;dfghjkl", "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 70909.10, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 70909.10, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 780000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 780000.00, "solucion_aplicada": "dfghjkldfghjk", "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-02-08T22:28:21.569063", "problema_reportado": "cvbnm,", "costo_real_subtotal": 709090.90, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 780000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 709090.90, "observaciones_cliente": "dfghj", "observaciones_tecnico": "erthjkdfghj", "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 780000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	\N	\N	\N	2026-02-08 22:28:21.569063
454	\N	\N	ventas	INSERT	54	\N	{"id": 54, "iva_5": 0.00, "total": 400000.00, "estado": "PENDIENTE", "iva_10": 36364.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 400000.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 36364.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T12:09:48", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000022", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T09:09:48.209671", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 400000.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T09:09:48.209671", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-02-09 09:09:48.209671
455	\N	\N	ventas	UPDATE	54	{"id": 54, "iva_5": 0.00, "total": 400000.00, "estado": "PENDIENTE", "iva_10": 36364.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 400000.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 36364.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T12:09:48", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000022", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T09:09:48.209671", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 400000.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T09:09:48.209671", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	{"id": 54, "iva_5": 0.00, "total": 400000.00, "estado": "COMPLETADA", "iva_10": 36364.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 400000.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 36364.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T12:09:48", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 400000.00, "numero_venta": "VTA-00000022", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T09:09:48.209671", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 0.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T09:09:48.209671", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-02-09 09:09:48.209671
516	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2y$10$brmGqqTAvQoVt1Psd/WYmOxhJvf16mNPVI7HEHY5yvbniKNwtOfdW", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1770583533_dccd1abbac817a82e016.jpg", "sucursal_id": 1, "tema_oscuro": true, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-09T16:26:44", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-09T13:32:49.730817"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2y$10$brmGqqTAvQoVt1Psd/WYmOxhJvf16mNPVI7HEHY5yvbniKNwtOfdW", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1770583533_dccd1abbac817a82e016.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-09T16:26:44", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-09T13:32:50.998422"}	\N	\N	\N	2026-02-09 13:32:50.998422
517	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2y$10$brmGqqTAvQoVt1Psd/WYmOxhJvf16mNPVI7HEHY5yvbniKNwtOfdW", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1770583533_dccd1abbac817a82e016.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": true, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-09T16:26:44", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-09T13:32:50.998422"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2y$10$brmGqqTAvQoVt1Psd/WYmOxhJvf16mNPVI7HEHY5yvbniKNwtOfdW", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1770583533_dccd1abbac817a82e016.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-09T16:26:44", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-09T13:32:51.001075"}	\N	\N	\N	2026-02-09 13:32:51.001075
417	\N	\N	ordenes_servicio	UPDATE	1	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 7, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": "sdfghjkl;dfghjkl", "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 70909.10, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 70909.10, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 780000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 780000.00, "solucion_aplicada": "dfghjkldfghjk", "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-02-08T22:28:21.569063", "problema_reportado": "cvbnm,", "costo_real_subtotal": 709090.90, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 780000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 709090.90, "observaciones_cliente": "dfghj", "observaciones_tecnico": "erthjkdfghj", "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 780000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	{"id": 1, "imei": "-", "color": "azul", "marca": "samsung", "modelo": "rrss", "moneda": "PYG", "venta_id": null, "estado_id": 9, "prioridad": "BAJA", "accesorios": "dfghjk", "cliente_id": 1, "empresa_id": 1, "diagnostico": "sdfghjkl;dfghjkl", "sucursal_id": 1, "tipo_cambio": 1.000000, "tipo_equipo": "notebook", "clave_equipo": null, "numero_orden": "OS-00000001", "numero_serie": "lasdljkls", "estado_activo": true, "estado_fisico": "dfghjm,.", "fecha_entrega": null, "garantia_dias": 0, "anticipo_monto": 0.00, "autorizado_por": null, "costo_real_iva": 70909.10, "fecha_creacion": "2026-01-16T16:56:13", "fecha_recepcion": "2026-01-16T20:56:13.304637", "presupuesto_iva": 70909.10, "saldo_pendiente": 0.00, "costo_real_otros": 0.00, "costo_real_total": 780000.00, "fecha_aprobacion": null, "tecnico_asignado": null, "usuario_creacion": 1, "fecha_diagnostico": null, "fecha_facturacion": null, "patron_desbloqueo": "234559", "presupuesto_otros": 0.00, "presupuesto_total": 780000.00, "solucion_aplicada": "dfghjkldfghjk", "terminos_garantia": null, "usuario_recepcion": 1, "cliente_contactado": false, "fecha_autorizacion": null, "fecha_finalizacion": null, "fecha_modificacion": "2026-02-08T22:30:16.2582", "problema_reportado": "cvbnm,", "costo_real_subtotal": 709090.90, "fecha_limite_retiro": "2026-04-16", "costo_real_mano_obra": 780000.00, "costo_real_repuestos": 0.00, "presupuesto_aprobado": false, "presupuesto_subtotal": 709090.90, "observaciones_cliente": "dfghj", "observaciones_tecnico": "erthjkdfghj", "presupuesto_descuento": 0.00, "presupuesto_mano_obra": 780000.00, "presupuesto_repuestos": 0.00, "requiere_autorizacion": false, "observaciones_internas": null, "fecha_inicio_reparacion": null, "presupuesto_diagnostico": 0.00, "cliente_notificado_listo": false, "fecha_vencimiento_garantia": null, "fecha_vencimiento_presupuesto": null}	\N	\N	\N	2026-02-08 22:30:16.2582
456	\N	\N	ventas	INSERT	55	\N	{"id": 55, "iva_5": 0.00, "total": 1000000.00, "estado": "PENDIENTE", "iva_10": 90909.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 1000000.00, "timbrado": "1234567", "tipo_pago": "CONTADO", "total_iva": 90909.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T12:10:15", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000023", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T09:10:15.532361", "notas_internas": null, "numero_factura": "1000009", "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 1000000.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T09:10:15.532361", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-02-09 09:10:15.532361
457	\N	\N	ventas	UPDATE	55	{"id": 55, "iva_5": 0.00, "total": 1000000.00, "estado": "PENDIENTE", "iva_10": 90909.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 1000000.00, "timbrado": "1234567", "tipo_pago": "CONTADO", "total_iva": 90909.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T12:10:15", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000023", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T09:10:15.532361", "notas_internas": null, "numero_factura": "1000009", "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 1000000.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T09:10:15.532361", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	{"id": 55, "iva_5": 0.00, "total": 1000000.00, "estado": "COMPLETADA", "iva_10": 90909.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 1000000.00, "timbrado": "1234567", "tipo_pago": "CONTADO", "total_iva": 90909.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T12:10:15", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 1000000.00, "numero_venta": "VTA-00000023", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T09:10:15.532361", "notas_internas": null, "numero_factura": "1000009", "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 0.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T09:10:15.532361", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-02-09 09:10:15.532361
518	\N	\N	ventas	INSERT	58	\N	{"id": 58, "iva_5": 0.00, "total": 20000.00, "estado": "COMPLETADA", "iva_10": 0.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 18181.82, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 1818.18, "cliente_id": 1, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T17:18:06", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": null, "monto_pagado": 20000.00, "numero_venta": "NC-00000002", "origen_venta": "SUCURSAL", "observaciones": "Devolución de venta #VTA-00000001. Motivo: Se rompio la compu", "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T17:18:06", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 0.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "NOTA_CREDITO", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T17:18:06", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-02-09 14:18:06.29013
519	\N	\N	ventas	UPDATE	36	{"id": 36, "iva_5": 0.00, "total": 20000.00, "estado": "COMPLETADA", "iva_10": 1818.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 20000.00, "timbrado": "1234567", "tipo_pago": "CONTADO", "total_iva": 1818.00, "cliente_id": 1, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-21T22:36:03", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 20000.00, "numero_venta": "VTA-00000001", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-22T02:36:03.385359", "notas_internas": null, "numero_factura": "1000005", "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 0.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-01-22T02:36:03.385359", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	{"id": 36, "iva_5": 0.00, "total": 20000.00, "estado": "DEVUELTA_TOTAL", "iva_10": 1818.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 20000.00, "timbrado": "1234567", "tipo_pago": "CONTADO", "total_iva": 1818.00, "cliente_id": 1, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-01-21T22:36:03", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 20000.00, "numero_venta": "VTA-00000001", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-01-22T02:36:03.385359", "notas_internas": null, "numero_factura": "1000005", "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 0.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T17:18:06", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-02-09 14:18:06.29013
418	2	1	empresas	DELETE	2	{"id": "2", "ruc": "11156766-6", "logo": "1768598459_a7d67cc9bad19fa1bce0.jpg", "email": "albert.salo.654@gmail.com", "ciudad": "Asunción", "estado": "f", "nombre": "xxx", "telefono": "(021) 555-666", "timbrado": "1234567", "direccion": "Av. República Argentina 123", "aplica_iva": "t", "tema_oscuro": "f", "fecha_creacion": "2026-01-16 22:20:59.285386", "porcentaje_iva": "10.00", "establecimiento": "1", "moneda_principal": "PYG", "punto_expedicion": "1", "usuario_creacion": "1", "dias_plazo_retiro": "90", "politica_garantia": null, "fecha_modificacion": "2026-01-29 14:23:24.117369", "protector_pantalla": null, "terminos_condiciones": null, "usa_facturacion_electronica": "f"}	\N	::1	\N	\N	2026-02-09 01:33:54
458	\N	\N	ventas	INSERT	56	\N	{"id": 56, "iva_5": 0.00, "total": 400000.00, "estado": "PENDIENTE", "iva_10": 36364.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 400000.00, "timbrado": "1234567", "tipo_pago": "CONTADO", "total_iva": 36364.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T12:21:10", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000024", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T09:21:10.715855", "notas_internas": null, "numero_factura": "1000010", "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 400000.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T09:21:10.715855", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-02-09 09:21:10.715855
459	\N	\N	ventas	UPDATE	56	{"id": 56, "iva_5": 0.00, "total": 400000.00, "estado": "PENDIENTE", "iva_10": 36364.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 400000.00, "timbrado": "1234567", "tipo_pago": "CONTADO", "total_iva": 36364.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T12:21:10", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000024", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T09:21:10.715855", "notas_internas": null, "numero_factura": "1000010", "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 400000.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T09:21:10.715855", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	{"id": 56, "iva_5": 0.00, "total": 400000.00, "estado": "COMPLETADA", "iva_10": 36364.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 400000.00, "timbrado": "1234567", "tipo_pago": "CONTADO", "total_iva": 36364.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T12:21:10", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 400000.00, "numero_venta": "VTA-00000024", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T09:21:10.715855", "notas_internas": null, "numero_factura": "1000010", "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 0.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T09:21:10.715855", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-02-09 09:21:10.715855
521	\N	\N	ventas	INSERT	59	\N	{"id": 59, "iva_5": 0.00, "total": 1200000.00, "estado": "PENDIENTE", "iva_10": 109091.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 1200000.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 109091.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T17:35:06", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000026", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T14:35:06.123203", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 1200000.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T14:35:06.123203", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-02-09 14:35:06.123203
522	\N	\N	ventas	UPDATE	59	{"id": 59, "iva_5": 0.00, "total": 1200000.00, "estado": "PENDIENTE", "iva_10": 109091.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 1200000.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 109091.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T17:35:06", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000026", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T14:35:06.123203", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 1200000.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T14:35:06.123203", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	{"id": 59, "iva_5": 0.00, "total": 1200000.00, "estado": "COMPLETADA", "iva_10": 109091.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 1200000.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 109091.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T17:35:06", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 1200000.00, "numero_venta": "VTA-00000026", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T14:35:06.123203", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 0.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T14:35:06.123203", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-02-09 14:35:06.123203
523	\N	\N	ventas	INSERT	60	\N	{"id": 60, "iva_5": 0.00, "total": 400000.00, "estado": "PENDIENTE", "iva_10": 36364.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 400000.00, "timbrado": "1234567", "tipo_pago": "CONTADO", "total_iva": 36364.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T17:38:26", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000027", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T14:38:26.05427", "notas_internas": null, "numero_factura": "1000011", "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 400000.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T14:38:26.05427", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-02-09 14:38:26.05427
524	\N	\N	ventas	UPDATE	60	{"id": 60, "iva_5": 0.00, "total": 400000.00, "estado": "PENDIENTE", "iva_10": 36364.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 400000.00, "timbrado": "1234567", "tipo_pago": "CONTADO", "total_iva": 36364.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T17:38:26", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000027", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T14:38:26.05427", "notas_internas": null, "numero_factura": "1000011", "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 400000.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T14:38:26.05427", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	{"id": 60, "iva_5": 0.00, "total": 400000.00, "estado": "COMPLETADA", "iva_10": 36364.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 400000.00, "timbrado": "1234567", "tipo_pago": "CONTADO", "total_iva": 36364.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T17:38:26", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 400000.00, "numero_venta": "VTA-00000027", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T14:38:26.05427", "notas_internas": null, "numero_factura": "1000011", "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 0.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T14:38:26.05427", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-02-09 14:38:26.05427
421	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1770583533_dccd1abbac817a82e016.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-09T00:56:11", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-08T21:56:11.556375"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1770583533_dccd1abbac817a82e016.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-09T02:40:34", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-08T23:40:34.309949"}	\N	\N	\N	2026-02-08 23:40:34.309949
422	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.131 Safari/537.36 Edg/92.0.902.67	\N	2026-02-09 02:40:34
525	\N	\N	usuarios	UPDATE	2	{"ci": "7654321", "id": 2, "email": "maria@electronica.com.py", "estado": true, "nombre": "María", "rol_id": 3, "apellido": "González", "password": "$2a$06$nC1E.KABrqPt0ojj9d8bB.o7NzaDPkWWuVp2nylwJl.IMWJbyDg/C", "telefono": "(0982) 654-321", "username": "maria", "direccion": null, "empresa_id": 1, "foto_perfil": null, "sucursal_id": 1, "tema_oscuro": null, "preferencias": null, "firma_digital": null, "ultimo_acceso": null, "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-16T01:34:21.74548"}	{"ci": "7654321", "id": 2, "email": "maria@electronica.com.py", "estado": true, "nombre": "María", "rol_id": 6, "apellido": "González", "password": "$2y$10$JhKjgOv252DHvv8sReQQR.AJN2o74WgBl1AXOSP0Y0opb9YgTaCPm", "telefono": "(0982) 654-321", "username": "maria", "direccion": null, "empresa_id": 1, "foto_perfil": null, "sucursal_id": 1, "tema_oscuro": null, "preferencias": null, "firma_digital": null, "ultimo_acceso": null, "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-09T16:21:46.780951"}	\N	\N	\N	2026-02-09 16:21:46.780951
526	1	1	usuarios	UPDATE	2	{"ci": "7654321", "id": "2", "email": "maria@electronica.com.py", "estado": "t", "nombre": "María", "rol_id": "3", "apellido": "González", "password": "$2a$06$nC1E.KABrqPt0ojj9d8bB.o7NzaDPkWWuVp2nylwJl.IMWJbyDg/C", "telefono": "(0982) 654-321", "username": "maria", "direccion": null, "empresa_id": "1", "foto_perfil": null, "sucursal_id": "1", "tema_oscuro": null, "preferencias": null, "firma_digital": null, "ultimo_acceso": null, "fecha_creacion": "2026-01-16 01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-01-16 01:34:21.74548"}	{"ci": "7654321", "email": "maria@electronica.com.py", "nombre": "María", "rol_id": 6, "apellido": "González", "password": "@cabron654", "telefono": "(0982) 654-321", "username": "maria", "direccion": null, "empresa_id": 1, "sucursal_id": 1}	::1	\N	\N	2026-02-09 19:21:46
423	\N	\N	usuarios	UPDATE	1	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1770583533_dccd1abbac817a82e016.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-09T02:40:34", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-08T23:40:34.309949"}	{"ci": "1234567", "id": 1, "email": "admin@electronica.com.py", "estado": true, "nombre": "Juan", "rol_id": 1, "apellido": "Pérez", "password": "$2a$06$sn6MhTBeUQvCs2wdnBJVLuAHQfKNu35oNqzcmz6i8cQPNCsjumMhm", "telefono": "(0981) 123-456", "username": "admin", "direccion": null, "empresa_id": 1, "foto_perfil": "1770583533_dccd1abbac817a82e016.jpg", "sucursal_id": 1, "tema_oscuro": false, "preferencias": {"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}, "firma_digital": null, "ultimo_acceso": "2026-02-09T10:46:27", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-09T07:46:27.270272"}	\N	\N	\N	2026-02-09 07:46:27.270272
424	1	1	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 OPR/126.0.0.0	\N	2026-02-09 10:46:27
527	\N	\N	usuarios	UPDATE	2	{"ci": "7654321", "id": 2, "email": "maria@electronica.com.py", "estado": true, "nombre": "María", "rol_id": 6, "apellido": "González", "password": "$2y$10$JhKjgOv252DHvv8sReQQR.AJN2o74WgBl1AXOSP0Y0opb9YgTaCPm", "telefono": "(0982) 654-321", "username": "maria", "direccion": null, "empresa_id": 1, "foto_perfil": null, "sucursal_id": 1, "tema_oscuro": null, "preferencias": null, "firma_digital": null, "ultimo_acceso": null, "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-09T16:21:46.780951"}	{"ci": "7654321", "id": 2, "email": "maria@electronica.com.py", "estado": true, "nombre": "María", "rol_id": 6, "apellido": "González", "password": "$2y$10$JhKjgOv252DHvv8sReQQR.AJN2o74WgBl1AXOSP0Y0opb9YgTaCPm", "telefono": "(0982) 654-321", "username": "maria", "direccion": null, "empresa_id": 1, "foto_perfil": null, "sucursal_id": 1, "tema_oscuro": null, "preferencias": null, "firma_digital": null, "ultimo_acceso": "2026-02-09T19:23:58", "fecha_creacion": "2026-01-16T01:34:21.74548", "usuario_creacion": null, "fecha_modificacion": "2026-02-09T16:23:58.96703"}	\N	\N	\N	2026-02-09 16:23:58.96703
528	1	2	usuarios	LOGIN	\N	\N	\N	::1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 OPR/126.0.0.0	\N	2026-02-09 19:23:59
425	\N	\N	ventas	INSERT	44	\N	{"id": 44, "iva_5": 0.00, "total": 400000.00, "estado": "PENDIENTE", "iva_10": 36364.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 400000.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 36364.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T11:06:55", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000012", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T08:06:55.135366", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 400000.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T08:06:55.135366", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-02-09 08:06:55.135366
426	\N	\N	ventas	UPDATE	44	{"id": 44, "iva_5": 0.00, "total": 400000.00, "estado": "PENDIENTE", "iva_10": 36364.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 400000.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 36364.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T11:06:55", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000012", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T08:06:55.135366", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 400000.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T08:06:55.135366", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	{"id": 44, "iva_5": 0.00, "total": 400000.00, "estado": "COMPLETADA", "iva_10": 36364.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 400000.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 36364.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T11:06:55", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 400000.00, "numero_venta": "VTA-00000012", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T08:06:55.135366", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 0.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T08:06:55.135366", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-02-09 08:06:55.135366
427	\N	\N	ventas	INSERT	45	\N	{"id": 45, "iva_5": 0.00, "total": 400000.00, "estado": "PENDIENTE", "iva_10": 36364.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 400000.00, "timbrado": "1234567", "tipo_pago": "CONTADO", "total_iva": 36364.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T11:07:12", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000013", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T08:07:12.007826", "notas_internas": null, "numero_factura": "1000006", "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 400000.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T08:07:12.007826", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-02-09 08:07:12.007826
428	\N	\N	ventas	UPDATE	45	{"id": 45, "iva_5": 0.00, "total": 400000.00, "estado": "PENDIENTE", "iva_10": 36364.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 400000.00, "timbrado": "1234567", "tipo_pago": "CONTADO", "total_iva": 36364.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T11:07:12", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000013", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T08:07:12.007826", "notas_internas": null, "numero_factura": "1000006", "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 400000.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T08:07:12.007826", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	{"id": 45, "iva_5": 0.00, "total": 400000.00, "estado": "COMPLETADA", "iva_10": 36364.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 400000.00, "timbrado": "1234567", "tipo_pago": "CONTADO", "total_iva": 36364.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T11:07:12", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 400000.00, "numero_venta": "VTA-00000013", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T08:07:12.007826", "notas_internas": null, "numero_factura": "1000006", "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 0.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "FACTURA", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T08:07:12.007826", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-02-09 08:07:12.007826
429	\N	\N	ventas	INSERT	46	\N	{"id": 46, "iva_5": 0.00, "total": 400000.00, "estado": "PENDIENTE", "iva_10": 36364.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 400000.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 36364.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T11:30:57", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000014", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T08:30:57.378599", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 400000.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T08:30:57.378599", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-02-09 08:30:57.378599
430	\N	\N	ventas	UPDATE	46	{"id": 46, "iva_5": 0.00, "total": 400000.00, "estado": "PENDIENTE", "iva_10": 36364.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 400000.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 36364.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T11:30:57", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000014", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T08:30:57.378599", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 400000.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T08:30:57.378599", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	{"id": 46, "iva_5": 0.00, "total": 400000.00, "estado": "COMPLETADA", "iva_10": 36364.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 400000.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 36364.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T11:30:57", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 400000.00, "numero_venta": "VTA-00000014", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T08:30:57.378599", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 0.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T08:30:57.378599", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-02-09 08:30:57.378599
431	\N	\N	ventas	INSERT	47	\N	{"id": 47, "iva_5": 0.00, "total": 400000.00, "estado": "PENDIENTE", "iva_10": 36364.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 400000.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 36364.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T11:31:31", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000015", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T08:31:31.993372", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 400000.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T08:31:31.993372", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-02-09 08:31:31.993372
432	\N	\N	ventas	UPDATE	47	{"id": 47, "iva_5": 0.00, "total": 400000.00, "estado": "PENDIENTE", "iva_10": 36364.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 400000.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 36364.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T11:31:31", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000015", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T08:31:31.993372", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 400000.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T08:31:31.993372", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	{"id": 47, "iva_5": 0.00, "total": 400000.00, "estado": "COMPLETADA", "iva_10": 36364.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 400000.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 36364.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T11:31:31", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 400000.00, "numero_venta": "VTA-00000015", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T08:31:31.993372", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 0.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T08:31:31.993372", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-02-09 08:31:31.993372
433	\N	\N	ventas	INSERT	48	\N	{"id": 48, "iva_5": 0.00, "total": 400000.00, "estado": "PENDIENTE", "iva_10": 36364.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 400000.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 36364.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T11:39:27", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000016", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T08:39:27.5311", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 400000.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T08:39:27.5311", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-02-09 08:39:27.5311
434	\N	\N	ventas	UPDATE	48	{"id": 48, "iva_5": 0.00, "total": 400000.00, "estado": "PENDIENTE", "iva_10": 36364.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 400000.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 36364.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T11:39:27", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000016", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T08:39:27.5311", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 400000.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T08:39:27.5311", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	{"id": 48, "iva_5": 0.00, "total": 400000.00, "estado": "COMPLETADA", "iva_10": 36364.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 400000.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 36364.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T11:39:27", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 400000.00, "numero_venta": "VTA-00000016", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T08:39:27.5311", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 0.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T08:39:27.5311", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-02-09 08:39:27.5311
435	\N	\N	ventas	INSERT	49	\N	{"id": 49, "iva_5": 0.00, "total": 1000000.00, "estado": "PENDIENTE", "iva_10": 90909.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 1000000.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 90909.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T11:39:43", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000017", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T08:39:43.807772", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 1000000.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T08:39:43.807772", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-02-09 08:39:43.807772
436	\N	\N	ventas	UPDATE	49	{"id": 49, "iva_5": 0.00, "total": 1000000.00, "estado": "PENDIENTE", "iva_10": 90909.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 1000000.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 90909.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T11:39:43", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000017", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T08:39:43.807772", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 1000000.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T08:39:43.807772", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	{"id": 49, "iva_5": 0.00, "total": 1000000.00, "estado": "COMPLETADA", "iva_10": 90909.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 1000000.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 90909.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T11:39:43", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 1000000.00, "numero_venta": "VTA-00000017", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T08:39:43.807772", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 0.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T08:39:43.807772", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-02-09 08:39:43.807772
437	\N	\N	ventas	INSERT	50	\N	{"id": 50, "iva_5": 0.00, "total": 1000000.00, "estado": "PENDIENTE", "iva_10": 90909.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 1000000.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 90909.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T11:44:43", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000018", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T08:44:43.976208", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 1000000.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T08:44:43.976208", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-02-09 08:44:43.976208
438	\N	\N	ventas	UPDATE	50	{"id": 50, "iva_5": 0.00, "total": 1000000.00, "estado": "PENDIENTE", "iva_10": 90909.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 1000000.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 90909.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T11:44:43", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000018", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T08:44:43.976208", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 1000000.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T08:44:43.976208", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	{"id": 50, "iva_5": 0.00, "total": 1000000.00, "estado": "COMPLETADA", "iva_10": 90909.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 1000000.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 90909.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T11:44:43", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 1000000.00, "numero_venta": "VTA-00000018", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T08:44:43.976208", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 0.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T08:44:43.976208", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-02-09 08:44:43.976208
439	\N	\N	ventas	INSERT	51	\N	{"id": 51, "iva_5": 0.00, "total": 1000000.00, "estado": "PENDIENTE", "iva_10": 90909.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 1000000.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 90909.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T11:44:53", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000019", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T08:44:53.712619", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 1000000.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T08:44:53.712619", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-02-09 08:44:53.712619
440	\N	\N	ventas	UPDATE	51	{"id": 51, "iva_5": 0.00, "total": 1000000.00, "estado": "PENDIENTE", "iva_10": 90909.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 1000000.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 90909.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T11:44:53", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 0.00, "numero_venta": "VTA-00000019", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T08:44:53.712619", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 1000000.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T08:44:53.712619", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	{"id": 51, "iva_5": 0.00, "total": 1000000.00, "estado": "COMPLETADA", "iva_10": 90909.00, "moneda": "PYG", "vuelto": 0.00, "subtotal": 1000000.00, "timbrado": null, "tipo_pago": "CONTADO", "total_iva": 90909.00, "cliente_id": null, "empresa_id": 1, "tipo_venta": "PRODUCTO", "usuario_id": 1, "es_borrador": false, "fecha_venta": "2026-02-09T11:44:53", "sucursal_id": 1, "tipo_cambio": 1.000000, "vendedor_id": 1, "monto_pagado": 1000000.00, "numero_venta": "VTA-00000019", "origen_venta": "SUCURSAL", "observaciones": null, "recargo_monto": 0.00, "usuario_anula": null, "estado_entrega": null, "fecha_creacion": "2026-02-09T08:44:53.712619", "notas_internas": null, "numero_factura": null, "descuento_monto": 0.00, "factura_impresa": false, "fecha_anulacion": null, "lista_precio_id": null, "monto_pendiente": 0.00, "subtotal_exento": 0.00, "motivo_anulacion": null, "requiere_entrega": false, "tipo_comprobante": "TICKET", "direccion_entrega": null, "fecha_facturacion": null, "fecha_vencimiento": null, "orden_servicio_id": null, "fecha_entrega_real": null, "fecha_modificacion": "2026-02-09T08:44:53.712619", "recargo_porcentaje": 0.00, "subtotal_gravado_5": 0.00, "subtotal_gravado_10": 0.00, "cantidad_impresiones": 0, "descuento_porcentaje": 0.00, "total_moneda_principal": null, "fecha_entrega_programada": null}	\N	\N	\N	2026-02-09 08:44:53.712619
471	1	1	productos	INSERT	18	\N	{"codigo": "767878", "estado": true, "moneda": "PYG", "nombre": "Ford", "marca_id": "1", "tipo_iva": "GRAVADO_10", "usa_lote": "f", "usa_serie": "f", "empresa_id": "1", "descripcion": "uiygugjkj", "sucursal_id": "1", "categoria_id": "1", "codigo_barra": "2261913280827", "precio_venta": 100000, "stock_maximo": 0, "stock_minimo": 0, "precio_compra": 0, "precio_minimo": 0, "punto_reorden": 0, "stock_inicial": 0, "tipo_producto": "PRODUCTO", "controla_stock": "t", "usa_vencimiento": "f", "imagen_principal": "1770659563_3a5e157fae691ab3240f.jpg", "precio_mayorista": 0, "unidad_medida_id": "1", "usuario_creacion": "1", "permite_venta_sin_stock": "f"}	::1	\N	\N	2026-02-09 11:52:43.963111
\.


--
-- Data for Name: cajas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cajas (id, empresa_id, sucursal_id, usuario_id, nombre, descripcion, estado, saldo_inicial, saldo_actual, saldo_esperado, diferencia, fecha_apertura, fecha_cierre, usuario_apertura, usuario_cierre, observaciones_apertura, observaciones_cierre, fecha_creacion, fecha_modificacion) FROM stdin;
1	1	1	1	Caja mostrado	caja mostrador	CERRADA	513650.00	0.00	1027300.00	-1027300.00	2026-02-08 17:35:54	2026-02-09 01:39:50	1	1			2026-01-20 00:02:50	2026-02-08 22:39:50.708701
2	1	1	1	Caja mostrado 2		CERRADA	617000.00	2000000.00	3000000.00	-500000.00	2026-02-09 01:42:23	2026-02-09 18:36:48	1	1			2026-02-09 01:40:46	2026-02-09 15:36:48.322368
3	1	1	2	Casa Casa Central		ABIERTA	1350500.00	1350500.00	0.00	0.00	2026-02-09 19:48:39	\N	2	\N		\N	2026-02-09 19:47:49	2026-02-09 16:48:39.631326
\.


--
-- Data for Name: categorias; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.categorias (id, empresa_id, nombre, descripcion, categoria_padre_id, orden, imagen, icono, color, estado, fecha_creacion, fecha_modificacion, usuario_creacion) FROM stdin;
1	1	Smartphones	Teléfonos inteligentes	\N	0	\N	\N	\N	t	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548	\N
2	1	Tablets	Tablets y iPads	\N	0	\N	\N	\N	t	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548	\N
3	1	Reparaciones	Servicios de reparación	\N	0	\N	\N	\N	t	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548	\N
4	1	catergorias w	safasd	\N	0	\N		#880c0c	f	2026-01-16 14:25:35	2026-01-16 14:25:57	1
5	3	Hardware	Producto electronico	\N	1	\N		#250e77	t	2026-01-21 23:13:57	2026-01-21 23:13:57	6
6	1	Computadoras PC	computadoras de mesa	\N	0	\N		#000000	t	2026-02-09 01:06:01	2026-02-09 01:06:26	1
\.


--
-- Data for Name: chat_participantes; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.chat_participantes (id, chat_id, usuario_id, es_admin, unread_count, fecha_unido, activo) FROM stdin;
\.


--
-- Data for Name: chats; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.chats (id, empresa_id, tipo, nombre, foto, creador_id, ultimo_mensaje_at, estado, fecha_creacion, fecha_modificacion) FROM stdin;
\.


--
-- Data for Name: clientes; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.clientes (id, empresa_id, codigo, tipo_cliente, ruc, ci, nombre, apellido, razon_social, nombre_fantasia, direccion, ciudad, departamento, barrio, referencia_direccion, telefono, celular, email, fecha_nacimiento, sexo, tipo_contribuyente, categoria_cliente, lista_precio_id, limite_credito, credito_utilizado, credito_disponible, dias_credito, descuento_porcentaje, vendedor_asignado_id, foto, notas_internas, observaciones, etiquetas, estado, fecha_creacion, fecha_modificacion, fecha_ultima_compra, total_compras, cantidad_compras, usuario_creacion) FROM stdin;
2	1	CLI-000004	PERSONA	\N	5355147	Datamex	comermex	\N	\N	Av. República Argentina 123	Asunción	CAPITAL	\N	\N	(021) 555-666	0727784415	albert.salo.654@gmail.com	2003-11-06	M	CONTRIBUYENTE	NORMAL	1	1000000000.00	0.00	1000000000.00	0	0.00	\N	1768654934_f80381ca6f5f78a5aec8.jpg	\N	sdfghj	\N	t	2026-01-17 10:02:14	2026-01-22 01:23:32.457175	\N	0.00	0	1
1	1	CLI-003	EMPRESA	80123456-1	\N	Empresa Ejemplo Dos S.A.	\N	Empresa Ejemplo Dos S.A.	\N	Av. General Santos 123	\N	\N	\N	\N	0972778415	0972778415	otra@empresa.com.py	\N	\N	CONTRIBUYENTE	VIP	\N	10000000.00	0.00	10000000.00	10	0.00	\N	\N	\N	\N	\N	t	2026-01-16 01:34:21.74548	2026-01-22 02:36:03.385359	\N	0.00	0	\N
3	1	CLI-000005	PERSONA	\N	5351447	Paola	Morel	\N	\N	ruta 2 km 21	capiata	CENTRAL	\N	\N	0997258356	0997258356	paolamorel03@gmail.com	2003-07-01	F	CONTRIBUYENTE	VIP	\N	1000000000.00	0.00	1000000000.00	30	5.00	\N	1770610770_f1e326f526dc7b367c74.jpg	\N	\N	\N	t	2026-02-09 01:19:30	2026-02-08 22:20:01.472699	\N	0.00	0	1
\.


--
-- Data for Name: clientes_contactos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.clientes_contactos (id, cliente_id, nombre, cargo, telefono, celular, email, es_contacto_principal, observaciones, fecha_creacion) FROM stdin;
\.


--
-- Data for Name: clientes_imagenes; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.clientes_imagenes (id, cliente_id, url, tipo, descripcion, fecha_creacion) FROM stdin;
\.


--
-- Data for Name: compras; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.compras (id, empresa_id, sucursal_id, proveedor_id, usuario_id, numero_compra, numero_factura_proveedor, timbrado_proveedor, tipo_compra, condicion_pago, estado, moneda, tipo_cambio, subtotal, descuento_porcentaje, descuento_monto, total_iva, otros_gastos, total, total_moneda_principal, monto_pagado, monto_pendiente, fecha_compra, fecha_vencimiento, fecha_recepcion, fecha_factura_proveedor, forma_pago, dias_credito, observaciones, notas_internas, numero_despacho, gastos_importacion, fecha_creacion, fecha_modificacion) FROM stdin;
1	1	1	1	1	COMP-00000001	001001565776	3443344	LOCAL	CONTADO	PENDIENTE	PYG	1.000000	450000000.00	0.00	0.00	40909090.91	30000.00	490939090.91	490939090.91	200000.00	490739090.91	2026-01-18 02:03:26.27255	\N	\N	\N	\N	30	compra	\N	\N	0.00	2026-01-17 22:03:26	2026-01-17 22:56:33
\.


--
-- Data for Name: compras_detalle; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.compras_detalle (id, compra_id, producto_id, descripcion, cantidad_solicitada, cantidad_recibida, cantidad_pendiente, precio_unitario, descuento_porcentaje, descuento_monto, porcentaje_iva, subtotal, monto_iva, total, lote, fecha_vencimiento, numero_serie, observaciones, linea_orden) FROM stdin;
1	1	2	iPhone 13 Pro Max	100.000	0.000	100.000	5000000.00	10.00	50000000.00	10.00	450000000.00	40909090.91	450000000.00	\N	\N	\N	\N	0
\.


--
-- Data for Name: configuraciones; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.configuraciones (id, empresa_id, modulo, clave, valor, tipo, descripcion, editable, fecha_creacion, fecha_modificacion) FROM stdin;
\.


--
-- Data for Name: cotizaciones; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cotizaciones (id, empresa_id, sucursal_id, cliente_id, usuario_id, lista_precio_id, numero_cotizacion, estado, subtotal, descuento_porcentaje, descuento_monto, iva_10, iva_5, total, fecha_emision, fecha_vencimiento, venta_id, fecha_conversion, observaciones, notas_internas, terminos_condiciones, fecha_creacion, fecha_modificacion) FROM stdin;
\.


--
-- Data for Name: cotizaciones_detalle; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cotizaciones_detalle (id, cotizacion_id, producto_id, descripcion, cantidad, precio_unitario, descuento_porcentaje, descuento_monto, porcentaje_iva, subtotal, monto_iva, total, observaciones, linea_orden) FROM stdin;
\.


--
-- Data for Name: crm_chats; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.crm_chats (id, empresa_id, sucursal_id, cliente_id, telefono, nombre_contacto, foto_perfil, ultimo_mensaje, fecha_ultimo_mensaje, mensajes_no_leidos, etiquetas, asignado_a, estado, fecha_creacion, fecha_modificacion) FROM stdin;
\.


--
-- Data for Name: crm_mensajes; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.crm_mensajes (id, chat_id, empresa_id, usuario_id, direccion, tipo, contenido, url_adjunto, nombre_archivo, estado, id_mensaje_externo, orden_servicio_id, venta_id, fecha_creacion) FROM stdin;
\.


--
-- Data for Name: crm_plantillas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.crm_plantillas (id, empresa_id, nombre, contenido, tipo, trigger_evento, archivo_adjunto, estado, fecha_creacion, usuario_creacion) FROM stdin;
\.


--
-- Data for Name: cuentas_cobrar; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cuentas_cobrar (id, empresa_id, sucursal_id, cliente_id, venta_id, numero_documento, tipo_documento, fecha_emision, fecha_vencimiento, monto_total, monto_pagado, monto_pendiente, dias_vencido, estado, observaciones, fecha_creacion, fecha_modificacion) FROM stdin;
1	1	1	1	23	VTA-00000003	FACTURA	2026-01-17	2026-01-18	3592527.27	3592527.00	0.27	1	VENCIDO	\N	2026-01-18 00:10:40	2026-01-18 21:25:40
2	1	1	2	24	VTA-00000004	FACTURA	2026-01-17	2026-01-18	38181818.18	38181818.00	0.18	1	VENCIDO	\N	2026-01-18 00:10:40	2026-01-18 21:25:40
3	1	1	2	26	VTA-00000006	TICKET	2026-01-18	2026-01-18	3592527.27	3592527.00	0.27	1	VENCIDO	\N	2026-01-18 17:57:32.032632	2026-01-18 21:25:40
\.


--
-- Data for Name: cuentas_pagar; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cuentas_pagar (id, empresa_id, sucursal_id, proveedor_id, compra_id, numero_documento, tipo_documento, fecha_emision, fecha_vencimiento, monto_total, monto_pagado, monto_pendiente, dias_vencido, estado, observaciones, fecha_creacion, fecha_modificacion) FROM stdin;
1	1	1	1	1	001001565776	LOCAL	2026-01-18	2026-02-16	490939090.91	200000.00	490739090.91	0	PENDIENTE	\N	2026-01-17 22:55:46	2026-02-09 17:17:28
\.


--
-- Data for Name: descuentos_especiales; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.descuentos_especiales (id, empresa_id, nombre, descripcion, tipo_descuento, valor_descuento, aplica_a, id_aplicable, fecha_inicio, fecha_fin, minimo_compra, maximo_descuento, max_usos, usos_actuales, activo, observaciones, fecha_creacion, usuario_creacion) FROM stdin;
\.


--
-- Data for Name: empresa_modulos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.empresa_modulos (id, empresa_id, modulo_id, activo, fecha_activacion, fecha_vencimiento, configuracion, limite_usuarios, limite_registros, limite_sucursales, tipo_licencia, clave_licencia, fecha_inicio_licencia, fecha_fin_licencia, observaciones, usuario_asigna, fecha_creacion, fecha_modificacion) FROM stdin;
1	1	1	t	2026-01-16 01:34:21.74548	\N	\N	\N	\N	\N	PERPETUA	\N	2026-01-16	\N	\N	1	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
2	1	4	t	2026-01-16 01:34:21.74548	\N	\N	\N	\N	\N	PERPETUA	\N	2026-01-16	\N	\N	1	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
3	1	5	t	2026-01-16 01:34:21.74548	\N	\N	\N	\N	\N	PERPETUA	\N	2026-01-16	\N	\N	1	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
4	1	21	t	2026-01-16 01:34:21.74548	\N	\N	\N	\N	\N	PERPETUA	\N	2026-01-16	\N	\N	1	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
5	1	22	t	2026-01-16 01:34:21.74548	\N	\N	\N	\N	\N	PERPETUA	\N	2026-01-16	\N	\N	1	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
6	1	23	t	2026-01-16 01:34:21.74548	\N	\N	\N	\N	\N	PERPETUA	\N	2026-01-16	\N	\N	1	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
7	1	24	t	2026-01-16 01:34:21.74548	\N	\N	\N	\N	\N	PERPETUA	\N	2026-01-16	\N	\N	1	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
9	1	3	t	2026-01-16 01:34:21.74548	2026-02-15 01:34:21.74548	\N	\N	\N	\N	MENSUAL	\N	2026-01-16	2026-02-15	\N	1	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
8	1	9	t	2026-01-16 01:34:21.74548	2026-03-17 01:34:21.74548	\N	\N	\N	\N	MENSUAL	\N	2026-01-16	2026-03-17	\N	1	2026-01-16 01:34:21.74548	2026-02-09 17:22:57.873806
10	1	27	f	2026-01-28 19:23:24.101201	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	2026-01-28 19:23:24.101201	2026-02-09 20:25:59
13	1	12	t	2026-02-09 18:17:03.318877	2026-03-11 18:17:03.318877	\N	\N	\N	\N	MENSUAL	\N	2026-02-09	2026-03-11	\N	1	2026-02-09 18:17:03.318877	2026-02-09 18:17:03.318877
14	1	11	t	2026-02-09 18:17:09.873801	2026-03-11 18:17:09.873801	\N	\N	\N	\N	MENSUAL	\N	2026-02-09	2026-03-11	\N	1	2026-02-09 18:17:09.873801	2026-02-09 18:17:09.873801
\.


--
-- Data for Name: empresas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.empresas (id, nombre, ruc, direccion, ciudad, telefono, email, logo, protector_pantalla, aplica_iva, porcentaje_iva, usa_facturacion_electronica, timbrado, establecimiento, punto_expedicion, dias_plazo_retiro, moneda_principal, terminos_condiciones, politica_garantia, estado, fecha_creacion, fecha_modificacion, usuario_creacion, tema_oscuro) FROM stdin;
3	COMPUSUR PY	07282811-0	Sta. Rosa, Ñemby 111217	ÑEMBY	+595971414482	compusurvillarrica@gmail.com	1768784920_08df6ccac94683766d71.jpg	\N	t	10.00	f	\N	1	1	30	PYG	\N	\N	t	2026-01-19 02:08:40.70466	2026-01-19 02:08:40.70466	1	f
2	xxx	11156766-6	Av. República Argentina 123	Asunción	(021) 555-666	albert.salo.654@gmail.com	1768598459_a7d67cc9bad19fa1bce0.jpg	\N	t	10.00	f	1234567	1	1	90	PYG	\N	\N	f	2026-01-16 22:20:59.285386	2026-02-08 22:33:54.712296	1	f
1	COMPUSURPY	80027345-1	ACCESO SUR ,SOBRE, Independencia Nacional, Ñemby	Ñemby	(021) 123-456	Arquisoft@gmail.com.py	1768922380_be83a78e92103133c926.jpg	\N	t	10.00	f	1234567	1	1	30	PYG	\N	\N	t	2026-01-16 01:34:21.74548	2026-02-09 08:56:33.175051	\N	f
\.


--
-- Data for Name: estados_orden; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.estados_orden (id, nombre, descripcion, color, icono, orden, es_final, permite_edicion, permite_facturacion, notifica_cliente, plantilla_email, plantilla_sms, estado) FROM stdin;
1	RECEPCIONADA	Equipo recibido, pendiente de diagnóstico	#6c757d	inbox	1	f	t	f	t	\N	\N	t
2	EN_DIAGNOSTICO	Técnico revisando el equipo	#17a2b8	search	2	f	t	f	f	\N	\N	t
3	DIAGNOSTICADA	Diagnóstico completado, esperando aprobación	#ffc107	clipboard-check	3	f	t	f	t	\N	\N	t
4	PRESUPUESTO_ENVIADO	Presupuesto enviado al cliente	#fd7e14	file-invoice-dollar	4	f	t	f	t	\N	\N	t
5	APROBADA	Cliente aprobó la reparación	#28a745	check-circle	5	f	t	f	t	\N	\N	t
6	EN_ESPERA_REPUESTOS	Esperando llegada de repuestos	#6f42c1	hourglass-half	6	f	t	f	t	\N	\N	t
7	EN_REPARACION	Equipo en proceso de reparación	#007bff	tools	7	f	t	f	f	\N	\N	t
8	REPARADA	Reparación finalizada, lista para entrega	#20c997	check-double	8	f	t	t	t	\N	\N	t
9	LISTA_RETIRO	Equipo listo para retiro	#17c671	bell	9	f	t	t	t	\N	\N	t
10	ENTREGADA	Equipo entregado al cliente	#28a745	handshake	10	t	t	f	f	\N	\N	t
11	NO_REPARADA	No se pudo o no se quiso reparar	#dc3545	times-circle	11	t	t	t	t	\N	\N	t
12	RECHAZADA	Cliente rechazó el presupuesto	#dc3545	ban	12	t	t	f	t	\N	\N	t
13	CANCELADA	Orden cancelada por el cliente	#6c757d	window-close	13	t	t	f	t	\N	\N	t
14	NO_RETIRADA	Cliente no retiró el equipo en plazo	#fd7e14	exclamation-triangle	14	t	t	f	t	\N	\N	t
\.


--
-- Data for Name: facturacion_electronica; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.facturacion_electronica (id, venta_id, cdc, numero_factura, timbrado, fecha_emision, tipo_documento, tipo_transaccion, tipo_emision, estado_sifen, codigo_seguridad, xml_enviado, xml_respuesta, xml_firmado, qr_code, url_consulta, url_kude, fecha_envio, fecha_aprobacion, fecha_rechazo, fecha_cancelacion, mensaje_aprobacion, mensaje_error, codigo_error, intentos_envio, fecha_ultimo_intento, fecha_creacion) FROM stdin;
\.


--
-- Data for Name: listas_precios; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.listas_precios (id, empresa_id, nombre, descripcion, tipo_lista, tipo_descuento, descuento_general, margen_porcentaje, es_predeterminada, moneda, fecha_vigencia_desde, fecha_vigencia_hasta, aplica_lunes, aplica_martes, aplica_miercoles, aplica_jueves, aplica_viernes, aplica_sabado, aplica_domingo, hora_desde, hora_hasta, sucursales_aplicables, categorias_aplicables, clientes_aplicables, cantidad_minima, estado, fecha_creacion, fecha_modificacion, usuario_creacion) FROM stdin;
1	1	Lista de precio 3%	lista de precio de 3%	GENERAL	PORCENTAJE	3.00	\N	t	PYG	2026-01-01	2026-02-08	t	t	t	t	t	t	t	\N	\N	\N	\N	\N	3	t	2026-01-16 21:59:11	2026-01-16 22:00:45	1
\.


--
-- Data for Name: listas_precios_detalle; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.listas_precios_detalle (id, lista_id, producto_id, tipo_aplicacion, descuento_porcentaje, descuento_monto, precio_especial, precio_final, precio_oferta, es_oferta, fecha_inicio_oferta, fecha_fin_oferta, cantidad_minima, cantidad_maxima, observaciones, fecha_creacion) FROM stdin;
2	1	4	PORCENTAJE	0.00	\N	\N	400000.00	\N	f	\N	\N	1	\N	\N	2026-01-17 02:00:45.758093
\.


--
-- Data for Name: logs_sistema; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.logs_sistema (id, empresa_id, usuario_id, nivel, modulo, mensaje, datos, ip_address, fecha_creacion) FROM stdin;
\.


--
-- Data for Name: marcas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.marcas (id, empresa_id, nombre, descripcion, logo, pais_origen, sitio_web, estado, fecha_creacion) FROM stdin;
1	1	Samsung	Electrónica coreana	\N	\N	\N	t	2026-01-16 01:34:21.74548
2	1	Apple	Productos Apple	\N	\N	\N	t	2026-01-16 01:34:21.74548
4	1	Datemex	datamex	1768531483_b089557c058fe88bfc90.png	paraguay		t	2026-01-16 03:44:43.422928
3	1	Xiaomi	Electrónica china	\N	\N	\N	f	2026-01-16 01:34:21.74548
5	3	Asus	Marcas de diversos componentes	1769048220_710bf772a89c817d15e4.png	China		t	2026-01-22 03:17:00.083524
6	1	Ford		1770610122_b82f47f1881f20673d45.png	Estados unidos de norte america	https://www.ford.com.py	t	2026-02-08 22:08:42.548302
\.


--
-- Data for Name: mensajes; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.mensajes (id, chat_id, usuario_id, mensaje, tipo, adjunto_url, leido_por_todos, fecha_envio) FROM stdin;
\.


--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.migrations (id, version, class, "group", namespace, "time", batch) FROM stdin;
2	20260128190500	App\\Database\\Migrations\\CreateCrmTables	default	App	1769624604	1
3	20260128192000	App\\Database\\Migrations\\SeedCrmModule	default	App	1769624604	1
4	20260209120000	App\\Database\\Migrations\\CreateInternalChatTables	default	App	1770664302	2
\.


--
-- Data for Name: modulos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.modulos (id, nombre, codigo, descripcion, icono, color, categoria, orden, requiere_licencia, precio_mensual, es_modulo_base, url_documentacion, version, estado, fecha_creacion, fecha_modificacion) FROM stdin;
1	Punto de Venta	POS	Sistema de punto de venta para registrar ventas	cash-register	#28a745	VENTAS	1	f	0.00	t	\N	\N	t	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
2	Cotizaciones	COTIZACIONES	Gestión de cotizaciones y presupuestos	file-invoice-dollar	#17a2b8	VENTAS	2	f	0.00	f	\N	\N	t	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
3	Facturación Electrónica	FACTURACION_ELECTRONICA	Integración con SIFEN para facturación electrónica	file-invoice	#007bff	VENTAS	3	f	0.00	f	\N	\N	t	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
4	Gestión de Clientes	CLIENTES	Administración de clientes y contactos	users	#6f42c1	VENTAS	4	f	0.00	t	\N	\N	t	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
5	Inventario	INVENTARIO	Control de stock y productos	boxes	#fd7e14	INVENTARIO	5	f	0.00	t	\N	\N	t	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
6	Transferencias	TRANSFERENCIAS	Transferencias de stock entre sucursales	exchange-alt	#20c997	INVENTARIO	6	f	0.00	f	\N	\N	t	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
7	Ajustes de Inventario	AJUSTES	Ajustes y correcciones de inventario	edit	#ffc107	INVENTARIO	7	f	0.00	f	\N	\N	t	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
8	Lotes y Vencimientos	LOTES	Control de lotes y fechas de vencimiento	calendar-times	#e83e8c	INVENTARIO	8	f	0.00	f	\N	\N	t	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
9	Órdenes de Servicio	ORDENES_SERVICIO	Gestión de órdenes de servicio y reparaciones	tools	#6610f2	SERVICIOS	9	f	0.00	f	\N	\N	t	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
10	Garantías	GARANTIAS	Control de garantías de productos y servicios	shield-alt	#17a2b8	SERVICIOS	10	f	0.00	f	\N	\N	t	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
11	Compras	COMPRAS	Gestión de compras a proveedores	shopping-cart	#dc3545	COMPRAS	11	f	0.00	f	\N	\N	t	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
12	Proveedores	PROVEEDORES	Administración de proveedores	truck	#28a745	COMPRAS	12	f	0.00	f	\N	\N	t	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
13	Órdenes de Compra	ORDENES_COMPRA	Generación y seguimiento de órdenes de compra	file-alt	#007bff	COMPRAS	13	f	0.00	f	\N	\N	t	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
14	Cuentas por Cobrar	CUENTAS_COBRAR	Gestión de cuentas por cobrar	hand-holding-usd	#20c997	FINANZAS	14	f	0.00	f	\N	\N	t	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
15	Cuentas por Pagar	CUENTAS_PAGAR	Gestión de cuentas por pagar	money-check-alt	#dc3545	FINANZAS	15	f	0.00	f	\N	\N	t	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
16	Cajas	CAJAS	Control de cajas y movimientos de efectivo	cash-register	#28a745	FINANZAS	16	f	0.00	f	\N	\N	t	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
17	Bancos	BANCOS	Conciliación bancaria y movimientos	university	#007bff	FINANZAS	17	f	0.00	f	\N	\N	t	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
18	Reportes de Ventas	REPORTES_VENTAS	Reportes y análisis de ventas	chart-line	#17a2b8	REPORTES	18	f	0.00	f	\N	\N	t	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
19	Reportes de Inventario	REPORTES_INVENTARIO	Reportes de stock y movimientos	chart-bar	#6f42c1	REPORTES	19	f	0.00	f	\N	\N	t	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
20	Reportes Financieros	REPORTES_FINANCIEROS	Estados financieros y reportes contables	chart-pie	#fd7e14	REPORTES	20	f	0.00	f	\N	\N	t	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
21	Dashboard Ejecutivo	DASHBOARD	Panel de control con métricas clave	tachometer-alt	#20c997	REPORTES	21	f	0.00	t	\N	\N	t	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
22	Usuarios y Permisos	USUARIOS	Gestión de usuarios, roles y permisos	user-cog	#6c757d	CONFIGURACION	22	f	0.00	t	\N	\N	t	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
23	Configuración General	CONFIGURACION	Configuración general del sistema	cogs	#495057	CONFIGURACION	23	f	0.00	t	\N	\N	t	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
24	Sucursales	SUCURSALES	Administración de sucursales	building	#6610f2	CONFIGURACION	24	f	0.00	t	\N	\N	t	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
25	Auditoría	AUDITORIA	Registro de auditoría y logs del sistema	history	#343a40	CONFIGURACION	25	f	0.00	f	\N	\N	t	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
27	CRM & WhatsApp	MOD_CRM	Gestión de clientes y mensajería WhatsApp integrada	fab fa-whatsapp	#25D366	VENTAS	2	f	0.00	t	\N	\N	f	2026-01-28 19:23:24.088223	2026-01-28 19:23:24.088223
\.


--
-- Data for Name: modulos_dependencias; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.modulos_dependencias (id, modulo_id, modulo_requerido_id, es_obligatorio, descripcion, fecha_creacion) FROM stdin;
1	3	1	t	La facturación electrónica requiere el módulo de punto de venta	2026-01-16 01:34:21.74548
2	2	4	t	Las cotizaciones requieren gestión de clientes	2026-01-16 01:34:21.74548
3	9	4	t	Las órdenes de servicio requieren gestión de clientes	2026-01-16 01:34:21.74548
4	9	5	t	Las órdenes de servicio requieren control de inventario	2026-01-16 01:34:21.74548
5	6	5	t	Las transferencias requieren control de inventario	2026-01-16 01:34:21.74548
6	7	5	t	Los ajustes de inventario requieren el módulo de inventario	2026-01-16 01:34:21.74548
7	8	5	t	El control de lotes requiere el módulo de inventario	2026-01-16 01:34:21.74548
8	10	9	t	La gestión de garantías requiere el módulo de órdenes de servicio	2026-01-16 01:34:21.74548
9	11	12	t	Las compras requieren gestión de proveedores	2026-01-16 01:34:21.74548
10	11	5	t	Las compras requieren control de inventario	2026-01-16 01:34:21.74548
11	13	12	t	Las órdenes de compra requieren gestión de proveedores	2026-01-16 01:34:21.74548
12	13	11	t	Las órdenes de compra requieren el módulo de compras	2026-01-16 01:34:21.74548
13	14	1	t	Las cuentas por cobrar requieren el módulo de ventas	2026-01-16 01:34:21.74548
14	14	4	t	Las cuentas por cobrar requieren gestión de clientes	2026-01-16 01:34:21.74548
15	15	11	t	Las cuentas por pagar requieren el módulo de compras	2026-01-16 01:34:21.74548
16	15	12	t	Las cuentas por pagar requieren gestión de proveedores	2026-01-16 01:34:21.74548
17	18	1	t	Los reportes de ventas requieren el módulo de ventas	2026-01-16 01:34:21.74548
18	19	5	t	Los reportes de inventario requieren el módulo de inventario	2026-01-16 01:34:21.74548
19	20	16	f	Los reportes financieros pueden incluir información de cajas	2026-01-16 01:34:21.74548
\.


--
-- Data for Name: movimientos_caja; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.movimientos_caja (id, caja_id, tipo_movimiento, monto, moneda, tipo_cambio, monto_moneda_principal, concepto, referencia, documento_id, tipo_documento, observaciones, fecha_movimiento, usuario_id) FROM stdin;
1	1	APERTURA	200000.00	PYG	1.000000	200000.00	Apertura de caja	\N	\N	\N	monto en guaranies	2026-01-20 04:03:21.512135	1
2	1	AJUSTE	444800.00	PYG	1.000000	444800.00	Ajuste por arqueo - Diferencia: 444.800 Gs.	\N	\N	\N	Arqueo realizado el 21/01/2026 22:01	2026-01-22 02:01:34.678388	1
3	1	CIERRE	1610000.00	PYG	1.000000	1610000.00	Cierre de caja - Diferencia: -1.610.000 Gs.	\N	\N	\N	fghjl	2026-01-22 02:02:15.602214	1
4	1	APERTURA	1000000.00	PYG	1.000000	1000000.00	Apertura de caja	\N	\N	\N	Datamex	2026-01-22 02:27:02.351836	1
5	1	CIERRE	1000000.00	PYG	1.000000	1000000.00	Cierre de caja - Diferencia: -1.000.000 Gs.	\N	\N	\N	sobrante	2026-01-25 05:12:49.179092	1
6	1	APERTURA	513650.00	PYG	1.000000	513650.00	Apertura de caja	\N	\N	\N		2026-02-08 21:35:54.233808	1
7	1	CIERRE	1027300.00	PYG	1.000000	1027300.00	Cierre de caja - Diferencia: -1.027.300 Gs.	\N	\N	\N		2026-02-08 22:39:50.708701	1
8	2	APERTURA	617000.00	PYG	1.000000	617000.00	Apertura de caja	\N	\N	\N		2026-02-08 22:42:23.703072	1
9	2	INGRESO	200000.00	PYG	1.000000	200000.00	Venta POS VTA-VTA-00000026	Venta ID: 59	59	VENTA	\N	2026-02-09 17:35:06	1
10	2	INGRESO	400000.00	PYG	1.000000	400000.00	Venta POS VTA-VTA-00000027	Venta ID: 60	60	VENTA	\N	2026-02-09 17:38:26	1
11	2	EGRESO	100000.00	PYG	1.000000	100000.00	efectivo	ljkjkju	\N	\N		2026-02-09 17:50:27	1
12	2	AJUSTE	283000.00	PYG	1.000000	283000.00	Ajuste por arqueo - Diferencia: 283.000 Gs.	\N	\N	\N	Arqueo realizado el 09/02/2026 18:16	2026-02-09 15:16:31.348999	1
13	2	CIERRE	500000.00	PYG	1.000000	500000.00	Cierre de caja - Diferencia: -500.000 Gs.	\N	\N	\N		2026-02-09 18:36:48	1
14	3	APERTURA	1350500.00	PYG	1.000000	1350500.00	Apertura de caja	\N	\N	\N		2026-02-09 19:48:39	2
\.


--
-- Data for Name: movimientos_stock; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.movimientos_stock (id, empresa_id, sucursal_id, producto_id, tipo_movimiento, cantidad, cantidad_anterior, cantidad_nueva, costo_unitario, precio_venta_unitario, moneda, documento_referencia, tipo_documento, lote, motivo, fecha_movimiento, usuario_id) FROM stdin;
1	1	1	2	TRANSFERENCIA_SALIDA	10.000	10.000	0.000	\N	\N	PYG	TRANSF-00000001	TRANSFERENCIA		\N	2026-01-16 19:50:38.919678	1
2	1	1	2	AJUSTE_ENTRADA	10.000	0.000	10.000	5000000.00	\N	PYG	AJUS-00000001	AJUSTE	\N	Ingreso de mercaderia	2026-01-17 01:11:33.648438	1
3	1	1	2	AJUSTE_ENTRADA	10.000	10.000	0.000	5000000.00	\N	PYG	\N	\N	\N	Ajuste manual de stock	2026-01-17 01:25:56.493702	1
4	1	1	5	AJUSTE_ENTRADA	100.000	0.000	100.000	0.00	\N	PYG	AJUS-00000002	AJUSTE	\N	Ingreso de mercaderia	2026-01-20 01:35:07.432985	1
5	1	2	2	TRANSFERENCIA_ENTRADA	10.000	8.000	18.000	\N	\N	PYG	TRANSF-00000001	TRANSFERENCIA		\N	2026-02-08 22:10:18.516949	1
6	1	1	5	TRANSFERENCIA_SALIDA	5.000	80.000	75.000	\N	\N	PYG	TRANSF-00000002	TRANSFERENCIA	\N	\N	2026-02-08 22:13:49.904329	1
7	1	2	5	TRANSFERENCIA_ENTRADA	5.000	0.000	5.000	\N	\N	PYG	TRANSF-00000002	TRANSFERENCIA	\N	\N	2026-02-08 22:13:58.449238	1
8	1	1	4	AJUSTE_ENTRADA	100.000	0.000	100.000	0.00	\N	PYG	AJUS-00000004	AJUSTE	\N	conteo fisico	2026-02-09 01:16:02	1
\.


--
-- Data for Name: notificaciones; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.notificaciones (id, empresa_id, usuario_id, tipo, titulo, mensaje, leida, fecha_lectura, accion_url, icono, estado, fecha_creacion, fecha_expiracion) FROM stdin;
1	1	\N	WARNING	Stock Mínimo Alcanzado	El producto iPhone 13 Pro Max ha alcanzado el stock mínimo. Stock actual: 0.000	f	\N	\N	\N	t	2026-01-16 19:50:38.919678	\N
2	1	\N	WARNING	Stock Mínimo Alcanzado	El producto iPhone 13 Pro Max ha alcanzado el stock mínimo. Stock actual: 0.000	f	\N	\N	\N	t	2026-01-17 01:25:56.482608	\N
\.


--
-- Data for Name: ordenes_servicio; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ordenes_servicio (id, empresa_id, sucursal_id, cliente_id, usuario_recepcion, tecnico_asignado, numero_orden, estado_id, prioridad, tipo_equipo, marca, modelo, numero_serie, imei, color, estado_fisico, accesorios, patron_desbloqueo, clave_equipo, problema_reportado, diagnostico, solucion_aplicada, observaciones_tecnico, observaciones_internas, observaciones_cliente, fecha_recepcion, fecha_diagnostico, fecha_aprobacion, fecha_inicio_reparacion, fecha_finalizacion, fecha_entrega, fecha_limite_retiro, fecha_vencimiento_presupuesto, moneda, tipo_cambio, presupuesto_diagnostico, presupuesto_repuestos, presupuesto_mano_obra, presupuesto_otros, presupuesto_subtotal, presupuesto_descuento, presupuesto_iva, presupuesto_total, costo_real_repuestos, costo_real_mano_obra, costo_real_otros, costo_real_subtotal, costo_real_iva, costo_real_total, anticipo_monto, saldo_pendiente, venta_id, fecha_facturacion, garantia_dias, fecha_vencimiento_garantia, terminos_garantia, presupuesto_aprobado, cliente_contactado, cliente_notificado_listo, requiere_autorizacion, autorizado_por, fecha_autorizacion, estado_activo, fecha_creacion, fecha_modificacion, usuario_creacion) FROM stdin;
1	1	1	1	1	\N	OS-00000001	9	BAJA	notebook	samsung	rrss	lasdljkls	-	azul	dfghjm,.	dfghjk	234559	\N	cvbnm,	sdfghjkl;dfghjkl	dfghjkldfghjk	erthjkdfghj	\N	dfghj	2026-01-16 20:56:13.304637	\N	\N	\N	\N	\N	2026-04-16	\N	PYG	1.000000	0.00	0.00	780000.00	0.00	709090.90	0.00	70909.10	780000.00	0.00	780000.00	0.00	709090.90	70909.10	780000.00	0.00	0.00	\N	\N	0	\N	\N	f	f	f	f	\N	\N	t	2026-01-16 16:56:13	2026-02-08 22:30:16.2582	1
\.


--
-- Data for Name: ordenes_servicio_comentarios; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ordenes_servicio_comentarios (id, orden_id, usuario_id, comentario, es_interno, visible_cliente, fecha_creacion, fecha_modificacion) FROM stdin;
\.


--
-- Data for Name: ordenes_servicio_documentos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ordenes_servicio_documentos (id, orden_id, nombre_archivo, url, tipo_documento, descripcion, "tamaño_bytes", formato, fecha_creacion, usuario_creacion) FROM stdin;
\.


--
-- Data for Name: ordenes_servicio_historial; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ordenes_servicio_historial (id, orden_id, estado_anterior_id, estado_nuevo_id, observaciones, duracion_minutos, fecha_cambio, usuario_id, ip_address) FROM stdin;
2	1	1	2		\N	2026-01-19 00:52:07.861003	1	::1
3	1	2	9	Falta pagar\r\n	\N	2026-01-20 16:07:36	1	::1
4	1	9	2	dfgh	\N	2026-01-21 17:01:53	1	::1
5	1	2	6	dfghjl	\N	2026-01-21 17:02:10	1	::1
6	1	6	7	asdfghjkl	\N	2026-01-21 22:47:43	1	::1
7	1	7	9	a	\N	2026-02-09 01:30:16	1	::1
\.


--
-- Data for Name: ordenes_servicio_imagenes; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ordenes_servicio_imagenes (id, orden_id, url, url_thumbnail, titulo, descripcion, tipo, categoria, orden, "tamaño_bytes", ancho_px, alto_px, formato, es_publica, visible_cliente, fecha_creacion, usuario_creacion) FROM stdin;
2	1	1769045949_d33d2aeabed875798682.jpg	\N	\N	\N	ANTES	\N	0	\N	\N	\N	\N	f	t	2026-01-22 02:39:09.652889	1
3	1	1769045960_23cc13b9fbe7210fd3bc.jpg	\N	\N	\N	ANTES	\N	0	\N	\N	\N	\N	f	t	2026-01-22 02:39:20.710213	1
4	1	1770611428_641af3366efebf2c2855.jpg	\N	\N	\N	ANTES	\N	0	\N	\N	\N	\N	f	t	2026-02-08 22:30:28.914445	1
\.


--
-- Data for Name: ordenes_servicio_mano_obra; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ordenes_servicio_mano_obra (id, orden_id, descripcion, tipo_servicio, horas_estimadas, horas_reales, precio_hora, precio_fijo, descuento_porcentaje, descuento_monto, tipo_iva, porcentaje_iva, subtotal, monto_iva, total, tecnico_id, es_presupuesto, fue_realizado, observaciones, fecha_agregado, usuario_agrega) FROM stdin;
4	1	cmaibo de antalla	REPARACION	\N	\N	\N	100000.00	0.00	0.00	GRAVADO_10	10.00	100000.00	9090.91	100000.00	\N	t	t	\N	2026-01-20 15:25:52	1
13	1	12000	REPARACION	\N	\N	\N	500000.00	0.00	0.00	GRAVADO_10	10.00	500000.00	45454.55	500000.00	\N	t	f	\N	2026-01-21 22:48:14	1
14	1	Cambio de teclado	REPARACION	\N	\N	\N	180000.00	0.00	0.00	GRAVADO_10	10.00	180000.00	16363.64	180000.00	\N	t	f	\N	2026-02-09 01:28:21	1
\.


--
-- Data for Name: ordenes_servicio_notificaciones; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ordenes_servicio_notificaciones (id, orden_id, tipo_notificacion, destinatario, asunto, mensaje, estado, fecha_programada, fecha_envio, fecha_entrega, fecha_lectura, respuesta, error, costo, proveedor, mensaje_id, fecha_creacion) FROM stdin;
\.


--
-- Data for Name: ordenes_servicio_repuestos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ordenes_servicio_repuestos (id, orden_id, producto_id, cantidad, precio_unitario, descuento_porcentaje, descuento_monto, tipo_iva, porcentaje_iva, subtotal, monto_iva, total, es_presupuesto, fue_utilizado, costo_unitario, observaciones, fecha_agregado, usuario_agrega) FROM stdin;
\.


--
-- Data for Name: pagos_cuentas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.pagos_cuentas (id, empresa_id, sucursal_id, cuenta_cobrar_id, cuenta_pagar_id, forma_pago, monto, moneda, tipo_cambio, referencia, fecha_pago, fecha_acreditacion, observaciones, usuario_id, caja_id, fecha_creacion) FROM stdin;
1	1	1	\N	1	EFECTIVO	200000.00	PYG	1.000000	comermex	2026-01-17	\N	\N	1	\N	2026-01-18 02:56:33.531694
\.


--
-- Data for Name: pagos_ventas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.pagos_ventas (id, venta_id, forma_pago, monto, moneda, tipo_cambio, monto_moneda_principal, referencia, numero_transaccion, numero_autorizacion, numero_tarjeta_ultimos, tipo_tarjeta, banco_emisor, numero_cheque, banco_cheque, fecha_cheque, cuenta_destino, comision_porcentaje, comision_monto, monto_neto, fecha_pago, fecha_acreditacion, estado, observaciones, usuario_id, caja_id) FROM stdin;
8	23	EFECTIVO	3592527.00	PYG	1.000000	3592527.00		\N	\N	\N	\N	\N	\N	\N	\N	\N	0.00	0.00	\N	2026-01-18 03:52:57.426578	\N	APROBADO	\N	1	\N
9	24	EFECTIVO	38181818.00	PYG	1.000000	38181818.00		\N	\N	\N	\N	\N	\N	\N	\N	\N	0.00	0.00	\N	2026-01-18 03:57:48.973976	\N	APROBADO	\N	1	\N
10	25	EFECTIVO	3818182.00	PYG	1.000000	3818182.00		\N	\N	\N	\N	\N	\N	\N	\N	\N	0.00	0.00	\N	2026-01-18 04:36:36.832548	\N	APROBADO	\N	1	\N
11	26	EFECTIVO	3592527.00	PYG	1.000000	3592527.00		\N	\N	\N	\N	\N	\N	\N	\N	\N	0.00	0.00	\N	2026-01-18 17:57:32.032632	\N	APROBADO	\N	1	\N
12	27	EFECTIVO	3818182.00	PYG	1.000000	3818182.00		\N	\N	\N	\N	\N	\N	\N	\N	\N	0.00	0.00	\N	2026-01-18 18:21:30.591639	\N	APROBADO	\N	1	\N
13	28	EFECTIVO	3500000.00	PYG	1.000000	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	0.00	0.00	\N	2026-01-20 04:29:08.374319	\N	APROBADO	\N	1	\N
14	29	EFECTIVO	10000.00	PYG	1.000000	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	0.00	0.00	\N	2026-01-20 13:17:25.89267	\N	APROBADO	\N	1	\N
15	30	TARJETA_CREDITO	155200.00	PYG	1.000000	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	0.00	0.00	\N	2026-01-20 15:50:34.952161	\N	APROBADO	\N	1	\N
16	31	TRANSFERENCIA	9700.00	PYG	1.000000	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	0.00	0.00	\N	2026-01-22 01:23:32.457175	\N	APROBADO	\N	1	\N
17	36	TARJETA_CREDITO	20000.00	PYG	1.000000	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	0.00	0.00	\N	2026-01-22 02:36:03.385359	\N	APROBADO	\N	1	\N
18	37	TARJETA_CREDITO	500000.00	PYG	1.000000	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	0.00	0.00	\N	2026-02-08 21:56:56.036544	\N	APROBADO	\N	1	\N
19	37	TRANSFERENCIA	500000.00	PYG	1.000000	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	0.00	0.00	\N	2026-02-08 21:56:56.036544	\N	APROBADO	\N	1	\N
20	44	EFECTIVO	400000.00	PYG	1.000000	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	0.00	0.00	\N	2026-02-09 08:06:55.135366	\N	APROBADO	\N	1	\N
21	45	EFECTIVO	400000.00	PYG	1.000000	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	0.00	0.00	\N	2026-02-09 08:07:12.007826	\N	APROBADO	\N	1	\N
22	46	EFECTIVO	400000.00	PYG	1.000000	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	0.00	0.00	\N	2026-02-09 08:30:57.378599	\N	APROBADO	\N	1	\N
23	47	EFECTIVO	400000.00	PYG	1.000000	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	0.00	0.00	\N	2026-02-09 08:31:31.993372	\N	APROBADO	\N	1	\N
24	48	EFECTIVO	400000.00	PYG	1.000000	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	0.00	0.00	\N	2026-02-09 08:39:27.5311	\N	APROBADO	\N	1	\N
25	49	EFECTIVO	1000000.00	PYG	1.000000	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	0.00	0.00	\N	2026-02-09 08:39:43.807772	\N	APROBADO	\N	1	\N
26	50	EFECTIVO	1000000.00	PYG	1.000000	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	0.00	0.00	\N	2026-02-09 08:44:43.976208	\N	APROBADO	\N	1	\N
27	51	EFECTIVO	1000000.00	PYG	1.000000	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	0.00	0.00	\N	2026-02-09 08:44:53.712619	\N	APROBADO	\N	1	\N
28	52	EFECTIVO	400000.00	PYG	1.000000	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	0.00	0.00	\N	2026-02-09 08:45:07.608553	\N	APROBADO	\N	1	\N
29	53	EFECTIVO	400000.00	PYG	1.000000	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	0.00	0.00	\N	2026-02-09 08:47:00.6594	\N	APROBADO	\N	1	\N
30	54	EFECTIVO	400000.00	PYG	1.000000	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	0.00	0.00	\N	2026-02-09 09:09:48.209671	\N	APROBADO	\N	1	\N
31	55	EFECTIVO	1000000.00	PYG	1.000000	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	0.00	0.00	\N	2026-02-09 09:10:15.532361	\N	APROBADO	\N	1	\N
32	56	EFECTIVO	400000.00	PYG	1.000000	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	0.00	0.00	\N	2026-02-09 09:21:10.715855	\N	APROBADO	\N	1	\N
33	57	EFECTIVO	400000.00	PYG	1.000000	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	0.00	0.00	\N	2026-02-09 13:31:53.451582	\N	APROBADO	\N	1	\N
34	59	EFECTIVO	200000.00	PYG	1.000000	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	0.00	0.00	\N	2026-02-09 14:35:06.123203	\N	APROBADO	\N	1	2
35	59	TRANSFERENCIA	1000000.00	PYG	1.000000	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	0.00	0.00	\N	2026-02-09 14:35:06.123203	\N	APROBADO	\N	1	2
36	60	EFECTIVO	400000.00	PYG	1.000000	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	0.00	0.00	\N	2026-02-09 14:38:26.05427	\N	APROBADO	\N	1	2
\.


--
-- Data for Name: permisos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.permisos (id, rol_id, modulo, ver, crear, editar, eliminar, anular, fecha_creacion) FROM stdin;
1	6	DASHBOARD	t	f	f	f	f	2026-02-09 16:20:50.9675
2	6	CLIENTES	t	t	f	t	f	2026-02-09 16:20:51.053237
3	6	PRODUCTOS	t	f	f	f	f	2026-02-09 16:20:51.054959
4	6	VENTAS	t	f	f	f	f	2026-02-09 16:20:51.056114
5	6	POS	t	t	f	f	f	2026-02-09 17:11:53.429312
6	6	CAJAS	t	t	f	f	f	2026-02-09 17:11:53.509029
7	6	INVENTARIO	t	f	f	f	f	2026-02-09 17:11:53.509461
8	1	DASHBOARD	t	t	t	t	t	2026-02-09 18:13:44.429407
9	1	CLIENTES	t	t	t	t	t	2026-02-09 18:13:44.492522
10	1	PROVEEDORES	t	t	t	t	t	2026-02-09 18:13:44.494403
11	1	PRODUCTOS	t	t	t	t	t	2026-02-09 18:13:44.496293
12	1	INVENTARIO	t	t	t	t	t	2026-02-09 18:13:44.497912
13	1	VENTAS	t	t	t	t	t	2026-02-09 18:13:44.499085
14	1	COMPRAS	t	t	t	t	t	2026-02-09 18:13:44.500154
15	1	SERVICIOS	t	t	t	t	t	2026-02-09 18:13:44.501101
16	1	FINANZAS	t	t	t	t	t	2026-02-09 18:13:44.501947
17	1	REPORTES	t	t	t	t	t	2026-02-09 18:13:44.502808
18	1	USUARIOS	t	t	t	t	t	2026-02-09 18:13:44.503614
19	1	EMPRESAS	t	t	t	t	t	2026-02-09 18:13:44.504377
20	1	SUCURSALES	t	t	t	t	t	2026-02-09 18:13:44.505085
21	1	ROLES	t	t	t	t	t	2026-02-09 18:13:44.505811
22	1	MODULOS	t	t	t	t	t	2026-02-09 18:13:44.506525
23	1	CONFIGURACION	t	t	t	t	t	2026-02-09 18:13:44.507675
24	1	NOTIFICACIONES	t	t	t	t	t	2026-02-09 18:13:44.508469
\.


--
-- Data for Name: producto_imagenes; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.producto_imagenes (id, producto_id, url, url_thumbnail, titulo, descripcion, es_principal, orden, tipo, "tamaño_bytes", ancho_px, alto_px, formato, fecha_creacion, usuario_creacion) FROM stdin;
\.


--
-- Data for Name: productos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.productos (id, empresa_id, categoria_id, marca_id, unidad_medida_id, codigo, codigo_barra, codigo_fabricante, nombre, descripcion, descripcion_corta, especificaciones_tecnicas, tipo_producto, tipo_iva, porcentaje_iva, precio_compra, precio_venta, precio_mayorista, precio_minimo, margen_utilidad, moneda, stock_minimo, stock_maximo, punto_reorden, controla_stock, permite_venta_sin_stock, usa_lote, usa_vencimiento, usa_serie, imagen_principal, peso, dimensiones, garantia_meses, observaciones, tags, es_destacado, es_novedad, es_oferta, estado, fecha_creacion, fecha_modificacion, usuario_creacion) FROM stdin;
4	1	1	1	1	lkjalfja	jafdjajdio	\N	iPhone 13 Pro Max	asdasdadsa	\N	\N	PRODUCTO	GRAVADO_10	10.00	40000.00	400000.00	4000.00	4000.00	900.00	PYG	5	100	4	t	f	f	f	f	1768530662_270fa593bc2dd6585ec6.jpg	\N	\N	0	\N	\N	f	f	f	t	2026-01-15 23:31:02	2026-01-15 23:31:02	1
7	3	5	5	1	001	001	\N	Placa Madre	Placa madre asus de 10 generacion	\N	\N	PRODUCTO	GRAVADO_10	10.00	500000.00	900000.00	750000.00	750000.00	80.00	PYG	5	20	7	t	f	f	f	f	1769048524_af9e94e71185138264d6.jpg	\N	\N	0	\N	\N	f	f	f	t	2026-01-21 23:22:04	2026-01-21 23:22:04	6
8	3	5	5	1	cpy-001	001	\N	Placa Madre	Placa madre asus de 10 generacion	\N	\N	PRODUCTO	GRAVADO_10	10.00	500000.00	900000.00	750000.00	750000.00	80.00	PYG	5	20	7	t	f	f	f	f	1769048931_56499ec130ec00308160.jpg	\N	\N	0	\N	\N	f	f	f	t	2026-01-21 23:28:51	2026-01-21 23:28:51	6
5	1	1	1	1	001	2770989328009	\N	Samsung Gala	sfsfsfsfddd	\N	\N	PRODUCTO	GRAVADO_10	10.00	1000000.00	1000000.00	1000000.00	1000000.00	0.00	PYG	5	50	10	t	t	t	t	f	1768583964_420a7e0282cac39cc7bb.jpg	\N	\N	0	\N	\N	f	f	f	t	2026-01-16 14:19:24	2026-01-29 18:14:08.177208	1
6	1	2	1	1	0012	2327054410383	\N	Samsung Gala	Es un gran producto todos los deberiamos poder usar	\N	\N	PRODUCTO	GRAVADO_10	10.00	100000000.00	100000000.00	100000000.00	100000000.00	0.00	PYG	5	50	7	t	t	t	t	f	1768584032_582e004bd9c82d0bb2ae.png	\N	\N	0	\N	\N	f	f	f	t	2026-01-16 14:20:32	2026-02-08 22:04:17.758022	1
1	1	1	1	1	SM-G998	\N	\N	Samsung Galaxy S21 Ultra	Smartphone gama alta	\N	\N	PRODUCTO	GRAVADO_10	\N	2500000.00	3500000.00	\N	\N	40.00	PYG	5	50	10	t	f	f	f	f	\N	\N	\N	0	\N	\N	f	f	f	f	2026-01-16 01:34:21.74548	2026-02-08 22:04:39.795167	\N
18	1	1	1	1	767878	2261913280827	\N	Ford	uiygugjkj	\N	\N	PRODUCTO	GRAVADO_10	\N	0.00	100000.00	0.00	0.00	\N	PYG	0	0	0	t	f	f	f	f	1770659563_3a5e157fae691ab3240f.jpg	\N	\N	0	\N	\N	f	f	f	t	2026-02-09 14:52:43	2026-02-09 14:52:43	1
19	1	3	2	1	Pro-004	2475373593270	\N	ford to on	sfsfs	\N	\N	PRODUCTO	GRAVADO_10	\N	1000000.00	2000000.00	1500000.00	1750000.00	100.00	PYG	2	10	5	t	f	f	f	f	1770660066_fa309aa89c1bf27a0bd4.png	\N	\N	0	\N	\N	f	f	f	t	2026-02-09 15:01:06	2026-02-09 15:01:06	1
3	1	3	\N	10	SERV-001	\N	\N	Reparación pantalla	Reemplazo de pantalla	\N	\N	SERVICIO	GRAVADO_10	\N	0.00	150000.00	\N	\N	0.00	PYG	0	0	0	f	f	f	f	f	\N	\N	\N	0	\N	\N	f	f	f	f	2026-01-16 01:34:21.74548	2026-02-09 12:01:59.876078	\N
2	1	2	2	1	IPH-13		\N	iPhone 13 Pro Maxdd	iPhone 13 Pro Max 256GB	\N	\N	PRODUCTO	GRAVADO_10	10.00	500000000.00	650000000.00	0.00	0.00	30.00	PYG	3	30	5	t	t	t	t	f	1768757756_e86e34db5248c3a9855b.jpg	\N	\N	0	\N	\N	f	f	f	t	2026-01-16 01:34:21.74548	2026-02-09 12:02:21.535807	\N
20	1	2	2	1	pro-006	2342669243353	\N	prueba	ssfs	\N	\N	PRODUCTO	GRAVADO_10	\N	0.00	4000000.00	0.00	0.00	\N	PYG	2	10	5	t	f	f	f	f	1770663970_89613549c874e4de6f2d.png	\N	\N	0	\N	\N	f	f	f	t	2026-02-09 16:06:10	2026-02-09 16:06:10	1
\.


--
-- Data for Name: proveedores; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.proveedores (id, empresa_id, codigo, ruc, razon_social, nombre_comercial, direccion, ciudad, pais, telefono, celular, email, sitio_web, contacto_nombre, contacto_cargo, contacto_telefono, contacto_email, dias_pago, condiciones_pago, moneda_preferida, cuenta_bancaria, banco, observaciones, calificacion, estado, fecha_creacion, fecha_modificacion, usuario_creacion) FROM stdin;
1	1	pro-0010	7363666-3	Comermex s.a	Datamex	ajsjsjisis	Asuncion	Paraguay	0972778415	0972778415	datamex@datamex.com.py	https://datamex.com.py	Alberto Cantero	Representante	0987654456	alberto@gmail.com	30	Efectivo	PYG	-	-	sssdk	\N	t	2026-01-17 21:26:32	2026-01-17 21:29:41	1
2	1	Pro-002	552221-9	San Carlos s.a	San Carlos	ss		Paraguay	0997258356	0997258356	sancorlos023@gmail.com		pedro	Gerente	0973586214	predro@gmail.com	0		PYG				\N	t	2026-02-09 02:12:24	2026-02-09 02:12:39	1
\.


--
-- Data for Name: roles; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.roles (id, nombre, descripcion, nivel, es_rol_sistema, estado, fecha_creacion) FROM stdin;
1	Administrador	Acceso completo al sistema, gestiona todas las empresas	1	t	t	2026-01-16 01:34:21.74548
2	Administrador de Empresa	Acceso completo dentro de su empresa	2	t	t	2026-01-16 01:34:21.74548
3	Gerente de Empresa	Acceso operativo completo de su empresa	3	t	t	2026-01-16 01:34:21.74548
4	Sub Administrador	Acceso limitado a sucursales asignadas	4	t	t	2026-01-16 01:34:21.74548
5	Usuario Operativo	Acceso limitado a su sucursal	5	t	t	2026-01-16 01:34:21.74548
6	vendedor	Vendedor de articulo cajero	4	f	t	2026-02-09 16:09:14.119019
\.


--
-- Data for Name: stock; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.stock (id, producto_id, sucursal_id, cantidad, cantidad_reservada, cantidad_disponible, ubicacion, lote, fecha_vencimiento, costo_promedio, fecha_actualizacion) FROM stdin;
2	1	2	15.000	0.000	15.000	\N	\N	\N	2500000.00	2026-01-16 01:34:21.74548
3	2	1	0.000	0.000	0.000		\N	\N	5000000.00	2026-01-16 01:34:21.74548
1	1	1	11.000	8.000	3.000	\N	\N	\N	2500000.00	2026-01-20 04:29:08.374319
4	2	2	18.000	0.000	18.000	\N	\N	\N	5000000.00	2026-01-16 01:34:21.74548
11	5	2	5.000	0.000	5.000	\N	\N	\N	0.00	2026-02-08 22:13:58.449238
14	20	1	0.000	0.000	0.000	\N	\N	\N	0.00	2026-02-09 16:06:11
8	5	1	73.000	0.000	73.000	\N	\N	\N	0.00	2026-01-20 01:35:07.432985
12	4	1	86.000	0.000	86.000	\N	\N	\N	0.00	2026-02-08 22:16:02.205484
\.


--
-- Data for Name: sucursal_modulos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.sucursal_modulos (id, sucursal_id, modulo_id, empresa_modulo_id, activo, fecha_activacion, configuracion, limite_usuarios, limite_registros_diarios, permite_crear, permite_editar, permite_eliminar, permite_anular, permite_reportes, observaciones, usuario_asigna, fecha_creacion, fecha_modificacion) FROM stdin;
1	1	1	1	t	2026-01-16 01:34:21.74548	\N	\N	\N	t	t	t	t	t	\N	1	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
2	1	4	2	t	2026-01-16 01:34:21.74548	\N	\N	\N	t	t	t	t	t	\N	1	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
3	1	5	3	t	2026-01-16 01:34:21.74548	\N	\N	\N	t	t	t	t	t	\N	1	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
4	1	21	4	t	2026-01-16 01:34:21.74548	\N	\N	\N	t	t	t	t	t	\N	1	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
5	1	22	5	t	2026-01-16 01:34:21.74548	\N	\N	\N	t	t	t	t	t	\N	1	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
6	1	23	6	t	2026-01-16 01:34:21.74548	\N	\N	\N	t	t	t	t	t	\N	1	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
7	1	24	7	t	2026-01-16 01:34:21.74548	\N	\N	\N	t	t	t	t	t	\N	1	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
8	1	9	8	t	2026-01-16 01:34:21.74548	\N	\N	\N	t	t	t	t	t	\N	1	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
9	1	3	9	t	2026-01-16 01:34:21.74548	\N	\N	\N	t	t	t	t	t	\N	1	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
10	2	1	1	t	2026-01-16 01:34:21.74548	\N	\N	\N	t	t	t	t	t	\N	1	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
11	2	4	2	t	2026-01-16 01:34:21.74548	\N	\N	\N	t	t	t	t	t	\N	1	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
12	2	5	3	t	2026-01-16 01:34:21.74548	\N	\N	\N	t	t	t	t	t	\N	1	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
13	2	21	4	t	2026-01-16 01:34:21.74548	\N	\N	\N	t	t	t	t	t	\N	1	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
14	2	22	5	t	2026-01-16 01:34:21.74548	\N	\N	\N	t	t	t	t	t	\N	1	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
15	2	23	6	t	2026-01-16 01:34:21.74548	\N	\N	\N	t	t	t	t	t	\N	1	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
16	2	24	7	t	2026-01-16 01:34:21.74548	\N	\N	\N	t	t	t	t	t	\N	1	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
17	2	9	8	t	2026-01-16 01:34:21.74548	\N	\N	\N	t	t	t	t	t	\N	1	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
18	2	3	9	t	2026-01-16 01:34:21.74548	\N	\N	\N	t	t	t	t	t	\N	1	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548
19	1	27	10	f	2026-01-28 19:23:24.116116	\N	\N	\N	t	t	t	t	t	\N	\N	2026-01-28 19:23:24.116116	2026-02-09 17:25:59.966862
\.


--
-- Data for Name: sucursales; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.sucursales (id, empresa_id, nombre, codigo, direccion, ciudad, departamento, telefono, email, latitud, longitud, horario_apertura, horario_cierre, es_matriz, codigo_establecimiento, responsable, estado, fecha_creacion, fecha_modificacion, tema_oscuro) FROM stdin;
1	1	Casa Central	\N	Av. República Argentina 123	Asunción	CAPITAL	(021) 123-456	\N	\N	\N	\N	\N	t	001	\N	t	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548	f
2	1	Sucursal San Lorenzo	\N	Av. San Lorenzo 456	San Lorenzo	CENTRAL	(021) 789-012	\N	\N	\N	\N	\N	f	002	\N	t	2026-01-16 01:34:21.74548	2026-01-16 01:34:21.74548	f
3	3	DEPOSITO-CENTRAL	DEP-001	Av. General Santos 123	ÑEMBY	CAPITAL	+595971414482	compusurvillarrica@gmail.com	-25.39327796	-57.53131902	08:00:00	19:00:00	t	DEP	Luis Alberto (Dueño)	t	2026-01-18 22:17:20	2026-01-18 22:17:20	f
4	3	SUCURSAL N°1	SURC-001	ACCESO SUR ,SOBRE, Independencia Nacional, Ñemby	ÑEMBY	CAPITAL	0987480507		-25.39142060	-57.54583069	08:00:00	18:00:00	f	SUR	Luis Alberto(Hijo)	t	2026-01-18 22:21:49	2026-01-18 22:21:49	f
\.


--
-- Data for Name: talonarios; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.talonarios (id, empresa_id, sucursal_id, tipo_comprobante, timbrado, establecimiento, punto_expedicion, numero_desde, numero_hasta, numero_actual, fecha_inicio_vigencia, fecha_fin_vigencia, estado, observaciones, fecha_creacion, usuario_creacion) FROM stdin;
1	1	1	FACTURA	1234567	001	001	1000001	1100000	1000011	2026-01-20	2027-01-20	ACTIVO		2026-01-20 00:45:39	1
\.


--
-- Data for Name: tipos_cambio; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.tipos_cambio (id, empresa_id, fecha, moneda_origen, moneda_destino, compra, venta, promedio, fuente, observaciones, fecha_creacion, usuario_creacion) FROM stdin;
\.


--
-- Data for Name: transferencias_stock; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.transferencias_stock (id, empresa_id, numero_transferencia, sucursal_origen_id, sucursal_destino_id, estado, fecha_solicitud, fecha_envio, fecha_recepcion, usuario_solicita, usuario_autoriza, usuario_recibe, observaciones, motivo_rechazo) FROM stdin;
1	1	TRANSF-00000001	1	2	RECIBIDA	2026-01-16 19:47:14.524372	2026-01-16 15:50:38	2026-02-09 01:10:18	1	1	1	rtyujkl	\N
2	1	TRANSF-00000002	1	2	RECIBIDA	2026-02-08 22:13:40.631847	2026-02-09 01:13:49	2026-02-09 01:13:58	1	1	1	Verificar si llega todo \n	\N
\.


--
-- Data for Name: transferencias_stock_detalle; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.transferencias_stock_detalle (id, transferencia_id, producto_id, cantidad_solicitada, cantidad_enviada, cantidad_recibida, cantidad_pendiente, lote, observaciones) FROM stdin;
1	1	2	10.000	10.000	10.000	0.000		
2	2	5	5.000	5.000	5.000	0.000	\N	\N
\.


--
-- Data for Name: unidades_medida; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.unidades_medida (id, nombre, abreviatura, tipo, estado) FROM stdin;
1	Unidad	Un	UNIDAD	t
2	Kilogramo	Kg	PESO	t
3	Gramo	g	PESO	t
4	Litro	L	VOLUMEN	t
5	Metro	m	LONGITUD	t
6	Caja	Cj	UNIDAD	t
7	Paquete	Paq	UNIDAD	t
8	Docena	Doc	UNIDAD	t
9	Par	Par	UNIDAD	t
10	Servicio	Srv	UNIDAD	t
\.


--
-- Data for Name: usuario_sucursales; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.usuario_sucursales (id, usuario_id, sucursal_id, fecha_asignacion, usuario_asignacion) FROM stdin;
\.


--
-- Data for Name: usuarios; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.usuarios (id, empresa_id, sucursal_id, rol_id, nombre, apellido, ci, email, telefono, direccion, username, password, foto_perfil, firma_digital, estado, ultimo_acceso, fecha_creacion, fecha_modificacion, usuario_creacion, preferencias, tema_oscuro) FROM stdin;
5	3	3	2	Luis Alberto	Cantero	123456	compusurvillarrica@gmail.com	+595971414482	\N	LuisCantero	$2y$10$WlsOsU03MVru2v1Ae9y8ieKwx/eHfQd7GdNyqBaupXimjK9hh8Vp.	1768785860_e3d49f26dfc9b56c5657.jpg	\N	t	2026-01-18 22:25:12	2026-01-19 02:24:21.032868	2026-01-19 02:25:12.189003	1	\N	\N
3	1	2	5	Carlos	Rodríguez	9876543	carlos@electronica.com.py	(0983) 987-654	ww	carlos	$2a$06$1vqenScBJztwANFB22UOTOay/1QAE41k./M3MhzDvWCZperKiPSi.	1768595479_26979c22f1826ff4a2c8.jpeg	\N	t	\N	2026-01-16 01:34:21.74548	2026-01-16 21:31:19.037993	\N	\N	\N
4	1	2	4	datemex	Rodríguez	5355147	manager@demo.com	(0983) 987-654	erty	manager	$2y$10$HQimm97SJkyjyx.bS0psyOa7HCqmc4f9S6PIYSTFeGcsYgZkIc5TC	1768596576_582b077595d4de164353.jpeg	\N	t	\N	2026-01-16 21:49:36.816209	2026-01-16 21:49:36.816209	1	\N	\N
6	3	4	3	Luis Alberto	Cantero Britez	87664647	canterol888@gmail.com	+595971414482	\N	LuiyiCantero	$2y$10$WiOFDwIcHdrMu4MXEM5ZwOX50.7kwpk4c1psNWl4udCvCjfp5kEe2	1769047807_0d0e1deb4262832de5ae.jpg	\N	t	2026-01-21 23:05:29	2026-01-19 03:36:37.495024	2026-01-22 03:11:29.943727	1	{"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}	f
1	1	1	1	Juan	Pérez	1234567	admin@electronica.com.py	(0981) 123-456	\N	admin	$2y$10$brmGqqTAvQoVt1Psd/WYmOxhJvf16mNPVI7HEHY5yvbniKNwtOfdW	1770583533_dccd1abbac817a82e016.jpg	\N	t	2026-02-09 16:26:44	2026-01-16 01:34:21.74548	2026-02-09 13:32:51.001075	\N	{"idioma": "es", "tema_oscuro": false, "formato_hora": "H:i:s", "zona_horaria": "America/Asuncion", "formato_fecha": "d/m/Y", "notificaciones_push": true, "notificaciones_email": true}	f
2	1	1	6	María	González	7654321	maria@electronica.com.py	(0982) 654-321	\N	maria	$2y$10$JhKjgOv252DHvv8sReQQR.AJN2o74WgBl1AXOSP0Y0opb9YgTaCPm	\N	\N	t	2026-02-09 19:23:58	2026-01-16 01:34:21.74548	2026-02-09 16:23:58.96703	\N	\N	\N
\.


--
-- Data for Name: ventas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ventas (id, empresa_id, sucursal_id, cliente_id, usuario_id, vendedor_id, lista_precio_id, orden_servicio_id, numero_venta, tipo_comprobante, numero_factura, timbrado, tipo_venta, tipo_pago, estado, moneda, tipo_cambio, subtotal, descuento_porcentaje, descuento_monto, recargo_porcentaje, recargo_monto, subtotal_gravado_10, iva_10, subtotal_gravado_5, iva_5, subtotal_exento, total_iva, total, total_moneda_principal, monto_pagado, monto_pendiente, vuelto, observaciones, notas_internas, origen_venta, fecha_venta, fecha_vencimiento, fecha_anulacion, fecha_facturacion, motivo_anulacion, usuario_anula, requiere_entrega, direccion_entrega, fecha_entrega_programada, fecha_entrega_real, estado_entrega, es_borrador, factura_impresa, cantidad_impresiones, fecha_creacion, fecha_modificacion) FROM stdin;
28	1	1	\N	1	1	\N	\N	VTA-00000008	TICKET	\N	\N	PRODUCTO	CONTADO	COMPLETADA	PYG	1.000000	3500000.00	0.00	0.00	0.00	0.00	0.00	318182.00	0.00	0.00	0.00	318182.00	3500000.00	\N	3500000.00	0.00	0.00	\N	\N	SUCURSAL	2026-01-20 00:29:08	\N	\N	\N	\N	\N	f	\N	\N	\N	\N	f	f	0	2026-01-20 04:29:08.374319	2026-01-20 04:29:08.374319
29	1	1	\N	1	1	\N	\N	VTA-00000009	FACTURA	1000002	1234567	PRODUCTO	CONTADO	COMPLETADA	PYG	1.000000	10000.00	0.00	0.00	0.00	0.00	0.00	909.00	0.00	0.00	0.00	909.00	10000.00	\N	10000.00	0.00	0.00	\N	\N	SUCURSAL	2026-01-20 09:17:25	\N	\N	\N	\N	\N	f	\N	\N	\N	\N	f	f	0	2026-01-20 13:17:25.89267	2026-01-20 13:17:25.89267
30	1	1	\N	1	1	\N	\N	VTA-00000010	FACTURA	1000003	1234567	PRODUCTO	CONTADO	COMPLETADA	PYG	1.000000	155200.00	0.00	4800.00	0.00	0.00	0.00	14109.00	0.00	0.00	0.00	14109.00	155200.00	\N	155200.00	0.00	0.00	\N	\N	SUCURSAL	2026-01-20 11:50:34	\N	\N	\N	\N	\N	f	\N	\N	\N	\N	f	f	0	2026-01-20 15:50:34.952161	2026-01-20 15:50:34.952161
23	1	1	1	1	1	1	\N	VTA-00000003	FACTURA	\N	\N	PRODUCTO	CONTADO	PENDIENTE	PYG	1.000000	3293150.00	0.00	101850.00	0.00	0.00	3293150.00	299377.27	0.00	0.00	0.00	299377.27	3592527.27	3592527.27	3592527.00	0.27	0.00		\N	SUCURSAL	2026-01-17 23:52:57	\N	\N	\N	\N	\N	f	\N	\N	\N	\N	f	f	0	2026-01-18 03:52:57.426578	2026-01-18 03:52:57.426578
24	1	1	2	1	1	\N	\N	VTA-00000004	FACTURA	\N	\N	PRODUCTO	CONTADO	PENDIENTE	PYG	1.000000	35000000.00	0.00	0.00	0.00	0.00	35000000.00	3181818.18	0.00	0.00	0.00	3181818.18	38181818.18	38181818.18	38181818.00	0.18	0.00		\N	SUCURSAL	2026-01-17 23:57:48	\N	\N	\N	\N	\N	f	\N	\N	\N	\N	f	f	0	2026-01-18 03:57:48.973976	2026-01-18 03:57:48.973976
35	1	1	2	1	\N	\N	\N	NC-00000001	NOTA_CREDITO	\N	\N	PRODUCTO	CONTADO	COMPLETADA	PYG	1.000000	9090.91	0.00	0.00	0.00	0.00	0.00	0.00	0.00	0.00	0.00	909.09	10000.00	\N	10000.00	0.00	0.00	Devolución de venta #VTA-00000011. Motivo: asdfghjklqsdfghjkkasd	\N	SUCURSAL	2026-01-21 21:48:19	\N	\N	\N	\N	\N	f	\N	\N	\N	\N	f	f	0	2026-01-21 21:48:19	2026-01-21 21:48:19
25	1	1	2	1	1	\N	\N	VTA-00000005	TICKET	\N	\N	PRODUCTO	CREDITO	COMPLETADA	PYG	1.000000	3500000.00	0.00	0.00	0.00	0.00	3500000.00	318181.82	0.00	0.00	0.00	318181.82	3818181.82	3818181.82	3818182.00	-0.18	0.18		\N	SUCURSAL	2026-01-18 00:36:36	\N	\N	\N	\N	\N	f	\N	\N	\N	\N	f	f	0	2026-01-18 04:36:36.832548	2026-01-18 04:36:36.832548
26	1	1	2	1	1	1	\N	VTA-00000006	TICKET	\N	\N	PRODUCTO	CREDITO	PENDIENTE	PYG	1.000000	3293150.00	0.00	101850.00	0.00	0.00	3293150.00	299377.27	0.00	0.00	0.00	299377.27	3592527.27	3592527.27	3592527.00	0.27	0.00		\N	SUCURSAL	2026-01-18 13:57:32	\N	\N	\N	\N	\N	f	\N	\N	\N	\N	f	f	0	2026-01-18 17:57:32.032632	2026-01-18 17:57:32.032632
31	1	1	2	1	1	\N	\N	VTA-00000011	FACTURA	1000004	1234567	PRODUCTO	CONTADO	DEVUELTA_TOTAL	PYG	1.000000	9700.00	0.00	300.00	0.00	0.00	0.00	882.00	0.00	0.00	0.00	882.00	9700.00	\N	9700.00	0.00	0.00	\N	\N	SUCURSAL	2026-01-21 21:23:32	\N	\N	\N	\N	\N	f	\N	\N	\N	\N	f	f	0	2026-01-22 01:23:32.457175	2026-01-21 21:48:19
27	1	1	1	1	1	\N	\N	VTA-00000007	TICKET	\N	\N	PRODUCTO	CREDITO	COMPLETADA	PYG	1.000000	3500000.00	0.00	0.00	0.00	0.00	3500000.00	318181.82	0.00	0.00	0.00	318181.82	3818181.82	3818181.82	3818182.00	-0.18	0.18		\N	SUCURSAL	2026-01-18 14:21:30	\N	\N	\N	\N	\N	f	\N	\N	\N	\N	f	f	0	2026-01-18 18:21:30.591639	2026-01-18 18:21:30.591639
37	1	1	\N	1	1	\N	\N	VTA-00000002	TICKET	\N	\N	PRODUCTO	CONTADO	COMPLETADA	PYG	1.000000	1000000.00	0.00	0.00	0.00	0.00	0.00	90909.00	0.00	0.00	0.00	90909.00	1000000.00	\N	1000000.00	0.00	0.00	\N	\N	SUCURSAL	2026-02-09 00:56:56	\N	\N	\N	\N	\N	f	\N	\N	\N	\N	f	f	0	2026-02-08 21:56:56.036544	2026-02-08 21:56:56.036544
44	1	1	\N	1	1	\N	\N	VTA-00000012	TICKET	\N	\N	PRODUCTO	CONTADO	COMPLETADA	PYG	1.000000	400000.00	0.00	0.00	0.00	0.00	0.00	36364.00	0.00	0.00	0.00	36364.00	400000.00	\N	400000.00	0.00	0.00	\N	\N	SUCURSAL	2026-02-09 11:06:55	\N	\N	\N	\N	\N	f	\N	\N	\N	\N	f	f	0	2026-02-09 08:06:55.135366	2026-02-09 08:06:55.135366
45	1	1	\N	1	1	\N	\N	VTA-00000013	FACTURA	1000006	1234567	PRODUCTO	CONTADO	COMPLETADA	PYG	1.000000	400000.00	0.00	0.00	0.00	0.00	0.00	36364.00	0.00	0.00	0.00	36364.00	400000.00	\N	400000.00	0.00	0.00	\N	\N	SUCURSAL	2026-02-09 11:07:12	\N	\N	\N	\N	\N	f	\N	\N	\N	\N	f	f	0	2026-02-09 08:07:12.007826	2026-02-09 08:07:12.007826
46	1	1	\N	1	1	\N	\N	VTA-00000014	TICKET	\N	\N	PRODUCTO	CONTADO	COMPLETADA	PYG	1.000000	400000.00	0.00	0.00	0.00	0.00	0.00	36364.00	0.00	0.00	0.00	36364.00	400000.00	\N	400000.00	0.00	0.00	\N	\N	SUCURSAL	2026-02-09 11:30:57	\N	\N	\N	\N	\N	f	\N	\N	\N	\N	f	f	0	2026-02-09 08:30:57.378599	2026-02-09 08:30:57.378599
47	1	1	\N	1	1	\N	\N	VTA-00000015	TICKET	\N	\N	PRODUCTO	CONTADO	COMPLETADA	PYG	1.000000	400000.00	0.00	0.00	0.00	0.00	0.00	36364.00	0.00	0.00	0.00	36364.00	400000.00	\N	400000.00	0.00	0.00	\N	\N	SUCURSAL	2026-02-09 11:31:31	\N	\N	\N	\N	\N	f	\N	\N	\N	\N	f	f	0	2026-02-09 08:31:31.993372	2026-02-09 08:31:31.993372
48	1	1	\N	1	1	\N	\N	VTA-00000016	TICKET	\N	\N	PRODUCTO	CONTADO	COMPLETADA	PYG	1.000000	400000.00	0.00	0.00	0.00	0.00	0.00	36364.00	0.00	0.00	0.00	36364.00	400000.00	\N	400000.00	0.00	0.00	\N	\N	SUCURSAL	2026-02-09 11:39:27	\N	\N	\N	\N	\N	f	\N	\N	\N	\N	f	f	0	2026-02-09 08:39:27.5311	2026-02-09 08:39:27.5311
49	1	1	\N	1	1	\N	\N	VTA-00000017	TICKET	\N	\N	PRODUCTO	CONTADO	COMPLETADA	PYG	1.000000	1000000.00	0.00	0.00	0.00	0.00	0.00	90909.00	0.00	0.00	0.00	90909.00	1000000.00	\N	1000000.00	0.00	0.00	\N	\N	SUCURSAL	2026-02-09 11:39:43	\N	\N	\N	\N	\N	f	\N	\N	\N	\N	f	f	0	2026-02-09 08:39:43.807772	2026-02-09 08:39:43.807772
50	1	1	\N	1	1	\N	\N	VTA-00000018	TICKET	\N	\N	PRODUCTO	CONTADO	COMPLETADA	PYG	1.000000	1000000.00	0.00	0.00	0.00	0.00	0.00	90909.00	0.00	0.00	0.00	90909.00	1000000.00	\N	1000000.00	0.00	0.00	\N	\N	SUCURSAL	2026-02-09 11:44:43	\N	\N	\N	\N	\N	f	\N	\N	\N	\N	f	f	0	2026-02-09 08:44:43.976208	2026-02-09 08:44:43.976208
51	1	1	\N	1	1	\N	\N	VTA-00000019	TICKET	\N	\N	PRODUCTO	CONTADO	COMPLETADA	PYG	1.000000	1000000.00	0.00	0.00	0.00	0.00	0.00	90909.00	0.00	0.00	0.00	90909.00	1000000.00	\N	1000000.00	0.00	0.00	\N	\N	SUCURSAL	2026-02-09 11:44:53	\N	\N	\N	\N	\N	f	\N	\N	\N	\N	f	f	0	2026-02-09 08:44:53.712619	2026-02-09 08:44:53.712619
52	1	1	\N	1	1	\N	\N	VTA-00000020	FACTURA	1000007	1234567	PRODUCTO	CONTADO	COMPLETADA	PYG	1.000000	400000.00	0.00	0.00	0.00	0.00	0.00	36364.00	0.00	0.00	0.00	36364.00	400000.00	\N	400000.00	0.00	0.00	\N	\N	SUCURSAL	2026-02-09 11:45:07	\N	\N	\N	\N	\N	f	\N	\N	\N	\N	f	f	0	2026-02-09 08:45:07.608553	2026-02-09 08:45:07.608553
53	1	1	\N	1	1	\N	\N	VTA-00000021	FACTURA	1000008	1234567	PRODUCTO	CONTADO	COMPLETADA	PYG	1.000000	400000.00	0.00	0.00	0.00	0.00	0.00	36364.00	0.00	0.00	0.00	36364.00	400000.00	\N	400000.00	0.00	0.00	\N	\N	SUCURSAL	2026-02-09 11:47:00	\N	\N	\N	\N	\N	f	\N	\N	\N	\N	f	f	0	2026-02-09 08:47:00.6594	2026-02-09 08:47:00.6594
54	1	1	\N	1	1	\N	\N	VTA-00000022	TICKET	\N	\N	PRODUCTO	CONTADO	COMPLETADA	PYG	1.000000	400000.00	0.00	0.00	0.00	0.00	0.00	36364.00	0.00	0.00	0.00	36364.00	400000.00	\N	400000.00	0.00	0.00	\N	\N	SUCURSAL	2026-02-09 12:09:48	\N	\N	\N	\N	\N	f	\N	\N	\N	\N	f	f	0	2026-02-09 09:09:48.209671	2026-02-09 09:09:48.209671
55	1	1	\N	1	1	\N	\N	VTA-00000023	FACTURA	1000009	1234567	PRODUCTO	CONTADO	COMPLETADA	PYG	1.000000	1000000.00	0.00	0.00	0.00	0.00	0.00	90909.00	0.00	0.00	0.00	90909.00	1000000.00	\N	1000000.00	0.00	0.00	\N	\N	SUCURSAL	2026-02-09 12:10:15	\N	\N	\N	\N	\N	f	\N	\N	\N	\N	f	f	0	2026-02-09 09:10:15.532361	2026-02-09 09:10:15.532361
56	1	1	\N	1	1	\N	\N	VTA-00000024	FACTURA	1000010	1234567	PRODUCTO	CONTADO	COMPLETADA	PYG	1.000000	400000.00	0.00	0.00	0.00	0.00	0.00	36364.00	0.00	0.00	0.00	36364.00	400000.00	\N	400000.00	0.00	0.00	\N	\N	SUCURSAL	2026-02-09 12:21:10	\N	\N	\N	\N	\N	f	\N	\N	\N	\N	f	f	0	2026-02-09 09:21:10.715855	2026-02-09 09:21:10.715855
57	1	1	\N	1	1	\N	\N	VTA-00000025	TICKET	\N	\N	PRODUCTO	CONTADO	COMPLETADA	PYG	1.000000	400000.00	0.00	0.00	0.00	0.00	0.00	36364.00	0.00	0.00	0.00	36364.00	400000.00	\N	400000.00	0.00	0.00	\N	\N	SUCURSAL	2026-02-09 16:31:53	\N	\N	\N	\N	\N	f	\N	\N	\N	\N	f	f	0	2026-02-09 13:31:53.451582	2026-02-09 13:31:53.451582
58	1	1	1	1	\N	\N	\N	NC-00000002	NOTA_CREDITO	\N	\N	PRODUCTO	CONTADO	COMPLETADA	PYG	1.000000	18181.82	0.00	0.00	0.00	0.00	0.00	0.00	0.00	0.00	0.00	1818.18	20000.00	\N	20000.00	0.00	0.00	Devolución de venta #VTA-00000001. Motivo: Se rompio la compu	\N	SUCURSAL	2026-02-09 17:18:06	\N	\N	\N	\N	\N	f	\N	\N	\N	\N	f	f	0	2026-02-09 17:18:06	2026-02-09 17:18:06
36	1	1	1	1	1	\N	\N	VTA-00000001	FACTURA	1000005	1234567	PRODUCTO	CONTADO	DEVUELTA_TOTAL	PYG	1.000000	20000.00	0.00	0.00	0.00	0.00	0.00	1818.00	0.00	0.00	0.00	1818.00	20000.00	\N	20000.00	0.00	0.00	\N	\N	SUCURSAL	2026-01-21 22:36:03	\N	\N	\N	\N	\N	f	\N	\N	\N	\N	f	f	0	2026-01-22 02:36:03.385359	2026-02-09 17:18:06
59	1	1	\N	1	1	\N	\N	VTA-00000026	TICKET	\N	\N	PRODUCTO	CONTADO	COMPLETADA	PYG	1.000000	1200000.00	0.00	0.00	0.00	0.00	0.00	109091.00	0.00	0.00	0.00	109091.00	1200000.00	\N	1200000.00	0.00	0.00	\N	\N	SUCURSAL	2026-02-09 17:35:06	\N	\N	\N	\N	\N	f	\N	\N	\N	\N	f	f	0	2026-02-09 14:35:06.123203	2026-02-09 14:35:06.123203
60	1	1	\N	1	1	\N	\N	VTA-00000027	FACTURA	1000011	1234567	PRODUCTO	CONTADO	COMPLETADA	PYG	1.000000	400000.00	0.00	0.00	0.00	0.00	0.00	36364.00	0.00	0.00	0.00	36364.00	400000.00	\N	400000.00	0.00	0.00	\N	\N	SUCURSAL	2026-02-09 17:38:26	\N	\N	\N	\N	\N	f	\N	\N	\N	\N	f	f	0	2026-02-09 14:38:26.05427	2026-02-09 14:38:26.05427
\.


--
-- Data for Name: ventas_detalle; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ventas_detalle (id, venta_id, producto_id, orden_servicio_id, descripcion, cantidad, precio_unitario, precio_lista, descuento_lista_porcentaje, descuento_lista_monto, descuento_adicional_porcentaje, descuento_adicional_monto, descuento_total_porcentaje, descuento_total_monto, tipo_iva, porcentaje_iva, subtotal, monto_iva, total, costo_unitario, costo_total, utilidad, margen_porcentaje, lote, numero_serie, fecha_vencimiento, es_servicio, es_repuesto_orden, observaciones, linea_orden) FROM stdin;
18	23	1	\N	Samsung Galaxy S21 Ultra	1.000	3395000.00	3395000.00	0.00	0.00	3.00	101850.00	3.00	101850.00	GRAVADO_10	10.00	3293150.00	299377.27	3293150.00	2500000.00	2500000.00	793150.00	\N	\N	\N	\N	f	f	\N	0
19	24	1	\N	Samsung Galaxy S21 Ultra	10.000	3500000.00	3500000.00	0.00	0.00	0.00	0.00	0.00	0.00	GRAVADO_10	10.00	35000000.00	3181818.18	35000000.00	2500000.00	25000000.00	10000000.00	\N	\N	\N	\N	f	f	\N	0
20	25	1	\N	Samsung Galaxy S21 Ultra	1.000	3500000.00	3500000.00	0.00	0.00	0.00	0.00	0.00	0.00	GRAVADO_10	10.00	3500000.00	318181.82	3500000.00	2500000.00	2500000.00	1000000.00	\N	\N	\N	\N	f	f	\N	0
21	26	1	\N	Samsung Galaxy S21 Ultra	1.000	3395000.00	3395000.00	0.00	0.00	3.00	101850.00	3.00	101850.00	GRAVADO_10	10.00	3293150.00	299377.27	3293150.00	2500000.00	2500000.00	793150.00	\N	\N	\N	\N	f	f	\N	0
22	27	1	\N	Samsung Galaxy S21 Ultra	1.000	3500000.00	3500000.00	0.00	0.00	0.00	0.00	0.00	0.00	GRAVADO_10	10.00	3500000.00	318181.82	3500000.00	2500000.00	2500000.00	1000000.00	\N	\N	\N	\N	f	f	\N	0
23	28	1	\N	Samsung Galaxy S21 Ultra	1.000	3500000.00	\N	0.00	0.00	0.00	0.00	0.00	0.00	GRAVADO_10	10.00	3500000.00	318181.82	3500000.00	\N	\N	\N	\N	\N	\N	\N	f	f	\N	0
24	29	5	\N	Samsung Gala	1.000	10000.00	\N	0.00	0.00	0.00	0.00	0.00	0.00	GRAVADO_10	10.00	10000.00	909.09	10000.00	\N	\N	\N	\N	\N	\N	\N	f	f	\N	0
25	30	5	\N	Samsung Gala	16.000	10000.00	\N	0.00	0.00	0.00	0.00	0.00	0.00	GRAVADO_10	10.00	160000.00	14545.45	160000.00	\N	\N	\N	\N	\N	\N	\N	f	f	\N	0
26	31	5	\N	Samsung Gala	1.000	10000.00	\N	0.00	0.00	0.00	0.00	0.00	0.00	GRAVADO_10	10.00	10000.00	909.09	10000.00	\N	\N	\N	\N	\N	\N	\N	f	f	\N	0
30	35	5	\N	Samsung Gala (DEVUELTO)	1.000	10000.00	\N	0.00	0.00	0.00	0.00	0.00	0.00	GRAVADO_10	10.00	10000.00	909.09	-10000.00	\N	\N	\N	\N	\N	\N	\N	f	f	\N	0
31	36	5	\N	Samsung Gala	2.000	10000.00	\N	0.00	0.00	0.00	0.00	0.00	0.00	GRAVADO_10	10.00	20000.00	1818.18	20000.00	\N	\N	\N	\N	\N	\N	\N	f	f	\N	0
32	37	5	\N	Samsung Gala	1.000	1000000.00	\N	0.00	0.00	0.00	0.00	0.00	0.00	GRAVADO_10	10.00	1000000.00	90909.09	1000000.00	\N	\N	\N	\N	\N	\N	\N	f	f	\N	0
33	44	4	\N	iPhone 13 Pro Max	1.000	400000.00	\N	0.00	0.00	0.00	0.00	0.00	0.00	GRAVADO_10	10.00	400000.00	36363.64	400000.00	\N	\N	\N	\N	\N	\N	\N	f	f	\N	0
34	45	4	\N	iPhone 13 Pro Max	1.000	400000.00	\N	0.00	0.00	0.00	0.00	0.00	0.00	GRAVADO_10	10.00	400000.00	36363.64	400000.00	\N	\N	\N	\N	\N	\N	\N	f	f	\N	0
35	46	4	\N	iPhone 13 Pro Max	1.000	400000.00	\N	0.00	0.00	0.00	0.00	0.00	0.00	GRAVADO_10	10.00	400000.00	36363.64	400000.00	\N	\N	\N	\N	\N	\N	\N	f	f	\N	0
36	47	4	\N	iPhone 13 Pro Max	1.000	400000.00	\N	0.00	0.00	0.00	0.00	0.00	0.00	GRAVADO_10	10.00	400000.00	36363.64	400000.00	\N	\N	\N	\N	\N	\N	\N	f	f	\N	0
37	48	4	\N	iPhone 13 Pro Max	1.000	400000.00	\N	0.00	0.00	0.00	0.00	0.00	0.00	GRAVADO_10	10.00	400000.00	36363.64	400000.00	\N	\N	\N	\N	\N	\N	\N	f	f	\N	0
38	49	5	\N	Samsung Gala	1.000	1000000.00	\N	0.00	0.00	0.00	0.00	0.00	0.00	GRAVADO_10	10.00	1000000.00	90909.09	1000000.00	\N	\N	\N	\N	\N	\N	\N	f	f	\N	0
39	50	5	\N	Samsung Gala	1.000	1000000.00	\N	0.00	0.00	0.00	0.00	0.00	0.00	GRAVADO_10	10.00	1000000.00	90909.09	1000000.00	\N	\N	\N	\N	\N	\N	\N	f	f	\N	0
40	51	5	\N	Samsung Gala	1.000	1000000.00	\N	0.00	0.00	0.00	0.00	0.00	0.00	GRAVADO_10	10.00	1000000.00	90909.09	1000000.00	\N	\N	\N	\N	\N	\N	\N	f	f	\N	0
41	52	4	\N	iPhone 13 Pro Max	1.000	400000.00	\N	0.00	0.00	0.00	0.00	0.00	0.00	GRAVADO_10	10.00	400000.00	36363.64	400000.00	\N	\N	\N	\N	\N	\N	\N	f	f	\N	0
42	53	4	\N	iPhone 13 Pro Max	1.000	400000.00	\N	0.00	0.00	0.00	0.00	0.00	0.00	GRAVADO_10	10.00	400000.00	36363.64	400000.00	\N	\N	\N	\N	\N	\N	\N	f	f	\N	0
43	54	4	\N	iPhone 13 Pro Max	1.000	400000.00	\N	0.00	0.00	0.00	0.00	0.00	0.00	GRAVADO_10	10.00	400000.00	36363.64	400000.00	\N	\N	\N	\N	\N	\N	\N	f	f	\N	0
44	55	5	\N	Samsung Gala	1.000	1000000.00	\N	0.00	0.00	0.00	0.00	0.00	0.00	GRAVADO_10	10.00	1000000.00	90909.09	1000000.00	\N	\N	\N	\N	\N	\N	\N	f	f	\N	0
45	56	4	\N	iPhone 13 Pro Max	1.000	400000.00	\N	0.00	0.00	0.00	0.00	0.00	0.00	GRAVADO_10	10.00	400000.00	36363.64	400000.00	\N	\N	\N	\N	\N	\N	\N	f	f	\N	0
46	57	4	\N	iPhone 13 Pro Max	1.000	400000.00	\N	0.00	0.00	0.00	0.00	0.00	0.00	GRAVADO_10	10.00	400000.00	36363.64	400000.00	\N	\N	\N	\N	\N	\N	\N	f	f	\N	0
47	58	5	\N	Samsung Gala (DEVUELTO)	2.000	10000.00	\N	0.00	0.00	0.00	0.00	0.00	0.00	GRAVADO_10	10.00	20000.00	1818.18	-20000.00	\N	\N	\N	\N	\N	\N	\N	f	f	\N	0
48	59	4	\N	iPhone 13 Pro Max	3.000	400000.00	\N	0.00	0.00	0.00	0.00	0.00	0.00	GRAVADO_10	10.00	1200000.00	109090.91	1200000.00	\N	\N	\N	\N	\N	\N	\N	f	f	\N	0
49	60	4	\N	iPhone 13 Pro Max	1.000	400000.00	\N	0.00	0.00	0.00	0.00	0.00	0.00	GRAVADO_10	10.00	400000.00	36363.64	400000.00	\N	\N	\N	\N	\N	\N	\N	f	f	\N	0
\.


--
-- Name: ajustes_inventario_detalle_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.ajustes_inventario_detalle_id_seq', 4, true);


--
-- Name: ajustes_inventario_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.ajustes_inventario_id_seq', 4, true);


--
-- Name: arqueos_caja_detalle_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.arqueos_caja_detalle_id_seq', 16, true);


--
-- Name: arqueos_caja_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.arqueos_caja_id_seq', 4, true);


--
-- Name: auditoria_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.auditoria_id_seq', 528, true);


--
-- Name: cajas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cajas_id_seq', 3, true);


--
-- Name: categorias_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.categorias_id_seq', 6, true);


--
-- Name: chat_participantes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.chat_participantes_id_seq', 1, false);


--
-- Name: chats_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.chats_id_seq', 1, false);


--
-- Name: clientes_contactos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.clientes_contactos_id_seq', 1, false);


--
-- Name: clientes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.clientes_id_seq', 3, true);


--
-- Name: clientes_imagenes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.clientes_imagenes_id_seq', 1, false);


--
-- Name: compras_detalle_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.compras_detalle_id_seq', 1, true);


--
-- Name: compras_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.compras_id_seq', 1, true);


--
-- Name: configuraciones_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.configuraciones_id_seq', 1, false);


--
-- Name: cotizaciones_detalle_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cotizaciones_detalle_id_seq', 1, false);


--
-- Name: cotizaciones_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cotizaciones_id_seq', 1, false);


--
-- Name: crm_chats_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.crm_chats_id_seq', 1, false);


--
-- Name: crm_mensajes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.crm_mensajes_id_seq', 1, false);


--
-- Name: crm_plantillas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.crm_plantillas_id_seq', 1, false);


--
-- Name: cuentas_cobrar_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cuentas_cobrar_id_seq', 3, true);


--
-- Name: cuentas_pagar_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cuentas_pagar_id_seq', 1, true);


--
-- Name: descuentos_especiales_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.descuentos_especiales_id_seq', 1, false);


--
-- Name: empresa_modulos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.empresa_modulos_id_seq', 14, true);


--
-- Name: empresas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.empresas_id_seq', 3, true);


--
-- Name: estados_orden_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.estados_orden_id_seq', 14, true);


--
-- Name: facturacion_electronica_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.facturacion_electronica_id_seq', 1, false);


--
-- Name: listas_precios_detalle_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.listas_precios_detalle_id_seq', 2, true);


--
-- Name: listas_precios_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.listas_precios_id_seq', 1, true);


--
-- Name: logs_sistema_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.logs_sistema_id_seq', 1, false);


--
-- Name: marcas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.marcas_id_seq', 6, true);


--
-- Name: mensajes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.mensajes_id_seq', 1, false);


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 4, true);


--
-- Name: modulos_dependencias_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.modulos_dependencias_id_seq', 19, true);


--
-- Name: modulos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.modulos_id_seq', 28, true);


--
-- Name: movimientos_caja_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.movimientos_caja_id_seq', 14, true);


--
-- Name: movimientos_stock_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.movimientos_stock_id_seq', 8, true);


--
-- Name: notificaciones_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.notificaciones_id_seq', 2, true);


--
-- Name: ordenes_servicio_comentarios_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.ordenes_servicio_comentarios_id_seq', 1, false);


--
-- Name: ordenes_servicio_documentos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.ordenes_servicio_documentos_id_seq', 1, false);


--
-- Name: ordenes_servicio_historial_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.ordenes_servicio_historial_id_seq', 8, true);


--
-- Name: ordenes_servicio_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.ordenes_servicio_id_seq', 2, true);


--
-- Name: ordenes_servicio_imagenes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.ordenes_servicio_imagenes_id_seq', 4, true);


--
-- Name: ordenes_servicio_mano_obra_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.ordenes_servicio_mano_obra_id_seq', 14, true);


--
-- Name: ordenes_servicio_notificaciones_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.ordenes_servicio_notificaciones_id_seq', 1, false);


--
-- Name: ordenes_servicio_repuestos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.ordenes_servicio_repuestos_id_seq', 1, false);


--
-- Name: pagos_cuentas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.pagos_cuentas_id_seq', 1, true);


--
-- Name: pagos_ventas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.pagos_ventas_id_seq', 36, true);


--
-- Name: permisos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.permisos_id_seq', 24, true);


--
-- Name: producto_imagenes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.producto_imagenes_id_seq', 1, false);


--
-- Name: productos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.productos_id_seq', 20, true);


--
-- Name: proveedores_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.proveedores_id_seq', 2, true);


--
-- Name: roles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.roles_id_seq', 6, true);


--
-- Name: stock_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.stock_id_seq', 14, true);


--
-- Name: sucursal_modulos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.sucursal_modulos_id_seq', 19, true);


--
-- Name: sucursales_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.sucursales_id_seq', 4, true);


--
-- Name: talonarios_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.talonarios_id_seq', 1, true);


--
-- Name: tipos_cambio_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.tipos_cambio_id_seq', 1, false);


--
-- Name: transferencias_stock_detalle_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.transferencias_stock_detalle_id_seq', 2, true);


--
-- Name: transferencias_stock_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.transferencias_stock_id_seq', 2, true);


--
-- Name: unidades_medida_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.unidades_medida_id_seq', 10, true);


--
-- Name: usuario_sucursales_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.usuario_sucursales_id_seq', 1, false);


--
-- Name: usuarios_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.usuarios_id_seq', 6, true);


--
-- Name: ventas_detalle_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.ventas_detalle_id_seq', 49, true);


--
-- Name: ventas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.ventas_id_seq', 60, true);


--
-- Name: ajustes_inventario_detalle ajustes_inventario_detalle_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ajustes_inventario_detalle
    ADD CONSTRAINT ajustes_inventario_detalle_pkey PRIMARY KEY (id);


--
-- Name: ajustes_inventario ajustes_inventario_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ajustes_inventario
    ADD CONSTRAINT ajustes_inventario_pkey PRIMARY KEY (id);


--
-- Name: arqueos_caja_detalle arqueos_caja_detalle_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.arqueos_caja_detalle
    ADD CONSTRAINT arqueos_caja_detalle_pkey PRIMARY KEY (id);


--
-- Name: arqueos_caja arqueos_caja_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.arqueos_caja
    ADD CONSTRAINT arqueos_caja_pkey PRIMARY KEY (id);


--
-- Name: auditoria auditoria_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.auditoria
    ADD CONSTRAINT auditoria_pkey PRIMARY KEY (id);


--
-- Name: cajas cajas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cajas
    ADD CONSTRAINT cajas_pkey PRIMARY KEY (id);


--
-- Name: categorias categorias_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias
    ADD CONSTRAINT categorias_pkey PRIMARY KEY (id);


--
-- Name: chat_participantes chat_participantes_chat_id_usuario_id; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chat_participantes
    ADD CONSTRAINT chat_participantes_chat_id_usuario_id UNIQUE (chat_id, usuario_id);


--
-- Name: clientes_contactos clientes_contactos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clientes_contactos
    ADD CONSTRAINT clientes_contactos_pkey PRIMARY KEY (id);


--
-- Name: clientes_imagenes clientes_imagenes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clientes_imagenes
    ADD CONSTRAINT clientes_imagenes_pkey PRIMARY KEY (id);


--
-- Name: clientes clientes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clientes
    ADD CONSTRAINT clientes_pkey PRIMARY KEY (id);


--
-- Name: compras_detalle compras_detalle_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.compras_detalle
    ADD CONSTRAINT compras_detalle_pkey PRIMARY KEY (id);


--
-- Name: compras compras_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.compras
    ADD CONSTRAINT compras_pkey PRIMARY KEY (id);


--
-- Name: configuraciones configuraciones_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configuraciones
    ADD CONSTRAINT configuraciones_pkey PRIMARY KEY (id);


--
-- Name: cotizaciones_detalle cotizaciones_detalle_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cotizaciones_detalle
    ADD CONSTRAINT cotizaciones_detalle_pkey PRIMARY KEY (id);


--
-- Name: cotizaciones cotizaciones_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cotizaciones
    ADD CONSTRAINT cotizaciones_pkey PRIMARY KEY (id);


--
-- Name: crm_chats crm_chats_empresa_id_telefono; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_chats
    ADD CONSTRAINT crm_chats_empresa_id_telefono UNIQUE (empresa_id, telefono);


--
-- Name: cuentas_cobrar cuentas_cobrar_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cuentas_cobrar
    ADD CONSTRAINT cuentas_cobrar_pkey PRIMARY KEY (id);


--
-- Name: cuentas_pagar cuentas_pagar_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cuentas_pagar
    ADD CONSTRAINT cuentas_pagar_pkey PRIMARY KEY (id);


--
-- Name: descuentos_especiales descuentos_especiales_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.descuentos_especiales
    ADD CONSTRAINT descuentos_especiales_pkey PRIMARY KEY (id);


--
-- Name: empresa_modulos empresa_modulos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.empresa_modulos
    ADD CONSTRAINT empresa_modulos_pkey PRIMARY KEY (id);


--
-- Name: empresas empresas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.empresas
    ADD CONSTRAINT empresas_pkey PRIMARY KEY (id);


--
-- Name: empresas empresas_ruc_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.empresas
    ADD CONSTRAINT empresas_ruc_key UNIQUE (ruc);


--
-- Name: estados_orden estados_orden_nombre_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.estados_orden
    ADD CONSTRAINT estados_orden_nombre_key UNIQUE (nombre);


--
-- Name: estados_orden estados_orden_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.estados_orden
    ADD CONSTRAINT estados_orden_pkey PRIMARY KEY (id);


--
-- Name: facturacion_electronica facturacion_electronica_cdc_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.facturacion_electronica
    ADD CONSTRAINT facturacion_electronica_cdc_key UNIQUE (cdc);


--
-- Name: facturacion_electronica facturacion_electronica_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.facturacion_electronica
    ADD CONSTRAINT facturacion_electronica_pkey PRIMARY KEY (id);


--
-- Name: listas_precios_detalle listas_precios_detalle_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.listas_precios_detalle
    ADD CONSTRAINT listas_precios_detalle_pkey PRIMARY KEY (id);


--
-- Name: listas_precios listas_precios_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.listas_precios
    ADD CONSTRAINT listas_precios_pkey PRIMARY KEY (id);


--
-- Name: logs_sistema logs_sistema_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.logs_sistema
    ADD CONSTRAINT logs_sistema_pkey PRIMARY KEY (id);


--
-- Name: marcas marcas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.marcas
    ADD CONSTRAINT marcas_pkey PRIMARY KEY (id);


--
-- Name: modulos modulos_codigo_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.modulos
    ADD CONSTRAINT modulos_codigo_key UNIQUE (codigo);


--
-- Name: modulos_dependencias modulos_dependencias_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.modulos_dependencias
    ADD CONSTRAINT modulos_dependencias_pkey PRIMARY KEY (id);


--
-- Name: modulos modulos_nombre_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.modulos
    ADD CONSTRAINT modulos_nombre_key UNIQUE (nombre);


--
-- Name: modulos modulos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.modulos
    ADD CONSTRAINT modulos_pkey PRIMARY KEY (id);


--
-- Name: movimientos_caja movimientos_caja_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimientos_caja
    ADD CONSTRAINT movimientos_caja_pkey PRIMARY KEY (id);


--
-- Name: movimientos_stock movimientos_stock_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimientos_stock
    ADD CONSTRAINT movimientos_stock_pkey PRIMARY KEY (id);


--
-- Name: notificaciones notificaciones_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notificaciones
    ADD CONSTRAINT notificaciones_pkey PRIMARY KEY (id);


--
-- Name: ordenes_servicio_comentarios ordenes_servicio_comentarios_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_servicio_comentarios
    ADD CONSTRAINT ordenes_servicio_comentarios_pkey PRIMARY KEY (id);


--
-- Name: ordenes_servicio_documentos ordenes_servicio_documentos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_servicio_documentos
    ADD CONSTRAINT ordenes_servicio_documentos_pkey PRIMARY KEY (id);


--
-- Name: ordenes_servicio_historial ordenes_servicio_historial_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_servicio_historial
    ADD CONSTRAINT ordenes_servicio_historial_pkey PRIMARY KEY (id);


--
-- Name: ordenes_servicio_imagenes ordenes_servicio_imagenes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_servicio_imagenes
    ADD CONSTRAINT ordenes_servicio_imagenes_pkey PRIMARY KEY (id);


--
-- Name: ordenes_servicio_mano_obra ordenes_servicio_mano_obra_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_servicio_mano_obra
    ADD CONSTRAINT ordenes_servicio_mano_obra_pkey PRIMARY KEY (id);


--
-- Name: ordenes_servicio_notificaciones ordenes_servicio_notificaciones_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_servicio_notificaciones
    ADD CONSTRAINT ordenes_servicio_notificaciones_pkey PRIMARY KEY (id);


--
-- Name: ordenes_servicio ordenes_servicio_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_servicio
    ADD CONSTRAINT ordenes_servicio_pkey PRIMARY KEY (id);


--
-- Name: ordenes_servicio_repuestos ordenes_servicio_repuestos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_servicio_repuestos
    ADD CONSTRAINT ordenes_servicio_repuestos_pkey PRIMARY KEY (id);


--
-- Name: pagos_cuentas pagos_cuentas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pagos_cuentas
    ADD CONSTRAINT pagos_cuentas_pkey PRIMARY KEY (id);


--
-- Name: pagos_ventas pagos_ventas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pagos_ventas
    ADD CONSTRAINT pagos_ventas_pkey PRIMARY KEY (id);


--
-- Name: permisos permisos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permisos
    ADD CONSTRAINT permisos_pkey PRIMARY KEY (id);


--
-- Name: chat_participantes pk_chat_participantes; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chat_participantes
    ADD CONSTRAINT pk_chat_participantes PRIMARY KEY (id);


--
-- Name: chats pk_chats; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chats
    ADD CONSTRAINT pk_chats PRIMARY KEY (id);


--
-- Name: crm_chats pk_crm_chats; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_chats
    ADD CONSTRAINT pk_crm_chats PRIMARY KEY (id);


--
-- Name: crm_mensajes pk_crm_mensajes; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_mensajes
    ADD CONSTRAINT pk_crm_mensajes PRIMARY KEY (id);


--
-- Name: crm_plantillas pk_crm_plantillas; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_plantillas
    ADD CONSTRAINT pk_crm_plantillas PRIMARY KEY (id);


--
-- Name: mensajes pk_mensajes; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mensajes
    ADD CONSTRAINT pk_mensajes PRIMARY KEY (id);


--
-- Name: migrations pk_migrations; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT pk_migrations PRIMARY KEY (id);


--
-- Name: producto_imagenes producto_imagenes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.producto_imagenes
    ADD CONSTRAINT producto_imagenes_pkey PRIMARY KEY (id);


--
-- Name: productos productos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.productos
    ADD CONSTRAINT productos_pkey PRIMARY KEY (id);


--
-- Name: proveedores proveedores_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proveedores
    ADD CONSTRAINT proveedores_pkey PRIMARY KEY (id);


--
-- Name: roles roles_nombre_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_nombre_key UNIQUE (nombre);


--
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (id);


--
-- Name: stock stock_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock
    ADD CONSTRAINT stock_pkey PRIMARY KEY (id);


--
-- Name: sucursal_modulos sucursal_modulos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sucursal_modulos
    ADD CONSTRAINT sucursal_modulos_pkey PRIMARY KEY (id);


--
-- Name: sucursales sucursales_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sucursales
    ADD CONSTRAINT sucursales_pkey PRIMARY KEY (id);


--
-- Name: talonarios talonarios_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.talonarios
    ADD CONSTRAINT talonarios_pkey PRIMARY KEY (id);


--
-- Name: tipos_cambio tipos_cambio_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tipos_cambio
    ADD CONSTRAINT tipos_cambio_pkey PRIMARY KEY (id);


--
-- Name: transferencias_stock_detalle transferencias_stock_detalle_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transferencias_stock_detalle
    ADD CONSTRAINT transferencias_stock_detalle_pkey PRIMARY KEY (id);


--
-- Name: transferencias_stock transferencias_stock_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transferencias_stock
    ADD CONSTRAINT transferencias_stock_pkey PRIMARY KEY (id);


--
-- Name: arqueos_caja uk_arqueo_caja_fecha; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.arqueos_caja
    ADD CONSTRAINT uk_arqueo_caja_fecha UNIQUE (caja_id, fecha_arqueo, hora_arqueo);


--
-- Name: cajas uk_caja_nombre; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cajas
    ADD CONSTRAINT uk_caja_nombre UNIQUE (empresa_id, sucursal_id, nombre);


--
-- Name: categorias uk_categoria_nombre; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias
    ADD CONSTRAINT uk_categoria_nombre UNIQUE (empresa_id, nombre);


--
-- Name: clientes uk_cliente_codigo; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clientes
    ADD CONSTRAINT uk_cliente_codigo UNIQUE (empresa_id, codigo);


--
-- Name: configuraciones uk_config_clave; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configuraciones
    ADD CONSTRAINT uk_config_clave UNIQUE (empresa_id, modulo, clave);


--
-- Name: empresa_modulos uk_empresa_modulo; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.empresa_modulos
    ADD CONSTRAINT uk_empresa_modulo UNIQUE (empresa_id, modulo_id);


--
-- Name: listas_precios uk_lista_nombre; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.listas_precios
    ADD CONSTRAINT uk_lista_nombre UNIQUE (empresa_id, nombre);


--
-- Name: listas_precios_detalle uk_lista_producto; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.listas_precios_detalle
    ADD CONSTRAINT uk_lista_producto UNIQUE (lista_id, producto_id);


--
-- Name: marcas uk_marca_nombre; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.marcas
    ADD CONSTRAINT uk_marca_nombre UNIQUE (empresa_id, nombre);


--
-- Name: modulos_dependencias uk_modulo_dependencia; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.modulos_dependencias
    ADD CONSTRAINT uk_modulo_dependencia UNIQUE (modulo_id, modulo_requerido_id);


--
-- Name: ajustes_inventario uk_numero_ajuste; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ajustes_inventario
    ADD CONSTRAINT uk_numero_ajuste UNIQUE (empresa_id, numero_ajuste);


--
-- Name: compras uk_numero_compra; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.compras
    ADD CONSTRAINT uk_numero_compra UNIQUE (empresa_id, numero_compra);


--
-- Name: cotizaciones uk_numero_cotizacion; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cotizaciones
    ADD CONSTRAINT uk_numero_cotizacion UNIQUE (empresa_id, numero_cotizacion);


--
-- Name: ordenes_servicio uk_numero_orden; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_servicio
    ADD CONSTRAINT uk_numero_orden UNIQUE (empresa_id, numero_orden);


--
-- Name: transferencias_stock uk_numero_transferencia; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transferencias_stock
    ADD CONSTRAINT uk_numero_transferencia UNIQUE (empresa_id, numero_transferencia);


--
-- Name: ventas uk_numero_venta; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ventas
    ADD CONSTRAINT uk_numero_venta UNIQUE (empresa_id, numero_venta);


--
-- Name: productos uk_producto_codigo; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.productos
    ADD CONSTRAINT uk_producto_codigo UNIQUE (empresa_id, codigo);


--
-- Name: proveedores uk_proveedor_codigo; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proveedores
    ADD CONSTRAINT uk_proveedor_codigo UNIQUE (empresa_id, codigo);


--
-- Name: proveedores uk_proveedor_ruc; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proveedores
    ADD CONSTRAINT uk_proveedor_ruc UNIQUE (empresa_id, ruc);


--
-- Name: permisos uk_rol_modulo; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permisos
    ADD CONSTRAINT uk_rol_modulo UNIQUE (rol_id, modulo);


--
-- Name: stock uk_stock_producto_sucursal; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock
    ADD CONSTRAINT uk_stock_producto_sucursal UNIQUE (producto_id, sucursal_id, lote);


--
-- Name: sucursal_modulos uk_sucursal_modulo; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sucursal_modulos
    ADD CONSTRAINT uk_sucursal_modulo UNIQUE (sucursal_id, modulo_id);


--
-- Name: sucursales uk_sucursal_nombre; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sucursales
    ADD CONSTRAINT uk_sucursal_nombre UNIQUE (empresa_id, nombre);


--
-- Name: tipos_cambio uk_tipo_cambio_dia; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tipos_cambio
    ADD CONSTRAINT uk_tipo_cambio_dia UNIQUE (empresa_id, fecha, moneda_destino);


--
-- Name: usuario_sucursales uk_usuario_sucursal; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuario_sucursales
    ADD CONSTRAINT uk_usuario_sucursal UNIQUE (usuario_id, sucursal_id);


--
-- Name: unidades_medida unidades_medida_abreviatura_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.unidades_medida
    ADD CONSTRAINT unidades_medida_abreviatura_key UNIQUE (abreviatura);


--
-- Name: unidades_medida unidades_medida_nombre_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.unidades_medida
    ADD CONSTRAINT unidades_medida_nombre_key UNIQUE (nombre);


--
-- Name: unidades_medida unidades_medida_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.unidades_medida
    ADD CONSTRAINT unidades_medida_pkey PRIMARY KEY (id);


--
-- Name: usuario_sucursales usuario_sucursales_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuario_sucursales
    ADD CONSTRAINT usuario_sucursales_pkey PRIMARY KEY (id);


--
-- Name: usuarios usuarios_ci_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_ci_key UNIQUE (ci);


--
-- Name: usuarios usuarios_email_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_email_key UNIQUE (email);


--
-- Name: usuarios usuarios_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_pkey PRIMARY KEY (id);


--
-- Name: usuarios usuarios_username_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_username_key UNIQUE (username);


--
-- Name: ventas_detalle ventas_detalle_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ventas_detalle
    ADD CONSTRAINT ventas_detalle_pkey PRIMARY KEY (id);


--
-- Name: ventas ventas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ventas
    ADD CONSTRAINT ventas_pkey PRIMARY KEY (id);


--
-- Name: idx_ajustes_detalle_ajuste; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ajustes_detalle_ajuste ON public.ajustes_inventario_detalle USING btree (ajuste_id);


--
-- Name: idx_ajustes_detalle_producto; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ajustes_detalle_producto ON public.ajustes_inventario_detalle USING btree (producto_id);


--
-- Name: idx_ajustes_empresa; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ajustes_empresa ON public.ajustes_inventario USING btree (empresa_id);


--
-- Name: idx_ajustes_estado; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ajustes_estado ON public.ajustes_inventario USING btree (estado);


--
-- Name: idx_ajustes_sucursal; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ajustes_sucursal ON public.ajustes_inventario USING btree (sucursal_id);


--
-- Name: idx_arqueo_tipo_medio; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_arqueo_tipo_medio ON public.arqueos_caja_detalle USING btree (arqueo_id, tipo_medio_pago);


--
-- Name: idx_arqueos_caja; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_arqueos_caja ON public.arqueos_caja USING btree (caja_id);


--
-- Name: idx_arqueos_detalle_arqueo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_arqueos_detalle_arqueo ON public.arqueos_caja_detalle USING btree (arqueo_id);


--
-- Name: idx_arqueos_fecha; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_arqueos_fecha ON public.arqueos_caja USING btree (fecha_arqueo DESC);


--
-- Name: idx_auditoria_accion; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_auditoria_accion ON public.auditoria USING btree (accion);


--
-- Name: idx_auditoria_empresa; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_auditoria_empresa ON public.auditoria USING btree (empresa_id);


--
-- Name: idx_auditoria_fecha; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_auditoria_fecha ON public.auditoria USING btree (fecha_evento DESC);


--
-- Name: idx_auditoria_tabla; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_auditoria_tabla ON public.auditoria USING btree (tabla_afectada);


--
-- Name: idx_auditoria_usuario; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_auditoria_usuario ON public.auditoria USING btree (usuario_id);


--
-- Name: idx_cajas_empresa; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_cajas_empresa ON public.cajas USING btree (empresa_id);


--
-- Name: idx_cajas_estado; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_cajas_estado ON public.cajas USING btree (estado);


--
-- Name: idx_cajas_sucursal; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_cajas_sucursal ON public.cajas USING btree (sucursal_id);


--
-- Name: idx_cajas_usuario; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_cajas_usuario ON public.cajas USING btree (usuario_id);


--
-- Name: idx_categorias_empresa; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_categorias_empresa ON public.categorias USING btree (empresa_id);


--
-- Name: idx_categorias_padre; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_categorias_padre ON public.categorias USING btree (categoria_padre_id);


--
-- Name: idx_clientes_categoria; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_clientes_categoria ON public.clientes USING btree (categoria_cliente);


--
-- Name: idx_clientes_ci; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_clientes_ci ON public.clientes USING btree (ci);


--
-- Name: idx_clientes_contactos_cliente; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_clientes_contactos_cliente ON public.clientes_contactos USING btree (cliente_id);


--
-- Name: idx_clientes_empresa; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_clientes_empresa ON public.clientes USING btree (empresa_id);


--
-- Name: idx_clientes_etiquetas; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_clientes_etiquetas ON public.clientes USING gin (etiquetas);


--
-- Name: idx_clientes_imagenes_cliente; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_clientes_imagenes_cliente ON public.clientes_imagenes USING btree (cliente_id);


--
-- Name: idx_clientes_nombre; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_clientes_nombre ON public.clientes USING gin (to_tsvector('spanish'::regconfig, (((((((COALESCE(nombre, ''::character varying))::text || ' '::text) || (COALESCE(apellido, ''::character varying))::text) || ' '::text) || (COALESCE(razon_social, ''::character varying))::text) || ' '::text) || (COALESCE(nombre_fantasia, ''::character varying))::text)));


--
-- Name: idx_clientes_nombre_telefono; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_clientes_nombre_telefono ON public.clientes USING btree (nombre, telefono);


--
-- Name: idx_clientes_ruc; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_clientes_ruc ON public.clientes USING btree (ruc);


--
-- Name: idx_compras_detalle_compra; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_compras_detalle_compra ON public.compras_detalle USING btree (compra_id);


--
-- Name: idx_compras_detalle_compra_producto; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_compras_detalle_compra_producto ON public.compras_detalle USING btree (compra_id, producto_id);


--
-- Name: idx_compras_detalle_producto; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_compras_detalle_producto ON public.compras_detalle USING btree (producto_id);


--
-- Name: idx_compras_empresa; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_compras_empresa ON public.compras USING btree (empresa_id);


--
-- Name: idx_compras_estado; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_compras_estado ON public.compras USING btree (estado);


--
-- Name: idx_compras_fecha; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_compras_fecha ON public.compras USING btree (fecha_compra DESC);


--
-- Name: idx_compras_fecha_estado; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_compras_fecha_estado ON public.compras USING btree (fecha_compra, estado);


--
-- Name: idx_compras_proveedor; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_compras_proveedor ON public.compras USING btree (proveedor_id);


--
-- Name: idx_compras_sucursal; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_compras_sucursal ON public.compras USING btree (sucursal_id);


--
-- Name: idx_configuraciones_empresa; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_configuraciones_empresa ON public.configuraciones USING btree (empresa_id);


--
-- Name: idx_configuraciones_modulo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_configuraciones_modulo ON public.configuraciones USING btree (modulo);


--
-- Name: idx_cotizaciones_cliente; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_cotizaciones_cliente ON public.cotizaciones USING btree (cliente_id);


--
-- Name: idx_cotizaciones_detalle_cotizacion; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_cotizaciones_detalle_cotizacion ON public.cotizaciones_detalle USING btree (cotizacion_id);


--
-- Name: idx_cotizaciones_detalle_producto; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_cotizaciones_detalle_producto ON public.cotizaciones_detalle USING btree (producto_id);


--
-- Name: idx_cotizaciones_empresa; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_cotizaciones_empresa ON public.cotizaciones USING btree (empresa_id);


--
-- Name: idx_cotizaciones_estado; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_cotizaciones_estado ON public.cotizaciones USING btree (estado);


--
-- Name: idx_cotizaciones_fecha; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_cotizaciones_fecha ON public.cotizaciones USING btree (fecha_emision DESC);


--
-- Name: idx_cuentas_cobrar_cliente; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_cuentas_cobrar_cliente ON public.cuentas_cobrar USING btree (cliente_id);


--
-- Name: idx_cuentas_cobrar_cliente_estado; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_cuentas_cobrar_cliente_estado ON public.cuentas_cobrar USING btree (cliente_id, estado);


--
-- Name: idx_cuentas_cobrar_empresa; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_cuentas_cobrar_empresa ON public.cuentas_cobrar USING btree (empresa_id);


--
-- Name: idx_cuentas_cobrar_estado; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_cuentas_cobrar_estado ON public.cuentas_cobrar USING btree (estado);


--
-- Name: idx_cuentas_cobrar_vencimiento; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_cuentas_cobrar_vencimiento ON public.cuentas_cobrar USING btree (fecha_vencimiento);


--
-- Name: idx_cuentas_cobrar_vencimiento_estado; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_cuentas_cobrar_vencimiento_estado ON public.cuentas_cobrar USING btree (fecha_vencimiento, estado);


--
-- Name: idx_cuentas_cobrar_venta; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_cuentas_cobrar_venta ON public.cuentas_cobrar USING btree (venta_id);


--
-- Name: idx_cuentas_pagar_compra; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_cuentas_pagar_compra ON public.cuentas_pagar USING btree (compra_id);


--
-- Name: idx_cuentas_pagar_empresa; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_cuentas_pagar_empresa ON public.cuentas_pagar USING btree (empresa_id);


--
-- Name: idx_cuentas_pagar_estado; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_cuentas_pagar_estado ON public.cuentas_pagar USING btree (estado);


--
-- Name: idx_cuentas_pagar_proveedor; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_cuentas_pagar_proveedor ON public.cuentas_pagar USING btree (proveedor_id);


--
-- Name: idx_cuentas_pagar_vencimiento; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_cuentas_pagar_vencimiento ON public.cuentas_pagar USING btree (fecha_vencimiento);


--
-- Name: idx_descuentos_activo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_descuentos_activo ON public.descuentos_especiales USING btree (activo);


--
-- Name: idx_descuentos_empresa; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_descuentos_empresa ON public.descuentos_especiales USING btree (empresa_id);


--
-- Name: idx_descuentos_vigencia; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_descuentos_vigencia ON public.descuentos_especiales USING btree (fecha_inicio, fecha_fin);


--
-- Name: idx_empresa_modulos_activo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_empresa_modulos_activo ON public.empresa_modulos USING btree (activo);


--
-- Name: idx_empresa_modulos_empresa; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_empresa_modulos_empresa ON public.empresa_modulos USING btree (empresa_id);


--
-- Name: idx_empresa_modulos_empresa_activo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_empresa_modulos_empresa_activo ON public.empresa_modulos USING btree (empresa_id, activo) WHERE (activo = true);


--
-- Name: idx_empresa_modulos_modulo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_empresa_modulos_modulo ON public.empresa_modulos USING btree (modulo_id);


--
-- Name: idx_empresa_modulos_vencimiento; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_empresa_modulos_vencimiento ON public.empresa_modulos USING btree (fecha_vencimiento);


--
-- Name: idx_empresa_modulos_vencimiento_proximo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_empresa_modulos_vencimiento_proximo ON public.empresa_modulos USING btree (fecha_vencimiento) WHERE ((fecha_vencimiento IS NOT NULL) AND (activo = true));


--
-- Name: idx_empresas_estado; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_empresas_estado ON public.empresas USING btree (estado);


--
-- Name: idx_empresas_ruc; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_empresas_ruc ON public.empresas USING btree (ruc);


--
-- Name: idx_empresas_tema_oscuro; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_empresas_tema_oscuro ON public.empresas USING btree (tema_oscuro);


--
-- Name: idx_factura_electronica_cdc; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_factura_electronica_cdc ON public.facturacion_electronica USING btree (cdc);


--
-- Name: idx_factura_electronica_estado; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_factura_electronica_estado ON public.facturacion_electronica USING btree (estado_sifen);


--
-- Name: idx_factura_electronica_fecha; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_factura_electronica_fecha ON public.facturacion_electronica USING btree (fecha_emision DESC);


--
-- Name: idx_factura_electronica_venta; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_factura_electronica_venta ON public.facturacion_electronica USING btree (venta_id);


--
-- Name: idx_listas_detalle_lista; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_listas_detalle_lista ON public.listas_precios_detalle USING btree (lista_id);


--
-- Name: idx_listas_detalle_oferta; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_listas_detalle_oferta ON public.listas_precios_detalle USING btree (es_oferta, fecha_fin_oferta);


--
-- Name: idx_listas_detalle_producto; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_listas_detalle_producto ON public.listas_precios_detalle USING btree (producto_id);


--
-- Name: idx_listas_precios_empresa; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_listas_precios_empresa ON public.listas_precios USING btree (empresa_id);


--
-- Name: idx_listas_precios_vigencia; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_listas_precios_vigencia ON public.listas_precios USING btree (fecha_vigencia_desde, fecha_vigencia_hasta);


--
-- Name: idx_logs_empresa; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_logs_empresa ON public.logs_sistema USING btree (empresa_id);


--
-- Name: idx_logs_fecha; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_logs_fecha ON public.logs_sistema USING btree (fecha_creacion DESC);


--
-- Name: idx_logs_nivel; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_logs_nivel ON public.logs_sistema USING btree (nivel);


--
-- Name: idx_logs_usuario; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_logs_usuario ON public.logs_sistema USING btree (usuario_id);


--
-- Name: idx_marcas_empresa; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_marcas_empresa ON public.marcas USING btree (empresa_id);


--
-- Name: idx_modulos_categoria; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_modulos_categoria ON public.modulos USING btree (categoria);


--
-- Name: idx_modulos_codigo_activo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_modulos_codigo_activo ON public.modulos USING btree (codigo) WHERE (estado = true);


--
-- Name: idx_modulos_dependencias_modulo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_modulos_dependencias_modulo ON public.modulos_dependencias USING btree (modulo_id);


--
-- Name: idx_modulos_dependencias_requerido; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_modulos_dependencias_requerido ON public.modulos_dependencias USING btree (modulo_requerido_id);


--
-- Name: idx_modulos_estado; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_modulos_estado ON public.modulos USING btree (estado);


--
-- Name: idx_movimientos_caja; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_movimientos_caja ON public.movimientos_caja USING btree (caja_id);


--
-- Name: idx_movimientos_empresa; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_movimientos_empresa ON public.movimientos_stock USING btree (empresa_id);


--
-- Name: idx_movimientos_fecha; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_movimientos_fecha ON public.movimientos_stock USING btree (fecha_movimiento DESC);


--
-- Name: idx_movimientos_producto; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_movimientos_producto ON public.movimientos_stock USING btree (producto_id);


--
-- Name: idx_movimientos_producto_fecha; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_movimientos_producto_fecha ON public.movimientos_stock USING btree (producto_id, fecha_movimiento DESC);


--
-- Name: idx_movimientos_sucursal; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_movimientos_sucursal ON public.movimientos_stock USING btree (sucursal_id);


--
-- Name: idx_movimientos_tipo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_movimientos_tipo ON public.movimientos_stock USING btree (tipo_movimiento);


--
-- Name: idx_movimientos_tipo_fecha; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_movimientos_tipo_fecha ON public.movimientos_stock USING btree (tipo_movimiento, fecha_movimiento DESC);


--
-- Name: idx_notificaciones_empresa; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_notificaciones_empresa ON public.notificaciones USING btree (empresa_id);


--
-- Name: idx_notificaciones_fecha; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_notificaciones_fecha ON public.notificaciones USING btree (fecha_creacion DESC);


--
-- Name: idx_notificaciones_leida; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_notificaciones_leida ON public.notificaciones USING btree (leida);


--
-- Name: idx_notificaciones_usuario; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_notificaciones_usuario ON public.notificaciones USING btree (usuario_id);


--
-- Name: idx_ordenes_cliente; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ordenes_cliente ON public.ordenes_servicio USING btree (cliente_id);


--
-- Name: idx_ordenes_comentarios_fecha; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ordenes_comentarios_fecha ON public.ordenes_servicio_comentarios USING btree (fecha_creacion DESC);


--
-- Name: idx_ordenes_comentarios_orden; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ordenes_comentarios_orden ON public.ordenes_servicio_comentarios USING btree (orden_id);


--
-- Name: idx_ordenes_documentos_orden; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ordenes_documentos_orden ON public.ordenes_servicio_documentos USING btree (orden_id);


--
-- Name: idx_ordenes_empresa; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ordenes_empresa ON public.ordenes_servicio USING btree (empresa_id);


--
-- Name: idx_ordenes_estado; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ordenes_estado ON public.ordenes_servicio USING btree (estado_id);


--
-- Name: idx_ordenes_fecha_limite; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ordenes_fecha_limite ON public.ordenes_servicio USING btree (fecha_limite_retiro);


--
-- Name: idx_ordenes_fecha_recepcion; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ordenes_fecha_recepcion ON public.ordenes_servicio USING btree (fecha_recepcion DESC);


--
-- Name: idx_ordenes_historial_fecha; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ordenes_historial_fecha ON public.ordenes_servicio_historial USING btree (fecha_cambio DESC);


--
-- Name: idx_ordenes_historial_orden; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ordenes_historial_orden ON public.ordenes_servicio_historial USING btree (orden_id);


--
-- Name: idx_ordenes_imagenes_orden; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ordenes_imagenes_orden ON public.ordenes_servicio_imagenes USING btree (orden_id);


--
-- Name: idx_ordenes_imagenes_tipo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ordenes_imagenes_tipo ON public.ordenes_servicio_imagenes USING btree (tipo);


--
-- Name: idx_ordenes_imagenes_visible; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ordenes_imagenes_visible ON public.ordenes_servicio_imagenes USING btree (visible_cliente);


--
-- Name: idx_ordenes_mano_obra_orden; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ordenes_mano_obra_orden ON public.ordenes_servicio_mano_obra USING btree (orden_id);


--
-- Name: idx_ordenes_mano_obra_tecnico; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ordenes_mano_obra_tecnico ON public.ordenes_servicio_mano_obra USING btree (tecnico_id);


--
-- Name: idx_ordenes_notificaciones_estado; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ordenes_notificaciones_estado ON public.ordenes_servicio_notificaciones USING btree (estado);


--
-- Name: idx_ordenes_notificaciones_orden; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ordenes_notificaciones_orden ON public.ordenes_servicio_notificaciones USING btree (orden_id);


--
-- Name: idx_ordenes_numero; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ordenes_numero ON public.ordenes_servicio USING btree (numero_orden);


--
-- Name: idx_ordenes_prioridad; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ordenes_prioridad ON public.ordenes_servicio USING btree (prioridad);


--
-- Name: idx_ordenes_repuestos_orden; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ordenes_repuestos_orden ON public.ordenes_servicio_repuestos USING btree (orden_id);


--
-- Name: idx_ordenes_repuestos_presupuesto; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ordenes_repuestos_presupuesto ON public.ordenes_servicio_repuestos USING btree (es_presupuesto);


--
-- Name: idx_ordenes_repuestos_producto; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ordenes_repuestos_producto ON public.ordenes_servicio_repuestos USING btree (producto_id);


--
-- Name: idx_ordenes_servicio_cliente_fecha; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ordenes_servicio_cliente_fecha ON public.ordenes_servicio USING btree (cliente_id, fecha_recepcion DESC);


--
-- Name: idx_ordenes_servicio_estado_fecha; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ordenes_servicio_estado_fecha ON public.ordenes_servicio USING btree (estado_id, fecha_recepcion DESC);


--
-- Name: idx_ordenes_sucursal; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ordenes_sucursal ON public.ordenes_servicio USING btree (sucursal_id);


--
-- Name: idx_ordenes_tecnico; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ordenes_tecnico ON public.ordenes_servicio USING btree (tecnico_asignado);


--
-- Name: idx_pagos_cuentas_cobrar; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_pagos_cuentas_cobrar ON public.pagos_cuentas USING btree (cuenta_cobrar_id);


--
-- Name: idx_pagos_cuentas_empresa; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_pagos_cuentas_empresa ON public.pagos_cuentas USING btree (empresa_id);


--
-- Name: idx_pagos_cuentas_fecha; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_pagos_cuentas_fecha ON public.pagos_cuentas USING btree (fecha_pago DESC);


--
-- Name: idx_pagos_cuentas_pagar; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_pagos_cuentas_pagar ON public.pagos_cuentas USING btree (cuenta_pagar_id);


--
-- Name: idx_pagos_ventas_fecha; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_pagos_ventas_fecha ON public.pagos_ventas USING btree (fecha_pago DESC);


--
-- Name: idx_pagos_ventas_forma; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_pagos_ventas_forma ON public.pagos_ventas USING btree (forma_pago);


--
-- Name: idx_pagos_ventas_venta; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_pagos_ventas_venta ON public.pagos_ventas USING btree (venta_id);


--
-- Name: idx_permisos_rol; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_permisos_rol ON public.permisos USING btree (rol_id);


--
-- Name: idx_producto_imagenes_principal; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_producto_imagenes_principal ON public.producto_imagenes USING btree (producto_id, es_principal);


--
-- Name: idx_producto_imagenes_producto; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_producto_imagenes_producto ON public.producto_imagenes USING btree (producto_id);


--
-- Name: idx_productos_categoria; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_productos_categoria ON public.productos USING btree (categoria_id);


--
-- Name: idx_productos_codigo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_productos_codigo ON public.productos USING btree (codigo);


--
-- Name: idx_productos_codigo_barra; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_productos_codigo_barra ON public.productos USING btree (codigo_barra);


--
-- Name: idx_productos_codigo_nombre; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_productos_codigo_nombre ON public.productos USING btree (codigo, nombre);


--
-- Name: idx_productos_empresa; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_productos_empresa ON public.productos USING btree (empresa_id);


--
-- Name: idx_productos_marca; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_productos_marca ON public.productos USING btree (marca_id);


--
-- Name: idx_productos_nombre; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_productos_nombre ON public.productos USING gin (to_tsvector('spanish'::regconfig, (nombre)::text));


--
-- Name: idx_productos_tags; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_productos_tags ON public.productos USING gin (tags);


--
-- Name: idx_proveedores_empresa; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_proveedores_empresa ON public.proveedores USING btree (empresa_id);


--
-- Name: idx_proveedores_ruc; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_proveedores_ruc ON public.proveedores USING btree (ruc);


--
-- Name: idx_stock_disponible; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_stock_disponible ON public.stock USING btree (cantidad_disponible);


--
-- Name: idx_stock_producto; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_stock_producto ON public.stock USING btree (producto_id);


--
-- Name: idx_stock_sucursal; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_stock_sucursal ON public.stock USING btree (sucursal_id);


--
-- Name: idx_stock_sucursal_producto; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_stock_sucursal_producto ON public.stock USING btree (sucursal_id, producto_id);


--
-- Name: idx_stock_vencimiento; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_stock_vencimiento ON public.stock USING btree (fecha_vencimiento);


--
-- Name: idx_sucursal_modulos_activo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_sucursal_modulos_activo ON public.sucursal_modulos USING btree (activo);


--
-- Name: idx_sucursal_modulos_empresa_modulo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_sucursal_modulos_empresa_modulo ON public.sucursal_modulos USING btree (empresa_modulo_id);


--
-- Name: idx_sucursal_modulos_modulo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_sucursal_modulos_modulo ON public.sucursal_modulos USING btree (modulo_id);


--
-- Name: idx_sucursal_modulos_sucursal; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_sucursal_modulos_sucursal ON public.sucursal_modulos USING btree (sucursal_id);


--
-- Name: idx_sucursal_modulos_sucursal_activo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_sucursal_modulos_sucursal_activo ON public.sucursal_modulos USING btree (sucursal_id, activo) WHERE (activo = true);


--
-- Name: idx_sucursales_empresa; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_sucursales_empresa ON public.sucursales USING btree (empresa_id);


--
-- Name: idx_sucursales_estado; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_sucursales_estado ON public.sucursales USING btree (estado);


--
-- Name: idx_sucursales_tema_oscuro; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_sucursales_tema_oscuro ON public.sucursales USING btree (tema_oscuro);


--
-- Name: idx_talonarios_empresa; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_talonarios_empresa ON public.talonarios USING btree (empresa_id);


--
-- Name: idx_talonarios_estado; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_talonarios_estado ON public.talonarios USING btree (estado);


--
-- Name: idx_talonarios_sucursal; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_talonarios_sucursal ON public.talonarios USING btree (sucursal_id);


--
-- Name: idx_talonarios_vigencia; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_talonarios_vigencia ON public.talonarios USING btree (fecha_fin_vigencia);


--
-- Name: idx_tipos_cambio_empresa; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_tipos_cambio_empresa ON public.tipos_cambio USING btree (empresa_id);


--
-- Name: idx_tipos_cambio_fecha; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_tipos_cambio_fecha ON public.tipos_cambio USING btree (fecha DESC);


--
-- Name: idx_transferencias_destino; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_transferencias_destino ON public.transferencias_stock USING btree (sucursal_destino_id);


--
-- Name: idx_transferencias_detalle_producto; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_transferencias_detalle_producto ON public.transferencias_stock_detalle USING btree (producto_id);


--
-- Name: idx_transferencias_detalle_transferencia; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_transferencias_detalle_transferencia ON public.transferencias_stock_detalle USING btree (transferencia_id);


--
-- Name: idx_transferencias_empresa; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_transferencias_empresa ON public.transferencias_stock USING btree (empresa_id);


--
-- Name: idx_transferencias_estado; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_transferencias_estado ON public.transferencias_stock USING btree (estado);


--
-- Name: idx_transferencias_origen; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_transferencias_origen ON public.transferencias_stock USING btree (sucursal_origen_id);


--
-- Name: idx_usuario_sucursales_sucursal; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_usuario_sucursales_sucursal ON public.usuario_sucursales USING btree (sucursal_id);


--
-- Name: idx_usuario_sucursales_usuario; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_usuario_sucursales_usuario ON public.usuario_sucursales USING btree (usuario_id);


--
-- Name: idx_usuarios_empresa; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_usuarios_empresa ON public.usuarios USING btree (empresa_id);


--
-- Name: idx_usuarios_rol; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_usuarios_rol ON public.usuarios USING btree (rol_id);


--
-- Name: idx_usuarios_sucursal; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_usuarios_sucursal ON public.usuarios USING btree (sucursal_id);


--
-- Name: idx_usuarios_tema_oscuro; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_usuarios_tema_oscuro ON public.usuarios USING btree (tema_oscuro);


--
-- Name: idx_usuarios_username; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_usuarios_username ON public.usuarios USING btree (username);


--
-- Name: idx_ventas_cliente; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ventas_cliente ON public.ventas USING btree (cliente_id);


--
-- Name: idx_ventas_cliente_fecha; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ventas_cliente_fecha ON public.ventas USING btree (cliente_id, fecha_venta DESC);


--
-- Name: idx_ventas_detalle_orden; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ventas_detalle_orden ON public.ventas_detalle USING btree (orden_servicio_id);


--
-- Name: idx_ventas_detalle_producto; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ventas_detalle_producto ON public.ventas_detalle USING btree (producto_id);


--
-- Name: idx_ventas_detalle_venta; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ventas_detalle_venta ON public.ventas_detalle USING btree (venta_id);


--
-- Name: idx_ventas_detalle_venta_producto; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ventas_detalle_venta_producto ON public.ventas_detalle USING btree (venta_id, producto_id);


--
-- Name: idx_ventas_empresa; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ventas_empresa ON public.ventas USING btree (empresa_id);


--
-- Name: idx_ventas_estado; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ventas_estado ON public.ventas USING btree (estado);


--
-- Name: idx_ventas_fecha; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ventas_fecha ON public.ventas USING btree (fecha_venta DESC);


--
-- Name: idx_ventas_fecha_estado; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ventas_fecha_estado ON public.ventas USING btree (fecha_venta, estado);


--
-- Name: idx_ventas_numero_factura; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ventas_numero_factura ON public.ventas USING btree (numero_factura);


--
-- Name: idx_ventas_orden; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ventas_orden ON public.ventas USING btree (orden_servicio_id);


--
-- Name: idx_ventas_sucursal; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ventas_sucursal ON public.ventas USING btree (sucursal_id);


--
-- Name: idx_ventas_tipo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ventas_tipo ON public.ventas USING btree (tipo_venta);


--
-- Name: idx_ventas_usuario; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ventas_usuario ON public.ventas USING btree (usuario_id);


--
-- Name: vista_modulos_disponibles _RETURN; Type: RULE; Schema: public; Owner: -
--

CREATE OR REPLACE VIEW public.vista_modulos_disponibles AS
 SELECT m.id,
    m.codigo,
    m.nombre,
    m.descripcion,
    m.categoria,
    m.icono,
    m.color,
    m.requiere_licencia,
    m.precio_mensual,
    m.es_modulo_base,
    count(md.modulo_requerido_id) AS cantidad_dependencias,
    string_agg((m2.nombre)::text, ', '::text) AS modulos_requeridos
   FROM ((public.modulos m
     LEFT JOIN public.modulos_dependencias md ON (((md.modulo_id = m.id) AND (md.es_obligatorio = true))))
     LEFT JOIN public.modulos m2 ON ((m2.id = md.modulo_requerido_id)))
  WHERE (m.estado = true)
  GROUP BY m.id, m.codigo, m.nombre, m.descripcion, m.categoria, m.icono, m.color, m.requiere_licencia, m.precio_mensual, m.es_modulo_base
  ORDER BY m.categoria, m.orden, m.nombre;


--
-- Name: ventas trg_actualizar_credito_cliente; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_actualizar_credito_cliente AFTER INSERT OR UPDATE ON public.ventas FOR EACH ROW EXECUTE FUNCTION public.actualizar_credito_cliente();


--
-- Name: movimientos_caja trg_actualizar_saldo_caja; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_actualizar_saldo_caja AFTER INSERT ON public.movimientos_caja FOR EACH ROW EXECUTE FUNCTION public.actualizar_saldo_caja();


--
-- Name: ventas_detalle trg_actualizar_stock_venta; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_actualizar_stock_venta AFTER DELETE ON public.ventas_detalle FOR EACH ROW EXECUTE FUNCTION public.actualizar_stock_venta();


--
-- Name: clientes trg_auditoria_clientes; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_auditoria_clientes AFTER INSERT OR DELETE OR UPDATE ON public.clientes FOR EACH ROW EXECUTE FUNCTION public.registrar_auditoria();


--
-- Name: ordenes_servicio trg_auditoria_ordenes_servicio; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_auditoria_ordenes_servicio AFTER INSERT OR DELETE OR UPDATE ON public.ordenes_servicio FOR EACH ROW EXECUTE FUNCTION public.registrar_auditoria();


--
-- Name: usuarios trg_auditoria_usuarios; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_auditoria_usuarios AFTER INSERT OR DELETE OR UPDATE ON public.usuarios FOR EACH ROW EXECUTE FUNCTION public.registrar_auditoria();


--
-- Name: ventas trg_auditoria_ventas; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_auditoria_ventas AFTER INSERT OR DELETE OR UPDATE ON public.ventas FOR EACH ROW EXECUTE FUNCTION public.registrar_auditoria();


--
-- Name: stock trg_calcular_cantidad_disponible; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_calcular_cantidad_disponible BEFORE INSERT OR UPDATE ON public.stock FOR EACH ROW EXECUTE FUNCTION public.calcular_cantidad_disponible();


--
-- Name: compras_detalle trg_calcular_cantidad_pendiente_compra; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_calcular_cantidad_pendiente_compra BEFORE INSERT OR UPDATE ON public.compras_detalle FOR EACH ROW EXECUTE FUNCTION public.calcular_cantidad_pendiente_compra();


--
-- Name: transferencias_stock_detalle trg_calcular_cantidad_pendiente_transferencia; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_calcular_cantidad_pendiente_transferencia BEFORE INSERT OR UPDATE ON public.transferencias_stock_detalle FOR EACH ROW EXECUTE FUNCTION public.calcular_cantidad_pendiente_transferencia();


--
-- Name: clientes trg_calcular_credito_disponible_cliente; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_calcular_credito_disponible_cliente BEFORE INSERT OR UPDATE ON public.clientes FOR EACH ROW EXECUTE FUNCTION public.calcular_credito_disponible_cliente();


--
-- Name: cuentas_cobrar trg_calcular_cuenta_cobrar; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_calcular_cuenta_cobrar BEFORE INSERT OR UPDATE ON public.cuentas_cobrar FOR EACH ROW EXECUTE FUNCTION public.calcular_cuenta_cobrar();


--
-- Name: cuentas_pagar trg_calcular_cuenta_pagar; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_calcular_cuenta_pagar BEFORE INSERT OR UPDATE ON public.cuentas_pagar FOR EACH ROW EXECUTE FUNCTION public.calcular_cuenta_pagar();


--
-- Name: ajustes_inventario_detalle trg_calcular_diferencia_ajuste; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_calcular_diferencia_ajuste BEFORE INSERT OR UPDATE ON public.ajustes_inventario_detalle FOR EACH ROW EXECUTE FUNCTION public.calcular_diferencia_ajuste();


--
-- Name: arqueos_caja trg_calcular_diferencia_arqueo; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_calcular_diferencia_arqueo BEFORE INSERT OR UPDATE ON public.arqueos_caja FOR EACH ROW EXECUTE FUNCTION public.calcular_diferencia_arqueo();


--
-- Name: compras trg_calcular_monto_pendiente_compra; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_calcular_monto_pendiente_compra BEFORE INSERT OR UPDATE ON public.compras FOR EACH ROW EXECUTE FUNCTION public.calcular_monto_pendiente_compra();


--
-- Name: ventas trg_calcular_monto_pendiente_venta; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_calcular_monto_pendiente_venta BEFORE INSERT OR UPDATE ON public.ventas FOR EACH ROW EXECUTE FUNCTION public.calcular_monto_pendiente_venta();


--
-- Name: empresa_modulos trg_desactivar_modulos_sucursales; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_desactivar_modulos_sucursales AFTER UPDATE ON public.empresa_modulos FOR EACH ROW EXECUTE FUNCTION public.desactivar_modulos_sucursales();


--
-- Name: clientes trg_fecha_modificacion_clientes; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_fecha_modificacion_clientes BEFORE UPDATE ON public.clientes FOR EACH ROW EXECUTE FUNCTION public.actualizar_fecha_modificacion();


--
-- Name: empresas trg_fecha_modificacion_empresas; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_fecha_modificacion_empresas BEFORE UPDATE ON public.empresas FOR EACH ROW EXECUTE FUNCTION public.actualizar_fecha_modificacion();


--
-- Name: ordenes_servicio trg_fecha_modificacion_ordenes_servicio; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_fecha_modificacion_ordenes_servicio BEFORE UPDATE ON public.ordenes_servicio FOR EACH ROW EXECUTE FUNCTION public.actualizar_fecha_modificacion();


--
-- Name: productos trg_fecha_modificacion_productos; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_fecha_modificacion_productos BEFORE UPDATE ON public.productos FOR EACH ROW EXECUTE FUNCTION public.actualizar_fecha_modificacion();


--
-- Name: usuarios trg_fecha_modificacion_usuarios; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_fecha_modificacion_usuarios BEFORE UPDATE ON public.usuarios FOR EACH ROW EXECUTE FUNCTION public.actualizar_fecha_modificacion();


--
-- Name: clientes trg_generar_nombre_empresa; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_generar_nombre_empresa BEFORE INSERT ON public.clientes FOR EACH ROW EXECUTE FUNCTION public.generar_nombre_empresa();


--
-- Name: empresa_modulos trg_notificar_vencimiento_modulo; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_notificar_vencimiento_modulo AFTER INSERT OR UPDATE ON public.empresa_modulos FOR EACH ROW EXECUTE FUNCTION public.notificar_vencimiento_modulo();


--
-- Name: sucursal_modulos trg_validar_empresa_modulo_valido; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_validar_empresa_modulo_valido BEFORE INSERT OR UPDATE ON public.sucursal_modulos FOR EACH ROW EXECUTE FUNCTION public.validar_empresa_modulo_valido();


--
-- Name: stock trg_validar_stock_minimo; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_validar_stock_minimo AFTER UPDATE OF cantidad ON public.stock FOR EACH ROW EXECUTE FUNCTION public.validar_stock_minimo();


--
-- Name: empresa_modulos trg_verificar_dependencias_empresa_modulo; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_verificar_dependencias_empresa_modulo BEFORE INSERT OR UPDATE ON public.empresa_modulos FOR EACH ROW EXECUTE FUNCTION public.verificar_dependencias_modulo();


--
-- Name: ajustes_inventario_detalle ajustes_inventario_detalle_ajuste_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ajustes_inventario_detalle
    ADD CONSTRAINT ajustes_inventario_detalle_ajuste_id_fkey FOREIGN KEY (ajuste_id) REFERENCES public.ajustes_inventario(id) ON DELETE CASCADE;


--
-- Name: ajustes_inventario_detalle ajustes_inventario_detalle_producto_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ajustes_inventario_detalle
    ADD CONSTRAINT ajustes_inventario_detalle_producto_id_fkey FOREIGN KEY (producto_id) REFERENCES public.productos(id);


--
-- Name: ajustes_inventario ajustes_inventario_empresa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ajustes_inventario
    ADD CONSTRAINT ajustes_inventario_empresa_id_fkey FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: ajustes_inventario ajustes_inventario_sucursal_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ajustes_inventario
    ADD CONSTRAINT ajustes_inventario_sucursal_id_fkey FOREIGN KEY (sucursal_id) REFERENCES public.sucursales(id);


--
-- Name: ajustes_inventario ajustes_inventario_usuario_aprueba_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ajustes_inventario
    ADD CONSTRAINT ajustes_inventario_usuario_aprueba_fkey FOREIGN KEY (usuario_aprueba) REFERENCES public.usuarios(id);


--
-- Name: ajustes_inventario ajustes_inventario_usuario_solicita_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ajustes_inventario
    ADD CONSTRAINT ajustes_inventario_usuario_solicita_fkey FOREIGN KEY (usuario_solicita) REFERENCES public.usuarios(id);


--
-- Name: arqueos_caja arqueos_caja_caja_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.arqueos_caja
    ADD CONSTRAINT arqueos_caja_caja_id_fkey FOREIGN KEY (caja_id) REFERENCES public.cajas(id) ON DELETE RESTRICT;


--
-- Name: arqueos_caja_detalle arqueos_caja_detalle_arqueo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.arqueos_caja_detalle
    ADD CONSTRAINT arqueos_caja_detalle_arqueo_id_fkey FOREIGN KEY (arqueo_id) REFERENCES public.arqueos_caja(id) ON DELETE CASCADE;


--
-- Name: arqueos_caja arqueos_caja_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.arqueos_caja
    ADD CONSTRAINT arqueos_caja_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id);


--
-- Name: auditoria auditoria_empresa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.auditoria
    ADD CONSTRAINT auditoria_empresa_id_fkey FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: auditoria auditoria_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.auditoria
    ADD CONSTRAINT auditoria_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id);


--
-- Name: cajas cajas_empresa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cajas
    ADD CONSTRAINT cajas_empresa_id_fkey FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE RESTRICT;


--
-- Name: cajas cajas_sucursal_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cajas
    ADD CONSTRAINT cajas_sucursal_id_fkey FOREIGN KEY (sucursal_id) REFERENCES public.sucursales(id) ON DELETE RESTRICT;


--
-- Name: cajas cajas_usuario_apertura_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cajas
    ADD CONSTRAINT cajas_usuario_apertura_fkey FOREIGN KEY (usuario_apertura) REFERENCES public.usuarios(id);


--
-- Name: cajas cajas_usuario_cierre_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cajas
    ADD CONSTRAINT cajas_usuario_cierre_fkey FOREIGN KEY (usuario_cierre) REFERENCES public.usuarios(id);


--
-- Name: cajas cajas_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cajas
    ADD CONSTRAINT cajas_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id);


--
-- Name: categorias categorias_categoria_padre_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias
    ADD CONSTRAINT categorias_categoria_padre_id_fkey FOREIGN KEY (categoria_padre_id) REFERENCES public.categorias(id);


--
-- Name: categorias categorias_empresa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias
    ADD CONSTRAINT categorias_empresa_id_fkey FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE RESTRICT;


--
-- Name: categorias categorias_usuario_creacion_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias
    ADD CONSTRAINT categorias_usuario_creacion_fkey FOREIGN KEY (usuario_creacion) REFERENCES public.usuarios(id);


--
-- Name: chat_participantes chat_participantes_chat_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chat_participantes
    ADD CONSTRAINT chat_participantes_chat_id_foreign FOREIGN KEY (chat_id) REFERENCES public.chats(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: chat_participantes chat_participantes_usuario_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chat_participantes
    ADD CONSTRAINT chat_participantes_usuario_id_foreign FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: chats chats_creador_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chats
    ADD CONSTRAINT chats_creador_id_foreign FOREIGN KEY (creador_id) REFERENCES public.usuarios(id) ON UPDATE SET NULL ON DELETE SET NULL;


--
-- Name: chats chats_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chats
    ADD CONSTRAINT chats_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: clientes_contactos clientes_contactos_cliente_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clientes_contactos
    ADD CONSTRAINT clientes_contactos_cliente_id_fkey FOREIGN KEY (cliente_id) REFERENCES public.clientes(id) ON DELETE CASCADE;


--
-- Name: clientes clientes_empresa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clientes
    ADD CONSTRAINT clientes_empresa_id_fkey FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE RESTRICT;


--
-- Name: clientes_imagenes clientes_imagenes_cliente_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clientes_imagenes
    ADD CONSTRAINT clientes_imagenes_cliente_id_fkey FOREIGN KEY (cliente_id) REFERENCES public.clientes(id) ON DELETE CASCADE;


--
-- Name: clientes clientes_usuario_creacion_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clientes
    ADD CONSTRAINT clientes_usuario_creacion_fkey FOREIGN KEY (usuario_creacion) REFERENCES public.usuarios(id);


--
-- Name: clientes clientes_vendedor_asignado_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clientes
    ADD CONSTRAINT clientes_vendedor_asignado_id_fkey FOREIGN KEY (vendedor_asignado_id) REFERENCES public.usuarios(id);


--
-- Name: compras_detalle compras_detalle_compra_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.compras_detalle
    ADD CONSTRAINT compras_detalle_compra_id_fkey FOREIGN KEY (compra_id) REFERENCES public.compras(id) ON DELETE CASCADE;


--
-- Name: compras_detalle compras_detalle_producto_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.compras_detalle
    ADD CONSTRAINT compras_detalle_producto_id_fkey FOREIGN KEY (producto_id) REFERENCES public.productos(id);


--
-- Name: compras compras_empresa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.compras
    ADD CONSTRAINT compras_empresa_id_fkey FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: compras compras_proveedor_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.compras
    ADD CONSTRAINT compras_proveedor_id_fkey FOREIGN KEY (proveedor_id) REFERENCES public.proveedores(id);


--
-- Name: compras compras_sucursal_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.compras
    ADD CONSTRAINT compras_sucursal_id_fkey FOREIGN KEY (sucursal_id) REFERENCES public.sucursales(id);


--
-- Name: compras compras_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.compras
    ADD CONSTRAINT compras_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id);


--
-- Name: configuraciones configuraciones_empresa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configuraciones
    ADD CONSTRAINT configuraciones_empresa_id_fkey FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: cotizaciones cotizaciones_cliente_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cotizaciones
    ADD CONSTRAINT cotizaciones_cliente_id_fkey FOREIGN KEY (cliente_id) REFERENCES public.clientes(id);


--
-- Name: cotizaciones_detalle cotizaciones_detalle_cotizacion_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cotizaciones_detalle
    ADD CONSTRAINT cotizaciones_detalle_cotizacion_id_fkey FOREIGN KEY (cotizacion_id) REFERENCES public.cotizaciones(id) ON DELETE CASCADE;


--
-- Name: cotizaciones_detalle cotizaciones_detalle_producto_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cotizaciones_detalle
    ADD CONSTRAINT cotizaciones_detalle_producto_id_fkey FOREIGN KEY (producto_id) REFERENCES public.productos(id);


--
-- Name: cotizaciones cotizaciones_empresa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cotizaciones
    ADD CONSTRAINT cotizaciones_empresa_id_fkey FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: cotizaciones cotizaciones_lista_precio_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cotizaciones
    ADD CONSTRAINT cotizaciones_lista_precio_id_fkey FOREIGN KEY (lista_precio_id) REFERENCES public.listas_precios(id);


--
-- Name: cotizaciones cotizaciones_sucursal_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cotizaciones
    ADD CONSTRAINT cotizaciones_sucursal_id_fkey FOREIGN KEY (sucursal_id) REFERENCES public.sucursales(id);


--
-- Name: cotizaciones cotizaciones_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cotizaciones
    ADD CONSTRAINT cotizaciones_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id);


--
-- Name: crm_chats crm_chats_asignado_a_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_chats
    ADD CONSTRAINT crm_chats_asignado_a_foreign FOREIGN KEY (asignado_a) REFERENCES public.usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: crm_chats crm_chats_cliente_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_chats
    ADD CONSTRAINT crm_chats_cliente_id_foreign FOREIGN KEY (cliente_id) REFERENCES public.clientes(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: crm_chats crm_chats_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_chats
    ADD CONSTRAINT crm_chats_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: crm_chats crm_chats_sucursal_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_chats
    ADD CONSTRAINT crm_chats_sucursal_id_foreign FOREIGN KEY (sucursal_id) REFERENCES public.sucursales(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: crm_mensajes crm_mensajes_chat_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_mensajes
    ADD CONSTRAINT crm_mensajes_chat_id_foreign FOREIGN KEY (chat_id) REFERENCES public.crm_chats(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: crm_mensajes crm_mensajes_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_mensajes
    ADD CONSTRAINT crm_mensajes_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: crm_mensajes crm_mensajes_orden_servicio_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_mensajes
    ADD CONSTRAINT crm_mensajes_orden_servicio_id_foreign FOREIGN KEY (orden_servicio_id) REFERENCES public.ordenes_servicio(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: crm_mensajes crm_mensajes_usuario_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_mensajes
    ADD CONSTRAINT crm_mensajes_usuario_id_foreign FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: crm_mensajes crm_mensajes_venta_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_mensajes
    ADD CONSTRAINT crm_mensajes_venta_id_foreign FOREIGN KEY (venta_id) REFERENCES public.ventas(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: crm_plantillas crm_plantillas_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_plantillas
    ADD CONSTRAINT crm_plantillas_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: crm_plantillas crm_plantillas_usuario_creacion_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_plantillas
    ADD CONSTRAINT crm_plantillas_usuario_creacion_foreign FOREIGN KEY (usuario_creacion) REFERENCES public.usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: cuentas_cobrar cuentas_cobrar_cliente_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cuentas_cobrar
    ADD CONSTRAINT cuentas_cobrar_cliente_id_fkey FOREIGN KEY (cliente_id) REFERENCES public.clientes(id);


--
-- Name: cuentas_cobrar cuentas_cobrar_empresa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cuentas_cobrar
    ADD CONSTRAINT cuentas_cobrar_empresa_id_fkey FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: cuentas_cobrar cuentas_cobrar_sucursal_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cuentas_cobrar
    ADD CONSTRAINT cuentas_cobrar_sucursal_id_fkey FOREIGN KEY (sucursal_id) REFERENCES public.sucursales(id);


--
-- Name: cuentas_pagar cuentas_pagar_empresa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cuentas_pagar
    ADD CONSTRAINT cuentas_pagar_empresa_id_fkey FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: cuentas_pagar cuentas_pagar_proveedor_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cuentas_pagar
    ADD CONSTRAINT cuentas_pagar_proveedor_id_fkey FOREIGN KEY (proveedor_id) REFERENCES public.proveedores(id);


--
-- Name: cuentas_pagar cuentas_pagar_sucursal_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cuentas_pagar
    ADD CONSTRAINT cuentas_pagar_sucursal_id_fkey FOREIGN KEY (sucursal_id) REFERENCES public.sucursales(id);


--
-- Name: descuentos_especiales descuentos_especiales_empresa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.descuentos_especiales
    ADD CONSTRAINT descuentos_especiales_empresa_id_fkey FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: descuentos_especiales descuentos_especiales_usuario_creacion_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.descuentos_especiales
    ADD CONSTRAINT descuentos_especiales_usuario_creacion_fkey FOREIGN KEY (usuario_creacion) REFERENCES public.usuarios(id);


--
-- Name: empresa_modulos empresa_modulos_empresa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.empresa_modulos
    ADD CONSTRAINT empresa_modulos_empresa_id_fkey FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: empresa_modulos empresa_modulos_modulo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.empresa_modulos
    ADD CONSTRAINT empresa_modulos_modulo_id_fkey FOREIGN KEY (modulo_id) REFERENCES public.modulos(id) ON DELETE CASCADE;


--
-- Name: empresa_modulos empresa_modulos_usuario_asigna_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.empresa_modulos
    ADD CONSTRAINT empresa_modulos_usuario_asigna_fkey FOREIGN KEY (usuario_asigna) REFERENCES public.usuarios(id);


--
-- Name: clientes fk_cliente_lista_precio; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clientes
    ADD CONSTRAINT fk_cliente_lista_precio FOREIGN KEY (lista_precio_id) REFERENCES public.listas_precios(id);


--
-- Name: listas_precios_detalle listas_precios_detalle_lista_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.listas_precios_detalle
    ADD CONSTRAINT listas_precios_detalle_lista_id_fkey FOREIGN KEY (lista_id) REFERENCES public.listas_precios(id) ON DELETE CASCADE;


--
-- Name: listas_precios_detalle listas_precios_detalle_producto_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.listas_precios_detalle
    ADD CONSTRAINT listas_precios_detalle_producto_id_fkey FOREIGN KEY (producto_id) REFERENCES public.productos(id) ON DELETE CASCADE;


--
-- Name: listas_precios listas_precios_empresa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.listas_precios
    ADD CONSTRAINT listas_precios_empresa_id_fkey FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE RESTRICT;


--
-- Name: listas_precios listas_precios_usuario_creacion_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.listas_precios
    ADD CONSTRAINT listas_precios_usuario_creacion_fkey FOREIGN KEY (usuario_creacion) REFERENCES public.usuarios(id);


--
-- Name: logs_sistema logs_sistema_empresa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.logs_sistema
    ADD CONSTRAINT logs_sistema_empresa_id_fkey FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: logs_sistema logs_sistema_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.logs_sistema
    ADD CONSTRAINT logs_sistema_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id);


--
-- Name: marcas marcas_empresa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.marcas
    ADD CONSTRAINT marcas_empresa_id_fkey FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE RESTRICT;


--
-- Name: mensajes mensajes_chat_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mensajes
    ADD CONSTRAINT mensajes_chat_id_foreign FOREIGN KEY (chat_id) REFERENCES public.chats(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: mensajes mensajes_usuario_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mensajes
    ADD CONSTRAINT mensajes_usuario_id_foreign FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id) ON UPDATE SET NULL ON DELETE SET NULL;


--
-- Name: modulos_dependencias modulos_dependencias_modulo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.modulos_dependencias
    ADD CONSTRAINT modulos_dependencias_modulo_id_fkey FOREIGN KEY (modulo_id) REFERENCES public.modulos(id) ON DELETE CASCADE;


--
-- Name: modulos_dependencias modulos_dependencias_modulo_requerido_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.modulos_dependencias
    ADD CONSTRAINT modulos_dependencias_modulo_requerido_id_fkey FOREIGN KEY (modulo_requerido_id) REFERENCES public.modulos(id) ON DELETE CASCADE;


--
-- Name: movimientos_caja movimientos_caja_caja_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimientos_caja
    ADD CONSTRAINT movimientos_caja_caja_id_fkey FOREIGN KEY (caja_id) REFERENCES public.cajas(id) ON DELETE RESTRICT;


--
-- Name: movimientos_caja movimientos_caja_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimientos_caja
    ADD CONSTRAINT movimientos_caja_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id);


--
-- Name: movimientos_stock movimientos_stock_empresa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimientos_stock
    ADD CONSTRAINT movimientos_stock_empresa_id_fkey FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: movimientos_stock movimientos_stock_producto_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimientos_stock
    ADD CONSTRAINT movimientos_stock_producto_id_fkey FOREIGN KEY (producto_id) REFERENCES public.productos(id);


--
-- Name: movimientos_stock movimientos_stock_sucursal_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimientos_stock
    ADD CONSTRAINT movimientos_stock_sucursal_id_fkey FOREIGN KEY (sucursal_id) REFERENCES public.sucursales(id);


--
-- Name: movimientos_stock movimientos_stock_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimientos_stock
    ADD CONSTRAINT movimientos_stock_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id);


--
-- Name: notificaciones notificaciones_empresa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notificaciones
    ADD CONSTRAINT notificaciones_empresa_id_fkey FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: notificaciones notificaciones_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notificaciones
    ADD CONSTRAINT notificaciones_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id);


--
-- Name: ordenes_servicio ordenes_servicio_autorizado_por_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_servicio
    ADD CONSTRAINT ordenes_servicio_autorizado_por_fkey FOREIGN KEY (autorizado_por) REFERENCES public.usuarios(id);


--
-- Name: ordenes_servicio ordenes_servicio_cliente_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_servicio
    ADD CONSTRAINT ordenes_servicio_cliente_id_fkey FOREIGN KEY (cliente_id) REFERENCES public.clientes(id) ON DELETE RESTRICT;


--
-- Name: ordenes_servicio_comentarios ordenes_servicio_comentarios_orden_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_servicio_comentarios
    ADD CONSTRAINT ordenes_servicio_comentarios_orden_id_fkey FOREIGN KEY (orden_id) REFERENCES public.ordenes_servicio(id) ON DELETE CASCADE;


--
-- Name: ordenes_servicio_comentarios ordenes_servicio_comentarios_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_servicio_comentarios
    ADD CONSTRAINT ordenes_servicio_comentarios_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id);


--
-- Name: ordenes_servicio_documentos ordenes_servicio_documentos_orden_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_servicio_documentos
    ADD CONSTRAINT ordenes_servicio_documentos_orden_id_fkey FOREIGN KEY (orden_id) REFERENCES public.ordenes_servicio(id) ON DELETE CASCADE;


--
-- Name: ordenes_servicio_documentos ordenes_servicio_documentos_usuario_creacion_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_servicio_documentos
    ADD CONSTRAINT ordenes_servicio_documentos_usuario_creacion_fkey FOREIGN KEY (usuario_creacion) REFERENCES public.usuarios(id);


--
-- Name: ordenes_servicio ordenes_servicio_empresa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_servicio
    ADD CONSTRAINT ordenes_servicio_empresa_id_fkey FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE RESTRICT;


--
-- Name: ordenes_servicio ordenes_servicio_estado_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_servicio
    ADD CONSTRAINT ordenes_servicio_estado_id_fkey FOREIGN KEY (estado_id) REFERENCES public.estados_orden(id);


--
-- Name: ordenes_servicio_historial ordenes_servicio_historial_estado_anterior_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_servicio_historial
    ADD CONSTRAINT ordenes_servicio_historial_estado_anterior_id_fkey FOREIGN KEY (estado_anterior_id) REFERENCES public.estados_orden(id);


--
-- Name: ordenes_servicio_historial ordenes_servicio_historial_estado_nuevo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_servicio_historial
    ADD CONSTRAINT ordenes_servicio_historial_estado_nuevo_id_fkey FOREIGN KEY (estado_nuevo_id) REFERENCES public.estados_orden(id);


--
-- Name: ordenes_servicio_historial ordenes_servicio_historial_orden_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_servicio_historial
    ADD CONSTRAINT ordenes_servicio_historial_orden_id_fkey FOREIGN KEY (orden_id) REFERENCES public.ordenes_servicio(id) ON DELETE CASCADE;


--
-- Name: ordenes_servicio_historial ordenes_servicio_historial_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_servicio_historial
    ADD CONSTRAINT ordenes_servicio_historial_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id);


--
-- Name: ordenes_servicio_imagenes ordenes_servicio_imagenes_orden_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_servicio_imagenes
    ADD CONSTRAINT ordenes_servicio_imagenes_orden_id_fkey FOREIGN KEY (orden_id) REFERENCES public.ordenes_servicio(id) ON DELETE CASCADE;


--
-- Name: ordenes_servicio_imagenes ordenes_servicio_imagenes_usuario_creacion_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_servicio_imagenes
    ADD CONSTRAINT ordenes_servicio_imagenes_usuario_creacion_fkey FOREIGN KEY (usuario_creacion) REFERENCES public.usuarios(id);


--
-- Name: ordenes_servicio_mano_obra ordenes_servicio_mano_obra_orden_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_servicio_mano_obra
    ADD CONSTRAINT ordenes_servicio_mano_obra_orden_id_fkey FOREIGN KEY (orden_id) REFERENCES public.ordenes_servicio(id) ON DELETE CASCADE;


--
-- Name: ordenes_servicio_mano_obra ordenes_servicio_mano_obra_tecnico_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_servicio_mano_obra
    ADD CONSTRAINT ordenes_servicio_mano_obra_tecnico_id_fkey FOREIGN KEY (tecnico_id) REFERENCES public.usuarios(id);


--
-- Name: ordenes_servicio_mano_obra ordenes_servicio_mano_obra_usuario_agrega_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_servicio_mano_obra
    ADD CONSTRAINT ordenes_servicio_mano_obra_usuario_agrega_fkey FOREIGN KEY (usuario_agrega) REFERENCES public.usuarios(id);


--
-- Name: ordenes_servicio_notificaciones ordenes_servicio_notificaciones_orden_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_servicio_notificaciones
    ADD CONSTRAINT ordenes_servicio_notificaciones_orden_id_fkey FOREIGN KEY (orden_id) REFERENCES public.ordenes_servicio(id) ON DELETE CASCADE;


--
-- Name: ordenes_servicio_repuestos ordenes_servicio_repuestos_orden_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_servicio_repuestos
    ADD CONSTRAINT ordenes_servicio_repuestos_orden_id_fkey FOREIGN KEY (orden_id) REFERENCES public.ordenes_servicio(id) ON DELETE CASCADE;


--
-- Name: ordenes_servicio_repuestos ordenes_servicio_repuestos_producto_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_servicio_repuestos
    ADD CONSTRAINT ordenes_servicio_repuestos_producto_id_fkey FOREIGN KEY (producto_id) REFERENCES public.productos(id);


--
-- Name: ordenes_servicio_repuestos ordenes_servicio_repuestos_usuario_agrega_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_servicio_repuestos
    ADD CONSTRAINT ordenes_servicio_repuestos_usuario_agrega_fkey FOREIGN KEY (usuario_agrega) REFERENCES public.usuarios(id);


--
-- Name: ordenes_servicio ordenes_servicio_sucursal_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_servicio
    ADD CONSTRAINT ordenes_servicio_sucursal_id_fkey FOREIGN KEY (sucursal_id) REFERENCES public.sucursales(id) ON DELETE RESTRICT;


--
-- Name: ordenes_servicio ordenes_servicio_tecnico_asignado_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_servicio
    ADD CONSTRAINT ordenes_servicio_tecnico_asignado_fkey FOREIGN KEY (tecnico_asignado) REFERENCES public.usuarios(id);


--
-- Name: ordenes_servicio ordenes_servicio_usuario_creacion_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_servicio
    ADD CONSTRAINT ordenes_servicio_usuario_creacion_fkey FOREIGN KEY (usuario_creacion) REFERENCES public.usuarios(id);


--
-- Name: ordenes_servicio ordenes_servicio_usuario_recepcion_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ordenes_servicio
    ADD CONSTRAINT ordenes_servicio_usuario_recepcion_fkey FOREIGN KEY (usuario_recepcion) REFERENCES public.usuarios(id);


--
-- Name: pagos_cuentas pagos_cuentas_empresa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pagos_cuentas
    ADD CONSTRAINT pagos_cuentas_empresa_id_fkey FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: pagos_cuentas pagos_cuentas_sucursal_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pagos_cuentas
    ADD CONSTRAINT pagos_cuentas_sucursal_id_fkey FOREIGN KEY (sucursal_id) REFERENCES public.sucursales(id);


--
-- Name: pagos_cuentas pagos_cuentas_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pagos_cuentas
    ADD CONSTRAINT pagos_cuentas_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id);


--
-- Name: pagos_ventas pagos_ventas_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pagos_ventas
    ADD CONSTRAINT pagos_ventas_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id);


--
-- Name: pagos_ventas pagos_ventas_venta_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pagos_ventas
    ADD CONSTRAINT pagos_ventas_venta_id_fkey FOREIGN KEY (venta_id) REFERENCES public.ventas(id) ON DELETE RESTRICT;


--
-- Name: permisos permisos_rol_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permisos
    ADD CONSTRAINT permisos_rol_id_fkey FOREIGN KEY (rol_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: producto_imagenes producto_imagenes_producto_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.producto_imagenes
    ADD CONSTRAINT producto_imagenes_producto_id_fkey FOREIGN KEY (producto_id) REFERENCES public.productos(id) ON DELETE CASCADE;


--
-- Name: producto_imagenes producto_imagenes_usuario_creacion_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.producto_imagenes
    ADD CONSTRAINT producto_imagenes_usuario_creacion_fkey FOREIGN KEY (usuario_creacion) REFERENCES public.usuarios(id);


--
-- Name: productos productos_categoria_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.productos
    ADD CONSTRAINT productos_categoria_id_fkey FOREIGN KEY (categoria_id) REFERENCES public.categorias(id);


--
-- Name: productos productos_empresa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.productos
    ADD CONSTRAINT productos_empresa_id_fkey FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE RESTRICT;


--
-- Name: productos productos_marca_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.productos
    ADD CONSTRAINT productos_marca_id_fkey FOREIGN KEY (marca_id) REFERENCES public.marcas(id);


--
-- Name: productos productos_unidad_medida_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.productos
    ADD CONSTRAINT productos_unidad_medida_id_fkey FOREIGN KEY (unidad_medida_id) REFERENCES public.unidades_medida(id);


--
-- Name: productos productos_usuario_creacion_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.productos
    ADD CONSTRAINT productos_usuario_creacion_fkey FOREIGN KEY (usuario_creacion) REFERENCES public.usuarios(id);


--
-- Name: proveedores proveedores_empresa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proveedores
    ADD CONSTRAINT proveedores_empresa_id_fkey FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE RESTRICT;


--
-- Name: proveedores proveedores_usuario_creacion_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proveedores
    ADD CONSTRAINT proveedores_usuario_creacion_fkey FOREIGN KEY (usuario_creacion) REFERENCES public.usuarios(id);


--
-- Name: stock stock_producto_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock
    ADD CONSTRAINT stock_producto_id_fkey FOREIGN KEY (producto_id) REFERENCES public.productos(id) ON DELETE RESTRICT;


--
-- Name: stock stock_sucursal_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock
    ADD CONSTRAINT stock_sucursal_id_fkey FOREIGN KEY (sucursal_id) REFERENCES public.sucursales(id) ON DELETE RESTRICT;


--
-- Name: sucursal_modulos sucursal_modulos_empresa_modulo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sucursal_modulos
    ADD CONSTRAINT sucursal_modulos_empresa_modulo_id_fkey FOREIGN KEY (empresa_modulo_id) REFERENCES public.empresa_modulos(id) ON DELETE CASCADE;


--
-- Name: sucursal_modulos sucursal_modulos_modulo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sucursal_modulos
    ADD CONSTRAINT sucursal_modulos_modulo_id_fkey FOREIGN KEY (modulo_id) REFERENCES public.modulos(id) ON DELETE CASCADE;


--
-- Name: sucursal_modulos sucursal_modulos_sucursal_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sucursal_modulos
    ADD CONSTRAINT sucursal_modulos_sucursal_id_fkey FOREIGN KEY (sucursal_id) REFERENCES public.sucursales(id) ON DELETE CASCADE;


--
-- Name: sucursal_modulos sucursal_modulos_usuario_asigna_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sucursal_modulos
    ADD CONSTRAINT sucursal_modulos_usuario_asigna_fkey FOREIGN KEY (usuario_asigna) REFERENCES public.usuarios(id);


--
-- Name: sucursales sucursales_empresa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sucursales
    ADD CONSTRAINT sucursales_empresa_id_fkey FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE RESTRICT;


--
-- Name: talonarios talonarios_empresa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.talonarios
    ADD CONSTRAINT talonarios_empresa_id_fkey FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: talonarios talonarios_sucursal_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.talonarios
    ADD CONSTRAINT talonarios_sucursal_id_fkey FOREIGN KEY (sucursal_id) REFERENCES public.sucursales(id);


--
-- Name: talonarios talonarios_usuario_creacion_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.talonarios
    ADD CONSTRAINT talonarios_usuario_creacion_fkey FOREIGN KEY (usuario_creacion) REFERENCES public.usuarios(id);


--
-- Name: tipos_cambio tipos_cambio_empresa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tipos_cambio
    ADD CONSTRAINT tipos_cambio_empresa_id_fkey FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: tipos_cambio tipos_cambio_usuario_creacion_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tipos_cambio
    ADD CONSTRAINT tipos_cambio_usuario_creacion_fkey FOREIGN KEY (usuario_creacion) REFERENCES public.usuarios(id);


--
-- Name: transferencias_stock_detalle transferencias_stock_detalle_producto_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transferencias_stock_detalle
    ADD CONSTRAINT transferencias_stock_detalle_producto_id_fkey FOREIGN KEY (producto_id) REFERENCES public.productos(id);


--
-- Name: transferencias_stock_detalle transferencias_stock_detalle_transferencia_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transferencias_stock_detalle
    ADD CONSTRAINT transferencias_stock_detalle_transferencia_id_fkey FOREIGN KEY (transferencia_id) REFERENCES public.transferencias_stock(id) ON DELETE CASCADE;


--
-- Name: transferencias_stock transferencias_stock_empresa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transferencias_stock
    ADD CONSTRAINT transferencias_stock_empresa_id_fkey FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: transferencias_stock transferencias_stock_sucursal_destino_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transferencias_stock
    ADD CONSTRAINT transferencias_stock_sucursal_destino_id_fkey FOREIGN KEY (sucursal_destino_id) REFERENCES public.sucursales(id);


--
-- Name: transferencias_stock transferencias_stock_sucursal_origen_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transferencias_stock
    ADD CONSTRAINT transferencias_stock_sucursal_origen_id_fkey FOREIGN KEY (sucursal_origen_id) REFERENCES public.sucursales(id);


--
-- Name: transferencias_stock transferencias_stock_usuario_autoriza_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transferencias_stock
    ADD CONSTRAINT transferencias_stock_usuario_autoriza_fkey FOREIGN KEY (usuario_autoriza) REFERENCES public.usuarios(id);


--
-- Name: transferencias_stock transferencias_stock_usuario_recibe_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transferencias_stock
    ADD CONSTRAINT transferencias_stock_usuario_recibe_fkey FOREIGN KEY (usuario_recibe) REFERENCES public.usuarios(id);


--
-- Name: transferencias_stock transferencias_stock_usuario_solicita_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transferencias_stock
    ADD CONSTRAINT transferencias_stock_usuario_solicita_fkey FOREIGN KEY (usuario_solicita) REFERENCES public.usuarios(id);


--
-- Name: usuario_sucursales usuario_sucursales_sucursal_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuario_sucursales
    ADD CONSTRAINT usuario_sucursales_sucursal_id_fkey FOREIGN KEY (sucursal_id) REFERENCES public.sucursales(id) ON DELETE CASCADE;


--
-- Name: usuario_sucursales usuario_sucursales_usuario_asignacion_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuario_sucursales
    ADD CONSTRAINT usuario_sucursales_usuario_asignacion_fkey FOREIGN KEY (usuario_asignacion) REFERENCES public.usuarios(id);


--
-- Name: usuario_sucursales usuario_sucursales_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuario_sucursales
    ADD CONSTRAINT usuario_sucursales_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id) ON DELETE CASCADE;


--
-- Name: usuarios usuarios_empresa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_empresa_id_fkey FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE RESTRICT;


--
-- Name: usuarios usuarios_rol_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_rol_id_fkey FOREIGN KEY (rol_id) REFERENCES public.roles(id);


--
-- Name: usuarios usuarios_sucursal_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_sucursal_id_fkey FOREIGN KEY (sucursal_id) REFERENCES public.sucursales(id) ON DELETE RESTRICT;


--
-- Name: usuarios usuarios_usuario_creacion_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_usuario_creacion_fkey FOREIGN KEY (usuario_creacion) REFERENCES public.usuarios(id);


--
-- Name: ventas ventas_cliente_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ventas
    ADD CONSTRAINT ventas_cliente_id_fkey FOREIGN KEY (cliente_id) REFERENCES public.clientes(id);


--
-- Name: ventas_detalle ventas_detalle_producto_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ventas_detalle
    ADD CONSTRAINT ventas_detalle_producto_id_fkey FOREIGN KEY (producto_id) REFERENCES public.productos(id);


--
-- Name: ventas_detalle ventas_detalle_venta_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ventas_detalle
    ADD CONSTRAINT ventas_detalle_venta_id_fkey FOREIGN KEY (venta_id) REFERENCES public.ventas(id) ON DELETE CASCADE;


--
-- Name: ventas ventas_empresa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ventas
    ADD CONSTRAINT ventas_empresa_id_fkey FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: ventas ventas_lista_precio_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ventas
    ADD CONSTRAINT ventas_lista_precio_id_fkey FOREIGN KEY (lista_precio_id) REFERENCES public.listas_precios(id);


--
-- Name: ventas ventas_sucursal_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ventas
    ADD CONSTRAINT ventas_sucursal_id_fkey FOREIGN KEY (sucursal_id) REFERENCES public.sucursales(id);


--
-- Name: ventas ventas_usuario_anula_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ventas
    ADD CONSTRAINT ventas_usuario_anula_fkey FOREIGN KEY (usuario_anula) REFERENCES public.usuarios(id);


--
-- Name: ventas ventas_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ventas
    ADD CONSTRAINT ventas_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id);


--
-- Name: ventas ventas_vendedor_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ventas
    ADD CONSTRAINT ventas_vendedor_id_fkey FOREIGN KEY (vendedor_id) REFERENCES public.usuarios(id);


--
-- PostgreSQL database dump complete
--

\unrestrict O870L5eLnZXkHZCsI5cmwldj63buhg7HdWbNGhFd5ukB1aovjHeNgUjR5C0qUzS

