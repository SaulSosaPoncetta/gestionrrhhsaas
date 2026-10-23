<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReciboItem extends Model
{
    protected $table = 'recibo_items';

    public $timestamps = false;

    protected $fillable = [
        'recibo_id', 'concepto_id', 'codigo', 'nombre', 'tipo', 'cantidad', 'unidad', 'importe', 'orden',
    ];

    protected $casts = [
        'cantidad' => 'float',
        'importe'  => 'float',
    ];

    public function recibo()
    {
        return $this->belongsTo(Recibo::class);
    }

    /** "12,50 hs" · "3 días" · "11 %" */
    public function getCantidadTextoAttribute(): string
    {
        if ($this->cantidad === null) {
            return '';
        }
        $n = rtrim(rtrim(number_format($this->cantidad, 2, ',', '.'), '0'), ',');

        return trim($n . ' ' . ($this->unidad ?? ''));
    }
}
