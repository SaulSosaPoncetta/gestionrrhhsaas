<?php

namespace App\Services\Asistencia;

use App\Models\Empleado;
use App\Models\Feriado;
use App\Models\Fichada;
use App\Models\Horario;
use App\Models\Licencia;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Calcula la asistencia diaria a partir del horario del empleado, sus fichadas y los feriados.
 *
 * Reglas:
 *  - Las fichadas se agrupan en jornadas (entrada → salida). La jornada pertenece al día
 *    de la entrada, así un turno de 22:00 a 06:00 queda en el día que empezó.
 *  - Llegada tarde: si entra después de la hora programada + tolerancia; se computan
 *    los minutos desde la hora programada.
 *  - Un día cubierto por una licencia aprobada y sin fichadas queda como «licencia» (no es ausencia).
 *  - Horas extra: lo trabajado por encima de lo programado (en franco o feriado, todo),
 *    si supera el mínimo configurado en el horario.
 *      · 100 %: domingos, feriados y sábados después de las 13 hs.
 *      · 50 %: el resto.
 */
class CalculadorAsistencia
{
    /** Más de estas horas entre una entrada y una salida no se considera la misma jornada */
    public const MAX_HORAS_JORNADA = 16;

    /** @return array<string, DiaAsistencia>  (clave Y-m-d) */
    public function calcular(Empleado $empleado, Carbon $desde, Carbon $hasta): array
    {
        return $this->calcularVarios(new EloquentCollection([$empleado]), $desde, $hasta)[$empleado->id] ?? [];
    }

    /** @return array<int, array<string, DiaAsistencia>>  (empleado_id => fecha => día) */
    public function calcularVarios(Collection $empleados, Carbon $desde, Carbon $hasta): array
    {
        if ($empleados->isEmpty()) {
            return [];
        }

        $desde = $desde->copy()->startOfDay();
        $hasta = $hasta->copy()->startOfDay();

        if (! $empleados instanceof EloquentCollection) {
            $empleados = new EloquentCollection($empleados->all());
        }
        $empleados->loadMissing('horario');

        $feriados = Feriado::whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->get()
            ->keyBy(fn ($f) => $f->fecha->toDateString());

        $fichadas = Fichada::whereIn('empleado_id', $empleados->pluck('id'))
            ->whereBetween('fecha_hora', [
                $desde->copy()->subHours(self::MAX_HORAS_JORNADA),
                $hasta->copy()->addDay()->addHours(self::MAX_HORAS_JORNADA),
            ])
            ->orderBy('fecha_hora')
            ->get()
            ->groupBy('empleado_id');

        $licencias = Licencia::with('tipo')
            ->aprobadas()
            ->whereIn('empleado_id', $empleados->pluck('id'))
            ->entre($desde, $hasta)
            ->get()
            ->groupBy('empleado_id');

        $resultado = [];
        foreach ($empleados as $empleado) {
            $jornadas = $this->armarJornadas($fichadas->get($empleado->id, collect()));
            $resultado[$empleado->id] = $this->calcularDias(
                $empleado, $desde, $hasta, $jornadas, $feriados, $licencias->get($empleado->id, collect())
            );
        }

        return $resultado;
    }

    /** Totales de un conjunto de días (para resúmenes) */
    public static function totales(array $dias): array
    {
        $t = [
            'programados'         => 0,
            'asistencias'         => 0,
            'ausencias'           => 0,
            'licencias'           => 0,
            'licencias_sin_goce'  => 0,
            'tardanzas'           => 0,
            'minutos_tarde'       => 0,
            'salidas_anticipadas' => 0,
            'incompletos'         => 0,
            'minutos_trabajados'  => 0,
            'extra50'             => 0,
            'extra100'            => 0,
        ];

        foreach ($dias as $d) {
            if ($d->tieneProgramacion() && ! in_array($d->estado, ['fuera', 'pendiente'], true)) {
                $t['programados']++;
            }
            if ($d->asistio())                    $t['asistencias']++;
            if ($d->estado === 'ausente')         $t['ausencias']++;
            if ($d->estado === 'licencia')        $t['licencias']++;
            if ($d->estado === 'licencia' && ! $d->licenciaConGoce) $t['licencias_sin_goce']++;
            if ($d->minutosTarde > 0)             { $t['tardanzas']++; $t['minutos_tarde'] += $d->minutosTarde; }
            if ($d->minutosSalidaAnticipada > 0)  $t['salidas_anticipadas']++;
            if ($d->estado === 'incompleto')      $t['incompletos']++;

            $t['minutos_trabajados'] += $d->minutosTrabajados;
            $t['extra50']            += $d->extra50;
            $t['extra100']           += $d->extra100;
        }

        return $t;
    }

