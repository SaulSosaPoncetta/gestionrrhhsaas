<?php

use App\Models\Empleado;
use App\Models\EmpleadoDocumento;
use App\Models\Licencia;
use App\Models\Liquidacion;
use App\Models\User;
use Database\Factories\TipoLicenciaFactory;
use Database\Factories\UserFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Lo más delicado de un SaaS: que una empresa no vea ni toque datos de otra.
 */
beforeEach(function () {
    $this->empresaA = crearEmpresa(['razon_social' => 'Empresa A']);
    $this->empresaB = crearEmpresa(['razon_social' => 'Empresa B']);
    $this->adminA   = crearAdmin($this->empresaA);
    $this->adminB   = crearAdmin($this->empresaB);

    $this->empleadoA = crearEmpleado($this->empresaA, ['apellido' => 'Aguirre']);
    $this->empleadoB = crearEmpleado($this->empresaB, ['apellido' => 'Benítez']);
});

it('el listado de legajos solo muestra los de la propia empresa', function () {
    $this->actingAs($this->adminA)->get('/empleados')
        ->assertOk()
        ->assertSee('Aguirre')
        ->assertDontSee('Benítez');
});

it('no se puede ver, editar, dar de baja ni borrar un legajo de otra empresa', function () {
    $id = $this->empleadoB->id;

    $this->actingAs($this->adminA)->get("/empleados/{$id}")->assertNotFound();
    $this->actingAs($this->adminA)->get("/empleados/{$id}/edit")->assertNotFound();
    $this->actingAs($this->adminA)->put("/empleados/{$id}", ['apellido' => 'Hackeado'])->assertNotFound();
    $this->actingAs($this->adminA)->patch("/empleados/{$id}/baja", ['fecha_egreso' => '2026-01-01', 'motivo_egreso' => 'renuncia'])->assertNotFound();
    $this->actingAs($this->adminA)->delete("/empleados/{$id}")->assertNotFound();

    expect(Empleado::withoutGlobalScopes()->find($id))
        ->apellido->toBe('Benítez')
        ->fecha_egreso->toBeNull()
        ->deleted_at->toBeNull();
});

it('el scope por empresa filtra las consultas Eloquent', function () {
    $this->actingAs($this->adminA);

    expect(Empleado::pluck('id')->all())->toBe([$this->empleadoA->id])
        ->and(Empleado::find($this->empleadoB->id))->toBeNull();
});

it('al crear, empresa_id sale del usuario logueado', function () {
    $this->actingAs($this->adminA);

    $tipo = TipoLicenciaFactory::new()->make(['empresa_id' => null]);
    $tipo->save();

    expect($tipo->empresa_id)->toBe($this->empresaA->id);
});

it('no se puede asignar un área, horario o supervisor de otra empresa al legajo', function () {
    $this->actingAs($this->adminA)->put("/empleados/{$this->empleadoA->id}", [
        'legajo' => $this->empleadoA->legajo, 'apellido' => 'Aguirre', 'nombre' => 'Ana',
        'cuil' => $this->empleadoA->cuil, 'dni' => $this->empleadoA->dni, 'fecha_ingreso' => '2020-01-01',
        'modalidad' => 'indeterminado', 'jornada' => 'completa', 'sueldo_basico' => 1000,
        'supervisor_id' => $this->empleadoB->id,
    ])->assertSessionHasErrors('supervisor_id');
});

it('no se pueden aprobar licencias de otra empresa', function () {
    $tipo     = TipoLicenciaFactory::new()->create(['empresa_id' => $this->empresaB->id]);
    $licencia = Licencia::withoutGlobalScopes()->create([
        'empresa_id' => $this->empresaB->id, 'empleado_id' => $this->empleadoB->id, 'tipo_licencia_id' => $tipo->id,
        'fecha_desde' => '2026-11-02', 'fecha_hasta' => '2026-11-03', 'dias' => 2, 'estado' => 'pendiente',
    ]);

    $this->actingAs($this->adminA)->patch("/licencias/{$licencia->id}/aprobar")->assertNotFound();

    expect($licencia->fresh()->estado)->toBe('pendiente');
});

it('no se ven ni descargan liquidaciones de otra empresa', function () {
    $liq = Liquidacion::withoutGlobalScopes()->create(['empresa_id' => $this->empresaB->id, 'anio' => 2026, 'mes' => 9, 'tipo' => 'mensual']);

    $this->actingAs($this->adminA)->get("/liquidaciones/{$liq->id}")->assertNotFound();
    $this->actingAs($this->adminA)->get("/liquidaciones/{$liq->id}/exportar")->assertNotFound();
    $this->actingAs($this->adminA)->delete("/liquidaciones/{$liq->id}")->assertNotFound();
});

it('no se descargan documentos de legajos de otra empresa', function () {
    Storage::fake('local');

    $this->actingAs($this->adminB)->post("/empleados/{$this->empleadoB->id}/documentos", [
        'tipo' => array_key_first(EmpleadoDocumento::TIPOS),
        'archivo' => UploadedFile::fake()->create('dni.pdf', 100, 'application/pdf'),
    ])->assertRedirect();

    $doc = EmpleadoDocumento::withoutGlobalScopes()->firstOrFail();

    $this->actingAs($this->adminB)->get("/empleados/{$this->empleadoB->id}/documentos/{$doc->id}/descargar")->assertOk();

    // A no puede, ni usando su propio legajo en la URL
    $this->actingAs($this->adminA)->get("/empleados/{$this->empleadoB->id}/documentos/{$doc->id}/descargar")->assertNotFound();
    $this->actingAs($this->adminA)->get("/empleados/{$this->empleadoA->id}/documentos/{$doc->id}/descargar")->assertNotFound();
});

it('un admin no edita usuarios de otra empresa', function () {
    $this->actingAs($this->adminA)->get("/usuarios/{$this->adminB->id}/edit")->assertForbidden();
    $this->actingAs($this->adminA)->delete("/usuarios/{$this->adminB->id}")->assertForbidden();

    $this->actingAs($this->adminA)->get('/usuarios')->assertOk()->assertDontSee($this->adminB->email);
});

it('un admin no ve ni edita al superadmin', function () {
    $super = crearSuperadmin();

    $this->actingAs($this->adminA)->get("/usuarios/{$super->id}/edit")->assertForbidden();
});

it('el portal no deja ver recibos ni licencias de otros empleados', function () {
    [$usuario] = crearUsuarioPortal($this->empresaA);
    $tipo = TipoLicenciaFactory::new()->create(['empresa_id' => $this->empresaA->id]);

    $ajena = Licencia::withoutGlobalScopes()->create([
        'empresa_id' => $this->empresaA->id, 'empleado_id' => $this->empleadoA->id, 'tipo_licencia_id' => $tipo->id,
        'fecha_desde' => '2026-11-02', 'fecha_hasta' => '2026-11-03', 'dias' => 2, 'estado' => 'pendiente',
    ]);

    $this->actingAs($usuario)->patch("/mi/licencias/{$ajena->id}/cancelar")->assertNotFound();
    $this->actingAs($usuario)->get("/mi/licencias/{$ajena->id}/certificado")->assertNotFound();

    expect($ajena->fresh()->estado)->toBe('pendiente');
});
