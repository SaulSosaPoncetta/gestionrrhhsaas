<?php

namespace App\Services\Licencias;

use App\Models\Empleado;
use App\Models\Licencia;
use App\Models\TipoLicencia;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Reglas de negocio de una licencia. Las usan RRHH y el portal del empleado.
 *  - No antes del ingreso ni después del egreso
 *  - Que tenga días (en los tipos por días hábiles)
 *  - Máximo por licencia y tope anual del tipo
 *  - Sin superponerse con otra licencia pendiente o aprobada
 *  - Vacaciones: con período y dentro del saldo disponible
 */
class RegistroLicencias
{
    public function __construct(private CalculadorVacaciones $vacaciones)
    {
    }

    /**
     * @return array{dias:int, periodo:?int}
     * @throws ValidationException
     */
    public function validar(Empleado $empleado, TipoLicencia $tipo, Carbon $desde, Carbon $hasta, ?int $periodo, ?Licencia $licencia = null): array
    {
        $empleado->loadMissing('horario');
        $desde   = $desde->copy()->startOfDay();
        $hasta   = $hasta->copy()->startOfDay();
        $errores = [];

        if ($hasta->lt($desde)) {
            throw ValidationException::withMessages(['fecha_hasta' => 'La fecha de fin no puede ser anterior a la de inicio.']);
        }

        if ($desde->lt($empleado->fecha_ingreso)) {
            $errores['fecha_desde'] = 'La licencia no puede empezar antes del ingreso del empleado (' . $empleado->fecha_ingreso->format('d/m/Y') . ').';
        }
        if ($empleado->fecha_egreso && $hasta->gt($empleado->fecha_egreso)) {
            $errores['fecha_hasta'] = 'La licencia no puede terminar después del egreso del empleado (' . $empleado->fecha_egreso->format('d/m/Y') . ').';
        }

        $dias = $this->vacaciones->diasLicencia($tipo, $desde, $hasta, $empleado);

        if ($dias < 1) {
            $errores['fecha_hasta'] = 'El período elegido no tiene días hábiles.';
        }
        if ($tipo->dias_maximos && $dias > $tipo->dias_maximos) {
            $errores['fecha_hasta'] = "«{$tipo->nombre}» permite hasta {$tipo->dias_maximos} días por licencia (elegiste {$dias}).";
        }

        $superpuesta = Licencia::with('tipo')
            ->where('empleado_id', $empleado->id)
            ->activas()
            ->entre($desde, $hasta)
            ->when($licencia, fn ($q) => $q->where('id', '!=', $licencia->id))
            ->first();
        if ($superpuesta) {
            $errores['fecha_desde'] = "Se superpone con otra licencia ({$superpuesta->tipo->nombre}, {$superpuesta->periodo_texto}, {$superpuesta->estado_nombre}).";
        }

        if ($tipo->maximo_anual) {
            $usados = (int) Licencia::where('empleado_id', $empleado->id)
                ->where('tipo_licencia_id', $tipo->id)
                ->activas()
                ->whereYear('fecha_desde', $desde->year)
                ->when($licencia, fn ($q) => $q->where('id', '!=', $licencia->id))
                ->sum('dias');
            if ($usados + $dias > $tipo->maximo_anual) {
                $quedan = max(0, $tipo->maximo_anual - $usados);
                $errores['fecha_hasta'] = "«{$tipo->nombre}» tiene un tope de {$tipo->maximo_anual} días por año y ya usó {$usados} en {$desde->year} (quedan {$quedan}).";
            }
        }

        if ($tipo->es_vacaciones) {
            if (! $periodo) {
                $errores['periodo'] = 'Indicá a qué año corresponden las vacaciones.';
            } elseif (! isset($errores['fecha_hasta'])) {
                $saldo = $this->vacaciones->saldo($empleado, $periodo, $licencia?->id);
                if ($dias > $saldo['disponible']) {
                    $errores['fecha_hasta'] = "Supera el saldo de vacaciones {$periodo}: quedan {$saldo['disponible']} días y se pidieron {$dias}.";
                }
            }
        } else {
            $periodo = null;
        }

        if ($errores) {
            throw ValidationException::withMessages($errores);
        }

        return ['dias' => $dias, 'periodo' => $periodo];
    }
}
