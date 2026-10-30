# MBCC Control de Calidad

## Desarrollo local

1. Inicia Laravel desde `mbcc-backend` con `php artisan serve`.
2. Inicia Vite desde `mbcc-frontend` con `npm run dev`.
3. Abre `http://127.0.0.1:5173`.

Vite redirige `/api` a `http://127.0.0.1:8000`; `VITE_API_URL` permite cambiar esa URL. Los endpoints QC usan tokens Bearer de Sanctum. La app solo usa respuestas de ejemplo cuando `VITE_USE_MOCK=true`; déjalo sin definir para trabajar con Laravel.

En una base de desarrollo recién creada, el seeder define `EMP-0001` / `password`. No uses esas credenciales en producción. Las órdenes y los defectos se leen de los catálogos existentes en Laravel.

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
