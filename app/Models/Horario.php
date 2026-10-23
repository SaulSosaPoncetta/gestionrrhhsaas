<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;

class Horario extends Model
{
    use BelongsToEmpresa;

    protected $table = 'horarios';

    public const DIAS = [
        1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves',
        5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo',
    ];

    public const DIAS_CORTOS = [
        1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom',
    ];

    protected $fillable = [
        'empresa_id', 'nombre', 'dias', 'tolerancia_minutos', 'minimo_extra_minutos', 'activo',
    ];

    protected $casts = [
        'dias'                 => 'array',
        'activo'               => 'boolean',
        'tolerancia_minutos'   => 'integer',
        'minimo_extra_minutos' => 'integer',
    ];

    public function empleados()
    {
        return $this->hasMany(Empleado::class);
    }

    /**
     * Programación de un día (1 = lunes ... 7 = domingo).
     * Devuelve ['entrada' => '08:00', 'salida' => '17:00'] o null si es franco.
     */
    public function programacion(int $diaIso): ?array
    {
        $d = $this->dias[$diaIso] ?? null;

        return ($d && ! empty($d['entrada']) && ! empty($d['salida'])) ? $d : null;
    }

    /** Minutos programados para un día (contempla horarios nocturnos) */
    public function minutosDia(int $diaIso): int
    {
        $p = $this->programacion($diaIso);
        if (! $p) {
            return 0;
        }

        [$he, $me] = array_map('intval', explode(':', $p['entrada']));
        [$hs, $ms] = array_map('intval', explode(':', $p['salida']));
        $min = ($hs * 60 + $ms) - ($he * 60 + $me);

        return $min <= 0 ? $min + 1440 : $min;
    }

    public function getMinutosSemanalesAttribute(): int
    {
        return array_sum(array_map(fn ($d) => $this->minutosDia($d), array_keys(self::DIAS)));
    }

    /** Texto corto: "Lun a Vie 08:00 a 17:00 · Sáb 08:00 a 13:00" */
    public function getResumenAttribute(): string
    {
        $grupos = [];

        foreach (array_keys(self::DIAS) as $d) {
            $p = $this->programacion($d);
            if (! $p) {
                continue;
            }

            $texto = "{$p['entrada']} a {$p['salida']}";
            $n     = count($grupos);

            if ($n && $grupos[$n - 1]['texto'] === $texto && $grupos[$n - 1]['hasta'] === $d - 1) {
                $grupos[$n - 1]['hasta'] = $d;
            } else {
                $grupos[] = ['desde' => $d, 'hasta' => $d, 'texto' => $texto];
            }
        }

        if (! $grupos) {
            return 'Sin días asignados';
        }

        return collect($grupos)->map(function ($g) {
            $c = self::DIAS_CORTOS;
            $dias = match (true) {
                $g['desde'] === $g['hasta']     => $c[$g['desde']],
                $g['hasta'] - $g['desde'] === 1 => "{$c[$g['desde']]} y {$c[$g['hasta']]}",
                default                         => "{$c[$g['desde']]} a {$c[$g['hasta']]}",
            };
            return "{$dias} {$g['texto']}";
        })->implode(' · ');
    }
}
