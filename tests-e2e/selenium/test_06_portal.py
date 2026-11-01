"""Portal de autogestión del empleado."""
from utilidades import marca


def test_pantallas_del_portal(ingresar):
    pagina = ingresar("empleado")

    for ruta, texto in [
        ("/mi", "Rodríguez"),
        ("/mi/recibos", "recibos"),
        ("/mi/licencias", "licencias"),
        ("/mi/asistencia", "asistencia"),
        ("/mi/datos", "Rodríguez"),
    ]:
        pagina.ir(ruta)
        assert pagina.ruta == ruta
        assert texto.lower() in pagina.texto().lower(), f"{ruta} no muestra «{texto}»"


def test_actualizar_datos_de_contacto(ingresar):
    pagina = ingresar("empleado")
    telefono = "11 " + marca()[-6:]

    pagina.ir("/mi/datos")
    pagina.escribir("telefono", telefono)
    pagina.apretar("Guardar")

    assert "Tus datos se actualizaron" in pagina.mensaje_ok()
    pagina.ir("/mi/datos")
    assert pagina.valor("telefono") == telefono


def test_fichaje_web(ingresar):
    pagina = ingresar("empleado")
    pagina.ir("/mi")

    texto = pagina.texto()
    boton = "Fichar entrada" if "Fichar entrada" in texto else "Fichar salida"
    pagina.apretar(boton)

    mensaje = pagina.mensaje_ok() or pagina.mensaje_error()
    # Si ya fichó hace menos de un minuto, el sistema lo frena: también es un resultado válido
    assert "registrada a las" in mensaje or "menos de un minuto" in mensaje
