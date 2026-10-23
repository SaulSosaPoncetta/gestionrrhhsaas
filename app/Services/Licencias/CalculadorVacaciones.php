<?php

namespace App\Services\Licencias;

use App\Models\Empleado;
use App\Models\Feriado;
use App\Models\Licencia;
use App\Models\TipoLicencia;
use App\Models\VacacionPeriodo;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Vacaciones según la Ley de Contrato de Trabajo:
 *  - Art. 150: 14 días corridos hasta 5 años de antigüedad, 21 hasta 10, 28 hasta 20 y 35 con más de 20.
 *    La antigüedad se calcula al 31 de diciembre del año al que corresponden.
 *  - Art. 153: si no trabajó al menos la mitad de los días hábiles del año, le corresponde
 *    1 día de vacaciones por cada 20 días trabajados.
 *  - Art. 151: deben empezar un lunes (o el día hábil siguiente si es feriado).
 *  - Art. 154: se otorgan entre el 1 de octubre y el 30 de abril.
 */
class CalculadorVacaciones
{
    /** Cache de feriados: "empresa|año" => ['Y-m-d' => true] */
    private array $feriados = [];

    /** Año del período vacacional en curso (oct–abr): en enero de 2027 todavía es el período 2026 */
    public static function periodoActual(): int
    {
        $hoy = today();

        return $hoy->month <= 4 ? $hoy->year - 1 : $hoy->year;
    }

    public static function diasPorAntiguedad(Carbon $ingreso, int $anio): int
    {
        $corte = Carbon::create($anio, 12, 31)->startOfDay();

        return match (true) {
            $ingreso->copy()->addYears(20)->lt($corte) => 35,
            $ingreso->copy()->addYears(10)->lt($corte) => 28,
            $ingreso->copy()->addYears(5)->lt($corte)  => 21,
            default                                    => 14,
        };
    }

    /**
     * Días de vacaciones que le corresponden a un empleado por un año.
     * @return array{dias:int, proporcional:bool, antiguedad:string, trabajados:int, habiles:int}
     */
    public function calcular(Empleado $empleado, int $anio): array
    {
        $inicio = Carbon::create($anio, 1, 1)->startOfDay();
        $fin    = Carbon::create($anio, 12, 31)->startOfDay();

        $vacio = ['dias' => 0, 'proporcional' => false, 'antiguedad' => '—', 'trabajados' => 0, 'habiles' => 0];

        if ($empleado->fecha_ingreso->gt($fin) || ($empleado->fecha_egreso && $empleado->fecha_egreso->lt($inicio))) {
            return $vacio;
        }

        $desde = $empleado->fecha_ingreso->gt($inicio) ? $empleado->fecha_ingreso->copy()->startOfDay() : $inicio;
        $hasta = $empleado->fecha_egreso && $empleado->fecha_egreso->lt($fin) ? $empleado->fecha_egreso->copy()->startOfDay() : $fin;

        $habiles    = $this->contarHabiles($empleado, $inicio, $fin);
        $trabajados = $this->contarHabiles($empleado, $desde, $hasta);

        $diff       = $empleado->fecha_ingreso->diff($fin);
        $antiguedad = $diff->y . ' ' . ($diff->y === 1 ? 'año' : 'años') . ($diff->m ? ", {$diff->m} " . ($diff->m === 1 ? 'mes' : 'meses') : '');

        if ($trabajados < $habiles / 2) {
            return [
                'dias'         => intdiv($trabajados, 20),
                'proporcional' => true,
                'antiguedad'   => $antiguedad,
                'trabajados'   => $trabajados,
                'habiles'      => $habiles,
            ];
        }

        return [
            'dias'         => self::diasPorAntiguedad($empleado->fecha_ingreso, $anio),
            'proporcional' => false,
            'antiguedad'   => $antiguedad,
            'trabajados'   => $trabajados,
            'habiles'      => $habiles,
        ];
    }

    /** Días que consume una licencia según el tipo (corridos o hábiles) */
    public function diasLicencia(TipoLicencia $tipo, Carbon $desde, Carbon $hasta, ?Empleado $empleado = null): int
    {
        if ($hasta->lt($desde)) {
            return 0;
        }

        if (! $tipo->esHabiles()) {
            return intdiv($hasta->copy()->startOfDay()->getTimestamp() - $desde->copy()->startOfDay()->getTimestamp(), 86400) + 1;
        }

        return $this->contarHabiles($empleado, $desde, $hasta, $tipo->empresa_id);
    }

