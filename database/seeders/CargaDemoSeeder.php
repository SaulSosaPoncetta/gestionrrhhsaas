<?php

namespace Database\Seeders;

use App\Models\Empleado;
use App\Models\Empresa;
use App\Models\Horario;
use App\Models\Liquidacion;
use App\Models\User;
use App\Services\Liquidacion\MotorLiquidacion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Usuarios para la prueba de carga con Locust (50 usuarios simultáneos).
 *
 *   carga01@demo.test … carga05@demo.test  → supervisores (cada uno con 9 empleados a cargo)
 *   carga06@demo.test … carga50@demo.test  → empleados
 *   Contraseña de todos: empleado123
 *
 * Uso:  php artisan db:seed --class=CargaDemoSeeder
 * Se puede correr varias veces (no duplica). Nunca se ejecuta en producción.
 */
class CargaDemoSeeder extends Seeder
{
    public const CANTIDAD     = 50;
    public const SUPERVISORES = 5;
    public const PASSWORD     = 'empleado123';

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('CargaDemoSeeder no se ejecuta en producción.');
            return;
        }

        $empresa = Empresa::where('cuit', '30-71234567-1')->first();
        if (! $empresa) {
            $this->command?->error('Primero cargá los datos de demo: php artisan db:seed');
            return;
        }

        $horario  = Horario::withoutGlobalScopes()->where('empresa_id', $empresa->id)->orderBy('id')->first();
        $password = Hash::make(self::PASSWORD);
        $supervisores = [];

        for ($i = 1; $i <= self::CANTIDAD; $i++) {
            $n      = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $esSup  = $i <= self::SUPERVISORES;
            $legajo = 9000 + $i;
            $dni    = (string) (50000000 + $i);

            $usuario = User::updateOrCreate(
                ['email' => "carga{$n}@demo.test"],
                [
                    'empresa_id'            => $empresa->id,
                    'name'                  => "Carga {$n}",
                    'password'              => $password,
                    'rol'                   => $esSup ? User::ROL_SUPERVISOR : User::ROL_EMPLEADO,
                    'activo'                => true,
                    'debe_cambiar_password' => false,
                ]
            );

            $empleado = Empleado::withoutGlobalScopes()->updateOrCreate(
                ['empresa_id' => $empresa->id, 'legajo' => $legajo],
                [
                    'apellido'            => 'Carga',
                    'nombre'              => "Usuario {$n}",
                    'cuil'                => self::cuil('20', $dni),
                    'dni'                 => $dni,
                    'fecha_ingreso'       => '2024-03-01',
                    'modalidad'           => 'indeterminado',
                    'jornada'             => 'completa',
                    'sueldo_basico'       => 1200000,
                    'horario_id'          => $horario?->id,
                    'user_id'             => $usuario->id,
                    'permite_fichaje_web' => true,
                    'supervisor_id'       => $esSup ? null : ($supervisores[($i - 1) % self::SUPERVISORES] ?? null),
                ]
            );

            if ($esSup) {
                $supervisores[] = $empleado->id;
            }
        }

        // Recalcular las liquidaciones en borrador para que los nuevos empleados tengan recibo
        $motor = app(MotorLiquidacion::class);
        Liquidacion::withoutGlobalScopes()->where('empresa_id', $empresa->id)->where('estado', 'borrador')
            ->get()->each(fn ($l) => $motor->liquidar($l));

        $this->command?->info(self::CANTIDAD . ' usuarios de carga listos (carga01…carga' . self::CANTIDAD . '@demo.test / ' . self::PASSWORD . ').');
    }

    private static function cuil(string $prefijo, string $dni): string
    {
        $base = $prefijo . $dni;
        $mult = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $suma = 0;
        for ($i = 0; $i < 10; $i++) {
            $suma += (int) $base[$i] * $mult[$i];
        }
        $dv = 11 - ($suma % 11);
        $dv = $dv === 11 ? 0 : ($dv === 10 ? 9 : $dv);

        return "{$prefijo}-{$dni}-{$dv}";
    }
}
