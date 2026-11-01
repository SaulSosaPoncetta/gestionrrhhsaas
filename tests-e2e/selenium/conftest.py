"""
Configuración de los tests de Selenium.

Variables de entorno (todas opcionales):
  E2E_URL       URL del sistema a probar          (por defecto http://127.0.0.1:8000)
  E2E_HEADLESS  1 = sin ventana del navegador     (por defecto 0: se ve el navegador)
  E2E_LENTO     segundos de pausa entre pasos     (para mostrarlo en clase; por defecto 0)
  E2E_CHROME    ruta a otro Chrome/Chromium       (por defecto el Chrome instalado)
  E2E_DRIVER    ruta a un chromedriver propio     (por defecto lo baja Selenium Manager)

Usuarios: los del seeder de demostración (php artisan migrate:fresh --seed).
NUNCA correr contra producción: los tests crean legajos, licencias y cierran liquidaciones.
"""
import os
import time
from datetime import datetime
from pathlib import Path

import pytest
from selenium import webdriver
from selenium.webdriver.chrome.service import Service

from paginas import Pagina

BASE_URL = os.environ.get("E2E_URL", "http://127.0.0.1:8000")
HEADLESS = os.environ.get("E2E_HEADLESS", "0") == "1"
LENTO = float(os.environ.get("E2E_LENTO", "0"))

USUARIOS = {
    "superadmin": ("superadmin@rrhh.test", "admin1234"),
    "admin":      ("admin@demo.test", "admin1234"),
    "supervisor": ("pereyra@demo.test", "empleado123"),     # Pereyra: tiene a Rodríguez a cargo
    "empleado":   ("rodriguez@demo.test", "empleado123"),   # Rodríguez
}

CAPTURAS = Path(__file__).parent / "capturas"


def crear_driver():
    opciones = webdriver.ChromeOptions()
    if HEADLESS:
        opciones.add_argument("--headless=new")
    opciones.add_argument("--window-size=1366,900")
    opciones.add_argument("--lang=es-AR")
    opciones.add_argument("--disable-search-engine-choice-screen")
    opciones.add_experimental_option("prefs", {"credentials_enable_service": False,
                                               "profile.password_manager_enabled": False,
                                               "profile.password_manager_leak_detection": False})
    if os.environ.get("E2E_CHROME"):
        opciones.binary_location = os.environ["E2E_CHROME"]
        opciones.add_argument("--no-sandbox")
        opciones.add_argument("--disable-dev-shm-usage")
    # Sin E2E_DRIVER, Selenium Manager (incluido en Selenium 4.6+) descarga el ChromeDriver que corresponde
    servicio = Service(os.environ["E2E_DRIVER"]) if os.environ.get("E2E_DRIVER") else Service()
    driver = webdriver.Chrome(service=servicio, options=opciones)
    driver.implicitly_wait(0)
    return driver


@pytest.fixture
def driver():
    d = crear_driver()
    yield d
    d.quit()


@pytest.fixture
def pagina(driver):
    p = Pagina(driver, BASE_URL)
    if LENTO:
        original = p.apretar

        def apretar_lento(*args, **kwargs):
            time.sleep(LENTO)
            return original(*args, **kwargs)

        p.apretar = apretar_lento
    return p


@pytest.fixture(scope="session")
def sesiones():
    """Cookies de sesión por rol, compartidas por todos los tests.

    El login tiene un límite de 10 intentos por minuto (throttle): si cada test volviera
    a ingresar, la suite chocaría contra ese límite. Por eso se ingresa una vez por rol
    y después se reutiliza la sesión.
    """
    return {}


@pytest.fixture
def ingresar(pagina, sesiones):
    """ingresar('admin') → deja la sesión iniciada con ese usuario y devuelve la página."""
    def _ingresar(rol: str, forzar_login: bool = False) -> Pagina:
        pagina.driver.delete_all_cookies()

        if rol in sesiones and not forzar_login:
            pagina.ir("/login")
            for cookie in sesiones[rol]:
                pagina.driver.add_cookie({k: cookie[k] for k in ("name", "value", "path") if k in cookie})
            pagina.ir("/")
            if "/login" not in pagina.ruta:
                return pagina
            # la sesión guardada ya no sirve (por ejemplo, se cerró): se ingresa de nuevo

        email, password = USUARIOS[rol]
        pagina.ingresar(email, password)
        if "429" in pagina.titulo() + pagina.texto()[:200]:
            pytest.fail("El login devolvió 429 (más de 10 intentos por minuto). Esperá un minuto y volvé a correr.")
        assert "/login" not in pagina.ruta, f"No pudo ingresar {email}: {pagina.mensaje_error()}"
        sesiones[rol] = pagina.driver.get_cookies()
        return pagina
    return _ingresar


@pytest.hookimpl(hookwrapper=True)
def pytest_runtest_makereport(item, call):
    """Si un test falla, guarda una captura de pantalla en capturas/."""
    resultado = yield
    reporte = resultado.get_result()
    if reporte.when == "call" and reporte.failed and "driver" in item.fixturenames:
        CAPTURAS.mkdir(exist_ok=True)
        nombre = f"{item.name}_{datetime.now():%Y%m%d_%H%M%S}.png"
        try:
            item.funcargs["driver"].save_screenshot(str(CAPTURAS / nombre))
        except Exception:
            pass
