@extends('layouts.app')

@section('title', 'Parte diario')

@section('content')
@php
    $Formato = \App\Support\Formato::class;
@endphp

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <form method="GET" class="d-flex flex-wrap gap-2 align-items-center">
        <div class="input-group" style="width:auto">
            <a href="{{ route('asistencia.parte', ['fecha' => $fecha->copy()->subDay()->format('Y-m-d'), 'area_id' => $areaId]) }}"
               class="btn btn-outline-secondary" title="Día anterior"><i class="bi bi-chevron-left"></i></a>
            <input type="date" name="fecha" value="{{ $fecha->format('Y-m-d') }}" class="form-control" onchange="this.form.submit()">
            <a href="{{ route('asistencia.parte', ['fecha' => $fecha->copy()->addDay()->format('Y-m-d'), 'area_id' => $areaId]) }}"
               class="btn btn-outline-secondary" title="Día siguiente"><i class="bi bi-chevron-right"></i></a>
        </div>
        <select name="area_id" class="form-select" style="max-width:220px" onchange="this.form.submit()">
            <option value="">Todas las áreas</option>
            @foreach($areas as $a)
                <option value="{{ $a->id }}" @selected((string) $areaId === (string) $a->id)>{{ str_repeat('— ', $a->nivel) }}{{ $a->nombre }}</option>
            @endforeach
        </select>
        @if($estado)<input type="hidden" name="estado" value="{{ $estado }}">@endif
        @unless($fecha->isToday())
            <a href="{{ route('asistencia.parte', ['area_id' => $areaId]) }}" class="btn btn-outline-secondary">Hoy</a>
        @endunless
    </form>
    <button class="btn btn-primary" onclick="abrirFichada({ fecha: @js($fecha->format('Y-m-d')) })">
        <i class="bi bi-plus-lg me-1"></i>Registrar fichada
    </button>
</div>

<h2 class="h6 text-muted mb-3">
    {{ ucfirst($fecha->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY')) }}
</h2>

{{-- Contadores (clic para filtrar) --}}
@php
    $tarjetas = [
        'presente'   => ['Presentes', 'success'],
        'trabajando' => ['En jornada', 'primary'],
        'tarde'      => ['Llegadas tarde', 'warning'],
        'ausente'    => ['Ausentes', 'danger'],
        'licencia'   => ['De licencia', 'info'],
        'incompleto' => ['Incompletas', 'info'],
        'pendiente'  => ['Pendientes', 'secondary'],
    ];
@endphp
<div class="row g-2 mb-3">
    @foreach($tarjetas as $clave => [$titulo, $color])
        <div class="col-6 col-md-3 col-xl">
            <a href="{{ route('asistencia.parte', ['fecha' => $fecha->format('Y-m-d'), 'area_id' => $areaId, 'estado' => $estado === $clave ? null : $clave]) }}"
               class="card border-0 shadow-sm text-decoration-none h-100 {{ $estado === $clave ? 'border border-2 border-' . $color : '' }}">
                <div class="card-body py-2">
                    <div class="small text-muted">{{ $titulo }}</div>
                    <div class="fs-4 fw-semibold text-{{ $color === 'secondary' ? 'dark' : $color }}">{{ $conteo[$clave] ?? 0 }}</div>
                </div>
            </a>
        </div>
    @endforeach
</div>

@if($estado)
    <div class="mb-2 small">
        Mostrando: <span class="badge text-bg-dark">{{ \App\Services\Asistencia\DiaAsistencia::ESTADOS[$estado][0] ?? $estado }}</span>
        <a href="{{ route('asistencia.parte', ['fecha' => $fecha->format('Y-m-d'), 'area_id' => $areaId]) }}" class="ms-1">ver todos</a>
    </div>
@endif

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Empleado</th>
                    <th class="d-none d-md-table-cell">Horario</th>
                    <th class="text-center">Entrada</th>
                    <th class="text-center">Salida</th>
                    <th class="text-center d-none d-md-table-cell">Trabajado</th>
                    <th class="text-center d-none d-lg-table-cell">Tarde</th>
                    <th class="text-center">Estado</th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
            @forelse($filas as ['empleado' => $emp, 'dia' => $dia])
                <tr>
                    <td>
                        <a href="{{ route('asistencia.empleado', [$emp, 'mes' => $fecha->format('Y-m')]) }}" class="fw-semibold text-decoration-none text-dark">
                            {{ $emp->nombre_completo }}
                        </a>
                        <div class="small text-muted">Leg. {{ $emp->legajo }}@if($emp->area) · {{ $emp->area->nombre }}@endif</div>
                    </td>
                    <td class="d-none d-md-table-cell small">{{ $dia->textoProgramado() }}</td>
                    <td class="text-center font-monospace {{ $dia->minutosTarde ? 'text-danger fw-semibold' : '' }}">
                        {{ $dia->entradaReal?->format('H:i') ?? '—' }}
                    </td>
                    <td class="text-center font-monospace {{ $dia->minutosSalidaAnticipada ? 'text-danger' : '' }}">
                        {{ $dia->salidaReal?->format('H:i') ?? '—' }}
                    </td>
                    <td class="text-center d-none d-md-table-cell">{{ $Formato::horas($dia->minutosTrabajados) }}</td>
                    <td class="text-center d-none d-lg-table-cell">{{ $dia->minutosTarde ? $dia->minutosTarde . ' min' : '—' }}</td>
                    <td class="text-center">
                        <span class="badge text-bg-{{ $dia->color() }}" title="{{ $dia->feriado }}">{{ $dia->etiqueta() }}</span>
                        @if($dia->licencia)<div class="small text-muted">{{ $dia->licencia }}</div>@endif
                    </td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-secondary" title="Registrar fichada"
                                onclick="abrirFichada({ empleado_id: {{ $emp->id }}, fecha: @js($fecha->format('Y-m-d')), tipo: @js($dia->entradaReal && ! $dia->salidaReal ? 'salida' : 'entrada') })">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No hay empleados para mostrar.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('asistencia._modal_fichada')
@endsection
