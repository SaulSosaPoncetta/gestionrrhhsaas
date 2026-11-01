"""Circuito completo de una licencia: el empleado la pide, el supervisor la aprueba, el empleado la ve aprobada."""
from datetime import timedelta

from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait

from utilidades import dia_habil_futuro, marca


def esperar_calculo(pagina):
    """El formulario consulta los días por AJAX al cambiar las fechas: se espera esa respuesta
    antes de enviar, como haría una persona que mira el panel lateral."""
    WebDriverWait(pagina.driver, 5).until(
        lambda d: "d-none" not in d.find_element(By.ID, "calculo-datos").get_attribute("class")
    )


def test_solicitud_y_aprobacion_de_licencia(ingresar):
    dia = dia_habil_futuro()
    texto_dia = dia.strftime("%d/%m/%Y")
    observacion = marca()

    # 1. El empleado la solicita desde el portal
    pagina = ingresar("empleado")
    pagina.ir("/mi/licencias/nueva")
    pagina.elegir("tipo_licencia_id", "Donación de sangre")
    pagina.escribir("fecha_desde", dia.isoformat())
    pagina.escribir("fecha_hasta", dia.isoformat())
    pagina.escribir("observaciones", observacion)
    esperar_calculo(pagina)
    pagina.apretar("Enviar solicitud")

    assert pagina.ruta == "/mi/licencias"
    assert "Solicitud enviada" in pagina.mensaje_ok()
    assert "Pendiente" in pagina.bloque_con(texto_dia).text

    # 2. El supervisor la ve en su equipo y la aprueba
    pagina = ingresar("supervisor")
    pagina.ir("/mi/equipo")
    solicitud = pagina.bloque_con(observacion)
    assert "Rodríguez" in solicitud.text
    pagina.apretar("Aprobar", dentro=solicitud)

    assert "Aprobaste la licencia" in pagina.mensaje_ok()

    # 3. El empleado la ve aprobada
    pagina = ingresar("empleado")
    pagina.ir("/mi/licencias")
    assert "Aprobada" in pagina.bloque_con(texto_dia).text


def test_fecha_de_fin_anterior_se_corrige_en_el_formulario(ingresar):
    """El formulario no deja una fecha de fin anterior al inicio: la iguala a la de inicio."""
    dia = dia_habil_futuro()

    pagina = ingresar("empleado")
    pagina.ir("/mi/licencias/nueva")
    pagina.elegir("tipo_licencia_id", "Donación de sangre")
    pagina.escribir("fecha_desde", dia.isoformat())
    pagina.escribir("fecha_hasta", "2025-01-01")

    WebDriverWait(pagina.driver, 5).until(lambda d: pagina.valor("fecha_hasta") == dia.isoformat())
    # El panel lateral recalcula los días con la fecha corregida
    WebDriverWait(pagina.driver, 5).until(
        lambda d: d.find_element(By.ID, "calculo-dias").text == "1 día"
    )


def test_no_permite_superar_el_maximo_del_tipo(ingresar):
    dia = dia_habil_futuro()

    pagina = ingresar("empleado")
    pagina.ir("/mi/licencias/nueva")
    pagina.elegir("tipo_licencia_id", "Donación de sangre")   # máximo 1 día
    pagina.escribir("fecha_desde", dia.isoformat())
    pagina.escribir("fecha_hasta", (dia + timedelta(days=2)).isoformat())
    esperar_calculo(pagina)
    pagina.apretar("Enviar solicitud")

    assert pagina.ruta == "/mi/licencias/nueva"
    assert "permite hasta 1 días por licencia" in pagina.texto()
