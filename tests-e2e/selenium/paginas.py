"""
Capa de ayuda sobre Selenium: los tests hablan en términos del sistema
(ingresar, escribir en un campo, apretar un botón, leer el mensaje) y acá
se resuelve cómo encontrar cada cosa en la página.
"""
import re

from selenium.common.exceptions import (
    ElementClickInterceptedException, NoAlertPresentException, TimeoutException, WebDriverException,
)
from selenium.webdriver.common.by import By
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.support.ui import Select, WebDriverWait


class Pagina:
    def __init__(self, driver, base_url: str, espera: int = 10):
        self.driver = driver
        self.base_url = base_url.rstrip("/")
        self.wait = WebDriverWait(driver, espera)

    # ------------------------------------------------------------------ navegación

    def ir(self, ruta: str):
        self.driver.get(self.base_url + ruta)
        self._esperar_carga()

    @property
    def ruta(self) -> str:
        """Ruta actual sin el dominio ni el query string: '/mi/licencias'."""
        url = self.driver.current_url
        return re.sub(r"^https?://[^/]+", "", url).split("?")[0].split("#")[0] or "/"

    def texto(self) -> str:
        return self.driver.find_element(By.TAG_NAME, "body").text

    def titulo(self) -> str:
        return self.driver.title

    # ------------------------------------------------------------------ sesión

    def ingresar(self, email: str, password: str):
        self.ir("/login")
        self.escribir("email", email)
        self.escribir("password", password)
        self.apretar("Ingresar")

    def salir(self):
        """El botón de salir está en un menú desplegable: se envía su formulario directamente."""
        formularios = self.driver.find_elements(By.CSS_SELECTOR, "form[action$='/logout']")
        if formularios:
            viejo = self.driver.find_element(By.TAG_NAME, "html")
            formularios[0].submit()
            self._esperar_navegacion(viejo)
        self.driver.delete_all_cookies()

    # ------------------------------------------------------------------ formularios

    def campo(self, nombre: str):
        return self.wait.until(EC.presence_of_element_located((By.NAME, nombre)))

    def escribir(self, nombre: str, valor: str):
        el = self.campo(nombre)
        if el.get_attribute("type") in ("date", "month"):
            # El formato que se tipea en un input date depende del idioma del navegador:
            # se asigna el valor ISO (aaaa-mm-dd) directamente.
            self.driver.execute_script(
                "arguments[0].value = arguments[1];"
                "arguments[0].dispatchEvent(new Event('input', {bubbles: true}));"
                "arguments[0].dispatchEvent(new Event('change', {bubbles: true}));",
                el, valor,
            )
            return
        el.clear()
        el.send_keys(valor)

    def elegir(self, nombre: str, texto: str):
        """Elige una opción de un <select> por su texto visible (o parte de él)."""
        select = Select(self.campo(nombre))
        for opcion in select.options:
            if texto.lower() in opcion.text.lower():
                select.select_by_visible_text(opcion.text)
                return
        raise AssertionError(f"El select «{nombre}» no tiene una opción con «{texto}»")

    def valor(self, nombre: str) -> str:
        return self.campo(nombre).get_attribute("value")

    def apretar(self, texto: str, dentro=None, confirmar: bool = False):
        """Click en el primer botón visible que contiene ese texto."""
        base = dentro or self.driver
        xpath = f".//button[contains(normalize-space(.), '{texto}')]"
        botones = [b for b in base.find_elements(By.XPATH, xpath) if b.is_displayed()]
        if not botones:
            raise AssertionError(f"No hay un botón visible con el texto «{texto}»")
        viejo = self.driver.find_element(By.TAG_NAME, "html")
        boton = botones[0]
        self.driver.execute_script("arguments[0].scrollIntoView({block: 'center'});", boton)
        try:
            boton.click()
        except ElementClickInterceptedException:
            # Una barra fija o un aviso tapa el botón: se hace el click por JavaScript
            self.driver.execute_script("arguments[0].click();", boton)
        if confirmar:
            self.aceptar_confirmacion()
        self._esperar_navegacion(viejo)

    def aceptar_confirmacion(self):
        """Acepta el confirm() de JavaScript (Cerrar liquidación, Eliminar, etc.)."""
        try:
            WebDriverWait(self.driver, 5).until(EC.alert_is_present())
            self.driver.switch_to.alert.accept()
        except (TimeoutException, NoAlertPresentException):
            pass

    def bloque_con(self, texto: str, etiqueta: str = "li"):
        """Fila de una lista o tabla (li, tr) que contiene un texto."""
        xpath = f"//{etiqueta}[contains(normalize-space(.), '{texto}')]"
        return self.wait.until(EC.presence_of_element_located((By.XPATH, xpath)))

    # ------------------------------------------------------------------ mensajes

    def mensaje_ok(self) -> str:
        return self._alerta("alert-success")

    def mensaje_error(self) -> str:
        return self._alerta("alert-danger")

    def errores_de(self, nombre: str) -> str:
        """Texto de validación debajo de un campo (Bootstrap: .invalid-feedback)."""
        el = self.campo(nombre)
        feedback = el.find_elements(By.XPATH, "following-sibling::*[contains(@class,'invalid-feedback')]")
        return feedback[0].text if feedback else ""

    def _alerta(self, clase: str) -> str:
        try:
            el = WebDriverWait(self.driver, 5).until(
                EC.visibility_of_element_located((By.CSS_SELECTOR, f".alert.{clase}"))
            )
            return el.text
        except TimeoutException:
            return ""

    # ------------------------------------------------------------------ interno

    def _esperar_navegacion(self, viejo):
        """Espera a que el formulario enviado cargue la página siguiente."""
        def cambio_de_pagina(_):
            try:
                viejo.is_enabled()          # si sigue respondiendo, todavía es la página anterior
                return False
            except WebDriverException:      # StaleElementReference (u otro error del nodo viejo): ya navegó
                return True

        try:
            self.wait.until(cambio_de_pagina)
        except TimeoutException:
            pass  # el click no navegó (por ejemplo, la validación del navegador lo frenó)
        self._esperar_carga()

    def _esperar_carga(self):
        self.wait.until(lambda d: d.execute_script("return document.readyState") == "complete")
