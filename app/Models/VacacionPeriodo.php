<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;

class VacacionPeriodo extends Model
{
    use BelongsToEmpresa;

    protected $table = 'vacaciones_periodos';

    protected $fillable = [
        'empresa_id', 'empleado_id', 'anio', 'dias_corresponden', 'dias_ajuste', 'manual', 'observaciones',
    ];

    protected $casts = [
        'anio'              => 'integer',
        'dias_corresponden' => 'integer',
        'dias_ajuste'       => 'integer',
        'manual'            => 'boolean',
    ];

    public function empleado()
    {
        return $this->belongsTo(Empleado::class);
    }
}
