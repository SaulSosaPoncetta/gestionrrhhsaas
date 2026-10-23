<?php

namespace Database\Seeders;

use App\Models\Empleado;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Usuarios de ejemplo para el portal de autogestión:
 *   - pereyra@demo.test   → supervisor (tiene a Rodríguez a cargo)
 *   - rodriguez@demo.test → empleado
 * Contraseña de los dos: empleado123 (sin cambio obligatorio, para probar rápido).
 */
class PortalDemoSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::where('cuit', '30-71234567-1')->first();
        if (! $empresa) {
            return;
        }

        $accesos = [
            [3, 'pereyra@demo.test', User::ROL_SUPERVISOR],
            [4, 'rodriguez@demo.test', User::ROL_EMPLEADO],
        ];

        foreach ($accesos as [$legajo, $email, $rol]) {
            $empleado = Empleado::where('empresa_id', $empresa->id)->where('legajo', $legajo)->first();
            if (! $empleado) {
                continue;
            }

            $usuario = User::updateOrCreate(
                ['email' => $email],
                [
                    'empresa_id'            => $empresa->id,
                    'name'                  => $empleado->nombre . ' ' . $empleado->apellido,
                    'password'              => 'empleado123',
                    'rol'                   => $rol,
                    'activo'                => true,
                    'debe_cambiar_password' => false,
                ]
            );

            $empleado->update(['user_id' => $usuario->id, 'permite_fichaje_web' => true, 'email' => $empleado->email ?: $email]);
        }
    }
}
