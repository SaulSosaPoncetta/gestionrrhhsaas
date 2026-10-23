<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;

class EmpleadoDocumento extends Model
{
    use BelongsToEmpresa;

    protected $table = 'empleado_documentos';

    public const TIPOS = [
        'dni'          => 'DNI',
        'cuil'         => 'Constancia de CUIL',
        'alta_arca'    => 'Alta temprana (ARCA)',
        'contrato'     => 'Contrato de trabajo',
        'cv'           => 'Currículum',
        'titulo'       => 'Título / certificado de estudios',
        'apto_medico'  => 'Apto médico / preocupacional',
        'antecedentes' => 'Certificado de antecedentes',
        'carnet'       => 'Licencia de conducir / carnet',
        'sancion'      => 'Sanción / apercibimiento',
        'otro'         => 'Otro',
    ];

    protected $fillable = [
        'empresa_id', 'empleado_id', 'tipo', 'descripcion', 'archivo',
        'nombre_original', 'tamanio', 'vencimiento', 'subido_por',
    ];

    protected $casts = [
        'vencimiento' => 'date',
    ];

    public function empleado()
    {
        return $this->belongsTo(Empleado::class);
    }

    public function subidoPor()
    {
        return $this->belongsTo(User::class, 'subido_por');
    }

    public function getTipoNombreAttribute(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }

    /** vencido | por_vencer (30 días) | vigente | null */
    public function getEstadoVencimientoAttribute(): ?string
    {
        if (! $this->vencimiento) {
            return null;
        }
        if ($this->vencimiento->isPast() && ! $this->vencimiento->isToday()) {
            return 'vencido';
        }
        if ($this->vencimiento->lte(now()->addDays(30))) {
            return 'por_vencer';
        }
        return 'vigente';
    }

    public function getTamanioLegibleAttribute(): string
    {
        $kb = $this->tamanio / 1024;
        return $kb >= 1024 ? number_format($kb / 1024, 1, ',', '.') . ' MB' : number_format($kb, 0, ',', '.') . ' KB';
    }
}
