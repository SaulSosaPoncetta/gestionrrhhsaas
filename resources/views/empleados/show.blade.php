@extends('layouts.app')

@section('title', 'Legajo ' . $empleado->legajo)

@section('content')
@php($e = $empleado)
@php($dato = fn ($v) => filled($v) ? e($v) : '<span class="text-muted">—</span>')

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
    </div>
@endif

{{-- ENCABEZADO --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body d-flex flex-wrap gap-3 align-items-center">
        @if($e->foto_url)
            <img src="{{ $e->foto_url }}" alt="" width="80" height="80" class="rounded-circle object-fit-cover">
        @else
            <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary bg-opacity-10 text-primary fw-semibold fs-4"
                  style="width:80px;height:80px">{{ $e->iniciales }}</span>
        @endif

        <div class="flex-grow-1">
            <div class="small text-muted">Legajo N° {{ $e->legajo }}</div>
            <h2 class="h4 mb-1">{{ $e->nombre_completo }}</h2>
            <div class="text-muted">
                {{ $e->puesto?->nombre ?? 'Sin puesto' }}
                @if($e->area) · {{ $e->area->nombre }} @endif
            </div>
            <div class="mt-1">
                @if($e->activo)
                    <span class="badge text-bg-success">Activo</span>
                    <span class="small text-muted ms-1">Antigüedad: {{ $e->antiguedad }}</span>
                @else
                    <span class="badge text-bg-secondary">Baja {{ $e->fecha_egreso->format('d/m/Y') }}</span>
                    <span class="small text-muted ms-1">{{ $e->motivo_egreso_nombre }} · trabajó {{ $e->antiguedad }}</span>
                @endif
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('empleados.edit', $e) }}" class="btn btn-primary"><i class="bi bi-pencil me-1"></i>Editar</a>
            <a href="{{ route('asistencia.empleado', $e) }}" class="btn btn-outline-primary"><i class="bi bi-calendar-check me-1"></i>Asistencia</a>
            @if($e->activo)
                <button class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalBaja">
                    <i class="bi bi-person-dash me-1"></i>Dar de baja
                </button>
            @else
                <form method="POST" action="{{ route('empleados.reincorporar', $e) }}"
                      onsubmit="return confirm('¿Reincorporar al empleado? Se borra la fecha y el motivo de egreso.')">
                    @csrf @method('PATCH')
                    <button class="btn btn-outline-success"><i class="bi bi-arrow-counterclockwise me-1"></i>Reincorporar</button>
                </form>
            @endif
            <div class="dropdown">
                <button class="btn btn-outline-secondary" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <form method="POST" action="{{ route('empleados.destroy', $e) }}"
                              onsubmit="return confirm('¿Eliminar el legajo de {{ addslashes($e->nombre_completo) }}? Esta acción es para legajos cargados por error; para egresos usá «Dar de baja».')">
                            @csrf @method('DELETE')
                            <button class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Eliminar legajo</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

{{-- ACCESO AL PORTAL --}}
@if(session('acceso'))
    <div class="alert alert-success">
        <div class="fw-semibold mb-1"><i class="bi bi-key me-1"></i>{{ session('acceso')['nuevo'] ? 'Acceso creado' : 'Contraseña restablecida' }}. Entregale estos datos al empleado:</div>
        <div class="font-monospace">Usuario: {{ session('acceso')['email'] }}<br>Contraseña temporal: <strong>{{ session('acceso')['password'] }}</strong></div>
        <div class="small mt-1">No se vuelve a mostrar. Al ingresar, el sistema le va a pedir que elija una contraseña propia.</div>
    </div>