    // -----------------------------------------------------------------

    /** Agrupa las fichadas en pares entrada → salida, indexadas por el día de inicio */
    protected function armarJornadas(Collection $fichadas): array
    {
        $jornadas = [];
        $abierta  = null;

        $agregar = function (?Fichada $entrada, ?Fichada $salida) use (&$jornadas) {
            $fecha = ($entrada ?? $salida)->fecha_hora->toDateString();
            $jornadas[$fecha][] = ['entrada' => $entrada, 'salida' => $salida];
        };

        foreach ($fichadas as $f) {
            if ($f->tipo === Fichada::ENTRADA) {
                if ($abierta) {
                    $agregar($abierta, null);   // entrada sin salida
                }
                $abierta = $f;
                continue;
            }

            // Salida
            if ($abierta && $this->minutos($abierta->fecha_hora, $f->fecha_hora) <= self::MAX_HORAS_JORNADA * 60) {
                $agregar($abierta, $f);
            } else {
                if ($abierta) {
                    $agregar($abierta, null);
                }
                $agregar(null, $f);             // salida sin entrada
            }
            $abierta = null;
        }

        if ($abierta) {
            $agregar($abierta, null);
        }

        return $jornadas;
    }

    protected function calcularDias(Empleado $empleado, Carbon $desde, Carbon $hasta, array $jornadas, Collection $feriados, ?Collection $licencias = null): array
    {
        $horario   = $empleado->horario;
        $licencias ??= collect();
        $ahora     = now();
        $dias      = [];

        foreach (CarbonPeriod::create($desde, $hasta) as $f) {
            $fecha = Carbon::parse($f->format('Y-m-d'))->startOfDay();
            $clave = $fecha->toDateString();
            $dia   = new DiaAsistencia($fecha);
            $dia->jornadas = $jornadas[$clave] ?? [];

            if ($feriado = $feriados->get($clave)) {
                $dia->feriado     = $feriado->descripcion;
                $dia->feriadoTipo = $feriado->tipo;
            }
            $esFeriado = $dia->feriado && $dia->feriadoTipo !== 'no_laborable';

            // Fuera de la relación laboral
            if ($fecha->lt($empleado->fecha_ingreso->copy()->startOfDay())
                || ($empleado->fecha_egreso && $fecha->gt($empleado->fecha_egreso))) {
                $dia->estado = 'fuera';
                $dias[$clave] = $dia;
                continue;
            }

            // Licencia aprobada que cubre el día
            if ($licencia = $licencias->first(fn ($l) => $l->cubre($fecha))) {
                $dia->licencia        = $licencia->tipo?->nombre ?? 'Licencia';
                $dia->licenciaConGoce = (bool) ($licencia->tipo?->con_goce ?? true);
            }

            // Lo programado (en un día no laborable se mantiene, por si la empresa trabaja)
            $prog = $horario?->programacion($fecha->dayOfWeekIso);
            if ($prog && ! $esFeriado) {
                $dia->entradaProg = $fecha->copy()->setTimeFromTimeString($prog['entrada']);
                $dia->salidaProg  = $fecha->copy()->setTimeFromTimeString($prog['salida']);
                if ($dia->salidaProg->lte($dia->entradaProg)) {
                    $dia->salidaProg->addDay();     // horario nocturno
                }
                $dia->minutosProgramados = $this->minutos($dia->entradaProg, $dia->salidaProg);
            }

            // Lo trabajado
            foreach ($dia->jornadas as $j) {
                $e = $j['entrada']?->fecha_hora;
                $s = $j['salida']?->fecha_hora;

                if ($e && $s) {
                    $dia->minutosTrabajados += $this->minutos($e, $s);
                } else {
                    $dia->incompleto = true;
                }
                if ($e && (! $dia->entradaReal || $e->lt($dia->entradaReal))) $dia->entradaReal = $e;
                if ($s && (! $dia->salidaReal || $s->gt($dia->salidaReal)))   $dia->salidaReal  = $s;
            }

            $tolerancia = (int) ($horario?->tolerancia_minutos ?? 0);

            // Sin fichadas
            if (empty($dia->jornadas)) {
                $dia->estado = match (true) {
                    (bool) $dia->licencia     => 'licencia',
                    (bool) $dia->feriado      => 'feriado',
                    ! $horario                => 'sin_horario',
                    ! $dia->entradaProg       => 'franco',
                    $dia->entradaProg->copy()->addMinutes($tolerancia)->gt($ahora) => 'pendiente',
                    default                   => 'ausente',
                };
                $dias[$clave] = $dia;
                continue;
            }

            // Tardanza y salida anticipada
            if ($dia->entradaProg && $dia->entradaReal
                && $dia->entradaReal->gt($dia->entradaProg->copy()->addMinutes($tolerancia))) {
                $dia->minutosTarde = $this->minutos($dia->entradaProg, $dia->entradaReal);
            }
            if ($dia->salidaProg && $dia->salidaReal && $dia->salidaReal->lt($dia->salidaProg)) {
                $dia->minutosSalidaAnticipada = $this->minutos($dia->salidaReal, $dia->salidaProg);
            }

            // ¿Está trabajando ahora? (última jornada abierta y reciente)
            $ultima  = $dia->jornadas[array_key_last($dia->jornadas)];
            $enCurso = $ultima['entrada'] && ! $ultima['salida']
                && $ultima['entrada']->fecha_hora->lte($ahora)
                && $this->minutos($ultima['entrada']->fecha_hora, $ahora) <= self::MAX_HORAS_JORNADA * 60;

            $dia->estado = match (true) {
                $esFeriado                        => 'feriado_trabajado',
                $horario && ! $dia->entradaProg   => 'franco_trabajado',
                $enCurso                          => 'trabajando',
                $dia->incompleto                  => 'incompleto',
                $dia->minutosTarde > 0            => 'tarde',
                default                           => 'presente',
            };

            if (! $enCurso) {
                $this->calcularExtras($dia, $horario, $esFeriado);
            }

            $dias[$clave] = $dia;
        }

        return $dias;
    }

