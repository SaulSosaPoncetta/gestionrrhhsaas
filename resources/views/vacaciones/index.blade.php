@extends('layouts.app')

@section('title', 'Vacaciones ' . $anio)

@section('content')
@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="btn-group">
        <a href="{{ route('vacaciones.index', ['anio' => $anio - 1]) }}" class="btn btn-outline-secondary"><i class="bi bi-chevron-left"></i></a>
        <span class="btn btn-outline-secondary disabled fw-semibold">Período {{ $anio }}</span>
        <a href="{{ route('vacaciones.index', ['anio' => $anio + 1]) }}" class="btn btn-outline-secondary"><i class="bi bi-chevron-right"></i></a>
    </div>
    <div class="d-flex gap-2">
        <form method="POST" action="{{ route('vacaciones.generar') }}"
              onsubmit="return confirm('Se van a guardar los días que corresponden a cada empleado en {{ $anio }} según la LCT. Los que modificaste a mano no se cambian. ¿Continuar?')">
            @csrf
            <input type="hidden" name="anio" value="{{ $anio }}">
            <button class="btn {{ $generado ? 'btn-outline-primary' : 'btn-primary' }}">
                <i class="bi bi-calculator me-1"></i>{{ $generado ? 'Recalcular' : 'Calcular y guardar' }} {{ $anio }}
            </button>
        </form>
        @if($tipoVacaciones)
            <a href="{{ route('licencias.create', ['tipo_id' => $tipoVacaciones->id, 'periodo' => $anio]) }}" class="btn btn-success">
                <i class="bi bi-sun me-1"></i>Asignar vacaciones
            </a>
        @endif
    </div>
</div>

@unless($tipoVacaciones)
    <div class="alert alert-warning">
        No hay un tipo de licencia marcado como vacaciones. Cargalo desde
        <a href="{{ route('tipos-licencia.index') }}">Tipos de licencia</a>.
    </div>
@endunless

@unless($generado)
    <div class="alert alert-info small">
        <i class="bi bi-info-circle me-1"></i>Los días se están mostrando calculados en el momento. Con «Calcular y guardar» quedan fijos
        y podés modificarlos a mano (por ejemplo, si el convenio da más días).
    </div>
