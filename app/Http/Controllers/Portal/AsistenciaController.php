<?php

namespace App\Http\Controllers\Portal;

use App\Services\Asistencia\CalculadorAsistencia;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AsistenciaController extends PortalController
{
    public function index(Request $request, CalculadorAsistencia $calculador)
    {
        $mes = $request->get('mes');
        try {
            $desde = $mes && preg_match('/^\d{4}-\d{2}$/', $mes)
                ? Carbon::createFromFormat('Y-m-d', "{$mes}-01")->startOfDay()
                : today()->startOfMonth();
        } catch (\Throwable) {
            $desde = today()->startOfMonth();
        }

        $e    = $this->empleado()->load('horario');
        $dias = $calculador->calcular($e, $desde, $desde->copy()->endOfMonth()->startOfDay());

        return view('portal.asistencia', [
            'empleado' => $e,
            'dias'     => $dias,
            'totales'  => CalculadorAsistencia::totales($dias),
            'desde'    => $desde,
            'mes'      => $desde->format('Y-m'),
        ]);
    }
}
