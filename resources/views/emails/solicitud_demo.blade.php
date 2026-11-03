<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"></head>
<body style="font-family:Arial,Helvetica,sans-serif;color:#2a0610;line-height:1.5">
    <h2 style="color:#800020;margin-bottom:4px">Nueva solicitud de demo</h2>
    <p style="margin-top:0;color:#666">Llegó desde la landing de Gestión RRHH.</p>

    <table cellpadding="6" cellspacing="0" style="border-collapse:collapse">
        <tr><td><strong>Nombre</strong></td><td>{{ $datos['nombre'] }}</td></tr>
        <tr><td><strong>Empresa</strong></td><td>{{ $datos['empresa'] }}</td></tr>
        <tr><td><strong>Email</strong></td><td>{{ $datos['email'] }}</td></tr>
        <tr><td><strong>Teléfono</strong></td><td>{{ $datos['telefono'] ?: '—' }}</td></tr>
        <tr><td><strong>Empleados</strong></td><td>{{ $datos['empleados'] }}</td></tr>
        <tr><td><strong>Plan sugerido</strong></td><td>{{ $datos['plan'] ?? '—' }}</td></tr>
    </table>

    @if(!empty($datos['mensaje']))
        <p><strong>Mensaje</strong></p>
        <p style="white-space:pre-line">{{ $datos['mensaje'] }}</p>
    @endif

    <p style="color:#666;font-size:12px">Podés responder este mail directamente: la respuesta va a {{ $datos['email'] }}.</p>
</body>
</html>
