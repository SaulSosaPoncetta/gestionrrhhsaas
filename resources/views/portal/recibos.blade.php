@extends('layouts.app')

@section('title', 'Mis recibos')

@section('content')
<p class="text-muted small">Acá aparecen los recibos de las liquidaciones ya cerradas. Podés verlos, imprimirlos o guardarlos en PDF, y dejar constancia de que los recibiste.</p>

<div class="card border-0 shadow-sm">
    <ul class="list-group list-group-flush">
        @forelse($recibos as $r)
            <li class="list-group-item">
                <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <div>
                        <div class="fw-semibold">{{ $r->liquidacion->periodo_texto }}</div>
                        <div class="small text-muted">
                            @if($r->liquidacion->fecha_pago) Pagado el {{ $r->liquidacion->fecha_pago->format('d/m/Y') }} · @endif
                            Neto <strong>$ {{ number_format($r->neto, 2, ',', '.') }}</strong>
                        </div>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        @if($r->conformado_at)
                            <span class="small text-success"><i class="bi bi-check2-circle"></i> Conforme el {{ $r->conformado_at->format('d/m/Y') }}</span>
                        @else
                            <form method="POST" action="{{ route('portal.recibos.conformar', $r) }}"
                                  onsubmit="return confirm('¿Confirmás que recibiste el recibo de {{ $r->liquidacion->periodo_texto }} y estás conforme?')">
                                @csrf @method('PATCH')
                                <button class="btn btn-sm btn-outline-success"><i class="bi bi-check2 me-1"></i>Dar conformidad</button>
                            </form>
                        @endif
                        <a href="{{ route('portal.recibos.ver', $r) }}" target="_blank" class="btn btn-sm btn-primary"><i class="bi bi-eye me-1"></i>Ver</a>
                    </div>
                </div>
            </li>
        @empty
            <li class="list-group-item text-center text-muted py-5">Todavía no hay recibos disponibles.</li>
        @endforelse
    </ul>
</div>
@endsection
