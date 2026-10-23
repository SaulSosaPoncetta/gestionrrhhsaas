<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Empleado;
use App\Services\Asistencia\CalculadorAsistencia;
use App\Support\Formato;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AsistenciaController extends Controller
{
    public function __construct(private CalculadorAsistencia $calculador)
    {
    }

    /** Parte diario: cómo está cada empleado en una fecha */
    public function parte(Request $request)
    {
        $request->validate(['fecha' => ['nullable', 'date']]);

        $fecha  = $request->filled('fecha') ? Carbon::parse($request->fecha)->startOfDay() : today();
        $areaId = $request->get('area_id');
        $estado = $request->get('estado');

        $empleados = $this->vigentes($fecha, $fecha, $areaId);
        $calculo   = $this->calculador->calcularVarios($empleados, $fecha, $fecha);

        $filas = $empleados->map(fn ($e) => [
            'empleado' => $e,
            'dia'      => $calculo[$e->id][$fecha->toDateString()],
        ]);

        $conteo = $filas->countBy(fn ($f) => $f['dia']->estado);

        if ($estado) {
            $filas = $filas->filter(fn ($f) => $f['dia']->estado === $estado);
        }

        return view('asistencia.parte', [
            'fecha'            => $fecha,
            'filas'            => $filas,
            'conteo'           => $conteo,
            'estado'           => $estado,
            'areaId'           => $areaId,
            'areas'            => Area::arbol(),
            'empleadosFichada' => FichadaController::empleadosParaFichar(),
        ]);
    }

    /** Resumen mensual de todos los empleados */
    public function resumen(Request $request)
    {
        [$desde, $hasta, $mes] = $this->periodo($request);
        $areaId = $request->get('area_id');

        $filas = $this->armarResumen($desde, $hasta, $areaId);

        return view('asistencia.resumen', [
            'filas'  => $filas,
            'mes'    => $mes,
            'desde'  => $desde,
            'areaId' => $areaId,
            'areas'  => Area::arbol(),
            'total'  => [
                'ausencias'          => $filas->sum('totales.ausencias'),
                'tardanzas'          => $filas->sum('totales.tardanzas'),
                'minutos_trabajados' => $filas->sum('totales.minutos_trabajados'),
                'extra50'            => $filas->sum('totales.extra50'),
                'extra100'           => $filas->sum('totales.extra100'),
            ],
        ]);
    }

    /** Exporta el resumen mensual en CSV (abre directo en Excel) */
    public function exportarResumen(Request $request)
    {
        [$desde, $hasta, $mes] = $this->periodo($request);
        $filas = $this->armarResumen($desde, $hasta, $request->get('area_id'));

        $nombre = 'asistencia-' . $desde->format('Y-m') . '.csv';

        return response()->streamDownload(function () use ($filas) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");   // BOM para que Excel lea bien los acentos

            fputcsv($out, [
                'Legajo', 'Apellido y nombre', 'CUIL', 'Área', 'Días programados', 'Asistencias',
                'Ausencias', 'Días de licencia', 'Licencia sin goce', 'Llegadas tarde', 'Minutos tarde', 'Fichadas incompletas',
                'Horas trabajadas', 'Horas extra 50%', 'Horas extra 100%',
            ], ';');

            foreach ($filas as $f) {
                $e = $f['empleado'];
                $t = $f['totales'];
                fputcsv($out, [
                    $e->legajo, $e->nombre_completo, $e->cuil, $e->area?->nombre,
                    $t['programados'], $t['asistencias'], $t['ausencias'], $t['licencias'], $t['licencias_sin_goce'], $t['tardanzas'],
                    $t['minutos_tarde'], $t['incompletos'],
                    Formato::horasDecimal($t['minutos_trabajados']),
                    Formato::horasDecimal($t['extra50']),
                    Formato::horasDecimal($t['extra100']),
                ], ';');
            }

            fclose($out);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Detalle día por día de un empleado en un mes */
    public function empleado(Request $request, Empleado $empleado)
    {
        [$desde, $hasta, $mes] = $this->periodo($request);

        $empleado->load(['horario', 'area', 'puesto']);
        $dias    = $this->calculador->calcular($empleado, $desde, $hasta);
        $totales = CalculadorAsistencia::totales($dias);

        return view('asistencia.empleado', [
            'empleado'         => $empleado,
            'dias'             => $dias,
            'totales'          => $totales,
            'mes'              => $mes,
            'desde'            => $desde,
            'empleadosFichada' => FichadaController::empleadosParaFichar(),
        ]);
    }

    // -----------------------------------------------------------------

    /** Mes pedido (?mes=2026-10) → [desde, hasta, 'Y-m'] */
    private function periodo(Request $request): array
    {
        $mes = $request->get('mes');

        try {
            $desde = $mes && preg_match('/^\d{4}-\d{2}$/', $mes)
                ? Carbon::createFromFormat('Y-m-d', "{$mes}-01")->startOfDay()
                : today()->startOfMonth();
        } catch (\Throwable) {
            $desde = today()->startOfMonth();
        }

        return [$desde, $desde->copy()->endOfMonth()->startOfDay(), $desde->format('Y-m')];
    }

    /** Empleados con relación laboral vigente en algún momento del período */
    private function vigentes(Carbon $desde, Carbon $hasta, $areaId = null)
    {
        return Empleado::with(['horario', 'area', 'puesto'])
            ->where('fecha_ingreso', '<=', $hasta->toDateString())
            ->where(fn ($q) => $q->whereNull('fecha_egreso')->orWhere('fecha_egreso', '>=', $desde->toDateString()))
            ->when($areaId, fn ($q) => $q->where('area_id', $areaId))
            ->orderBy('apellido')->orderBy('nombre')
            ->get();
    }

    private function armarResumen(Carbon $desde, Carbon $hasta, $areaId = null)
    {
        $empleados = $this->vigentes($desde, $hasta, $areaId);
        $calculo   = $this->calculador->calcularVarios($empleados, $desde, $hasta);

        return $empleados->map(fn ($e) => [
            'empleado' => $e,
            'totales'  => CalculadorAsistencia::totales($calculo[$e->id] ?? []),
        ]);
    }
}
