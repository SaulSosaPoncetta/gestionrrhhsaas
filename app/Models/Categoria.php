<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;

class Categoria extends Model
{
    use BelongsToEmpresa;

    protected $table = 'categorias';

    protected $fillable = [
        'empresa_id', 'convenio_id', 'nombre', 'sueldo_basico', 'vigencia_desde', 'orden', 'activa',
    ];

    protected $casts = [
        'activa'         => 'boolean',
        'sueldo_basico'  => 'decimal:2',
        'vigencia_desde' => 'date',
    ];

    public function convenio()
    {
        return $this->belongsTo(Convenio::class);
    }

    public function getBasicoFormateadoAttribute(): string
    {
        return '$ ' . number_format((float) $this->sueldo_basico, 2, ',', '.');
    }
}
