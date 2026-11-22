<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>
## MBCC Control de Calidad API

El frontend QC usa los endpoints de este backend y conserva su esquema actual: las órdenes se leen de `sort_orders`, las inspecciones se guardan en `serial_records` y sus defectos en `serial_defect_logs`. El login valida `employee_number` y `password_hash` y emite tokens de Sanctum; no ejecutes la migración del backend adjunto, porque corresponde a otro esquema.

```bash
php artisan migrate
php artisan serve
```

La migración QC agrega únicamente el campo nullable `shift` a `serial_records`. Las rutas de `ordenes`, `defectos`, `inspecciones` y `reportes` requieren `Authorization: Bearer <token>`; `POST /api/login` es público y `POST /api/logout` revoca el token actual. En una base nueva, `php artisan db:seed` crea datos de desarrollo una sola vez.

Las pruebas de integración se ejecutan con:

```bash
php artisan test
```

### Niveles de uso y endpoints

Todo requiere `Authorization: Bearer <token>` salvo `POST /api/login`. Los niveles salen de `roles.name` (`admin`, `supervisor`, `inspector`) y se validan con el middleware `EnsureRole` en `routes/api.php`.

| Endpoint | Niveles |
|---|---|
| `GET /api/ordenes`, `GET/POST /api/ordenes/{id}/inspecciones`, `GET /api/defectos`, `GET /api/turno`, `/api/trazabilidad…`, `POST /api/me/password` | todos (`?cerradas=1` y `alcance=equipo` solo supervisor/admin) |
| `GET /api/reportes/resumen`, `GET /api/reportes/datos.csv`, `GET /api/alertas` | supervisor, admin |
| `GET /api/auditoria` (bitácora) | admin |
| `GET /api/salud`, `POST /api/errores-cliente` | públicos (monitoreo; sin datos sensibles) |
| `GET /api/ordenes/opciones`, `POST /api/ordenes`, `PATCH /api/ordenes/{id}`, `PATCH /api/ordenes/{id}/estado` | supervisor, admin (reabrir una cerrada: solo admin) |
| `GET/POST/PATCH/DELETE /api/work-instructions`, `POST …/{id}/aprobar`, `POST …/{id}/obsoleta`, `GET …/{id}/archivo`, `GET /api/part-numbers` | supervisor, admin |
| `/api/users`, `/api/roles`, `/api/clients`, `/api/part-numbers` (alta y edición), `/api/defect-catalog` | admin |

Antes de esta versión `users`, `clients`, `sort-orders`, etc. estaban expuestos sin autenticación; ahora exigen token y nivel. Las tablas internas (`sort-orders`, `serial-records`, `serial-defect-logs`, `rework-logs`) ya no se publican como recursos.

Recomendado en tu `.env` (México): `APP_TIMEZONE=America/Mexico_City`, para que "hoy" en Mi Turno y Reportes coincida con tu horario. Si cambias el nivel, desactivas a un usuario o cambias su contraseña, sus sesiones abiertas se cierran.

### Comparación de imágenes con IA (Estación)

El inspector toma o elige una foto de la pieza y el backend la compara con las fotos de referencia OK / NG de la
instrucción de trabajo de la orden usando Claude (visión). **Solo sugiere**: la Estación preselecciona OK/NG y el
defecto, y el inspector confirma con PASSED / FAILED. La foto no se guarda.

1. En `.env`: `ANTHROPIC_API_KEY=` (tu clave) y opcionalmente `ANTHROPIC_MODEL=` (por defecto `claude-sonnet-5-5`).
   La clave nunca llega al navegador. Sin clave, la función responde "no configurada".
2. `php artisan migrate` (tabla `wi_reference_images`).
3. Un supervisor/admin sube las fotos en Configuración → Instrucciones de trabajo → **Fotos IA** (hasta 4 OK y 4 NG por
   instrucción; las NG llevan el nombre del defecto). Sin al menos una foto OK no se puede comparar.

| Método | Ruta | Quién |
| --- | --- | --- |
| GET | `/api/ordenes/{orden}/referencias` · `/api/referencias/{id}/imagen` | cualquier usuario con sesión |
| POST | `/api/ordenes/{orden}/comparar` (campo `imagen`, JPG/PNG/WebP ≤ 3 MB, 20/min) | cualquier usuario con sesión |
| GET/POST | `/api/work-instructions/{id}/referencias` | supervisor, admin |
| DELETE | `/api/referencias/{id}` | supervisor, admin |

Cada comparación es una llamada de pago a la API de Anthropic (el frontend reduce la foto a 1600 px antes de enviarla).

### Inicio de sesión con QR único (gafetes)

Cada empleado puede entrar con un QR personal impreso en su gafete (además de número de empleado y contraseña).

- Un administrador lo genera en Configuración → **Credenciales QR** (uno por uno o varios a la vez) e imprime los gafetes.
- El QR contiene un código aleatorio (`MBCC-` + 40 caracteres). En la base solo se guarda su hash SHA-256
  (`users.qr_token_hash`); el código en claro solo se ve al generarlo. Si se pierde un gafete: **Regenerar** o **Revocar**
  (invalida el código y cierra las sesiones abiertas de ese empleado).
- `POST /api/login/qr` (`qr`, `turno` opcional, 10 intentos/min) · admin: `POST /api/users/{id}/qr`, `POST /api/users/qr-masivo`,
  `DELETE /api/users/{id}/qr`.
- Requiere `php artisan migrate`. Los usuarios inactivos no pueden entrar con QR.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

### Producción
Ver `DESPLIEGUE.md` (raíz del proyecto): Docker con MySQL, HTTPS, respaldos diarios y monitoreo. El primer administrador se crea con `php artisan mbcc:crear-admin <número> "<nombre>"`. Variables opcionales: `NG_UMBRAL`, `NG_VENTANA`, `NG_MUESTRA_MINIMA` (alertas de rechazos) y `OFFLINE_MAX_HORAS` (antigüedad máxima de una captura hecha sin conexión).
