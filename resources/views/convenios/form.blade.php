@extends('layouts.app')

@section('title', $convenio->exists ? 'Editar convenio' : 'Nuevo convenio')

@section('content')
<form method="POST" action="{{ $convenio->exists ? route('convenios.update', $convenio) : route('convenios.store') }}">
    @csrf
    @if($convenio->exists) @method('PUT') @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body row g-3">
            <div class="col-md-6">
                <label class="form-label">Nombre *</label>
                <input type="text" name="nombre" value="{{ old('nombre', $convenio->nombre) }}"
                       class="form-control @error('nombre') is-invalid @enderror" placeholder="Ej: Empleados de Comercio" required autofocus>
            </div>
            <div class="col-md-3">
                <label class="form-label">N° de convenio</label>
                <input type="text" name="numero" value="{{ old('numero', $convenio->numero) }}" class="form-control" placeholder="Ej: CCT 130/75">
            </div>
            <div class="col-md-3">
                <label class="form-label">Horas semanales</label>
                <input type="number" name="horas_semanales" value="{{ old('horas_semanales', $convenio->horas_semanales) }}"
                       class="form-control @error('horas_semanales') is-invalid @enderror" step="0.5" min="1" max="80">
            </div>
            <div class="col-md-6">
                <label class="form-label">Sindicato</label>
                <input type="text" name="sindicato" value="{{ old('sindicato', $convenio->sindicato) }}" class="form-control">
            </div>
            <div class="col-12">
                <label class="form-label">Observaciones</label>
                <textarea name="observaciones" rows="3" class="form-control">{{ old('observaciones', $convenio->observaciones) }}</textarea>
            </div>
            <div class="col-12">
                <div class="form-check form-switch">
                    <input type="hidden" name="activo" value="0">
                    <input type="checkbox" name="activo" value="1" id="activo" class="form-check-input"
                           @checked(old('activo', $convenio->activo))>
                    <label for="activo" class="form-check-label">Convenio activo</label>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
        <a href="{{ route('convenios.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>
@endsection
