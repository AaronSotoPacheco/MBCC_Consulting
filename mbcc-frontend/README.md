# MBCC Control de Calidad

## Desarrollo local

1. Inicia Laravel desde `mbcc-backend` con `php artisan serve`.
2. Inicia Vite desde `mbcc-frontend` con `npm run dev`.
3. Abre `http://127.0.0.1:5173`.

Vite redirige `/api` a `http://127.0.0.1:8000`; `VITE_API_URL` permite cambiar esa URL. Los endpoints QC usan tokens Bearer de Sanctum. La app solo usa respuestas de ejemplo cuando `VITE_USE_MOCK=true`; déjalo sin definir para trabajar con Laravel.

En una base de desarrollo recién creada, el seeder define `EMP-0001` / `password`. No uses esas credenciales en producción. Las órdenes y los defectos se leen de los catálogos existentes en Laravel.

## Niveles de uso

| Sección | Inspector | Supervisor | Admin |
|---|:-:|:-:|:-:|
| Órdenes Activas, Estación de trabajo, Mi Turno | ✔ | ✔ | ✔ |
| Crear, editar, pausar y cerrar órdenes | — | ✔ | ✔ |
| Reabrir una orden cerrada | — | — | ✔ |
| Reportes | — | ✔ | ✔ |
| Configuración → Instrucciones de trabajo | — | ✔ | ✔ |
| Configuración → Usuarios, Clientes y partes, Defectos | — | — | ✔ |

El frontend solo decide qué se muestra (`src/access.js`); la seguridad real está en Laravel (`EnsureRole` en `routes/api.php`).

## Órdenes y Mi Turno

- En Órdenes Activas, supervisor y administrador ven "Nueva orden" y, en cada tarjeta, Pausar/Reanudar, Cerrar (con confirmación) y Editar (meta y horario). Los filtros "Cerradas" e "Incluir cerradas" solo aparecen para ellos.
- "Iniciar inspección" queda deshabilitado en órdenes pausadas o cerradas (el backend también las rechaza).
- Mi Turno muestra lo inspeccionado en una fecha. Supervisor y administrador pueden cambiar a la vista "Equipo".

## Configuración

Las instrucciones de trabajo se suben como PDF (máx. 20 MB) y quedan en borrador; al aprobarlas pasan a vigentes y la vigente anterior de esa parte queda obsoleta. La aprobación (quién y cuándo) la registra Laravel. El PDF se guarda en `storage/app/private/wi` y se abre con el botón "Ver PDF". Los registros de ejemplo del seeder no traen PDF: súbelo de nuevo o colócalo en esa ruta.

Usuarios, clientes, partes y defectos son exclusivos del administrador.

## Validación

```bash
npm run lint
npm run build
```
# React + Vite

This template provides a minimal setup to get React working in Vite with HMR and some ESLint rules.

Currently, two official plugins are available:

- [@vitejs/plugin-react](https://github.com/vitejs/vite-plugin-react/blob/main/packages/plugin-react) uses [Oxc](https://oxc.rs)
- [@vitejs/plugin-react-swc](https://github.com/vitejs/vite-plugin-react/blob/main/packages/plugin-react-swc) uses [SWC](https://swc.rs/)

## React Compiler

The React Compiler is not enabled on this template because of its impact on dev & build performances. To add it, see [this documentation](https://react.dev/learn/react-compiler/installation).

## Expanding the ESLint configuration

If you are developing a production application, we recommend using TypeScript with type-aware lint rules enabled. Check out the [TS template](https://github.com/vitejs/vite/tree/main/packages/create-vite/template-react-ts) for information on how to integrate TypeScript and [`typescript-eslint`](https://typescript-eslint.io) in your project.
