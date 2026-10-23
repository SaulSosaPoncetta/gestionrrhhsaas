@extends('layouts.app')

@section('title', 'Calendario de licencias')

@push('styles')
<style>
    .cal th, .cal td { padding: .25rem .15rem; font-size: .75rem; text-align: center; min-width: 24px; }
    .cal td.nombre, .cal th.nombre { text-align: left; min-width: 180px; position: sticky; left: 0; background: #fff; z-index: 1; }
    .cal .finde { background: #f1f3f5; }
    .cal .feriado { background: #fde2e1; }
    .cal .hoy { outline: 2px solid var(--bs-primary); outline-offset: -2px; }
    .cal .celda { display: block; height: 22px; border-radius: 3px; }
    .cal .celda.pendiente { opacity: .45; background-image: repeating-linear-gradient(45deg, transparent 0 4px, rgba(255,255,255,.6) 4px 8px); }
</style>
@endpush

@section('content')
@php
    $anterior  = $desde->copy()->subMonth()->format('Y-m');
    $siguiente = $desde->copy()->addMonth()->format('Y-m');
@endphp

<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <form method="GET" class="input-group" style="width:auto">
        <a href="{{ route('licencias.calendario', ['mes' => $anterior]) }}" class="btn btn-outline-secondary"><i class="bi bi-chevron-left"></i></a>
        <input type="month" name="mes" value="{{ $mes }}" class="form-control" onchange="this.form.submit()">
        <a href="{{ route('licencias.calendario', ['mes' => $siguiente]) }}" class="btn btn-outline-secondary"><i class="bi bi-chevron-right"></i></a>
    </form>
    <a href="{{ route('licencias.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Nueva licencia</a>
</div>

<h2 class="h6 text-muted mb-3">{{ ucfirst($desde->locale('es')->isoFormat('MMMM [de] YYYY')) }}</h2>

@if($filas->isEmpty())
    <div class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5">
        No hay licencias aprobadas ni pendientes en este mes.
    </div></div>
@else
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-bordered cal mb-0">
                <thead>
                    <tr>
                        <th class="nombre">Empleado</th>
                        @foreach($dias as $d)
                            <th class="{{ $d->isWeekend() ? 'finde' : '' }} {{ isset($feriados[$d->toDateString()]) ? 'feriado' : '' }} {{ $d->isToday() ? 'hoy' : '' }}"
                                title="{{ $feriados[$d->toDateString()] ?? '' }}">
                                <div class="text-muted">{{ mb_substr(\App\Models\Horario::DIAS_CORTOS[$d->dayOfWeekIso], 0, 1) }}</div>
                                {{ $d->day }}
                            </th>
                        @endforeach
                        <th title="Días de licencia aprobada en el mes">Días</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($filas as $fila)
                    <tr>
                        <td class="nombre">
                            <a href="{{ route('empleados.show', $fila['empleado']) }}#licencias" class="text-decoration-none">{{ $fila['empleado']->nombre_completo }}</a>
                        </td>
                        @foreach($dias as $d)
                            @php
                                $lic = $fila['celdas'][$d->toDateString()];
                            @endphp
                            <td class="{{ $d->isWeekend() ? 'finde' : '' }} {{ isset($feriados[$d->toDateString()]) ? 'feriado' : '' }}">
                                @if($lic)
                                    <a href="{{ route('licencias.edit', $lic) }}"
                                       class="celda bg-{{ $lic->tipo->color }} {{ $lic->esPendiente() ? 'pendiente' : '' }}"
                                       title="{{ $lic->tipo->nombre }} · {{ $lic->periodo_texto }} · {{ $lic->estado_nombre }}"></a>
                                @endif
                            </td>
                        @endforeach
                        <td class="fw-semibold">{{ $fila['dias'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-3 mt-2 small">
        @foreach($tipos as $t)
            <span><span class="badge text-bg-{{ $t->color }}">&nbsp;</span> {{ $t->nombre }}</span>
        @endforeach
        <span><span class="badge text-bg-secondary opacity-50">&nbsp;</span> Pendiente de aprobación (rayado)</span>
        <span><span class="badge" style="background:#fde2e1">&nbsp;</span> Feriado</span>
    </div>
@endif
@endsection
