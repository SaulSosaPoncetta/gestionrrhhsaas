<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Módulos que trabajan sobre los datos de UNA empresa (estructura, legajos, etc.).
 * El superadmin no pertenece a ninguna empresa, así que no puede operarlos.
 */
class RequiereEmpresa
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->empresa_id) {
            return redirect()->route('dashboard')
                ->with('error', 'Este módulo trabaja con los datos de una empresa. Ingresá con un usuario de la empresa.');
        }

        return $next($request);
    }
}
