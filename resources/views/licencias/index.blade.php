@extends('layouts.app')

@section('title', 'Licencias')

@section('content')
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-end mb-3">
    <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
        <div>
            <label class="form-label small mb-1">Estado</label>
            <select name="estado" class="form-select">
                <option value="">Todos</option>
                @foreach(\App\Models\Licencia::ESTADOS as $k => [$nombre])
                    <option value="{{ $k }}" @selected($estado === $k)>{{ $nombre }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label small mb-1">Tipo</label>
            <select name="tipo_id" class="form-select" style="max-width:220px">
                <option value="">Todos</option>
                @foreach($tipos as $t)
                    <option value="{{ $t->id }}" @selected((string) $tipoId === (string) $t->id)>{{ $t->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label small mb-1">Empleado</label>
            <select name="empleado_id" class="form-select" style="max-width:220px">
                <option value="">Todos</option>
                @foreach($empleados as $emp)
                    <option value="{{ $emp->id }}" @selected((string) $empleadoId === (string) $emp->id)>{{ $emp->nombre_completo }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label small mb-1">Año</label>
            <input type="number" name="anio" value="{{ $anio }}" class="form-control" style="width:100px" min="2000" max="2100" placeholder="Todos">
        </div>
        <button class="btn btn-outline-secondary"><i class="bi bi-funnel"></i></button>
        @if($estado || $tipoId || $empleadoId || $anio)
            <a href="{{ route('licencias.index') }}" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
        @endif
    </form>
    <div class="d-flex gap-2">
        @if($pendientes && $estado !== 'pendiente')
            <a href="{{ route('licencias.index', ['estado' => 'pendiente']) }}" class="btn btn-warning">
                <i class="bi bi-hourglass-split me-1"></i>{{ $pendientes }} pendiente{{ $pendientes === 1 ? '' : 's' }}
            </a>
        @endif
        <a href="{{ route('licencias.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Nueva licencia</a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Empleado</th>
                    <th>Tipo</th>
                    <th>Período</th>
                    <th class="text-center">Días</th>
                    <th class="text-center">Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse($licencias as $l)
                <tr class="{{ $l->esPendiente() ? 'table-warning' : '' }}">
                    <td>
                        @if($l->empleado)
                            <a href="{{ route('empleados.show', $l->empleado) }}#licencias" class="fw-semibold text-decoration-none text-dark">{{ $l->empleado->nombre_completo }}</a>
                            <div class="small text-muted">Leg. {{ $l->empleado->legajo }}</div>
                        @else
                            <span class="text-muted">Empleado eliminado</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge text-bg-{{ $l->tipo->color }}">{{ $l->tipo->nombre }}</span>
                        @if($l->periodo)<div class="small text-muted">Período {{ $l->periodo }}</div>@endif
                        @if($l->observaciones)<div class="small text-muted text-truncate" style="max-width:240px" title="{{ $l->observaciones }}">{{ $l->observaciones }}</div>@endif
                    </td>
                    <td class="text-nowrap">{{ $l->periodo_texto }}</td>
                    <td class="text-center">
                        {{ $l->dias }}
                        <div class="small text-muted">{{ $l->tipo->esHabiles() ? 'hábiles' : 'corridos' }}</div>
                    </td>
                    <td class="text-center">
                        <span class="badge text-bg-{{ $l->estado_color }}">{{ $l->estado_nombre }}</span>
                        @if($l->resueltaPor && ! $l->esPendiente())
                            <div class="small text-muted" title="{{ $l->resuelta_at?->format('d/m/Y H:i') }}">{{ $l->resueltaPor->name }}</div>
                        @endif
                        @if($l->motivo_rechazo)<div class="small text-danger">{{ $l->motivo_rechazo }}</div>@endif
                    </td>
                    <td class="text-end text-nowrap">
                        @if($l->certificado)
                            <a href="{{ route('licencias.certificado', $l) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="Ver certificado">
                                <i class="bi bi-file-earmark-medical"></i>
                            </a>
                        @elseif($l->faltaCertificado())
                            <span class="btn btn-sm btn-outline-warning disabled" title="Falta el certificado"><i class="bi bi-exclamation-triangle"></i></span>
                        @endif

                        @if($l->esPendiente())
                            <form method="POST" action="{{ route('licencias.aprobar', $l) }}" class="d-inline">
                                @csrf @method('PATCH')
                                <button class="btn btn-sm btn-success" title="Aprobar"><i class="bi bi-check-lg"></i></button>
                            </form>
                            <form method="POST" action="{{ route('licencias.rechazar', $l) }}" class="d-inline"
                                  onsubmit="const m = prompt('Motivo del rechazo (opcional):'); if (m === null) return false; this.motivo_rechazo.value = m;">
                                @csrf @method('PATCH')
                                <input type="hidden" name="motivo_rechazo">
                                <button class="btn btn-sm btn-outline-danger" title="Rechazar"><i class="bi bi-x-lg"></i></button>
                            </form>
                        @elseif($l->esAprobada())
                            <form method="POST" action="{{ route('licencias.cancelar', $l) }}" class="d-inline"
                                  onsubmit="return confirm('¿Cancelar esta licencia? Los días vuelven al saldo y las ausencias dejan de estar justificadas.')">
                                @csrf @method('PATCH')
                                <button class="btn btn-sm btn-outline-secondary" title="Cancelar"><i class="bi bi-slash-circle"></i></button>
                            </form>
                        @endif

                        <a href="{{ route('licencias.edit', $l) }}" class="btn btn-sm btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                        <form method="POST" action="{{ route('licencias.destroy', $l) }}" class="d-inline"
                              onsubmit="return confirm('¿Eliminar esta licencia? Si fue cargada por error está bien; si no, conviene cancelarla.')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No hay licencias para mostrar.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $licencias->links() }}</div>
@endsection
