@extends('layouts.app')

@section('title', $empresa->exists ? 'Editar empresa' : 'Nueva empresa')

@section('content')
<form method="POST" enctype="multipart/form-data"
      action="{{ $empresa->exists ? route('empresas.update', $empresa) : route('empresas.store') }}">
    @csrf
    @if($empresa->exists) @method('PUT') @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold">Datos fiscales</div>
        <div class="card-body row g-3">
            <div class="col-md-6">
                <label class="form-label">Razón social *</label>
                <input type="text" name="razon_social" value="{{ old('razon_social', $empresa->razon_social) }}"
                       class="form-control @error('razon_social') is-invalid @enderror" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Nombre de fantasía</label>
                <input type="text" name="nombre_fantasia" value="{{ old('nombre_fantasia', $empresa->nombre_fantasia) }}" class="form-control">
            </div>
            <div class="col-md-6">
                <label class="form-label">CUIT *</label>
                <input type="text" name="cuit" id="cuit" value="{{ old('cuit', $empresa->cuit) }}"
                       class="form-control @error('cuit') is-invalid @enderror" placeholder="30-12345678-9" maxlength="13" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Condición frente al IVA</label>
                <select name="condicion_iva" class="form-select">
                    <option value="">— Seleccionar —</option>
                    @foreach($condiciones as $c)
                        <option value="{{ $c }}" @selected(old('condicion_iva', $empresa->condicion_iva) === $c)>{{ $c }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold">Contacto y domicilio</div>
        <div class="card-body row g-3">
            <div class="col-md-6">
                <label class="form-label">Domicilio</label>
                <input type="text" name="domicilio" value="{{ old('domicilio', $empresa->domicilio) }}" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">Localidad</label>
                <input type="text" name="localidad" value="{{ old('localidad', $empresa->localidad) }}" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">Provincia</label>
                <input type="text" name="provincia" value="{{ old('provincia', $empresa->provincia) }}" class="form-control">
            </div>
            <div class="col-md-6">
                <label class="form-label">Teléfono</label>
                <input type="text" name="telefono" value="{{ old('telefono', $empresa->telefono) }}" class="form-control">
            </div>
            <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="email" value="{{ old('email', $empresa->email) }}" class="form-control">
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold">Configuración</div>
        <div class="card-body row g-3 align-items-center">
            <div class="col-md-6">
                <label class="form-label">Logo (máx. 2 MB)</label>
                <input type="file" name="logo" accept="image/*" class="form-control">
                @if($empresa->logo)
                    <img src="{{ asset('storage/'.$empresa->logo) }}" alt="Logo actual" class="mt-2 rounded border" height="60">
                @endif
            </div>
            <div class="col-md-6">
                <div class="form-check form-switch">
                    <input type="hidden" name="activa" value="0">
                    <input type="checkbox" name="activa" value="1" id="activa" class="form-check-input"
                           @checked(old('activa', $empresa->activa))>
                    <label for="activa" class="form-check-label">Empresa activa</label>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
        <a href="{{ route('empresas.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>
@endsection

@push('scripts')
<script>
// Formatea el CUIT mientras se escribe: 30-12345678-9
document.getElementById('cuit').addEventListener('input', function () {
    let v = this.value.replace(/\D/g, '').slice(0, 11);
    if (v.length > 10) v = v.slice(0, 2) + '-' + v.slice(2, 10) + '-' + v.slice(10);
    else if (v.length > 2) v = v.slice(0, 2) + '-' + v.slice(2);
    this.value = v;
});
</script>
@endpush
