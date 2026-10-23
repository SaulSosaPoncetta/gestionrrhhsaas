<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recibos · {{ $liquidacion->periodo_texto }}</title>
    <style>
        :root { --borde: #333; --suave: #f6e5e8; }
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 10.5px; color: #111; margin: 0; background: #e9ecef; }
        .barra { position: sticky; top: 0; background: #55101f; color: #fff; padding: 10px 16px; display: flex; gap: 12px; align-items: center; z-index: 10; }
        .barra button { background: #fff; border: 0; padding: 6px 14px; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .hoja { width: 210mm; min-height: 297mm; margin: 12px auto; background: #fff; padding: 8mm 10mm; box-shadow: 0 0 6px rgba(0,0,0,.15); }
        .recibo { border: 1px solid var(--borde); height: 136mm; display: flex; flex-direction: column; }
        .recibo + .corte { border-top: 1px dashed #999; margin: 4mm 0; text-align: center; font-size: 9px; color: #888; }
        .cab { display: flex; border-bottom: 1px solid var(--borde); }
        .cab > div { padding: 4px 6px; }
        .empresa { flex: 1; display: flex; gap: 8px; align-items: center; }
        .empresa img { max-height: 34px; max-width: 70px; }
        .empresa .rs { font-weight: bold; font-size: 12px; }
        .titulo { width: 52mm; border-left: 1px solid var(--borde); text-align: center; }
        .titulo .t1 { font-weight: bold; font-size: 11px; color: #800020; }
        .copia { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #555; }
        table { width: 100%; border-collapse: collapse; }
        .datos td { border-bottom: 1px solid var(--borde); border-right: 1px solid var(--borde); padding: 2px 5px; vertical-align: top; }
        .datos td:last-child { border-right: 0; }
        .datos .et { display: block; font-size: 8px; color: #555; text-transform: uppercase; }
        .items { flex: 1; }
        .items th { background: var(--suave); border-bottom: 1px solid var(--borde); padding: 3px 5px; font-size: 9px; text-transform: uppercase; }
        .items td { padding: 1.5px 5px; }
        .items .num { text-align: right; font-family: "Courier New", monospace; white-space: nowrap; }
        .items .cod { width: 34px; color: #555; }
        .items .cant { width: 60px; text-align: right; color: #555; }
        .items .col { width: 92px; }
        .totales td { border-top: 1px solid var(--borde); padding: 3px 5px; font-weight: bold; }
        .neto { display: flex; border-top: 1px solid var(--borde); }
        .neto .letras { flex: 1; padding: 4px 6px; }
        .neto .importe { width: 52mm; border-left: 1px solid var(--borde); padding: 4px 6px; text-align: right; font-size: 13px; font-weight: bold; }
        .pie { display: flex; border-top: 1px solid var(--borde); min-height: 18mm; }
        .pie > div { flex: 1; padding: 4px 6px; font-size: 9px; }
        .pie .firma { border-left: 1px solid var(--borde); display: flex; flex-direction: column; justify-content: flex-end; text-align: center; }
        .pie .firma span { border-top: 1px solid #555; padding-top: 2px; }
        .obs { font-size: 8.5px; color: #444; padding: 2px 6px; border-top: 1px solid var(--borde); }
        @media print {
            body { background: #fff; }
            .barra { display: none; }
            .hoja { margin: 0; box-shadow: none; padding: 6mm 8mm; page-break-after: always; }
            .hoja:last-child { page-break-after: auto; }
            @page { size: A4; margin: 0; }
        }
    </style>
</head>
<body>
@php
    $m = fn ($v) => number_format((float) $v, 2, ',', '.');
@endphp

<div class="barra">
    <strong>{{ $liquidacion->periodo_texto }}</strong>
    <span>{{ $recibos->count() }} {{ $recibos->count() === 1 ? 'recibo' : 'recibos' }}</span>
    @if($liquidacion->esBorrador())<span style="background:#f59f00;color:#000;padding:2px 8px;border-radius:3px">BORRADOR</span>@endif
    <span style="flex:1"></span>
    <button onclick="window.print()">Imprimir / Guardar PDF</button>
</div>

@foreach($recibos as $recibo)
    <div class="hoja">
        @foreach(['Original · empleador', 'Duplicado · empleado'] as $i => $copia)
            @if($i)<div class="corte">✂ - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -</div>@endif
            <div class="recibo">
                <div class="cab">
                    <div class="empresa">
                        @if($empresa?->logo)<img src="{{ asset('storage/' . $empresa->logo) }}" alt="">@endif
                        <div>
                            <div class="rs">{{ $empresa?->razon_social }}</div>
                            <div>CUIT {{ $empresa?->cuit }}</div>
                            <div>{{ collect([$empresa?->domicilio, $empresa?->localidad, $empresa?->provincia])->filter()->implode(', ') }}</div>
                        </div>
                    </div>
                    <div class="titulo">
                        <div class="t1">RECIBO DE HABERES</div>
                        <div>Ley 20.744 art. 140</div>
                        <div class="copia">{{ $copia }}</div>
                    </div>
                </div>

                <table class="datos">
                    <tr>
                        <td style="width:12%"><span class="et">Legajo</span>{{ $recibo->legajo }}</td>
                        <td style="width:38%"><span class="et">Apellido y nombre</span><strong>{{ $recibo->apellido_nombre }}</strong></td>
                        <td style="width:20%"><span class="et">CUIL</span>{{ $recibo->cuil }}</td>
                        <td style="width:30%"><span class="et">Período liquidado</span><strong>{{ $liquidacion->periodo_texto }}</strong></td>
                    </tr>
                    <tr>
                        <td><span class="et">Ingreso</span>{{ $recibo->fecha_ingreso->format('d/m/Y') }}</td>
                        <td><span class="et">Categoría / puesto</span>{{ $recibo->categoria }}@if($recibo->puesto) · {{ $recibo->puesto }}@endif</td>
                        <td><span class="et">Sueldo básico</span>$ {{ $m($recibo->basico) }}</td>
                        <td><span class="et">Convenio / modalidad</span>{{ $recibo->convenio ?? 'Fuera de convenio' }} · {{ $recibo->modalidad }}</td>
                    </tr>
                </table>

                <div class="items">
                    <table>
                        <thead>
                            <tr>
                                <th class="cod">Cód.</th>
                                <th style="text-align:left">Concepto</th>
                                <th class="cant">Cant.</th>
                                <th class="col num">Remunerativo</th>
                                <th class="col num">No remun.</th>
                                <th class="col num">Descuentos</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($recibo->items as $item)
                            <tr>
                                <td class="cod">{{ $item->codigo }}</td>
                                <td>{{ $item->nombre }}</td>
                                <td class="cant">{{ $item->cantidad_texto }}</td>
                                <td class="num">{{ $item->tipo === 'remunerativo' ? $m($item->importe) : '' }}</td>
                                <td class="num">{{ $item->tipo === 'no_remunerativo' ? $m($item->importe) : '' }}</td>
                                <td class="num">{{ $item->tipo === 'descuento' ? $m($item->importe) : '' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <table class="totales">
                    <tr>
                        <td>Totales</td>
                        <td class="col num" style="width:92px;text-align:right">{{ $m($recibo->total_remunerativo) }}</td>
                        <td class="col num" style="width:92px;text-align:right">{{ $m($recibo->total_no_remunerativo) }}</td>
                        <td class="col num" style="width:92px;text-align:right">{{ $m($recibo->total_descuentos) }}</td>
                    </tr>
                </table>

                <div class="neto">
                    <div class="letras"><span class="et" style="font-size:8px;color:#555">SON PESOS:</span> {{ \App\Support\NumeroALetras::convertir($recibo->neto) }}</div>
                    <div class="importe">NETO $ {{ $m($recibo->neto) }}</div>
                </div>

                @if($recibo->observaciones)
                    <div class="obs">{{ $recibo->observaciones }}</div>
                @endif

                <div class="pie">
                    <div>
                        <div><strong>Lugar y fecha de pago:</strong> {{ $liquidacion->lugar_pago ?: ($empresa?->localidad ?: '—') }}{{ $liquidacion->fecha_pago ? ', ' . $liquidacion->fecha_pago->format('d/m/Y') : '' }}</div>
                        @if($recibo->banco || $recibo->cbu)
                            <div><strong>Acreditado en:</strong> {{ $recibo->banco }} {{ $recibo->cbu ? 'CBU ' . $recibo->cbu : '' }}</div>
                        @endif
                        <div><strong>Último depósito de aportes:</strong>
                            @if($liquidacion->deposito_periodo)
                                período {{ \Carbon\Carbon::createFromFormat('Y-m', $liquidacion->deposito_periodo)->format('m/Y') }}{{ $liquidacion->deposito_fecha ? ', ' . $liquidacion->deposito_fecha->format('d/m/Y') : '' }}{{ $liquidacion->deposito_banco ? ', ' . $liquidacion->deposito_banco : '' }}
                            @else
                                —
                            @endif
                        </div>
                    </div>
                    <div class="firma">
                        <span>{{ $i === 0 ? 'Firma del empleado' : 'Firma y sello del empleador' }}</span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endforeach
</body>
</html>
