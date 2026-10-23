@extends('layouts.app')

@section('title', 'Asistencia · ' . $empleado->nombre_completo)

@section('content')
@php
    $Formato   = \App\Support\Formato::class;
    $e         = $empleado;
    $anterior  = $desde->copy()->subMonth()->format('Y-m');
    $siguiente = $desde->copy()->addMonth()->format('Y-m');
@endphp

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
    </div>
@endif

<div class="d-flex flex-wrap gap-3 justify-content-between align-items-start mb-3">
    <div>
        <a href="{{ route('asistencia.resumen', ['mes' => $mes]) }}" class="text-decoration-none small"><i class="bi bi-arrow-left"></i> Resumen mensual</a>
        <h2 class="h5 mb-0 mt-1">
            <a href="{{ route('empleados.show', $e) }}" class="text-decoration-none text-dark">{{ $e->nombre_completo }}</a>
            <span class="text-muted fw-normal small">· Leg. {{ $e->legajo }}</span>
        </h2>
        <div class="small text-muted">
            @if($e->horario)
                {{ $e->horario->nombre }}: {{ $e->horario->resumen }} · tolerancia {{ $e->horario->tolerancia_minutos }} min
            @else
                <span class="text-warning"><i class="bi bi-exclamation-triangle"></i> Sin horario asignado: no se calculan tardanzas, ausencias ni extras.</span>
                <a href="{{ route('empleados.edit', $e) }}">Asignar</a>
            @endif
        </div>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <form method="GET" class="input-group" style="width:auto">
            <a href="{{ route('asistencia.empleado', [$e, 'mes' => $anterior]) }}" class="btn btn-outline-secondary"><i class="bi bi-chevron-left"></i></a>
            <input type="month" name="mes" value="{{ $mes }}" class="form-control" onchange="this.form.submit()">
            <a href="{{ route('asistencia.empleado', [$e, 'mes' => $siguiente]) }}" class="btn btn-outline-secondary"><i class="bi bi-chevron-right"></i></a>
        </form>
        <button class="btn btn-primary text-nowrap" onclick="abrirFichada({ empleado_id: {{ $e->id }} })">
            <i class="bi bi-plus-lg me-1"></i>Fichada
        </button>
    </div>
</div>

{{-- Totales del mes --}}
@php
    $tarjetas = [
        ['Asistencias', $totales['asistencias'] . ' / ' . $totales['programados'], 'dark'],
        ['Ausencias', $totales['ausencias'], $totales['ausencias'] ? 'danger' : 'dark'],
        ['Días de licencia', $totales['licencias'], 'info'],
        ['Llegadas tarde', $totales['tardanzas'] . ($totales['minutos_tarde'] ? ' (' . $totales['minutos_tarde'] . ' min)' : ''), $totales['tardanzas'] ? 'warning' : 'dark'],
        ['Trabajado', $Formato::horas($totales['minutos_trabajados'], '0:00'), 'dark'],
        ['Extra 50 %', $Formato::horas($totales['extra50'], '0:00'), 'primary'],
        ['Extra 100 %', $Formato::horas($totales['extra100'], '0:00'), 'primary'],
    ];
@endphp
<div class="row g-2 mb-3">
    @foreach($tarjetas as [$titulo, $valor, $color])
        <div class="col-6 col-md-3 col-xl">
            <div class="card border-0 shadow-sm h-100"><div class="card-body py-2">
                <div class="small text-muted">{{ $titulo }}</div>
                <div class="fs-5 fw-semibold text-{{ $color }}">{{ $valor }}</div>
            </div></div>
        </div>
    @endforeach
</div>

@if($totales['incompletos'])
    <div class="alert alert-info py-2 small">
        <i class="bi bi-info-circle me-1"></i>Hay {{ $totales['incompletos'] }} {{ $totales['incompletos'] === 1 ? 'día' : 'días' }} con fichadas incompletas
        (entrada sin salida o al revés). Esas horas no se suman hasta que se corrijan.
    </div>
