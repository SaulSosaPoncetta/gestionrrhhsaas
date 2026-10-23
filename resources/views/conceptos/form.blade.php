@extends('layouts.app')

@section('title', $concepto->exists ? 'Editar concepto' : 'Nuevo concepto')

@section('content')
@php
    $c = $concepto;
@endphp

<form method="POST" action="{{ $c->exists ? route('conceptos.update', $c) : route('conceptos.store') }}">
    @csrf
    @if($c->exists) @method('PUT') @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body row g-3">
            <div class="col-md-2">
                <label class="form-label">Código *</label>
                <input type="number" name="codigo" value="{{ old('codigo', $c->codigo) }}" class="form-control @error('codigo') is-invalid @enderror" min="1" max="9999" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Nombre *</label>
                <input type="text" name="nombre" value="{{ old('nombre', $c->nombre) }}" class="form-control @error('nombre') is-invalid @enderror" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Tipo *</label>
                <select name="tipo" class="form-select @error('tipo') is-invalid @enderror">
                    @foreach(\App\Models\Concepto::TIPOS as $k => $v)
                        <option value="{{ $k }}" @selected(old('tipo', $c->tipo) === $k)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-8">
                <label class="form-label">Forma de cálculo *</label>
                <select name="forma" id="forma" class="form-select @error('forma') is-invalid @enderror">
                    @foreach(\App\Models\Concepto::FORMAS as $k => [$nombre, $etiquetaValor])
                        <option value="{{ $k }}" data-valor="{{ $etiquetaValor }}" @selected(old('forma', $c->forma) === $k)>{{ $nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4" id="bloque-valor">
                <label class="form-label" id="etiqueta-valor">Valor</label>
                <input type="number" name="valor" value="{{ old('valor', $c->valor) }}" class="form-control @error('valor') is-invalid @enderror" step="0.0001" min="0">
            </div>

            <div class="col-md-4" id="bloque-base">
                <label class="form-label">Base de cálculo</label>
                <select name="base" class="form-select @error('base') is-invalid @enderror">
                    <option value="">—</option>
                    @foreach(\App\Models\Concepto::BASES as $k => $v)
                        <option value="{{ $k }}" @selected(old('base', $c->base) === $k)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4" id="bloque-tope">
                <label class="form-label">Tope de base imponible</label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" name="tope" value="{{ old('tope', $c->tope) }}" class="form-control" step="0.01" min="0">
                </div>
                <div class="form-text">Vacío = sin tope.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Orden de cálculo *</label>
                <input type="number" name="orden" value="{{ old('orden', $c->orden) }}" class="form-control" min="0" max="9999" required>
            </div>

            <div class="col-md-6" id="bloque-presentismo">
                <label class="form-label">Se pierde el presentismo con más de</label>
                <div class="input-group">
                    <input type="number" name="max_ausencias" value="{{ old('max_ausencias', $c->parametro('max_ausencias', 0)) }}" class="form-control" min="0" max="31">
                    <span class="input-group-text">ausencias o</span>
                    <input type="number" name="max_tardanzas" value="{{ old('max_tardanzas', $c->parametro('max_tardanzas', 2)) }}" class="form-control" min="0" max="31">
                    <span class="input-group-text">llegadas tarde</span>
                </div>
            </div>

            <div class="col-md-6">
                <label class="form-label">Solo para el convenio</label>
                <select name="convenio_id" class="form-select">
                    <option value="">Todos</option>
                    @foreach($convenios as $conv)
                        <option value="{{ $conv->id }}" @selected((string) old('convenio_id', $c->convenio_id) === (string) $conv->id)>{{ $conv->nombre_completo }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-4">
                <div class="form-check form-switch">
                    <input type="hidden" name="solo_con_convenio" value="0">
                    <input type="checkbox" name="solo_con_convenio" value="1" id="solo_con_convenio" class="form-check-input" @checked(old('solo_con_convenio', $c->parametro('solo_con_convenio')))>
                    <label for="solo_con_convenio" class="form-check-label">Solo empleados en convenio</label>
                </div>
            </div>
            <div class="col-md-4" id="bloque-automatico">
                <div class="form-check form-switch">
                    <input type="hidden" name="automatico" value="0">
                    <input type="checkbox" name="automatico" value="1" id="automatico" class="form-check-input" @checked(old('automatico', $c->automatico))>
                    <label for="automatico" class="form-check-label">Se aplica automáticamente</label>
                </div>
                <div class="form-text">Si no, solo cuando se carga como novedad.</div>
            </div>
            <div class="col-md-4">
                <div class="form-check form-switch">
                    <input type="hidden" name="activo" value="0">
                    <input type="checkbox" name="activo" value="1" id="activo" class="form-check-input" @checked(old('activo', $c->activo))>
                    <label for="activo" class="form-check-label">Activo</label>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
        <a href="{{ route('conceptos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    const conValor = ['antiguedad', 'porcentaje', 'fijo', 'presentismo', 'horas_extra_50', 'horas_extra_100', 'aporte'];
    const conBase  = ['antiguedad', 'porcentaje', 'presentismo', 'ausencias', 'sin_goce', 'plus_vacacional', 'horas_extra_50', 'horas_extra_100', 'aporte'];
    const forma = document.getElementById('forma');

    function actualizar() {
        const f = forma.value;
        document.getElementById('bloque-valor').classList.toggle('d-none', !conValor.includes(f));
        document.getElementById('etiqueta-valor').textContent = forma.selectedOptions[0].dataset.valor || 'Valor';
        document.getElementById('bloque-base').classList.toggle('d-none', !conBase.includes(f));
        document.getElementById('bloque-tope').classList.toggle('d-none', f !== 'aporte');
        document.getElementById('bloque-presentismo').classList.toggle('d-none', f !== 'presentismo');
        document.getElementById('bloque-automatico').classList.toggle('d-none', f === 'manual' || f === 'sac');
    }
    forma.addEventListener('change', actualizar);
    actualizar();
})();
</script>
@endpush
