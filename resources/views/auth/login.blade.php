<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingresar · {{ config('app.name', 'Gestión RRHH') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/tema.css') }}?v=1" rel="stylesheet">
    <style>
        body { min-height: 100vh; }
    </style>
</head>
<body class="fondo-login d-flex align-items-center justify-content-center p-3">
<div class="card shadow border-0" style="max-width: 400px; width: 100%;">
    <div class="card-body p-4">
        <div class="text-center mb-4">
            <i class="bi bi-people-fill fs-1 text-primary"></i>
            <h1 class="h4 mt-2 mb-0">{{ config('app.name', 'Gestión RRHH') }}</h1>
            <p class="text-muted small">Recursos y Capital Humano</p>
        </div>

        @if($errors->any())
            <div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" class="form-control" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label">Contraseña</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="form-check mb-3">
                <input type="checkbox" name="remember" id="remember" class="form-check-input">
                <label for="remember" class="form-check-label">Recordarme</label>
            </div>
            <button class="btn btn-primary w-100">Ingresar</button>
        </form>
    </div>
</div>
</body>
</html>