@endif

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body d-flex flex-wrap gap-3 align-items-center py-2">
        <i class="bi bi-phone fs-4 text-primary"></i>
        @if($e->usuario)
            <div class="flex-grow-1">
                <div class="fw-semibold">
                    Portal de autogestión
                    <span class="badge {{ $e->usuario->activo ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $e->usuario->activo ? 'Activo' : 'Desactivado' }}</span>
                    <span class="badge text-bg-light">{{ $e->usuario->rol_nombre }}</span>
                    @if($e->permite_fichaje_web)<span class="badge text-bg-info">Ficha por web</span>@endif
                </div>
                <div class="small text-muted">
                    {{ $e->usuario->email }} ·
                    {{ $e->usuario->ultimo_acceso ? 'último ingreso ' . $e->usuario->ultimo_acceso->format('d/m/Y H:i') : 'todavía no ingresó' }}
                    @if($e->usuario->debe_cambiar_password) · <span class="text-warning">con contraseña temporal</span>@endif
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalAccesoEditar"><i class="bi bi-pencil me-1"></i>Editar</button>
                <form method="POST" action="{{ route('empleados.acceso.resetear', $e) }}"
                      onsubmit="return confirm('Se va a generar una contraseña temporal nueva. ¿Continuar?')">
                    @csrf
                    <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-counterclockwise me-1"></i>Restablecer contraseña</button>
                </form>
                <form method="POST" action="{{ route('empleados.acceso.quitar', $e) }}"
                      onsubmit="return confirm('¿Quitar el acceso al portal? El usuario queda desactivado.')">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-person-x me-1"></i>Quitar acceso</button>
                </form>
            </div>
        @else
            <div class="flex-grow-1">
                <div class="fw-semibold">Portal de autogestión</div>
                <div class="small text-muted">Sin acceso. Con el portal puede ver sus recibos, pedir licencias y consultar su asistencia.</div>
            </div>
            @if($e->activo)
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalAccesoCrear"><i class="bi bi-person-plus me-1"></i>Dar acceso</button>
            @endif
        @endif
    </div>
</div>

@if(! $e->usuario && $e->activo)
<div class="modal fade" id="modalAccesoCrear" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('empleados.acceso.crear', $e) }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Dar acceso al portal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <div class="col-12">
                    <label class="form-label">Email para ingresar *</label>
                    <input type="email" name="email" value="{{ old('email', $e->email) }}" class="form-control @error('email') is-invalid @enderror" required>
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label">Perfil</label>
                    <select name="rol" class="form-select">
                        <option value="empleado">Empleado</option>
                        <option value="supervisor" @selected($e->aCargo->isNotEmpty())>Supervisor (aprueba licencias de su personal a cargo)</option>
                    </select>
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input type="checkbox" name="permite_fichaje_web" value="1" id="fichaje_web" class="form-check-input">
                        <label for="fichaje_web" class="form-check-label">Puede fichar entrada y salida desde el portal</label>
                    </div>
                </div>
                <div class="col-12 small text-muted">Se genera una contraseña temporal que vas a ver una sola vez.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary">Crear acceso</button>
            </div>
        </form>
    </div>
</div>
@endif

@if($e->usuario)
<div class="modal fade" id="modalAccesoEditar" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('empleados.acceso.actualizar', $e) }}" class="modal-content">
            @csrf @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title">Acceso al portal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <div class="col-12">
                    <label class="form-label">Email para ingresar *</label>
                    <input type="email" name="email" value="{{ old('email', $e->usuario->email) }}" class="form-control" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Perfil</label>
                    <select name="rol" class="form-select">
                        <option value="empleado" @selected($e->usuario->rol === 'empleado')>Empleado</option>
                        <option value="supervisor" @selected($e->usuario->rol === 'supervisor')>Supervisor</option>
                    </select>
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input type="checkbox" name="permite_fichaje_web" value="1" id="fichaje_web_e" class="form-check-input" @checked($e->permite_fichaje_web)>
                        <label for="fichaje_web_e" class="form-check-label">Puede fichar desde el portal</label>
                    </div>
                    <div class="form-check form-switch">
                        <input type="checkbox" name="activo" value="1" id="acceso_activo" class="form-check-input" @checked($e->usuario->activo)>
                        <label for="acceso_activo" class="form-check-label">Acceso activo</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- PESTAÑAS --}}
