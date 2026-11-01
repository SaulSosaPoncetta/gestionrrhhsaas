<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Paginación con estilos de Bootstrap 5
        Paginator::useBootstrapFive();

        // Intentos de login por minuto y por IP (protección contra fuerza bruta).
        // Se configura con LOGIN_INTENTOS_POR_MINUTO: 10 en producción; más alto solo para pruebas de carga.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(
            max(1, (int) config('auth.login_intentos_por_minuto', 10))
        )->by($request->ip()));
    }
}
