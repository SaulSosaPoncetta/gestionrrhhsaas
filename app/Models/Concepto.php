<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;

class Concepto extends Model
{
    use BelongsToEmpresa;

    protected $table = 'conceptos';

    public const REMUNERATIVO    = 'remunerativo';
    public const NO_REMUNERATIVO = 'no_remunerativo';
    public const DESCUENTO       = 'descuento';

    public const TIPOS = [
        self::REMUNERATIVO    => 'Remunerativo',
        self::NO_REMUNERATIVO => 'No remunerativo',
        self::DESCUENTO       => 'Descuento',
    ];

    /**
     * Formas de cálculo. "valor" significa algo distinto en cada una (se muestra en el formulario).
     * mensual: se aplica en liquidaciones mensuales · sac: en el aguinaldo · ambas: en las dos.
     */
    public const FORMAS = [
        'basico'          => ['Sueldo básico (proporcional a los días del mes)', null, 'mensual'],
        'antiguedad'      => ['Antigüedad: % por año sobre la base', '% por año', 'mensual'],
        'porcentaje'      => ['Porcentaje sobre la base', '%', 'ambas'],
        'fijo'            => ['Monto fijo', 'Monto $', 'mensual'],
        'presentismo'     => ['Presentismo: % sobre la base, se pierde con ausencias o tardanzas', '%', 'mensual'],
        'ausencias'       => ['Descuento de días de ausencia injustificada (base / 30)', null, 'mensual'],
        'sin_goce'        => ['Descuento de días de licencia sin goce (base / 30)', null, 'mensual'],
        'plus_vacacional' => ['Plus vacacional: (base / 25 − base / 30) × días de vacaciones', null, 'mensual'],
        'horas_extra_50'  => ['Horas extra al 50 % (valor hora = base / divisor)', 'Divisor', 'mensual'],
        'horas_extra_100' => ['Horas extra al 100 % (valor hora = base / divisor)', 'Divisor', 'mensual'],
        'sac'             => ['SAC: 50 % de la mejor remuneración del semestre, proporcional', null, 'sac'],
        'aporte'          => ['Aporte: % sobre la base, con tope opcional', '%', 'ambas'],
        'manual'          => ['Manual: el importe se carga como novedad', null, 'ambas'],
    ];

    public const BASES = [
        'basico'       => 'Sueldo básico',
        'remunerativo' => 'Total remunerativo',
        'bruto'        => 'Remunerativo + no remunerativo',
    ];

    /**
     * Conceptos iniciales. Los porcentajes de aportes son los de ley; los de convenio
     * (antigüedad, presentismo, sindicato, divisor de horas) son los de Comercio y se ajustan por CCT.
     */
    public const PREDETERMINADOS = [
        // Remunerativos
        ['codigo' => 100, 'nombre' => 'Sueldo básico', 'tipo' => 'remunerativo', 'forma' => 'basico', 'orden' => 10],
        ['codigo' => 110, 'nombre' => 'Antigüedad', 'tipo' => 'remunerativo', 'forma' => 'antiguedad', 'valor' => 1, 'base' => 'basico', 'orden' => 20,
         'parametros' => ['solo_con_convenio' => true]],
        ['codigo' => 150, 'nombre' => 'Ausencias injustificadas', 'tipo' => 'remunerativo', 'forma' => 'ausencias', 'base' => 'remunerativo', 'orden' => 30],
        ['codigo' => 155, 'nombre' => 'Licencia sin goce de haberes', 'tipo' => 'remunerativo', 'forma' => 'sin_goce', 'base' => 'remunerativo', 'orden' => 35],
        ['codigo' => 120, 'nombre' => 'Presentismo', 'tipo' => 'remunerativo', 'forma' => 'presentismo', 'valor' => 8.33, 'base' => 'remunerativo', 'orden' => 40,
         'parametros' => ['max_ausencias' => 0, 'max_tardanzas' => 2, 'solo_con_convenio' => true]],
        ['codigo' => 130, 'nombre' => 'Plus vacacional', 'tipo' => 'remunerativo', 'forma' => 'plus_vacacional', 'base' => 'remunerativo', 'orden' => 50],
        ['codigo' => 140, 'nombre' => 'Horas extra 50 %', 'tipo' => 'remunerativo', 'forma' => 'horas_extra_50', 'valor' => 200, 'base' => 'remunerativo', 'orden' => 60],
        ['codigo' => 141, 'nombre' => 'Horas extra 100 %', 'tipo' => 'remunerativo', 'forma' => 'horas_extra_100', 'valor' => 200, 'base' => 'remunerativo', 'orden' => 61],
        ['codigo' => 160, 'nombre' => 'Premio / bonificación', 'tipo' => 'remunerativo', 'forma' => 'manual', 'automatico' => false, 'orden' => 70],
        ['codigo' => 170, 'nombre' => 'SAC (aguinaldo)', 'tipo' => 'remunerativo', 'forma' => 'sac', 'orden' => 80],
        // No remunerativos
        ['codigo' => 200, 'nombre' => 'Suma no remunerativa (acuerdo)', 'tipo' => 'no_remunerativo', 'forma' => 'manual', 'automatico' => false, 'orden' => 100],
        // Descuentos
        ['codigo' => 300, 'nombre' => 'Jubilación', 'tipo' => 'descuento', 'forma' => 'aporte', 'valor' => 11, 'base' => 'remunerativo', 'orden' => 200],
        ['codigo' => 310, 'nombre' => 'Ley 19.032 (PAMI)', 'tipo' => 'descuento', 'forma' => 'aporte', 'valor' => 3, 'base' => 'remunerativo', 'orden' => 210],
        ['codigo' => 320, 'nombre' => 'Obra social', 'tipo' => 'descuento', 'forma' => 'aporte', 'valor' => 3, 'base' => 'remunerativo', 'orden' => 220],
        ['codigo' => 330, 'nombre' => 'Aporte sindical', 'tipo' => 'descuento', 'forma' => 'aporte', 'valor' => 2, 'base' => 'bruto', 'orden' => 230,
         'parametros' => ['solo_con_convenio' => true]],
        ['codigo' => 340, 'nombre' => 'Anticipo de sueldo', 'tipo' => 'descuento', 'forma' => 'manual', 'automatico' => false, 'orden' => 300],
        ['codigo' => 350, 'nombre' => 'Embargo judicial', 'tipo' => 'descuento', 'forma' => 'manual', 'automatico' => false, 'orden' => 310],
    ];

