<?php

namespace App\Http\Controllers;

use App\Models\Convenio;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConvenioController extends Controller
{
    public function index()
    {
        $convenios = Convenio::withCount('categorias')->orderBy('nombre')->get();

        return view('convenios.index', compact('convenios'));
    }

    public function create()
    {
        return view('convenios.form', [
            'convenio' => new Convenio(['activo' => true]),
        ]);
    }

    public function store(Request $request)
    {
        $convenio = Convenio::create($this->validar($request));

        return redirect()->route('convenios.categorias.index', $convenio)
            ->with('ok', 'Convenio creado. Ahora cargá sus categorías.');
    }

    public function edit(Convenio $convenio)
    {
        return view('convenios.form', compact('convenio'));
    }

    public function update(Request $request, Convenio $convenio)
    {
        $convenio->update($this->validar($request, $convenio));

        return redirect()->route('convenios.index')->with('ok', 'Convenio actualizado correctamente.');
    }

    public function destroy(Convenio $convenio)
    {
        if ($convenio->categorias()->exists()) {
            return back()->with('error', 'No se puede eliminar: el convenio tiene categorías cargadas.');
        }

        $convenio->delete();

        return redirect()->route('convenios.index')->with('ok', 'Convenio eliminado.');
    }

    private function validar(Request $request, ?Convenio $convenio = null): array
    {
        $datos = $request->validate([
            'nombre'          => [
                'required', 'string', 'max:255',
                Rule::unique('convenios', 'nombre')
                    ->where('empresa_id', auth()->user()->empresa_id)
                    ->ignore($convenio?->id),
            ],
            'numero'          => ['nullable', 'string', 'max:30'],
            'sindicato'       => ['nullable', 'string', 'max:255'],
            'horas_semanales' => ['nullable', 'numeric', 'min:1', 'max:80'],
            'observaciones'   => ['nullable', 'string', 'max:2000'],
        ], [
            'nombre.required'     => 'El nombre es obligatorio.',
            'nombre.unique'       => 'Ya existe un convenio con ese nombre.',
            'horas_semanales.max' => 'Las horas semanales no pueden superar 80.',
        ]);

        $datos['activo'] = $request->boolean('activo');

        return $datos;
    }
}
