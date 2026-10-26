<?php

use App\Models\User;
use Database\Factories\UserFactory;

it('muestra el login a los invitados y redirige el resto al login', function () {
    $this->get('/login')->assertOk()->assertSee('password', false);

    $this->get('/dashboard')->assertRedirect('/login');
    $this->get('/empleados')->assertRedirect('/login');
    $this->get('/mi')->assertRedirect('/login');
});

it('ingresa con credenciales correctas y registra el último acceso', function () {
    $admin = crearAdmin();

    $this->post('/login', ['email' => $admin->email, 'password' => UserFactory::PASSWORD])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($admin);
    expect($admin->fresh()->ultimo_acceso)->not->toBeNull();
});

it('rechaza una contraseña incorrecta sin decir si el email existe', function () {
    $admin = crearAdmin();

    $this->from('/login')->post('/login', ['email' => $admin->email, 'password' => 'incorrecta'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['email' => 'Email o contraseña incorrectos.']);

    $this->from('/login')->post('/login', ['email' => 'noexiste@x.test', 'password' => 'incorrecta'])
        ->assertSessionHasErrors(['email' => 'Email o contraseña incorrectos.']);

    $this->assertGuest();
});

it('valida los campos del login', function () {
    $this->post('/login', ['email' => 'no-es-email', 'password' => ''])
        ->assertSessionHasErrors(['email', 'password']);
});

it('limita los intentos de login (throttle 10 por minuto)', function () {
    for ($i = 0; $i < 10; $i++) {
        $this->post('/login', ['email' => 'ataque@x.test', 'password' => 'x' . $i]);
    }

    $this->post('/login', ['email' => 'ataque@x.test', 'password' => 'otra'])->assertStatus(429);
});

it('saca al usuario deshabilitado aunque tenga sesión', function () {
    $admin = crearAdmin(null, ['activo' => false]);

    $this->actingAs($admin)->get('/dashboard')
        ->assertRedirect('/login')
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('saca a los usuarios de una empresa inactiva, pero no al superadmin', function () {
    $empresa = crearEmpresa(['activa' => false]);
    $admin   = crearAdmin($empresa);

    $this->actingAs($admin)->get('/dashboard')->assertRedirect('/login');
    $this->assertGuest();

    $this->actingAs(crearSuperadmin())->get('/dashboard')->assertOk();
});

it('obliga a cambiar la contraseña provisoria antes de usar el sistema', function () {
    $admin = UserFactory::new()->admin(crearEmpresa())->debeCambiarPassword()->create();

    $this->actingAs($admin)->get('/dashboard')->assertRedirect(route('password.editar'));
    $this->actingAs($admin)->get('/empleados')->assertRedirect(route('password.editar'));
    $this->actingAs($admin)->get('/password')->assertOk();

    $this->actingAs($admin)->put('/password', [
        'actual'                => UserFactory::PASSWORD,
        'password'              => 'NuevaClave2026',
        'password_confirmation' => 'NuevaClave2026',
    ])->assertRedirect(route('dashboard'));

    expect($admin->fresh()->debe_cambiar_password)->toBeFalse();
    $this->actingAs($admin->fresh())->get('/dashboard')->assertOk();
});

it('exige la contraseña actual, una nueva distinta y con letras y números', function () {
    $admin = crearAdmin();

    $this->actingAs($admin)->put('/password', [
        'actual' => 'mal', 'password' => 'NuevaClave2026', 'password_confirmation' => 'NuevaClave2026',
    ])->assertSessionHasErrors('actual');

    $this->actingAs($admin)->put('/password', [
        'actual' => UserFactory::PASSWORD, 'password' => 'soloLetrasAqui', 'password_confirmation' => 'soloLetrasAqui',
    ])->assertSessionHasErrors('password');

    $this->actingAs($admin)->put('/password', [
        'actual' => UserFactory::PASSWORD, 'password' => UserFactory::PASSWORD, 'password_confirmation' => UserFactory::PASSWORD,
    ])->assertSessionHasErrors('password');
});

it('cierra la sesión', function () {
    $this->actingAs(crearAdmin())->post('/logout')->assertRedirect('/login');
    $this->assertGuest();
});
