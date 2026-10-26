<?php

use App\Models\Feriado;
use App\Models\Fichada;
use App\Models\Licencia;
use App\Services\Asistencia\CalculadorAsistencia;
use Carbon\Carbon;
use Database\Factories\HorarioFactory;
use Database\Factories\TipoLicenciaFactory;

/*
 * Semana de referencia (horario L a V 09:00 a 18:00, tolerancia 10', extra desde 30'):
 *   lun 19/10 · mar 20 · mié 21 · jue 22 · vie 23 · sáb 24 · dom 25.   "Hoy" = lun 26/10/2026 12:00.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-10-26 12:00:00');

    $this->empresa  = crearEmpresa();
    $this->horario  = HorarioFactory::new()->create(['empresa_id' => $this->empresa->id]);
    $this->empleado = crearEmpleado($this->empresa, ['horario_id' => $this->horario->id, 'fecha_ingreso' => '2024-01-01']);
    $this->calc     = app(CalculadorAsistencia::class);

    $this->fichar = function (string $fechaHora, string $tipo, $empleado = null) {
        Fichada::withoutGlobalScopes()->create([
            'empresa_id'  => $this->empresa->id,
            'empleado_id' => ($empleado ?? $this->empleado)->id,
            'fecha_hora'  => $fechaHora,
            'tipo'        => $tipo,
        ]);
    };
    $this->jornada = function (string $fecha, string $entrada, string $salida) {
        ($this->fichar)("{$fecha} {$entrada}:00", 'entrada');
        ($this->fichar)("{$fecha} {$salida}:00", 'salida');
    };
    $this->dia = fn (string $fecha) => $this->calc->calcular($this->empleado, Carbon::parse($fecha), Carbon::parse($fecha))[$fecha];
});

afterEach(fn () => Carbon::setTestNow());

it('presente cuando cumple el horario', function () {
    ($this->jornada)('2026-10-19', '08:58', '18:02');

    $d = ($this->dia)('2026-10-19');
    expect($d->estado)->toBe('presente')
        ->and($d->minutosTrabajados)->toBe(544)
        ->and($d->minutosTarde)->toBe(0)
        ->and($d->extra50)->toBe(0);   // 4' de más no llegan al mínimo de 30'
});

it('dentro de la tolerancia no es tarde; pasada la tolerancia cuenta desde la hora programada', function () {
    ($this->jornada)('2026-10-19', '09:10', '18:00');
    ($this->jornada)('2026-10-20', '09:25', '18:00');

    expect(($this->dia)('2026-10-19')->estado)->toBe('presente');

    $tarde = ($this->dia)('2026-10-20');
    expect($tarde->estado)->toBe('tarde')->and($tarde->minutosTarde)->toBe(25);
});

it('registra la salida anticipada', function () {
    ($this->jornada)('2026-10-19', '09:00', '16:30');

    expect(($this->dia)('2026-10-19')->minutosSalidaAnticipada)->toBe(90);
});

it('ausente un día laborable sin fichadas; franco el domingo', function () {
    expect(($this->dia)('2026-10-21')->estado)->toBe('ausente')
        ->and(($this->dia)('2026-10-25')->estado)->toBe('franco');
});

it('hoy, antes de la hora de entrada + tolerancia, queda pendiente', function () {
    Carbon::setTestNow('2026-10-26 09:05:00');

    expect(($this->dia)('2026-10-26')->estado)->toBe('pendiente');
});

it('un feriado sin fichadas no es ausencia; trabajarlo es todo al 100 %', function () {
    Feriado::withoutGlobalScopes()->create(['empresa_id' => $this->empresa->id, 'fecha' => '2026-10-21', 'descripcion' => 'Feriado de prueba', 'tipo' => 'inamovible']);
    Feriado::withoutGlobalScopes()->create(['empresa_id' => $this->empresa->id, 'fecha' => '2026-10-22', 'descripcion' => 'Otro feriado', 'tipo' => 'inamovible']);
    ($this->jornada)('2026-10-22', '09:00', '13:00');

    expect(($this->dia)('2026-10-21')->estado)->toBe('feriado');

    $trabajado = ($this->dia)('2026-10-22');
    expect($trabajado->estado)->toBe('feriado_trabajado')->and($trabajado->extra100)->toBe(240);
});

it('una licencia aprobada cubre el día', function () {
    $tipo = TipoLicenciaFactory::new()->create(['empresa_id' => $this->empresa->id]);
    Licencia::withoutGlobalScopes()->create([
        'empresa_id' => $this->empresa->id, 'empleado_id' => $this->empleado->id, 'tipo_licencia_id' => $tipo->id,
        'fecha_desde' => '2026-10-21', 'fecha_hasta' => '2026-10-21', 'dias' => 1, 'estado' => 'aprobada',
    ]);

    expect(($this->dia)('2026-10-21')->estado)->toBe('licencia');
});

it('una licencia pendiente no cubre el día (sigue siendo ausencia)', function () {
    $tipo = TipoLicenciaFactory::new()->create(['empresa_id' => $this->empresa->id]);
    Licencia::withoutGlobalScopes()->create([
        'empresa_id' => $this->empresa->id, 'empleado_id' => $this->empleado->id, 'tipo_licencia_id' => $tipo->id,
        'fecha_desde' => '2026-10-21', 'fecha_hasta' => '2026-10-21', 'dias' => 1, 'estado' => 'pendiente',
    ]);

    expect(($this->dia)('2026-10-21')->estado)->toBe('ausente');
});

it('horas extra al 50 % en día hábil desde el mínimo configurado', function () {
    ($this->jornada)('2026-10-19', '09:00', '18:20');   // 20' → no llega
    ($this->jornada)('2026-10-20', '09:00', '19:30');   // 90' → sí

    expect(($this->dia)('2026-10-19')->extra50)->toBe(0)
        ->and(($this->dia)('2026-10-20')->extra50)->toBe(90);
});

it('el sábado en franco: al 100 % lo trabajado después de las 13 hs', function () {
    ($this->jornada)('2026-10-24', '10:00', '15:00');

    $d = ($this->dia)('2026-10-24');
    expect($d->estado)->toBe('franco_trabajado')
        ->and($d->extra100)->toBe(120)
        ->and($d->extra50)->toBe(180);
});

it('una entrada sin salida en un día pasado queda incompleta', function () {
    ($this->fichar)('2026-10-19 09:00:00', 'entrada');

    expect(($this->dia)('2026-10-19')->estado)->toBe('incompleto');
});

it('el turno nocturno queda en el día en que empezó', function () {
    $noche = HorarioFactory::new()->create(['empresa_id' => $this->empresa->id, 'dias' => [
        1 => ['entrada' => '22:00', 'salida' => '06:00'], 2 => ['entrada' => '22:00', 'salida' => '06:00'],
    ]]);
    $sereno = crearEmpleado($this->empresa, ['horario_id' => $noche->id, 'fecha_ingreso' => '2024-01-01']);
    ($this->fichar)('2026-10-19 22:00:00', 'entrada', $sereno);
    ($this->fichar)('2026-10-20 06:00:00', 'salida', $sereno);

    $dias = $this->calc->calcular($sereno, Carbon::parse('2026-10-19'), Carbon::parse('2026-10-20'));

    expect($dias['2026-10-19']->estado)->toBe('presente')
        ->and($dias['2026-10-19']->minutosTrabajados)->toBe(480)
        ->and($dias['2026-10-20']->jornadas)->toBeEmpty();
});

it('antes del ingreso del empleado el día no está vigente', function () {
    $nuevo = crearEmpleado($this->empresa, ['horario_id' => $this->horario->id, 'fecha_ingreso' => '2026-10-22']);

    $dias = $this->calc->calcular($nuevo, Carbon::parse('2026-10-21'), Carbon::parse('2026-10-22'));
    expect($dias['2026-10-21']->estado)->toBe('fuera');
});

it('totaliza la semana para el resumen y la liquidación', function () {
    ($this->jornada)('2026-10-19', '09:00', '18:00');
    ($this->jornada)('2026-10-20', '09:30', '18:00');
    ($this->jornada)('2026-10-22', '09:00', '19:00');
    ($this->jornada)('2026-10-23', '09:00', '18:00');

    $t = CalculadorAsistencia::totales($this->calc->calcular($this->empleado, Carbon::parse('2026-10-19'), Carbon::parse('2026-10-25')));

    expect($t)
        ->programados->toBe(5)
        ->asistencias->toBe(4)
        ->ausencias->toBe(1)
        ->tardanzas->toBe(1)
        ->minutos_tarde->toBe(30)
        ->extra50->toBe(60);
});
