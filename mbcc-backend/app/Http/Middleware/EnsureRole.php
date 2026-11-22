<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe una ruta a ciertos niveles de uso (roles.name: admin, supervisor, inspector).
 * Uso: ->middleware(EnsureRole::class . ':supervisor,admin')
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $nivel = $user?->loadMissing('role')->role?->name;

        if (! $nivel || ! in_array($nivel, $roles, true)) {
            return response()->json(['message' => 'No tienes permiso para acceder a esta sección.'], 403);
        }

        return $next($request);
    }
}
