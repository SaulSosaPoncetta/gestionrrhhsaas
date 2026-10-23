@extends('layouts.app')

@section('title', 'Categorías · ' . $convenio->nombre)

@section('content')
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <div>
        <a href="{{ route('convenios.index') }}" class="text-decoration-none small"><i class="bi bi-arrow-left"></i> Convenios</a>
        <h2 class="h5 mb-0 mt-1">{{ $convenio->nombre_completo }}</h2>
        @if($convenio->sindicato)<div class="small text-muted">{{ $convenio->sindicato }}</div>@endif
    </div>
    <div class="d-flex gap-2">
        @if($convenio->categorias->isNotEmpty())
            <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalAumento">
                <i class="bi bi-graph-up-arrow me-1"></i>Aplicar aumento
            </button>
        @endif
        <a href="{{ route('convenios.categorias.create', $convenio) }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Nueva categoría
        </a>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:70px">Orden</th>
                    <th>Categoría</th>
                    <th class="text-end">Sueldo básico</th>
                    <th class="d-none d-md-table-cell text-center">Vigencia desde</th>
                    <th class="text-center">Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse($convenio->categorias as $cat)
                <tr>
                    <td class="text-muted">{{ $cat->orden }}</td>
                    <td class="fw-semibold">{{ $cat->nombre }}</td>
                    <td class="text-end font-monospace">{{ $cat->basico_formateado }}</td>
                    <td class="d-none d-md-table-cell text-center">{{ $cat->vigencia_desde?->format('d/m/Y') ?? '—' }}</td>
                    <td class="text-center">
                        <span class="badge {{ $cat->activa ? 'text-bg-success' : 'text-bg-secondary' }}">
                            {{ $cat->activa ? 'Activa' : 'Inactiva' }}
                        </span>
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('categorias.edit', $cat) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form method="POST" action="{{ route('categorias.destroy', $cat) }}" class="d-inline"
                              onsubmit="return confirm('¿Eliminar la categoría {{ addslashes($cat->nombre) }}?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">Este convenio todavía no tiene categorías.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Modal aumento paritario --}}
<div class="modal fade" id="modalAumento" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('convenios.aumento', $convenio) }}" class="modal-content"
              onsubmit="return confirm('Se va a modificar el básico de TODAS las categorías del convenio. ¿Continuar?')">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Aplicar aumento paritario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <div class="col-6">
                    <label class="form-label">Porcentaje (%)</label>
                    <input type="number" name="porcentaje" class="form-control" step="0.01" required>
                </div>
                <div class="col-6">
                    <label class="form-label">Vigencia desde</label>
                    <input type="date" name="vigencia_desde" value="{{ now()->startOfMonth()->format('Y-m-d') }}" class="form-control" required>
                </div>
                <div class="col-12 small text-muted">
                    Se aplica sobre el básico actual de cada categoría y se redondea a 2 decimales.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-success">Aplicar</button>
            </div>
        </form>
    </div>
</div>
@endsection
