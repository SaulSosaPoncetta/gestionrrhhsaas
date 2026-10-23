@extends('layouts.app')

@section('title', 'Mi asistencia')

@section('content')
@php
    $Formato   = \App\Support\Formato::class;
    $anterior  = $desde->copy()->subMonth()->format('Y-m');
    $siguiente = $desde->copy()->addMonth()->format('Y-m');
@endphp

<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <form method="GET" class="input-group" style="width:auto">
        <a href="{{ route('portal.asistencia', ['mes' => $anterior]) }}" class="btn btn-outline-secondary"><i class="bi bi-chevron-left"></i></a>
        <input type="month" name="mes" value="{{ $mes }}" class="form-control" onchange="this.form.submit()">
        <a href="{{ route('portal.asistencia', ['mes' => $siguiente]) }}" class="btn btn-outline-secondary"><i class="bi bi-chevron-right"></i></a>
    </form>
    <div class="small text-muted">{{ $empleado->horario ? $empleado->horario->resumen : 'Sin horario asignado' }}</div>
</div>

<div class="row g-2 mb-3">
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2">
        <div class="small text-muted">Asistencias</div><div class="fs-5 fw-semibold">{{ $totales['asistencias'] }} / {{ $totales['programados'] }}</div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2">
        <div class="small text-muted">Ausencias</div><div class="fs-5 fw-semibold {{ $totales['ausencias'] ? 'text-danger' : '' }}">{{ $totales['ausencias'] }}</div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2">
        <div class="small text-muted">Llegadas tarde</div><div class="fs-5 fw-semibold {{ $totales['tardanzas'] ? 'text-warning' : '' }}">{{ $totales['tardanzas'] }}</div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2">
        <div class="small text-muted">Horas extra</div><div class="fs-5 fw-semibold">{{ $Formato::horas($totales['extra50'] + $totales['extra100'], '0:00') }}</div>
    </div></div></div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr><th>Día</th><th class="d-none d-sm-table-cell">Horario</th><th>Fichadas</th><th class="text-center">Trabajado</th><th class="text-center">Estado</th></tr>
            </thead>
            <tbody>
            @foreach($dias as $dia)
                @continue($dia->estado === 'fuera')
                <tr class="{{ $dia->fecha->isToday() ? 'table-primary' : ($dia->fecha->isWeekend() || $dia->feriado ? 'table-light' : '') }}">
                    <td class="text-nowrap">
                        <span class="fw-semibold">{{ $dia->fecha->format('d') }}</span>
                        <span class="small text-muted">{{ \App\Models\Horario::DIAS_CORTOS[$dia->fecha->dayOfWeekIso] }}</span>
                    </td>
                    <td class="small d-none d-sm-table-cell">{{ $dia->textoProgramado() }}</td>
                    <td>
                        @foreach($dia->fichadas() as $f)
                            <span class="badge font-monospace {{ $f->esEntrada() ? 'text-bg-success' : 'text-bg-danger' }}">{{ $f->fecha_hora->format('H:i') }}</span>
                        @endforeach
                    </td>
                    <td class="text-center small">{{ $Formato::horas($dia->minutosTrabajados) }}</td>
                    <td class="text-center">
                        <span class="badge text-bg-{{ $dia->color() }}">{{ $dia->etiqueta() }}</span>
                        @if($dia->minutosTarde)<div class="small text-danger">{{ $dia->minutosTarde }} min tarde</div>@endif
                        @if($dia->licencia)<div class="small text-muted">{{ $dia->licencia }}</div>@endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
<p class="small text-muted mt-2 mb-0">Si ves un error en tus fichadas (por ejemplo, te olvidaste de fichar la salida), avisale a RRHH para que lo corrija.</p>
@endsection
