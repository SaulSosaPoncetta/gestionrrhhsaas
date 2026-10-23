<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Convenio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CategoriaController extends Controller
{
    public function index(Convenio $convenio)
    {
        $convenio->load('categorias');

        return view('categorias.index', compact('convenio'));
    }

    public function create(Convenio $convenio)
    {
        $categoria = new Categoria([
            'activa'         => true,
            'vigencia_desde' => now()->startOfMonth(),
            'orden'          => ($convenio->categorias()->max('orden') ?? 0) + 1,
        ]);

        return view('categorias.form', compact('convenio', 'categoria'));
    }

    public function store(Request $request, Convenio $convenio)
    {
        $datos = $this->validar($request, $convenio);
        $datos['convenio_id'] = $convenio->id;

        Categoria::create($datos);

        return redirect()->route('convenios.categorias.index', $convenio)->with('ok', 'Categoría creada.');
    }

    public function edit(Categoria $categoria)
    {
        $convenio = $categoria->convenio;

        return view('categorias.form', compact('convenio', 'categoria'));
    }

    public function update(Request $request, Categoria $categoria)
    {
        $categoria->update($this->validar($request, $categoria->convenio, $categoria));

        return redirect()->route('convenios.categorias.index', $categoria->convenio_id)->with('ok', 'Categoría actualizada.');
    }

    public function destroy(Categoria $categoria)
    {
        if (\App\Models\Empleado::withTrashed()->where('categoria_id', $categoria->id)->exists()) {
            return back()->with('error', 'No se puede eliminar: hay empleados en esta categoría. Podés desactivarla.');
        }

        $convenioId = $categoria->convenio_id;
        $categoria->delete();

        return redirect()->route('convenios.categorias.index', $convenioId)->with('ok', 'Categoría eliminada.');
    }

    /**
     * Aumento paritario: aplica un % a todos los básicos del convenio con nueva vigencia.
     */
    public function aumento(Request $request, Convenio $convenio)
    {
        $datos = $request->validate([
            'porcentaje'     => ['required', 'numeric', 'min:-50', 'max:500', 'not_in:0'],
            'vigencia_desde' => ['required', 'date'],
        ], [
            'porcentaje.required'     => 'Ingresá el porcentaje de aumento.',
            'porcentaje.not_in'       => 'El porcentaje no puede ser 0.',
            'vigencia_desde.required' => 'Ingresá la fecha de vigencia.',
        ]);

        $factor = 1 + ($datos['porcentaje'] / 100);

        DB::transaction(function () use ($convenio, $factor, $datos) {
            foreach ($convenio->categorias as $cat) {
                $cat->update([
                    'sueldo_basico'  => round($cat->sueldo_basico * $factor, 2),
                    'vigencia_desde' => $datos['vigencia_desde'],
                ]);
            }
        });

        return back()->with('ok', "Se aplicó un {$datos['porcentaje']}% a todas las categorías.");
    }

    private function validar(Request $request, Convenio $convenio, ?Categoria $categoria = null): array
    {
        $datos = $request->validate([
            'nombre'         => [
                'required', 'string', 'max:255',
                Rule::unique('categorias', 'nombre')->where('convenio_id', $convenio->id)->ignore($categoria?->id),
            ],
            'sueldo_basico'  => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'vigencia_desde' => ['nullable', 'date'],
            'orden'          => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [
            'nombre.required'        => 'El nombre es obligatorio.',
            'nombre.unique'          => 'Ya existe una categoría con ese nombre en este convenio.',
            'sueldo_basico.required' => 'El sueldo básico es obligatorio.',
            'sueldo_basico.numeric'  => 'El sueldo básico debe ser un número.',
        ]);

        $datos['orden']  = $datos['orden'] ?? 0;
        $datos['activa'] = $request->boolean('activa');

        return $datos;
    }
}
