<?php

use App\Models\User;

it('el empleado del portal no entra a los módulos de RRHH', function (string $url) {
    $empresa      = crearEmpresa();
    [$usuario]    = crearUsuarioPortal($empresa);

    $this->actingAs($usuario)->get($url)->assertForbidden();
})->with(['/empleados', '/liquidaciones', '/usuarios', '/empresas', '/licencias', '/asistencia/parte', '/conceptos']);

it('el supervisor tampoco entra a los módulos de RRHH', function () {
    [$supervisor] = crearUsuarioPortal(crearEmpresa(), User::ROL_SUPERVISOR);

    $this->actingAs($supervisor)->get('/empleados')->assertForbidden();
    $this->actingAs($supervisor)->get('/liquidaciones')->assertForbidden();
});

it('el admin de RRHH no administra empresas', function () {
    $this->actingAs(crearAdmin())->get('/empresas')->assertForbidden();
});

it('el superadmin administra empresas pero no opera los datos de una empresa', function () {
    $super = crearSuperadmin();

    $this->actingAs($super)->get('/empresas')->assertOk();
    $this->actingAs($super)->get('/usuarios')->assertOk();
    $this->actingAs($super)->get('/empleados')
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('error');
});

it('solo los supervisores ven el equipo en el portal', function () {
    $empresa = crearEmpresa();
    [$empleado]   = crearUsuarioPortal($empresa);
    [$supervisor] = crearUsuarioPortal($empresa, User::ROL_SUPERVISOR);

    $this->actingAs($empleado)->get('/mi/equipo')->assertForbidden();
    $this->actingAs($supervisor)->get('/mi/equipo')->assertOk();
});

it('un usuario de portal sin legajo activo es expulsado', function () {
    $empresa = crearEmpresa();
    [$usuario, $legajo] = crearUsuarioPortal($empresa);
    $legajo->update(['fecha_egreso' => now()->subDay(), 'motivo_egreso' => 'renuncia']);

    $this->actingAs($usuario)->get('/mi')->assertRedirect('/login');
    $this->assertGuest();
});

it('un admin sin legajo vinculado no entra al portal', function () {
    $this->actingAs(crearAdmin())->get('/mi')->assertRedirect(route('dashboard'));
});

describe('Alta de usuarios', function () {
    it('el admin no puede crear superadmins', function () {
        $admin = crearAdmin();

        $this->actingAs($admin)->post('/usuarios', [
            'name' => 'Intruso', 'email' => 'intruso@x.test', 'rol' => User::ROL_SUPERADMIN,
            'password' => 'Clave12345', 'password_confirmation' => 'Clave12345', 'activo' => 1,
        ])->assertSessionHasErrors('rol');

        expect(User::where('email', 'intruso@x.test')->exists())->toBeFalse();
    });

    it('el admin solo crea usuarios en su propia empresa aunque mande otra empresa_id', function () {
        $admin = crearAdmin();
        $otra  = crearEmpresa();

        $this->actingAs($admin)->post('/usuarios', [
            'name' => 'Nuevo', 'email' => 'nuevo@x.test', 'rol' => User::ROL_ADMIN, 'empresa_id' => $otra->id,
            'password' => 'Clave12345', 'password_confirmation' => 'Clave12345', 'activo' => 1,
        ])->assertRedirect(route('usuarios.index'));

        expect(User::where('email', 'nuevo@x.test')->value('empresa_id'))->toBe($admin->empresa_id);
    });

    it('nadie puede bajarse el rol ni desactivarse a sí mismo', function () {
        $admin = crearAdmin();

        $this->actingAs($admin)->put("/usuarios/{$admin->id}", [
            'name' => $admin->name, 'email' => $admin->email, 'rol' => User::ROL_EMPLEADO,
        ]);

        expect($admin->fresh())->rol->toBe(User::ROL_ADMIN)->activo->toBeTrue();
    });

    it('no se puede eliminar el propio usuario', function () {
        $admin = crearAdmin();

        $this->actingAs($admin)->delete("/usuarios/{$admin->id}")->assertSessionHas('error');
        expect(User::find($admin->id))->not->toBeNull();
    });
});
