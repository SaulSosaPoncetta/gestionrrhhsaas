<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;

class Puesto extends Model
{
    use BelongsToEmpresa;

    protected $table = 'puestos';

    protected $fillable = [
        'empresa_id', 'area_id', 'nombre', 'descripcion', 'requisitos', 'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function area()
    {
        return $this->belongsTo(Area::class);
    }
}
