<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Empleado;

/**
 * Base de los controladores del portal: todo se resuelve sobre el legajo del usuario logueado.
 */
abstract class PortalController extends Controller
{
    protected function empleado(): Empleado
    {
        return request()->attributes->get('miEmpleado');
    }

    /** Corta con 404 si el registro no es del empleado logueado */
    protected function esMio($registro): void
    {
        abort_unless($registro && (int) $registro->empleado_id === (int) $this->empleado()->id, 404);
    }
}
