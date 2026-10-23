@extends('layouts.app')

@section('title', 'Mi equipo')

@section('content')
@if($pendientes->isNotEmpty())
    <div class="card border-0 shadow-sm mb-3 border-start border-warning border-4">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-hourglass-split text-warning me-1"></i>Licencias para aprobar</div>
        <ul class="list-group list-group-flush">
            @foreach($pendientes as $l)
                <li class="list-group-item">
                    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
                        <div>
                            <div class="fw-semibold">{{ $l->empleado->nombre_completo }}</div>
                            <span class="badge text-bg-{{ $l->tipo->color }}">{{ $l->tipo->nombre }}</span>
                            <span class="small">{{ $l->periodo_texto }} · {{ $l->dias }} días</span>
                            @if($l->observaciones)<div class="small text-muted">«{{ $l->observaciones }}»</div>@endif
                            @if($l->faltaCertificado())<div class="small text-warning"><i class="bi bi-exclamation-triangle"></i> Sin certificado</div>@endif
                        </div>
                        <div class="d-flex gap-2">
                            <form method="POST" action="{{ route('portal.equipo.aprobar', $l) }}">
                                @csrf @method('PATCH')
                                <button class="btn btn-success btn-sm"><i class="bi bi-check-lg me-1"></i>Aprobar</button>
                            </form>
                            <form method="POST" action="{{ route('portal.equipo.rechazar', $l) }}"
                                  onsubmit="const m = prompt('Motivo del rechazo (opcional):'); if (m === null) return false; this.motivo_rechazo.value = m;">
                                @csrf @method('PATCH')
                                <input type="hidden" name="motivo_rechazo">
                                <button class="btn btn-outline-danger btn-sm"><i class="bi bi-x-lg me-1"></i>Rechazar</button>
                            </form>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-clipboard-check me-1"></i>Asistencia de hoy</div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr><th>Empleado</th><th class="d-none d-sm-table-cell">Horario</th><th class="text-center">Entrada</th><th class="text-center">Salida</th><th class="text-center">Estado</th></tr>
            </thead>
            <tbody>
            @forelse($filas as $fila)
                <tr>
                    <td>
                        <span class="fw-semibold">{{ $fila['empleado']->nombre_completo }}</span>
                        <div class="small text-muted">{{ $fila['empleado']->puesto?->nombre }}</div>
                    </td>
                    <td class="small d-none d-sm-table-cell">{{ $fila['dia']->textoProgramado() }}</td>
                    <td class="text-center font-monospace {{ $fila['dia']->minutosTarde ? 'text-danger fw-semibold' : '' }}">{{ $fila['dia']->entradaReal?->format('H:i') ?? '—' }}</td>
                    <td class="text-center font-monospace">{{ $fila['dia']->salidaReal?->format('H:i') ?? '—' }}</td>
                    <td class="text-center">
                        <span class="badge text-bg-{{ $fila['dia']->color() }}">{{ $fila['dia']->etiqueta() }}</span>
                        @if($fila['dia']->licencia)<div class="small text-muted">{{ $fila['dia']->licencia }}</div>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">No tenés personal a cargo asignado. RRHH lo define en el legajo de cada empleado.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-calendar-week me-1"></i>Licencias aprobadas en los próximos 30 días</div>
    <ul class="list-group list-group-flush">
        @forelse($proximas as $l)
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <span>{{ $l->empleado->nombre_completo }}</span>
                <span class="small"><span class="badge text-bg-{{ $l->tipo->color }}">{{ $l->tipo->nombre }}</span> {{ $l->periodo_texto }}</span>
            </li>
        @empty
            <li class="list-group-item text-muted small">Nadie de tu equipo tiene licencias en los próximos 30 días.</li>
        @endforelse
    </ul>
</div>
@endsection
