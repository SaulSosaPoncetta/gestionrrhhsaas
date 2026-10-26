<?php

namespace Database\Factories;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Uso: UserFactory::new()->admin($empresa)->create()
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /** Contraseña de todos los usuarios de prueba */
    public const PASSWORD = 'password123';

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'empresa_id'            => null,
            'name'                  => fake()->name(),
            'email'                 => fake()->unique()->safeEmail(),
            'email_verified_at'     => now(),
            'password'              => static::$password ??= Hash::make(self::PASSWORD),
            'rol'                   => User::ROL_EMPLEADO,
            'activo'                => true,
            'debe_cambiar_password' => false,
            'remember_token'        => Str::random(10),
        ];
    }

    public function superadmin(): static
    {
        return $this->state(['empresa_id' => null, 'rol' => User::ROL_SUPERADMIN]);
    }

    public function admin(Empresa $empresa): static
    {
        return $this->state(['empresa_id' => $empresa->id, 'rol' => User::ROL_ADMIN]);
    }

    public function supervisor(Empresa $empresa): static
    {
        return $this->state(['empresa_id' => $empresa->id, 'rol' => User::ROL_SUPERVISOR]);
    }

    public function empleado(Empresa $empresa): static
    {
        return $this->state(['empresa_id' => $empresa->id, 'rol' => User::ROL_EMPLEADO]);
    }

    public function inactivo(): static
    {
        return $this->state(['activo' => false]);
    }

    public function debeCambiarPassword(): static
    {
        return $this->state(['debe_cambiar_password' => true]);
    }

    public function unverified(): static
    {
        return $this->state(['email_verified_at' => null]);
    }
}
