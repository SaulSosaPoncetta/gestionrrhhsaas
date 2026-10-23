@extends('layouts.app')

@section('title', ($categoria->exists ? 'Editar categoría' : 'Nueva categoría') . ' · ' . $convenio->nombre)

@section('content')
<form method="POST"
      action="{{ $categoria->exists ? route('categorias.update', $categoria) : route('convenios.categorias.store', $convenio) }}">
    @csrf
    @if($categoria->exists) @method('PUT') @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white">
            <span class="text-muted small">Convenio:</span> <span class="fw-semibold">{{ $convenio->nombre_completo }}</span>
        </div>
        <div class="card-body row g-3">
            <div class="col-md-6">
                <label class="form-label">Nombre de la categoría *</label>
                <input type="text" name="nombre" value="{{ old('nombre', $categoria->nombre) }}"
                       class="form-control @error('nombre') is-invalid @enderror" placeholder="Ej: Administrativo A" required autofocus>
            </div>
            <div class="col-md-3">
                <label class="form-label">Orden</label>
                <input type="number" name="orden" value="{{ old('orden', $categoria->orden) }}" class="form-control" min="0">
            </div>
            <div class="col-md-3"></div>
            <div class="col-md-6">
                <label class="form-label">Sueldo básico *</label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" name="sueldo_basico" value="{{ old('sueldo_basico', $categoria->sueldo_basico) }}"
                           class="form-control @error('sueldo_basico') is-invalid @enderror" step="0.01" min="0" required>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Vigencia desde</label>
                <input type="date" name="vigencia_desde"
                       value="{{ old('vigencia_desde', $categoria->vigencia_desde?->format('Y-m-d')) }}" class="form-control">
            </div>
            <div class="col-12">
                <div class="form-check form-switch">
                    <input type="hidden" name="activa" value="0">
                    <input type="checkbox" name="activa" value="1" id="activa" class="form-check-input"
                           @checked(old('activa', $categoria->activa))>
                    <label for="activa" class="form-check-label">Categoría activa</label>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
        <a href="{{ route('convenios.categorias.index', $convenio) }}" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>
@endsection
