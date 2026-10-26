<?php

use App\Models\Empleado;
use App\Models\User;
use Database\Factories\EmpresaFactory;
use Illuminate\Support\Facades\Storage;

function datosLegajo(array $cambios = []): array
{
    return array_merge([
        'legajo'        => 100,
        'apellido'      => 'Pérez',
        'nombre'        => 'Juana',
        'cuil'          => '27351112226',   // se guarda formateado
        'dni'           => '35.111.222',
        'fecha_ingreso' => '2024-03-01',
        'modalidad'     => 'indeterminado',
        'jornada'       => 'completa',
        'sueldo_basico' => 950000,
        'cbu'           => '0170099200000123456784',
    ], $cambios);
}

beforeEach(function () {
    $this->empresa = crearEmpresa();
    $this->admin   = crearAdmin($this->empresa);
});

it('crea un legajo normalizando CUIL, DNI y CBU', function () {
    $this->actingAs($this->admin)->post('/empleados', datosLegajo())->assertRedirect();

    expect(Empleado::firstOrFail())
        ->empresa_id->toBe($this->empresa->id)
        ->cuil->toBe('27-35111222-6')
        ->dni->toBe('35111222')
        ->cbu->toBe('0170099200000123456784');
});

it('rechaza CUIL con dígito verificador incorrecto y CBU inválido', function () {
    $this->actingAs($this->admin)->post('/empleados', datosLegajo(['cuil' => '27-35111222-5', 'cbu' => '1234567890123456789012']))
        ->assertSessionHasErrors(['cuil', 'cbu']);

    expect(Empleado::count())->toBe(0);
});

it('no repite legajo ni CUIL dentro de la empresa, pero sí en otra empresa', function () {
    $this->actingAs($this->admin)->post('/empleados', datosLegajo());

    $this->actingAs($this->admin)->post('/empleados', datosLegajo(['cuil' => EmpresaFactory::cuitValido('20')]))
        ->assertSessionHasErrors('legajo');
    $this->actingAs($this->admin)->post('/empleados', datosLegajo(['legajo' => 101]))
        ->assertSessionHasErrors('cuil');

    $otroAdmin = crearAdmin();
    $this->actingAs($otroAdmin)->post('/empleados', datosLegajo())->assertSessionHasNoErrors();

    expect(Empleado::withoutGlobalScopes()->count())->toBe(2);
});

it('exige categoría con convenio y básico sin convenio', function () {
    $this->actingAs($this->admin)->post('/empleados', datosLegajo(['sueldo_basico' => null]))
        ->assertSessionHasErrors('sueldo_basico');
});

it('valida campos obligatorios y fechas', function () {
    $this->actingAs($this->admin)->post('/empleados', ['fecha_nacimiento' => now()->addDay()->toDateString()])
        ->assertSessionHasErrors(['legajo', 'apellido', 'nombre', 'cuil', 'dni', 'fecha_ingreso', 'modalidad', 'jornada', 'fecha_nacimiento']);
});

it('un empleado no puede ser su propio supervisor', function () {
    $e = crearEmpleado($this->empresa);

    $this->actingAs($this->admin)->put("/empleados/{$e->id}", datosLegajo([
        'legajo' => $e->legajo, 'cuil' => $e->cuil, 'dni' => $e->dni, 'supervisor_id' => $e->id,
    ]))->assertSessionHasErrors('supervisor_id');
});

it('busca por apellido, legajo, DNI y CUIL sin guiones', function (string $buscar) {
    crearEmpleado($this->empresa, ['apellido' => 'Zapata', 'legajo' => 777, 'dni' => '31222333', 'cuil' => EmpresaFactory::cuitValido('20', '31222333')]);
    crearEmpleado($this->empresa, ['apellido' => 'Otro', 'legajo' => 1, 'dni' => '20000001', 'cuil' => EmpresaFactory::cuitValido('20', '20000001')]);

    $this->actingAs($this->admin)->get('/empleados?buscar=' . urlencode($buscar))
        ->assertOk()->assertSee('Zapata')->assertDontSee('Otro');
})->with(['Zapata', '777', '31222333', '203122233']);

it('la baja registra el egreso y desactiva el usuario del portal; reincorporar lo revierte', function () {
    [$usuario, $e] = crearUsuarioPortal($this->empresa, User::ROL_EMPLEADO, ['fecha_ingreso' => '2022-01-01']);

    $this->actingAs($this->admin)->patch("/empleados/{$e->id}/baja", ['fecha_egreso' => '2021-12-31', 'motivo_egreso' => 'renuncia'])
        ->assertSessionHasErrors('fecha_egreso');

    $this->actingAs($this->admin)->patch("/empleados/{$e->id}/baja", ['fecha_egreso' => '2026-09-30', 'motivo_egreso' => 'renuncia'])
        ->assertSessionHasNoErrors();

    expect($e->fresh()->activo)->toBeFalse()
        ->and($usuario->fresh()->activo)->toBeFalse();

    $this->actingAs($this->admin)->patch("/empleados/{$e->id}/reincorporar");
    expect($e->fresh()->activo)->toBeTrue();
});

it('eliminar un legajo es soft delete', function () {
    $e = crearEmpleado($this->empresa);

    $this->actingAs($this->admin)->delete("/empleados/{$e->id}")->assertRedirect(route('empleados.index'));

    expect(Empleado::find($e->id))->toBeNull()
        ->and(Empleado::withTrashed()->find($e->id))->not->toBeNull();
});

it('el próximo legajo libre cuenta los eliminados', function () {
    crearEmpleado($this->empresa, ['legajo' => 5])->delete();
    $this->actingAs($this->admin);

    expect(Empleado::proximoLegajo())->toBe(6);
});

it('los documentos se guardan en el disco privado y no se aceptan ejecutables', function () {
    Storage::fake('local');
    $e = crearEmpleado($this->empresa);

    $this->actingAs($this->admin)->post("/empleados/{$e->id}/documentos", [
        'tipo' => 'dni', 'archivo' => \Illuminate\Http\UploadedFile::fake()->create('shell.php', 10, 'application/x-php'),
    ])->assertSessionHasErrors('archivo');

    $this->actingAs($this->admin)->post("/empleados/{$e->id}/documentos", [
        'tipo' => array_key_first(\App\Models\EmpleadoDocumento::TIPOS),
        'archivo' => \Illuminate\Http\UploadedFile::fake()->create('alta.pdf', 50, 'application/pdf'),
    ])->assertSessionHasNoErrors();

    $doc = \App\Models\EmpleadoDocumento::firstOrFail();
    Storage::disk('local')->assertExists($doc->archivo);
    expect($doc->archivo)->toStartWith("empresas/{$this->empresa->id}/empleados/{$e->id}/");
});