@endif

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Día</th>
                    <th class="d-none d-md-table-cell">Programado</th>
                    <th>Fichadas</th>
                    <th class="text-center">Trabajado</th>
                    <th class="text-center d-none d-md-table-cell">Tarde</th>
                    <th class="text-center d-none d-lg-table-cell">Sal. antic.</th>
                    <th class="text-center d-none d-md-table-cell">Extra 50</th>
                    <th class="text-center d-none d-md-table-cell">Extra 100</th>
                    <th class="text-center">Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @foreach($dias as $dia)
                <tr class="{{ $dia->fecha->isToday() ? 'table-primary' : ($dia->fecha->isWeekend() || $dia->feriado ? 'table-light' : '') }}">
                    <td class="text-nowrap">
                        <span class="fw-semibold">{{ $dia->fecha->format('d') }}</span>
                        <span class="small text-muted">{{ \App\Models\Horario::DIAS_CORTOS[$dia->fecha->dayOfWeekIso] }}</span>
                        @if($dia->feriado)<div class="small text-muted text-truncate" style="max-width:140px" title="{{ $dia->feriado }}">{{ $dia->feriado }}</div>@endif
                    </td>
                    <td class="d-none d-md-table-cell small">{{ $dia->textoProgramado() }}</td>
                    <td>
                        @foreach($dia->fichadas() as $f)
                            <button type="button"
                                    class="badge border-0 font-monospace {{ $f->esEntrada() ? 'text-bg-success' : 'text-bg-danger' }} {{ $f->editado_por ? 'opacity-75' : '' }}"
                                    title="{{ $f->tipo_nombre }} · {{ $f->origen_nombre }}{{ $f->observacion ? ' · ' . $f->observacion : '' }} — clic para editar"
                                    data-fichada="{{ json_encode([
                                        'id'          => $f->id,
                                        'empleado_id' => $f->empleado_id,
                                        'fecha'       => $f->fecha_hora->format('Y-m-d'),
                                        'hora'        => $f->fecha_hora->format('H:i'),
                                        'tipo'        => $f->tipo,
                                        'observacion' => $f->observacion,
                                    ]) }}"
                                    onclick="abrirFichada(JSON.parse(this.dataset.fichada))">
                                {{ $f->esEntrada() ? '↘' : '↗' }} {{ $f->fecha_hora->format('H:i') }}@unless($f->fecha_hora->isSameDay($dia->fecha))<sup>+1</sup>@endunless
                            </button>
                        @endforeach
                    </td>
                    <td class="text-center">{{ $Formato::horas($dia->minutosTrabajados) }}</td>
                    <td class="text-center d-none d-md-table-cell {{ $dia->minutosTarde ? 'text-danger' : 'text-muted' }}">
                        {{ $dia->minutosTarde ? $dia->minutosTarde . "'" : '—' }}
                    </td>
                    <td class="text-center d-none d-lg-table-cell {{ $dia->minutosSalidaAnticipada ? 'text-danger' : 'text-muted' }}">
                        {{ $dia->minutosSalidaAnticipada ? $dia->minutosSalidaAnticipada . "'" : '—' }}
                    </td>
                    <td class="text-center d-none d-md-table-cell">{{ $Formato::horas($dia->extra50) }}</td>
                    <td class="text-center d-none d-md-table-cell">{{ $Formato::horas($dia->extra100) }}</td>
                    <td class="text-center">
                        <span class="badge text-bg-{{ $dia->color() }}">{{ $dia->etiqueta() }}</span>
                        @if($dia->licencia)<div class="small text-muted text-truncate" style="max-width:140px" title="{{ $dia->licencia }}">{{ $dia->licencia }}</div>@endif
                    </td>
                    <td class="text-end">
                        @if($dia->estado !== 'fuera' && $dia->fecha->lte(today()))
                            <button class="btn btn-sm btn-link p-0" title="Agregar fichada"
                                    onclick="abrirFichada({ empleado_id: {{ $e->id }}, fecha: @js($dia->fecha->format('Y-m-d')), tipo: @js($dia->entradaReal && ! $dia->salidaReal ? 'salida' : 'entrada') })">
                                <i class="bi bi-plus-circle"></i>
                            </button>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
<p class="small text-muted mt-2 mb-0">
    ↘ entrada · ↗ salida · <sup>+1</sup> al día siguiente. Hacé clic en una fichada para corregirla o eliminarla.
    <a href="{{ route('fichadas.index', ['empleado_id' => $e->id, 'desde' => $desde->format('Y-m-d'), 'hasta' => $desde->copy()->endOfMonth()->format('Y-m-d')]) }}">Ver el listado de fichadas del mes</a>.
</p>

@include('asistencia._modal_fichada')
@endsection
