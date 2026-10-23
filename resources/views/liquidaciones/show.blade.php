@extends('layouts.app')

@section('title', 'Liquidación ' . $liquidacion->periodo_texto)

@section('content')
@php
    $l = $liquidacion;
    $m = fn ($v) => number_format((float) $v, 2, ',', '.');
@endphp

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<div class="d-flex flex-wrap gap-2 justify-content-between align-items-start mb-3">
    <div>
        <a href="{{ route('liquidaciones.index', ['anio' => $l->anio]) }}" class="text-decoration-none small"><i class="bi bi-arrow-left"></i> Liquidaciones</a>
        <h2 class="h5 mb-0 mt-1">
            {{ $l->periodo_texto }}
            <span class="badge {{ $l->esBorrador() ? 'text-bg-warning' : 'text-bg-success' }} align-middle">{{ $l->esBorrador() ? 'Borrador' : 'Cerrada' }}</span>
        </h2>
        <div class="small text-muted">
            @if($l->calculada_at) Calculada el {{ $l->calculada_at->format('d/m/Y H:i') }} @endif
            @if($l->cerrada_at) · cerrada el {{ $l->cerrada_at->format('d/m/Y H:i') }} por {{ $l->cerradaPor?->name }} @endif
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        @if($l->esBorrador())
            <form method="POST" action="{{ route('liquidaciones.calcular', $l) }}">
                @csrf
                <button class="btn btn-outline-primary"><i class="bi bi-arrow-repeat me-1"></i>Recalcular</button>
            </form>
            <form method="POST" action="{{ route('liquidaciones.cerrar', $l) }}"
                  onsubmit="return confirm('¿Cerrar la liquidación? Los recibos quedan fijos.')">
                @csrf @method('PATCH')
                <button class="btn btn-success"><i class="bi bi-lock me-1"></i>Cerrar</button>
            </form>
        @else
            <form method="POST" action="{{ route('liquidaciones.reabrir', $l) }}"
                  onsubmit="return confirm('¿Reabrir la liquidación para corregirla?')">
                @csrf @method('PATCH')
                <button class="btn btn-outline-warning"><i class="bi bi-unlock me-1"></i>Reabrir</button>
            </form>
        @endif
        @if($l->cantidad_recibos)
            <a href="{{ route('liquidaciones.imprimir', $l) }}" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-printer me-1"></i>Imprimir recibos</a>
            <a href="{{ route('liquidaciones.exportar', $l) }}" class="btn btn-outline-success"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Exportar</a>
        @endif
        <div class="dropdown">
            <button class="btn btn-outline-secondary" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#modalDatos"><i class="bi bi-pencil me-2"></i>Datos de pago y depósito</button></li>
                @if($l->esBorrador())
                    <li>
                        <form method="POST" action="{{ route('liquidaciones.destroy', $l) }}" onsubmit="return confirm('¿Eliminar la liquidación y sus recibos?')">
                            @csrf @method('DELETE')
                            <button class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Eliminar</button>
                        </form>
                    </li>
                @endif
            </ul>
        </div>
    </div>
</div>

{{-- Totales --}}
<div class="row g-2 mb-3">
    @foreach([['Recibos', $l->cantidad_recibos, false], ['Remunerativo', $l->total_remunerativo, true], ['No remunerativo', $l->total_no_remunerativo, true], ['Descuentos', $l->total_descuentos, true], ['Neto a pagar', $l->total_neto, true]] as [$t, $v, $dinero])
        <div class="col-6 col-md">
            <div class="card border-0 shadow-sm h-100"><div class="card-body py-2">
                <div class="small text-muted">{{ $t }}</div>
                <div class="fs-5 fw-semibold {{ $t === 'Neto a pagar' ? 'text-success' : '' }}">{{ $dinero ? '$ ' . $m($v) : $v }}</div>
            </div></div>
        </div>
    @endforeach
</div>

@unless($l->fecha_pago && $l->deposito_periodo)
    <div class="alert alert-light border small">
        <i class="bi bi-info-circle me-1"></i>Para el recibo faltan:
        {{ collect([! $l->fecha_pago ? 'la fecha de pago' : null, ! $l->deposito_periodo ? 'el último depósito de aportes (art. 140 inc. h)' : null])->filter()->implode(' y ') }}.
        <a href="#" data-bs-toggle="modal" data-bs-target="#modalDatos">Completar</a>
    </div>