    protected $fillable = [
        'empresa_id', 'codigo', 'nombre', 'tipo', 'forma', 'valor', 'base', 'tope', 'parametros',
        'convenio_id', 'automatico', 'orden', 'activo',
    ];

    protected $casts = [
        'codigo'     => 'integer',
        'valor'      => 'float',
        'tope'       => 'float',
        'parametros' => 'array',
        'automatico' => 'boolean',
        'activo'     => 'boolean',
        'orden'      => 'integer',
    ];

    public function convenio()
    {
        return $this->belongsTo(Convenio::class);
    }

    public function getTipoNombreAttribute(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }

    public function getFormaNombreAttribute(): string
    {
        return self::FORMAS[$this->forma][0] ?? $this->forma;
    }

    public function parametro(string $clave, $defecto = null)
    {
        return $this->parametros[$clave] ?? $defecto;
    }

    /** ¿Se aplica en este tipo de liquidación? */
    public function aplicaEn(string $tipoLiquidacion): bool
    {
        $ambito = self::FORMAS[$this->forma][2] ?? 'ambas';

        return $ambito === 'ambas' || $ambito === $tipoLiquidacion;
    }

    /** Resumen legible del cálculo para el listado */
    public function getDescripcionCalculoAttribute(): string
    {
        $base = $this->base ? ' sobre ' . mb_strtolower(self::BASES[$this->base] ?? $this->base) : '';
        $valor = fn () => rtrim(rtrim(number_format((float) $this->valor, 2, ',', '.'), '0'), ',');

        return match ($this->forma) {
            'basico'          => 'Básico de la categoría (o del legajo) × días / 30',
            'antiguedad'      => $valor() . ' % por año' . $base,
            'porcentaje', 'presentismo', 'aporte' => $valor() . ' %' . $base . ($this->tope ? ' (tope $ ' . number_format($this->tope, 2, ',', '.') . ')' : ''),
            'fijo'            => '$ ' . number_format((float) $this->valor, 2, ',', '.'),
            'horas_extra_50'  => 'Horas × (base / ' . $valor() . ') × 1,5',
            'horas_extra_100' => 'Horas × (base / ' . $valor() . ') × 2',
            'ausencias', 'sin_goce' => 'Días × base / 30',
            'plus_vacacional' => 'Días × (base/25 − base/30)',
            'sac'             => 'Mejor remuneración / 2 × días / días del semestre',
            'manual'          => 'Por novedad',
            default           => $this->forma,
        };
    }

    public static function cargarPredeterminados(int $empresaId): int
    {
        $agregados = 0;

        foreach (self::PREDETERMINADOS as $c) {
            $concepto = static::withoutGlobalScopes()->firstOrCreate(
                ['empresa_id' => $empresaId, 'codigo' => $c['codigo']],
                $c + ['automatico' => true, 'activo' => true]
            );
            if ($concepto->wasRecentlyCreated) {
                $agregados++;
            }
        }

        return $agregados;
    }
}
