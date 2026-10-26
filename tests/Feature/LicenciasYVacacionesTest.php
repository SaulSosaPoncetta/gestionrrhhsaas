<?php

use App\Models\Feriado;
use App\Models\Licencia;
use App\Models\User;
use App\Models\VacacionPeriodo;
use App\Services\Licencias\CalculadorVacaciones;
use Carbon\Carbon;
use Database\Factories\TipoLicenciaFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Carbon::setTestNow('2026-10-26 10:00:00');

    $this->empresa = crearEmpresa();
    $this->admin   = crearAdmin($this->empresa);
    [$this->usuario, $this->empleado] = crearUsuarioPortal($this->empresa, User::ROL_EMPLEADO, ['fecha_ingreso' => '2023-03-01']);

    $this->vacaciones = TipoLicenciaFactory::new()->vacaciones()->create(['empresa_id' => $this->empresa->id]);
    $this->matrimonio = TipoLicenciaFactory::new()->create(['empresa_id' => $this->empresa->id, 'nombre' => 'Matrimonio', 'dias_maximos' => 10]);
    $this->examen     = TipoLicenciaFactory::new()->create(['empresa_id' => $this->empresa->id, 'nombre' => 'Examen', 'dias_maximos' => 2, 'maximo_anual' => 3]);

    $this->solicitar = fn (array $datos) => $this->actingAs($this->usuario)->post('/mi/licencias', $datos);
});

afterEach(fn () => Carbon::setTestNow());

it('calcula 14 días de vacaciones con menos de 5 años de antigüedad', function () {
    $saldo = app(CalculadorVacaciones::class)->saldo($this->empleado, 2026);

    expect($saldo)->corresponden->toBe(14)->tomados->toBe(0)->disponible->toBe(14);
});

it('si trabajó menos de la mitad del año: 1 día cada 20 trabajados (art. 153)', function () {
    $nuevo = crearEmpleado($this->empresa, ['fecha_ingreso' => '2026-09-01']);
    $r     = app(CalculadorVacaciones::class)->calcular($nuevo, 2026);

    expect($r['proporcional'])->toBeTrue()
        ->and($r['dias'])->toBe(intdiv($r['trabajados'], 20))
        ->and($r['trabajados'])->toBeLessThan($r['habiles'] / 2);
});

it('el empleado solicita vacaciones desde el portal y quedan pendientes', function () {
    ($this->solicitar)([
        'tipo_licencia_id' => $this->vacaciones->id, 'fecha_desde' => '2026-12-07', 'fecha_hasta' => '2026-12-13', 'periodo' => 2026,
    ])->assertRedirect(route('portal.licencias'));

    expect(Licencia::firstOrFail())
        ->estado->toBe('pendiente')->dias->toBe(7)->periodo->toBe(2026)
        ->empresa_id->toBe($this->empresa->id)->solicitada_por->toBe($this->usuario->id);

    // Las pendientes descuentan del disponible, no del saldo
    $saldo = app(CalculadorVacaciones::class)->saldo($this->empleado, 2026);
    expect($saldo)->pendientes->toBe(7)->saldo->toBe(14)->disponible->toBe(7);
});

it('no deja pedir más vacaciones que el saldo disponible', function () {
    ($this->solicitar)([
        'tipo_licencia_id' => $this->vacaciones->id, 'fecha_desde' => '2026-12-01', 'fecha_hasta' => '2026-12-20', 'periodo' => 2026,
    ])->assertSessionHasErrors('fecha_hasta');

    expect(Licencia::count())->toBe(0);
});

it('respeta el ajuste manual del período de vacaciones', function () {
    VacacionPeriodo::withoutGlobalScopes()->create([
        'empresa_id' => $this->empresa->id, 'empleado_id' => $this->empleado->id, 'anio' => 2026,
        'dias_corresponden' => 14, 'dias_ajuste' => 7, 'manual' => true,
    ]);

    ($this->solicitar)([
        'tipo_licencia_id' => $this->vacaciones->id, 'fecha_desde' => '2026-12-01', 'fecha_hasta' => '2026-12-20', 'periodo' => 2026,
    ])->assertSessionHasNoErrors();
});

it('las vacaciones requieren el período', function () {
    ($this->solicitar)([
        'tipo_licencia_id' => $this->vacaciones->id, 'fecha_desde' => '2026-12-07', 'fecha_hasta' => '2026-12-08',
    ])->assertSessionHasErrors('periodo');
});

it('no permite licencias superpuestas', function () {
    ($this->solicitar)(['tipo_licencia_id' => $this->matrimonio->id, 'fecha_desde' => '2026-11-02', 'fecha_hasta' => '2026-11-06']);
    ($this->solicitar)(['tipo_licencia_id' => $this->examen->id, 'fecha_desde' => '2026-11-06', 'fecha_hasta' => '2026-11-06'])
        ->assertSessionHasErrors('fecha_desde');

    expect(Licencia::count())->toBe(1);
});

it('una licencia cancelada libera las fechas', function () {
    ($this->solicitar)(['tipo_licencia_id' => $this->matrimonio->id, 'fecha_desde' => '2026-11-02', 'fecha_hasta' => '2026-11-06']);
    $this->actingAs($this->usuario)->patch('/mi/licencias/' . Licencia::first()->id . '/cancelar');

    ($this->solicitar)(['tipo_licencia_id' => $this->examen->id, 'fecha_desde' => '2026-11-06', 'fecha_hasta' => '2026-11-06'])
        ->assertSessionHasNoErrors();
});

