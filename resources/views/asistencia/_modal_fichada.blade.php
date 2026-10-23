{{--
    Modal para cargar / editar una fichada. Requiere $empleadosFichada.
    Uso desde JS:  abrirFichada({ empleado_id, fecha, hora, tipo })            → alta
                   abrirFichada({ id, empleado_id, fecha, hora, tipo, observacion }) → edición
--}}
<div class="modal fade" id="modalFichada" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('fichadas.store') }}" id="formFichada" class="modal-content">
            @csrf
            <input type="hidden" name="_method" id="fichadaMetodo" value="POST">
            <input type="hidden" name="_origen" value="fichada">
            <input type="hidden" name="_fichada_id" id="fichadaId" value="">
            <div class="modal-header">
                <h5 class="modal-title" id="fichadaTitulo">Registrar fichada</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <div class="col-12">
                    <label class="form-label">Empleado *</label>
                    <select name="empleado_id" class="form-select" required>
                        <option value="">— Seleccionar —</option>
                        @foreach($empleadosFichada as $ef)
                            <option value="{{ $ef->id }}">{{ $ef->apellido }}, {{ $ef->nombre }} (leg. {{ $ef->legajo }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label">Fecha *</label>
                    <input type="date" name="fecha" class="form-control" max="{{ now()->format('Y-m-d') }}" required>
                </div>
                <div class="col-6">
                    <label class="form-label">Hora *</label>
                    <input type="time" name="hora" class="form-control" required>
                </div>
                <div class="col-12">
                    <label class="form-label d-block">Tipo *</label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="tipo" value="entrada" id="tipoEntrada" required>
                        <label class="btn btn-outline-success" for="tipoEntrada"><i class="bi bi-box-arrow-in-right me-1"></i>Entrada</label>
                        <input type="radio" class="btn-check" name="tipo" value="salida" id="tipoSalida">
                        <label class="btn btn-outline-danger" for="tipoSalida"><i class="bi bi-box-arrow-right me-1"></i>Salida</label>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Observación</label>
                    <input type="text" name="observacion" class="form-control" maxlength="255" placeholder="Ej: se olvidó de fichar, falla del reloj...">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-danger me-auto d-none" id="fichadaEliminar">
                    <i class="bi bi-trash me-1"></i>Eliminar
                </button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
            </div>
        </form>
    </div>
</div>

<form method="POST" id="formEliminarFichada" class="d-none">
    @csrf
    @method('DELETE')
</form>

@push('scripts')
<script>
const urlAltaFichada = @json(route('fichadas.store'));
const urlEditarFichada = @json(route('fichadas.update', '__ID__'));

function abrirFichada(datos = {}) {
    const form = document.getElementById('formFichada');
    const editar = !!datos.id;

    form.reset();
    form.action = editar ? urlEditarFichada.replace('__ID__', datos.id) : urlAltaFichada;
    document.getElementById('fichadaMetodo').value = editar ? 'PUT' : 'POST';
    document.getElementById('fichadaId').value = datos.id ?? '';
    document.getElementById('fichadaTitulo').textContent = editar ? 'Editar fichada' : 'Registrar fichada';

    form.empleado_id.value = datos.empleado_id ?? '';
    form.fecha.value = datos.fecha ?? @json(now()->format('Y-m-d'));
    form.hora.value = datos.hora ?? '';
    form.observacion.value = datos.observacion ?? '';
    if (datos.tipo) {
        form.querySelector(`input[name=tipo][value=${datos.tipo}]`).checked = true;
    }

    const eliminar = document.getElementById('fichadaEliminar');
    eliminar.classList.toggle('d-none', !editar);
    eliminar.onclick = () => {
        if (!confirm('¿Eliminar esta fichada?')) return;
        const f = document.getElementById('formEliminarFichada');
        f.action = urlEditarFichada.replace('__ID__', datos.id);
        f.submit();
    };

    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalFichada')).show();
}

@if($errors->any() && old('_origen') === 'fichada')
    // Si la validación falló, se vuelve a abrir el modal con lo que se había cargado
    document.addEventListener('DOMContentLoaded', () => abrirFichada({
        id: @json(old('_fichada_id')) || null,
        empleado_id: @json(old('empleado_id')),
        fecha: @json(old('fecha')),
        hora: @json(old('hora')),
        tipo: @json(old('tipo')),
        observacion: @json(old('observacion')),
    }));
@endif
</script>
@endpush
