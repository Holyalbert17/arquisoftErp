-- =====================================================
-- AGREGAR COLUMNA PREFERENCIAS A TABLA USUARIOS
-- =====================================================

-- Agregar columna preferencias si no existe
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'usuarios' 
        AND column_name = 'preferencias'
    ) THEN
        ALTER TABLE usuarios ADD COLUMN preferencias JSONB;
        COMMENT ON COLUMN usuarios.preferencias IS 'Preferencias del usuario en formato JSON';
    END IF;
END $$;
