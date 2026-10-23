@extends('layouts.app')

@section('title', $area->exists ? 'Editar área' : 'Nueva área')

@section('content')
<form method="POST" action="{{ $area->exists ? route('areas.update', $area) : route('areas.store') }}">
    @csrf
    @if($area->exists) @method('PUT') @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body row g-3">
            <div class="col-md-8">
                <label class="form-label">Nombre *</label>
                <input type="text" name="nombre" value="{{ old('nombre', $area->nombre) }}"
                       class="form-control @error('nombre') is-invalid @enderror" required autofocus>
            </div>
            <div class="col-md-4">
                <label class="form-label">Código</label>
                <input type="text" name="codigo" value="{{ old('codigo', $area->codigo) }}" class="form-control" maxlength="20">
            </div>
            <div class="col-12">
                <label class="form-label">Depende de</label>
                <select name="area_padre_id" class="form-select @error('area_padre_id') is-invalid @enderror">
                    <option value="">— Ninguna (área principal) —</option>
                    @foreach($padres as $p)
                        <option value="{{ $p->id }}" @selected((string) old('area_padre_id', $area->area_padre_id) === (string) $p->id)>
                            {{ str_repeat('— ', $p->nivel) }}{{ $p->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-12">
                <label class="form-label">Descripción</label>
                <textarea name="descripcion" rows="3" class="form-control">{{ old('descripcion', $area->descripcion) }}</textarea>
            </div>
            <div class="col-12">
                <div class="form-check form-switch">
                    <input type="hidden" name="activa" value="0">
                    <input type="checkbox" name="activa" value="1" id="activa" class="form-check-input"
                           @checked(old('activa', $area->activa))>
                    <label for="activa" class="form-check-label">Área activa</label>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
        <a href="{{ route('areas.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>
@endsection
