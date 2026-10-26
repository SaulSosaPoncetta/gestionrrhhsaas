<?php

use App\Models\Concepto;
use App\Models\Liquidacion;
use App\Models\Recibo;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-10-26 10:00:00');

    $this->empresa = crearEmpresa();
    $this->admin   = crearAdmin($this->empresa);
    Concepto::cargarPredeterminados($this->empresa->id);

    // Fuera de convenio, sin horario (no genera ausencias): $ 1.000.000
    [$this->usuario, $this->completo] = crearUsuarioPortal($this->empresa, User::ROL_EMPLEADO, [
        'apellido' => 'Completo', 'fecha_ingreso' => '2022-01-10', 'sueldo_basico' => 1000000,
    ]);
    $this->medio = crearEmpleado($this->empresa, ['apellido' => 'Medio', 'fecha_ingreso' => '2026-09-16', 'sueldo_basico' => 1000000]);
    $this->baja  = crearEmpleado($this->empresa, ['apellido' => 'Baja', 'fecha_ingreso' => '2020-01-01', 'fecha_egreso' => '2026-08-31', 'motivo_egreso' => 'renuncia']);

    $this->liquidarSeptiembre = function () {
        $this->actingAs($this->admin)->post('/liquidaciones', ['anio' => 2026, 'mes' => 9, 'tipo' => 'mensual'])->assertRedirect();

        return Liquidacion::firstOrFail();
    };
});

afterEach(fn () => Carbon::setTestNow());

it('los conceptos predeterminados se cargan una sola vez', function () {
    expect(Concepto::cargarPredeterminados($this->empresa->id))->toBe(0)
        ->and(Concepto::withoutGlobalScopes()->where('empresa_id', $this->empresa->id)->count())->toBe(count(Concepto::PREDETERMINADOS));
});

it('liquida el mes: completo, proporcional y sin los dados de baja', function () {
    $liq = ($this->liquidarSeptiembre)();

    expect($liq)->cantidad_recibos->toBe(2)->estado->toBe('borrador');

    $completo = Recibo::where('empleado_id', $this->completo->id)->firstOrFail();
    expect($completo)->dias_trabajados->toBe(30)->total_remunerativo->toBe(1000000.0)->neto->toBe(830000.0);

    $medio = Recibo::where('empleado_id', $this->medio->id)->firstOrFail();
    expect($medio)->dias_trabajados->toBe(15)->neto->toBe(415000.0);

    expect(Recibo::where('empleado_id', $this->baja->id)->exists())->toBeFalse()
        ->and($liq->total_neto)->toBe(1245000.0);
});

it('no duplica una liquidación del mismo período', function () {
    ($this->liquidarSeptiembre)();
    $this->actingAs($this->admin)->post('/liquidaciones', ['anio' => 2026, 'mes' => 9, 'tipo' => 'mensual'])->assertSessionHas('error');

    expect(Liquidacion::count())->toBe(1);
});

it('el recibo es una foto: no cambia si después se edita el legajo', function () {
    $liq = ($this->liquidarSeptiembre)();
    $this->completo->update(['apellido' => 'Cambiado', 'sueldo_basico' => 2000000]);

    expect(Recibo::where('empleado_id', $this->completo->id)->first())
        ->apellido_nombre->toContain('Completo')->neto->toBe(830000.0);

    // Recién al recalcular toma los datos nuevos
    $this->actingAs($this->admin)->post("/liquidaciones/{$liq->id}/calcular");
    expect(Recibo::where('empleado_id', $this->completo->id)->first()->neto)->toBe(1660000.0);
});

it('una novedad manual se suma y recalcula', function () {
    $liq    = ($this->liquidarSeptiembre)();
    $premio = Concepto::where('codigo', 160)->firstOrFail();

    $this->actingAs($this->admin)->post("/liquidaciones/{$liq->id}/novedades", [
        'empleado_id' => $this->completo->id, 'concepto_id' => $premio->id, 'importe' => 100000,
    ])->assertSessionHasNoErrors();

    expect(Recibo::where('empleado_id', $this->completo->id)->first())
        ->total_remunerativo->toBe(1100000.0)->neto->toBe(913000.0);
});

