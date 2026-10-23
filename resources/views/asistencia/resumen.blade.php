@extends('layouts.app')

@section('title', 'Resumen mensual de asistencia')

@section('content')
@php($Formato = \App\Support\Formato::class)
@php($anterior = $desde->copy()->subMonth()->format('Y-m'))
@php($siguiente = $desde->copy()->addMonth()->format('Y-m'))

<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <form method="GET" class="d-flex flex-wrap gap-2 align-items-center">
        <div class="input-group" style="width:auto">
            <a href="{{ route('asistencia.resumen', ['mes' => $anterior, 'area_id' => $areaId]) }}" class="btn btn-outline-secondary"><i class="bi bi-chevron-left"></i></a>
            <input type="month" name="mes" value="{{ $mes }}" class="form-control" onchange="this.form.submit()">
            <a href="{{ route('asistencia.resumen', ['mes' => $siguiente, 'area_id' => $areaId]) }}" class="btn btn-outline-secondary"><i class="bi bi-chevron-right"></i></a>
        </div>
        <select name="area_id" class="form-select" style="max-width:220px" onchange="this.form.submit()">
            <option value="">Todas las áreas</option>
            @foreach($areas as $a)
                <option value="{{ $a->id }}" @selected((string) $areaId === (string) $a->id)>{{ str_repeat('— ', $a->nivel) }}{{ $a->nombre }}</option>
            @endforeach
        </select>
    </form>
    <a href="{{ route('asistencia.resumen.exportar', ['mes' => $mes, 'area_id' => $areaId]) }}" class="btn btn-outline-success">
        <i class="bi bi-file-earmark-spreadsheet me-1"></i>Exportar a Excel (CSV)
    </a>
</div>

<h2 class="h6 text-muted mb-3">{{ ucfirst($desde->locale('es')->isoFormat('MMMM [de] YYYY')) }}</h2>

<div class="row g-2 mb-3">
    <div class="col-6 col-lg">
        <div class="card border-0 shadow-sm h-100"><div class="card-body py-2">
            <div class="small text-muted">Horas trabajadas</div>
            <div class="fs-5 fw-semibold">{{ $Formato::horas($total['minutos_trabajados'], '0:00') }}</div>
        </div></div>
    </div>
    <div class="col-6 col-lg">
        <div class="card border-0 shadow-sm h-100"><div class="card-body py-2">
            <div class="small text-muted">Extras al 50 %</div>
            <div class="fs-5 fw-semibold text-primary">{{ $Formato::horas($total['extra50'], '0:00') }}</div>
        </div></div>
    </div>
    <div class="col-6 col-lg">
        <div class="card border-0 shadow-sm h-100"><div class="card-body py-2">
            <div class="small text-muted">Extras al 100 %</div>
            <div class="fs-5 fw-semibold text-primary">{{ $Formato::horas($total['extra100'], '0:00') }}</div>
        </div></div>
    </div>
    <div class="col-6 col-lg">
        <div class="card border-0 shadow-sm h-100"><div class="card-body py-2">
            <div class="small text-muted">Ausencias</div>
            <div class="fs-5 fw-semibold text-danger">{{ $total['ausencias'] }}</div>
        </div></div>
    </div>
    <div class="col-6 col-lg">
        <div class="card border-0 shadow-sm h-100"><div class="card-body py-2">
            <div class="small text-muted">Llegadas tarde</div>
            <div class="fs-5 fw-semibold text-warning">{{ $total['tardanzas'] }}</div>
        </div></div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Empleado</th>
                    <th class="text-center" title="Días con horario programado">Días prog.</th>
                    <th class="text-center">Asistencias</th>
                    <th class="text-center">Ausencias</th>
                    <th class="text-center">Licencia</th>
                    <th class="text-center">Tardanzas</th>
                    <th class="text-center d-none d-md-table-cell">Incompletas</th>
                    <th class="text-center">Trabajado</th>
                    <th class="text-center">Extra 50 %</th>
                    <th class="text-center">Extra 100 %</th>
                </tr>
            </thead>
            <tbody>
            @forelse($filas as ['empleado' => $emp, 'totales' => $t])
                <tr style="cursor:pointer" onclick="window.location='{{ route('asistencia.empleado', [$emp, 'mes' => $mes]) }}'">
                    <td>
                        <a href="{{ route('asistencia.empleado', [$emp, 'mes' => $mes]) }}" class="fw-semibold text-decoration-none text-dark">{{ $emp->nombre_completo }}</a>
                        <div class="small text-muted">
                            Leg. {{ $emp->legajo }}
                            @unless($emp->horario)<span class="badge text-bg-light">sin horario</span>@endunless
                        </div>
                    </td>
                    <td class="text-center">{{ $t['programados'] }}</td>
                    <td class="text-center">{{ $t['asistencias'] }}</td>
                    <td class="text-center {{ $t['ausencias'] ? 'text-danger fw-semibold' : 'text-muted' }}">{{ $t['ausencias'] }}</td>
                    <td class="text-center {{ $t['licencias'] ? 'text-info fw-semibold' : 'text-muted' }}">
                        {{ $t['licencias'] }}
                        @if($t['licencias_sin_goce'])<div class="small text-danger fw-normal">{{ $t['licencias_sin_goce'] }} sin goce</div>@endif
                    </td>
                    <td class="text-center {{ $t['tardanzas'] ? 'text-warning fw-semibold' : 'text-muted' }}">
                        {{ $t['tardanzas'] }}
                        @if($t['minutos_tarde'])<div class="small text-muted fw-normal">{{ $t['minutos_tarde'] }} min</div>@endif
                    </td>
                    <td class="text-center d-none d-md-table-cell {{ $t['incompletos'] ? 'text-info fw-semibold' : 'text-muted' }}">{{ $t['incompletos'] }}</td>
                    <td class="text-center">{{ $Formato::horas($t['minutos_trabajados']) }}</td>
                    <td class="text-center">{{ $Formato::horas($t['extra50']) }}</td>
                    <td class="text-center">{{ $Formato::horas($t['extra100']) }}</td>
                </tr>
            @empty
                <tr><td colspan="10" class="text-center text-muted py-4">No hay empleados en el período.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<p class="small text-muted mt-2 mb-0">
    Las fichadas incompletas no suman horas: corregilas desde el detalle del empleado antes de liquidar.
</p>
@endsection
