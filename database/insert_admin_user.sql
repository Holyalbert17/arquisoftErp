-- Script para crear usuario administrador
-- Ejecutar este script en PostgreSQL

DO $$
DECLARE
    v_empresa_id INTEGER;
    v_sucursal_id INTEGER;
    v_rol_id INTEGER;
    v_password TEXT;
BEGIN
    -- Verificar si ya existe el usuario admin
    IF EXISTS (SELECT 1 FROM usuarios WHERE username = 'admin') THEN
        RAISE NOTICE 'El usuario admin ya existe';
        RETURN;
    END IF;

    -- Obtener o crear empresa
    SELECT id INTO v_empresa_id FROM empresas LIMIT 1;
    
    IF v_empresa_id IS NULL THEN
        -- Crear empresa de ejemplo
        INSERT INTO empresas (
            nombre, ruc, direccion, ciudad, telefono, email,
            porcentaje_iva, timbrado, establecimiento, punto_expedicion,
            moneda_principal, estado, fecha_creacion
        ) VALUES (
            'Empresa Demo',
            '80012345-1',
            'Av. República Argentina 123',
            'Asunción',
            '(021) 123-456',
            'admin@empresa.com.py',
            10.00,
            '1234567',
            '001',
            '001',
            'PYG',
            true,
            CURRENT_TIMESTAMP
        ) RETURNING id INTO v_empresa_id;
    END IF;

    -- Obtener o crear sucursal matriz
    SELECT id INTO v_sucursal_id 
    FROM sucursales 
    WHERE empresa_id = v_empresa_id AND es_matriz = true 
    LIMIT 1;
    
    IF v_sucursal_id IS NULL THEN
        INSERT INTO sucursales (
            empresa_id, nombre, direccion, ciudad, departamento,
            es_matriz, codigo_establecimiento, estado, fecha_creacion
        ) VALUES (
            v_empresa_id,
            'Casa Central',
            'Av. República Argentina 123',
            'Asunción',
            'CAPITAL',
            true,
            '001',
            true,
            CURRENT_TIMESTAMP
        ) RETURNING id INTO v_sucursal_id;
    END IF;

    -- Obtener o crear rol Administrador
    SELECT id INTO v_rol_id FROM roles WHERE nivel = 1 LIMIT 1;
    
    IF v_rol_id IS NULL THEN
        INSERT INTO roles (
            nombre, descripcion, nivel, es_rol_sistema, estado, fecha_creacion
        ) VALUES (
            'Administrador',
            'Acceso completo al sistema, gestiona todas las empresas',
            1,
            true,
            true,
            CURRENT_TIMESTAMP
        ) RETURNING id INTO v_rol_id;
    END IF;

    -- Crear usuario admin
    -- Contraseña: Admin123
    v_password := crypt('Admin123', gen_salt('bf'));
    
    INSERT INTO usuarios (
        empresa_id, sucursal_id, rol_id, nombre, apellido, ci,
        email, telefono, username, password, estado, fecha_creacion
    ) VALUES (
        v_empresa_id,
        v_sucursal_id,
        v_rol_id,
        'Administrador',
        'Sistema',
        '1234567',
        'admin@sistema.com',
        '(0981) 123-456',
        'admin',
        v_password,
        true,
        CURRENT_TIMESTAMP
    );

    RAISE NOTICE 'Usuario admin creado exitosamente!';
    RAISE NOTICE 'Usuario: admin';
    RAISE NOTICE 'Contraseña: Admin123';
    RAISE NOTICE 'Empresa ID: %', v_empresa_id;
    RAISE NOTICE 'Sucursal ID: %', v_sucursal_id;
END $$;
