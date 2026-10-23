<?php

namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Models\Horario;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HorarioController extends Controller
{
    public function index()
    {
        $horarios = Horario::withCount(['empleados' => fn ($q) => $q->activos()])
            ->orderBy('nombre')
            ->get();

        return view('horarios.index', compact('horarios'));
    }

    public function create()
    {
        $dias = [];
        foreach (range(1, 5) as $d) {
            $dias[$d] = ['entrada' => '08:00', 'salida' => '17:00'];
        }

        return view('horarios.form', [
            'horario' => new Horario([
                'dias'                 => $dias,
                'tolerancia_minutos'   => 10,
                'minimo_extra_minutos' => 30,
                'activo'               => true,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        Horario::create($this->validar($request));

        return redirect()->route('horarios.index')->with('ok', 'Horario creado correctamente.');
    }

    public function edit(Horario $horario)
    {
        return view('horarios.form', compact('horario'));
    }

    public function update(Request $request, Horario $horario)
    {
        $horario->update($this->validar($request, $horario));

        return redirect()->route('horarios.index')->with('ok', 'Horario actualizado correctamente.');
    }

    public function destroy(Horario $horario)
    {
        if (Empleado::withTrashed()->where('horario_id', $horario->id)->exists()) {
            return back()->with('error', 'No se puede eliminar: hay empleados con este horario. Podés desactivarlo.');
        }

        $horario->delete();

        return redirect()->route('horarios.index')->with('ok', 'Horario eliminado.');
    }

    private function validar(Request $request, ?Horario $horario = null): array
    {
        $datos = $request->validate([
            'nombre'               => [
                'required', 'string', 'max:255',
                Rule::unique('horarios', 'nombre')
                    ->where('empresa_id', auth()->user()->empresa_id)
                    ->ignore($horario?->id),
            ],
            'tolerancia_minutos'   => ['required', 'integer', 'min:0', 'max:120'],
            'minimo_extra_minutos' => ['required', 'integer', 'min:0', 'max:240'],
            'dias'                 => ['nullable', 'array'],
            'dias.*.entrada'       => ['nullable', 'date_format:H:i'],
            'dias.*.salida'        => ['nullable', 'date_format:H:i'],
        ], [
            'nombre.required'             => 'El nombre es obligatorio.',
            'nombre.unique'               => 'Ya existe un horario con ese nombre.',
            'tolerancia_minutos.max'      => 'La tolerancia no puede superar los 120 minutos.',
            'minimo_extra_minutos.max'    => 'El mínimo para horas extra no puede superar los 240 minutos.',
            'dias.*.entrada.date_format'  => 'La hora de entrada no es válida.',
            'dias.*.salida.date_format'   => 'La hora de salida no es válida.',
        ]);

        $dias    = [];
        $errores = [];

        foreach (Horario::DIAS as $n => $nombre) {
            $d = $request->input("dias.{$n}", []);
            if (empty($d['trabaja'])) {
                continue;
            }
            if (empty($d['entrada']) || empty($d['salida'])) {
                $errores["dias.{$n}"] = "Completá la entrada y la salida del {$nombre}.";
            } elseif ($d['entrada'] === $d['salida']) {
                $errores["dias.{$n}"] = "El {$nombre} la entrada y la salida no pueden ser iguales.";
            } else {
                $dias[$n] = ['entrada' => $d['entrada'], 'salida' => $d['salida']];
            }
        }

        if (! $dias && ! $errores) {
            $errores['dias'] = 'Marcá al menos un día de trabajo.';
        }
        if ($errores) {
            throw ValidationException::withMessages($errores);
        }

        $datos['dias']   = $dias;
        $datos['activo'] = $request->boolean('activo');

        return $datos;
    }
}
