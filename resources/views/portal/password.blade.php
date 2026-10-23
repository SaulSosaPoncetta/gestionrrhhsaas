@extends('layouts.app')

@section('title', 'Cambiar contraseña')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-7 col-lg-5">
        @if($obligatorio)
            <div class="alert alert-info small">
                <i class="bi bi-shield-lock me-1"></i>Estás usando una contraseña temporal. Elegí una propia para seguir.
            </div>
        @endif

        <form method="POST" action="{{ route('password.actualizar') }}" class="card border-0 shadow-sm">
            @csrf @method('PUT')
            <div class="card-body">
                @if($errors->any())
                    <div class="alert alert-danger py-2 small">
                        <ul class="mb-0">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
                    </div>
                @endif

                <div class="mb-3">
                    <label class="form-label">Contraseña actual{{ $obligatorio ? ' (la temporal)' : '' }}</label>
                    <input type="password" name="actual" class="form-control @error('actual') is-invalid @enderror" required autocomplete="current-password">
                </div>
                <div class="mb-3">
                    <label class="form-label">Contraseña nueva</label>
                    <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required autocomplete="new-password">
                    <div class="form-text">Al menos 8 caracteres, con letras y números.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Repetir contraseña nueva</label>
                    <input type="password" name="password_confirmation" class="form-control" required autocomplete="new-password">
                </div>
                <button class="btn btn-primary w-100"><i class="bi bi-check-lg me-1"></i>Guardar contraseña</button>
            </div>
        </form>
    </div>
</div>
@endsection
