<?php

namespace App\Http\Controllers\Portal;

use App\Models\Recibo;
use Illuminate\Http\Request;

class RecibosController extends PortalController
{
    public function index()
    {
        $recibos = Recibo::with('liquidacion')
            ->where('empleado_id', $this->empleado()->id)
            ->whereHas('liquidacion', fn ($q) => $q->where('estado', 'cerrada'))
            ->get()
            ->sortByDesc(fn ($r) => sprintf('%04d%02d%s', $r->liquidacion->anio, $r->liquidacion->mes, $r->liquidacion->tipo === 'sac' ? 'b' : 'a'))
            ->values();

        return view('portal.recibos', compact('recibos'));
    }

    public function ver(Recibo $recibo)
    {
        $this->autorizar($recibo);

        return view('liquidaciones.recibos', [
            'liquidacion' => $recibo->liquidacion,
            'recibos'     => collect([$recibo->load('items')]),
            'empresa'     => auth()->user()->empresa,
        ]);
    }

    /** El empleado deja constancia de que recibió el recibo y está conforme */
    public function conformar(Request $request, Recibo $recibo)
    {
        $this->autorizar($recibo);

        if (! $recibo->conformado_at) {
            $recibo->update(['conformado_at' => now(), 'conformado_ip' => $request->ip()]);
        }

        return back()->with('ok', 'Registramos tu conformidad con el recibo de ' . $recibo->liquidacion->periodo_texto . '.');
    }

    private function autorizar(Recibo $recibo): void
    {
        $this->esMio($recibo);
        abort_unless($recibo->liquidacion?->estado === 'cerrada', 404);
    }
}