<ul class="nav nav-tabs" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#datos" type="button">Datos personales</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#laboral" type="button">Laboral</button></li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#familia" type="button">
            Familia <span class="badge text-bg-light">{{ $e->familiares->count() }}</span>
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#documentos" type="button">
            Documentos <span class="badge text-bg-light">{{ $e->documentos->count() }}</span>
            @if($e->documentos->contains(fn ($d) => in_array($d->estado_vencimiento, ['vencido', 'por_vencer'])))
                <i class="bi bi-exclamation-triangle-fill text-warning"></i>
            @endif
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#licencias" type="button">
            Licencias
            @if($licencias->contains(fn ($l) => $l->esPendiente()))<span class="badge text-bg-warning">pend.</span>@endif
        </button>
    </li>
</ul>

<div class="tab-content bg-white border border-top-0 rounded-bottom shadow-sm p-3 p-md-4 mb-4">

    {{-- DATOS PERSONALES --}}
    <div class="tab-pane fade show active" id="datos">
        <div class="row g-4">
            <div class="col-lg-6">
                <h3 class="h6 text-uppercase text-muted mb-3">Identificación</h3>
                <dl class="row mb-0">
                    <dt class="col-5 fw-normal text-muted">CUIL</dt><dd class="col-7">{!! $dato($e->cuil) !!}</dd>
                    <dt class="col-5 fw-normal text-muted">DNI</dt><dd class="col-7">{!! $dato($e->dni) !!}</dd>
                    <dt class="col-5 fw-normal text-muted">Fecha de nacimiento</dt>
                    <dd class="col-7">
                        @if($e->fecha_nacimiento){{ $e->fecha_nacimiento->format('d/m/Y') }} <span class="text-muted">({{ $e->edad }} años)</span>
                        @else<span class="text-muted">—</span>@endif
                    </dd>
                    <dt class="col-5 fw-normal text-muted">Sexo</dt><dd class="col-7">{!! $dato($e->sexo_nombre) !!}</dd>
                    <dt class="col-5 fw-normal text-muted">Estado civil</dt><dd class="col-7">{!! $dato($e->estado_civil_nombre) !!}</dd>
                    <dt class="col-5 fw-normal text-muted">Nacionalidad</dt><dd class="col-7">{!! $dato($e->nacionalidad) !!}</dd>
                </dl>
            </div>
            <div class="col-lg-6">
                <h3 class="h6 text-uppercase text-muted mb-3">Contacto</h3>
                <dl class="row mb-0">
                    <dt class="col-5 fw-normal text-muted">Domicilio</dt>
                    <dd class="col-7">{!! $dato(collect([$e->domicilio, $e->localidad, $e->provincia, $e->codigo_postal ? 'CP ' . $e->codigo_postal : null])->filter()->implode(', ')) !!}</dd>
                    <dt class="col-5 fw-normal text-muted">Teléfono</dt><dd class="col-7">{!! $dato($e->telefono) !!}</dd>
                    <dt class="col-5 fw-normal text-muted">Email</dt>
                    <dd class="col-7">@if($e->email)<a href="mailto:{{ $e->email }}">{{ $e->email }}</a>@else<span class="text-muted">—</span>@endif</dd>
                    <dt class="col-5 fw-normal text-muted">Emergencia</dt>
                    <dd class="col-7">{!! $dato(collect([$e->contacto_emergencia, $e->telefono_emergencia])->filter()->implode(' · ')) !!}</dd>
                </dl>
            </div>
            @if($e->observaciones)
                <div class="col-12">
                    <h3 class="h6 text-uppercase text-muted mb-2">Observaciones</h3>
                    <p class="mb-0" style="white-space: pre-line">{{ $e->observaciones }}</p>
                </div>
            @endif
        </div>
    </div>

    {{-- LABORAL --}}
    <div class="tab-pane fade" id="laboral">
        <div class="row g-4">
            <div class="col-lg-6">
                <h3 class="h6 text-uppercase text-muted mb-3">Relación laboral</h3>
                <dl class="row mb-0">
                    <dt class="col-5 fw-normal text-muted">Ingreso</dt><dd class="col-7">{{ $e->fecha_ingreso->format('d/m/Y') }}</dd>
                    @unless($e->activo)
                        <dt class="col-5 fw-normal text-muted">Egreso</dt>
                        <dd class="col-7">{{ $e->fecha_egreso->format('d/m/Y') }} · {{ $e->motivo_egreso_nombre }}</dd>
                    @endunless
                    <dt class="col-5 fw-normal text-muted">Antigüedad</dt><dd class="col-7">{{ $e->antiguedad }}</dd>
                    <dt class="col-5 fw-normal text-muted">Modalidad</dt><dd class="col-7">{{ $e->modalidad_nombre }}</dd>
                    <dt class="col-5 fw-normal text-muted">Jornada</dt>
                    <dd class="col-7">{{ $e->jornada_nombre }}@if($e->horas_semanales) · {{ rtrim(rtrim(number_format($e->horas_semanales, 2, ',', '.'), '0'), ',') }} hs/sem @endif</dd>
                    <dt class="col-5 fw-normal text-muted">Horario</dt>
                    <dd class="col-7">
                        @if($e->horario)
                            {{ $e->horario->nombre }}
                            <div class="small text-muted">{{ $e->horario->resumen }}</div>
                        @else
                            <span class="text-muted">Sin horario asignado</span>
                        @endif
                    </dd>
                    <dt class="col-5 fw-normal text-muted">Área</dt><dd class="col-7">{!! $dato($e->area?->ruta) !!}</dd>
                    <dt class="col-5 fw-normal text-muted">Puesto</dt><dd class="col-7">{!! $dato($e->puesto?->nombre) !!}</dd>
                    <dt class="col-5 fw-normal text-muted">Supervisor</dt>
                    <dd class="col-7">
                        @if($e->supervisor)<a href="{{ route('empleados.show', $e->supervisor) }}">{{ $e->supervisor->nombre_completo }}</a>
                        @else<span class="text-muted">—</span>@endif
                    </dd>
                    @if($e->aCargo->isNotEmpty())
                        <dt class="col-5 fw-normal text-muted">Personal a cargo</dt>
                        <dd class="col-7">
                            @foreach($e->aCargo as $sub)
                                <a href="{{ route('empleados.show', $sub) }}" class="d-block">{{ $sub->nombre_completo }}</a>
                            @endforeach
                        </dd>
                    @endif
                </dl>
            </div>
            <div class="col-lg-6">
                <h3 class="h6 text-uppercase text-muted mb-3">Remuneración</h3>
                <dl class="row mb-4">
                    <dt class="col-5 fw-normal text-muted">Convenio</dt><dd class="col-7">{{ $e->convenio?->nombre_completo ?? 'Fuera de convenio' }}</dd>
                    @if($e->categoria)
                        <dt class="col-5 fw-normal text-muted">Categoría</dt><dd class="col-7">{{ $e->categoria->nombre }}</dd>
                    @endif
                    <dt class="col-5 fw-normal text-muted">Básico vigente</dt>
                    <dd class="col-7 fw-semibold">$ {{ number_format($e->basico_vigente, 2, ',', '.') }}</dd>
                </dl>

                <h3 class="h6 text-uppercase text-muted mb-3">Cobro y obra social</h3>
                <dl class="row mb-0">
                    <dt class="col-5 fw-normal text-muted">Banco</dt><dd class="col-7">{!! $dato($e->banco) !!}</dd>
                    <dt class="col-5 fw-normal text-muted">CBU</dt><dd class="col-7 font-monospace">{!! $dato($e->cbu) !!}</dd>
                    <dt class="col-5 fw-normal text-muted">Obra social</dt>
                    <dd class="col-7">{!! $dato(collect([$e->obra_social, $e->codigo_obra_social ? 'RNOS ' . $e->codigo_obra_social : null])->filter()->implode(' · ')) !!}</dd>
                </dl>
            </div>
        </div>
    </div>

    {{-- FAMILIA --}}
    <div class="tab-pane fade" id="familia">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="h6 text-uppercase text-muted mb-0">Grupo familiar</h3>
            <button class="btn btn-sm btn-primary" onclick="abrirFamiliar()"><i class="bi bi-plus-lg me-1"></i>Agregar familiar</button>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Parentesco</th><th>Apellido y nombre</th><th>DNI</th><th>Nacimiento</th>
                        <th class="text-center">A cargo</th><th class="text-center">Discapacidad</th><th class="text-end"></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($e->familiares as $f)
                    <tr>
                        <td>{{ $f->parentesco_nombre }}</td>
                        <td class="fw-semibold">{{ $f->apellido_nombre }}</td>
                        <td>{{ $f->dni ?? '—' }}</td>
                        <td>{{ $f->fecha_nacimiento?->format('d/m/Y') ?? '—' }}@if($f->edad !== null) <span class="text-muted small">({{ $f->edad }})</span>@endif</td>
                        <td class="text-center">{!! $f->a_cargo ? '<i class="bi bi-check-lg text-success"></i>' : '—' !!}</td>
                        <td class="text-center">{!! $f->discapacidad ? '<i class="bi bi-check-lg text-success"></i>' : '—' !!}</td>
                        <td class="text-end text-nowrap">
                            <button class="btn btn-sm btn-outline-primary" title="Editar"
                                    data-familiar="{{ json_encode([
                                        'url'              => route('empleados.familiares.update', [$e, $f]),
                                        'parentesco'       => $f->parentesco,
                                        'apellido_nombre'  => $f->apellido_nombre,
                                        'dni'              => $f->dni,
                                        'fecha_nacimiento' => $f->fecha_nacimiento?->format('Y-m-d'),
                                        'a_cargo'          => $f->a_cargo,
                                        'discapacidad'     => $f->discapacidad,
                                    ]) }}"
                                    onclick="abrirFamiliar(JSON.parse(this.dataset.familiar))"><i class="bi bi-pencil"></i></button>
                            <form method="POST" action="{{ route('empleados.familiares.destroy', [$e, $f]) }}" class="d-inline"
                                  onsubmit="return confirm('¿Eliminar a {{ addslashes($f->apellido_nombre) }}?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No hay familiares cargados.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- DOCUMENTOS --}}
    <div class="tab-pane fade" id="documentos">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="h6 text-uppercase text-muted mb-0">Documentación</h3>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalDocumento">
                <i class="bi bi-upload me-1"></i>Subir documento
            </button>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Tipo</th><th>Archivo</th><th class="d-none d-md-table-cell">Subido</th><th>Vencimiento</th><th class="text-end"></th></tr>
                </thead>
                <tbody>
                @forelse($e->documentos as $doc)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $doc->tipo_nombre }}</div>
                            @if($doc->descripcion)<div class="small text-muted">{{ $doc->descripcion }}</div>@endif
                        </td>
                        <td>
                            <a href="{{ route('empleados.documentos.ver', [$e, $doc]) }}" target="_blank" class="text-decoration-none">
                                <i class="bi bi-file-earmark me-1"></i>{{ \Illuminate\Support\Str::limit($doc->nombre_original, 40) }}
                            </a>
                            <div class="small text-muted">{{ $doc->tamanio_legible }}</div>
                        </td>
                        <td class="d-none d-md-table-cell small text-muted">
                            {{ $doc->created_at->format('d/m/Y') }}<br>{{ $doc->subidoPor?->name }}
                        </td>
                        <td>
                            @if($doc->vencimiento)
                                {{ $doc->vencimiento->format('d/m/Y') }}
                                @if($doc->estado_vencimiento === 'vencido')
                                    <span class="badge text-bg-danger">Vencido</span>
                                @elseif($doc->estado_vencimiento === 'por_vencer')
                                    <span class="badge text-bg-warning">Por vencer</span>
                                @endif
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('empleados.documentos.descargar', [$e, $doc]) }}" class="btn btn-sm btn-outline-secondary" title="Descargar">
                                <i class="bi bi-download"></i>
                            </a>
                            <form method="POST" action="{{ route('empleados.documentos.destroy', [$e, $doc]) }}" class="d-inline"
                                  onsubmit="return confirm('¿Eliminar este documento? El archivo se borra definitivamente.')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No hay documentos cargados.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- LICENCIAS --}}
    <div class="tab-pane fade" id="licencias">
        <div class="row g-3 mb-3">
            <div class="col-md-8">
                <div class="d-flex flex-wrap gap-3 align-items-center border rounded p-3 h-100">
                    <div>
                        <div class="small text-muted">Vacaciones {{ $periodoVacaciones }}</div>
                        <div class="fs-3 fw-semibold {{ $saldoVacaciones['saldo'] < 0 ? 'text-danger' : 'text-success' }}">
                            {{ $saldoVacaciones['saldo'] }} <span class="fs-6 fw-normal text-muted">días de saldo</span>
                        </div>
                    </div>
                    <div class="small text-muted">
                        Corresponden {{ $saldoVacaciones['corresponden'] }}
                        @if($saldoVacaciones['ajuste']) · ajuste {{ $saldoVacaciones['ajuste'] > 0 ? '+' : '' }}{{ $saldoVacaciones['ajuste'] }} @endif
                        · tomados {{ $saldoVacaciones['tomados'] }}
                        @if($saldoVacaciones['pendientes']) · <span class="text-warning">{{ $saldoVacaciones['pendientes'] }} pendientes</span>@endif
                        <br>Antigüedad al 31/12: {{ $saldoVacaciones['calculado']['antiguedad'] }}
                    </div>
                    <a href="{{ route('vacaciones.index', ['anio' => $periodoVacaciones]) }}" class="small ms-auto">Ver vacaciones</a>
                </div>
            </div>
            <div class="col-md-4 d-flex align-items-center justify-content-md-end">
                @if($e->activo)
                    <a href="{{ route('licencias.create', ['empleado_id' => $e->id]) }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Nueva licencia</a>
                @endif
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Tipo</th><th>Período</th><th class="text-center">Días</th><th class="text-center">Estado</th><th class="text-end"></th></tr>
                </thead>
                <tbody>
                @forelse($licencias as $lic)
                    <tr>
                        <td>
                            <span class="badge text-bg-{{ $lic->tipo->color }}">{{ $lic->tipo->nombre }}</span>
                            @if($lic->periodo)<span class="small text-muted">período {{ $lic->periodo }}</span>@endif
                        </td>
                        <td class="text-nowrap">{{ $lic->periodo_texto }}</td>
                        <td class="text-center">{{ $lic->dias }}</td>
                        <td class="text-center">
                            <span class="badge text-bg-{{ $lic->estado_color }}">{{ $lic->estado_nombre }}</span>
                            @if($lic->faltaCertificado())<i class="bi bi-exclamation-triangle text-warning" title="Falta el certificado"></i>@endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('licencias.edit', $lic) }}" class="btn btn-sm btn-outline-primary" title="Ver / editar"><i class="bi bi-pencil"></i></a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No tiene licencias registradas.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($licencias->count() >= 15)
            <a href="{{ route('licencias.index', ['empleado_id' => $e->id]) }}" class="small d-inline-block mt-2">Ver todas las licencias</a>
        @endif
    </div>
