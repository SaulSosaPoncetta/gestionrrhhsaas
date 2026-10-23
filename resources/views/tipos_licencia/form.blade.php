@extends('layouts.app')

@section('title', $tipo->exists ? 'Editar tipo de licencia' : 'Nuevo tipo de licencia')

@section('content')
<form method="POST" action="{{ $tipo->exists ? route('tipos-licencia.update', $tipo) : route('tipos-licencia.store') }}">
    @csrf
    @if($tipo->exists) @method('PUT') @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body row g-3">
            <div class="col-md-7">
                <label class="form-label">Nombre *</label>
                <input type="text" name="nombre" value="{{ old('nombre', $tipo->nombre) }}"
                       class="form-control @error('nombre') is-invalid @enderror" required autofocus>
            </div>
            <div class="col-md-2">
                <label class="form-label">Código</label>
                <input type="text" name="codigo" value="{{ old('codigo', $tipo->codigo) }}" class="form-control" maxlength="20">
            </div>
            <div class="col-md-3">
                <label class="form-label">Color</label>
                <select name="color" id="color" class="form-select">
                    @foreach(\App\Models\TipoLicencia::COLORES as $k => $v)
                        <option value="{{ $k }}" @selected(old('color', $tipo->color) === $k)>{{ $v }}</option>
                    @endforeach
                </select>
                <span class="badge mt-2 text-bg-{{ old('color', $tipo->color) }}" id="muestra">Muestra</span>
            </div>

            <div class="col-md-4">
                <label class="form-label">Se cuentan</label>
                <select name="unidad" class="form-select">
                    @foreach(\App\Models\TipoLicencia::UNIDADES as $k => $v)
                        <option value="{{ $k }}" @selected(old('unidad', $tipo->unidad) === $k)>{{ $v }}</option>
                    @endforeach
                </select>
                <div class="form-text">Hábiles: según el horario del empleado, sin feriados.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Máximo por licencia</label>
                <div class="input-group">
                    <input type="number" name="dias_maximos" value="{{ old('dias_maximos', $tipo->dias_maximos) }}"
                           class="form-control @error('dias_maximos') is-invalid @enderror" min="1" max="365">
                    <span class="input-group-text">días</span>
                </div>
                <div class="form-text">Vacío = sin máximo.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Máximo por año calendario</label>
                <div class="input-group">
                    <input type="number" name="maximo_anual" value="{{ old('maximo_anual', $tipo->maximo_anual) }}"
                           class="form-control @error('maximo_anual') is-invalid @enderror" min="1" max="365">
                    <span class="input-group-text">días</span>
                </div>
            </div>

            <div class="col-12">
                <label class="form-label">Descripción / base legal</label>
                <input type="text" name="descripcion" value="{{ old('descripcion', $tipo->descripcion) }}" class="form-control" maxlength="255"
                       placeholder="Ej: CCT 130/75 art. 31: 3 días corridos">
            </div>

            <div class="col-md-3">
                <div class="form-check form-switch">
                    <input type="hidden" name="con_goce" value="0">
                    <input type="checkbox" name="con_goce" value="1" id="con_goce" class="form-check-input" @checked(old('con_goce', $tipo->con_goce))>
                    <label for="con_goce" class="form-check-label">Con goce de haberes</label>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-check form-switch">
                    <input type="hidden" name="requiere_certificado" value="0">
                    <input type="checkbox" name="requiere_certificado" value="1" id="requiere_certificado" class="form-check-input" @checked(old('requiere_certificado', $tipo->requiere_certificado))>
                    <label for="requiere_certificado" class="form-check-label">Requiere certificado</label>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-check form-switch">
                    <input type="hidden" name="es_vacaciones" value="0">
                    <input type="checkbox" name="es_vacaciones" value="1" id="es_vacaciones" class="form-check-input @error('es_vacaciones') is-invalid @enderror" @checked(old('es_vacaciones', $tipo->es_vacaciones))>
                    <label for="es_vacaciones" class="form-check-label">Son las vacaciones</label>
                </div>
                <div class="form-text">Descuenta del saldo anual.</div>
            </div>
            <div class="col-md-3">
                <div class="form-check form-switch">
                    <input type="hidden" name="activo" value="0">
                    <input type="checkbox" name="activo" value="1" id="activo" class="form-check-input" @checked(old('activo', $tipo->activo))>
                    <label for="activo" class="form-check-label">Activo</label>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
        <a href="{{ route('tipos-licencia.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>
@endsection

@push('scripts')
<script>
document.getElementById('color').addEventListener('change', function () {
    document.getElementById('muestra').className = 'badge mt-2 text-bg-' + this.value;
});
</script>
@endpush
