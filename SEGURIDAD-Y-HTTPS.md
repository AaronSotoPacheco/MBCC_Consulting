# Seguridad y HTTPS en producción

## Por qué HTTPS es obligatorio
- La cámara del teléfono (escáner QR y fotos de evidencia NG) **solo funciona en páginas HTTPS** (o `localhost`).
- Sin HTTPS, el token de sesión y las contraseñas viajan en claro por la red de la planta.

## Esquema recomendado
Un servidor (o contenedor) con un proxy inverso que termina HTTPS y reparte:

- `https://calidad.tuempresa.com/`      → archivos de `mbcc-frontend/dist` (`npm run build`)
- `https://calidad.tuempresa.com/api/`  → Laravel (`public/index.php`, PHP-FPM)

Con Caddy el certificado es automático (Let's Encrypt):

```
calidad.tuempresa.com {
    root * /var/www/mbcc-frontend/dist
    encode gzip
    handle /api/* {
        reverse_proxy 127.0.0.1:8000     # o php_fastcgi unix//run/php/php-fpm.sock con root en mbcc-backend/public
    }
    handle {
        try_files {path} /index.html
        file_server
    }
    header {
        X-Content-Type-Options nosniff
        X-Frame-Options DENY
        Referrer-Policy no-referrer
        Permissions-Policy "camera=(self), microphone=(), geolocation=()"
        Strict-Transport-Security "max-age=31536000; includeSubDomains"
    }
}
```

(La API ya envía estas cabeceras por sí sola; las del bloque `header` protegen los archivos del frontend.)

## `.env` del backend
```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://calidad.tuempresa.com
APP_TIMEZONE=America/Mexico_City
TRUSTED_PROXIES=*            # o la IP del proxy; hace que Laravel sepa que la petición original fue HTTPS
SESSION_SECURE_COOKIE=true
```
Si usas `php artisan config:cache`, define `TRUSTED_PROXIES` como variable real del servidor (no solo en `.env`).

## Qué se audita (Configuración → Bitácora, solo administrador)
Aprobación/obsolescencia/borrado/edición de instrucciones · creación, edición, pausa, cierre y reapertura de órdenes ·
generación y revocación de QR · alta y cambios de usuarios · inicios de sesión y accesos fallidos · cambios de contraseña.
Nunca se guardan contraseñas ni códigos QR.

## Reportes
`/reportes` y la exportación CSV son solo para supervisor y administrador (también en el backend).
