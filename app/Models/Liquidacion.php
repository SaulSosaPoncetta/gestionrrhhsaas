<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Liquidacion extends Model
{
    use BelongsToEmpresa;

    protected $table = 'liquidaciones';

    public const MENSUAL = 'mensual';
    public const SAC     = 'sac';

    public const TIPOS = [
        self::MENSUAL => 'Mensual',
        self::SAC     => 'SAC (aguinaldo)',
    ];

    public const MESES = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
        7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    protected $fillable = [
        'empresa_id', 'anio', 'mes', 'tipo', 'descripcion', 'fecha_pago', 'lugar_pago',
        'deposito_periodo', 'deposito_fecha', 'deposito_banco', 'estado', 'calculada_at',
        'cerrada_at', 'cerrada_por', 'cantidad_recibos', 'total_remunerativo',
        'total_no_remunerativo', 'total_descuentos', 'total_neto',
    ];

    protected $casts = [
        'anio'                  => 'integer',
        'mes'                   => 'integer',
        'fecha_pago'            => 'date',
        'deposito_fecha'        => 'date',
        'calculada_at'          => 'datetime',
        'cerrada_at'            => 'datetime',
        'total_remunerativo'    => 'float',
        'total_no_remunerativo' => 'float',
        'total_descuentos'      => 'float',
        'total_neto'            => 'float',
    ];

    public function recibos()
    {
        return $this->hasMany(Recibo::class)->orderBy('apellido_nombre');
    }

    public function novedades()
    {
        return $this->hasMany(Novedad::class);
    }

    public function cerradaPor()
    {
        return $this->belongsTo(User::class, 'cerrada_por');
    }

    public function esBorrador(): bool { return $this->estado === 'borrador'; }
    public function esSac(): bool      { return $this->tipo === self::SAC; }

    /** Primer y último día del período liquidado (para el SAC, el semestre) */
    public function desde(): Carbon
    {
        if ($this->esSac()) {
            return Carbon::create($this->anio, $this->mes <= 6 ? 1 : 7, 1)->startOfDay();
        }
        return Carbon::create($this->anio, $this->mes, 1)->startOfDay();
    }

    public function hasta(): Carbon
    {
        if ($this->esSac()) {
            return Carbon::create($this->anio, $this->mes <= 6 ? 6 : 12, 1)->endOfMonth()->startOfDay();
        }
        return $this->desde()->endOfMonth()->startOfDay();
    }

    public function getPeriodoTextoAttribute(): string
    {
        if ($this->esSac()) {
            return 'SAC ' . ($this->mes <= 6 ? '1er' : '2do') . " semestre {$this->anio}";
        }
        return self::MESES[$this->mes] . " {$this->anio}";
    }

    public function getTipoNombreAttribute(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }
}
