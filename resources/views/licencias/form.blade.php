@extends('layouts.app')

@section('title', $licencia->exists ? 'Editar licencia' : 'Nueva licencia')

@section('content')
@php
    $l = $licencia;
@endphp

<form method="POST" enctype="multipart/form-data" action="{{ $l->exists ? route('licencias.update', $l) : route('licencias.store') }}">
    @csrf
    @if($l->exists) @method('PUT') @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    @if($l->exists)
        <div class="alert alert-light border small">
            Estado actual: <span class="badge text-bg-{{ $l->estado_color }}">{{ $l->estado_nombre }}</span>
            · Para aprobar, rechazar o cancelar usá los botones del listado.
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Empleado *</label>
                        <select name="empleado_id" id="empleado_id" class="form-select @error('empleado_id') is-invalid @enderror" required>
                            <option value="">— Seleccionar —</option>
                            @foreach($empleados as $emp)
                                <option value="{{ $emp->id }}" @selected((string) old('empleado_id', $l->empleado_id) === (string) $emp->id)>
                                    {{ $emp->nombre_completo }} (leg. {{ $emp->legajo }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Tipo de licencia *</label>
                        <select name="tipo_licencia_id" id="tipo_id" class="form-select @error('tipo_licencia_id') is-invalid @enderror" required>
                            <option value="">— Seleccionar —</option>
                            @foreach($tipos as $t)
                                <option value="{{ $t->id }}"
                                        data-vacaciones="{{ $t->es_vacaciones ? 1 : 0 }}"
                                        data-certificado="{{ $t->requiere_certificado ? 1 : 0 }}"
                                        data-descripcion="{{ $t->descripcion }}"
                                        @selected((string) old('tipo_licencia_id', $l->tipo_licencia_id) === (string) $t->id)>{{ $t->nombre }}</option>
                            @endforeach
                        </select>
                        <div class="form-text" id="tipo-descripcion"></div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Desde *</label>
                        <input type="date" name="fecha_desde" id="fecha_desde" value="{{ old('fecha_desde', $l->fecha_desde?->format('Y-m-d')) }}"
                               class="form-control @error('fecha_desde') is-invalid @enderror" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Hasta (inclusive) *</label>
                        <input type="date" name="fecha_hasta" id="fecha_hasta" value="{{ old('fecha_hasta', $l->fecha_hasta?->format('Y-m-d')) }}"
                               class="form-control @error('fecha_hasta') is-invalid @enderror" required>
                    </div>
                    <div class="col-md-4" id="bloque-periodo">
                        <label class="form-label">Vacaciones del año *</label>
                        <input type="number" name="periodo" id="periodo" value="{{ old('periodo', $l->periodo) }}"
                               class="form-control @error('periodo') is-invalid @enderror" min="2000" max="2100">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Observaciones</label>
                        <textarea name="observaciones" rows="2" class="form-control">{{ old('observaciones', $l->observaciones) }}</textarea>
                    </div>

                    <div class="col-md-8">
                        <label class="form-label">Certificado <span class="text-muted small">(PDF o imagen, máx. 5 MB)</span></label>
                        <input type="file" name="certificado" accept=".pdf,.jpg,.jpeg,.png,.webp" class="form-control @error('certificado') is-invalid @enderror">
                        @if($l->certificado)
                            <div class="small mt-1">
                                Actual: <a href="{{ route('licencias.certificado', $l) }}" target="_blank">{{ $l->certificado_nombre }}</a>
                                <span class="text-muted">· si subís otro se reemplaza</span>
                            </div>
                        @endif
                        <div class="form-text text-warning d-none" id="aviso-certificado">
                            <i class="bi bi-exclamation-triangle"></i> Este tipo de licencia requiere certificado.
                        </div>
                    </div>

                    @unless($l->exists)
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input type="checkbox" name="aprobar" value="1" id="aprobar" class="form-check-input" @checked(old('aprobar', true))>
                                <label for="aprobar" class="form-check-label">Aprobar al guardar</label>
                            </div>
                        </div>
                    @endunless
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm" id="panel-calculo">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-calculator me-1"></i>Cálculo</div>
                <div class="card-body">
                    <div class="text-muted small" id="calculo-vacio">Elegí empleado, tipo y fechas.</div>
                    <div class="d-none" id="calculo-datos">
                        <div class="display-6 fw-semibold" id="calculo-dias">—</div>
                        <div class="text-muted small mb-2" id="calculo-unidad"></div>
                        <div id="calculo-maximo" class="small"></div>
                        <div id="calculo-saldo" class="small"></div>
                        <ul id="calculo-avisos" class="small text-warning ps-3 mt-2 mb-0"></ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-3">
        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
        <a href="{{ route('licencias.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    const urlCalculo = @json(route('licencias.calcular-dias'));
    const excluir = @json($l->id);
    const $ = id => document.getElementById(id);

    const empleado = $('empleado_id'), tipo = $('tipo_id'), desde = $('fecha_desde'), hasta = $('fecha_hasta'), periodo = $('periodo');

    function tipoActual() {
        return tipo.selectedOptions[0] ?? null;
    }

    function actualizarTipo() {
        const t = tipoActual();
        const esVac = t?.dataset.vacaciones === '1';
        $('bloque-periodo').classList.toggle('d-none', !esVac);
        periodo.required = esVac;
        $('aviso-certificado').classList.toggle('d-none', t?.dataset.certificado !== '1');
        $('tipo-descripcion').textContent = t?.dataset.descripcion ?? '';
    }

    let temporizador;
    function calcular() {
        clearTimeout(temporizador);
        temporizador = setTimeout(async () => {
            if (!empleado.value || !tipo.value || !desde.value || !hasta.value) {
                $('calculo-vacio').classList.remove('d-none');
                $('calculo-datos').classList.add('d-none');
                return;
            }
            if (hasta.value < desde.value) hasta.value = desde.value;

            const params = new URLSearchParams({
                empleado_id: empleado.value, tipo_id: tipo.value, desde: desde.value, hasta: hasta.value,
                periodo: periodo.value || '', excluir: excluir || '',
            });
            try {
                const r = await fetch(urlCalculo + '?' + params, { headers: { 'Accept': 'application/json' } });
                if (!r.ok) return;
                const d = await r.json();

                $('calculo-vacio').classList.add('d-none');
                $('calculo-datos').classList.remove('d-none');
                $('calculo-dias').textContent = d.dias + (d.dias === 1 ? ' día' : ' días');
                $('calculo-unidad').textContent = d.unidad;

                const excede = d.maximo && d.dias > d.maximo;
                $('calculo-maximo').innerHTML = d.maximo
                    ? `<span class="${excede ? 'text-danger fw-semibold' : 'text-muted'}">Máximo del tipo: ${d.maximo} días</span>` : '';

                if (d.disponible !== undefined) {
                    const queda = d.disponible - d.dias;
                    $('calculo-saldo').innerHTML = `Saldo disponible: <strong>${d.disponible}</strong> días`
                        + `<br><span class="${queda < 0 ? 'text-danger fw-semibold' : 'text-success'}">Después de esta licencia: ${queda}</span>`;
                } else {
                    $('calculo-saldo').innerHTML = '';
                }

                $('calculo-avisos').innerHTML = (d.avisos || []).map(a => `<li>${a}</li>`).join('');
            } catch (e) { /* sin conexión: el servidor valida igual al guardar */ }
        }, 250);
    }

    tipo.addEventListener('change', () => { actualizarTipo(); calcular(); });
    [empleado, desde, hasta, periodo].forEach(el => el.addEventListener('change', calcular));
    actualizarTipo();
    calcular();
})();
</script>
@endpush
