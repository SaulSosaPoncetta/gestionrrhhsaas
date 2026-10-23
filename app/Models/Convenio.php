<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;

class Convenio extends Model
{
    use BelongsToEmpresa;

    protected $table = 'convenios';

    protected $fillable = [
        'empresa_id', 'nombre', 'numero', 'sindicato', 'horas_semanales', 'observaciones', 'activo',
    ];

    protected $casts = [
        'activo'          => 'boolean',
        'horas_semanales' => 'decimal:2',
    ];

    public function categorias()
    {
        return $this->hasMany(Categoria::class)->orderBy('orden')->orderBy('nombre');
    }

    public function getNombreCompletoAttribute(): string
    {
        return $this->numero ? "{$this->nombre} ({$this->numero})" : $this->nombre;
    }
}
