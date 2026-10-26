<?php

use App\Models\Empleado;
use App\Models\Liquidacion;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;

/**
 * Smoke test: con los datos de demostración cargados, todas las pantallas abren sin error 500.
 * Es la red de seguridad para vistas Blade rotas, relaciones nulas y consultas inválidas.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-10-26 10:00:00');
    $this->seed(DatabaseSeeder::class);

    $this->admin      = User::where('email', 'admin@demo.test')->firstOrFail();
    $this->superadmin = User::where('email', 'superadmin@rrhh.test')->firstOrFail();
});

afterEach(fn () => Carbon::setTestNow());

it('el seeder de demo carga datos consistentes', function () {
    expect(Empleado::withoutGlobalScopes()->count())->toBeGreaterThan(0)
        ->and(User::where('email', 'pereyra@demo.test')->value('rol'))->toBe(User::ROL_SUPERVISOR)
        ->and(User::where('email', 'rodriguez@demo.test')->value('rol'))->toBe(User::ROL_EMPLEADO);
});

it('el admin de RRHH abre todas las pantallas de listado y alta', function (string $url) {
    $this->actingAs($this->admin)->get($url)->assertOk();
})->with([
    '/dashboard', '/usuarios', '/usuarios/create',
    '/areas', '/areas/create', '/puestos', '/puestos/create', '/convenios', '/convenios/create',
    '/empleados', '/empleados/create', '/empleados?estado=bajas', '/empleados?estado=todos&buscar=a',
    '/asistencia/parte', '/asistencia/resumen', '/fichadas', '/fichadas/importar',
    '/horarios', '/horarios/create', '/feriados',
    '/licencias', '/licencias/create', '/licencias/calendario', '/vacaciones',
    '/tipos-licencia', '/tipos-licencia/create',
    '/conceptos', '/conceptos/create', '/liquidaciones',
]);

it('el admin abre el detalle y la edición de cada legajo', function () {
    $this->actingAs($this->admin);

    foreach (Empleado::all() as $e) {
        $this->get("/empleados/{$e->id}")->assertOk();
        $this->get("/empleados/{$e->id}/edit")->assertOk();
        $this->get("/asistencia/empleados/{$e->id}")->assertOk();
    }
});

it('el admin abre cada liquidación, sus recibos y la exportación', function () {
    $this->actingAs($this->admin);

    foreach (Liquidacion::with('recibos')->get() as $l) {
        $this->get("/liquidaciones/{$l->id}")->assertOk();
        $this->get("/liquidaciones/{$l->id}/imprimir")->assertOk();
        $this->get("/liquidaciones/{$l->id}/exportar")->assertOk();
        if ($r = $l->recibos->first()) {
            $this->get("/liquidaciones/{$l->id}/recibos/{$r->id}")->assertOk();
        }
    }
});

it('el resumen de asistencia se exporta', function () {
    $this->actingAs($this->admin)->get('/asistencia/resumen/exportar')->assertOk();
});

it('el superadmin abre sus pantallas', function (string $url) {
    $this->actingAs($this->superadmin)->get($url)->assertOk();
})->with(['/dashboard', '/empresas', '/empresas/create', '/usuarios', '/usuarios/create']);

it('supervisor y empleado demo abren su portal', function (string $email, array $urls) {
    $usuario = User::where('email', $email)->firstOrFail();

    foreach ($urls as $url) {
        $this->actingAs($usuario)->get($url)->assertOk();
    }
})->with([
    'supervisor' => ['pereyra@demo.test', ['/mi', '/mi/equipo', '/mi/recibos', '/mi/licencias', '/mi/asistencia', '/mi/datos']],
    'empleado'   => ['rodriguez@demo.test', ['/mi', '/mi/recibos', '/mi/licencias', '/mi/licencias/nueva', '/mi/asistencia', '/mi/datos']],
]);

it('el dashboard de un usuario de portal redirige o abre sin error', function () {
    $usuario = User::where('email', 'rodriguez@demo.test')->firstOrFail();

    expect($this->actingAs($usuario)->get('/dashboard')->status())->toBeIn([200, 302]);
});
