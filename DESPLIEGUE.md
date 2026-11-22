# Puesta en producción — MBCC Control de Calidad

Un solo servidor con Docker levanta todo: **MySQL**, el backend **Laravel**, el frontend con **HTTPS automático** (Caddy) y un servicio de **respaldos diarios**. Complementa a `SEGURIDAD-Y-HTTPS.md`.

## 0. Qué necesitas
- Un equipo que quede encendido: servidor Linux (recomendado) o una PC con Windows + **Docker Desktop**.
- Mínimo 2 GB de RAM y 10 GB de disco libres (más si guardas muchas fotos de evidencia).
- Para que la cámara del teléfono funcione hace falta HTTPS (ver paso 6).

## 1. Copia el proyecto al servidor
Clona el repositorio (o copia la carpeta `MBCC_Consulting`) y entra a ella. Todos los comandos de esta guía se corren ahí, donde está `docker-compose.yml`.

## 2. Crea el archivo de configuración
```
copy .env.docker.example .env        (Windows)
cp .env.docker.example .env          (Linux)
```
Abre `.env` y llena: `SITIO`, `DB_ROOT_PASSWORD`, `DB_PASSWORD` (contraseñas largas y distintas) y, si la usarás, `ANTHROPIC_API_KEY`.

## 3. Genera la llave de la aplicación (una sola vez)
```
docker run --rm php:8.3-cli php -r "echo 'base64:'.base64_encode(random_bytes(32));"
```
Pega el resultado completo en `APP_KEY=` dentro de `.env`. **No la cambies después**: invalidaría todas las sesiones abiertas. Guarda una copia de `.env` en un lugar seguro (sin ella ni las contraseñas no se puede restaurar).

## 4. Arranca
```
docker compose up -d --build
```
La primera vez tarda varios minutos (descarga imágenes y compila el frontend). Al iniciar, el backend espera a MySQL y crea las tablas solo (`migrate`). Revisa con `docker compose ps` que todo esté "running"/"healthy".

## 5. Crea el primer administrador
No se cargan los usuarios de demostración (tienen contraseña conocida). Crea el tuyo:
```
docker compose exec app php artisan mbcc:crear-admin 9001 "Tu Nombre"
```
Te pedirá la contraseña (mínimo 8 caracteres). Entra a la página con ese número y desde **Configuración** da de alta clientes, números de parte, catálogo de defectos, usuarios y sus QR.

Si alguien se queda sin acceso: `docker compose exec app php artisan mbcc:crear-admin 9001 "Tu Nombre" --restablecer`.

## 6. HTTPS (obligatorio para la cámara)
- **Con dominio público** (`calidad.tuempresa.com` apuntando a la IP del servidor, puertos 80 y 443 abiertos): no hagas nada, Caddy obtiene y renueva el certificado solo.
- **Solo red interna** (IP o nombre local): en `deploy/Caddyfile` quita el `#` de `# tls internal` y reinicia (`docker compose restart web`). Caddy usa un certificado propio; para que los teléfonos no muestren advertencia hay que instalar su certificado raíz en cada uno (`docker compose cp web:/data/caddy/pki/authorities/local/root.crt .`). Si prefieres evitarlo, un dominio público (aunque apunte a una IP privada, con certificado por DNS) es más cómodo.

## 7. Respaldos (ya incluidos)
El servicio `respaldo` guarda cada día (a `HORA_RESPALDO`) en la carpeta **`respaldos/`** del proyecto:
- `bd-AAAAMMDD-HHMMSS.sql.gz` → toda la base de datos
- `archivos-AAAAMMDD-HHMMSS.tar.gz` → PDF de instrucciones, fotos de referencia y evidencias NG
- `ultimo-respaldo.txt` → resultado del último (revísalo de vez en cuando)

Se conservan `RETENCION_DIAS` días (siempre quedan al menos los 3 más recientes). También respalda al arrancar.

**Importante:** esa carpeta está en el mismo equipo. Copia `respaldos/` a otro disco, otra PC o la nube (OneDrive, rclone…) con una tarea programada; si se daña el servidor y los respaldos estaban solo ahí, se pierden con él.

Respaldo manual inmediato: `docker compose exec respaldo respaldar.sh`

**Restaurar** (reemplaza los datos actuales):
```
docker compose stop app web
docker compose run --rm --entrypoint restaurar.sh -e CONFIRMAR=SI respaldo /respaldos/bd-AAAAMMDD-HHMMSS.sql.gz /respaldos/archivos-AAAAMMDD-HHMMSS.tar.gz
docker compose start app web
```
(El segundo archivo es opcional: sin él solo se restaura la base de datos.) **Haz una prueba de restauración en otro equipo antes de necesitarla de verdad.**

## 8. Monitoreo de errores
- **Estado del sistema:** `https://TU-SITIO/api/salud` responde `{"estado":"ok"}` (503 si falla la base de datos o el almacenamiento). Conéctalo a un monitor externo gratuito (UptimeRobot, Better Stack…) con aviso por correo.
- **Errores del servidor:** `docker compose exec app ls storage/logs` (archivos `laravel-AAAA-MM-DD.log`) o `docker compose logs -f app`. Nivel y retención con `LOG_LEVEL` y `LOG_DAILY_DAYS`.
- **Errores en los navegadores** (pantallas que fallan en la planta): llegan solos a `storage/logs/frontend-AAAA-MM-DD.log` con la pantalla, el navegador y el detalle.
- **Opcional, avisos por correo/Slack con agrupación (Sentry):** en tu PC corre `composer require sentry/sentry-laravel` dentro de `mbcc-backend`, agrega `SENTRY_LARAVEL_DSN` a las variables del servicio `app` en `docker-compose.yml`, y en `bootstrap/app.php`, dentro de `withExceptions`, escribe `\Sentry\Laravel\Integration::handles($exceptions);`. Después `docker compose up -d --build`.

## 9. Mantenimiento
| Tarea | Comando |
|---|---|
| Ver estado | `docker compose ps` |
| Ver registros | `docker compose logs -f app` |
| Actualizar a una versión nueva | traer el código nuevo y `docker compose up -d --build` (las migraciones corren solas) |
| Reiniciar | `docker compose restart` |
| Apagar (conserva datos) | `docker compose down` |
| **Borrar TODO, datos incluidos** | `docker compose down -v` — nunca lo uses salvo que quieras empezar de cero |

Todos los servicios tienen `restart: unless-stopped`: vuelven solos tras un reinicio del equipo (en Windows, activa "Start Docker Desktop when you sign in").

## 10. Lista de verificación antes de abrir a la planta
- [ ] `https://TU-SITIO` abre con candado y el teléfono pide permiso de cámara.
- [ ] Entras con tu administrador y creas un usuario de prueba, una orden y una inspección.
- [ ] `/api/salud` responde ok y está en tu monitor.
- [ ] `respaldos/` tiene archivos de hoy y los copias a otro lugar.
- [ ] Probaste restaurar un respaldo.
- [ ] Guardaste una copia de `.env` fuera del servidor.
- [ ] Solo 80 y 443 están abiertos al exterior (MySQL no se publica).
