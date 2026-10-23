<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'rol'              => \App\Http\Middleware\RolMiddleware::class,
            'empresa.activa'   => \App\Http\Middleware\EmpresaActiva::class,
            'requiere.empresa' => \App\Http\Middleware\RequiereEmpresa::class,
            'portal'           => \App\Http\Middleware\PortalEmpleado::class,
            'password.cambio'  => \App\Http\Middleware\CambioPasswordObligatorio::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
