<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EmpresaActiva
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && ! $user->activo) {
            return $this->salir($request, 'Tu usuario está deshabilitado.');
        }

        if ($user && ! $user->esSuperadmin()) {
            if (! $user->empresa || ! $user->empresa->activa) {
                return $this->salir($request, 'La empresa no está activa. Contactá al administrador.');
            }
        }

        return $next($request);
    }

    private function salir(Request $request, string $mensaje): Response
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['email' => $mensaje]);
    }
}
