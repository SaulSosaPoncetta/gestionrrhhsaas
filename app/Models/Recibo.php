<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;

class Recibo extends Model
{
    use BelongsToEmpresa;

    protected $table = 'recibos';

    protected $fillable = [
        'empresa_id', 'liquidacion_id', 'empleado_id', 'legajo', 'apellido_nombre', 'cuil', 'fecha_ingreso',
        'categoria', 'puesto', 'convenio', 'modalidad', 'banco', 'cbu', 'basico', 'dias_trabajados',
        'total_remunerativo', 'total_no_remunerativo', 'total_descuentos', 'neto', 'observaciones',
        'conformado_at', 'conformado_ip',
    ];

    protected $casts = [
        'fecha_ingreso'         => 'date',
        'basico'                => 'float',
        'total_remunerativo'    => 'float',
        'total_no_remunerativo' => 'float',
        'total_descuentos'      => 'float',
        'neto'                  => 'float',
        'conformado_at'         => 'datetime',
    ];

    public function liquidacion() { return $this->belongsTo(Liquidacion::class); }
    public function empleado()    { return $this->belongsTo(Empleado::class)->withTrashed(); }

    public function items()
    {
        return $this->hasMany(ReciboItem::class)->orderBy('orden')->orderBy('codigo');
    }
}