@endunless

{{-- Recibos --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold">Recibos</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Empleado</th>
                    <th class="text-center d-none d-md-table-cell">Días</th>
                    <th class="text-end">Remunerativo</th>
                    <th class="text-end d-none d-md-table-cell">No rem.</th>
                    <th class="text-end d-none d-md-table-cell">Descuentos</th>
                    <th class="text-end">Neto</th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
            @forelse($l->recibos as $r)
                <tr>
                    <td>
                        <span class="fw-semibold">{{ $r->apellido_nombre }}</span>
                        <div class="small text-muted">Leg. {{ $r->legajo }} · {{ $r->categoria }}</div>
                        @if($r->observaciones)<div class="small text-warning">{{ $r->observaciones }}</div>@endif
                    </td>
                    <td class="text-center d-none d-md-table-cell">{{ $r->dias_trabajados }}</td>
                    <td class="text-end font-monospace">{{ $m($r->total_remunerativo) }}</td>
                    <td class="text-end font-monospace d-none d-md-table-cell">{{ $m($r->total_no_remunerativo) }}</td>
                    <td class="text-end font-monospace d-none d-md-table-cell">{{ $m($r->total_descuentos) }}</td>
                    <td class="text-end font-monospace fw-semibold">
                        {{ $m($r->neto) }}
                        @if($r->conformado_at)
                            <div class="small text-success fw-normal" title="IP {{ $r->conformado_ip }}"><i class="bi bi-check2-circle"></i> Conforme {{ $r->conformado_at->format('d/m') }}</div>
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('liquidaciones.recibo', [$l, $r]) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="Ver recibo"><i class="bi bi-receipt"></i></a>
                        @if($l->esBorrador())
                            <button class="btn btn-sm btn-outline-primary" title="Agregar novedad" onclick="abrirNovedad({ empleado_id: {{ $r->empleado_id }} })">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No hay recibos. ¿Hay empleados activos en el período?</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Novedades --}}
<div class="card border-0 shadow-sm" id="novedades">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Novedades del período</span>
        @if($l->esBorrador())
            <button class="btn btn-sm btn-primary" onclick="abrirNovedad()"><i class="bi bi-plus-lg me-1"></i>Agregar novedad</button>
        @endif
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr><th>Empleado</th><th>Concepto</th><th class="text-end">Cantidad</th><th class="text-end">Importe</th><th class="d-none d-md-table-cell">Observación</th><th></th></tr>
            </thead>
            <tbody>
            @forelse($novedades as $n)
                <tr>
                    <td>{{ $n->empleado?->nombre_completo }}</td>
                    <td><span class="font-monospace small text-muted">{{ $n->concepto?->codigo }}</span> {{ $n->concepto?->nombre }}</td>
                    <td class="text-end">{{ $n->cantidad !== null ? rtrim(rtrim(number_format($n->cantidad, 2, ',', '.'), '0'), ',') : '—' }}</td>
                    <td class="text-end font-monospace">{{ $n->importe !== null ? $m($n->importe) : '—' }}</td>
                    <td class="d-none d-md-table-cell small text-muted">{{ $n->observacion }}</td>
                    <td class="text-end">
                        @if($l->esBorrador())
                            <form method="POST" action="{{ route('liquidaciones.novedades.destroy', [$l, $n]) }}" onsubmit="return confirm('¿Eliminar la novedad?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">
                    Sin novedades. Usalas para anticipos, premios, embargos o para corregir horas y días que vienen de la asistencia.
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Modal novedad --}}
@if($l->esBorrador())
<div class="modal fade" id="modalNovedad" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('liquidaciones.novedades.store', $l) }}" id="formNovedad" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Novedad</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <div class="col-12">
                    <label class="form-label">Empleado *</label>
                    <select name="empleado_id" class="form-select" required>
                        <option value="">— Seleccionar —</option>
                        @foreach($empleados as $emp)
                            <option value="{{ $emp->id }}" @selected((string) old('empleado_id') === (string) $emp->id)>{{ $emp->nombre_completo }} (leg. {{ $emp->legajo }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Concepto *</label>
                    <select name="concepto_id" id="conceptoNovedad" class="form-select" required>
                        <option value="">— Seleccionar —</option>
                        <optgroup label="Manuales (importe)">
                            @foreach($conceptosNovedad->where('forma', 'manual') as $c)
                                <option value="{{ $c->id }}" data-manual="1" @selected((string) old('concepto_id') === (string) $c->id)>{{ $c->codigo }} · {{ $c->nombre }} ({{ mb_strtolower($c->tipo_nombre) }})</option>
                            @endforeach
                        </optgroup>
                        <optgroup label="Automáticos (corregir cantidad o importe)">
                            @foreach($conceptosNovedad->where('forma', '!=', 'manual') as $c)
                                <option value="{{ $c->id }}" data-manual="0" @selected((string) old('concepto_id') === (string) $c->id)>{{ $c->codigo }} · {{ $c->nombre }}</option>
                            @endforeach
                        </optgroup>
                    </select>
                </div>
                <div class="col-6" id="bloqueCantidad">
                    <label class="form-label">Cantidad</label>
                    <input type="number" name="cantidad" value="{{ old('cantidad') }}" class="form-control" step="0.01" min="0">
                    <div class="form-text">Horas, días o años según el concepto.</div>
                </div>
                <div class="col-6">
                    <label class="form-label">Importe</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" name="importe" value="{{ old('importe') }}" class="form-control" step="0.01">
                    </div>
                    <div class="form-text" id="ayudaImporte">Reemplaza el cálculo automático.</div>
                </div>
                <div class="col-12">
                    <label class="form-label">Observación</label>
                    <input type="text" name="observacion" value="{{ old('observacion') }}" class="form-control" maxlength="255">
                </div>
                <div class="col-12 small text-muted">Si ya hay una novedad del mismo concepto para el empleado, se reemplaza. Al guardar se recalcula la liquidación.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- Modal datos de pago --}}