@endunless

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Empleado</th>
                    <th class="d-none d-md-table-cell">Antigüedad al 31/12</th>
                    <th class="text-center">Corresponden</th>
                    <th class="text-center d-none d-md-table-cell">Ajuste</th>
                    <th class="text-center">Tomados</th>
                    <th class="text-center d-none d-md-table-cell">Pendientes</th>
                    <th class="text-center">Saldo</th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
            @forelse($filas as ['empleado' => $emp, 'saldo' => $s])
                <tr>
                    <td>
                        <a href="{{ route('empleados.show', $emp) }}#licencias" class="fw-semibold text-decoration-none text-dark">{{ $emp->nombre_completo }}</a>
                        <div class="small text-muted">
                            Ingreso {{ $emp->fecha_ingreso->format('d/m/Y') }}
                            @unless($emp->activo)<span class="badge text-bg-secondary">baja</span>@endunless
                        </div>
                    </td>
                    <td class="d-none d-md-table-cell small">{{ $s['calculado']['antiguedad'] }}</td>
                    <td class="text-center">
                        <span class="fw-semibold">{{ $s['corresponden'] }}</span>
                        @if($s['periodo']?->manual)
                            <span class="badge text-bg-warning" title="Modificado a mano (la LCT da {{ $s['calculado']['dias'] }})">manual</span>
                        @elseif($s['calculado']['proporcional'])
                            <span class="badge text-bg-info" title="Trabajó {{ $s['calculado']['trabajados'] }} de {{ $s['calculado']['habiles'] }} días hábiles: 1 día cada 20 trabajados (LCT art. 153)">proporcional</span>
                        @endif
                    </td>
                    <td class="text-center d-none d-md-table-cell {{ $s['ajuste'] ? 'fw-semibold' : 'text-muted' }}">
                        {{ $s['ajuste'] > 0 ? '+' . $s['ajuste'] : $s['ajuste'] }}
                    </td>
                    <td class="text-center">{{ $s['tomados'] }}</td>
                    <td class="text-center d-none d-md-table-cell {{ $s['pendientes'] ? 'text-warning fw-semibold' : 'text-muted' }}">{{ $s['pendientes'] }}</td>
                    <td class="text-center">
                        <span class="badge fs-6 {{ $s['saldo'] < 0 ? 'text-bg-danger' : ($s['saldo'] === 0 ? 'text-bg-secondary' : 'text-bg-success') }}">{{ $s['saldo'] }}</span>
                    </td>
                    <td class="text-end text-nowrap">
                        <button class="btn btn-sm btn-outline-primary" title="Modificar días"
                                data-vacacion="{{ json_encode([
                                    'url'           => route('vacaciones.update', [$emp, $anio]),
                                    'nombre'        => $emp->nombre_completo,
                                    'corresponden'  => $s['corresponden'],
                                    'calculado'     => $s['calculado']['dias'],
                                    'ajuste'        => $s['ajuste'],
                                    'observaciones' => $s['periodo']?->observaciones,
                                ]) }}"
                                onclick="abrirVacacion(JSON.parse(this.dataset.vacacion))"><i class="bi bi-pencil"></i></button>
                        @if($tipoVacaciones && $emp->activo && $s['disponible'] > 0)
                            <a href="{{ route('licencias.create', ['empleado_id' => $emp->id, 'tipo_id' => $tipoVacaciones->id, 'periodo' => $anio]) }}"
                               class="btn btn-sm btn-outline-success" title="Asignar vacaciones"><i class="bi bi-sun"></i></a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No hay empleados con relación laboral en {{ $anio }}.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="small text-muted mt-2">
    <p class="mb-1"><strong>LCT art. 150:</strong> 14 días corridos hasta 5 años de antigüedad · 21 hasta 10 · 28 hasta 20 · 35 con más de 20. La antigüedad se calcula al 31/12.</p>
    <p class="mb-1"><strong>Art. 153:</strong> si trabajó menos de la mitad de los días hábiles del año, corresponde 1 día cada 20 trabajados.</p>
    <p class="mb-0"><strong>Arts. 151 y 154:</strong> empiezan un lunes y se otorgan entre el 1/10 y el 30/4. <strong>Ajuste:</strong> días de convenio, saldo del año anterior o descuentos.</p>
</div>

{{-- Modal edición --}}
<div class="modal fade" id="modalVacacion" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="formVacacion" class="modal-content">
            @csrf @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title">Vacaciones {{ $anio }} · <span id="vacNombre"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <div class="col-6">
                    <label class="form-label">Días que corresponden *</label>
                    <input type="number" name="dias_corresponden" class="form-control" min="0" max="365" required>
                    <div class="form-text">Según la LCT: <span id="vacCalculado"></span></div>
                </div>
                <div class="col-6">
                    <label class="form-label">Ajuste (+/−)</label>
                    <input type="number" name="dias_ajuste" class="form-control" min="-365" max="365" required>
                    <div class="form-text">Ej: +5 de saldo anterior.</div>
                </div>
                <div class="col-12">
                    <label class="form-label">Observaciones</label>
                    <input type="text" name="observaciones" class="form-control" maxlength="255" placeholder="Ej: CCT da 2 días más · arrastre de 2025">
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
function abrirVacacion(d) {
    const f = document.getElementById('formVacacion');
    f.action = d.url;
    document.getElementById('vacNombre').textContent = d.nombre;
    document.getElementById('vacCalculado').textContent = d.calculado + ' días';
    f.dias_corresponden.value = d.corresponden;
    f.dias_ajuste.value = d.ajuste;
    f.observaciones.value = d.observaciones ?? '';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalVacacion')).show();
}
</script>
@endpush
