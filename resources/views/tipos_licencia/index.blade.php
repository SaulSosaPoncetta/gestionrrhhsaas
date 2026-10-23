@extends('layouts.app')

@section('title', 'Tipos de licencia')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <form method="POST" action="{{ route('tipos-licencia.predeterminados') }}">
        @csrf
        <button class="btn btn-outline-primary"><i class="bi bi-magic me-1"></i>Cargar los tipos de la LCT</button>
    </form>
    <a href="{{ route('tipos-licencia.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Nuevo tipo</a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Tipo</th>
                    <th class="text-center">Días</th>
                    <th class="text-center d-none d-md-table-cell">Máximo</th>
                    <th class="text-center d-none d-md-table-cell">Con goce</th>
                    <th class="text-center d-none d-md-table-cell">Certificado</th>
                    <th class="text-center d-none d-lg-table-cell">Licencias</th>
                    <th class="text-center">Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse($tipos as $t)
                <tr>
                    <td>
                        <span class="badge text-bg-{{ $t->color }} me-1">{{ $t->codigo ?: '·' }}</span>
                        <span class="fw-semibold">{{ $t->nombre }}</span>
                        @if($t->es_vacaciones)<span class="badge text-bg-light">vacaciones</span>@endif
                        @if($t->descripcion)<div class="small text-muted">{{ $t->descripcion }}</div>@endif
                    </td>
                    <td class="text-center small">{{ $t->esHabiles() ? 'Hábiles' : 'Corridos' }}</td>
                    <td class="text-center d-none d-md-table-cell small">
                        @if($t->es_vacaciones)
                            Según antigüedad
                        @else
                            {{ $t->dias_maximos ? $t->dias_maximos . ' por licencia' : '—' }}
                            @if($t->maximo_anual)<div class="text-muted">{{ $t->maximo_anual }} por año</div>@endif
                        @endif
                    </td>
                    <td class="text-center d-none d-md-table-cell">{!! $t->con_goce ? '<i class="bi bi-check-lg text-success"></i>' : '<span class="text-danger small">Sin goce</span>' !!}</td>
                    <td class="text-center d-none d-md-table-cell">{!! $t->requiere_certificado ? '<i class="bi bi-file-earmark-medical text-primary"></i>' : '—' !!}</td>
                    <td class="text-center d-none d-lg-table-cell">{{ $t->licencias_count }}</td>
                    <td class="text-center">
                        <span class="badge {{ $t->activo ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $t->activo ? 'Activo' : 'Inactivo' }}</span>
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('tipos-licencia.edit', $t) }}" class="btn btn-sm btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                        <form method="POST" action="{{ route('tipos-licencia.destroy', $t) }}" class="d-inline"
                              onsubmit="return confirm('¿Eliminar el tipo {{ addslashes($t->nombre) }}?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">
                    No hay tipos de licencia. Usá «Cargar los tipos de la LCT» para empezar con los de la ley.
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<p class="small text-muted mt-2 mb-0">Si el convenio colectivo da más días que la ley, editá el tipo y cambiá el máximo.</p>
@endsection
