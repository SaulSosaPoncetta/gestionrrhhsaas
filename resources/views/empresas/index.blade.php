@extends('layouts.app')

@section('title', 'Empresas')

@section('content')
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <form method="GET" class="d-flex gap-2">
        <input type="text" name="buscar" value="{{ $buscar }}" class="form-control" placeholder="Razón social o CUIT">
        <button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
        @if($buscar)
            <a href="{{ route('empresas.index') }}" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
        @endif
    </form>
    <a href="{{ route('empresas.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Nueva empresa
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Empresa</th>
                    <th>CUIT</th>
                    <th class="d-none d-md-table-cell">Condición IVA</th>
                    <th class="text-center">Usuarios</th>
                    <th class="text-center">Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse($empresas as $empresa)
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            @if($empresa->logo)
                                <img src="{{ asset('storage/'.$empresa->logo) }}" alt="" width="32" height="32" class="rounded object-fit-cover">
                            @else
                                <span class="d-inline-flex align-items-center justify-content-center rounded bg-light text-secondary" style="width:32px;height:32px">
                                    <i class="bi bi-building"></i>
                                </span>
                            @endif
                            <div>
                                <div class="fw-semibold">{{ $empresa->razon_social }}</div>
                                @if($empresa->nombre_fantasia)
                                    <div class="small text-muted">{{ $empresa->nombre_fantasia }}</div>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td>{{ $empresa->cuit }}</td>
                    <td class="d-none d-md-table-cell">{{ $empresa->condicion_iva ?? '—' }}</td>
                    <td class="text-center">{{ $empresa->usuarios_count }}</td>
                    <td class="text-center">
                        <form method="POST" action="{{ route('empresas.toggle', $empresa) }}">
                            @csrf @method('PATCH')
                            <button class="badge border-0 {{ $empresa->activa ? 'text-bg-success' : 'text-bg-secondary' }}" title="Cambiar estado">
                                {{ $empresa->activa ? 'Activa' : 'Inactiva' }}
                            </button>
                        </form>
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('empresas.edit', $empresa) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form method="POST" action="{{ route('empresas.destroy', $empresa) }}" class="d-inline"
                              onsubmit="return confirm('¿Eliminar la empresa {{ addslashes($empresa->razon_social) }}?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No hay empresas cargadas.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $empresas->links() }}</div>
@endsection