<div class="modal fade" id="modalDatos" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="{{ route('liquidaciones.update', $l) }}" class="modal-content">
            @csrf @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title">Datos de pago y depósito</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <div class="col-md-8">
                    <label class="form-label">Descripción</label>
                    <input type="text" name="descripcion" value="{{ $l->descripcion }}" class="form-control" maxlength="255">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Fecha de pago</label>
                    <input type="date" name="fecha_pago" value="{{ $l->fecha_pago?->format('Y-m-d') }}" class="form-control">
                </div>
                <div class="col-12">
                    <label class="form-label">Lugar de pago</label>
                    <input type="text" name="lugar_pago" value="{{ $l->lugar_pago }}" class="form-control" maxlength="255" placeholder="Ej: acreditación bancaria / domicilio de la empresa">
                </div>
                <div class="col-12"><div class="small text-muted">Último depósito de aportes (LCT art. 140 inc. h)</div></div>
                <div class="col-md-4">
                    <label class="form-label">Período</label>
                    <input type="month" name="deposito_periodo" value="{{ $l->deposito_periodo }}" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Fecha</label>
                    <input type="date" name="deposito_fecha" value="{{ $l->deposito_fecha?->format('Y-m-d') }}" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Banco</label>
                    <input type="text" name="deposito_banco" value="{{ $l->deposito_banco }}" class="form-control" maxlength="255">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function abrirNovedad(datos = {}) {
    const f = document.getElementById('formNovedad');
    if (!f) return;
    if (datos.empleado_id) f.empleado_id.value = datos.empleado_id;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalNovedad')).show();
}
(function () {
    const sel = document.getElementById('conceptoNovedad');
    if (!sel) return;
    const actualizar = () => {
        const manual = sel.selectedOptions[0]?.dataset.manual === '1';
        document.getElementById('ayudaImporte').textContent = manual ? 'Obligatorio en los conceptos manuales.' : 'Opcional: reemplaza el cálculo automático.';
    };
    sel.addEventListener('change', actualizar);
    actualizar();

    @if($errors->hasAny(['empleado_id', 'concepto_id', 'cantidad', 'importe']))
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalNovedad')).show();
    @endif
})();
</script>
@endpush
