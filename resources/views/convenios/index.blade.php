@extends('layouts.app')

@section('title', 'Convenios colectivos')

@section('content')
<div class="d-flex justify-content-end mb-3">
    <a href="{{ route('convenios.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Nuevo convenio
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Convenio</th>
                    <th>N° CCT</th>
                    <th class="d-none d-md-table-cell">Sindicato</th>
                    <th class="d-none d-md-table-cell text-center">Hs. semanales</th>
                    <th class="text-center">Categorías</th>
                    <th class="text-center">Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse($convenios as $convenio)
                <tr>
                    <td class="fw-semibold">{{ $convenio->nombre }}</td>
                    <td>{{ $convenio->numero ?? '—' }}</td>
                    <td class="d-none d-md-table-cell">{{ $convenio->sindicato ?? '—' }}</td>
                    <td class="d-none d-md-table-cell text-center">
                        {{ $convenio->horas_semanales ? rtrim(rtrim(number_format($convenio->horas_semanales, 2, ',', '.'), '0'), ',') : '—' }}
                    </td>
                    <td class="text-center">
                        <a href="{{ route('convenios.categorias.index', $convenio) }}" class="btn btn-sm btn-outline-secondary">
                            {{ $convenio->categorias_count }} <i class="bi bi-chevron-right small"></i>
                        </a>
                    </td>
                    <td class="text-center">
                        <span class="badge {{ $convenio->activo ? 'text-bg-success' : 'text-bg-secondary' }}">
                            {{ $convenio->activo ? 'Activo' : 'Inactivo' }}
                        </span>
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('convenios.edit', $convenio) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form method="POST" action="{{ route('convenios.destroy', $convenio) }}" class="d-inline"
                              onsubmit="return confirm('¿Eliminar el convenio {{ addslashes($convenio->nombre) }}?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No hay convenios cargados.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
