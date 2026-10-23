<?php

namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Models\EmpleadoDocumento;
use App\Models\Empresa;
use App\Models\Licencia;
use App\Models\User;
use App\Services\Asistencia\CalculadorAsistencia;

class DashboardController extends Controller
{
    public function index(CalculadorAsistencia $calculador)
    {
        $user = auth()->user();

        // Empleados y supervisores usan el portal de autogestión
        if ($user->esUsuarioPortal()) {
            return redirect()->route('portal.inicio');
        }

        if ($user->esSuperadmin()) {
            return view('dashboard', [
                'user'  => $user,
                'stats' => [
                    'empresas' => Empresa::count(),
                    'activas'  => Empresa::where('activa', true)->count(),
                    'usuarios' => User::count(),
                ],
            ]);
        }

        $inicioMes = now()->startOfMonth();
        $finMes    = now()->endOfMonth();

        $stats = [
            'activos'  => Empleado::activos()->count(),
            'ingresos' => Empleado::whereBetween('fecha_ingreso', [$inicioMes, $finMes])->count(),
            'egresos'  => Empleado::whereBetween('fecha_egreso', [$inicioMes, $finMes])->count(),
            'usuarios' => User::where('empresa_id', $user->empresa_id)->count(),
        ];

        $cumpleanios = Empleado::activos()
            ->whereNotNull('fecha_nacimiento')
            ->whereMonth('fecha_nacimiento', now()->month)
            ->get()
            ->sortBy(fn ($e) => $e->fecha_nacimiento->day);

        $documentosAlerta = EmpleadoDocumento::with('empleado')
            ->whereNotNull('vencimiento')
            ->where('vencimiento', '<=', now()->addDays(30))
            ->whereHas('empleado', fn ($q) => $q->activos())
            ->orderBy('vencimiento')
            ->limit(10)
            ->get();

        $aniversarios = Empleado::activos()
            ->whereMonth('fecha_ingreso', now()->month)
            ->whereYear('fecha_ingreso', '<', now()->year)
            ->get()
            ->sortBy(fn ($e) => $e->fecha_ingreso->day);

        // Asistencia de hoy
        $empleadosHoy = Empleado::activos()->where('fecha_ingreso', '<=', today()->toDateString())->get();
        $calculo      = $calculador->calcularVarios($empleadosHoy, today(), today());
        $asistenciaHoy = collect($calculo)
            ->map(fn ($dias) => reset($dias)->estado)
            ->countBy()
            ->all();

        // Licencias
        $licenciasPendientes = Licencia::where('estado', Licencia::PENDIENTE)->count();
        $deLicenciaHoy = Licencia::with(['empleado', 'tipo'])
            ->aprobadas()
            ->entre(today(), today())
            ->get()
            ->filter(fn ($l) => $l->empleado)
            ->sortBy(fn ($l) => $l->empleado->apellido);

        return view('dashboard', compact(
            'user', 'stats', 'cumpleanios', 'documentosAlerta', 'aniversarios', 'asistenciaHoy',
            'licenciasPendientes', 'deLicenciaHoy'
        ));
    }
}
