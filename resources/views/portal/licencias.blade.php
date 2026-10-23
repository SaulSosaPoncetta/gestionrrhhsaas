@extends('layouts.app')

@section('title', 'Mis licencias')

@section('content')
@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
    </div>
@endif

<div class="row g-3 mb-3">
    @foreach($saldos as $anio => $s)
        <div class="col-sm-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="small text-muted">Vacaciones {{ $anio }}</div>
                <div class="fs-3 fw-semibold {{ $s['saldo'] < 0 ? 'text-danger' : 'text-success' }}">{{ $s['saldo'] }} <span class="fs-6 fw-normal text-muted">días</span></div>
                <div class="small text-muted">
                    Corresponden {{ $s['corresponden'] + $s['ajuste'] }} · tomados {{ $s['tomados'] }}
                    @if($s['pendientes']) · {{ $s['pendientes'] }} pedidos @endif
                </div>
            </div></div>
        </div>
    @endforeach
    <div class="col-sm-6 col-lg-4 d-flex align-items-center">
        <a href="{{ route('portal.licencias.create') }}" class="btn btn-primary btn-lg w-100"><i class="bi bi-plus-lg me-1"></i>Pedir licencia</a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <ul class="list-group list-group-flush">
        @forelse($licencias as $l)
            <li class="list-group-item">
                <div class="d-flex flex-wrap gap-2 justify-content-between align-items-start">
                    <div>
                        <span class="badge text-bg-{{ $l->tipo->color }}">{{ $l->tipo->nombre }}</span>
                        <span class="badge text-bg-{{ $l->estado_color }}">{{ $l->estado_nombre }}</span>
                        <div class="mt-1">{{ $l->periodo_texto }} · <strong>{{ $l->dias }}</strong> {{ $l->tipo->esHabiles() ? 'días hábiles' : 'días' }}</div>
                        @if($l->observaciones)<div class="small text-muted">{{ $l->observaciones }}</div>@endif
                        @if($l->motivo_rechazo)<div class="small text-danger">Motivo: {{ $l->motivo_rechazo }}</div>@endif
                        @if($l->resueltaPor && ! $l->esPendiente() && $l->estado !== 'cancelada')
                            <div class="small text-muted">{{ $l->estado_nombre }} por {{ $l->resueltaPor->name }} el {{ $l->resuelta_at?->format('d/m/Y') }}</div>
                        @endif
                    </div>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        @if($l->certificado)
                            <a href="{{ route('portal.licencias.certificado.ver', $l) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-file-earmark-medical me-1"></i>Certificado
                            </a>
                        @elseif(in_array($l->estado, ['pendiente', 'aprobada']) && $l->tipo->requiere_certificado)
                            <form method="POST" action="{{ route('portal.licencias.certificado', $l) }}" enctype="multipart/form-data" class="d-flex gap-1">
                                @csrf
                                <input type="file" name="certificado" accept=".pdf,.jpg,.jpeg,.png,.webp" class="form-control form-control-sm" style="max-width:200px" required>
                                <button class="btn btn-sm btn-outline-warning" title="Adjuntar certificado"><i class="bi bi-upload"></i></button>
                            </form>
                        @endif
                        @if($l->esPendiente())
                            <form method="POST" action="{{ route('portal.licencias.cancelar', $l) }}" onsubmit="return confirm('¿Cancelar esta solicitud?')">
                                @csrf @method('PATCH')
                                <button class="btn btn-sm btn-outline-danger">Cancelar</button>
                            </form>
                        @endif
                    </div>
                </div>
            </li>
        @empty
            <li class="list-group-item text-center text-muted py-5">No tenés licencias registradas.</li>
        @endforelse
    </ul>
</div>

<div class="mt-3">{{ $licencias->links() }}</div>
@endsection