</div>

{{-- MODAL BAJA --}}
@if($e->activo)
<div class="modal fade" id="modalBaja" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('empleados.baja', $e) }}" class="modal-content">
            @csrf @method('PATCH')
            <div class="modal-header">
                <h5 class="modal-title">Registrar baja de {{ $e->nombre_completo }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <div class="col-md-5">
                    <label class="form-label">Fecha de egreso *</label>
                    <input type="date" name="fecha_egreso" value="{{ now()->format('Y-m-d') }}" class="form-control" required>
                </div>
                <div class="col-md-7">
                    <label class="form-label">Motivo *</label>
                    <select name="motivo_egreso" class="form-select" required>
                        <option value="">— Seleccionar —</option>
                        @foreach($motivosEgreso as $k => $v)
                            <option value="{{ $k }}">{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 small text-muted">
                    El legajo queda guardado. Si el empleado tiene usuario de autogestión, se desactiva.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-warning">Registrar baja</button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- MODAL FAMILIAR (alta y edición) --}}
<div class="modal fade" id="modalFamiliar" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('empleados.familiares.store', $e) }}" id="formFamiliar" class="modal-content">
            @csrf
            <input type="hidden" name="_method" id="familiarMetodo" value="POST">
            <div class="modal-header">
                <h5 class="modal-title" id="familiarTitulo">Agregar familiar</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <div class="col-md-5">
                    <label class="form-label">Parentesco *</label>
                    <select name="parentesco" class="form-select" required>
                        <option value="">— Seleccionar —</option>
                        @foreach($parentescos as $k => $v)
                            <option value="{{ $k }}">{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-7">
                    <label class="form-label">Apellido y nombre *</label>
                    <input type="text" name="apellido_nombre" class="form-control" required>
                </div>
                <div class="col-md-5">
                    <label class="form-label">DNI</label>
                    <input type="text" name="dni" class="form-control" maxlength="8" inputmode="numeric">
                </div>
                <div class="col-md-7">
                    <label class="form-label">Fecha de nacimiento</label>
                    <input type="date" name="fecha_nacimiento" class="form-control">
                </div>
                <div class="col-md-6">
                    <div class="form-check form-switch">
                        <input type="checkbox" name="a_cargo" value="1" id="a_cargo" class="form-check-input" checked>
                        <label for="a_cargo" class="form-check-label">A cargo</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check form-switch">
                        <input type="checkbox" name="discapacidad" value="1" id="discapacidad" class="form-check-input">
                        <label for="discapacidad" class="form-check-label">Con discapacidad</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

{{-- MODAL DOCUMENTO --}}
<div class="modal fade" id="modalDocumento" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('empleados.documentos.store', $e) }}" enctype="multipart/form-data" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Subir documento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <div class="col-12">
                    <label class="form-label">Tipo *</label>
                    <select name="tipo" class="form-select" required>
                        <option value="">— Seleccionar —</option>
                        @foreach($tiposDocumento as $k => $v)
                            <option value="{{ $k }}">{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Descripción</label>
                    <input type="text" name="descripcion" class="form-control" maxlength="255">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Vencimiento</label>
                    <input type="date" name="vencimiento" class="form-control">
                </div>
                <div class="col-12">
                    <label class="form-label">Archivo * <span class="text-muted small">(PDF, imagen, Word o Excel · máx. 10 MB)</span></label>
                    <input type="file" name="archivo" class="form-control" required
                           accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary"><i class="bi bi-upload me-1"></i>Subir</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Abrir la pestaña indicada en la URL (#familia, #documentos...)
(function () {
    const hash = window.location.hash;
    if (hash) {
        const btn = document.querySelector(`[data-bs-target="${hash}"]`);
        if (btn) bootstrap.Tab.getOrCreateInstance(btn).show();
    }
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(btn => {
        btn.addEventListener('shown.bs.tab', e => history.replaceState(null, '', e.target.dataset.bsTarget));
    });
})();

// Modal de familiar: vacío para alta, cargado para edición
const urlAltaFamiliar = @json(route('empleados.familiares.store', $e));
function abrirFamiliar(datos) {
    const form = document.getElementById('formFamiliar');
    form.reset();
    form.action = datos ? datos.url : urlAltaFamiliar;
    document.getElementById('familiarMetodo').value = datos ? 'PUT' : 'POST';
    document.getElementById('familiarTitulo').textContent = datos ? 'Editar familiar' : 'Agregar familiar';
    if (datos) {
        form.parentesco.value = datos.parentesco ?? '';
        form.apellido_nombre.value = datos.apellido_nombre ?? '';
        form.dni.value = datos.dni ?? '';
        form.fecha_nacimiento.value = datos.fecha_nacimiento ?? '';
        form.a_cargo.checked = !!datos.a_cargo;
        form.discapacidad.checked = !!datos.discapacidad;
    }
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalFamiliar')).show();
}
</script>
@endpush
