"""Alta de legajos desde el formulario: validaciones y búsqueda."""
from utilidades import cuil, dni_aleatorio, marca


def completar_legajo(pagina, apellido, dni, cuil_texto):
    pagina.ir("/empleados/create")
    pagina.escribir("apellido", apellido)
    pagina.escribir("nombre", "Prueba")
    pagina.escribir("dni", dni)
    pagina.escribir("cuil", cuil_texto)
    pagina.escribir("fecha_ingreso", "2025-03-03")
    pagina.escribir("sueldo_basico", "950000")


def test_alta_de_legajo_completa(ingresar):
    pagina = ingresar("admin")
    apellido = marca()
    dni = dni_aleatorio()

    completar_legajo(pagina, apellido, dni, cuil(dni).replace("-", ""))   # se carga sin guiones
    pagina.apretar("Guardar")

    assert "Legajo creado correctamente" in pagina.mensaje_ok()
    assert apellido in pagina.texto()
    assert cuil(dni) in pagina.texto()   # se muestra formateado XX-XXXXXXXX-X


def test_cuil_con_digito_verificador_incorrecto(ingresar):
    pagina = ingresar("admin")
    dni = dni_aleatorio()
    correcto = cuil(dni)
    incorrecto = correcto[:-1] + str((int(correcto[-1]) + 1) % 10)

    completar_legajo(pagina, marca(), dni, incorrecto)
    pagina.apretar("Guardar")

    assert pagina.ruta == "/empleados/create"
    assert "dígito verificador" in pagina.texto()


def test_buscar_legajo_por_apellido_y_por_dni(ingresar):
    pagina = ingresar("admin")
    apellido = marca()
    dni = dni_aleatorio()
    completar_legajo(pagina, apellido, dni, cuil(dni))
    pagina.apretar("Guardar")

    pagina.ir(f"/empleados?buscar={apellido}")
    assert apellido in pagina.texto()
    assert "Gómez" not in pagina.texto()

    pagina.ir(f"/empleados?buscar={dni}")
    assert apellido in pagina.texto()
