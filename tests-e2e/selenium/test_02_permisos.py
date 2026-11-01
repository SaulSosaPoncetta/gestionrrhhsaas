"""Cada rol ve solo lo que le corresponde."""
import pytest


@pytest.mark.parametrize("ruta", ["/empleados", "/liquidaciones", "/usuarios"])
def test_empleado_no_entra_a_modulos_de_rrhh(ingresar, ruta):
    pagina = ingresar("empleado")

    pagina.ir(ruta)

    assert "403" in pagina.titulo() + pagina.texto()


def test_empleado_no_ve_el_menu_de_rrhh(ingresar):
    pagina = ingresar("empleado")

    texto = pagina.texto()
    assert "Mis recibos" in texto
    assert "Legajos" not in texto


def test_supervisor_ve_su_equipo_y_el_empleado_no(ingresar):
    pagina = ingresar("supervisor")
    pagina.ir("/mi/equipo")
    assert "Rodríguez" in pagina.texto()

    pagina = ingresar("empleado")
    pagina.ir("/mi/equipo")
    assert "403" in pagina.titulo() + pagina.texto()


def test_admin_no_administra_empresas(ingresar):
    pagina = ingresar("admin")

    pagina.ir("/empresas")

    assert "403" in pagina.titulo() + pagina.texto()


def test_superadmin_ve_empresas_pero_no_legajos(ingresar):
    pagina = ingresar("superadmin")

    pagina.ir("/empresas")
    assert "Empresa Demo" in pagina.texto()

    pagina.ir("/empleados")
    assert pagina.ruta == "/dashboard"
    assert "datos de una empresa" in pagina.mensaje_error()
