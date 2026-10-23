@extends('layouts.app')

@section('title', 'Usuarios')

@section('content')
@php($yo = auth()->user())

<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <form method="GET" class="d-flex flex-wrap gap-2">
        <input type="text" name="buscar" value="{{ $buscar }}" class="form-control" style="max-width:220px" placeholder="Nombre o email">
        <select name="rol" class="form-select" style="max-width:200px">
            <option value="">Todos los roles</option>
            @foreach($roles as $clave => $nombre)
                <option value="{{ $clave }}" @selected($rol === $clave)>{{ $nombre }}</option>
            @endforeach
        </select>
        @if($yo->esSuperadmin())
            <select name="empresa_id" class="form-select" style="max-width:220px">
                <option value="">Todas las empresas</option>
                @foreach($empresas as $e)
                    <option value="{{ $e->id }}" @selected((string) $empId === (string) $e->id)>{{ $e->razon_social }}</option>
                @endforeach
            </select>
        @endif
        <button class="btn btn-outline-secondary"><i class="bi bi-funnel"></i></button>
        @if($buscar || $rol || $empId)
            <a href="{{ route('usuarios.index') }}" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
        @endif
    </form>
    <a href="{{ route('usuarios.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Nuevo usuario
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Nombre</th>
                    <th>Email</th>
                    @if($yo->esSuperadmin())<th>Empresa</th>@endif
                    <th>Rol</th>
                    <th class="d-none d-md-table-cell">Último acceso</th>
                    <th class="text-center">Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse($usuarios as $usuario)
                <tr>
                    <td class="fw-semibold">
                        {{ $usuario->name }}
                        @if($usuario->id === $yo->id)<span class="badge text-bg-light ms-1">Vos</span>@endif
                    </td>
                    <td>{{ $usuario->email }}</td>
                    @if($yo->esSuperadmin())
                        <td>{{ $usuario->empresa?->razon_social ?? '—' }}</td>
                    @endif
                    <td><span class="badge text-bg-primary bg-opacity-75">{{ $usuario->rol_nombre }}</span></td>
                    <td class="d-none d-md-table-cell small text-muted">
                        {{ $usuario->ultimo_acceso?->format('d/m/Y H:i') ?? 'Nunca' }}
                    </td>
                    <td class="text-center">
                        <span class="badge {{ $usuario->activo ? 'text-bg-success' : 'text-bg-secondary' }}">
                            {{ $usuario->activo ? 'Activo' : 'Inactivo' }}
                        </span>
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('usuarios.edit', $usuario) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                            <i class="bi bi-pencil"></i>
                        </a>
                        @if($usuario->id !== $yo->id)
                            <form method="POST" action="{{ route('usuarios.destroy', $usuario) }}" class="d-inline"
                                  onsubmit="return confirm('¿Eliminar el usuario {{ addslashes($usuario->name) }}?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No hay usuarios para mostrar.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $usuarios->links() }}</div>
@endsection