    protected function calcularExtras(DiaAsistencia $dia, ?Horario $horario, bool $esFeriado): void
    {
        if (! $horario) {
            return;     // sin horario no hay contra qué comparar
        }

        $extra = max(0, $dia->minutosTrabajados - $dia->minutosProgramados);
        if ($extra === 0 || $extra < $horario->minimo_extra_minutos) {
            return;
        }

        if ($esFeriado || $dia->fecha->isSunday()) {
            $dia->extra100 = $extra;
            return;
        }

        if ($dia->fecha->isSaturday()) {
            // Al 100 % lo trabajado después de las 13 hs del sábado
            $limite  = $dia->fecha->copy()->setTime(13, 0);
            $despues = 0;
            foreach ($dia->jornadas as $j) {
                if (! $j['entrada'] || ! $j['salida']) {
                    continue;
                }
                $ini = $j['entrada']->fecha_hora->gt($limite) ? $j['entrada']->fecha_hora : $limite;
                $fin = $j['salida']->fecha_hora;
                if ($fin->gt($ini)) {
                    $despues += $this->minutos($ini, $fin);
                }
            }
            $dia->extra100 = min($extra, $despues);
        }

        $dia->extra50 = $extra - $dia->extra100;
    }

    /** Minutos entre dos fechas (igual en Carbon 2 y 3) */
    protected function minutos(Carbon $desde, Carbon $hasta): int
    {
        return intdiv($hasta->getTimestamp() - $desde->getTimestamp(), 60);
    }
}
