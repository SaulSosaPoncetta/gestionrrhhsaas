<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Empresa extends Model
{
    use SoftDeletes;

    protected $table = 'empresas';

    protected $fillable = [
        'razon_social', 'nombre_fantasia', 'cuit', 'condicion_iva',
        'domicilio', 'localidad', 'provincia', 'telefono', 'email',
        'logo', 'activa',
    ];

    protected $casts = [
        'activa' => 'boolean',
    ];

    public function usuarios()
    {
        return $this->hasMany(User::class);
    }

    public function getNombreAttribute(): string
    {
        return $this->nombre_fantasia ?: $this->razon_social;
    }
}
