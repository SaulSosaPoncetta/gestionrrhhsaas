<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Inicio') · {{ config('app.name', 'Gestión RRHH') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/tema.css') }}?v=1" rel="stylesheet">
    <style>
        .barra-principal .navbar-brand { font-weight: 600; line-height: 1.1; }
        .barra-principal .navbar-brand small { display: block; font-size: .7rem; font-weight: 400; max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .barra-principal .nav-link { border-radius: .375rem; padding: .45rem .75rem !important; }
        .barra-principal .dropdown-menu { border: 0; box-shadow: 0 .5rem 1rem rgba(0,0,0,.15); margin-top: .35rem; }
        .barra-principal .dropdown-item.active, .barra-principal .dropdown-item:active { font-weight: 600; }
        .barra-principal .dropdown-item i { width: 1.25rem; display: inline-block; }
        .barra-principal .dropdown-header { font-size: .7rem; text-transform: uppercase; letter-spacing: .03em; }
        .barra-principal .badge-menu { font-size: .65rem; vertical-align: top; }
        @media (max-width: 1199.98px) {
            .barra-principal .navbar-collapse { padding: .5rem 0 1rem; max-height: calc(100vh - 60px); overflow-y: auto; }
            .barra-principal .dropdown-menu { box-shadow: none; margin-top: 0; }
        }
    </style>
    @stack('styles')
</head>
<body>
@php($u = auth()->user())
@php($miLegajo = ($u->esUsuarioPortal() || $u->esAdmin()) ? $u->empleado : null)
@php($pendientesLic = $u->esAdmin() ? \App\Models\Licencia::where('estado', 'pendiente')->count() : 0)
@php($pendEquipo = ($u->esSupervisor() && $miLegajo) ? \App\Models\Licencia::where('estado', 'pendiente')->whereIn('empleado_id', \App\Models\Empleado::activos()->where('supervisor_id', $miLegajo->id)->pluck('id'))->count() : 0)
@php($activo = fn (...$rutas) => request()->routeIs(...$rutas) ? 'active' : '')

<nav class="navbar navbar-expand-xl navbar-dark barra-principal sticky-top shadow-sm">
    <div class="container-fluid px-3">
        <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route($u->esUsuarioPortal() ? 'portal.inicio' : 'dashboard') }}">
            <i class="bi bi-people-fill fs-4"></i>
            <span>
                {{ config('app.name', 'Gestión RRHH') }}
                @if($u->empresa)<small>{{ $u->empresa->nombre }}</small>@endif
            </span>
        </a>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#menuPrincipal" aria-label="Menú">
            <span class="navbar-toggler-icon"></span>
            @if($pendientesLic || $pendEquipo)<span class="badge rounded-pill text-bg-warning badge-menu">{{ $pendientesLic + $pendEquipo }}</span>@endif
        </button>

        <div class="collapse navbar-collapse" id="menuPrincipal">
            <ul class="navbar-nav me-auto gap-xl-1">

                {{-- Inicio --}}
                @if($u->esUsuarioPortal())
                    <li class="nav-item"><a class="nav-link {{ $activo('portal.inicio') }}" href="{{ route('portal.inicio') }}"><i class="bi bi-house me-1"></i>Inicio</a></li>
                @else
                    <li class="nav-item"><a class="nav-link {{ $activo('dashboard') }}" href="{{ route('dashboard') }}"><i class="bi bi-speedometer2 me-1"></i>Inicio</a></li>
                @endif

                {{-- Autogestión: para empleados y supervisores, enlaces directos --}}
                @if($u->esUsuarioPortal() && $miLegajo)
                    <li class="nav-item"><a class="nav-link {{ $activo('portal.recibos*') }}" href="{{ route('portal.recibos') }}"><i class="bi bi-receipt me-1"></i>Mis recibos</a></li>
                    <li class="nav-item"><a class="nav-link {{ $activo('portal.licencias*') }}" href="{{ route('portal.licencias') }}"><i class="bi bi-calendar-heart me-1"></i>Mis licencias</a></li>
                    <li class="nav-item"><a class="nav-link {{ $activo('portal.asistencia') }}" href="{{ route('portal.asistencia') }}"><i class="bi bi-calendar-check me-1"></i>Mi asistencia</a></li>
                    <li class="nav-item"><a class="nav-link {{ $activo('portal.datos*') }}" href="{{ route('portal.datos') }}"><i class="bi bi-person-lines-fill me-1"></i>Mis datos</a></li>
                    @if($u->esSupervisor())
                        <li class="nav-item">
                            <a class="nav-link {{ $activo('portal.equipo*') }}" href="{{ route('portal.equipo') }}">
                                <i class="bi bi-people me-1"></i>Mi equipo
                                @if($pendEquipo)<span class="badge rounded-pill text-bg-warning badge-menu">{{ $pendEquipo }}</span>@endif
                            </a>
                        </li>
                    @endif
                @endif

                {{-- Administración de RRHH --}}
                @if($u->esAdmin())
                    <li class="nav-item"><a class="nav-link {{ $activo('empleados.*') }}" href="{{ route('empleados.index') }}"><i class="bi bi-person-vcard me-1"></i>Legajos</a></li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle {{ $activo('asistencia.*', 'fichadas.*', 'horarios.*', 'feriados.*') }}" href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-clipboard-check me-1"></i>Asistencia
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item {{ $activo('asistencia.parte') }}" href="{{ route('asistencia.parte') }}"><i class="bi bi-clipboard-check"></i>Parte diario</a></li>
                            <li><a class="dropdown-item {{ $activo('fichadas.*') }}" href="{{ route('fichadas.index') }}"><i class="bi bi-fingerprint"></i>Fichadas</a></li>
                            <li><a class="dropdown-item {{ $activo('asistencia.resumen*', 'asistencia.empleado') }}" href="{{ route('asistencia.resumen') }}"><i class="bi bi-calendar3"></i>Resumen mensual</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><h6 class="dropdown-header">Configuración</h6></li>
                            <li><a class="dropdown-item {{ $activo('horarios.*') }}" href="{{ route('horarios.index') }}"><i class="bi bi-clock"></i>Horarios</a></li>
                            <li><a class="dropdown-item {{ $activo('feriados.*') }}" href="{{ route('feriados.index') }}"><i class="bi bi-calendar-x"></i>Feriados</a></li>
                        </ul>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle {{ $activo('licencias.*', 'vacaciones.*', 'tipos-licencia.*') }}" href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-calendar-heart me-1"></i>Licencias
                            @if($pendientesLic)<span class="badge rounded-pill text-bg-warning badge-menu">{{ $pendientesLic }}</span>@endif
                        </a>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item d-flex align-items-center gap-2 {{ $activo('licencias.index', 'licencias.create', 'licencias.edit') }}" href="{{ route('licencias.index') }}">
                                    <i class="bi bi-calendar-heart"></i>Licencias
                                    @if($pendientesLic)<span class="badge rounded-pill text-bg-warning ms-auto">{{ $pendientesLic }} pend.</span>@endif
                                </a>
                            </li>
                            <li><a class="dropdown-item {{ $activo('vacaciones.*') }}" href="{{ route('vacaciones.index') }}"><i class="bi bi-sun"></i>Vacaciones</a></li>
                            <li><a class="dropdown-item {{ $activo('licencias.calendario') }}" href="{{ route('licencias.calendario') }}"><i class="bi bi-calendar-week"></i>Calendario</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><h6 class="dropdown-header">Configuración</h6></li>
                            <li><a class="dropdown-item {{ $activo('tipos-licencia.*') }}" href="{{ route('tipos-licencia.index') }}"><i class="bi bi-tags"></i>Tipos de licencia</a></li>
                        </ul>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle {{ $activo('liquidaciones.*', 'conceptos.*') }}" href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-cash-stack me-1"></i>Sueldos
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item {{ $activo('liquidaciones.*') }}" href="{{ route('liquidaciones.index') }}"><i class="bi bi-cash-stack"></i>Liquidaciones</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><h6 class="dropdown-header">Configuración</h6></li>
                            <li><a class="dropdown-item {{ $activo('conceptos.*') }}" href="{{ route('conceptos.index') }}"><i class="bi bi-list-columns-reverse"></i>Conceptos</a></li>
                        </ul>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle {{ $activo('areas.*', 'puestos.*', 'convenios.*', 'categorias.*') }}" href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-diagram-3 me-1"></i>Estructura
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item {{ $activo('areas.*') }}" href="{{ route('areas.index') }}"><i class="bi bi-diagram-3"></i>Áreas</a></li>
                            <li><a class="dropdown-item {{ $activo('puestos.*') }}" href="{{ route('puestos.index') }}"><i class="bi bi-briefcase"></i>Puestos</a></li>
                            <li><a class="dropdown-item {{ $activo('convenios.*', 'categorias.*') }}" href="{{ route('convenios.index') }}"><i class="bi bi-journal-text"></i>Convenios y categorías</a></li>
                        </ul>
                    </li>
                @endif

                {{-- Administración del sistema --}}
                @if($u->esSuperadmin() || $u->esAdmin())
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle {{ $activo('empresas.*', 'usuarios.*') }}" href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-gear me-1"></i>Administración
                        </a>
                        <ul class="dropdown-menu">
                            @if($u->esSuperadmin())
                                <li><a class="dropdown-item {{ $activo('empresas.*') }}" href="{{ route('empresas.index') }}"><i class="bi bi-building"></i>Empresas</a></li>
                            @endif
                            <li><a class="dropdown-item {{ $activo('usuarios.*') }}" href="{{ route('usuarios.index') }}"><i class="bi bi-person-gear"></i>Usuarios</a></li>
                        </ul>
                    </li>
                @endif

                {{-- Mi portal: admins que además tienen legajo --}}
                @if(! $u->esUsuarioPortal() && $miLegajo)
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle {{ $activo('portal.*') }}" href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-person-badge me-1"></i>Mi portal
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item {{ $activo('portal.inicio') }}" href="{{ route('portal.inicio') }}"><i class="bi bi-person-badge"></i>Mi inicio</a></li>
                            <li><a class="dropdown-item {{ $activo('portal.recibos*') }}" href="{{ route('portal.recibos') }}"><i class="bi bi-receipt"></i>Mis recibos</a></li>
                            <li><a class="dropdown-item {{ $activo('portal.licencias*') }}" href="{{ route('portal.licencias') }}"><i class="bi bi-calendar-heart"></i>Mis licencias</a></li>
                            <li><a class="dropdown-item {{ $activo('portal.asistencia') }}" href="{{ route('portal.asistencia') }}"><i class="bi bi-calendar-check"></i>Mi asistencia</a></li>
                            <li><a class="dropdown-item {{ $activo('portal.datos*') }}" href="{{ route('portal.datos') }}"><i class="bi bi-person-lines-fill"></i>Mis datos</a></li>
                        </ul>
                    </li>
                @endif
            </ul>

            {{-- Usuario --}}
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-1"></i>{{ $u->name }}
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text small text-muted">{{ $u->rol_nombre }}</span></li>
                        <li><a class="dropdown-item {{ $activo('password.*') }}" href="{{ route('password.editar') }}"><i class="bi bi-key"></i>Cambiar contraseña</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="dropdown-item"><i class="bi bi-box-arrow-right"></i>Cerrar sesión</button>
                            </form>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<main class="container-fluid p-3 p-md-4">
    <h1 class="h5 fw-semibold mb-3">@yield('title', 'Inicio')</h1>

    @if(session('ok'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('ok') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('aviso'))
        <div class="alert alert-warning alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle me-1"></i>{{ session('aviso') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @yield('content')
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
