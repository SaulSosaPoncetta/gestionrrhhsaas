"""
Liquidación de sueldos: crear, recalcular, cerrar y que el empleado vea su recibo.
Los tests dejan los datos como estaban (eliminan o reabren lo que tocan).
"""
import pytest
from selenium.webdriver.common.by import By


def abrir_liquidacion(pagina, texto_periodo: str):
    """Desde el listado, entra a la liquidación de ese período (ej. 'Octubre 2026')."""
    pagina.ir("/liquidaciones?anio=" + texto_periodo.split()[-1])
    fila = pagina.bloque_con(texto_periodo, "tr")
    fila.find_element(By.XPATH, ".//a[contains(@href, '/liquidaciones/')]").click()
    pagina._esperar_carga()


def eliminar_liquidacion_actual(pagina):
    """El botón Eliminar está en un menú desplegable: se envía su formulario y se acepta el confirm."""
    formulario = pagina.driver.find_element(By.CSS_SELECTOR, "form[action*='/liquidaciones/'] input[name='_method'][value='DELETE']")
    pagina.driver.execute_script("arguments[0].form.submit()", formulario)
    pagina._esperar_carga()


def test_crear_liquidacion_desde_el_modal(ingresar):
    pagina = ingresar("admin")
    pagina.ir("/liquidaciones?anio=2027")
    pagina.apretar("Nueva liquidación")          # abre el modal de Bootstrap

    pagina.elegir("tipo", "Mensual")
    pagina.elegir("mes", "Marzo")
    pagina.escribir("anio", "2027")
    pagina.apretar("Crear y calcular")

    try:
        assert "/liquidaciones/" in pagina.ruta
        assert "Marzo 2027" in pagina.texto()
        assert "se calcularon" in pagina.mensaje_ok() or "ya existe" in pagina.mensaje_error()
        assert pagina.driver.find_elements(By.XPATH, "//button[contains(., 'Cerrar')]")
    finally:
        eliminar_liquidacion_actual(pagina)

    assert "eliminada" in pagina.mensaje_ok()


def test_cerrar_liquidacion_y_el_empleado_ve_su_recibo(ingresar):
    # Octubre 2026 viene en borrador en los datos de demo
    pagina = ingresar("admin")
    abrir_liquidacion(pagina, "Octubre 2026")
    if not pagina.driver.find_elements(By.XPATH, "//button[contains(., 'Recalcular')]"):
        pytest.skip("La liquidación de octubre 2026 no está en borrador: recargá los datos de demo")

    pagina.apretar("Recalcular")
    assert "Se recalcularon" in pagina.mensaje_ok()

    pagina.apretar("Cerrar", confirmar=True)
    assert "Liquidación cerrada" in pagina.mensaje_ok()
    url_liquidacion = pagina.ruta

    try:
        # El empleado ve el recibo de octubre y lo puede abrir
        pagina = ingresar("empleado")
        pagina.ir("/mi/recibos")
        assert "Octubre 2026" in pagina.texto()

        recibo = pagina.bloque_con("Octubre 2026", "li")
        enlace = recibo.find_element(By.XPATH, ".//a[contains(@href, '/mi/recibos/')]")
        pagina.driver.get(enlace.get_attribute("href"))   # el botón Ver abre otra pestaña: se navega directo
        pagina._esperar_carga()
        assert "Rodríguez" in pagina.texto()
        assert "/100" in pagina.texto()   # neto en letras: "... con 00/100"
    finally:
        # Deja la liquidación en borrador como estaba
        pagina = ingresar("admin")
        pagina.ir(url_liquidacion)
        pagina.apretar("Reabrir", confirmar=True)


def test_liquidacion_cerrada_no_se_recalcula(ingresar):
    # Septiembre 2026 viene cerrada en los datos de demo
    pagina = ingresar("admin")
    abrir_liquidacion(pagina, "Septiembre 2026")

    botones = pagina.driver.find_elements(By.XPATH, "//button[contains(., 'Recalcular')]")
    assert not [b for b in botones if b.is_displayed()]
    assert pagina.driver.find_elements(By.XPATH, "//button[contains(., 'Reabrir')]")
