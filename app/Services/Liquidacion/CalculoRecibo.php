<?php

namespace App\Services\Liquidacion;

use App\Models\Concepto;

/**
 * Cálculo de un recibo a partir del contexto del empleado y los conceptos. No toca la base de datos.
 *
 * Orden de cálculo:
 *   1. Remunerativos   (por "orden")  → la base "remunerativo" es lo acumulado hasta ese concepto
 *                                       (para las horas extra no se cuentan las otras horas extra)
 *   2. No remunerativos (por "orden")
 *   3. Descuentos      (por "orden")  → la base es el total final
 *
 * Contexto ($ctx):
 *   basico, dias, anios, convenio_id, ausencias, tardanzas, min_extra50, min_extra100,
 *   dias_sin_goce, dias_vacaciones, sac_mejor, sac_dias, sac_dias_semestre
 *
 * Novedades: [concepto_id => ['cantidad' => ?float, 'importe' => ?float]]
 *   - En un concepto manual, el importe es obligatorio.
 *   - En uno automático, la cantidad reemplaza la del sistema (días, horas) y el importe reemplaza el cálculo.
 */
class CalculoRecibo
{
    private const FASES = [Concepto::REMUNERATIVO => 1, Concepto::NO_REMUNERATIVO => 2, Concepto::DESCUENTO => 3];

    public static function calcular(array $ctx, iterable $conceptos, array $novedades, string $tipoLiquidacion): array
    {
        $ctx += [
            'basico' => 0, 'dias' => 30, 'anios' => 0, 'convenio_id' => null, 'ausencias' => 0, 'tardanzas' => 0,
            'min_extra50' => 0, 'min_extra100' => 0, 'dias_sin_goce' => 0, 'dias_vacaciones' => 0,
            'sac_mejor' => 0, 'sac_dias' => 0, 'sac_dias_semestre' => 181,
        ];

        $lista = collect($conceptos)
            ->filter(fn ($c) => self::aplica($c, $ctx, $novedades, $tipoLiquidacion))
            ->sortBy(fn ($c) => [self::FASES[$c->tipo] ?? 9, $c->orden, $c->codigo])
            ->values();

        $rem = $noRem = $desc = 0.0;
        $extras = 0.0;            // horas extra ya sumadas: no forman parte del valor hora de las otras
        $basicoImporte = null;
        $items = [];
        $notas = [];

        foreach ($lista as $c) {
            $nov = $novedades[$c->id] ?? null;

            $esExtra = in_array($c->forma, ['horas_extra_50', 'horas_extra_100'], true);

            $base = match ($c->base) {
                'basico'       => $basicoImporte ?? $ctx['basico'] * $ctx['dias'] / 30,
                'remunerativo' => $esExtra ? $rem - $extras : $rem,
                'bruto'        => ($esExtra ? $rem - $extras : $rem) + $noRem,
                default        => 0.0,
            };

            $r = self::calcularConcepto($c, $ctx, $nov, $base, $notas);
            if ($r === null) {
                continue;
            }
            [$cantidad, $unidad, $importe] = $r;

            if ($nov && $nov['importe'] !== null && $c->forma !== 'manual') {
                $importe = (float) $nov['importe'];
            }

            $importe = round($importe, 2);
            if ($importe == 0.0) {
                continue;
            }

            if ($c->forma === 'basico') {
                $basicoImporte = $importe;
            }
            if ($esExtra) {
                $extras += $importe;
            }

            match ($c->tipo) {
                Concepto::REMUNERATIVO    => $rem += $importe,
                Concepto::NO_REMUNERATIVO => $noRem += $importe,
                default                   => $desc += $importe,
            };

            $items[] = [
                'concepto_id' => $c->id,
                'codigo'      => $c->codigo,
                'nombre'      => $c->nombre,
                'tipo'        => $c->tipo,
                'cantidad'    => $cantidad === null ? null : round((float) $cantidad, 2),
                'unidad'      => $unidad,
                'importe'     => $importe,
                'orden'       => count($items) + 1,
            ];
        }

        $rem   = round($rem, 2);
        $noRem = round($noRem, 2);
        $desc  = round($desc, 2);

        return [
            'items'           => $items,
            'remunerativo'    => $rem,
            'no_remunerativo' => $noRem,
            'descuentos'      => $desc,
            'neto'            => round($rem + $noRem - $desc, 2),
            'notas'           => $notas,
        ];
    }

