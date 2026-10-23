@extends('layouts.app')

@section('title', $usuario->exists ? 'Editar usuario' : 'Nuevo usuario')

@section('content')
@php($yo = auth()->user())
@php($esYo = $usuario->exists && $usuario->id === $yo->id)

<form method="POST" action="{{ $usuario->exists ? route('usuarios.update', $usuario) : route('usuarios.store') }}">
    @csrf
    @if($usuario->exists) @method('PUT') @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold">Datos del usuario</div>
        <div class="card-body row g-3">
            <div class="col-md-6">
                <label class="form-label">Nombre y apellido *</label>
                <input type="text" name="name" value="{{ old('name', $usuario->name) }}"
                       class="form-control @error('name') is-invalid @enderror" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Email *</label>
                <input type="email" name="email" value="{{ old('email', $usuario->email) }}"
                       class="form-control @error('email') is-invalid @enderror" required>
            </div>

            <div class="col-md-6">
                <label class="form-label">Rol *</label>
                <select name="rol" id="rol" class="form-select @error('rol') is-invalid @enderror" @disabled($esYo)>
                    @foreach($roles as $clave => $nombre)
                        <option value="{{ $clave }}" @selected(old('rol', $usuario->rol) === $clave)>{{ $nombre }}</option>
                    @endforeach
                </select>
                @if($esYo)
                    <input type="hidden" name="rol" value="{{ $usuario->rol }}">
                    <div class="form-text">No podés cambiar tu propio rol.</div>
                @endif
            </div>

            @if($yo->esSuperadmin())
                <div class="col-md-6" id="bloque-empresa">
                    <label class="form-label">Empresa *</label>
                    <select name="empresa_id" class="form-select @error('empresa_id') is-invalid @enderror">
                        <option value="">— Seleccionar —</option>
                        @foreach($empresas as $e)
                            <option value="{{ $e->id }}" @selected((string) old('empresa_id', $usuario->empresa_id) === (string) $e->id)>
                                {{ $e->razon_social }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold">Acceso</div>
        <div class="card-body row g-3">
            <div class="col-md-6">
                <label class="form-label">Contraseña {{ $usuario->exists ? '' : '*' }}</label>
                <input type="password" name="password" class="form-control @error('password') is-invalid @enderror"
                       autocomplete="new-password" @required(! $usuario->exists)>
                @if($usuario->exists)
                    <div class="form-text">Dejala vacía para no cambiarla.</div>
                @endif
            </div>
            <div class="col-md-6">
                <label class="form-label">Repetir contraseña</label>
                <input type="password" name="password_confirmation" class="form-control" autocomplete="new-password">
            </div>
            <div class="col-12">
                <div class="form-check form-switch">
                    <input type="hidden" name="activo" value="0">
                    <input type="checkbox" name="activo" value="1" id="activo" class="form-check-input"
                           @checked(old('activo', $usuario->activo)) @disabled($esYo)>
                    <label for="activo" class="form-check-label">Usuario activo</label>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
        <a href="{{ route('usuarios.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>
@endsection

@if($yo->esSuperadmin())
@push('scripts')
<script>
// El superadmin no pertenece a ninguna empresa: se oculta el selector
(function () {
    const rol = document.getElementById('rol');
    const bloque = document.getElementById('bloque-empresa');
    const actualizar = () => bloque.classList.toggle('d-none', rol.value === 'superadmin');
    rol.addEventListener('change', actualizar);
    actualizar();
})();
</script>
@endpush
@endif
