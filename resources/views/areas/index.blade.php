@extends('layouts.app')

@section('title', 'Áreas')

@section('content')
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <form method="GET" class="d-flex gap-2">
        <input type="text" name="buscar" value="{{ $buscar }}" class="form-control" placeholder="Nombre o código">
        <button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
        @if($buscar)
            <a href="{{ route('areas.index') }}" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
        @endif
    </form>
    <a href="{{ route('areas.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Nueva área
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Área</th>
                    <th class="d-none d-md-table-cell">Código</th>
                    @if($buscar)<th class="d-none d-md-table-cell">Depende de</th>@endif
                    <th class="text-center">Puestos</th>
                    <th class="text-center">Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse($areas as $area)
                <tr>
                    <td>
                        <span style="padding-left: {{ $area->nivel * 1.5 }}rem">
                            @if($area->nivel > 0)<i class="bi bi-arrow-return-right text-muted me-1"></i>@endif
                            <i class="bi bi-diagram-3 text-primary me-1"></i>
                            <span class="fw-semibold">{{ $area->nombre }}</span>
                        </span>
                    </td>
                    <td class="d-none d-md-table-cell">{{ $area->codigo ?? '—' }}</td>
                    @if($buscar)
                        <td class="d-none d-md-table-cell">{{ $area->padre?->nombre ?? '—' }}</td>
                    @endif
                    <td class="text-center">
                        <a href="{{ route('puestos.index', ['area_id' => $area->id]) }}" class="text-decoration-none">
                            {{ $area->puestos_count }}
                        </a>
                    </td>
                    <td class="text-center">
                        <span class="badge {{ $area->activa ? 'text-bg-success' : 'text-bg-secondary' }}">
                            {{ $area->activa ? 'Activa' : 'Inactiva' }}
                        </span>
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('areas.create', ['padre' => $area->id]) }}" class="btn btn-sm btn-outline-secondary" title="Agregar sub-área">
                            <i class="bi bi-node-plus"></i>
                        </a>
                        <a href="{{ route('areas.edit', $area) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form method="POST" action="{{ route('areas.destroy', $area) }}" class="d-inline"
                              onsubmit="return confirm('¿Eliminar el área {{ addslashes($area->nombre) }}?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No hay áreas cargadas.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
