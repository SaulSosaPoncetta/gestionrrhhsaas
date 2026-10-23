<?php

namespace App\Models\Concerns;

use App\Models\Empresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Aislamiento multi-empresa.
 * Todo modelo que use este trait:
 *  - se filtra automáticamente por la empresa del usuario logueado
 *  - completa empresa_id al crearse
 * El superadmin no tiene filtro (ve todo).
 */
trait BelongsToEmpresa
{
    protected static function bootBelongsToEmpresa(): void
    {
        static::addGlobalScope('empresa', function (Builder $query) {
            $user = Auth::user();
            if ($user && ! $user->esSuperadmin()) {
                $query->where($query->getModel()->getTable() . '.empresa_id', $user->empresa_id);
            }
        });

        static::creating(function ($model) {
            $user = Auth::user();
            if (empty($model->empresa_id) && $user && $user->empresa_id) {
                $model->empresa_id = $user->empresa_id;
            }
        });
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }
}
