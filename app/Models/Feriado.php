<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Feriado extends Model
{
    use BelongsToEmpresa;

    protected $table = 'feriados';

    public const TIPOS = [
        'inamovible'   => 'Feriado inamovible',
        'trasladable'  => 'Feriado trasladable',
        'puente'       => 'Día puente',
        'no_laborable' => 'Día no laborable',
    ];

    protected $fillable = ['empresa_id', 'fecha', 'descripcion', 'tipo'];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function getTipoNombreAttribute(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }

    /**
     * Feriados nacionales de fecha fija (o calculable) para un año.
     * Los trasladables (17/6, 17/8, 12/10, 20/11) y los días puente
     * se definen por decreto cada año: hay que cargarlos a mano.
     */
    public static function fijosDelAnio(int $anio): array
    {
        $pascua = self::pascua($anio);

        $lista = [
            ["{$anio}-01-01", 'Año Nuevo'],
            [$pascua->copy()->subDays(48)->toDateString(), 'Carnaval'],
            [$pascua->copy()->subDays(47)->toDateString(), 'Carnaval'],
            ["{$anio}-03-24", 'Día Nacional de la Memoria por la Verdad y la Justicia'],
            ["{$anio}-04-02", 'Día del Veterano y de los Caídos en la Guerra de Malvinas'],
            [$pascua->copy()->subDays(2)->toDateString(), 'Viernes Santo'],
            ["{$anio}-05-01", 'Día del Trabajador'],
            ["{$anio}-05-25", 'Día de la Revolución de Mayo'],
            ["{$anio}-06-20", 'Paso a la Inmortalidad del Gral. Manuel Belgrano'],
            ["{$anio}-07-09", 'Día de la Independencia'],
            ["{$anio}-12-08", 'Inmaculada Concepción de María'],
            ["{$anio}-12-25", 'Navidad'],
        ];

        return array_map(fn ($f) => ['fecha' => $f[0], 'descripcion' => $f[1], 'tipo' => 'inamovible'], $lista);
    }

    /** Carga los feriados fijos del año; devuelve cuántos agregó */
    public static function cargarFijos(int $empresaId, int $anio): int
    {
        $agregados = 0;

        foreach (self::fijosDelAnio($anio) as $f) {
            $feriado = static::withoutGlobalScopes()->firstOrCreate(
                ['empresa_id' => $empresaId, 'fecha' => $f['fecha']],
                ['descripcion' => $f['descripcion'], 'tipo' => $f['tipo']]
            );
            if ($feriado->wasRecentlyCreated) {
                $agregados++;
            }
        }

        return $agregados;
    }

    /** Domingo de Pascua (algoritmo gregoriano anónimo) */
    public static function pascua(int $anio): Carbon
    {
        $a = $anio % 19;
        $b = intdiv($anio, 100);
        $c = $anio % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);

        $mes = intdiv($h + $l - 7 * $m + 114, 31);
        $dia = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Carbon::create($anio, $mes, $dia)->startOfDay();
    }
}
