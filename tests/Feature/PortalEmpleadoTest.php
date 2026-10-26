<?php

use App\Models\Fichada;
use App\Models\User;
use Carbon\Carbon;
use Database\Factories\HorarioFactory;

beforeEach(function () {
    Carbon::setTestNow('2026-10-26 08:55:00');

    $this->empresa = crearEmpresa();
    $horario       = HorarioFactory::new()->create(['empresa_id' => $this->empresa->id]);
    [$this->usuario, $this->empleado] = crearUsuarioPortal($this->empresa, User::ROL_EMPLEADO, [
        'horario_id' => $horario->id, 'permite_fichaje_web' => true, 'sueldo_basico' => 1000000,
    ]);
});

afterEach(fn () => Carbon::setTestNow());

it('muestra las pantallas del portal', function (string $url) {
    $this->actingAs($this->usuario)->get($url)->assertOk();
})->with(['/mi', '/mi/recibos', '/mi/licencias', '/mi/licencias/nueva', '/mi/asistencia', '/mi/asistencia?mes=2026-09', '/mi/datos', '/password']);

it('tolera un mes inválido en la asistencia', function () {
    $this->actingAs($this->usuario)->get('/mi/asistencia?mes=2026-13')->assertOk();
    $this->actingAs($this->usuario)->get('/mi/asistencia?mes=<script>')->assertOk()->assertDontSee('<script>', false);
});

it('ficha entrada y después salida desde la web', function () {
    $this->actingAs($this->usuario)->post('/mi/fichar')->assertSessionHas('ok', 'Entrada registrada a las 08:55.');

    Carbon::setTestNow('2026-10-26 18:01:00');
    $this->actingAs($this->usuario)->post('/mi/fichar')->assertSessionHas('ok');

    expect(Fichada::orderBy('fecha_hora')->get())
        ->toHaveCount(2)
        ->sequence(
            fn ($f) => $f->tipo->toBe('entrada')->origen->toBe('web')->empresa_id->toBe($this->empresa->id),
            fn ($f) => $f->tipo->toBe('salida'),
        );
});

it('no deja fichar dos veces en el mismo minuto', function () {
    $this->actingAs($this->usuario)->post('/mi/fichar');
    $this->actingAs($this->usuario)->post('/mi/fichar')->assertSessionHas('error');

    expect(Fichada::count())->toBe(1);
});

it('sin fichaje web habilitado devuelve 403', function () {
    $this->empleado->update(['permite_fichaje_web' => false]);

    $this->actingAs($this->usuario)->post('/mi/fichar')->assertForbidden();
});

it('el empleado solo puede cambiar sus datos de contacto', function () {
    $this->actingAs($this->usuario)->put('/mi/datos', [
        'telefono'      => '11 5555-0000',
        'domicilio'     => 'Calle Falsa 123',
        // Campos que NO debería poder tocar
        'sueldo_basico' => 99999999,
        'legajo'        => 1,
        'fecha_ingreso' => '1990-01-01',
        'user_id'       => 999,
        'permite_fichaje_web' => false,
    ])->assertSessionHas('ok');

    expect($this->empleado->fresh())
        ->telefono->toBe('11 5555-0000')
        ->domicilio->toBe('Calle Falsa 123')
        ->sueldo_basico->toBe('1000000.00')
        ->fecha_ingreso->toDateString()->not->toBe('1990-01-01')
        ->user_id->toBe($this->usuario->id)
        ->permite_fichaje_web->toBeTrue();
});

it('calcula días y saldo para el formulario de licencias (JSON)', function () {
    $tipo = \Database\Factories\TipoLicenciaFactory::new()->vacaciones()->create(['empresa_id' => $this->empresa->id]);

    $this->actingAs($this->usuario)
        ->getJson("/mi/licencias/calcular-dias?tipo_id={$tipo->id}&desde=2026-12-07&hasta=2026-12-13&periodo=2026")
        ->assertOk()
        ->assertJson(['dias' => 7, 'unidad' => 'corridos', 'disponible' => 14, 'avisos' => []]);
});

it('el cálculo de días no expone tipos de licencia de otra empresa', function () {
    $ajeno = \Database\Factories\TipoLicenciaFactory::new()->create(['empresa_id' => crearEmpresa()->id]);

    $this->actingAs($this->usuario)
        ->getJson("/mi/licencias/calcular-dias?tipo_id={$ajeno->id}&desde=2026-12-07&hasta=2026-12-13")
        ->assertNotFound();
});
