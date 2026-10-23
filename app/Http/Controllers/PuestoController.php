<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Puesto;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PuestoController extends Controller
{
    public function index(Request $request)
    {
        $buscar = trim((string) $request->get('buscar'));
        $areaId = $request->get('area_id');

        $puestos = Puesto::with('area')
            ->when($areaId === 'sin', fn ($q) => $q->whereNull('area_id'))
            ->when($areaId && $areaId !== 'sin', fn ($q) => $q->where('area_id', $areaId))
            ->when($buscar, fn ($q) => $q->where('nombre', 'like', "%{$buscar}%"))
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString();

        return view('puestos.index', [
            'puestos' => $puestos,
            'buscar'  => $buscar,
            'areaId'  => $areaId,
            'areas'   => Area::arbol(),
        ]);
    }

    public function create(Request $request)
    {
        return view('puestos.form', [
            'puesto' => new Puesto(['activo' => true, 'area_id' => $request->get('area')]),
            'areas'  => Area::arbol(true),
        ]);
    }

    public function store(Request $request)
    {
        Puesto::create($this->validar($request));

        return redirect()->route('puestos.index')->with('ok', 'Puesto creado correctamente.');
    }

    public function edit(Puesto $puesto)
    {
        return view('puestos.form', [
            'puesto' => $puesto,
            'areas'  => Area::arbol(true),
        ]);
    }

    public function update(Request $request, Puesto $puesto)
    {
        $puesto->update($this->validar($request, $puesto));

        return redirect()->route('puestos.index')->with('ok', 'Puesto actualizado correctamente.');
    }

    public function destroy(Puesto $puesto)
    {
        if (\App\Models\Empleado::withTrashed()->where('puesto_id', $puesto->id)->exists()) {
            return back()->with('error', 'No se puede eliminar: hay empleados con este puesto. Podés desactivarlo.');
        }

        $puesto->delete();

        return redirect()->route('puestos.index')->with('ok', 'Puesto eliminado.');
    }

    private function validar(Request $request, ?Puesto $puesto = null): array
    {
        $empresaId = auth()->user()->empresa_id;

        $datos = $request->validate([
            'nombre'      => [
                'required', 'string', 'max:255',
                Rule::unique('puestos', 'nombre')
                    ->where('empresa_id', $empresaId)
                    ->where('area_id', $request->input('area_id'))
                    ->ignore($puesto?->id),
            ],
            'area_id'     => ['nullable', Rule::exists('areas', 'id')->where('empresa_id', $empresaId)],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'requisitos'  => ['nullable', 'string', 'max:2000'],
        ], [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.unique'   => 'Ya existe un puesto con ese nombre en el área.',
            'area_id.exists'  => 'El área no es válida.',
        ]);

        $datos['activo'] = $request->boolean('activo');

        return $datos;
    }
}
