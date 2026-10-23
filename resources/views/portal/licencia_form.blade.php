@extends('layouts.app')

@section('title', 'Pedir licencia')

@section('content')
<a href="{{ route('portal.licencias') }}" class="text-decoration-none small"><i class="bi bi-arrow-left"></i> Mis licencias</a>

<form method="POST" action="{{ route('portal.licencias.store') }}" enctype="multipart/form-data" class="mt-2">
    @csrf

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body row g-3">
                    <div class="col-12">
                        <label class="form-label">Tipo de licencia *</label>
                        <select name="tipo_licencia_id" id="tipo_id" class="form-select @error('tipo_licencia_id') is-invalid @enderror" required>
                            <option value="">— Elegí —</option>
                            @foreach($tipos as $t)
                                <option value="{{ $t->id }}" data-vacaciones="{{ $t->es_vacaciones ? 1 : 0 }}" data-certificado="{{ $t->requiere_certificado ? 1 : 0 }}"
                                        data-descripcion="{{ $t->descripcion }}"
                                        @selected((string) old('tipo_licencia_id', $tipoId) === (string) $t->id)>{{ $t->nombre }}</option>
                            @endforeach
                        </select>
                        <div class="form-text" id="tipo-descripcion"></div>
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label">Desde *</label>
                        <input type="date" name="fecha_desde" id="fecha_desde" value="{{ old('fecha_desde', today()->format('Y-m-d')) }}"
                               class="form-control @error('fecha_desde') is-invalid @enderror" required>
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label">Hasta (inclusive) *</label>
                        <input type="date" name="fecha_hasta" id="fecha_hasta" value="{{ old('fecha_hasta', today()->format('Y-m-d')) }}"
                               class="form-control @error('fecha_hasta') is-invalid @enderror" required>
                    </div>
                    <div class="col-md-4" id="bloque-periodo">
                        <label class="form-label">Vacaciones del año</label>
                        <input type="number" name="periodo" id="periodo" value="{{ old('periodo', $periodo) }}" class="form-control" min="2000" max="2100">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Comentario para RRHH</label>
                        <textarea name="observaciones" rows="2" class="form-control" maxlength="2000">{{ old('observaciones') }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Certificado <span class="text-muted small">(PDF o foto, máx. 5 MB)</span></label>
                        <input type="file" name="certificado" accept=".pdf,.jpg,.jpeg,.png,.webp" class="form-control @error('certificado') is-invalid @enderror">
                        <div class="form-text text-warning d-none" id="aviso-certificado">Este tipo de licencia requiere certificado (lo podés adjuntar después).</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small" id="calculo-vacio">Elegí el tipo y las fechas.</div>
                    <div class="d-none" id="calculo-datos">
                        <div class="display-6 fw-semibold" id="calculo-dias"></div>
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
        <button class="btn btn-primary"><i class="bi bi-send me-1"></i>Enviar solicitud</button>
        <a href="{{ route('portal.licencias') }}" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    const url = @json(route('portal.licencias.calcular-dias'));
    const $ = id => document.getElementById(id);
    const tipo = $('tipo_id'), desde = $('fecha_desde'), hasta = $('fecha_hasta'), periodo = $('periodo');

    function actualizarTipo() {
        const t = tipo.selectedOptions[0];
        $('bloque-periodo').classList.toggle('d-none', t?.dataset.vacaciones !== '1');
        $('aviso-certificado').classList.toggle('d-none', t?.dataset.certificado !== '1');
        $('tipo-descripcion').textContent = t?.dataset.descripcion ?? '';
    }

    let tmr;
    function calcular() {
        clearTimeout(tmr);
        tmr = setTimeout(async () => {
            if (!tipo.value || !desde.value || !hasta.value) return;
            if (hasta.value < desde.value) hasta.value = desde.value;
            const p = new URLSearchParams({ tipo_id: tipo.value, desde: desde.value, hasta: hasta.value, periodo: periodo.value || '' });
            try {
                const r = await fetch(url + '?' + p, { headers: { Accept: 'application/json' } });
                if (!r.ok) return;
                const d = await r.json();
                $('calculo-vacio').classList.add('d-none');
                $('calculo-datos').classList.remove('d-none');
                $('calculo-dias').textContent = d.dias + (d.dias === 1 ? ' día' : ' días');
                $('calculo-unidad').textContent = d.unidad;
                $('calculo-maximo').innerHTML = d.maximo ? `<span class="${d.dias > d.maximo ? 'text-danger fw-semibold' : 'text-muted'}">Máximo: ${d.maximo} días</span>` : '';
                $('calculo-saldo').innerHTML = d.disponible !== undefined
                    ? `Te quedan <strong>${d.disponible}</strong> días · después: <span class="${d.disponible - d.dias < 0 ? 'text-danger fw-semibold' : 'text-success'}">${d.disponible - d.dias}</span>` : '';
                $('calculo-avisos').innerHTML = (d.avisos || []).map(a => `<li>${a}</li>`).join('');
            } catch (e) {}
        }, 250);
    }

    tipo.addEventListener('change', () => { actualizarTipo(); calcular(); });
    [desde, hasta, periodo].forEach(el => el.addEventListener('change', calcular));
    actualizarTipo();
    calcular();
})();
</script>
@endpush
