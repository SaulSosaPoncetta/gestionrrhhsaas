@extends('layouts.app')

@section('title', $puesto->exists ? 'Editar puesto' : 'Nuevo puesto')

@section('content')
<form method="POST" action="{{ $puesto->exists ? route('puestos.update', $puesto) : route('puestos.store') }}">
    @csrf
    @if($puesto->exists) @method('PUT') @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body row g-3">
            <div class="col-md-6">
                <label class="form-label">Nombre del puesto *</label>
                <input type="text" name="nombre" value="{{ old('nombre', $puesto->nombre) }}"
                       class="form-control @error('nombre') is-invalid @enderror" required autofocus>
            </div>
            <div class="col-md-6">
                <label class="form-label">Área</label>
                <select name="area_id" class="form-select @error('area_id') is-invalid @enderror">
                    <option value="">— Sin área —</option>
                    @foreach($areas as $a)
                        <option value="{{ $a->id }}" @selected((string) old('area_id', $puesto->area_id) === (string) $a->id)>
                            {{ str_repeat('— ', $a->nivel) }}{{ $a->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Descripción / tareas</label>
                <textarea name="descripcion" rows="4" class="form-control">{{ old('descripcion', $puesto->descripcion) }}</textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">Requisitos</label>
                <textarea name="requisitos" rows="4" class="form-control">{{ old('requisitos', $puesto->requisitos) }}</textarea>
            </div>
            <div class="col-12">
                <div class="form-check form-switch">
                    <input type="hidden" name="activo" value="0">
                    <input type="checkbox" name="activo" value="1" id="activo" class="form-check-input"
                           @checked(old('activo', $puesto->activo))>
                    <label for="activo" class="form-check-label">Puesto activo</label>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
        <a href="{{ route('puestos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>
@endsection
