# Deploy en Render (Gratuito)

## Requisitos previos
- Cuenta en [Render](https://render.com) (gratis)
- Repositorio Git (GitHub, GitLab, o Bitbucket)

---

## Paso 1: Subir código a GitHub

```bash
cd c:\xampp\htdocs\arquisoft_system
git init
git add .
git commit -m "Initial commit - ArquiSoft System"
git remote add origin https://github.com/TU_USUARIO/arquisoft_system.git
git push -u origin main
```

> **IMPORTANTE:** El archivo `.env` está en `.gitignore` y NO se sube. Las credenciales se configuran en Render.

---

## Paso 2: Crear Base de Datos PostgreSQL en Render

1. Ir a [Render Dashboard](https://dashboard.render.com)
2. Click **New** → **PostgreSQL**
3. Configurar:
   - **Name:** `arquisoft-db`
   - **Database:** `arquisoft_system`
   - **User:** `arquisoft_user`
   - **Region:** Oregon (US West)
   - **Plan:** Free
4. Click **Create Database**
5. Esperar a que se cree y copiar los datos de conexión:
   - **External Database URL** (para importar el dump)
   - **Internal Database URL** (para la app)
   - **Hostname**, **Port**, **Database**, **Username**, **Password**

---

## Paso 3: Importar la Base de Datos

Desde tu PC local, ejecutar en PowerShell:

```powershell
$env:PGPASSWORD='TU_PASSWORD_DE_RENDER'
& "D:\PostgreSQL17\bin\psql.exe" -h TU_HOST_EXTERNO_RENDER -U arquisoft_user -d arquisoft_system -f "c:\xampp\htdocs\arquisoft_system\database\schema_full.sql"
```

Reemplazar:
- `TU_PASSWORD_DE_RENDER` → la contraseña que Render generó
- `TU_HOST_EXTERNO_RENDER` → el hostname externo (ej: `dpg-xxxxx-a.oregon-postgres.render.com`)

> **Nota:** El archivo `database/schema_full.sql` contiene el dump completo de tu BD local.

---

## Paso 4: Crear Web Service en Render

1. Ir a [Render Dashboard](https://dashboard.render.com)
2. Click **New** → **Web Service**
3. Conectar tu repositorio de GitHub
4. Configurar:
   - **Name:** `arquisoft-system`
   - **Region:** Oregon (US West) (misma región que la BD)
   - **Runtime:** Docker
   - **Plan:** Free
5. En **Environment Variables**, agregar:

| Variable | Valor |
|---|---|
| `CI_ENVIRONMENT` | `production` |
| `APP_BASEURL` | `https://arquisoft-system.onrender.com` |
| `DB_HOSTNAME` | (Internal hostname de tu BD Render) |
| `DB_DATABASE` | `arquisoft_system` |
| `DB_USERNAME` | `arquisoft_user` |
| `DB_PASSWORD` | (Password generado por Render) |
| `DB_PORT` | `5432` |
| `DATABASE_URL` | (Internal Database URL completa) |
| `PORT` | `10000` |

6. Click **Create Web Service**

---

## Paso 5: Verificar

1. Esperar a que el build termine (puede tardar 5-10 minutos la primera vez)
2. Abrir `https://arquisoft-system.onrender.com`
3. Iniciar sesión con tu usuario admin

---

## Notas importantes

### Free Tier de Render
- El servicio web se **duerme** después de 15 minutos de inactividad
- La primera carga después de dormir tarda ~30 segundos
- La BD PostgreSQL gratuita se **elimina después de 90 días** (hay que recrearla o hacer upgrade)
- 750 horas/mes de ejecución gratuita

### Actualizar el deploy
Cada vez que hagas `git push` al repositorio, Render re-deploya automáticamente.

### Si necesitás actualizar la BD
```powershell
# Exportar dump local actualizado
$env:PGPASSWORD='1'
& "D:\PostgreSQL17\bin\pg_dump.exe" -U postgres -h localhost -d arquisoft_system --no-owner --no-acl -f "database\schema_full.sql"

# Importar en Render
$env:PGPASSWORD='TU_PASSWORD_RENDER'
& "D:\PostgreSQL17\bin\psql.exe" -h HOST_EXTERNO_RENDER -U arquisoft_user -d arquisoft_system -f "database\schema_full.sql"
```

### Archivos creados para el deploy
- `Dockerfile` — Imagen Docker con PHP 8.2 + Apache + extensiones PostgreSQL
- `.dockerignore` — Excluye archivos innecesarios del build
- `.env.example` — Template de variables de entorno para producción
- `.gitignore` — Excluye `.env`, `vendor/`, logs, cache
- `render.yaml` — Blueprint de Render (opcional, para deploy automático)
- `database/schema_full.sql` — Dump completo de la BD local
