@extends('layouts.app')

@section('title', $empleado->exists ? 'Editar legajo · ' . $empleado->nombre_completo : 'Nuevo legajo')

@section('content')
@php($e = $empleado)

<form method="POST" enctype="multipart/form-data"
      action="{{ $e->exists ? route('empleados.update', $e) : route('empleados.store') }}">
    @csrf
    @if($e->exists) @method('PUT') @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <div class="fw-semibold mb-1">Revisá los siguientes datos:</div>
            <ul class="mb-0">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- DATOS PERSONALES --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-person me-1"></i>Datos personales</div>
        <div class="card-body row g-3">
            <div class="col-md-2">
                <label class="form-label">Legajo N° *</label>
                <input type="number" name="legajo" value="{{ old('legajo', $e->legajo) }}"
                       class="form-control @error('legajo') is-invalid @enderror" min="1" required>
            </div>
            <div class="col-md-5">
                <label class="form-label">Apellido *</label>
                <input type="text" name="apellido" value="{{ old('apellido', $e->apellido) }}"
                       class="form-control @error('apellido') is-invalid @enderror" required>
            </div>
            <div class="col-md-5">
                <label class="form-label">Nombre/s *</label>
                <input type="text" name="nombre" value="{{ old('nombre', $e->nombre) }}"
                       class="form-control @error('nombre') is-invalid @enderror" required>
            </div>

            <div class="col-md-3">
                <label class="form-label">DNI *</label>
                <input type="text" name="dni" id="dni" value="{{ old('dni', $e->dni) }}"
                       class="form-control @error('dni') is-invalid @enderror" maxlength="10" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">CUIL *</label>
                <input type="text" name="cuil" id="cuil" value="{{ old('cuil', $e->cuil) }}"
                       class="form-control @error('cuil') is-invalid @enderror" placeholder="20-12345678-9" maxlength="13" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Fecha de nacimiento</label>
                <input type="date" name="fecha_nacimiento" value="{{ old('fecha_nacimiento', $e->fecha_nacimiento?->format('Y-m-d')) }}"
                       class="form-control @error('fecha_nacimiento') is-invalid @enderror">
            </div>
            <div class="col-md-3">
                <label class="form-label">Sexo</label>
                <select name="sexo" class="form-select">
                    <option value="">—</option>
                    @foreach(\App\Models\Empleado::SEXOS as $k => $v)
                        <option value="{{ $k }}" @selected(old('sexo', $e->sexo) === $k)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Estado civil</label>
                <select name="estado_civil" class="form-select">
                    <option value="">—</option>
                    @foreach(\App\Models\Empleado::ESTADOS_CIVILES as $k => $v)
                        <option value="{{ $k }}" @selected(old('estado_civil', $e->estado_civil) === $k)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Nacionalidad</label>
                <input type="text" name="nacionalidad" value="{{ old('nacionalidad', $e->nacionalidad) }}" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label">Foto (máx. 2 MB)</label>
                <input type="file" name="foto" accept="image/*" class="form-control @error('foto') is-invalid @enderror">
                @if($e->foto_url)
                    <div class="d-flex align-items-center gap-2 mt-2">
                        <img src="{{ $e->foto_url }}" alt="" width="48" height="48" class="rounded-circle object-fit-cover">
                        <div class="form-check">
                            <input type="checkbox" name="quitar_foto" value="1" id="quitar_foto" class="form-check-input">
                            <label for="quitar_foto" class="form-check-label small">Quitar foto</label>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- CONTACTO --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-geo-alt me-1"></i>Domicilio y contacto</div>
        <div class="card-body row g-3">
            <div class="col-md-6">
                <label class="form-label">Domicilio</label>
                <input type="text" name="domicilio" value="{{ old('domicilio', $e->domicilio) }}" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">Localidad</label>
                <input type="text" name="localidad" value="{{ old('localidad', $e->localidad) }}" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">Provincia</label>
                <input type="text" name="provincia" value="{{ old('provincia', $e->provincia) }}" class="form-control">
            </div>
            <div class="col-md-1">
                <label class="form-label">CP</label>
                <input type="text" name="codigo_postal" value="{{ old('codigo_postal', $e->codigo_postal) }}" class="form-control" maxlength="10">
            </div>
            <div class="col-md-3">
                <label class="form-label">Teléfono</label>
                <input type="text" name="telefono" value="{{ old('telefono', $e->telefono) }}" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" value="{{ old('email', $e->email) }}" class="form-control @error('email') is-invalid @enderror">
            </div>
            <div class="col-md-3">
                <label class="form-label">Contacto de emergencia</label>
                <input type="text" name="contacto_emergencia" value="{{ old('contacto_emergencia', $e->contacto_emergencia) }}"
                       class="form-control" placeholder="Nombre y vínculo">
            </div>
            <div class="col-md-3">
                <label class="form-label">Tel. de emergencia</label>
                <input type="text" name="telefono_emergencia" value="{{ old('telefono_emergencia', $e->telefono_emergencia) }}" class="form-control">
            </div>
        </div>
    </div>

    {{-- DATOS LABORALES --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-briefcase me-1"></i>Datos laborales</div>
        <div class="card-body row g-3">
            <div class="col-md-3">
                <label class="form-label">Fecha de ingreso *</label>
                <input type="date" name="fecha_ingreso" value="{{ old('fecha_ingreso', $e->fecha_ingreso?->format('Y-m-d')) }}"
                       class="form-control @error('fecha_ingreso') is-invalid @enderror" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Modalidad *</label>
                <select name="modalidad" class="form-select">
                    @foreach(\App\Models\Empleado::MODALIDADES as $k => $v)
                        <option value="{{ $k }}" @selected(old('modalidad', $e->modalidad) === $k)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Jornada *</label>
                <select name="jornada" class="form-select">
                    @foreach(\App\Models\Empleado::JORNADAS as $k => $v)
                        <option value="{{ $k }}" @selected(old('jornada', $e->jornada) === $k)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Horas semanales</label>
                <input type="number" name="horas_semanales" value="{{ old('horas_semanales', $e->horas_semanales) }}"
                       class="form-control @error('horas_semanales') is-invalid @enderror" step="0.5" min="1" max="80">
            </div>

            <div class="col-md-6">
                <label class="form-label">Horario de trabajo</label>
                <select name="horario_id" id="horario_id" class="form-select @error('horario_id') is-invalid @enderror">
                    <option value="">— Sin horario asignado —</option>
                    @foreach($horarios as $h)
                        <option value="{{ $h->id }}" data-resumen="{{ $h->resumen }}"
                                @selected((string) old('horario_id', $e->horario_id) === (string) $h->id)>{{ $h->nombre }}</option>
                    @endforeach
                </select>
                <div class="form-text" id="horario-resumen"></div>
            </div>
            <div class="col-md-6 d-flex align-items-center">
                <div class="small text-muted">
                    El horario se usa para calcular llegadas tarde, ausencias y horas extra.
                    Se administran en <a href="{{ route('horarios.index') }}" target="_blank">Asistencia › Horarios</a>.
                </div>
            </div>

            <div class="col-md-4">
                <label class="form-label">Área</label>
                <select name="area_id" id="area_id" class="form-select @error('area_id') is-invalid @enderror">
                    <option value="">— Sin área —</option>
                    @foreach($areas as $a)
                        <option value="{{ $a->id }}" @selected((string) old('area_id', $e->area_id) === (string) $a->id)>
                            {{ str_repeat('— ', $a->nivel) }}{{ $a->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Puesto</label>
                <select name="puesto_id" id="puesto_id" class="form-select @error('puesto_id') is-invalid @enderror">
                    <option value="">— Sin puesto —</option>
                    @foreach($puestos as $p)
                        <option value="{{ $p->id }}" data-area="{{ $p->area_id }}"
                                @selected((string) old('puesto_id', $e->puesto_id) === (string) $p->id)>{{ $p->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Supervisor directo</label>
                <select name="supervisor_id" class="form-select @error('supervisor_id') is-invalid @enderror">
                    <option value="">— Ninguno —</option>
                    @foreach($supervisores as $s)
                        <option value="{{ $s->id }}" @selected((string) old('supervisor_id', $e->supervisor_id) === (string) $s->id)>
                            {{ $s->nombre_completo }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Convenio</label>
                <select name="convenio_id" id="convenio_id" class="form-select @error('convenio_id') is-invalid @enderror">
                    <option value="">— Fuera de convenio —</option>
                    @foreach($convenios as $c)
                        <option value="{{ $c->id }}" @selected((string) old('convenio_id', $e->convenio_id) === (string) $c->id)>
                            {{ $c->nombre_completo }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4" id="bloque-categoria">
                <label class="form-label">Categoría *</label>
                <select name="categoria_id" id="categoria_id" class="form-select @error('categoria_id') is-invalid @enderror">
                    <option value="" data-basico="">— Seleccionar —</option>
                    @foreach($categorias as $cat)
                        <option value="{{ $cat->id }}" data-convenio="{{ $cat->convenio_id }}" data-basico="{{ $cat->basico_formateado }}"
                                @selected((string) old('categoria_id', $e->categoria_id) === (string) $cat->id)>{{ $cat->nombre }}</option>
                    @endforeach
                </select>
                <div class="form-text" id="basico-categoria"></div>
            </div>
            <div class="col-md-4" id="bloque-basico">
                <label class="form-label">Sueldo básico *</label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" name="sueldo_basico" value="{{ old('sueldo_basico', $e->sueldo_basico) }}"
                           class="form-control @error('sueldo_basico') is-invalid @enderror" step="0.01" min="0">
                </div>
            </div>
        </div>
    </div>

    {{-- PAGO Y OBRA SOCIAL --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-bank me-1"></i>Cobro y obra social</div>
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label">Banco</label>
                <input type="text" name="banco" value="{{ old('banco', $e->banco) }}" class="form-control">
            </div>
            <div class="col-md-8">
                <label class="form-label">CBU</label>
                <input type="text" name="cbu" value="{{ old('cbu', $e->cbu) }}"
                       class="form-control font-monospace @error('cbu') is-invalid @enderror" maxlength="22" inputmode="numeric">
            </div>
            <div class="col-md-8">
                <label class="form-label">Obra social</label>
                <input type="text" name="obra_social" value="{{ old('obra_social', $e->obra_social) }}" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label">Código RNOS</label>
                <input type="text" name="codigo_obra_social" value="{{ old('codigo_obra_social', $e->codigo_obra_social) }}" class="form-control" maxlength="10">
            </div>
            <div class="col-12">
                <label class="form-label">Observaciones</label>
                <textarea name="observaciones" rows="3" class="form-control">{{ old('observaciones', $e->observaciones) }}</textarea>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mb-4">
        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
        <a href="{{ $e->exists ? route('empleados.show', $e) : route('empleados.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    // CUIL: formato 20-12345678-9 mientras se escribe
    const cuil = document.getElementById('cuil');
    cuil.addEventListener('input', function () {
        let v = this.value.replace(/\D/g, '').slice(0, 11);
        if (v.length > 10) v = v.slice(0, 2) + '-' + v.slice(2, 10) + '-' + v.slice(10);
        else if (v.length > 2) v = v.slice(0, 2) + '-' + v.slice(2);
        this.value = v;
    });

    // Sugerir el DNI a partir del CUIL
    const dni = document.getElementById('dni');
    cuil.addEventListener('blur', function () {
        const n = this.value.replace(/\D/g, '');
        if (n.length === 11 && !dni.value) dni.value = String(parseInt(n.slice(2, 10), 10));
    });

    // Filtrar opciones de un select según el valor de otro
    function filtrar(select, atributo, valor) {
        [...select.options].forEach(o => {
            if (!o.value) return;
            const visible = !valor || o.dataset[atributo] === valor || (atributo === 'area' && !o.dataset.area);
            o.hidden = !visible;
            o.disabled = !visible;
        });
        if (select.selectedOptions[0]?.disabled) select.value = '';
    }

    // Puestos según área
    const area = document.getElementById('area_id');
    const puesto = document.getElementById('puesto_id');
    area.addEventListener('change', () => filtrar(puesto, 'area', area.value));
    filtrar(puesto, 'area', area.value);

    // Categoría / básico según convenio
    const convenio = document.getElementById('convenio_id');
    const categoria = document.getElementById('categoria_id');
    const bloqueCat = document.getElementById('bloque-categoria');
    const bloqueBas = document.getElementById('bloque-basico');
    const textoBasico = document.getElementById('basico-categoria');

    function actualizarConvenio() {
        const tiene = !!convenio.value;
        bloqueCat.classList.toggle('d-none', !tiene);
        bloqueBas.classList.toggle('d-none', tiene);
        filtrar(categoria, 'convenio', convenio.value);
        mostrarBasico();
    }
    function mostrarBasico() {
        const b = categoria.selectedOptions[0]?.dataset.basico;
        textoBasico.textContent = b ? 'Básico vigente: ' + b : '';
    }
    convenio.addEventListener('change', actualizarConvenio);
    categoria.addEventListener('change', mostrarBasico);
    actualizarConvenio();

    // Resumen del horario elegido
    const horario = document.getElementById('horario_id');
    const textoHorario = document.getElementById('horario-resumen');
    const mostrarHorario = () => textoHorario.textContent = horario.selectedOptions[0]?.dataset.resumen ?? '';
    horario.addEventListener('change', mostrarHorario);
    mostrarHorario();
})();
</script>
@endpush
