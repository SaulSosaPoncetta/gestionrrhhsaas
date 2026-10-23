@extends('layouts.app')

@section('title', 'Importar fichadas')

@section('content')
<a href="{{ route('fichadas.index') }}" class="text-decoration-none small"><i class="bi bi-arrow-left"></i> Fichadas</a>

@if($resultado)
    <div class="card border-0 shadow-sm my-3">
        <div class="card-header bg-white fw-semibold">Resultado de la importación</div>
        <div class="card-body">
            <div class="row text-center g-3 mb-2">
                <div class="col-4">
                    <div class="fs-4 fw-semibold">{{ $resultado['leidas'] }}</div>
                    <div class="small text-muted">Líneas leídas</div>
                </div>
                <div class="col-4">
                    <div class="fs-4 fw-semibold text-success">{{ $resultado['importadas'] }}</div>
                    <div class="small text-muted">Fichadas importadas</div>
                </div>
                <div class="col-4">
                    <div class="fs-4 fw-semibold text-secondary">{{ $resultado['duplicadas'] }}</div>
                    <div class="small text-muted">Ya existían (omitidas)</div>
                </div>
            </div>
            @if($resultado['errores'])
                <div class="alert alert-warning mb-0 mt-3">
                    <div class="fw-semibold mb-1">Líneas con problemas ({{ count($resultado['errores']) }}{{ count($resultado['errores']) >= 50 ? '+' : '' }}):</div>
                    <ul class="mb-0 small">
                        @foreach($resultado['errores'] as $err)<li>{{ $err }}</li>@endforeach
                    </ul>
                </div>
            @endif
            @if($resultado['importadas'])
                <a href="{{ route('fichadas.index', ['origen' => 'importacion']) }}" class="btn btn-sm btn-outline-primary mt-3">Ver fichadas importadas</a>
            @endif
        </div>
    </div>
@endif

<div class="row g-3 mt-1">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                @if($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif
                <form method="POST" action="{{ route('fichadas.importar.procesar') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Archivo del reloj (CSV o TXT, máx. 5 MB) *</label>
                        <input type="file" name="archivo" accept=".csv,.txt" class="form-control" required>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" name="deducir_tipo" value="1" id="deducir_tipo" class="form-check-input" checked>
                        <label for="deducir_tipo" class="form-check-label">
                            Si una fichada no dice si es entrada o salida, deducirlo
                            <div class="small text-muted">Alterna entrada / salida por empleado en orden de horario.</div>
                        </label>
                    </div>
                    <button class="btn btn-primary"><i class="bi bi-upload me-1"></i>Importar</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Formato del archivo</div>
            <div class="card-body small">
                <p>Una fichada por línea, con estas columnas:</p>
                <pre class="bg-light p-2 rounded mb-3">legajo;fecha;hora;tipo
12;01/10/2026;08:02;E
12;01/10/2026;17:05;S
15;01/10/2026;07:58
15;01/10/2026;16:01</pre>
                <ul class="mb-0">
                    <li>Separador: punto y coma, coma o tabulación (se detecta solo).</li>
                    <li>Fecha: <code>dd/mm/aaaa</code>, <code>dd-mm-aaaa</code> o <code>aaaa-mm-dd</code>. Puede venir junto con la hora en la misma columna.</li>
                    <li>Tipo (opcional): <code>E</code>, <code>ENTRADA</code>, <code>IN</code> o <code>0</code> para entrada; <code>S</code>, <code>SALIDA</code>, <code>OUT</code> o <code>1</code> para salida.</li>
                    <li>El legajo tiene que coincidir con el número de legajo del sistema.</li>
                    <li>Las fichadas repetidas se omiten, así que podés volver a importar el mismo archivo sin duplicar.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
