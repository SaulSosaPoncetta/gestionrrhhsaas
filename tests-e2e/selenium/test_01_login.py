"""Ingreso al sistema con cada rol y casos de error."""
import pytest


@pytest.mark.parametrize("rol, destino", [
    ("superadmin", "/dashboard"),
    ("admin", "/dashboard"),
    ("supervisor", "/mi"),
    ("empleado", "/mi"),
])
def test_cada_rol_ingresa_a_su_inicio(ingresar, rol, destino):
    pagina = ingresar(rol, forzar_login=True)   # pasa por el formulario de login

    assert pagina.ruta == destino


def test_contrasena_incorrecta_muestra_error_y_no_ingresa(pagina):
    pagina.ingresar("admin@demo.test", "incorrecta")

    assert pagina.ruta == "/login"
    assert "Email o contraseña incorrectos" in pagina.texto()
    # Conserva el email para reintentar, pero nunca la contraseña
    assert pagina.valor("email") == "admin@demo.test"
    assert pagina.valor("password") == ""


def test_sin_sesion_redirige_al_login(pagina):
    pagina.ir("/empleados")

    assert pagina.ruta == "/login"


def test_cerrar_sesion(pagina, ingresar):
    ingresar("admin")

    pagina.salir()
    pagina.ir("/dashboard")

    assert pagina.ruta == "/login"
