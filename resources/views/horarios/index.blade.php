@extends('layouts.app')

@section('title', 'Horarios')

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <p class="text-muted small mb-0">Cada empleado tiene un horario asignado en su legajo. Se usa para calcular llegadas tarde, ausencias y horas extra.</p>
    <a href="{{ route('horarios.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Nuevo horario
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Horario</th>
                    <th>Días y horas</th>
                    <th class="text-center d-none d-md-table-cell">Hs. semanales</th>
                    <th class="text-center d-none d-md-table-cell">Tolerancia</th>
                    <th class="text-center">Empleados</th>
                    <th class="text-center">Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse($horarios as $h)
                <tr>
                    <td class="fw-semibold">{{ $h->nombre }}</td>
                    <td class="small">{{ $h->resumen }}</td>
                    <td class="text-center d-none d-md-table-cell">{{ \App\Support\Formato::horas($h->minutos_semanales) }}</td>
                    <td class="text-center d-none d-md-table-cell">{{ $h->tolerancia_minutos }} min</td>
                    <td class="text-center">{{ $h->empleados_count }}</td>
                    <td class="text-center">
                        <span class="badge {{ $h->activo ? 'text-bg-success' : 'text-bg-secondary' }}">
                            {{ $h->activo ? 'Activo' : 'Inactivo' }}
                        </span>
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('horarios.edit', $h) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form method="POST" action="{{ route('horarios.destroy', $h) }}" class="d-inline"
                              onsubmit="return confirm('¿Eliminar el horario {{ addslashes($h->nombre) }}?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No hay horarios cargados.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
