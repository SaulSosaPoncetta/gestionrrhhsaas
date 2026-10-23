<?php

namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Models\EmpleadoFamiliar;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmpleadoFamiliarController extends Controller
{
    public function store(Request $request, Empleado $empleado)
    {
        $datos = $this->validar($request);
        $datos['empleado_id'] = $empleado->id;

        EmpleadoFamiliar::create($datos);

        return redirect()->to(route('empleados.show', $empleado) . '#familia')->with('ok', 'Familiar agregado.');
    }

    public function update(Request $request, Empleado $empleado, EmpleadoFamiliar $familiar)
    {
        $this->verificar($empleado, $familiar);
        $familiar->update($this->validar($request));

        return redirect()->to(route('empleados.show', $empleado) . '#familia')->with('ok', 'Familiar actualizado.');
    }

    public function destroy(Empleado $empleado, EmpleadoFamiliar $familiar)
    {
        $this->verificar($empleado, $familiar);
        $familiar->delete();

        return redirect()->to(route('empleados.show', $empleado) . '#familia')->with('ok', 'Familiar eliminado.');
    }

    private function verificar(Empleado $empleado, EmpleadoFamiliar $familiar): void
    {
        abort_unless((int) $familiar->empleado_id === (int) $empleado->id, 404);
    }

    private function validar(Request $request): array
    {
        $datos = $request->validate([
            'parentesco'       => ['required', Rule::in(array_keys(EmpleadoFamiliar::PARENTESCOS))],
            'apellido_nombre'  => ['required', 'string', 'max:255'],
            'dni'              => ['nullable', 'digits_between:7,8'],
            'fecha_nacimiento' => ['nullable', 'date', 'before_or_equal:today'],
        ], [
            'parentesco.required'      => 'Seleccioná el parentesco.',
            'apellido_nombre.required' => 'Ingresá el apellido y nombre del familiar.',
            'dni.digits_between'       => 'El DNI debe tener 7 u 8 dígitos.',
        ]);

        $datos['a_cargo']      = $request->boolean('a_cargo');
        $datos['discapacidad'] = $request->boolean('discapacidad');

        return $datos;
    }
}
