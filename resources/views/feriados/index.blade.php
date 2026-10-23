@extends('layouts.app')

@section('title', 'Feriados ' . $anio)

@section('content')
@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="btn-group">
        <a href="{{ route('feriados.index', ['anio' => $anio - 1]) }}" class="btn btn-outline-secondary"><i class="bi bi-chevron-left"></i></a>
        <span class="btn btn-outline-secondary disabled fw-semibold">{{ $anio }}</span>
        <a href="{{ route('feriados.index', ['anio' => $anio + 1]) }}" class="btn btn-outline-secondary"><i class="bi bi-chevron-right"></i></a>
    </div>
    <form method="POST" action="{{ route('feriados.cargar-fijos') }}">
        @csrf
        <input type="hidden" name="anio" value="{{ $anio }}">
        <button class="btn btn-outline-primary"><i class="bi bi-magic me-1"></i>Cargar feriados fijos de {{ $anio }}</button>
    </form>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Agregar día</div>
            <div class="card-body">
                <form method="POST" action="{{ route('feriados.store') }}" class="row g-3">
                    @csrf
                    <div class="col-12">
                        <label class="form-label">Fecha *</label>
                        <input type="date" name="fecha" value="{{ old('fecha') }}" class="form-control @error('fecha') is-invalid @enderror" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Descripción *</label>
                        <input type="text" name="descripcion" value="{{ old('descripcion') }}" class="form-control @error('descripcion') is-invalid @enderror"
                               placeholder="Ej: Paso a la Inmortalidad del Gral. San Martín" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Tipo</label>
                        <select name="tipo" class="form-select">
                            @foreach(\App\Models\Feriado::TIPOS as $k => $v)
                                <option value="{{ $k }}" @selected(old('tipo', 'trasladable') === $k)>{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary w-100"><i class="bi bi-plus-lg me-1"></i>Agregar</button>
                    </div>
                </form>
            </div>
            <div class="card-footer bg-white small text-muted">
                <p class="mb-1">El botón de feriados fijos carga los inamovibles, incluidos Carnaval y Viernes Santo.</p>
                <p class="mb-0">Los trasladables (17/6, 17/8, 12/10 y 20/11) y los días puente cambian por decreto cada año: cargalos con la fecha que corresponda.
                Un <strong>día no laborable</strong> no genera ausencia, pero si se trabaja cuenta como un día normal.</p>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Fecha</th><th>Descripción</th><th>Tipo</th><th class="text-end"></th></tr>
                    </thead>
                    <tbody>
                    @forelse($feriados as $f)
                        <tr>
                            <td class="text-nowrap">
                                <span class="fw-semibold">{{ $f->fecha->format('d/m') }}</span>
                                <span class="small text-muted">{{ \App\Models\Horario::DIAS_CORTOS[$f->fecha->dayOfWeekIso] }}</span>
                            </td>
                            <td>{{ $f->descripcion }}</td>
                            <td><span class="badge text-bg-light">{{ $f->tipo_nombre }}</span></td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('feriados.destroy', $f) }}"
                                      onsubmit="return confirm('¿Eliminar {{ addslashes($f->descripcion) }}?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">No hay feriados cargados para {{ $anio }}.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
