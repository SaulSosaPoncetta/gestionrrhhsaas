<?php

use App\Models\Empleado;
use App\Models\Empresa;
use App\Models\User;
use Database\Factories\EmpleadoFactory;
use Database\Factories\EmpresaFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Configuración de Pest
|--------------------------------------------------------------------------
| Unit:    lógica pura (cálculos, validadores). No toca la base.
| Feature: requests HTTP y servicios con base SQLite en memoria (RefreshDatabase).
*/

pest()->extend(TestCase::class)->in('Unit');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers para armar escenarios multi-empresa
|--------------------------------------------------------------------------
*/

function crearEmpresa(array $atributos = []): Empresa
{
    return EmpresaFactory::new()->create($atributos);
}

function crearSuperadmin(array $atributos = []): User
{
    return UserFactory::new()->superadmin()->create($atributos);
}

function crearAdmin(?Empresa $empresa = null, array $atributos = []): User
{
    return UserFactory::new()->admin($empresa ?? crearEmpresa())->create($atributos);
}

function crearEmpleado(Empresa $empresa, array $atributos = []): Empleado
{
    return EmpleadoFactory::new()->deEmpresa($empresa)->create($atributos);
}

/**
 * Empleado con usuario del portal de autogestión.
 * @return array{0: User, 1: Empleado}
 */
function crearUsuarioPortal(Empresa $empresa, string $rol = User::ROL_EMPLEADO, array $empleado = []): array
{
    $usuario = UserFactory::new()->state(['empresa_id' => $empresa->id, 'rol' => $rol])->create();
    $legajo  = crearEmpleado($empresa, $empleado + ['user_id' => $usuario->id]);

    return [$usuario, $legajo];
}
