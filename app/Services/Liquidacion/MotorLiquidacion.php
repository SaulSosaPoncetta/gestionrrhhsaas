<?php

namespace App\Services\Liquidacion;

use App\Models\Concepto;
use App\Models\Empleado;
use App\Models\Licencia;
use App\Models\Liquidacion;
use App\Models\Novedad;
use App\Models\Recibo;
use App\Models\ReciboItem;
use App\Services\Asistencia\CalculadorAsistencia;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Genera los recibos de una liquidación con los datos del legajo, la asistencia y las licencias.
 */
class MotorLiquidacion
{
    public function __construct(private CalculadorAsistencia $asistencia)
    {
    }

    /** Calcula (o recalcula) todos los recibos. Devuelve la cantidad generada. */
    public function liquidar(Liquidacion $liquidacion): int
    {
        if (! $liquidacion->esBorrador()) {
            throw new \RuntimeException('La liquidación está cerrada: reabrila para recalcular.');
        }

        return DB::transaction(function () use ($liquidacion) {
            $empresaId = $liquidacion->empresa_id;
            $desde     = $liquidacion->desde();
            $hasta     = $liquidacion->hasta();

            Recibo::withoutGlobalScopes()->where('liquidacion_id', $liquidacion->id)->delete();

            $empleados = Empleado::withoutGlobalScopes()
                ->with(['horario', 'categoria', 'puesto', 'convenio'])
                ->where('empresa_id', $empresaId)
                ->whereNull('deleted_at')
                ->whereDate('fecha_ingreso', '<=', $hasta->toDateString())
                ->where(fn ($q) => $q->whereNull('fecha_egreso')->orWhereDate('fecha_egreso', '>=', $desde->toDateString()))
                ->orderBy('apellido')->orderBy('nombre')
                ->get();

            $conceptos = Concepto::withoutGlobalScopes()
                ->where('empresa_id', $empresaId)
                ->where('activo', true)
                ->get();

            $novedades = Novedad::withoutGlobalScopes()
                ->where('liquidacion_id', $liquidacion->id)
                ->get()
                ->groupBy('empleado_id')
                ->map(fn ($g) => $g->mapWithKeys(fn ($n) => [$n->concepto_id => ['cantidad' => $n->cantidad, 'importe' => $n->importe]])->all());

            $extra = $liquidacion->esSac()
                ? $this->datosSac($liquidacion, $empleados)
                : $this->datosMensuales($empleados, $desde, $hasta);

            $totales = ['rem' => 0, 'norem' => 0, 'desc' => 0, 'neto' => 0];
            $cantidad = 0;

            foreach ($empleados as $empleado) {
                $ctx = $this->contexto($empleado, $desde, $hasta) + ($extra[$empleado->id] ?? []);

                $calculo = CalculoRecibo::calcular($ctx, $conceptos, $novedades->get($empleado->id, []), $liquidacion->tipo);

                if (! $calculo['items']) {
                    continue;   // nada para liquidar (ej.: SAC sin días en el semestre)
                }

                $recibo = Recibo::create([
                    'empresa_id'            => $empresaId,
                    'liquidacion_id'        => $liquidacion->id,
                    'empleado_id'           => $empleado->id,
                    'legajo'                => $empleado->legajo,
                    'apellido_nombre'       => $empleado->nombre_completo,
                    'cuil'                  => $empleado->cuil,
                    'fecha_ingreso'         => $empleado->fecha_ingreso,
                    'categoria'             => $empleado->categoria?->nombre ?? 'Fuera de convenio',
                    'puesto'                => $empleado->puesto?->nombre,
                    'convenio'              => $empleado->convenio?->nombre_completo,
                    'modalidad'             => $empleado->modalidad_nombre,
                    'banco'                 => $empleado->banco,
                    'cbu'                   => $empleado->cbu,
                    'basico'                => $ctx['basico'],
                    'dias_trabajados'       => $liquidacion->esSac() ? ($ctx['sac_dias'] ?? 0) : $ctx['dias'],
                    'total_remunerativo'    => $calculo['remunerativo'],
                    'total_no_remunerativo' => $calculo['no_remunerativo'],
                    'total_descuentos'      => $calculo['descuentos'],
                    'neto'                  => $calculo['neto'],
                    'observaciones'         => implode(' ', array_merge($calculo['notas'], $ctx['notas'] ?? [])) ?: null,
                ]);

                ReciboItem::insert(array_map(fn ($i) => $i + ['recibo_id' => $recibo->id], $calculo['items']));

                $totales['rem']   += $calculo['remunerativo'];
                $totales['norem'] += $calculo['no_remunerativo'];
                $totales['desc']  += $calculo['descuentos'];
                $totales['neto']  += $calculo['neto'];
                $cantidad++;
            }

            $liquidacion->update([
                'calculada_at'          => now(),
                'cantidad_recibos'      => $cantidad,
                'total_remunerativo'    => round($totales['rem'], 2),
                'total_no_remunerativo' => round($totales['norem'], 2),
                'total_descuentos'      => round($totales['desc'], 2),
                'total_neto'            => round($totales['neto'], 2),
            ]);

            return $cantidad;
        });
    }

    // -----------------------------------------------------------------

    /** Datos fijos del empleado para el período */
    public function contexto(Empleado $empleado, Carbon $desde, Carbon $hasta): array
    {
        $basico = $empleado->basico_vigente;

        // Jornada parcial: proporcional a las horas del convenio
        if (in_array($empleado->jornada, ['parcial', 'reducida'], true)
            && $empleado->horas_semanales && $empleado->convenio?->horas_semanales) {
            $basico = $basico * (float) $empleado->horas_semanales / (float) $empleado->convenio->horas_semanales;
        }

        return [
            'basico'      => round($basico, 2),
            'dias'        => self::diasDelPeriodo($empleado, $desde, $hasta),
            'anios'       => $empleado->fecha_ingreso->diff($hasta)->y,
            'convenio_id' => $empleado->convenio_id,
        ];
    }

