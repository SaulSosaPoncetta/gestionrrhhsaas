<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uso en rutas: ->middleware('rol:admin_rrhh,supervisor')
 * El superadmin siempre pasa.
 */
class RolMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->esSuperadmin() || $user->tieneRol(...$roles)) {
            return $next($request);
        }

        abort(403, 'No tenés permisos para acceder a esta sección.');
    }
}
