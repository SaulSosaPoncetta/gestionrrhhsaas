@extends('layouts.app')

@section('title', 'Liquidaciones ' . $anio)

@section('content')
@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="btn-group">
        <a href="{{ route('liquidaciones.index', ['anio' => $anio - 1]) }}" class="btn btn-outline-secondary"><i class="bi bi-chevron-left"></i></a>
        <span class="btn btn-outline-secondary disabled fw-semibold">{{ $anio }}</span>
        <a href="{{ route('liquidaciones.index', ['anio' => $anio + 1]) }}" class="btn btn-outline-secondary"><i class="bi bi-chevron-right"></i></a>
    </div>
    @if($hayConceptos)
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNueva"><i class="bi bi-plus-lg me-1"></i>Nueva liquidación</button>
    @else
        <a href="{{ route('conceptos.index') }}" class="btn btn-warning"><i class="bi bi-exclamation-triangle me-1"></i>Primero cargá los conceptos</a>
    @endif
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Período</th>
                    <th class="d-none d-md-table-cell">Pago</th>
                    <th class="text-center">Recibos</th>
                    <th class="text-end d-none d-md-table-cell">Remunerativo</th>
                    <th class="text-end d-none d-lg-table-cell">Descuentos</th>
                    <th class="text-end">Neto</th>
                    <th class="text-center">Estado</th>
                </tr>
            </thead>
            <tbody>
            @forelse($liquidaciones as $l)
                <tr style="cursor:pointer" onclick="window.location='{{ route('liquidaciones.show', $l) }}'">
                    <td>
                        <a href="{{ route('liquidaciones.show', $l) }}" class="fw-semibold text-decoration-none text-dark">{{ $l->periodo_texto }}</a>
                        @if($l->descripcion)<div class="small text-muted">{{ $l->descripcion }}</div>@endif
                    </td>
                    <td class="d-none d-md-table-cell">{{ $l->fecha_pago?->format('d/m/Y') ?? '—' }}</td>
                    <td class="text-center">{{ $l->cantidad_recibos }}</td>
                    <td class="text-end d-none d-md-table-cell font-monospace">{{ number_format($l->total_remunerativo, 2, ',', '.') }}</td>
                    <td class="text-end d-none d-lg-table-cell font-monospace">{{ number_format($l->total_descuentos, 2, ',', '.') }}</td>
                    <td class="text-end font-monospace fw-semibold">{{ number_format($l->total_neto, 2, ',', '.') }}</td>
                    <td class="text-center">
                        <span class="badge {{ $l->esBorrador() ? 'text-bg-warning' : 'text-bg-success' }}">{{ $l->esBorrador() ? 'Borrador' : 'Cerrada' }}</span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No hay liquidaciones en {{ $anio }}.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Modal nueva liquidación --}}
<div class="modal fade" id="modalNueva" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="{{ route('liquidaciones.store') }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Nueva liquidación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <div class="col-md-4">
                    <label class="form-label">Tipo *</label>
                    <select name="tipo" id="tipoLiq" class="form-select">
                        @foreach(\App\Models\Liquidacion::TIPOS as $k => $v)
                            <option value="{{ $k }}" @selected(old('tipo') === $k)>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" id="etiquetaMes">Mes *</label>
                    <select name="mes" class="form-select" id="mesLiq">
                        @foreach(\App\Models\Liquidacion::MESES as $n => $nombre)
                            <option value="{{ $n }}" @selected((int) old('mes', $sugerido['mes']) === $n)>{{ $nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Año *</label>
                    <input type="number" name="anio" value="{{ old('anio', $sugerido['anio']) }}" class="form-control" min="2000" max="2100" required>
                </div>
                <div class="col-md-8">
                    <label class="form-label">Descripción</label>
                    <input type="text" name="descripcion" value="{{ old('descripcion') }}" class="form-control" maxlength="255" placeholder="Opcional">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Fecha de pago</label>
                    <input type="date" name="fecha_pago" value="{{ old('fecha_pago') }}" class="form-control">
                </div>
                <div class="col-12"><hr class="my-1"><div class="small text-muted">Último depósito de aportes (LCT art. 140 inc. h) — se puede completar después</div></div>
                <div class="col-md-4">
                    <label class="form-label">Período</label>
                    <input type="month" name="deposito_periodo" value="{{ old('deposito_periodo') }}" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Fecha</label>
                    <input type="date" name="deposito_fecha" value="{{ old('deposito_fecha') }}" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Banco</label>
                    <input type="text" name="deposito_banco" value="{{ old('deposito_banco') }}" class="form-control" maxlength="255">
                </div>
                <div class="col-12 small text-muted" id="ayudaSac">
                    El SAC toma la mejor remuneración mensual del semestre (de las liquidaciones mensuales ya hechas) y la proporciona por los días trabajados.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary"><i class="bi bi-calculator me-1"></i>Crear y calcular</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const tipo = document.getElementById('tipoLiq');
    const mes = document.getElementById('mesLiq');
    const etiqueta = document.getElementById('etiquetaMes');
    const ayuda = document.getElementById('ayudaSac');
    const opcionesMes = mes.innerHTML;

    function actualizar() {
        const sac = tipo.value === 'sac';
        ayuda.classList.toggle('d-none', !sac);
        etiqueta.textContent = sac ? 'Semestre *' : 'Mes *';
        if (sac) {
            const valor = parseInt(mes.value, 10) <= 6 ? 6 : 12;
            mes.innerHTML = '<option value="6">1er semestre (enero a junio)</option><option value="12">2do semestre (julio a diciembre)</option>';
            mes.value = valor;
        } else if (mes.options.length === 2) {
            mes.innerHTML = opcionesMes;
        }
    }
    tipo.addEventListener('change', actualizar);
    actualizar();

    @if($errors->any())
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalNueva')).show();
    @endif
})();
</script>
@endpush
