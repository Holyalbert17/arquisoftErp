# Instrucciones para Agregar Campos de Tema Oscuro/Claro

## Descripción
Este script agrega el campo `tema_oscuro` a las tablas `empresas`, `sucursales` y `usuarios` para permitir configurar el tema de la interfaz a nivel de empresa, sucursal o usuario.

## Prioridad de Configuración
El sistema aplica el tema con la siguiente prioridad:
1. **Usuario** - Si el usuario tiene `tema_oscuro` configurado, se usa ese valor
2. **Sucursal** - Si no hay configuración de usuario, se usa la de la sucursal
3. **Empresa** - Si no hay configuración de sucursal, se usa la de la empresa
4. **Por defecto** - Si no hay ninguna configuración, se usa modo claro

## Ejecución del Script

### Opción 1: Desde psql (PostgreSQL)
```bash
psql -U tu_usuario -d tu_base_de_datos -f database/add_tema_oscuro_fields.sql
```

### Opción 2: Desde pgAdmin
1. Abre pgAdmin
2. Conecta a tu base de datos
3. Abre la herramienta "Query Tool"
4. Abre el archivo `database/add_tema_oscuro_fields.sql`
5. Ejecuta el script (F5 o botón "Execute")

### Opción 3: Desde línea de comandos de PostgreSQL
```sql
\i database/add_tema_oscuro_fields.sql
```

## Verificación
Después de ejecutar el script, verifica que los campos se hayan creado correctamente:

```sql
-- Verificar campos en empresas
SELECT column_name, data_type, column_default 
FROM information_schema.columns 
WHERE table_name = 'empresas' AND column_name = 'tema_oscuro';

-- Verificar campos en sucursales
SELECT column_name, data_type, column_default 
FROM information_schema.columns 
WHERE table_name = 'sucursales' AND column_name = 'tema_oscuro';

-- Verificar campos en usuarios
SELECT column_name, data_type, column_default 
FROM information_schema.columns 
WHERE table_name = 'usuarios' AND column_name = 'tema_oscuro';
```

## Configuración de Tema

### Para una Empresa
```sql
UPDATE empresas SET tema_oscuro = true WHERE id = 1;  -- Modo oscuro
UPDATE empresas SET tema_oscuro = false WHERE id = 1; -- Modo claro
```

### Para una Sucursal
```sql
UPDATE sucursales SET tema_oscuro = true WHERE id = 1;  -- Modo oscuro
UPDATE sucursales SET tema_oscuro = false WHERE id = 1; -- Modo claro
UPDATE sucursales SET tema_oscuro = NULL WHERE id = 1;  -- Heredar de empresa
```

### Para un Usuario
```sql
UPDATE usuarios SET tema_oscuro = true WHERE id = 1;  -- Modo oscuro
UPDATE usuarios SET tema_oscuro = false WHERE id = 1; -- Modo claro
UPDATE usuarios SET tema_oscuro = NULL WHERE id = 1;  -- Heredar de sucursal/empresa
```

## Notas
- El campo `tema_oscuro` en usuarios puede ser `NULL` para heredar la configuración
- El campo `tema_oscuro` en sucursales puede ser `NULL` para heredar de la empresa
- El campo `tema_oscuro` en empresas tiene valor por defecto `false` (modo claro)
- Los usuarios también pueden tener el tema configurado en el campo JSON `preferencias` con la clave `tema_oscuro`
