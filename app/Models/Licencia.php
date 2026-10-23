<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Licencia extends Model
{
    use BelongsToEmpresa;

    protected $table = 'licencias';

    public const PENDIENTE = 'pendiente';
    public const APROBADA  = 'aprobada';
    public const RECHAZADA = 'rechazada';
    public const CANCELADA = 'cancelada';

    /** estado => [etiqueta, color] */
    public const ESTADOS = [
        self::PENDIENTE => ['Pendiente', 'warning'],
        self::APROBADA  => ['Aprobada', 'success'],
        self::RECHAZADA => ['Rechazada', 'danger'],
        self::CANCELADA => ['Cancelada', 'secondary'],
    ];

    /** Estados que ocupan días (para superposiciones y saldos) */
    public const ACTIVAS = [self::PENDIENTE, self::APROBADA];

    protected $fillable = [
        'empresa_id', 'empleado_id', 'tipo_licencia_id', 'fecha_desde', 'fecha_hasta', 'dias', 'periodo',
        'estado', 'observaciones', 'certificado', 'certificado_nombre', 'solicitada_por', 'resuelta_por',
        'resuelta_at', 'motivo_rechazo',
    ];

    protected $casts = [
        'fecha_desde' => 'date',
        'fecha_hasta' => 'date',
        'resuelta_at' => 'datetime',
        'dias'        => 'integer',
        'periodo'     => 'integer',
    ];

    public function empleado()      { return $this->belongsTo(Empleado::class); }
    public function tipo()          { return $this->belongsTo(TipoLicencia::class, 'tipo_licencia_id'); }
    public function solicitadaPor() { return $this->belongsTo(User::class, 'solicitada_por'); }
    public function resueltaPor()   { return $this->belongsTo(User::class, 'resuelta_por'); }

    // ---------- Scopes ----------

    public function scopeAprobadas(Builder $q): Builder
    {
        return $q->where('estado', self::APROBADA);
    }

    public function scopeActivas(Builder $q): Builder
    {
        return $q->whereIn('estado', self::ACTIVAS);
    }

    public function scopeDeVacaciones(Builder $q): Builder
    {
        return $q->whereHas('tipo', fn ($t) => $t->where('es_vacaciones', true));
    }

    /** Licencias que se superponen con el rango */
    public function scopeEntre(Builder $q, $desde, $hasta): Builder
    {
        return $q->whereDate('fecha_desde', '<=', $hasta instanceof \DateTimeInterface ? $hasta->format('Y-m-d') : $hasta)
                 ->whereDate('fecha_hasta', '>=', $desde instanceof \DateTimeInterface ? $desde->format('Y-m-d') : $desde);
    }

    // ---------- Atributos ----------

    public function getEstadoNombreAttribute(): string
    {
        return self::ESTADOS[$this->estado][0] ?? $this->estado;
    }

    public function getEstadoColorAttribute(): string
    {
        return self::ESTADOS[$this->estado][1] ?? 'secondary';
    }

    public function getPeriodoTextoAttribute(): string
    {
        return $this->fecha_desde->equalTo($this->fecha_hasta)
            ? $this->fecha_desde->format('d/m/Y')
            : $this->fecha_desde->format('d/m/Y') . ' al ' . $this->fecha_hasta->format('d/m/Y');
    }

    public function esPendiente(): bool { return $this->estado === self::PENDIENTE; }
    public function esAprobada(): bool  { return $this->estado === self::APROBADA; }

    public function faltaCertificado(): bool
    {
        return $this->tipo?->requiere_certificado && ! $this->certificado;
    }

    public function cubre(\DateTimeInterface $fecha): bool
    {
        $f = $fecha->format('Y-m-d');

        return $f >= $this->fecha_desde->format('Y-m-d') && $f <= $this->fecha_hasta->format('Y-m-d');
    }
}