it('una novedad de un concepto manual exige importe', function () {
    $liq    = ($this->liquidarSeptiembre)();
    $premio = Concepto::where('codigo', 160)->firstOrFail();

    $this->actingAs($this->admin)->post("/liquidaciones/{$liq->id}/novedades", [
        'empleado_id' => $this->completo->id, 'concepto_id' => $premio->id, 'cantidad' => 1,
    ])->assertSessionHasErrors('importe');
});

it('cerrada no se recalcula, no admite novedades ni se elimina hasta reabrirla', function () {
    $liq = ($this->liquidarSeptiembre)();

    $this->actingAs($this->admin)->patch("/liquidaciones/{$liq->id}/cerrar")->assertSessionHas('ok');
    expect($liq->fresh())->estado->toBe('cerrada')->cerrada_por->toBe($this->admin->id);

    $this->actingAs($this->admin)->post("/liquidaciones/{$liq->id}/calcular")->assertSessionHas('error');
    $this->actingAs($this->admin)->delete("/liquidaciones/{$liq->id}")->assertSessionHas('error');
    $this->actingAs($this->admin)->post("/liquidaciones/{$liq->id}/novedades", [
        'empleado_id' => $this->completo->id, 'concepto_id' => Concepto::where('codigo', 160)->value('id'), 'importe' => 1,
    ])->assertSessionHas('error');

    expect(fn () => app(\App\Services\Liquidacion\MotorLiquidacion::class)->liquidar($liq->fresh()))
        ->toThrow(RuntimeException::class);

    $this->actingAs($this->admin)->patch("/liquidaciones/{$liq->id}/reabrir");
    $this->actingAs($this->admin)->delete("/liquidaciones/{$liq->id}")->assertRedirect();
    expect(Liquidacion::count())->toBe(0)->and(Recibo::count())->toBe(0);
});

it('el empleado ve su recibo en el portal solo cuando la liquidación está cerrada, y lo conforma', function () {
    $liq    = ($this->liquidarSeptiembre)();
    $recibo = Recibo::where('empleado_id', $this->completo->id)->firstOrFail();
    $ajeno  = Recibo::where('empleado_id', $this->medio->id)->firstOrFail();

    $this->actingAs($this->usuario)->get("/mi/recibos/{$recibo->id}")->assertNotFound();

    $this->actingAs($this->admin)->patch("/liquidaciones/{$liq->id}/cerrar");

    $this->actingAs($this->usuario)->get('/mi/recibos')->assertOk()->assertSee('Septiembre 2026');
    $this->actingAs($this->usuario)->get("/mi/recibos/{$recibo->id}")->assertOk()->assertSee('Ochocientos treinta mil');
    $this->actingAs($this->usuario)->get("/mi/recibos/{$ajeno->id}")->assertNotFound();

    $this->actingAs($this->usuario)->patch("/mi/recibos/{$recibo->id}/conformar")->assertSessionHas('ok');
    $primera = $recibo->fresh()->conformado_at;
    expect($primera)->not->toBeNull();

    // La conformidad no se pisa
    Carbon::setTestNow('2026-10-27 10:00:00');
    $this->actingAs($this->usuario)->patch("/mi/recibos/{$recibo->id}/conformar");
    expect($recibo->fresh()->conformado_at->equalTo($primera))->toBeTrue();
});

it('exporta el libro de sueldos en CSV', function () {
    $liq = ($this->liquidarSeptiembre)();

    $csv = $this->actingAs($this->admin)->get("/liquidaciones/{$liq->id}/exportar")
        ->assertOk()
        ->assertDownload('liquidacion-2026-09-mensual.csv')
        ->streamedContent();

    expect($csv)->toContain('Legajo;"Apellido y nombre";CUIL')
        ->toContain('830000,00')
        ->toContain('415000,00');
});

it('SAC del segundo semestre toma la mejor remuneración mensual', function () {
    ($this->liquidarSeptiembre)();

    $this->actingAs($this->admin)->post('/liquidaciones', ['anio' => 2026, 'mes' => 10, 'tipo' => 'sac'])->assertRedirect();
    $sac = Liquidacion::where('tipo', 'sac')->firstOrFail();

    expect($sac->mes)->toBe(12);   // se identifica por el último mes del semestre

    // Completo: mejor remuneración 1.000.000, todo el semestre (184 días) → 500.000
    $r = Recibo::where('liquidacion_id', $sac->id)->where('empleado_id', $this->completo->id)->firstOrFail();
    expect($r->total_remunerativo)->toBe(500000.0);
});
