@extends('layouts.app')

@section('title', 'Mis datos')

@section('content')
@php
    $e = $empleado;
@endphp

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">Datos del legajo</div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 fw-normal text-muted">Legajo</dt><dd class="col-7">{{ $e->legajo }}</dd>
                    <dt class="col-5 fw-normal text-muted">Apellido y nombre</dt><dd class="col-7">{{ $e->nombre_completo }}</dd>
                    <dt class="col-5 fw-normal text-muted">CUIL</dt><dd class="col-7">{{ $e->cuil }}</dd>
                    <dt class="col-5 fw-normal text-muted">Ingreso</dt><dd class="col-7">{{ $e->fecha_ingreso->format('d/m/Y') }} ({{ $e->antiguedad }})</dd>
                    <dt class="col-5 fw-normal text-muted">Área / puesto</dt><dd class="col-7">{{ $e->area?->nombre ?? '—' }} · {{ $e->puesto?->nombre ?? '—' }}</dd>
                    <dt class="col-5 fw-normal text-muted">Categoría</dt><dd class="col-7">{{ $e->categoria?->nombre ?? 'Fuera de convenio' }}</dd>
                    <dt class="col-5 fw-normal text-muted">Horario</dt><dd class="col-7">{{ $e->horario?->resumen ?? '—' }}</dd>
                    <dt class="col-5 fw-normal text-muted">Supervisor</dt><dd class="col-7">{{ $e->supervisor?->nombre_completo ?? '—' }}</dd>
                    <dt class="col-5 fw-normal text-muted">Obra social</dt><dd class="col-7">{{ $e->obra_social ?? '—' }}</dd>
                    <dt class="col-5 fw-normal text-muted">Cuenta sueldo</dt><dd class="col-7">{{ $e->banco ?? '—' }}{{ $e->cbu ? ' · CBU ' . substr($e->cbu, 0, 4) . '…' . substr($e->cbu, -4) : '' }}</dd>
                </dl>
            </div>
            <div class="card-footer bg-white small text-muted">Para corregir alguno de estos datos, hablá con RRHH.</div>
        </div>

        @if($e->familiares->isNotEmpty())
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">Grupo familiar</div>
                <ul class="list-group list-group-flush small">
                    @foreach($e->familiares as $f)
                        <li class="list-group-item d-flex justify-content-between"><span>{{ $f->apellido_nombre }}</span><span class="text-muted">{{ $f->parentesco_nombre }}</span></li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    <div class="col-lg-7">
        <form method="POST" action="{{ route('portal.datos.update') }}" class="card border-0 shadow-sm">
            @csrf @method('PUT')
            <div class="card-header bg-white fw-semibold">Contacto (lo podés actualizar vos)</div>
            <div class="card-body row g-3">
                <div class="col-md-8">
                    <label class="form-label">Domicilio</label>
                    <input type="text" name="domicilio" value="{{ old('domicilio', $e->domicilio) }}" class="form-control" maxlength="255">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Código postal</label>
                    <input type="text" name="codigo_postal" value="{{ old('codigo_postal', $e->codigo_postal) }}" class="form-control" maxlength="10">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Localidad</label>
                    <input type="text" name="localidad" value="{{ old('localidad', $e->localidad) }}" class="form-control" maxlength="255">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Provincia</label>
                    <input type="text" name="provincia" value="{{ old('provincia', $e->provincia) }}" class="form-control" maxlength="255">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Teléfono</label>
                    <input type="text" name="telefono" value="{{ old('telefono', $e->telefono) }}" class="form-control" maxlength="50">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email personal</label>
                    <input type="email" name="email" value="{{ old('email', $e->email) }}" class="form-control @error('email') is-invalid @enderror" maxlength="255">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Contacto de emergencia</label>
                    <input type="text" name="contacto_emergencia" value="{{ old('contacto_emergencia', $e->contacto_emergencia) }}" class="form-control" maxlength="255" placeholder="Nombre y vínculo">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Teléfono de emergencia</label>
                    <input type="text" name="telefono_emergencia" value="{{ old('telefono_emergencia', $e->telefono_emergencia) }}" class="form-control" maxlength="50">
                </div>
            </div>
            <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                <a href="{{ route('password.editar') }}" class="small"><i class="bi bi-key me-1"></i>Cambiar contraseña</a>
                <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
            </div>
        </form>
    </div>
</div>
@endsection
