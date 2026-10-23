<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;

class Novedad extends Model
{
    use BelongsToEmpresa;

    protected $table = 'novedades';

    protected $fillable = [
        'empresa_id', 'liquidacion_id', 'empleado_id', 'concepto_id', 'cantidad', 'importe', 'observacion',
    ];

    protected $casts = [
        'cantidad' => 'float',
        'importe'  => 'float',
    ];

    public function liquidacion() { return $this->belongsTo(Liquidacion::class); }
    public function empleado()    { return $this->belongsTo(Empleado::class)->withTrashed(); }
    public function concepto()    { return $this->belongsTo(Concepto::class); }
}
