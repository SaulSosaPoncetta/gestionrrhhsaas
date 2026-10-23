<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Categoria;
use App\Models\Convenio;
use App\Models\Empleado;
use App\Models\EmpleadoFamiliar;
use App\Models\Empresa;
use App\Models\Puesto;
use Illuminate\Database\Seeder;

/**
 * Empleados de ejemplo para la empresa demo (datos ficticios).
 */
class EmpleadosDemoSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::where('cuit', '30-71234567-1')->first();
        if (! $empresa) {
            return;
        }
        $e = $empresa->id;

        $area     = fn ($n) => Area::where('empresa_id', $e)->where('nombre', $n)->value('id');
        $puesto   = fn ($n) => Puesto::where('empresa_id', $e)->where('nombre', $n)->value('id');
        $convenio = Convenio::where('empresa_id', $e)->where('nombre', 'Empleados de Comercio')->first();
        $cat      = fn ($n) => Categoria::where('convenio_id', $convenio?->id)->where('nombre', $n)->value('id');

        $empleados = [
            [
                'legajo' => 1, 'apellido' => 'Gómez', 'nombre' => 'Martín', 'cuil' => '20-28987654-6', 'dni' => '28987654',
                'fecha_nacimiento' => '1981-03-14', 'sexo' => 'M', 'estado_civil' => 'casado',
                'fecha_ingreso' => '2012-02-01', 'area' => 'Dirección', 'puesto' => 'Gerente general',
                'convenio' => false, 'sueldo_basico' => 3500000,
            ],
            [
                'legajo' => 2, 'apellido' => 'Fernández', 'nombre' => 'Laura', 'cuil' => '27-32456789-0', 'dni' => '32456789',
                'fecha_nacimiento' => '1986-' . now()->format('m') . '-22', 'sexo' => 'F', 'estado_civil' => 'soltero',
                'fecha_ingreso' => '2016-' . now()->format('m') . '-10', 'area' => 'Recursos Humanos', 'puesto' => 'Analista de RRHH',
                'convenio' => true, 'categoria' => 'Administrativo D',
            ],
            [
                'legajo' => 3, 'apellido' => 'Pereyra', 'nombre' => 'Diego', 'cuil' => '20-30123456-3', 'dni' => '30123456',
                'fecha_nacimiento' => '1983-11-05', 'sexo' => 'M', 'estado_civil' => 'conviviente',
                'fecha_ingreso' => '2019-08-01', 'area' => 'Ventas', 'puesto' => 'Vendedor',
                'convenio' => true, 'categoria' => 'Vendedor B', 'supervisor' => 1,
            ],
            [
                'legajo' => 4, 'apellido' => 'Rodríguez', 'nombre' => 'Sofía', 'cuil' => '27-35111222-6', 'dni' => '35111222',
                'fecha_nacimiento' => '1990-06-30', 'sexo' => 'F', 'estado_civil' => 'casado',
                'fecha_ingreso' => '2021-04-12', 'area' => 'Ventas', 'puesto' => 'Cajero',
                'convenio' => true, 'categoria' => 'Cajero A', 'supervisor' => 3,
            ],
            [
                'legajo' => 5, 'apellido' => 'López', 'nombre' => 'Nicolás', 'cuil' => '20-40333444-9', 'dni' => '40333444',
                'fecha_nacimiento' => '1997-01-19', 'sexo' => 'M', 'estado_civil' => 'soltero',
                'fecha_ingreso' => now()->startOfMonth()->format('Y-m-d'), 'area' => 'Depósito', 'puesto' => 'Repositor',
                'convenio' => true, 'categoria' => 'Maestranza B', 'modalidad' => 'plazo_fijo',
            ],
            [
                'legajo' => 6, 'apellido' => 'Martínez', 'nombre' => 'Carla', 'cuil' => '27-38765432-7', 'dni' => '38765432',
                'fecha_nacimiento' => '1994-09-08', 'sexo' => 'F', 'estado_civil' => 'soltero',
                'fecha_ingreso' => '2020-03-02', 'area' => 'Administración', 'puesto' => 'Administrativo contable',
                'convenio' => true, 'categoria' => 'Administrativo B',
                'fecha_egreso' => now()->subMonths(2)->endOfMonth()->format('Y-m-d'), 'motivo_egreso' => 'renuncia',
            ],
        ];

        $porLegajo = [];

        foreach ($empleados as $d) {
            $empleado = Empleado::withoutGlobalScopes()->updateOrCreate(
                ['empresa_id' => $e, 'legajo' => $d['legajo']],
                [
                    'apellido'         => $d['apellido'],
                    'nombre'           => $d['nombre'],
                    'cuil'             => $d['cuil'],
                    'dni'              => $d['dni'],
                    'fecha_nacimiento' => $d['fecha_nacimiento'],
                    'sexo'             => $d['sexo'],
                    'estado_civil'     => $d['estado_civil'],
                    'nacionalidad'     => 'Argentina',
                    'provincia'        => 'Buenos Aires',
                    'fecha_ingreso'    => $d['fecha_ingreso'],
                    'fecha_egreso'     => $d['fecha_egreso'] ?? null,
                    'motivo_egreso'    => $d['motivo_egreso'] ?? null,
                    'area_id'          => $area($d['area']),
                    'puesto_id'        => $puesto($d['puesto']),
                    'convenio_id'      => $d['convenio'] ? $convenio?->id : null,
                    'categoria_id'     => $d['convenio'] ? $cat($d['categoria']) : null,
                    'sueldo_basico'    => $d['convenio'] ? null : ($d['sueldo_basico'] ?? null),
                    'modalidad'        => $d['modalidad'] ?? 'indeterminado',
                    'jornada'          => 'completa',
                    'horas_semanales'  => 48,
                ]
            );
            $porLegajo[$d['legajo']] = $empleado;
        }

        // Supervisores
        foreach ($empleados as $d) {
            if (! empty($d['supervisor'])) {
                $porLegajo[$d['legajo']]->update(['supervisor_id' => $porLegajo[$d['supervisor']]->id]);
            }
        }

        // Grupo familiar de ejemplo
        $gomez = $porLegajo[1];
        if ($gomez->familiares()->doesntExist()) {
            EmpleadoFamiliar::create([
                'empresa_id' => $e, 'empleado_id' => $gomez->id, 'parentesco' => 'conyuge',
                'apellido_nombre' => 'Suárez, Paula', 'fecha_nacimiento' => '1983-07-21', 'a_cargo' => false,
            ]);
            EmpleadoFamiliar::create([
                'empresa_id' => $e, 'empleado_id' => $gomez->id, 'parentesco' => 'hijo',
                'apellido_nombre' => 'Gómez, Tomás', 'fecha_nacimiento' => '2014-02-11', 'a_cargo' => true,
            ]);
        }
    }
}