    private static function aplica($c, array $ctx, array $novedades, string $tipoLiquidacion): bool
    {
        if (! $c->activo || ! $c->aplicaEn($tipoLiquidacion)) {
            return false;
        }
        if ($c->convenio_id && (int) $c->convenio_id !== (int) $ctx['convenio_id']) {
            return false;
        }
        if ($c->parametro('solo_con_convenio') && ! $ctx['convenio_id']) {
            return false;
        }

        $nov = $novedades[$c->id] ?? null;

        if ($c->forma === 'manual') {
            return $nov && $nov['importe'] !== null;
        }

        return $c->automatico || $nov;
    }

    /** @return array{0: ?float, 1: ?string, 2: float}|null  [cantidad, unidad, importe] */
    private static function calcularConcepto($c, array $ctx, ?array $nov, float $base, array &$notas): ?array
    {
        $cant  = $nov['cantidad'] ?? null;
        $valor = (float) $c->valor;

        switch ($c->forma) {
            case 'basico':
                $dias = $cant ?? $ctx['dias'];
                return [$dias, self::dias($dias), $ctx['basico'] * $dias / 30];

            case 'antiguedad':
                $anios = $cant ?? $ctx['anios'];
                return $anios > 0 ? [$anios, $anios == 1 ? 'año' : 'años', $base * $valor / 100 * $anios] : null;

            case 'porcentaje':
                return [$valor, '%', $base * $valor / 100];

            case 'fijo':
                return [$cant, null, $valor];

            case 'presentismo':
                $maxAus = (int) $c->parametro('max_ausencias', 0);
                $maxTar = (int) $c->parametro('max_tardanzas', 2);
                if (! $nov && ($ctx['ausencias'] > $maxAus || $ctx['tardanzas'] > $maxTar)) {
                    $motivo = $ctx['ausencias'] > $maxAus
                        ? $ctx['ausencias'] . ' ' . ($ctx['ausencias'] == 1 ? 'ausencia' : 'ausencias')
                        : $ctx['tardanzas'] . ' llegadas tarde';
                    $notas[] = "No cobra {$c->nombre} ({$motivo}).";
                    return null;
                }
                return [$valor, '%', $base * $valor / 100];

            case 'ausencias':
                $dias = $cant ?? $ctx['ausencias'];
                return $dias > 0 ? [$dias, self::dias($dias), -($base / 30 * $dias)] : null;

            case 'sin_goce':
                $dias = $cant ?? $ctx['dias_sin_goce'];
                return $dias > 0 ? [$dias, self::dias($dias), -($base / 30 * $dias)] : null;

            case 'plus_vacacional':
                $dias = $cant ?? $ctx['dias_vacaciones'];
                return $dias > 0 ? [$dias, self::dias($dias), $base * (1 / 25 - 1 / 30) * $dias] : null;

            case 'horas_extra_50':
            case 'horas_extra_100':
                $minutos = $c->forma === 'horas_extra_50' ? $ctx['min_extra50'] : $ctx['min_extra100'];
                $horas   = $cant ?? round($minutos / 60, 2);
                if ($horas <= 0) {
                    return null;
                }
                $divisor   = $valor > 0 ? $valor : 200;
                $recargo   = $c->forma === 'horas_extra_50' ? 1.5 : 2;
                return [$horas, 'hs', $horas * ($base / $divisor) * $recargo];

            case 'sac':
                if ($ctx['sac_dias'] <= 0 || $ctx['sac_mejor'] <= 0) {
                    return null;
                }
                return [$ctx['sac_dias'], self::dias($ctx['sac_dias']), $ctx['sac_mejor'] / 2 * $ctx['sac_dias'] / max(1, $ctx['sac_dias_semestre'])];

            case 'aporte':
                $imponible = $c->tope ? min($base, (float) $c->tope) : $base;
                if ($c->tope && $base > $c->tope) {
                    $notas[] = "{$c->nombre}: base imponible topeada en $ " . number_format((float) $c->tope, 2, ',', '.') . '.';
                }
                return [$valor, '%', $imponible * $valor / 100];

            case 'manual':
                return [$cant, null, (float) $nov['importe']];
        }

        return null;
    }

    private static function dias($n): string
    {
        return (float) $n == 1.0 ? 'día' : 'días';
    }
}
