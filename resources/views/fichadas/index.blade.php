@extends('layouts.app')

@section('title', 'Fichadas')

@section('content')
@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<div class="d-flex flex-wrap gap-2 justify-content-between align-items-end mb-3">
    <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
        <div>
            <label class="form-label small mb-1">Desde</label>
            <input type="date" name="desde" value="{{ $desde->format('Y-m-d') }}" class="form-control">
        </div>
        <div>
            <label class="form-label small mb-1">Hasta</label>
            <input type="date" name="hasta" value="{{ $hasta->format('Y-m-d') }}" class="form-control">
        </div>
        <div>
            <label class="form-label small mb-1">Empleado</label>
            <select name="empleado_id" class="form-select" style="max-width:240px">
                <option value="">Todos</option>
                @foreach($empleados as $emp)
                    <option value="{{ $emp->id }}" @selected((string) $empleadoId === (string) $emp->id)>{{ $emp->nombre_completo }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label small mb-1">Origen</label>
            <select name="origen" class="form-select">
                <option value="">Todos</option>
                @foreach(\App\Models\Fichada::ORIGENES as $k => $v)
                    <option value="{{ $k }}" @selected($origen === $k)>{{ $v }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-outline-secondary"><i class="bi bi-funnel"></i></button>
    </form>
    <div class="d-flex gap-2">
        <a href="{{ route('fichadas.importar') }}" class="btn btn-outline-primary"><i class="bi bi-upload me-1"></i>Importar</a>
        <button class="btn btn-primary" onclick="abrirFichada()"><i class="bi bi-plus-lg me-1"></i>Nueva fichada</button>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Fecha</th>
                    <th>Hora</th>
                    <th>Empleado</th>
                    <th>Tipo</th>
                    <th class="d-none d-md-table-cell">Origen</th>
                    <th class="d-none d-lg-table-cell">Observación</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse($fichadas as $f)
                <tr>
                    <td class="text-nowrap">
                        {{ $f->fecha_hora->format('d/m/Y') }}
                        <span class="small text-muted">{{ \App\Models\Horario::DIAS_CORTOS[$f->fecha_hora->dayOfWeekIso] }}</span>
                    </td>
                    <td class="font-monospace">{{ $f->fecha_hora->format('H:i') }}</td>
                    <td>
                        @if($f->empleado)
                            <a href="{{ route('asistencia.empleado', [$f->empleado, 'mes' => $f->fecha_hora->format('Y-m')]) }}" class="text-decoration-none">
                                {{ $f->empleado->nombre_completo }}
                            </a>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge {{ $f->esEntrada() ? 'text-bg-success' : 'text-bg-danger' }}">{{ $f->tipo_nombre }}</span>
                    </td>
                    <td class="d-none d-md-table-cell small">
                        {{ $f->origen_nombre }}
                        @if($f->editado_por)
                            <span class="badge text-bg-warning" title="Editada por {{ $f->editadoPor?->name }} el {{ $f->updated_at->format('d/m/Y H:i') }}">editada</span>
                        @endif
                        <div class="text-muted">{{ $f->registradoPor?->name }}</div>
                    </td>
                    <td class="d-none d-lg-table-cell small text-muted">{{ $f->observacion }}</td>
                    <td class="text-end text-nowrap">
                        <button class="btn btn-sm btn-outline-primary" title="Editar"
                                data-fichada="{{ json_encode([
                                    'id'          => $f->id,
                                    'empleado_id' => $f->empleado_id,
                                    'fecha'       => $f->fecha_hora->format('Y-m-d'),
                                    'hora'        => $f->fecha_hora->format('H:i'),
                                    'tipo'        => $f->tipo,
                                    'observacion' => $f->observacion,
                                ]) }}"
                                onclick="abrirFichada(JSON.parse(this.dataset.fichada))"><i class="bi bi-pencil"></i></button>
                        <form method="POST" action="{{ route('fichadas.destroy', $f) }}" class="d-inline"
                              onsubmit="return confirm('¿Eliminar la fichada del {{ $f->fecha_hora->format('d/m/Y H:i') }}?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No hay fichadas en el período.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span class="small text-muted">{{ $fichadas->total() }} fichadas</span>
    {{ $fichadas->links() }}
</div>

@include('asistencia._modal_fichada')
@endsection
