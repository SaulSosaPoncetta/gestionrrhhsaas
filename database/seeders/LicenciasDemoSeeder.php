<?php

namespace Database\Seeders;

use App\Models\Empleado;
use App\Models\Empresa;
use App\Models\Fichada;
use App\Models\Licencia;
use App\Models\TipoLicencia;
use App\Models\VacacionPeriodo;
use App\Services\Licencias\CalculadorVacaciones;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Tipos de licencia de la LCT, saldos de vacaciones y algunas licencias de ejemplo.
 */
class LicenciasDemoSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::where('cuit', '30-71234567-1')->first();
        if (! $empresa) {
            return;
        }
        $e = $empresa->id;

        TipoLicencia::cargarPredeterminados($e);

        if (Licencia::where('empresa_id', $e)->exists()) {
            return;
        }

        $tipo = fn ($codigo) => TipoLicencia::where('empresa_id', $e)->where('codigo', $codigo)->first();
        $emp  = fn ($legajo) => Empleado::with('horario')->where('empresa_id', $e)->where('legajo', $legajo)->first();
        $calc = new CalculadorVacaciones();

        // Saldos de vacaciones del período anterior y del actual
        $actual   = CalculadorVacaciones::periodoActual();
        $vigentes = Empleado::with('horario')->where('empresa_id', $e)->whereNull('fecha_egreso')->get();
        foreach ([$actual - 1, $actual] as $anio) {
            foreach ($vigentes as $empleado) {
                VacacionPeriodo::updateOrCreate(
                    ['empleado_id' => $empleado->id, 'anio' => $anio],
                    ['empresa_id' => $e, 'dias_corresponden' => $calc->calcular($empleado, $anio)['dias']]
                );
            }
        }

        $crear = function (Empleado $empleado, TipoLicencia $tipo, Carbon $desde, Carbon $hasta, string $estado, ?int $periodo = null, ?string $obs = null) use ($e, $calc) {
            return Licencia::create([
                'empresa_id'       => $e,
                'empleado_id'      => $empleado->id,
                'tipo_licencia_id' => $tipo->id,
                'fecha_desde'      => $desde->toDateString(),
                'fecha_hasta'      => $hasta->toDateString(),
                'dias'             => $calc->diasLicencia($tipo, $desde, $hasta, $empleado),
                'periodo'          => $periodo,
                'estado'           => $estado,
                'observaciones'    => $obs,
                'resuelta_at'      => $estado === Licencia::PENDIENTE ? null : now(),
            ]);
        };

        $vac = $tipo('VAC');

        // Pereyra: tomó 14 días de vacaciones del período anterior en enero
        $pereyra = $emp(3);
        $inicio  = Carbon::create($actual, 1, 1)->next(Carbon::MONDAY);
        $crear($pereyra, $vac, $inicio, $inicio->copy()->addDays(13), Licencia::APROBADA, $actual - 1);

        // Rodríguez: enfermedad la semana pasada, de lunes a miércoles (se borran las fichadas de esos días)
        $rodriguez = $emp(4);
        $lunes     = today()->subWeek()->startOfWeek();
        $crear($rodriguez, $tipo('ENF'), $lunes, $lunes->copy()->addDays(2), Licencia::APROBADA, null, 'Gripe. Presentó certificado médico.');
        Fichada::where('empleado_id', $rodriguez->id)
            ->whereBetween('fecha_hora', [$lunes->copy()->startOfDay(), $lunes->copy()->addDays(2)->endOfDay()])
            ->delete();

        // López: pidió 2 días por examen la semana que viene (pendiente)
        $lopez  = $emp(5);
        $martes = today()->addWeek()->startOfWeek()->addDay();
        $crear($lopez, $tipo('EXA'), $martes, $martes->copy()->addDay(), Licencia::PENDIENTE, null, 'Final de Contabilidad II');

        // Fernández: pidió 14 días de vacaciones del período actual el mes que viene (pendiente)
        $fernandez = $emp(2);
        $desde     = today()->addMonth()->startOfMonth()->next(Carbon::MONDAY);
        $crear($fernandez, $vac, $desde, $desde->copy()->addDays(13), Licencia::PENDIENTE, $actual);
    }
}
