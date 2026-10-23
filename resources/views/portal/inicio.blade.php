@extends('layouts.app')

@section('title', 'Mi portal')

@section('content')
@php
    $e = $empleado;
    $Formato = \App\Support\Formato::class;
    $hora = now()->hour;
    $saludo = $hora < 13 ? 'Buen día' : ($hora < 20 ? 'Buenas tardes' : 'Buenas noches');
@endphp

<h2 class="h5 mb-1">{{ $saludo }}, {{ $e->nombre }}</h2>
<p class="text-muted small mb-4">{{ ucfirst(now()->locale('es')->isoFormat('dddd D [de] MMMM')) }} · {{ $e->puesto?->nombre ?? 'Legajo ' . $e->legajo }}</p>

<div class="row g-3">
    {{-- Fichaje y hoy --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-clock me-1"></i>Hoy</div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <div class="small text-muted">Tu horario</div>
                        <div class="fw-semibold">{{ $hoy->textoProgramado() }}</div>
                    </div>
                    <span class="badge text-bg-{{ $hoy->color() }} fs-6">{{ $hoy->etiqueta() }}</span>
                </div>

                @if($hoy->licencia)
                    <div class="alert alert-info py-2 small mb-3"><i class="bi bi-calendar-heart me-1"></i>Estás de licencia: {{ $hoy->licencia }}.</div>
                @endif

                <div class="small text-muted mb-1">Fichadas de hoy</div>
                <div class="mb-3">
                    @forelse($hoy->fichadas() as $f)
                        <span class="badge font-monospace {{ $f->esEntrada() ? 'text-bg-success' : 'text-bg-danger' }} me-1">
                            {{ $f->esEntrada() ? 'Entrada' : 'Salida' }} {{ $f->fecha_hora->format('H:i') }}
                        </span>
                    @empty
                        <span class="text-muted small">Todavía no fichaste.</span>
                    @endforelse
                </div>

                @if($e->permite_fichaje_web)
                    <form method="POST" action="{{ route('portal.fichar') }}"
                          onsubmit="this.querySelector('button').disabled = true;">
                        @csrf
                        <button class="btn btn-lg w-100 {{ $proximo === 'entrada' ? 'btn-success' : 'btn-danger' }}">
                            <i class="bi {{ $proximo === 'entrada' ? 'bi-box-arrow-in-right' : 'bi-box-arrow-right' }} me-1"></i>
                            Fichar {{ $proximo }} · {{ now()->format('H:i') }}
                        </button>
                    </form>
                    <div class="form-text text-center">Se registra la hora del servidor y tu IP.</div>
                @else
                    <div class="small text-muted"><i class="bi bi-info-circle me-1"></i>Fichás con el reloj de la empresa.</div>
                @endif
            </div>
        </div>
    </div>

    {{-- Vacaciones y licencias --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="display-6 fw-semibold {{ $saldo['saldo'] < 0 ? 'text-danger' : 'text-success' }}">{{ $saldo['saldo'] }}</div>
                <div>
                    <div class="fw-semibold">días de vacaciones {{ $periodo }}</div>
                    <div class="small text-muted">
                        Te corresponden {{ $saldo['corresponden'] + $saldo['ajuste'] }} · tomaste {{ $saldo['tomados'] }}
                        @if($saldo['pendientes']) · {{ $saldo['pendientes'] }} pedidos sin aprobar @endif
                    </div>
                </div>
                <a href="{{ route('portal.licencias.create') }}" class="btn btn-outline-primary btn-sm ms-auto">Pedir</a>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-calendar-heart me-1"></i>Tus próximas licencias</span>
                <a href="{{ route('portal.licencias') }}" class="small">Ver todas</a>
            </div>
            <ul class="list-group list-group-flush">
                @forelse($licencias as $l)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <span class="badge text-bg-{{ $l->tipo->color }}">{{ $l->tipo->nombre }}</span>
                            <div class="small text-muted">{{ $l->periodo_texto }} · {{ $l->dias }} días</div>
                        </div>
                        <span class="badge text-bg-{{ $l->estado_color }}">{{ $l->estado_nombre }}</span>
                    </li>
                @empty
                    <li class="list-group-item text-muted small">No tenés licencias pedidas ni en curso.</li>
                @endforelse
            </ul>
        </div>
    </div>

    {{-- Supervisor --}}
    @if(auth()->user()->esSupervisor())
        <div class="col-12">
            <a href="{{ route('portal.equipo') }}" class="card border-0 shadow-sm text-decoration-none">
                <div class="card-body d-flex align-items-center gap-3">
                    <i class="bi bi-people fs-2 text-primary"></i>
                    <div>
                        <div class="fw-semibold text-dark">Mi equipo</div>
                        <div class="small text-muted">Asistencia de hoy y licencias de tu personal a cargo</div>
                    </div>
                    @if($pendientesEquipo)
                        <span class="badge text-bg-warning ms-auto fs-6">{{ $pendientesEquipo }} por aprobar</span>
                    @endif
                </div>
            </a>
        </div>
    @endif

    {{-- Recibos --}}
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-receipt me-1"></i>Últimos recibos</span>
                <a href="{{ route('portal.recibos') }}" class="small">Ver todos</a>
            </div>
            <ul class="list-group list-group-flush">
                @forelse($recibos as $r)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-semibold">{{ $r->liquidacion->periodo_texto }}</div>
                            <div class="small text-muted">Neto $ {{ number_format($r->neto, 2, ',', '.') }}</div>
                        </div>
                        <div class="d-flex gap-2 align-items-center">
                            @unless($r->conformado_at)<span class="badge text-bg-warning">Sin conformar</span>@endunless
                            <a href="{{ route('portal.recibos.ver', $r) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                        </div>
                    </li>
                @empty
                    <li class="list-group-item text-muted small">Todavía no hay recibos disponibles.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
