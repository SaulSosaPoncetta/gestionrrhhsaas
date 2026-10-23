<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Portal de autogestión: el usuario tiene que tener un legajo activo vinculado.
 * Deja el empleado disponible en $request->attributes('miEmpleado') y en las vistas como $miEmpleado.
 */
class PortalEmpleado
{
    public function handle(Request $request, Closure $next): Response
    {
        $user     = $request->user();
        $empleado = $user?->empleado;

        if (! $empleado || ! $empleado->activo) {
            if ($user?->esUsuarioPortal()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors(['email' => 'Tu usuario no tiene un legajo activo. Consultá con Recursos Humanos.']);
            }

            return redirect()->route('dashboard')->with('error', 'Tu usuario no tiene un legajo vinculado para usar el portal.');
        }

        $request->attributes->set('miEmpleado', $empleado);
        view()->share('miEmpleado', $empleado);

        return $next($request);
    }
}
