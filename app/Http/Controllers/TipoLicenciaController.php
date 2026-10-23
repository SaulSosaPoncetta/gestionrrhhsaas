<?php

namespace App\Http\Controllers;

use App\Models\TipoLicencia;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TipoLicenciaController extends Controller
{
    public function index()
    {
        $tipos = TipoLicencia::withCount('licencias')->orderByDesc('es_vacaciones')->orderBy('nombre')->get();

        return view('tipos_licencia.index', compact('tipos'));
    }

    public function create()
    {
        return view('tipos_licencia.form', [
            'tipo' => new TipoLicencia(['unidad' => 'corridos', 'con_goce' => true, 'color' => 'info', 'activo' => true]),
        ]);
    }

    public function store(Request $request)
    {
        TipoLicencia::create($this->validar($request));

        return redirect()->route('tipos-licencia.index')->with('ok', 'Tipo de licencia creado.');
    }

    public function edit(TipoLicencia $tipo)
    {
        return view('tipos_licencia.form', compact('tipo'));
    }

    public function update(Request $request, TipoLicencia $tipo)
    {
        $tipo->update($this->validar($request, $tipo));

        return redirect()->route('tipos-licencia.index')->with('ok', 'Tipo de licencia actualizado.');
    }

    public function destroy(TipoLicencia $tipo)
    {
        if ($tipo->licencias()->exists()) {
            return back()->with('error', 'No se puede eliminar: hay licencias de este tipo. Podés desactivarlo.');
        }

        $tipo->delete();

        return redirect()->route('tipos-licencia.index')->with('ok', 'Tipo de licencia eliminado.');
    }

    public function predeterminados()
    {
        $agregados = TipoLicencia::cargarPredeterminados(auth()->user()->empresa_id);

        return redirect()->route('tipos-licencia.index')->with('ok', $agregados
            ? "Se agregaron {$agregados} tipos de licencia de la LCT."
            : 'Los tipos de la LCT ya estaban cargados.');
    }

    private function validar(Request $request, ?TipoLicencia $tipo = null): array
    {
        $empresaId = auth()->user()->empresa_id;

        $datos = $request->validate([
            'nombre'       => ['required', 'string', 'max:255',
                               Rule::unique('tipos_licencia', 'nombre')->where('empresa_id', $empresaId)->ignore($tipo?->id)],
            'codigo'       => ['nullable', 'string', 'max:20'],
            'unidad'       => ['required', Rule::in(array_keys(TipoLicencia::UNIDADES))],
            'dias_maximos' => ['nullable', 'integer', 'min:1', 'max:365'],
            'maximo_anual' => ['nullable', 'integer', 'min:1', 'max:365'],
            'color'        => ['required', Rule::in(array_keys(TipoLicencia::COLORES))],
            'descripcion'  => ['nullable', 'string', 'max:255'],
        ], [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.unique'   => 'Ya existe un tipo de licencia con ese nombre.',
        ]);

        foreach (['con_goce', 'requiere_certificado', 'es_vacaciones', 'activo'] as $campo) {
            $datos[$campo] = $request->boolean($campo);
        }

        if ($datos['es_vacaciones']) {
            $otro = TipoLicencia::where('es_vacaciones', true)->when($tipo, fn ($q) => $q->where('id', '!=', $tipo->id))->first();
            if ($otro) {
                throw ValidationException::withMessages([
                    'es_vacaciones' => "Ya hay un tipo marcado como vacaciones: «{$otro->nombre}».",
                ]);
            }
        }

        return $datos;
    }
}
