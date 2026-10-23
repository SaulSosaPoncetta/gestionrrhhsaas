<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // En producción nunca se cargan los datos de demostración
        if (app()->isProduction()) {
            $this->call(ProduccionSeeder::class);
            return;
        }

        // Superadmin de la plataforma (sin empresa)
        User::updateOrCreate(
            ['email' => 'superadmin@rrhh.test'],
            [
                'name'     => 'Superadmin',
                'password' => 'admin1234',
                'rol'      => User::ROL_SUPERADMIN,
                'activo'   => true,
            ]
        );

        // Empresa de prueba
        $empresa = Empresa::updateOrCreate(
            ['cuit' => '30-71234567-1'],
            [
                'razon_social'    => 'Empresa Demo S.A.',
                'nombre_fantasia' => 'Demo',
                'condicion_iva'   => 'Responsable Inscripto',
                'provincia'       => 'Buenos Aires',
                'activa'          => true,
            ]
        );

        User::updateOrCreate(
            ['email' => 'admin@demo.test'],
            [
                'empresa_id' => $empresa->id,
                'name'       => 'Admin RRHH Demo',
                'password'   => 'admin1234',
                'rol'        => User::ROL_ADMIN,
                'activo'     => true,
            ]
        );

        // Áreas, puestos y convenio de ejemplo para la empresa demo
        $this->call(EstructuraDemoSeeder::class);

        // Empleados de ejemplo
        $this->call(EmpleadosDemoSeeder::class);

        // Horarios, feriados y fichadas de ejemplo
        $this->call(AsistenciaDemoSeeder::class);

        // Tipos de licencia de la LCT, saldos de vacaciones y licencias de ejemplo
        $this->call(LicenciasDemoSeeder::class);

        // Conceptos de liquidación y liquidaciones de ejemplo
        $this->call(LiquidacionDemoSeeder::class);

        // Usuarios del portal de autogestión
        $this->call(PortalDemoSeeder::class);
    }
}
