<?php

namespace App\Http\Controllers\Portal;

use App\Models\Licencia;
use App\Models\TipoLicencia;
use App\Services\Licencias\CalculadorVacaciones;
use App\Services\Licencias\RegistroLicencias;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class LicenciasController extends PortalController
{
    public function __construct(
        private CalculadorVacaciones $vacaciones,
        private RegistroLicencias $registro,
    ) {
    }

    public function index()
    {
        $e       = $this->empleado();
        $periodo = CalculadorVacaciones::periodoActual();

        // Saldos: el período actual y el anterior si todavía le quedan días
        $saldos = [$periodo => $this->vacaciones->saldo($e, $periodo)];
        $anterior = $this->vacaciones->saldo($e, $periodo - 1);
        if ($anterior['saldo'] > 0) {
            $saldos = [$periodo - 1 => $anterior] + $saldos;
        }

        return view('portal.licencias', [
            'licencias' => Licencia::with(['tipo', 'resueltaPor'])->where('empleado_id', $e->id)->orderByDesc('fecha_desde')->paginate(15),
            'saldos'    => $saldos,
        ]);
    }

    public function create(Request $request)
    {
        return view('portal.licencia_form', [
            'tipos'   => TipoLicencia::where('activo', true)->orderByDesc('es_vacaciones')->orderBy('nombre')->get(),
            'periodo' => CalculadorVacaciones::periodoActual(),
            'tipoId'  => $request->get('tipo_id'),
        ]);
    }

    public function store(Request $request)
    {
        $empresaId = auth()->user()->empresa_id;

        $request->validate([
            'tipo_licencia_id' => ['required', Rule::exists('tipos_licencia', 'id')->where('empresa_id', $empresaId)->where('activo', true)],
            'fecha_desde'      => ['required', 'date'],
            'fecha_hasta'      => ['required', 'date', 'after_or_equal:fecha_desde'],
            'periodo'          => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'observaciones'    => ['nullable', 'string', 'max:2000'],
            'certificado'      => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,webp'],
        ], [
            'tipo_licencia_id.required'  => 'Elegí el tipo de licencia.',
            'fecha_hasta.after_or_equal' => 'La fecha de fin no puede ser anterior a la de inicio.',
            'certificado.mimes'          => 'El certificado tiene que ser PDF o imagen.',
            'certificado.max'            => 'El certificado no puede superar los 5 MB.',
        ]);

        $e    = $this->empleado();
        $tipo = TipoLicencia::findOrFail($request->tipo_licencia_id);

        $r = $this->registro->validar(
            $e, $tipo,
            Carbon::parse($request->fecha_desde), Carbon::parse($request->fecha_hasta),
            $request->filled('periodo') ? (int) $request->periodo : null
        );

        $licencia = Licencia::create([
            'empleado_id'      => $e->id,
            'tipo_licencia_id' => $tipo->id,
            'fecha_desde'      => Carbon::parse($request->fecha_desde)->toDateString(),
            'fecha_hasta'      => Carbon::parse($request->fecha_hasta)->toDateString(),
            'dias'             => $r['dias'],
            'periodo'          => $r['periodo'],
            'estado'           => Licencia::PENDIENTE,
            'observaciones'    => $request->observaciones,
            'solicitada_por'   => auth()->id(),
        ]);

        $this->guardarCertificado($request, $licencia);

        return redirect()->route('portal.licencias')
            ->with('ok', 'Solicitud enviada. Te vas a enterar acá cuando la aprueben.')
            ->with('aviso', $tipo->requiere_certificado && ! $licencia->fresh()->certificado
                ? 'Este tipo de licencia requiere certificado: podés adjuntarlo desde la lista.' : null);
    }

    public function cancelar(Licencia $licencia)
    {
        $this->esMio($licencia);

        if (! $licencia->esPendiente()) {
            return back()->with('error', 'Solo podés cancelar solicitudes pendientes. Para una licencia aprobada, hablá con RRHH.');
        }

        $licencia->update(['estado' => Licencia::CANCELADA, 'resuelta_por' => auth()->id(), 'resuelta_at' => now()]);

        return back()->with('ok', 'Solicitud cancelada.');
    }

    public function subirCertificado(Request $request, Licencia $licencia)
    {
        $this->esMio($licencia);

        $request->validate([
            'certificado' => ['required', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,webp'],
        ], [
            'certificado.required' => 'Elegí el archivo.',
            'certificado.mimes'    => 'El certificado tiene que ser PDF o imagen.',
            'certificado.max'      => 'El certificado no puede superar los 5 MB.',
        ]);

        if (! in_array($licencia->estado, Licencia::ACTIVAS, true)) {
            return back()->with('error', 'La licencia no está activa.');
        }

        $this->guardarCertificado($request, $licencia);

        return back()->with('ok', 'Certificado adjuntado.');
    }

    public function verCertificado(Licencia $licencia)
    {
        $this->esMio($licencia);
        abort_unless($licencia->certificado && Storage::disk('local')->exists($licencia->certificado), 404);

        return Storage::disk('local')->response($licencia->certificado, $licencia->certificado_nombre);
    }

    /** JSON para el formulario: días y saldo */
    public function calcularDias(Request $request)
    {
        $request->validate([
            'tipo_id' => ['required', 'integer'],
            'desde'   => ['required', 'date'],
            'hasta'   => ['required', 'date'],
            'periodo' => ['nullable', 'integer'],
        ]);

        $e     = $this->empleado()->load('horario');
        $tipo  = TipoLicencia::findOrFail($request->tipo_id);
        $desde = Carbon::parse($request->desde);
        $hasta = Carbon::parse($request->hasta);

        $respuesta = [
            'dias'   => $this->vacaciones->diasLicencia($tipo, $desde, $hasta, $e),
            'unidad' => $tipo->esHabiles() ? 'hábiles' : 'corridos',
            'maximo' => $tipo->dias_maximos,
            'avisos' => [],
        ];

        if ($tipo->es_vacaciones && $request->filled('periodo')) {
            $respuesta['disponible'] = $this->vacaciones->saldo($e, (int) $request->periodo)['disponible'];
            $respuesta['avisos']     = CalculadorVacaciones::avisos($desde, $hasta);
        }

        return response()->json($respuesta);
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
}
