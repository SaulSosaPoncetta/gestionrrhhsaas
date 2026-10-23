@extends('layouts.app')

@section('title', 'Puestos')

@section('content')
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <form method="GET" class="d-flex flex-wrap gap-2">
        <input type="text" name="buscar" value="{{ $buscar }}" class="form-control" style="max-width:220px" placeholder="Nombre del puesto">
        <select name="area_id" class="form-select" style="max-width:240px">
            <option value="">Todas las áreas</option>
            <option value="sin" @selected($areaId === 'sin')>Sin área asignada</option>
            @foreach($areas as $a)
                <option value="{{ $a->id }}" @selected((string) $areaId === (string) $a->id)>
                    {{ str_repeat('— ', $a->nivel) }}{{ $a->nombre }}
                </option>
            @endforeach
        </select>
        <button class="btn btn-outline-secondary"><i class="bi bi-funnel"></i></button>
        @if($buscar || $areaId)
            <a href="{{ route('puestos.index') }}" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
        @endif
    </form>
    <a href="{{ route('puestos.create', ['area' => is_numeric($areaId) ? $areaId : null]) }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Nuevo puesto
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Puesto</th>
                    <th>Área</th>
                    <th class="d-none d-lg-table-cell">Descripción</th>
                    <th class="text-center">Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse($puestos as $puesto)
                <tr>
                    <td class="fw-semibold">{{ $puesto->nombre }}</td>
                    <td>{{ $puesto->area?->nombre ?? '—' }}</td>
                    <td class="d-none d-lg-table-cell small text-muted">{{ \Illuminate\Support\Str::limit($puesto->descripcion, 80) }}</td>
                    <td class="text-center">
                        <span class="badge {{ $puesto->activo ? 'text-bg-success' : 'text-bg-secondary' }}">
                            {{ $puesto->activo ? 'Activo' : 'Inactivo' }}
                        </span>
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('puestos.edit', $puesto) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form method="POST" action="{{ route('puestos.destroy', $puesto) }}" class="d-inline"
                              onsubmit="return confirm('¿Eliminar el puesto {{ addslashes($puesto->nombre) }}?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">No hay puestos para mostrar.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $puestos->links() }}</div>
@endsection
