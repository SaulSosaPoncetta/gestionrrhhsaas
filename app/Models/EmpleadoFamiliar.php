<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;

class EmpleadoFamiliar extends Model
{
    use BelongsToEmpresa;

    protected $table = 'empleado_familiares';

    public const PARENTESCOS = [
        'conyuge'     => 'Cónyuge',
        'conviviente' => 'Conviviente',
        'hijo'        => 'Hijo/a',
        'hijastro'    => 'Hijastro/a',
        'padre'       => 'Padre',
        'madre'       => 'Madre',
        'hermano'     => 'Hermano/a',
        'otro'        => 'Otro',
    ];

    protected $fillable = [
        'empresa_id', 'empleado_id', 'parentesco', 'apellido_nombre', 'dni',
        'fecha_nacimiento', 'a_cargo', 'discapacidad',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'a_cargo'          => 'boolean',
        'discapacidad'     => 'boolean',
    ];

    public function empleado()
    {
        return $this->belongsTo(Empleado::class);
    }

    public function getParentescoNombreAttribute(): string
    {
        return self::PARENTESCOS[$this->parentesco] ?? $this->parentesco;
    }

    public function getEdadAttribute(): ?int
    {
        return $this->fecha_nacimiento?->age;
    }
}
