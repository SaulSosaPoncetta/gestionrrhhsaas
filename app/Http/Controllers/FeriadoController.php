<?php

namespace App\Http\Controllers;

use App\Models\Feriado;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FeriadoController extends Controller
{
    public function index(Request $request)
    {
        $anio = (int) $request->get('anio', now()->year);
        if ($anio < 2000 || $anio > 2100) {
            $anio = now()->year;
        }

        $feriados = Feriado::whereYear('fecha', $anio)->orderBy('fecha')->get();

        return view('feriados.index', compact('feriados', 'anio'));
    }

    public function store(Request $request)
    {
        $empresaId = auth()->user()->empresa_id;

        $datos = $request->validate([
            'fecha'       => ['required', 'date', Rule::unique('feriados', 'fecha')->where('empresa_id', $empresaId)],
            'descripcion' => ['required', 'string', 'max:255'],
            'tipo'        => ['required', Rule::in(array_keys(Feriado::TIPOS))],
        ], [
            'fecha.required'       => 'Ingresá la fecha.',
            'fecha.unique'         => 'Esa fecha ya está cargada.',
            'descripcion.required' => 'Ingresá la descripción.',
        ]);

        $feriado = Feriado::create($datos);

        return redirect()->route('feriados.index', ['anio' => $feriado->fecha->year])->with('ok', 'Feriado agregado.');
    }

    public function destroy(Feriado $feriado)
    {
        $anio = $feriado->fecha->year;
        $feriado->delete();

        return redirect()->route('feriados.index', ['anio' => $anio])->with('ok', 'Feriado eliminado.');
    }

    public function cargarFijos(Request $request)
    {
        $anio = (int) $request->validate(['anio' => ['required', 'integer', 'min:2000', 'max:2100']])['anio'];

        $agregados = Feriado::cargarFijos(auth()->user()->empresa_id, $anio);

        $mensaje = $agregados
            ? "Se agregaron {$agregados} feriados fijos de {$anio}. Recordá cargar los trasladables y los días puente."
            : "Los feriados fijos de {$anio} ya estaban cargados.";

        return redirect()->route('feriados.index', ['anio' => $anio])->with('ok', $mensaje);
    }
}
