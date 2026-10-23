<?php

namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Models\Feriado;
use App\Models\Licencia;
use App\Models\TipoLicencia;
use App\Services\Licencias\CalculadorVacaciones;
use App\Services\Licencias\RegistroLicencias;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class LicenciaController extends Controller
{
    public function __construct(
        private CalculadorVacaciones $vacaciones,
        private RegistroLicencias $registro,
    ) {
    }

    public function index(Request $request)
    {
        $estado     = $request->get('estado');
        $tipoId     = $request->get('tipo_id');
        $empleadoId = $request->get('empleado_id');
        $anio       = $request->get('anio');

        $licencias = Licencia::with(['empleado', 'tipo', 'resueltaPor'])
            ->when($estado, fn ($q) => $q->where('estado', $estado))
            ->when($tipoId, fn ($q) => $q->where('tipo_licencia_id', $tipoId))
            ->when($empleadoId, fn ($q) => $q->where('empleado_id', $empleadoId))
            ->when($anio, fn ($q) => $q->whereYear('fecha_desde', $anio))
            ->orderByRaw("CASE WHEN estado = 'pendiente' THEN 0 ELSE 1 END")
            ->orderByDesc('fecha_desde')
            ->paginate(30)
            ->withQueryString();

        return view('licencias.index', [
            'licencias'  => $licencias,
            'estado'     => $estado,
            'tipoId'     => $tipoId,
            'empleadoId' => $empleadoId,
            'anio'       => $anio,
            'tipos'      => TipoLicencia::orderBy('nombre')->get(),
            'empleados'  => Empleado::withTrashed()->orderBy('apellido')->orderBy('nombre')->get(),
            'pendientes' => Licencia::where('estado', Licencia::PENDIENTE)->count(),
        ]);
    }

    public function create(Request $request)
    {
        $licencia = new Licencia([
            'empleado_id'      => $request->get('empleado_id'),
            'tipo_licencia_id' => $request->get('tipo_id'),
            'fecha_desde'      => $request->get('desde', today()->toDateString()),
            'fecha_hasta'      => $request->get('hasta', $request->get('desde', today()->toDateString())),
            'periodo'          => $request->get('periodo', CalculadorVacaciones::periodoActual()),
        ]);

        return view('licencias.form', $this->datosFormulario($licencia));
    }

    public function store(Request $request)
    {
        $datos = $this->validar($request);
        $datos['solicitada_por'] = auth()->id();

        if ($request->boolean('aprobar')) {
            $datos += ['estado' => Licencia::APROBADA, 'resuelta_por' => auth()->id(), 'resuelta_at' => now()];
        } else {
            $datos['estado'] = Licencia::PENDIENTE;
        }

        $licencia = Licencia::create($datos);
        $this->guardarCertificado($request, $licencia);

        return redirect()->route('licencias.index')
            ->with('ok', 'Licencia registrada' . ($licencia->esAprobada() ? ' y aprobada.' : '. Queda pendiente de aprobación.'))
            ->with('aviso', $this->avisos($licencia));
    }

    public function edit(Licencia $licencia)
    {
        return view('licencias.form', $this->datosFormulario($licencia));
    }

    public function update(Request $request, Licencia $licencia)
    {
        $licencia->update($this->validar($request, $licencia));
        $this->guardarCertificado($request, $licencia);

        return redirect()->route('licencias.index')
            ->with('ok', 'Licencia actualizada.')
            ->with('aviso', $this->avisos($licencia));
    }

    public function destroy(Licencia $licencia)
    {
        if ($licencia->certificado) {
            Storage::disk('local')->delete($licencia->certificado);
        }
        $licencia->delete();

        return back()->with('ok', 'Licencia eliminada.');
    }

    public function aprobar(Licencia $licencia)
    {
        if (! $licencia->esPendiente()) {
            return back()->with('error', 'Solo se pueden aprobar licencias pendientes.');
        }

        $licencia->update(['estado' => Licencia::APROBADA, 'resuelta_por' => auth()->id(), 'resuelta_at' => now(), 'motivo_rechazo' => null]);

        return back()->with('ok', "Licencia de {$licencia->empleado->nombre_completo} aprobada.");
    }

    public function rechazar(Request $request, Licencia $licencia)
    {
        if (! $licencia->esPendiente()) {
            return back()->with('error', 'Solo se pueden rechazar licencias pendientes.');
        }

        $request->validate(['motivo_rechazo' => ['nullable', 'string', 'max:255']]);

        $licencia->update([
            'estado'         => Licencia::RECHAZADA,
            'resuelta_por'   => auth()->id(),
            'resuelta_at'    => now(),
            'motivo_rechazo' => $request->motivo_rechazo,
        ]);

        return back()->with('ok', 'Licencia rechazada.');
    }

    public function cancelar(Licencia $licencia)
    {
        if (! $licencia->esAprobada()) {
            return back()->with('error', 'Solo se pueden cancelar licencias aprobadas.');
        }

        $licencia->update(['estado' => Licencia::CANCELADA, 'resuelta_por' => auth()->id(), 'resuelta_at' => now()]);

        return back()->with('ok', 'Licencia cancelada. Los días vuelven al saldo.');
    }

    public function certificado(Licencia $licencia)
    {
        abort_unless($licencia->certificado && Storage::disk('local')->exists($licencia->certificado), 404, 'El certificado no se encuentra.');

        return Storage::disk('local')->response($licencia->certificado, $licencia->certificado_nombre);
    }

    /** JSON para el formulario: días que consume y saldo disponible */
    public function calcularDias(Request $request)
    {
        $request->validate([
            'empleado_id' => ['required', 'integer'],
            'tipo_id'     => ['required', 'integer'],
            'desde'       => ['required', 'date'],
            'hasta'       => ['required', 'date'],
            'periodo'     => ['nullable', 'integer'],
            'excluir'     => ['nullable', 'integer'],
        ]);

        $empleado = Empleado::with('horario')->findOrFail($request->empleado_id);
        $tipo     = TipoLicencia::findOrFail($request->tipo_id);
        $desde    = Carbon::parse($request->desde);
        $hasta    = Carbon::parse($request->hasta);

        $respuesta = [
            'dias'    => $this->vacaciones->diasLicencia($tipo, $desde, $hasta, $empleado),
            'unidad'  => $tipo->esHabiles() ? 'hábiles' : 'corridos',
            'maximo'  => $tipo->dias_maximos,
            'avisos'  => [],
        ];

        if ($tipo->es_vacaciones && $request->filled('periodo')) {
            $saldo = $this->vacaciones->saldo($empleado, (int) $request->periodo, $request->integer('excluir') ?: null);
            $respuesta['disponible'] = $saldo['disponible'];
            $respuesta['avisos']     = CalculadorVacaciones::avisos($desde, $hasta);
        }

        return response()->json($respuesta);
    }

    /** Calendario mensual de licencias aprobadas y pendientes */
    public function calendario(Request $request)
    {
        $mes = $request->get('mes');
        try {
            $desde = $mes && preg_match('/^\d{4}-\d{2}$/', $mes)
                ? Carbon::createFromFormat('Y-m-d', "{$mes}-01")->startOfDay()
                : today()->startOfMonth();
        } catch (\Throwable) {
            $desde = today()->startOfMonth();
        }
        $hasta = $desde->copy()->endOfMonth()->startOfDay();

        $licencias = Licencia::with(['empleado', 'tipo'])
            ->activas()
            ->entre($desde, $hasta)
            ->get();

        $dias = collect(CarbonPeriod::create($desde, $hasta))->map(fn ($d) => Carbon::parse($d->format('Y-m-d')));

        $feriados = Feriado::whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->get()
            ->mapWithKeys(fn ($f) => [$f->fecha->toDateString() => $f->descripcion]);

        $filas = $licencias->groupBy('empleado_id')
            ->map(function ($lista) use ($dias) {
                return [
                    'empleado' => $lista->first()->empleado,
                    'celdas'   => $dias->mapWithKeys(fn ($d) => [
                        $d->toDateString() => $lista->first(fn ($l) => $l->cubre($d)),
                    ]),
                    'dias'     => $lista->where('estado', Licencia::APROBADA)->sum(fn ($l) => $dias->filter(fn ($d) => $l->cubre($d))->count()),
                ];
            })
            ->filter(fn ($f) => $f['empleado'])
            ->sortBy(fn ($f) => $f['empleado']->apellido . ' ' . $f['empleado']->nombre)
            ->values();

        return view('licencias.calendario', [
            'desde'    => $desde,
            'mes'      => $desde->format('Y-m'),
            'dias'     => $dias,
            'filas'    => $filas,
            'feriados' => $feriados,
            'tipos'    => $licencias->pluck('tipo')->unique('id')->values(),
        ]);
    }

    // -----------------------------------------------------------------

    private function datosFormulario(Licencia $licencia): array
    {
        return [
            'licencia'  => $licencia,
            'empleados' => Empleado::where(function ($q) use ($licencia) {
                    $q->whereNull('fecha_egreso')
                      ->when($licencia->empleado_id, fn ($q) => $q->orWhere('id', $licencia->empleado_id));
                })
                ->orderBy('apellido')->orderBy('nombre')->get(),
            'tipos'     => TipoLicencia::where(function ($q) use ($licencia) {
                    $q->where('activo', true)
                      ->when($licencia->tipo_licencia_id, fn ($q) => $q->orWhere('id', $licencia->tipo_licencia_id));
                })
                ->orderByDesc('es_vacaciones')->orderBy('nombre')->get(),
        ];
    }

    private function validar(Request $request, ?Licencia $licencia = null): array
    {
        $empresaId = auth()->user()->empresa_id;

        $request->validate([
            'empleado_id'      => ['required', Rule::exists('empleados', 'id')->where('empresa_id', $empresaId)],
            'tipo_licencia_id' => ['required', Rule::exists('tipos_licencia', 'id')->where('empresa_id', $empresaId)],
            'fecha_desde'      => ['required', 'date'],
            'fecha_hasta'      => ['required', 'date', 'after_or_equal:fecha_desde'],
            'periodo'          => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'observaciones'    => ['nullable', 'string', 'max:2000'],
            'certificado'      => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,webp'],
        ], [
            'empleado_id.required'      => 'Seleccioná el empleado.',
            'tipo_licencia_id.required' => 'Seleccioná el tipo de licencia.',
            'fecha_desde.required'      => 'Ingresá la fecha de inicio.',
            'fecha_hasta.required'      => 'Ingresá la fecha de fin.',
            'fecha_hasta.after_or_equal' => 'La fecha de fin no puede ser anterior a la de inicio.',
            'certificado.max'           => 'El certificado no puede superar los 5 MB.',
            'certificado.mimes'         => 'El certificado tiene que ser PDF o imagen.',
        ]);

        $empleado = Empleado::with('horario')->findOrFail($request->empleado_id);
        $tipo     = TipoLicencia::findOrFail($request->tipo_licencia_id);

        $r = $this->registro->validar(
            $empleado,
            $tipo,
            Carbon::parse($request->fecha_desde),
            Carbon::parse($request->fecha_hasta),
            $request->filled('periodo') ? (int) $request->periodo : null,
            $licencia
        );

        return [
            'empleado_id'      => $empleado->id,
            'tipo_licencia_id' => $tipo->id,
            'fecha_desde'      => Carbon::parse($request->fecha_desde)->toDateString(),
            'fecha_hasta'      => Carbon::parse($request->fecha_hasta)->toDateString(),
            'dias'             => $r['dias'],
            'periodo'          => $r['periodo'],
            'observaciones'    => $request->observaciones,
        ];
    }

    private function guardarCertificado(Request $request, Licencia $licencia): void
    {
        if (! $request->hasFile('certificado')) {
            return;
        }

        if ($licencia->certificado) {
            Storage::disk('local')->delete($licencia->certificado);
        }

        $archivo = $request->file('certificado');
        $licencia->update([
            'certificado'        => $archivo->store("empresas/{$licencia->empresa_id}/licencias", 'local'),
            'certificado_nombre' => $archivo->getClientOriginalName(),
        ]);
    }

    private function avisos(Licencia $licencia): ?string
    {
        $licencia->loadMissing('tipo');
        $avisos = [];

        if ($licencia->tipo->es_vacaciones) {
            $avisos = CalculadorVacaciones::avisos($licencia->fecha_desde, $licencia->fecha_hasta);
        }
        if ($licencia->faltaCertificado()) {
            $avisos[] = 'Este tipo de licencia requiere certificado: acordate de adjuntarlo.';
        }

        return $avisos ? implode(' ', $avisos) : null;
    }
}
