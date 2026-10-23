<?php

namespace App\Http\Controllers\Portal;

use App\Models\Empleado;
use App\Models\Licencia;
use App\Services\Asistencia\CalculadorAsistencia;
use Illuminate\Http\Request;

/**
 * Supervisores: ven a su personal a cargo directo y resuelven sus licencias.
 */
class EquipoController extends PortalController
{
    public function index(CalculadorAsistencia $calculador)
    {
        $equipo = $this->equipo();
        $hoy    = $calculador->calcularVarios($equipo, today(), today());

        $pendientes = Licencia::with(['empleado', 'tipo'])
            ->where('estado', Licencia::PENDIENTE)
            ->whereIn('empleado_id', $equipo->pluck('id'))
            ->orderBy('fecha_desde')
            ->get();

        $proximas = Licencia::with(['empleado', 'tipo'])
            ->aprobadas()
            ->whereIn('empleado_id', $equipo->pluck('id'))
            ->entre(today(), today()->addDays(30))
            ->orderBy('fecha_desde')
            ->get();

        return view('portal.equipo', [
            'filas'      => $equipo->map(fn ($e) => ['empleado' => $e, 'dia' => $hoy[$e->id][today()->toDateString()]]),
            'pendientes' => $pendientes,
            'proximas'   => $proximas,
        ]);
    }

    public function aprobar(Licencia $licencia)
    {
        $this->deMiEquipo($licencia);

        if (! $licencia->esPendiente()) {
            return back()->with('error', 'La solicitud ya fue resuelta.');
        }

        $licencia->update(['estado' => Licencia::APROBADA, 'resuelta_por' => auth()->id(), 'resuelta_at' => now(), 'motivo_rechazo' => null]);

        return back()->with('ok', "Aprobaste la licencia de {$licencia->empleado->nombre_completo}.");
    }

    public function rechazar(Request $request, Licencia $licencia)
    {
        $this->deMiEquipo($licencia);

        if (! $licencia->esPendiente()) {
            return back()->with('error', 'La solicitud ya fue resuelta.');
        }

        $request->validate(['motivo_rechazo' => ['nullable', 'string', 'max:255']]);

        $licencia->update([
            'estado'         => Licencia::RECHAZADA,
            'resuelta_por'   => auth()->id(),
            'resuelta_at'    => now(),
            'motivo_rechazo' => $request->motivo_rechazo,
        ]);

        return back()->with('ok', 'Solicitud rechazada.');
    }

    private function equipo()
    {
        return Empleado::with(['horario', 'puesto'])
            ->activos()
            ->where('supervisor_id', $this->empleado()->id)
            ->orderBy('apellido')->orderBy('nombre')
            ->get();
    }

    private function deMiEquipo(Licencia $licencia): void
    {
        $licencia->loadMissing('empleado');
        abort_unless($licencia->empleado && (int) $licencia->empleado->supervisor_id === (int) $this->empleado()->id, 404);
    }
}