it('respeta el máximo por licencia y el tope anual del tipo', function () {
    ($this->solicitar)(['tipo_licencia_id' => $this->matrimonio->id, 'fecha_desde' => '2026-11-02', 'fecha_hasta' => '2026-11-12'])
        ->assertSessionHasErrors(['fecha_hasta' => '«Matrimonio» permite hasta 10 días por licencia (elegiste 11).']);

    ($this->solicitar)(['tipo_licencia_id' => $this->examen->id, 'fecha_desde' => '2026-11-02', 'fecha_hasta' => '2026-11-03'])->assertSessionHasNoErrors();
    ($this->solicitar)(['tipo_licencia_id' => $this->examen->id, 'fecha_desde' => '2026-11-10', 'fecha_hasta' => '2026-11-11'])
        ->assertSessionHasErrors('fecha_hasta');
});

it('no permite licencias antes del ingreso ni con fin anterior al inicio', function () {
    ($this->solicitar)(['tipo_licencia_id' => $this->matrimonio->id, 'fecha_desde' => '2023-02-27', 'fecha_hasta' => '2023-03-02'])
        ->assertSessionHasErrors('fecha_desde');
    ($this->solicitar)(['tipo_licencia_id' => $this->matrimonio->id, 'fecha_desde' => '2026-11-05', 'fecha_hasta' => '2026-11-02'])
        ->assertSessionHasErrors('fecha_hasta');
});

it('no acepta tipos de licencia de otra empresa', function () {
    $ajeno = TipoLicenciaFactory::new()->create(['empresa_id' => crearEmpresa()->id]);

    ($this->solicitar)(['tipo_licencia_id' => $ajeno->id, 'fecha_desde' => '2026-11-02', 'fecha_hasta' => '2026-11-02'])
        ->assertSessionHasErrors('tipo_licencia_id');
});

it('los días hábiles no cuentan fines de semana ni feriados', function () {
    $habiles = TipoLicenciaFactory::new()->habiles()->create(['empresa_id' => $this->empresa->id]);
    Feriado::withoutGlobalScopes()->create(['empresa_id' => $this->empresa->id, 'fecha' => '2026-11-23', 'descripcion' => 'Soberanía', 'tipo' => 'trasladable']);

    // lun 16 al dom 29 de noviembre: 10 hábiles − 1 feriado
    $dias = app(CalculadorVacaciones::class)->diasLicencia($habiles, Carbon::parse('2026-11-16'), Carbon::parse('2026-11-29'), $this->empleado);
    expect($dias)->toBe(9);
});

it('el certificado se guarda en disco privado y solo lo ve su dueño', function () {
    Storage::fake('local');

    ($this->solicitar)([
        'tipo_licencia_id' => $this->matrimonio->id, 'fecha_desde' => '2026-11-02', 'fecha_hasta' => '2026-11-03',
        'certificado' => UploadedFile::fake()->create('acta.pdf', 80, 'application/pdf'),
    ])->assertSessionHasNoErrors();

    $licencia = Licencia::firstOrFail();
    Storage::disk('local')->assertExists($licencia->certificado);

    $this->actingAs($this->usuario)->get("/mi/licencias/{$licencia->id}/certificado")->assertOk();

    [$otro] = crearUsuarioPortal($this->empresa);
    $this->actingAs($otro)->get("/mi/licencias/{$licencia->id}/certificado")->assertNotFound();
});

describe('Aprobación', function () {
    beforeEach(function () {
        [$this->supervisorUser, $this->supervisor] = crearUsuarioPortal($this->empresa, User::ROL_SUPERVISOR);
        $this->empleado->update(['supervisor_id' => $this->supervisor->id]);

        $this->licencia = Licencia::withoutGlobalScopes()->create([
            'empresa_id' => $this->empresa->id, 'empleado_id' => $this->empleado->id, 'tipo_licencia_id' => $this->matrimonio->id,
            'fecha_desde' => '2026-11-02', 'fecha_hasta' => '2026-11-03', 'dias' => 2, 'estado' => 'pendiente',
        ]);
    });

    it('el supervisor aprueba licencias de su equipo', function () {
        $this->actingAs($this->supervisorUser)->patch("/mi/equipo/licencias/{$this->licencia->id}/aprobar")->assertSessionHas('ok');

        expect($this->licencia->fresh())->estado->toBe('aprobada')->resuelta_por->toBe($this->supervisorUser->id);
    });

    it('el supervisor no resuelve licencias de quien no tiene a cargo', function () {
        [$otroSupUser] = crearUsuarioPortal($this->empresa, User::ROL_SUPERVISOR);

        $this->actingAs($otroSupUser)->patch("/mi/equipo/licencias/{$this->licencia->id}/aprobar")->assertNotFound();
        expect($this->licencia->fresh()->estado)->toBe('pendiente');
    });

    it('RRHH rechaza con motivo y no se puede resolver dos veces', function () {
        $this->actingAs($this->admin)->patch("/licencias/{$this->licencia->id}/rechazar", ['motivo_rechazo' => 'Falta acta'])->assertSessionHas('ok');
        expect($this->licencia->fresh())->estado->toBe('rechazada')->motivo_rechazo->toBe('Falta acta');

        $this->actingAs($this->admin)->patch("/licencias/{$this->licencia->id}/aprobar")->assertSessionHas('error');
        expect($this->licencia->fresh()->estado)->toBe('rechazada');
    });

    it('el empleado solo cancela pendientes', function () {
        $this->licencia->update(['estado' => 'aprobada']);

        $this->actingAs($this->usuario)->patch("/mi/licencias/{$this->licencia->id}/cancelar")->assertSessionHas('error');
        expect($this->licencia->fresh()->estado)->toBe('aprobada');
    });
});
