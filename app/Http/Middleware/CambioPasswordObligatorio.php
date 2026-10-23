<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Si RRHH creó o reseteó el acceso, el usuario tiene que elegir su propia contraseña antes de seguir.
 */
class CambioPasswordObligatorio
{
    private const PERMITIDAS = ['password.editar', 'password.actualizar', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->debe_cambiar_password && ! $request->routeIs(...self::PERMITIDAS)) {
            return redirect()->route('password.editar')
                ->with('aviso', 'Por seguridad, elegí una contraseña nueva antes de continuar.');
        }

        return $next($request);
    }
}
