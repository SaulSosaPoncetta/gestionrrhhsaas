<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    use BelongsToEmpresa;

    protected $table = 'areas';

    protected $fillable = [
        'empresa_id', 'area_padre_id', 'nombre', 'codigo', 'descripcion', 'activa',
    ];

    protected $casts = [
        'activa' => 'boolean',
    ];

    public function padre()
    {
        return $this->belongsTo(Area::class, 'area_padre_id');
    }

    public function hijas()
    {
        return $this->hasMany(Area::class, 'area_padre_id')->orderBy('nombre');
    }

    public function puestos()
    {
        return $this->hasMany(Puesto::class)->orderBy('nombre');
    }

    /** IDs de esta área y todas sus descendientes */
    public function idsDescendientes(): array
    {
        $ids = [$this->id];
        foreach ($this->hijas as $hija) {
            $ids = array_merge($ids, $hija->idsDescendientes());
        }
        return $ids;
    }

    /** Ruta completa: Administración › Contaduría › Pagos */
    public function getRutaAttribute(): string
    {
        $partes = [$this->nombre];
        $actual = $this->padre;
        $guard  = 0;
        while ($actual && $guard++ < 20) {
            array_unshift($partes, $actual->nombre);
            $actual = $actual->padre;
        }
        return implode(' › ', $partes);
    }

    /**
     * Devuelve todas las áreas ordenadas como árbol, con ->nivel cargado.
     */
    public static function arbol(bool $soloActivas = false)
    {
        $todas = static::query()
            ->when($soloActivas, fn ($q) => $q->where('activa', true))
            ->withCount(['puestos', 'hijas'])
            ->orderBy('nombre')
            ->get();

        $porPadre = $todas->groupBy(fn ($a) => $a->area_padre_id ?? 0);
        $ids      = $todas->pluck('id')->all();
        $salida   = collect();

        $recorrer = function ($padreId, $nivel) use (&$recorrer, $porPadre, $salida) {
            foreach ($porPadre->get($padreId, collect()) as $area) {
                $area->nivel = $nivel;
                $salida->push($area);
                $recorrer($area->id, $nivel + 1);
            }
        };

        // Raíces: sin padre o con padre fuera del conjunto (ej. padre inactivo filtrado)
        $raices = $todas->filter(fn ($a) => ! $a->area_padre_id || ! in_array($a->area_padre_id, $ids));
        foreach ($raices as $raiz) {
            $raiz->nivel = 0;
            $salida->push($raiz);
            $recorrer($raiz->id, 1);
        }

        return $salida;
    }
}