    /**
     * Saldos de vacaciones de varios empleados para un año.
     * @return array<int, array{corresponden:int, calculado:array, ajuste:int, tomados:int, pendientes:int, saldo:int, disponible:int, periodo:?VacacionPeriodo}>
     */
    public function saldos(Collection $empleados, int $anio, ?int $excluirLicenciaId = null): array
    {
        if ($empleados->isEmpty()) {
            return [];
        }

        $ids = $empleados->pluck('id');

        $periodos = VacacionPeriodo::where('anio', $anio)->whereIn('empleado_id', $ids)->get()->keyBy('empleado_id');

        $sumas = Licencia::query()
            ->whereIn('empleado_id', $ids)
            ->where('periodo', $anio)
            ->whereIn('estado', Licencia::ACTIVAS)
            ->deVacaciones()
            ->when($excluirLicenciaId, fn ($q) => $q->where('id', '!=', $excluirLicenciaId))
            ->selectRaw('empleado_id, estado, SUM(dias) as total')
            ->groupBy('empleado_id', 'estado')
            ->get();

        $resultado = [];
        foreach ($empleados as $empleado) {
            $periodo   = $periodos->get($empleado->id);
            $calculado = $this->calcular($empleado, $anio);

            $tomados    = (int) $sumas->where('empleado_id', $empleado->id)->where('estado', Licencia::APROBADA)->sum('total');
            $pendientes = (int) $sumas->where('empleado_id', $empleado->id)->where('estado', Licencia::PENDIENTE)->sum('total');

            $corresponden = $periodo ? $periodo->dias_corresponden : $calculado['dias'];
            $ajuste       = $periodo?->dias_ajuste ?? 0;
            $saldo        = $corresponden + $ajuste - $tomados;

            $resultado[$empleado->id] = [
                'corresponden' => $corresponden,
                'calculado'    => $calculado,
                'ajuste'       => $ajuste,
                'tomados'      => $tomados,
                'pendientes'   => $pendientes,
                'saldo'        => $saldo,
                'disponible'   => $saldo - $pendientes,
                'periodo'      => $periodo,
            ];
        }

        return $resultado;
    }

    public function saldo(Empleado $empleado, int $anio, ?int $excluirLicenciaId = null): array
    {
        return $this->saldos(collect([$empleado]), $anio, $excluirLicenciaId)[$empleado->id];
    }

    /** Avisos (no bloquean) sobre las reglas de otorgamiento de vacaciones */
    public static function avisos(Carbon $desde, Carbon $hasta): array
    {
        $avisos = [];

        if (! $desde->isMonday()) {
            $avisos[] = 'Las vacaciones deberían empezar un lunes (LCT art. 151).';
        }

        $fueraDeTemporada = fn (Carbon $f) => $f->month >= 5 && $f->month <= 9;
        if ($fueraDeTemporada($desde) || $fueraDeTemporada($hasta)) {
            $avisos[] = 'El período queda fuera del 1/10 al 30/4 (LCT art. 154): si es a pedido del empleado, conviene dejarlo asentado.';
        }

        return $avisos;
    }

    // -----------------------------------------------------------------

    /**
     * Días hábiles entre dos fechas: los que el horario del empleado marca como laborables
     * (lunes a viernes si no tiene horario), sin contar feriados.
     */
    public function contarHabiles(?Empleado $empleado, Carbon $desde, Carbon $hasta, ?int $empresaId = null): int
    {
        $empresaId ??= $empleado?->empresa_id;
        $horario   = $empleado?->horario;
        $contador  = 0;

        for ($f = $desde->copy()->startOfDay(); $f->lte($hasta); $f->addDay()) {
            $laborable = $horario
                ? $horario->programacion($f->dayOfWeekIso) !== null
                : $f->isWeekday();

            if ($laborable && ! $this->esFeriado($f, $empresaId)) {
                $contador++;
            }
        }

        return $contador;
    }

    private function esFeriado(Carbon $fecha, ?int $empresaId): bool
    {
        $clave = $empresaId . '|' . $fecha->year;

        if (! isset($this->feriados[$clave])) {
            $this->feriados[$clave] = Feriado::withoutGlobalScopes()
                ->where('empresa_id', $empresaId)
                ->whereYear('fecha', $fecha->year)
                ->where('tipo', '!=', 'no_laborable')
                ->get()
                ->mapWithKeys(fn ($f) => [$f->fecha->toDateString() => true])
                ->all();
        }

        return isset($this->feriados[$clave][$fecha->toDateString()]);
    }
}
