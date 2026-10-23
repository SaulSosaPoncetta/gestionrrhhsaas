<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;

class Fichada extends Model
{
    use BelongsToEmpresa;

    protected $table = 'fichadas';

    public const ENTRADA = 'entrada';
    public const SALIDA  = 'salida';

    public const TIPOS = [
        self::ENTRADA => 'Entrada',
        self::SALIDA  => 'Salida',
    ];

    public const ORIGENES = [
        'manual'      => 'Carga manual',
        'importacion' => 'Importación',
        'reloj'       => 'Reloj',
        'web'         => 'Autogestión',
    ];

    protected $fillable = [
        'empresa_id', 'empleado_id', 'fecha_hora', 'tipo', 'origen',
        'observacion', 'registrado_por', 'editado_por',
    ];

    protected $casts = [
        'fecha_hora' => 'datetime',
    ];

    public function empleado()
    {
        return $this->belongsTo(Empleado::class);
    }

    public function registradoPor()
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function editadoPor()
    {
        return $this->belongsTo(User::class, 'editado_por');
    }

    public function getTipoNombreAttribute(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }

    public function getOrigenNombreAttribute(): string
    {
        return self::ORIGENES[$this->origen] ?? $this->origen;
    }

    public function esEntrada(): bool
    {
        return $this->tipo === self::ENTRADA;
    }
}