    /** Días a liquidar: 30 si trabajó el mes completo; si no, los días corridos de relación laboral */
    public static function diasDelPeriodo(Empleado $empleado, Carbon $desde, Carbon $hasta): int
    {
        $inicio = $empleado->fecha_ingreso->gt($desde) ? $empleado->fecha_ingreso->copy()->startOfDay() : $desde->copy();
        $fin    = $empleado->fecha_egreso && $empleado->fecha_egreso->lt($hasta) ? $empleado->fecha_egreso->copy()->startOfDay() : $hasta->copy();

        if ($fin->lt($inicio)) {
            return 0;
        }

        $dias      = intdiv($fin->getTimestamp() - $inicio->getTimestamp(), 86400) + 1;
        $diasMes   = $desde->daysInMonth;

        return $dias >= $diasMes ? 30 : min($dias, 30);
    }

    /** Asistencia y vacaciones del mes */
    private function datosMensuales(Collection $empleados, Carbon $desde, Carbon $hasta): array
    {
        if ($empleados->isEmpty()) {
            return [];
        }

        $calculo = $this->asistencia->calcularVarios($empleados, $desde, $hasta);

        $vacaciones = Licencia::withoutGlobalScopes()
            ->aprobadas()
            ->deVacaciones()
            ->whereIn('empleado_id', $empleados->pluck('id'))
            ->entre($desde, $hasta)
            ->get()
            ->groupBy('empleado_id');

        $datos = [];
        foreach ($empleados as $e) {
            $t = CalculadorAsistencia::totales($calculo[$e->id] ?? []);

            $diasVac = 0;
            foreach ($vacaciones->get($e->id, []) as $l) {
                $ini = $l->fecha_desde->gt($desde) ? $l->fecha_desde : $desde;
                $fin = $l->fecha_hasta->lt($hasta) ? $l->fecha_hasta : $hasta;
                $diasVac += max(0, intdiv($fin->copy()->startOfDay()->getTimestamp() - $ini->copy()->startOfDay()->getTimestamp(), 86400) + 1);
            }

            $datos[$e->id] = [
                'ausencias'       => $t['ausencias'],
                'tardanzas'       => $t['tardanzas'],
                'min_extra50'     => $t['extra50'],
                'min_extra100'    => $t['extra100'],
                'dias_sin_goce'   => $t['licencias_sin_goce'],
                'dias_vacaciones' => $diasVac,
            ];
        }

        return $datos;
    }

    /** SAC: mejor remuneración del semestre y días trabajados */
    private function datosSac(Liquidacion $liquidacion, Collection $empleados): array
    {
        $desde      = $liquidacion->desde();
        $hasta      = $liquidacion->hasta();
        $meses      = $liquidacion->mes <= 6 ? [1, 6] : [7, 12];
        $diasSemest = intdiv($hasta->getTimestamp() - $desde->getTimestamp(), 86400) + 1;

        $mejores = Recibo::withoutGlobalScopes()
            ->whereIn('empleado_id', $empleados->pluck('id'))
            ->whereHas('liquidacion', fn ($q) => $q->withoutGlobalScopes()
                ->where('tipo', Liquidacion::MENSUAL)
                ->where('anio', $liquidacion->anio)
                ->whereBetween('mes', $meses))
            ->selectRaw('empleado_id, MAX(total_remunerativo) as mejor')
            ->groupBy('empleado_id')
            ->pluck('mejor', 'empleado_id');

        // Días de licencia sin goce del semestre (no computan para el SAC)
        $sinGoce = Licencia::withoutGlobalScopes()
            ->aprobadas()
            ->whereHas('tipo', fn ($q) => $q->withoutGlobalScopes()->where('con_goce', false))
            ->whereIn('empleado_id', $empleados->pluck('id'))
            ->entre($desde, $hasta)
            ->get()
            ->groupBy('empleado_id');

        $datos = [];
        foreach ($empleados as $e) {
            $inicio = $e->fecha_ingreso->gt($desde) ? $e->fecha_ingreso->copy()->startOfDay() : $desde;
            $fin    = $e->fecha_egreso && $e->fecha_egreso->lt($hasta) ? $e->fecha_egreso->copy()->startOfDay() : $hasta;
            $dias   = $fin->lt($inicio) ? 0 : intdiv($fin->getTimestamp() - $inicio->getTimestamp(), 86400) + 1;

            foreach ($sinGoce->get($e->id, []) as $l) {
                $ini = $l->fecha_desde->gt($inicio) ? $l->fecha_desde : $inicio;
                $f   = $l->fecha_hasta->lt($fin) ? $l->fecha_hasta : $fin;
                $dias -= max(0, intdiv($f->copy()->startOfDay()->getTimestamp() - $ini->copy()->startOfDay()->getTimestamp(), 86400) + 1);
            }

            $mejor = (float) ($mejores[$e->id] ?? 0);
            $notas = [];
            if ($mejor <= 0) {
                $mejor   = $e->basico_vigente;
                $notas[] = 'Sin liquidaciones mensuales en el semestre: se tomó el básico vigente como mejor remuneración.';
            } else {
                $notas[] = 'Mejor remuneración del semestre: $ ' . number_format($mejor, 2, ',', '.') . '.';
            }

            $datos[$e->id] = [
                'sac_mejor'         => $mejor,
                'sac_dias'          => max(0, $dias),
                'sac_dias_semestre' => $diasSemest,
                'notas'             => $notas,
            ];
        }

        return $datos;
    }
}
