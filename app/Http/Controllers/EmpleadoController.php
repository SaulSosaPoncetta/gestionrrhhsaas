<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Categoria;
use App\Models\Convenio;
use App\Models\Empleado;
use App\Models\EmpleadoDocumento;
use App\Models\EmpleadoFamiliar;
use App\Models\Horario;
use App\Models\Puesto;
use App\Services\Licencias\CalculadorVacaciones;
use App\Support\Validadores;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class EmpleadoController extends Controller
{
    public function index(Request $request)
    {
        $buscar = trim((string) $request->get('buscar'));
        $areaId = $request->get('area_id');
        $estado = $request->get('estado', 'activos');

        $empleados = Empleado::with(['area', 'puesto', 'categoria'])
            ->when($estado === 'activos', fn ($q) => $q->activos())
            ->when($estado === 'bajas', fn ($q) => $q->deBaja())
            ->when($areaId, fn ($q) => $q->where('area_id', $areaId))
            ->when($buscar, function ($q) use ($buscar) {
                $soloNumeros = preg_replace('/\D/', '', $buscar);
                $q->where(function ($q) use ($buscar, $soloNumeros) {
                    $q->where('apellido', 'like', "%{$buscar}%")
                      ->orWhere('nombre', 'like', "%{$buscar}%")
                      ->orWhereRaw("CONCAT(apellido, ' ', nombre) LIKE ?", ["%{$buscar}%"]);
                    if ($soloNumeros !== '') {
                        $q->orWhere('legajo', $soloNumeros)
                          ->orWhere('dni', 'like', "%{$soloNumeros}%")
                          ->orWhereRaw("REPLACE(cuil, '-', '') LIKE ?", ["%{$soloNumeros}%"]);
                    }
                });
            })
            ->orderBy('apellido')->orderBy('nombre')
            ->paginate(20)
            ->withQueryString();

        return view('empleados.index', [
            'empleados' => $empleados,
            'buscar'    => $buscar,
            'areaId'    => $areaId,
            'estado'    => $estado,
            'areas'     => Area::arbol(),
        ]);
    }

    public function create()
    {
        $empleado = new Empleado([
            'legajo'        => Empleado::proximoLegajo(),
            'fecha_ingreso' => now(),
            'modalidad'     => 'indeterminado',
            'jornada'       => 'completa',
            'nacionalidad'  => 'Argentina',
        ]);

        return view('empleados.form', $this->datosFormulario($empleado));
    }

    public function store(Request $request)
    {
        $datos = $this->validar($request);

        if ($request->hasFile('foto')) {
            $datos['foto'] = $request->file('foto')->store('empleados/fotos', 'public');
        }

        $empleado = Empleado::create($datos);

        return redirect()->route('empleados.show', $empleado)->with('ok', 'Legajo creado correctamente.');
    }

    public function show(Empleado $empleado, CalculadorVacaciones $vacaciones)
    {
        $empleado->load(['area', 'puesto', 'convenio', 'categoria', 'horario', 'supervisor', 'familiares', 'documentos.subidoPor', 'aCargo', 'usuario']);

        $periodo = CalculadorVacaciones::periodoActual();

        return view('empleados.show', [
            'empleado'          => $empleado,
            'parentescos'       => EmpleadoFamiliar::PARENTESCOS,
            'tiposDocumento'    => EmpleadoDocumento::TIPOS,
            'motivosEgreso'     => Empleado::MOTIVOS_EGRESO,
            'licencias'         => $empleado->licencias()->with('tipo')->limit(15)->get(),
            'periodoVacaciones' => $periodo,
            'saldoVacaciones'   => $vacaciones->saldo($empleado, $periodo),
        ]);
    }

    public function edit(Empleado $empleado)
    {
        return view('empleados.form', $this->datosFormulario($empleado));
    }

    public function update(Request $request, Empleado $empleado)
    {
        $datos = $this->validar($request, $empleado);

        if ($request->hasFile('foto')) {
            if ($empleado->foto) {
                Storage::disk('public')->delete($empleado->foto);
            }
            $datos['foto'] = $request->file('foto')->store('empleados/fotos', 'public');
        } elseif ($request->boolean('quitar_foto') && $empleado->foto) {
            Storage::disk('public')->delete($empleado->foto);
            $datos['foto'] = null;
        }

        $empleado->update($datos);

        return redirect()->route('empleados.show', $empleado)->with('ok', 'Legajo actualizado correctamente.');
    }

    public function destroy(Empleado $empleado)
    {
        $empleado->delete(); // soft delete: el legajo queda guardado

        return redirect()->route('empleados.index')->with('ok', 'Legajo eliminado.');
    }

    /** Registrar el egreso del empleado */
    public function baja(Request $request, Empleado $empleado)
    {
        $datos = $request->validate([
            'fecha_egreso'  => ['required', 'date', 'after_or_equal:' . $empleado->fecha_ingreso->format('Y-m-d')],
            'motivo_egreso' => ['required', Rule::in(array_keys(Empleado::MOTIVOS_EGRESO))],
        ], [
            'fecha_egreso.required'       => 'Ingresá la fecha de egreso.',
            'fecha_egreso.after_or_equal' => 'La fecha de egreso no puede ser anterior al ingreso.',
            'motivo_egreso.required'      => 'Seleccioná el motivo de egreso.',
        ]);

        $empleado->update($datos);

        // Si tenía usuario de autogestión, se desactiva
        $empleado->usuario?->update(['activo' => false]);

        return back()->with('ok', 'Se registró la baja del empleado.');
    }

    /** Deshacer la baja */
    public function reincorporar(Empleado $empleado)
    {
        $empleado->update(['fecha_egreso' => null, 'motivo_egreso' => null]);

        return back()->with('ok', 'El empleado volvió a estar activo.');
    }

    // ---------------------------------------------------------------

    private function datosFormulario(Empleado $empleado): array
    {
        return [
            'empleado'     => $empleado,
            'areas'        => Area::arbol(true),
            'puestos'      => Puesto::where('activo', true)->orderBy('nombre')->get(),
            'convenios'    => Convenio::where('activo', true)->orderBy('nombre')->get(),
            'categorias'   => Categoria::where('activa', true)->orderBy('orden')->orderBy('nombre')->get(),
            'horarios'     => Horario::where(function ($q) use ($empleado) {
                    $q->where('activo', true)
                      ->when($empleado->horario_id, fn ($q) => $q->orWhere('id', $empleado->horario_id));
                })
                ->orderBy('nombre')->get(),
            'supervisores' => Empleado::activos()
                ->when($empleado->exists, fn ($q) => $q->where('id', '!=', $empleado->id))
                ->orderBy('apellido')->orderBy('nombre')->get(),
        ];
    }

    private function validar(Request $request, ?Empleado $empleado = null): array
    {
        $empresaId = auth()->user()->empresa_id;
        $existe    = fn (string $tabla) => Rule::exists($tabla, 'id')->where('empresa_id', $empresaId);

        // Normalizar antes de validar
        $request->merge([
            'cuil' => Validadores::formatearCuit($request->input('cuil')),
            'dni'  => preg_replace('/\D/', '', (string) $request->input('dni')),
            'cbu'  => $request->filled('cbu') ? preg_replace('/\D/', '', $request->input('cbu')) : null,
        ]);

        $datos = $request->validate([
            'legajo'              => ['required', 'integer', 'min:1',
                                      Rule::unique('empleados', 'legajo')->where('empresa_id', $empresaId)->ignore($empleado?->id)],
            'apellido'            => ['required', 'string', 'max:255'],
            'nombre'              => ['required', 'string', 'max:255'],
            'cuil'                => ['required', 'regex:/^\d{2}-\d{8}-\d$/',
                                      Rule::unique('empleados', 'cuil')->where('empresa_id', $empresaId)->ignore($empleado?->id),
                                      function ($a, $v, $fail) {
                                          if (! Validadores::cuit($v)) $fail('El CUIL no es válido (dígito verificador incorrecto).');
                                      }],
            'dni'                 => ['required', 'digits_between:7,8'],
            'fecha_nacimiento'    => ['nullable', 'date', 'before:today'],
            'sexo'                => ['nullable', Rule::in(array_keys(Empleado::SEXOS))],
            'estado_civil'        => ['nullable', Rule::in(array_keys(Empleado::ESTADOS_CIVILES))],
            'nacionalidad'        => ['nullable', 'string', 'max:60'],
            'foto'                => ['nullable', 'image', 'max:2048'],

            'domicilio'           => ['nullable', 'string', 'max:255'],
            'localidad'           => ['nullable', 'string', 'max:255'],
            'provincia'           => ['nullable', 'string', 'max:255'],
            'codigo_postal'       => ['nullable', 'string', 'max:10'],
            'telefono'            => ['nullable', 'string', 'max:50'],
            'email'               => ['nullable', 'email', 'max:255'],
            'contacto_emergencia' => ['nullable', 'string', 'max:255'],
            'telefono_emergencia' => ['nullable', 'string', 'max:50'],

            'fecha_ingreso'       => ['required', 'date'],
            'area_id'             => ['nullable', $existe('areas')],
            'puesto_id'           => ['nullable', $existe('puestos')],
            'convenio_id'         => ['nullable', $existe('convenios')],
            'categoria_id'        => ['nullable', 'required_with:convenio_id',
                                      Rule::exists('categorias', 'id')
                                          ->where('empresa_id', $empresaId)
                                          ->where('convenio_id', $request->input('convenio_id'))],
            'supervisor_id'       => ['nullable', $existe('empleados'), Rule::notIn([$empleado?->id])],
            'modalidad'           => ['required', Rule::in(array_keys(Empleado::MODALIDADES))],
            'jornada'             => ['required', Rule::in(array_keys(Empleado::JORNADAS))],
            'horas_semanales'     => ['nullable', 'numeric', 'min:1', 'max:80'],
            'horario_id'          => ['nullable', $existe('horarios')],
            'sueldo_basico'       => ['nullable', 'required_without:convenio_id', 'numeric', 'min:0'],

            'banco'               => ['nullable', 'string', 'max:255'],
            'cbu'                 => ['nullable', function ($a, $v, $fail) {
                                          if ($v && ! Validadores::cbu($v)) $fail('El CBU no es válido.');
                                      }],
            'obra_social'         => ['nullable', 'string', 'max:255'],
            'codigo_obra_social'  => ['nullable', 'string', 'max:10'],
            'observaciones'       => ['nullable', 'string', 'max:5000'],
        ], [
            'legajo.unique'                  => 'Ya existe un empleado con ese número de legajo.',
            'apellido.required'              => 'El apellido es obligatorio.',
            'nombre.required'                => 'El nombre es obligatorio.',
            'cuil.required'                  => 'El CUIL es obligatorio.',
            'cuil.regex'                     => 'El CUIL debe tener 11 dígitos.',
            'cuil.unique'                    => 'Ya existe un empleado con ese CUIL.',
            'dni.required'                   => 'El DNI es obligatorio.',
            'dni.digits_between'             => 'El DNI debe tener 7 u 8 dígitos.',
            'fecha_nacimiento.before'        => 'La fecha de nacimiento no es válida.',
            'fecha_ingreso.required'         => 'La fecha de ingreso es obligatoria.',
            'categoria_id.required_with'     => 'Seleccioná la categoría del convenio.',
            'categoria_id.exists'            => 'La categoría no pertenece al convenio seleccionado.',
            'supervisor_id.not_in'           => 'Un empleado no puede ser su propio supervisor.',
            'sueldo_basico.required_without' => 'Si el empleado está fuera de convenio, ingresá su sueldo básico.',
            'foto.image'                     => 'La foto debe ser una imagen.',
            'foto.max'                       => 'La foto no puede superar los 2 MB.',
        ]);

        unset($datos['foto']);

        // Dentro de convenio el básico sale de la categoría
        if (! empty($datos['convenio_id'])) {
            $datos['sueldo_basico'] = null;
        } else {
            $datos['categoria_id'] = null;
        }

        return $datos;
    }
}
