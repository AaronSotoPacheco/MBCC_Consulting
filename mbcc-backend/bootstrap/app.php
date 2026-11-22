<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [\Illuminate\Http\Middleware\HandleCors::class]);
        // Global (no del grupo api) para que también salgan en respuestas 401/403/422
        $middleware->append(\App\Http\Middleware\CabecerasSeguridad::class);
        // Detrás de un proxy con HTTPS (nginx, Caddy…): TRUSTED_PROXIES=* o la IP del proxy
        $middleware->trustProxies(at: env('TRUSTED_PROXIES') ?: null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
