<?php

namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Models\TipoLicencia;
use App\Models\VacacionPeriodo;
use App\Services\Licencias\CalculadorVacaciones;
use Illuminate\Http\Request;

class VacacionController extends Controller
{
    public function __construct(private CalculadorVacaciones $vacaciones)
    {
    }

    public function index(Request $request)
    {
        $anio = $this->anio($request);

        $empleados = $this->vigentes($anio);
        $saldos    = $this->vacaciones->saldos($empleados, $anio);

        return view('vacaciones.index', [
            'anio'          => $anio,
            'filas'         => $empleados->map(fn ($e) => ['empleado' => $e, 'saldo' => $saldos[$e->id]]),
            'generado'      => VacacionPeriodo::where('anio', $anio)->exists(),
            'tipoVacaciones' => TipoLicencia::vacaciones(),
        ]);
    }

    /** Guarda los días calculados del año (no toca los modificados a mano) */
    public function generar(Request $request)
    {
        $anio = (int) $request->validate(['anio' => ['required', 'integer', 'min:2000', 'max:2100']])['anio'];

        $actualizados = 0;
        foreach ($this->vigentes($anio) as $empleado) {
            $periodo = VacacionPeriodo::firstOrNew(['empleado_id' => $empleado->id, 'anio' => $anio]);
            if ($periodo->exists && $periodo->manual) {
                continue;
            }
            $periodo->fill([
                'empresa_id'        => $empleado->empresa_id,
                'dias_corresponden' => $this->vacaciones->calcular($empleado, $anio)['dias'],
            ])->save();
            $actualizados++;
        }

        return redirect()->route('vacaciones.index', ['anio' => $anio])
            ->with('ok', "Se calcularon las vacaciones {$anio} de {$actualizados} empleados. Los que modificaste a mano no se tocaron.");
    }

    public function update(Request $request, Empleado $empleado, int $anio)
    {
        $datos = $request->validate([
            'dias_corresponden' => ['required', 'integer', 'min:0', 'max:365'],
            'dias_ajuste'       => ['required', 'integer', 'min:-365', 'max:365'],
            'observaciones'     => ['nullable', 'string', 'max:255'],
        ], [
            'dias_corresponden.required' => 'Ingresá los días que corresponden.',
        ]);

        $empleado->load('horario');
        $calculado = $this->vacaciones->calcular($empleado, $anio)['dias'];

        VacacionPeriodo::updateOrCreate(
            ['empleado_id' => $empleado->id, 'anio' => $anio],
            $datos + [
                'empresa_id' => $empleado->empresa_id,
                'manual'     => (int) $datos['dias_corresponden'] !== $calculado,
            ]
        );

        return redirect()->route('vacaciones.index', ['anio' => $anio])
            ->with('ok', "Vacaciones {$anio} de {$empleado->nombre_completo} actualizadas.");
    }

    // -----------------------------------------------------------------

    private function anio(Request $request): int
    {
        $anio = (int) $request->get('anio', CalculadorVacaciones::periodoActual());

        return ($anio < 2000 || $anio > 2100) ? CalculadorVacaciones::periodoActual() : $anio;
    }

    private function vigentes(int $anio)
    {
        return Empleado::with(['horario', 'area'])
            ->where('fecha_ingreso', '<=', "{$anio}-12-31")
            ->where(fn ($q) => $q->whereNull('fecha_egreso')->orWhere('fecha_egreso', '>=', "{$anio}-01-01"))
            ->orderBy('apellido')->orderBy('nombre')
            ->get();
    }
}
