<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Categoria;
use App\Models\Convenio;
use App\Models\Empresa;
use App\Models\Puesto;
use Illuminate\Database\Seeder;

/**
 * Datos de ejemplo de estructura para la empresa demo.
 * Los sueldos básicos son VALORES DE EJEMPLO: actualizalos con la escala vigente.
 */
class EstructuraDemoSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::where('cuit', '30-71234567-1')->first();
        if (! $empresa) {
            return;
        }
        $e = $empresa->id;

        // Áreas
        $direccion = Area::firstOrCreate(['empresa_id' => $e, 'nombre' => 'Dirección'], ['codigo' => 'DIR', 'activa' => true]);
        $admin     = Area::firstOrCreate(['empresa_id' => $e, 'nombre' => 'Administración'], ['codigo' => 'ADM', 'area_padre_id' => $direccion->id, 'activa' => true]);
        $rrhh      = Area::firstOrCreate(['empresa_id' => $e, 'nombre' => 'Recursos Humanos'], ['codigo' => 'RRHH', 'area_padre_id' => $admin->id, 'activa' => true]);
        $ventas    = Area::firstOrCreate(['empresa_id' => $e, 'nombre' => 'Ventas'], ['codigo' => 'VTA', 'area_padre_id' => $direccion->id, 'activa' => true]);
        $deposito  = Area::firstOrCreate(['empresa_id' => $e, 'nombre' => 'Depósito'], ['codigo' => 'DEP', 'area_padre_id' => $direccion->id, 'activa' => true]);

        // Puestos
        $puestos = [
            [$direccion, 'Gerente general'],
            [$admin, 'Administrativo contable'],
            [$rrhh, 'Analista de RRHH'],
            [$ventas, 'Vendedor'],
            [$ventas, 'Cajero'],
            [$deposito, 'Repositor'],
            [$deposito, 'Encargado de depósito'],
        ];
        foreach ($puestos as [$area, $nombre]) {
            Puesto::firstOrCreate(['empresa_id' => $e, 'area_id' => $area->id, 'nombre' => $nombre], ['activo' => true]);
        }

        // Convenio de ejemplo
        $convenio = Convenio::firstOrCreate(
            ['empresa_id' => $e, 'nombre' => 'Empleados de Comercio'],
            ['numero' => 'CCT 130/75', 'sindicato' => 'FAECYS', 'horas_semanales' => 48, 'activo' => true]
        );

        $categorias = [
            'Maestranza A', 'Maestranza B', 'Maestranza C',
            'Administrativo A', 'Administrativo B', 'Administrativo C',
            'Administrativo D', 'Administrativo E', 'Administrativo F',
            'Cajero A', 'Cajero B', 'Cajero C',
            'Vendedor A', 'Vendedor B', 'Vendedor C', 'Vendedor D',
        ];

        $basico = 1000000; // EJEMPLO
        foreach ($categorias as $i => $nombre) {
            Categoria::firstOrCreate(
                ['convenio_id' => $convenio->id, 'nombre' => $nombre],
                [
                    'empresa_id'     => $e,
                    'sueldo_basico'  => $basico + ($i * 15000),
                    'vigencia_desde' => now()->startOfMonth(),
                    'orden'          => $i + 1,
                    'activa'         => true,
                ]
            );
        }
    }
}
