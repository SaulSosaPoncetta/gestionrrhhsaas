@extends('layouts.app')

@section('title', 'Legajos')

@section('content')
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <form method="GET" class="d-flex flex-wrap gap-2">
        <input type="text" name="buscar" value="{{ $buscar }}" class="form-control" style="max-width:240px"
               placeholder="Nombre, legajo, DNI o CUIL">
        <select name="area_id" class="form-select" style="max-width:220px">
            <option value="">Todas las áreas</option>
            @foreach($areas as $a)
                <option value="{{ $a->id }}" @selected((string) $areaId === (string) $a->id)>
                    {{ str_repeat('— ', $a->nivel) }}{{ $a->nombre }}
                </option>
            @endforeach
        </select>
        <select name="estado" class="form-select" style="max-width:150px">
            <option value="activos" @selected($estado === 'activos')>Activos</option>
            <option value="bajas" @selected($estado === 'bajas')>Dados de baja</option>
            <option value="todos" @selected($estado === 'todos')>Todos</option>
        </select>
        <button class="btn btn-outline-secondary"><i class="bi bi-funnel"></i></button>
        @if($buscar || $areaId || $estado !== 'activos')
            <a href="{{ route('empleados.index') }}" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
        @endif
    </form>
    <a href="{{ route('empleados.create') }}" class="btn btn-primary">
        <i class="bi bi-person-plus me-1"></i>Nuevo legajo
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:80px">Legajo</th>
                    <th>Empleado</th>
                    <th class="d-none d-md-table-cell">CUIL</th>
                    <th class="d-none d-lg-table-cell">Área / Puesto</th>
                    <th class="d-none d-lg-table-cell">Categoría</th>
                    <th class="d-none d-md-table-cell">Ingreso</th>
                    <th class="text-center">Estado</th>
                </tr>
            </thead>
            <tbody>
            @forelse($empleados as $emp)
                <tr style="cursor:pointer" onclick="window.location='{{ route('empleados.show', $emp) }}'">
                    <td class="text-muted">{{ $emp->legajo }}</td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            @if($emp->foto_url)
                                <img src="{{ $emp->foto_url }}" alt="" width="36" height="36" class="rounded-circle object-fit-cover">
                            @else
                                <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary bg-opacity-10 text-primary fw-semibold small"
                                      style="width:36px;height:36px">{{ $emp->iniciales }}</span>
                            @endif
                            <div>
                                <a href="{{ route('empleados.show', $emp) }}" class="fw-semibold text-decoration-none text-dark">{{ $emp->nombre_completo }}</a>
                                <div class="small text-muted d-md-none">{{ $emp->cuil }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="d-none d-md-table-cell">{{ $emp->cuil }}</td>
                    <td class="d-none d-lg-table-cell">
                        <div>{{ $emp->area?->nombre ?? '—' }}</div>
                        <div class="small text-muted">{{ $emp->puesto?->nombre }}</div>
                    </td>
                    <td class="d-none d-lg-table-cell">{{ $emp->categoria?->nombre ?? 'Fuera de convenio' }}</td>
                    <td class="d-none d-md-table-cell">{{ $emp->fecha_ingreso->format('d/m/Y') }}</td>
                    <td class="text-center">
                        @if($emp->activo)
                            <span class="badge text-bg-success">Activo</span>
                        @else
                            <span class="badge text-bg-secondary" title="{{ $emp->motivo_egreso_nombre }}">Baja {{ $emp->fecha_egreso->format('d/m/Y') }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No hay empleados para mostrar.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span class="small text-muted">{{ $empleados->total() }} {{ $empleados->total() === 1 ? 'empleado' : 'empleados' }}</span>
    {{ $empleados->links() }}
</div>
@endsection
