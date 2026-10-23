@extends('layouts.app')

@section('title', 'Conceptos de liquidación')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <form method="POST" action="{{ route('conceptos.predeterminados') }}">
        @csrf
        <button class="btn btn-outline-primary"><i class="bi bi-magic me-1"></i>Cargar conceptos predeterminados</button>
    </form>
    <a href="{{ route('conceptos.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Nuevo concepto</a>
</div>

@if($conceptos->isEmpty())
    <div class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5">
        No hay conceptos. Usá «Cargar conceptos predeterminados» para empezar con los habituales (básico, antigüedad, presentismo, horas extra, aportes...).
    </div></div>
@endif

@foreach(\App\Models\Concepto::TIPOS as $tipo => $titulo)
    @if($conceptos->has($tipo))
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">{{ $titulo }}s</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:80px">Código</th>
                            <th>Concepto</th>
                            <th class="d-none d-md-table-cell">Cálculo</th>
                            <th class="text-center d-none d-lg-table-cell">Orden</th>
                            <th class="text-center">Aplicación</th>
                            <th class="text-center">Estado</th>
                            <th class="text-end"></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($conceptos[$tipo] as $c)
                        <tr class="{{ $c->activo ? '' : 'text-muted' }}">
                            <td class="font-monospace">{{ $c->codigo }}</td>
                            <td>
                                <span class="fw-semibold">{{ $c->nombre }}</span>
                                @if($c->convenio)<div class="small text-muted">Solo {{ $c->convenio->nombre }}</div>@endif
                                @if($c->parametro('solo_con_convenio'))<div class="small text-muted">Solo empleados en convenio</div>@endif
                            </td>
                            <td class="d-none d-md-table-cell small">
                                {{ $c->descripcion_calculo }}
                                @if($c->forma === 'presentismo')
                                    <div class="text-muted">Se pierde con más de {{ $c->parametro('max_ausencias', 0) }} ausencias o {{ $c->parametro('max_tardanzas', 2) }} llegadas tarde</div>
                                @endif
                            </td>
                            <td class="text-center d-none d-lg-table-cell">{{ $c->orden }}</td>
                            <td class="text-center">
                                @if($c->forma === 'sac')
                                    <span class="badge text-bg-info">En el SAC</span>
                                @elseif($c->automatico)
                                    <span class="badge text-bg-primary">Automático</span>
                                @else
                                    <span class="badge text-bg-light">Por novedad</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge {{ $c->activo ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $c->activo ? 'Activo' : 'Inactivo' }}</span>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('conceptos.edit', $c) }}" class="btn btn-sm btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                                <form method="POST" action="{{ route('conceptos.destroy', $c) }}" class="d-inline"
                                      onsubmit="return confirm('¿Eliminar el concepto {{ addslashes($c->nombre) }}?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endforeach

<div class="small text-muted">
    <p class="mb-1">Se calculan en orden: primero los remunerativos, después los no remunerativos y al final los descuentos (por el número de orden dentro de cada grupo).
    La base «total remunerativo» es lo acumulado hasta ese concepto; en los descuentos es el total final.</p>
    <p class="mb-0"><strong>Importante:</strong> los porcentajes de convenio (antigüedad, presentismo, sindicato, divisor de horas) vienen con los de Comercio.
    Ajustalos según el CCT y cargá en los aportes el tope de base imponible vigente (ANSES lo actualiza periódicamente).</p>
</div>
@endsection
