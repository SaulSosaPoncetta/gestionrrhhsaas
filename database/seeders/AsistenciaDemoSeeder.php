<?php

namespace Database\Seeders;

use App\Models\Empleado;
use App\Models\Empresa;
use App\Models\Feriado;
use App\Models\Fichada;
use App\Models\Horario;
use Illuminate\Database\Seeder;

/**
 * Horarios, feriados y fichadas de los últimos 45 días para la empresa demo.
 * Genera un poco de todo: llegadas tarde, ausencias, horas extra y alguna fichada incompleta.
 */
class AsistenciaDemoSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::where('cuit', '30-71234567-1')->first();
        if (! $empresa) {
            return;
        }
        $e = $empresa->id;

        // Horarios
        $lunesAViernes = fn ($entrada, $salida) => array_fill_keys(range(1, 5), ['entrada' => $entrada, 'salida' => $salida]);

        $comercio = Horario::updateOrCreate(
            ['empresa_id' => $e, 'nombre' => 'Comercio (lunes a sábado)'],
            [
                'dias'                 => $lunesAViernes('09:00', '18:00') + [6 => ['entrada' => '09:00', 'salida' => '13:00']],
                'tolerancia_minutos'   => 10,
                'minimo_extra_minutos' => 30,
                'activo'               => true,
            ]
        );

        $administracion = Horario::updateOrCreate(
            ['empresa_id' => $e, 'nombre' => 'Administración (lunes a viernes)'],
            [
                'dias'                 => $lunesAViernes('08:00', '16:00'),
                'tolerancia_minutos'   => 10,
                'minimo_extra_minutos' => 30,
                'activo'               => true,
            ]
        );

        // Asignar por legajo
        $asignacion = [1 => $administracion, 2 => $administracion, 3 => $comercio, 4 => $comercio, 5 => $comercio, 6 => $administracion];
        foreach ($asignacion as $legajo => $horario) {
            Empleado::where('empresa_id', $e)->where('legajo', $legajo)->update(['horario_id' => $horario->id]);
        }

        // Feriados fijos
        $desde = today()->subDays(45);
        Feriado::cargarFijos($e, today()->year);
        if ($desde->year !== today()->year) {
            Feriado::cargarFijos($e, $desde->year);
        }

        // Fichadas (solo si todavía no hay)
        if (Fichada::where('empresa_id', $e)->exists()) {
            return;
        }

        mt_srand(2026);

        $feriados  = Feriado::where('empresa_id', $e)->pluck('fecha')->map(fn ($f) => \Carbon\Carbon::parse($f)->toDateString())->all();
        $empleados = Empleado::with('horario')->where('empresa_id', $e)->whereNull('fecha_egreso')->get();
        $ahora     = now();
        $filas     = [];

        $agregar = function ($empleado, $momento, $tipo) use (&$filas, $e, $ahora) {
            $filas[] = [
                'empresa_id'  => $e,
                'empleado_id' => $empleado->id,
                'fecha_hora'  => $momento->format('Y-m-d H:i:s'),
                'tipo'        => $tipo,
                'origen'      => 'reloj',
                'created_at'  => $ahora,
                'updated_at'  => $ahora,
            ];
        };

        foreach ($empleados as $emp) {
            for ($f = $desde->copy(); $f->lte(today()); $f->addDay()) {
                if ($f->lt($emp->fecha_ingreso) || in_array($f->toDateString(), $feriados, true)) {
                    continue;
                }

                $prog = $emp->horario?->programacion($f->dayOfWeekIso);
                if (! $prog) {
                    continue;
                }

                $azar = mt_rand(1, 100);
                if ($azar <= 4) {
                    continue;                                           // ausencia
                }

                $entrada = $f->copy()->setTimeFromTimeString($prog['entrada'])
                    ->addMinutes(mt_rand(-12, $azar <= 14 ? 40 : 8));   // ~10 % llega tarde
                $salida  = $f->copy()->setTimeFromTimeString($prog['salida'])
                    ->addMinutes(mt_rand(-3, $azar >= 88 ? 100 : 12));  // ~12 % se queda horas extra

                if ($entrada->lte($ahora)) {
                    $agregar($emp, $entrada, Fichada::ENTRADA);
                }
                if ($azar === 50) {
                    continue;                                           // se olvidó de fichar la salida
                }
                if ($salida->lte($ahora)) {
                    $agregar($emp, $salida, Fichada::SALIDA);
                }
            }
        }

        foreach (array_chunk($filas, 500) as $lote) {
            Fichada::insert($lote);
        }
    }
}
