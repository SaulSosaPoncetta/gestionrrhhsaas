<?php

namespace App\Http\Controllers;

use App\Models\Area;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AreaController extends Controller
{
    public function index(Request $request)
    {
        $buscar = trim((string) $request->get('buscar'));

        if ($buscar) {
            $areas = Area::with('padre')
                ->withCount(['puestos', 'hijas'])
                ->where(function ($q) use ($buscar) {
                    $q->where('nombre', 'like', "%{$buscar}%")
                      ->orWhere('codigo', 'like', "%{$buscar}%");
                })
                ->orderBy('nombre')
                ->get()
                ->each(fn ($a) => $a->nivel = 0);
        } else {
            $areas = Area::arbol();
        }

        return view('areas.index', compact('areas', 'buscar'));
    }

    public function create(Request $request)
    {
        return view('areas.form', [
            'area'    => new Area(['activa' => true, 'area_padre_id' => $request->get('padre')]),
            'padres'  => Area::arbol(true),
        ]);
    }

    public function store(Request $request)
    {
        Area::create($this->validar($request));

        return redirect()->route('areas.index')->with('ok', 'Área creada correctamente.');
    }

    public function edit(Area $area)
    {
        // No se puede elegir como padre a sí misma ni a sus descendientes
        $excluir = $area->idsDescendientes();

        return view('areas.form', [
            'area'   => $area,
            'padres' => Area::arbol(true)->reject(fn ($a) => in_array($a->id, $excluir)),
        ]);
    }

    public function update(Request $request, Area $area)
    {
        $area->update($this->validar($request, $area));

        return redirect()->route('areas.index')->with('ok', 'Área actualizada correctamente.');
    }

    public function destroy(Area $area)
    {
        if ($area->hijas()->exists()) {
            return back()->with('error', 'No se puede eliminar: el área tiene sub-áreas. Movelas o eliminalas primero.');
        }
        if ($area->puestos()->exists()) {
            return back()->with('error', 'No se puede eliminar: el área tiene puestos asignados.');
        }
        if (\App\Models\Empleado::withTrashed()->where('area_id', $area->id)->exists()) {
            return back()->with('error', 'No se puede eliminar: hay empleados asignados a esta área. Podés desactivarla.');
        }

        $area->delete();

        return redirect()->route('areas.index')->with('ok', 'Área eliminada.');
    }

    private function validar(Request $request, ?Area $area = null): array
    {
        $empresaId = auth()->user()->empresa_id;
        $excluir   = $area ? $area->idsDescendientes() : [];

        $datos = $request->validate([
            'nombre'        => [
                'required', 'string', 'max:255',
                Rule::unique('areas', 'nombre')->where('empresa_id', $empresaId)->ignore($area?->id),
            ],
            'codigo'        => ['nullable', 'string', 'max:20'],
            'area_padre_id' => [
                'nullable',
                Rule::exists('areas', 'id')->where('empresa_id', $empresaId),
                Rule::notIn($excluir),
            ],
            'descripcion'   => ['nullable', 'string', 'max:2000'],
        ], [
            'nombre.required'      => 'El nombre es obligatorio.',
            'nombre.unique'        => 'Ya existe un área con ese nombre.',
            'area_padre_id.exists' => 'El área superior no es válida.',
            'area_padre_id.not_in' => 'Un área no puede depender de sí misma ni de una de sus sub-áreas.',
        ]);

        $datos['activa'] = $request->boolean('activa');

        return $datos;
    }
}
