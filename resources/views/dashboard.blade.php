@extends('layouts.app')

@section('title', 'Inicio')

@section('content')
<h2 class="h5 mb-4">Hola, {{ $user->name }}</h2>

@if($user->esSuperadmin())
    <div class="row g-3">
        <div class="col-sm-6 col-lg-4">
            <div class="card border-0 shadow-sm"><div class="card-body">
                <div class="text-muted small">Empresas</div>
                <div class="fs-3 fw-semibold">{{ $stats['empresas'] }}</div>
                <div class="small text-success">{{ $stats['activas'] }} activas</div>
            </div></div>
        </div>
        <div class="col-sm-6 col-lg-4">
            <div class="card border-0 shadow-sm"><div class="card-body">
                <div class="text-muted small">Usuarios</div>
                <div class="fs-3 fw-semibold">{{ $stats['usuarios'] }}</div>
            </div></div>
        </div>
    </div>
@else
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <a href="{{ route('empleados.index') }}" class="card border-0 shadow-sm text-decoration-none h-100"><div class="card-body">
                <div class="text-muted small">Empleados activos</div>
                <div class="fs-3 fw-semibold text-dark">{{ $stats['activos'] }}</div>
            </div></a>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Ingresos del mes</div>
                <div class="fs-3 fw-semibold text-success">{{ $stats['ingresos'] }}</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Egresos del mes</div>
                <div class="fs-3 fw-semibold text-danger">{{ $stats['egresos'] }}</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Usuarios del sistema</div>
                <div class="fs-3 fw-semibold text-dark">{{ $stats['usuarios'] }}</div>
            </div></div>
        </div>
    </div>

    {{-- Asistencia de hoy --}}
    @php
        $presentesHoy = ($asistenciaHoy['presente'] ?? 0) + ($asistenciaHoy['trabajando'] ?? 0) + ($asistenciaHoy['tarde'] ?? 0);
        $resumenHoy = [
            ['Presentes', $presentesHoy, 'success', null],
            ['Llegadas tarde', $asistenciaHoy['tarde'] ?? 0, 'warning', 'tarde'],
            ['Ausentes', $asistenciaHoy['ausente'] ?? 0, 'danger', 'ausente'],
            ['De licencia', $asistenciaHoy['licencia'] ?? 0, 'info', 'licencia'],
            ['Pendientes', $asistenciaHoy['pendiente'] ?? 0, 'secondary', 'pendiente'],
        ];
    @endphp
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-semibold"><i class="bi bi-clipboard-check text-primary me-1"></i>Asistencia de hoy</span>
            <a href="{{ route('asistencia.parte') }}" class="small">Ver parte diario</a>
        </div>
        <div class="card-body row text-center g-2">
            @foreach($resumenHoy as [$titulo, $valor, $color, $filtro])
                <div class="col">
                    <a href="{{ route('asistencia.parte', array_filter(['estado' => $filtro])) }}" class="text-decoration-none">
                        <div class="fs-3 fw-semibold text-{{ $color === 'secondary' ? 'dark' : $color }}">{{ $valor }}</div>
                        <div class="small text-muted">{{ $titulo }}</div>
                    </a>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Licencias --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <a href="{{ route('licencias.index', ['estado' => 'pendiente']) }}" class="card border-0 shadow-sm text-decoration-none h-100">
                <div class="card-body">
                    <div class="text-muted small">Licencias pendientes de aprobar</div>
                    <div class="fs-3 fw-semibold {{ $licenciasPendientes ? 'text-warning' : 'text-dark' }}">{{ $licenciasPendientes }}</div>
                </div>
            </a>
        </div>
        <div class="col-md-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-calendar-heart text-info me-1"></i>De licencia hoy</span>
                    <a href="{{ route('licencias.calendario') }}" class="small">Calendario</a>
                </div>
                <ul class="list-group list-group-flush">
                    @forelse($deLicenciaHoy as $lic)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <a href="{{ route('empleados.show', $lic->empleado) }}#licencias" class="text-decoration-none">{{ $lic->empleado->nombre_completo }}</a>
                            <span class="small">
                                <span class="badge text-bg-{{ $lic->tipo->color }}">{{ $lic->tipo->nombre }}</span>
                                <span class="text-muted">hasta el {{ $lic->fecha_hasta->format('d/m') }}</span>
                            </span>
                        </li>
                    @empty
                        <li class="list-group-item text-muted small">Nadie está de licencia hoy.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-exclamation-triangle text-warning me-1"></i>Documentos vencidos o por vencer
                </div>
                <ul class="list-group list-group-flush">
                    @forelse($documentosAlerta as $doc)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <a href="{{ route('empleados.show', $doc->empleado) }}#documentos" class="fw-semibold text-decoration-none">
                                    {{ $doc->empleado->nombre_completo }}
                                </a>
                                <div class="small text-muted">{{ $doc->tipo_nombre }}</div>
                            </div>
                            <span class="badge {{ $doc->estado_vencimiento === 'vencido' ? 'text-bg-danger' : 'text-bg-warning' }}">
                                {{ $doc->vencimiento->format('d/m/Y') }}
                            </span>
                        </li>
                    @empty
                        <li class="list-group-item text-muted small">No hay documentos con vencimiento en los próximos 30 días.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-cake2 text-primary me-1"></i>Cumpleaños del mes</div>
                <ul class="list-group list-group-flush">
                    @forelse($cumpleanios as $emp)
                        <li class="list-group-item d-flex justify-content-between">
                            <a href="{{ route('empleados.show', $emp) }}" class="text-decoration-none">{{ $emp->nombre_completo }}</a>
                            <span class="small {{ $emp->fecha_nacimiento->day === now()->day ? 'fw-semibold text-primary' : 'text-muted' }}">
                                {{ $emp->fecha_nacimiento->format('d/m') }}
                            </span>
                        </li>
                    @empty
                        <li class="list-group-item text-muted small">Nadie cumple años este mes.</li>
                    @endforelse
                </ul>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-award text-success me-1"></i>Aniversarios laborales del mes</div>
                <ul class="list-group list-group-flush">
                    @forelse($aniversarios as $emp)
                        <li class="list-group-item d-flex justify-content-between">
                            <a href="{{ route('empleados.show', $emp) }}" class="text-decoration-none">{{ $emp->nombre_completo }}</a>
                            <span class="small text-muted">
                                {{ now()->year - $emp->fecha_ingreso->year }} {{ now()->year - $emp->fecha_ingreso->year === 1 ? 'año' : 'años' }}
                                · {{ $emp->fecha_ingreso->format('d/m') }}
                            </span>
                        </li>
                    @empty
                        <li class="list-group-item text-muted small">No hay aniversarios este mes.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
@endif
@endsection
