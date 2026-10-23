<?php

namespace App\Http\Controllers\Portal;

use App\Models\Empleado;
use App\Models\Fichada;
use App\Models\Licencia;
use App\Models\Recibo;
use App\Services\Asistencia\CalculadorAsistencia;
use App\Services\Licencias\CalculadorVacaciones;
use Illuminate\Http\Request;

class InicioController extends PortalController
{
    public function index(CalculadorAsistencia $asistencia, CalculadorVacaciones $vacaciones)
    {
        $e = $this->empleado()->load(['horario', 'puesto', 'area']);

        $hoy     = $asistencia->calcular($e, today(), today())[today()->toDateString()];
        $periodo = CalculadorVacaciones::periodoActual();

        $licencias = Licencia::with('tipo')
            ->where('empleado_id', $e->id)
            ->activas()
            ->whereDate('fecha_hasta', '>=', today()->toDateString())
            ->orderBy('fecha_desde')
            ->limit(5)
            ->get();

        $recibos = Recibo::with('liquidacion')
            ->where('empleado_id', $e->id)
            ->whereHas('liquidacion', fn ($q) => $q->where('estado', 'cerrada'))
            ->orderByDesc('id')
            ->limit(3)
            ->get();

        $pendientesEquipo = auth()->user()->esSupervisor()
            ? Licencia::where('estado', Licencia::PENDIENTE)
                ->whereIn('empleado_id', Empleado::activos()->where('supervisor_id', $e->id)->pluck('id'))
                ->count()
            : 0;

        return view('portal.inicio', [
            'empleado'         => $e,
            'hoy'              => $hoy,
            'proximo'          => self::proximoTipo($e),
            'saldo'            => $vacaciones->saldo($e, $periodo),
            'periodo'          => $periodo,
            'licencias'        => $licencias,
            'recibos'          => $recibos,
            'pendientesEquipo' => $pendientesEquipo,
        ]);
    }

    /** Fichaje desde el portal web */
    public function fichar(Request $request)
    {
        $e = $this->empleado();

        abort_unless($e->permite_fichaje_web, 403, 'No tenés habilitado el fichaje desde la web.');

        $ultima = Fichada::where('empleado_id', $e->id)->orderByDesc('fecha_hora')->first();
        if ($ultima && $ultima->fecha_hora->gt(now()->subMinute())) {
            return back()->with('error', 'Ya registraste una fichada hace menos de un minuto.');
        }

        $tipo  = self::proximoTipo($e);
        $ahora = now()->startOfSecond();

        Fichada::create([
            'empleado_id'    => $e->id,
            'fecha_hora'     => $ahora,
            'tipo'           => $tipo,
            'origen'         => 'web',
            'registrado_por' => auth()->id(),
            'observacion'    => 'Portal web · IP ' . $request->ip(),
        ]);

        return back()->with('ok', ($tipo === Fichada::ENTRADA ? 'Entrada' : 'Salida') . ' registrada a las ' . $ahora->format('H:i') . '.');
    }

    /** Después de una entrada (de las últimas 16 hs) toca salida; si no, entrada */
    public static function proximoTipo(Empleado $e): string
    {
        $ultima = Fichada::where('empleado_id', $e->id)->orderByDesc('fecha_hora')->first();

        return $ultima && $ultima->esEntrada() && $ultima->fecha_hora->gt(now()->subHours(CalculadorAsistencia::MAX_HORAS_JORNADA))
            ? Fichada::SALIDA
            : Fichada::ENTRADA;
    }
}
