<?php

namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Models\Fichada;
use App\Services\Asistencia\CalculadorAsistencia;
use App\Services\Asistencia\ImportadorFichadas;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FichadaController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
        ]);

        $desde      = $request->filled('desde') ? Carbon::parse($request->desde) : today()->subDays(6);
        $hasta      = $request->filled('hasta') ? Carbon::parse($request->hasta) : today();
        $empleadoId = $request->get('empleado_id');
        $origen     = $request->get('origen');

        if ($hasta->lt($desde)) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $fichadas = Fichada::with(['empleado', 'registradoPor', 'editadoPor'])
            ->whereBetween('fecha_hora', [$desde->copy()->startOfDay(), $hasta->copy()->endOfDay()])
            ->when($empleadoId, fn ($q) => $q->where('empleado_id', $empleadoId))
            ->when($origen, fn ($q) => $q->where('origen', $origen))
            ->orderByDesc('fecha_hora')
            ->paginate(50)
            ->withQueryString();

        return view('fichadas.index', [
            'fichadas'         => $fichadas,
            'desde'            => $desde,
            'hasta'            => $hasta,
            'empleadoId'       => $empleadoId,
            'origen'           => $origen,
            'empleados'        => Empleado::withTrashed()->orderBy('apellido')->orderBy('nombre')->get(),
            'empleadosFichada' => self::empleadosParaFichar(),
        ]);
    }

    public function store(Request $request)
    {
        $datos = $this->validar($request);

        Fichada::create($datos + [
            'origen'         => 'manual',
            'registrado_por' => auth()->id(),
        ]);

        return back()->with('ok', 'Fichada registrada.');
    }

    public function update(Request $request, Fichada $fichada)
    {
        $datos = $this->validar($request, $fichada);

        $fichada->update($datos + ['editado_por' => auth()->id()]);

        return back()->with('ok', 'Fichada actualizada.');
    }

    public function destroy(Fichada $fichada)
    {
        $fichada->delete();

        return back()->with('ok', 'Fichada eliminada.');
    }

    // ---------- Importación ----------

    public function importar()
    {
        return view('fichadas.importar', [
            'resultado' => session('resultado'),
        ]);
    }

    public function procesarImportacion(Request $request, ImportadorFichadas $importador)
    {
        $request->validate([
            'archivo' => ['required', 'file', 'max:5120', 'mimes:csv,txt'],
        ], [
            'archivo.required' => 'Seleccioná el archivo exportado del reloj.',
            'archivo.mimes'    => 'El archivo tiene que ser CSV o TXT.',
            'archivo.max'      => 'El archivo no puede superar los 5 MB.',
        ]);

        $resultado = $importador->importar(
            $request->file('archivo')->getRealPath(),
            auth()->user()->empresa_id,
            auth()->id(),
            $request->boolean('deducir_tipo')
        );

        return redirect()->route('fichadas.importar')->with('resultado', $resultado);
    }

    // -----------------------------------------------------------------

    /** Empleados que se pueden elegir en el modal de fichada */
    public static function empleadosParaFichar()
    {
        return Empleado::activos()->orderBy('apellido')->orderBy('nombre')->get(['id', 'legajo', 'apellido', 'nombre']);
    }

    private function validar(Request $request, ?Fichada $fichada = null): array
    {
        $empresaId = auth()->user()->empresa_id;

        $datos = $request->validate([
            'empleado_id' => ['required', Rule::exists('empleados', 'id')->where('empresa_id', $empresaId)],
            'fecha'       => ['required', 'date'],
            'hora'        => ['required', 'date_format:H:i'],
            'tipo'        => ['required', Rule::in(array_keys(Fichada::TIPOS))],
            'observacion' => ['nullable', 'string', 'max:255'],
        ], [
            'empleado_id.required' => 'Seleccioná el empleado.',
            'fecha.required'       => 'Ingresá la fecha.',
            'hora.required'        => 'Ingresá la hora.',
            'hora.date_format'     => 'La hora no es válida.',
            'tipo.required'        => 'Indicá si es entrada o salida.',
        ]);

        $fechaHora = Carbon::parse("{$datos['fecha']} {$datos['hora']}:00");

        if ($fechaHora->gt(now()->addMinutes(5))) {
            throw ValidationException::withMessages(['fecha' => 'No se pueden registrar fichadas a futuro.']);
        }

        $duplicada = Fichada::where('empleado_id', $datos['empleado_id'])
            ->where('fecha_hora', $fechaHora)
            ->when($fichada, fn ($q) => $q->where('id', '!=', $fichada->id))
            ->exists();

        if ($duplicada) {
            throw ValidationException::withMessages(['hora' => 'El empleado ya tiene una fichada en ese mismo horario.']);
        }

        return [
            'empleado_id' => $datos['empleado_id'],
            'fecha_hora'  => $fechaHora,
            'tipo'        => $datos['tipo'],
            'observacion' => $datos['observacion'] ?? null,
        ];
    }
}
