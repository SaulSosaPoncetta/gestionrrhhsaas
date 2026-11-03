@php
    $marca = 'Gestión RRHH';
    $fmtPrecio = fn ($p) => is_null($p) ? 'Consultar' : '$ ' . number_format($p, 0, ',', '.');
    $rango = fn ($pl) => is_null($pl['hasta'])
        ? 'Más de ' . ($pl['desde'] - 1) . ' empleados'
        : ($pl['desde'] === $pl['hasta'] ? $pl['desde'] . ' empleado' : $pl['desde'] . ' a ' . $pl['hasta'] . ' empleados');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $marca }} — Recursos y capital humano para tu empresa</title>
    <meta name="description" content="Legajos, asistencia, licencias, vacaciones y liquidación de sueldos en un solo sistema, con portal de autogestión para empleados y supervisores.">
    <meta name="theme-color" content="#800020">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --rh-bordo: #800020;       /* principal del sistema */
            --rh-700: #6b1427;
            --rh-800: #55101f;
            --rh-900: #3d0a16;
            --rh-950: #2a0610;
            --rh-400: #b6475d;
            --rh-300: #d17f90;
            --rh-200: #e9bcc5;
            --rh-100: #f6e5e8;
            --rh-50:  #fbf4f5;
            --rh-pizarra: #2b2024;
        }
        html { scroll-behavior: smooth; scroll-padding-top: 76px; }
        body { font-family: 'Segoe UI', system-ui, -apple-system, Roboto, sans-serif; color: var(--rh-pizarra); background: #fff; }

        .landing-nav { background: rgba(255,255,255,.96); backdrop-filter: blur(10px); box-shadow: 0 2px 20px rgba(0,0,0,.07); }
        .landing-nav .navbar-brand { color: var(--rh-bordo); font-weight: 800; }
        .landing-nav .nav-link { color: var(--rh-pizarra); font-weight: 600; }
        .landing-nav .nav-link:hover { color: var(--rh-bordo); }

        .btn-rh { background: var(--rh-bordo); color: #fff; border: 0; border-radius: 50px; padding: 13px 30px; font-weight: 600; text-decoration: none; transition: all .25s; display: inline-block; }
        .btn-rh:hover { background: var(--rh-700); color: #fff; transform: translateY(-2px); box-shadow: 0 8px 25px rgba(128,0,32,.4); }
        .btn-rh-outline { background: transparent; color: #fff; border: 2px solid rgba(255,255,255,.4); border-radius: 50px; padding: 11px 30px; font-weight: 600; text-decoration: none; transition: all .25s; display: inline-block; }
        .btn-rh-outline:hover { border-color: #fff; background: rgba(255,255,255,.1); color: #fff; }
        .btn-primary { --bs-btn-bg: var(--rh-bordo); --bs-btn-border-color: var(--rh-bordo); --bs-btn-hover-bg: var(--rh-700); --bs-btn-hover-border-color: var(--rh-700); --bs-btn-active-bg: var(--rh-800); --bs-btn-active-border-color: var(--rh-800); }
        .btn-outline-primary { --bs-btn-color: var(--rh-bordo); --bs-btn-border-color: var(--rh-bordo); --bs-btn-hover-bg: var(--rh-bordo); --bs-btn-hover-border-color: var(--rh-bordo); --bs-btn-active-bg: var(--rh-bordo); }
        .text-rh { color: var(--rh-bordo) !important; }

        .landing-hero { background: linear-gradient(135deg, var(--rh-950) 0%, var(--rh-900) 45%, var(--rh-700) 100%); padding: 130px 0 90px; position: relative; overflow: hidden; }
        .landing-hero::before { content: ''; position: absolute; width: 600px; height: 600px; top: -220px; right: -200px; background: radial-gradient(circle, rgba(209,127,144,.22) 0%, transparent 70%); }
        .landing-hero::after { content: ''; position: absolute; width: 420px; height: 420px; bottom: -140px; left: -110px; background: radial-gradient(circle, rgba(182,71,93,.25) 0%, transparent 70%); }
        .landing-badge { display: inline-block; background: rgba(233,188,197,.15); border: 1px solid rgba(233,188,197,.4); color: var(--rh-200); padding: 6px 16px; border-radius: 20px; font-size: 14px; margin-bottom: 22px; }
        .landing-hero h1 { font-size: clamp(2.1rem, 5vw, 3.3rem); font-weight: 800; line-height: 1.12; color: #fff; }
        .landing-hero h1 span { color: var(--rh-200); }

        /* Mock */
        .mock { background: #fff; border-radius: 16px; box-shadow: 0 30px 70px rgba(0,0,0,.5); overflow: hidden; color: var(--rh-pizarra); }
        .mock-bar { background: #f3eced; padding: 10px 14px; display: flex; gap: 6px; align-items: center; border-bottom: 1px solid #e6dcde; }
        .mock-bar i { width: 10px; height: 10px; border-radius: 50%; background: #cdbfc2; display: inline-block; }
        .mock-bar small { margin-left: 10px; color: #7a6a6e; font-size: 12px; }
        .mock-body { padding: 18px; }
        .mock table { width: 100%; font-size: 13px; }
        .mock th { color: #7a6a6e; font-weight: 600; font-size: 11px; text-transform: uppercase; padding: 6px 4px; }
        .mock td { padding: 7px 4px; border-top: 1px solid #f0e8ea; }
        .pill { display: inline-block; min-width: 70px; text-align: center; border-radius: 20px; font-size: 11px; font-weight: 700; padding: 3px 10px; }
        .pill-ok { background: #d9f2e3; color: #146c43; }
        .pill-lic { background: var(--rh-100); color: var(--rh-bordo); }
        .pill-aus { background: #fde0e0; color: #b02a37; }
        .pill-tar { background: #fff0c7; color: #8a6100; }
        .mock-kpi { background: var(--rh-50); border: 1px solid var(--rh-100); border-radius: 10px; padding: 10px; text-align: center; }
        .mock-kpi b { display: block; font-size: 18px; color: var(--rh-bordo); }
        .mock-kpi span { font-size: 11px; color: #7a6a6e; }
        .mock-caption { font-size: 12px; color: rgba(255,255,255,.6); margin-top: 12px; }

        .landing-section { padding: 80px 0; }
        .section-tag { display: inline-block; background: rgba(128,0,32,.1); color: var(--rh-bordo); padding: 7px 16px; border-radius: 50px; font-weight: 600; font-size: 14px; margin-bottom: 14px; }
        .bg-rh-fondo { background: var(--rh-50); }

        .persona-card { background: #fff; border: 1px solid var(--rh-100); border-radius: 18px; padding: 28px; height: 100%; }
        .persona-card .ic { width: 52px; height: 52px; border-radius: 14px; background: var(--rh-100); color: var(--rh-bordo); display: flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 14px; }

        .feature-card { padding: 28px; border-radius: 16px; border: 1px solid #ece4e6; background: #fff; height: 100%; transition: all .3s; }
        .feature-card:hover { transform: translateY(-4px); box-shadow: 0 12px 40px rgba(128,0,32,.14); border-color: var(--rh-bordo); }
        .feature-icon { width: 58px; height: 58px; border-radius: 16px; background: linear-gradient(135deg, var(--rh-100), var(--rh-200)); color: var(--rh-bordo); display: flex; align-items: center; justify-content: center; font-size: 26px; margin-bottom: 16px; }
        .feature-card ul { padding-left: 1.1rem; margin: 0; color: #6b5c60; font-size: .93rem; }

        .paso-num { width: 52px; height: 52px; border-radius: 50%; background: var(--rh-bordo); color: #fff; font-weight: 800; font-size: 22px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; }

        .landing-dark { background: linear-gradient(135deg, var(--rh-950) 0%, var(--rh-800) 100%); color: #fff; }
        .dark-card { background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.14); border-radius: 16px; padding: 24px; height: 100%; }
        .dark-card i.big { font-size: 30px; color: var(--rh-200); }

        .plan-card { border-radius: 20px; border: 2px solid #ece4e6; background: #fff; overflow: hidden; height: 100%; display: flex; flex-direction: column; transition: all .3s; }
        .plan-card:hover { transform: translateY(-6px); box-shadow: 0 20px 50px rgba(61,10,22,.14); }
        .plan-card.destacado { border-color: var(--rh-bordo); }
        .plan-card.destacado .plan-header { background: var(--rh-bordo); color: #fff; }
        .plan-card.sugerido { border-color: var(--rh-bordo); box-shadow: 0 0 0 4px rgba(128,0,32,.18); }
        .plan-header { padding: 22px; background: var(--rh-50); }
        .plan-price { font-size: 1.9rem; font-weight: 800; line-height: 1; }

        .accordion-button:not(.collapsed) { background: var(--rh-100); color: var(--rh-900); box-shadow: none; }
        .accordion-button:focus { box-shadow: 0 0 0 .2rem rgba(128,0,32,.2); }

        .contact-form { background: #fff; border-radius: 20px; padding: 36px; box-shadow: 0 4px 30px rgba(0,0,0,.06); }
        .contact-form .form-control { border-radius: 10px; border: 2px solid #ece4e6; }
        .contact-form .form-control:focus { border-color: var(--rh-bordo); box-shadow: 0 0 0 4px rgba(128,0,32,.1); }
        .trampa { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }

        .landing-cta { background: linear-gradient(135deg, var(--rh-700) 0%, var(--rh-950) 100%); color: #fff; }
        .landing-footer { background: var(--rh-950); }

        @media (max-width: 575.98px) { .landing-section { padding: 56px 0; } .contact-form { padding: 22px; } }
    </style>
</head>
<body>

{{-- NAVBAR --}}
<nav class="navbar navbar-expand-lg landing-nav fixed-top">
    <div class="container">
        <a class="navbar-brand fs-4" href="#inicio"><i class="bi bi-people-fill me-2"></i>{{ $marca }}</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navLanding" aria-label="Menú">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navLanding">
            <ul class="navbar-nav mx-auto gap-lg-2">
                <li class="nav-item"><a class="nav-link" href="#para-quien">Para quién</a></li>
                <li class="nav-item"><a class="nav-link" href="#funciones">Funciones</a></li>
                <li class="nav-item"><a class="nav-link" href="#portal">Portal del empleado</a></li>
                <li class="nav-item"><a class="nav-link" href="#planes">Planes</a></li>
                <li class="nav-item"><a class="nav-link" href="#faq">Preguntas</a></li>
            </ul>
            <div class="d-flex gap-2 mt-3 mt-lg-0">
                <a href="{{ route('login') }}" class="btn btn-outline-primary rounded-pill px-4">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Ingresar
                </a>
                <a href="#contacto" class="btn btn-primary rounded-pill px-4">
                    <i class="bi bi-calendar-check me-1"></i>Solicitar demo
                </a>
            </div>
        </div>
    </div>
</nav>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-5 shadow"
     style="z-index:9999;width:min(92vw,520px)">
    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-5 shadow"
     style="z-index:9999;width:min(92vw,520px)">
    <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- HERO --}}
<section id="inicio" class="landing-hero">
    <div class="container position-relative" style="z-index:1">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="landing-badge"><i class="bi bi-people me-1"></i>Recursos y capital humano</span>
                <h1 class="mb-4">Todo el personal de tu empresa, <span>en un solo sistema</span></h1>
                <p class="text-white-50 fs-5 mb-4">
                    Legajos, asistencia, licencias, vacaciones y liquidación de sueldos.
                    Con portal de autogestión para que cada empleado vea sus recibos y pida sus licencias.
                </p>
                <div class="d-flex gap-3 flex-wrap">
                    <a href="#contacto" class="btn-rh"><i class="bi bi-calendar-check me-2"></i>Solicitar demo</a>
                    <a href="#funciones" class="btn-rh-outline"><i class="bi bi-grid me-2"></i>Ver funciones</a>
                </div>
                <div class="d-flex flex-wrap gap-4 mt-4 text-white-50 small">
                    <span><i class="bi bi-building me-1"></i>Multiempresa</span>
                    <span><i class="bi bi-shield-lock me-1"></i>Roles y permisos</span>
                    <span><i class="bi bi-phone me-1"></i>Portal del empleado</span>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="mock">
                    <div class="mock-bar"><i></i><i></i><i></i><small>{{ $marca }} · Inicio</small></div>
                    <div class="mock-body">
                        <div class="row g-2 mb-3">
                            <div class="col-4"><div class="mock-kpi"><b>48</b><span>Empleados activos</span></div></div>
                            <div class="col-4"><div class="mock-kpi"><b>3</b><span>Licencias pendientes</span></div></div>
                            <div class="col-4"><div class="mock-kpi"><b>2</b><span>Cumpleaños del mes</span></div></div>
                        </div>
                        <div class="fw-bold mb-1" style="font-size:14px">Asistencia de hoy</div>
                        <table>
                            <thead><tr><th>Empleado</th><th>Área</th><th class="text-end">Estado</th></tr></thead>
                            <tbody>
                                <tr><td>Empleado/a 1</td><td class="text-muted">Administración</td><td class="text-end"><span class="pill pill-ok">Presente</span></td></tr>
                                <tr><td>Empleado/a 2</td><td class="text-muted">Ventas</td><td class="text-end"><span class="pill pill-tar">Tarde</span></td></tr>
                                <tr><td>Empleado/a 3</td><td class="text-muted">Depósito</td><td class="text-end"><span class="pill pill-lic">Licencia</span></td></tr>
                                <tr><td>Empleado/a 4</td><td class="text-muted">Ventas</td><td class="text-end"><span class="pill pill-aus">Ausente</span></td></tr>
                                <tr><td>Empleado/a 5</td><td class="text-muted">Administración</td><td class="text-end"><span class="pill pill-ok">Presente</span></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="mock-caption text-center">Vista de ejemplo con datos ficticios.</div>
            </div>
        </div>
    </div>
</section>

{{-- PARA QUIÉN --}}
<section id="para-quien" class="landing-section">
    <div class="container">
        <div class="text-center mb-5">
            <span class="section-tag">Para quién es</span>
            <h2 class="fw-bold fs-1 mb-3">Cada rol ve lo que necesita</h2>
            <p class="text-muted fs-5 mx-auto" style="max-width:660px">
                {{ $marca }} tiene perfiles distintos para quien administra, quien supervisa y quien trabaja.
            </p>
        </div>
        <div class="row g-4">
            @foreach([
                ['bi-person-gear', 'Administración de RRHH', 'Legajos, estructura, asistencia, licencias y liquidaciones de la empresa, todo desde un mismo lugar.'],
                ['bi-people', 'Supervisores', 'Ven a su equipo y aprueban o rechazan las licencias que piden sus empleados.'],
                ['bi-person-badge', 'Empleados', 'Entran a su portal para ver sus recibos, pedir licencias, consultar su asistencia y actualizar sus datos.'],
            ] as $p)
            <div class="col-md-4">
                <div class="persona-card">
                    <div class="ic"><i class="bi {{ $p[0] }}"></i></div>
                    <h5 class="fw-bold">{{ $p[1] }}</h5>
                    <p class="text-muted mb-0">{{ $p[2] }}</p>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- FUNCIONES --}}
<section id="funciones" class="landing-section bg-rh-fondo">
    <div class="container">
        <div class="text-center mb-5">
            <span class="section-tag">Funciones</span>
            <h2 class="fw-bold fs-1 mb-3">Lo que podés hacer</h2>
        </div>
        <div class="row g-4">
            @foreach([
                ['bi-folder2-open', 'Legajos digitales', ['Datos del empleado y grupo familiar', 'Documentación adjunta', 'Bajas y reincorporaciones']],
                ['bi-diagram-3', 'Estructura de la empresa', ['Áreas y puestos', 'Convenios y categorías', 'Aumentos por convenio']],
                ['bi-clock-history', 'Asistencia y fichadas', ['Fichadas manuales o importadas', 'Horarios y feriados', 'Parte diario y resumen exportable']],
                ['bi-calendar2-check', 'Licencias y vacaciones', ['Tipos de licencia configurables', 'Aprobación, rechazo y certificados', 'Calendario y períodos de vacaciones']],
                ['bi-cash-coin', 'Liquidación de sueldos', ['Conceptos y novedades', 'Cálculo, cierre y reapertura', 'Recibos, impresión y exportación a CSV']],
                ['bi-building-gear', 'Multiempresa y usuarios', ['Alta de empresas', 'Usuarios por empresa', 'Roles: administración, supervisor y empleado']],
            ] as $f)
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon"><i class="bi {{ $f[0] }}"></i></div>
                    <h5 class="fw-bold mb-3">{{ $f[1] }}</h5>
                    <ul>
                        @foreach($f[2] as $li)<li>{{ $li }}</li>@endforeach
                    </ul>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- PORTAL DEL EMPLEADO --}}
<section id="portal" class="landing-section landing-dark">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold fs-1 mb-3">Portal de autogestión del empleado</h2>
            <p class="fs-5 mx-auto" style="max-width:660px;opacity:.85">
                Menos consultas a RRHH: cada persona resuelve lo suyo desde su propio usuario.
            </p>
        </div>
        <div class="row g-4">
            @foreach([
                ['bi-receipt', 'Recibos de sueldo', 'Consulta sus recibos y deja su conformidad.'],
                ['bi-calendar-plus', 'Pedido de licencias', 'Solicita licencias, ve el cálculo de días y adjunta certificados.'],
                ['bi-clock', 'Asistencia y fichadas', 'Revisa su asistencia y ficha desde el portal.'],
                ['bi-person-lines-fill', 'Mis datos', 'Mantiene actualizados sus datos personales.'],
            ] as $c)
            <div class="col-sm-6 col-lg-3">
                <div class="dark-card">
                    <i class="bi {{ $c[0] }} big d-block mb-2"></i>
                    <h6 class="fw-bold">{{ $c[1] }}</h6>
                    <p class="small mb-0" style="opacity:.85">{{ $c[2] }}</p>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- CÓMO FUNCIONA --}}
<section id="como-funciona" class="landing-section">
    <div class="container">
        <div class="text-center mb-5">
            <span class="section-tag">Cómo empezar</span>
            <h2 class="fw-bold fs-1">En tres pasos</h2>
        </div>
        <div class="row g-4 text-center">
            @foreach([
                ['Pedís una demo', 'Nos contás cuántos empleados tiene tu empresa y te sugerimos el plan que corresponde.'],
                ['Damos de alta tu empresa', 'Creamos tu empresa y el usuario administrador para que ingreses.'],
                ['Cargás tu estructura', 'Armás áreas, puestos y legajos, y empezás a registrar asistencia y licencias.'],
            ] as $i => $paso)
            <div class="col-md-4">
                <div class="paso-num">{{ $i + 1 }}</div>
                <h5 class="fw-bold">{{ $paso[0] }}</h5>
                <p class="text-muted mb-0">{{ $paso[1] }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- PLANES --}}
<section id="planes" class="landing-section bg-rh-fondo">
    <div class="container">
        <div class="text-center mb-4">
            <span class="section-tag">Planes</span>
            <h2 class="fw-bold fs-1 mb-3">El plan según el tamaño de tu empresa</h2>
            <p class="text-muted fs-5">Indicá cuántos empleados tenés y te mostramos cuál te corresponde.</p>
        </div>

        <div class="row justify-content-center mb-4">
            <div class="col-md-6 col-lg-4">
                <label for="calcEmpleados" class="form-label fw-semibold text-center d-block">¿Cuántos empleados tiene tu empresa?</label>
                <input type="number" min="1" max="100000" id="calcEmpleados" class="form-control form-control-lg text-center rounded-pill" placeholder="Ej: 25">
                <div id="calcResultado" class="text-center fw-semibold text-rh mt-2" style="min-height:1.5rem"></div>
            </div>
        </div>

        <div class="row g-4 justify-content-center">
            @foreach($planes as $i => $plan)
            <div class="col-sm-6 col-lg-3">
                <div class="plan-card {{ !empty($plan['destacado']) ? 'destacado' : '' }}" data-plan="{{ $i }}">
                    <div class="plan-header">
                        @if(!empty($plan['destacado']))
                            <span class="badge bg-warning text-dark mb-2">Más elegido</span>
                        @endif
                        <h4 class="fw-bold mb-1">{{ $plan['nombre'] }}</h4>
                        <div class="small {{ !empty($plan['destacado']) ? 'text-white-50' : 'text-muted' }}">{{ $rango($plan) }}</div>
                    </div>
                    <div class="p-4 d-flex flex-column flex-grow-1">
                        <div class="mb-3">
                            <span class="plan-price {{ !empty($plan['destacado']) ? 'text-rh' : '' }}">{{ $fmtPrecio($plan['precio'] ?? null) }}</span>
                            @if(!is_null($plan['precio'] ?? null))<span class="text-muted small"> / mes</span>@endif
                        </div>
                        <p class="text-muted small">{{ $plan['descripcion'] }}</p>
                        <ul class="list-unstyled small mb-4">
                            @foreach(['Legajos, asistencia y licencias', 'Liquidación de sueldos y recibos', 'Portal del empleado y supervisores'] as $item)
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>{{ $item }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="btn {{ !empty($plan['destacado']) ? 'btn-primary' : 'btn-outline-primary' }} rounded-pill mt-auto js-plan"
                                data-desde="{{ $plan['desde'] }}">
                            Solicitar demo
                        </button>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- FAQ --}}
<section id="faq" class="landing-section">
    <div class="container">
        <div class="text-center mb-5">
            <span class="section-tag">Preguntas frecuentes</span>
            <h2 class="fw-bold fs-1">Antes de empezar</h2>
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="accordion" id="faqAcc">
                    @foreach([
                        ['¿Cómo accedo al sistema?', 'Solicitás una demo con el formulario de esta página. Damos de alta tu empresa y el usuario administrador y te pasamos el acceso.'],
                        ['¿Cómo se elige el plan?', 'Según la cantidad de empleados de tu empresa. Con el selector de la sección de planes ves cuál te corresponde.'],
                        ['¿Los empleados tienen su propio acceso?', 'Sí. Desde el legajo se crea el acceso al portal, donde cada persona ve sus recibos, pide licencias y consulta su asistencia.'],
                        ['¿Qué roles hay?', 'Administración de RRHH, supervisor y empleado. Cada uno ve y hace solo lo que le corresponde.'],
                        ['¿Puedo exportar la información?', 'Sí: las liquidaciones se exportan a CSV y el resumen de asistencia es exportable.'],
                        ['¿Puedo manejar más de una empresa?', 'El sistema es multiempresa: cada empresa trabaja con su propio personal y sus propios usuarios.'],
                    ] as $i => $q)
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button {{ $i ? 'collapsed' : '' }} fw-semibold" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#faq{{ $i }}">{{ $q[0] }}</button>
                        </h2>
                        <div id="faq{{ $i }}" class="accordion-collapse collapse {{ $i ? '' : 'show' }}" data-bs-parent="#faqAcc">
                            <div class="accordion-body text-muted">{{ $q[1] }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

{{-- CONTACTO / SOLICITAR DEMO --}}
<section id="contacto" class="landing-section bg-rh-fondo">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="text-center mb-5">
                    <span class="section-tag">Solicitar demo</span>
                    <h2 class="fw-bold fs-1 mb-3">Contanos sobre tu empresa</h2>
                    <p class="text-muted fs-5">Te respondemos a la brevedad con el plan sugerido y los próximos pasos.</p>
                </div>
                <div class="contact-form">
                    <form method="POST" action="{{ route('landing.solicitar') }}" novalidate>
                        @csrf
                        @if($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                        </div>
                        @endif
                        <div class="trampa" aria-hidden="true">
                            <label>No completar este campo<input type="text" name="sitio_web" tabindex="-1" autocomplete="off"></label>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Tu nombre <span class="text-danger">*</span></label>
                                <input type="text" name="nombre" class="form-control form-control-lg" value="{{ old('nombre') }}" placeholder="Nombre y apellido" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Empresa <span class="text-danger">*</span></label>
                                <input type="text" name="empresa" class="form-control form-control-lg" value="{{ old('empresa') }}" placeholder="Razón social o nombre" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control form-control-lg" value="{{ old('email') }}" placeholder="tu@empresa.com" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Teléfono</label>
                                <input type="text" name="telefono" class="form-control form-control-lg" value="{{ old('telefono') }}" placeholder="Ej: 2284-123456">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Cantidad de empleados <span class="text-danger">*</span></label>
                                <input type="number" min="1" max="100000" name="empleados" id="formEmpleados" class="form-control form-control-lg" value="{{ old('empleados') }}" placeholder="Ej: 25" required>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <div id="formPlan" class="fw-semibold text-rh pb-2" style="min-height:1.5rem"></div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Mensaje</label>
                                <textarea name="mensaje" class="form-control form-control-lg" rows="4" placeholder="¿Qué necesitás resolver? (opcional)">{{ old('mensaje') }}</textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary btn-lg w-100 rounded-pill">
                                    <i class="bi bi-send me-2"></i>Solicitar demo
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- CTA FINAL --}}
<section class="landing-cta text-center" style="padding:64px 0">
    <div class="container">
        <h2 class="fw-bold mb-3">Ordená el personal de tu empresa</h2>
        <p class="fs-5 mb-4" style="opacity:.9">Pedí una demo y mirá {{ $marca }} funcionando con tu estructura.</p>
        <div class="d-flex flex-wrap justify-content-center gap-3">
            <a href="#contacto" class="btn btn-light btn-lg px-5 fw-bold" style="color:var(--rh-bordo)"><i class="bi bi-calendar-check me-2"></i>Solicitar demo</a>
            <a href="{{ route('login') }}" class="btn btn-outline-light btn-lg px-4"><i class="bi bi-box-arrow-in-right me-2"></i>Ingresar</a>
        </div>
    </div>
</section>

{{-- FOOTER --}}
<footer class="landing-footer py-4">
    <div class="container">
        <div class="row align-items-center gy-2">
            <div class="col-md-6 text-white">
                <i class="bi bi-people-fill me-2" style="color:var(--rh-200)"></i>
                <strong>{{ $marca }}</strong> &copy; {{ date('Y') }}
            </div>
            <div class="col-md-6 text-md-end">
                <a href="{{ route('login') }}" class="text-white-50 text-decoration-none me-3"><i class="bi bi-box-arrow-in-right me-1"></i>Ingresar</a>
                <a href="#contacto" class="text-white-50 text-decoration-none"><i class="bi bi-envelope me-1"></i>Contacto</a>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    const planes = @json($planes);
    const calc = document.getElementById('calcEmpleados');
    const calcRes = document.getElementById('calcResultado');
    const formEmp = document.getElementById('formEmpleados');
    const formPlan = document.getElementById('formPlan');
    const cards = document.querySelectorAll('.plan-card');

    function planPara(n) {
        n = parseInt(n, 10);
        if (!n || n < 1) return -1;
        return planes.findIndex(p => n >= p.desde && (p.hasta === null || n <= p.hasta));
    }

    function marcar(idx) {
        cards.forEach(c => c.classList.toggle('sugerido', parseInt(c.dataset.plan, 10) === idx));
    }

    function actualizar(valor) {
        const idx = planPara(valor);
        marcar(idx);
        const txt = idx >= 0 ? 'Plan sugerido: ' + planes[idx].nombre : '';
        calcRes.textContent = txt;
        formPlan.textContent = txt;
    }

    calc.addEventListener('input', () => { formEmp.value = calc.value; actualizar(calc.value); });
    formEmp.addEventListener('input', () => { calc.value = formEmp.value; actualizar(formEmp.value); });

    document.querySelectorAll('.js-plan').forEach(btn => {
        btn.addEventListener('click', () => {
            if (!formEmp.value) {
                formEmp.value = btn.dataset.desde;
                calc.value = btn.dataset.desde;
            }
            actualizar(formEmp.value);
            document.getElementById('contacto').scrollIntoView({ behavior: 'smooth' });
        });
    });

    if (formEmp.value) { calc.value = formEmp.value; actualizar(formEmp.value); }
})();
</script>
</body>
</html>
