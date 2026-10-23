@extends('layouts.app')

@section('title', $horario->exists ? 'Editar horario' : 'Nuevo horario')

@section('content')
<form method="POST" action="{{ $horario->exists ? route('horarios.update', $horario) : route('horarios.store') }}">
    @csrf
    @if($horario->exists) @method('PUT') @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body row g-3">
            <div class="col-md-6">
                <label class="form-label">Nombre *</label>
                <input type="text" name="nombre" value="{{ old('nombre', $horario->nombre) }}"
                       class="form-control @error('nombre') is-invalid @enderror" placeholder="Ej: Comercio lunes a sábado" required autofocus>
            </div>
            <div class="col-md-3">
                <label class="form-label">Tolerancia de entrada</label>
                <div class="input-group">
                    <input type="number" name="tolerancia_minutos" value="{{ old('tolerancia_minutos', $horario->tolerancia_minutos) }}"
                           class="form-control @error('tolerancia_minutos') is-invalid @enderror" min="0" max="120" required>
                    <span class="input-group-text">min</span>
                </div>
                <div class="form-text">Pasado este margen se computa llegada tarde.</div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Mínimo para horas extra</label>
                <div class="input-group">
                    <input type="number" name="minimo_extra_minutos" value="{{ old('minimo_extra_minutos', $horario->minimo_extra_minutos) }}"
                           class="form-control @error('minimo_extra_minutos') is-invalid @enderror" min="0" max="240" required>
                    <span class="input-group-text">min</span>
                </div>
                <div class="form-text">Menos que esto no se cuenta como extra.</div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-semibold">Días y horarios</span>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="copiarLunes">
                <i class="bi bi-copy me-1"></i>Copiar el lunes a martes–viernes
            </button>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr><th style="width:170px">Día</th><th>Entrada</th><th>Salida</th></tr>
                </thead>
                <tbody>
                @foreach(\App\Models\Horario::DIAS as $n => $nombre)
                    @php($dia = $horario->dias[$n] ?? null)
                    @php($trabaja = old("dias.$n.trabaja", $dia ? '1' : null))
                    <tr class="fila-dia" data-dia="{{ $n }}">
                        <td>
                            <div class="form-check form-switch mb-0">
                                <input type="checkbox" name="dias[{{ $n }}][trabaja]" value="1" id="trabaja{{ $n }}"
                                       class="form-check-input chk-trabaja" @checked($trabaja)>
                                <label for="trabaja{{ $n }}" class="form-check-label">{{ $nombre }}</label>
                            </div>
                        </td>
                        <td style="max-width:160px">
                            <input type="time" name="dias[{{ $n }}][entrada]" value="{{ old("dias.$n.entrada", $dia['entrada'] ?? '') }}"
                                   class="form-control hora-entrada @error("dias.$n") is-invalid @enderror">
                        </td>
                        <td style="max-width:160px">
                            <input type="time" name="dias[{{ $n }}][salida]" value="{{ old("dias.$n.salida", $dia['salida'] ?? '') }}"
                                   class="form-control hora-salida @error("dias.$n") is-invalid @enderror">
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white small text-muted">
            Si la salida es anterior a la entrada (ej. 22:00 a 06:00) se toma como horario nocturno que termina al día siguiente.
            Los días sin marcar son franco.
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="form-check form-switch">
                <input type="hidden" name="activo" value="0">
                <input type="checkbox" name="activo" value="1" id="activo" class="form-check-input"
                       @checked(old('activo', $horario->activo))>
                <label for="activo" class="form-check-label">Horario activo</label>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
        <a href="{{ route('horarios.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    const filas = [...document.querySelectorAll('.fila-dia')];

    function actualizar(fila) {
        const activo = fila.querySelector('.chk-trabaja').checked;
        fila.querySelectorAll('input[type=time]').forEach(i => i.disabled = !activo);
        fila.classList.toggle('text-muted', !activo);
    }

    filas.forEach(fila => {
        fila.querySelector('.chk-trabaja').addEventListener('change', () => actualizar(fila));
        actualizar(fila);
    });

    document.getElementById('copiarLunes').addEventListener('click', () => {
        const lunes = filas[0];
        const entrada = lunes.querySelector('.hora-entrada').value;
        const salida = lunes.querySelector('.hora-salida').value;
        if (!entrada || !salida) {
            alert('Primero completá la entrada y la salida del lunes.');
            return;
        }
        filas.slice(1, 5).forEach(fila => {
            fila.querySelector('.chk-trabaja').checked = true;
            fila.querySelector('.hora-entrada').value = entrada;
            fila.querySelector('.hora-salida').value = salida;
            actualizar(fila);
        });
    });
})();
</script>
@endpush
