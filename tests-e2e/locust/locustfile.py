"""
Prueba de carga del Sistema de Gestión RRHH con Locust.

Escenario base: 50 usuarios simultáneos, con la mezcla de uso que tendría una empresa:
  - 80 % empleados en el portal (inicio, recibos, licencias, asistencia, fichaje)
  - 10 % supervisores (equipo, aprobaciones pendientes)
  - 10 % administradores de RRHH (legajos, parte diario, resumen mensual, liquidaciones)

Antes de correrlo, en el sistema a probar (local o staging, NUNCA producción):
  1. php artisan db:seed --class=CargaDemoSeeder      → crea carga01…carga50@demo.test
  2. LOGIN_INTENTOS_POR_MINUTO=1000 en el .env         → si no, el límite de login frena a los 50 usuarios

Correr:  locust --config carga.conf   → sin interfaz, deja los reportes en reporte/
         locust                       → interfaz web en http://localhost:8089
"""
import itertools
import os
import random
import re
import threading
from datetime import date, timedelta

from locust import HttpUser, between, events, task

os.makedirs(os.path.join(os.path.dirname(os.path.abspath(__file__)), "reporte"), exist_ok=True)

PASSWORD_CARGA = os.environ.get("CARGA_PASSWORD", "empleado123")
ADMIN = (os.environ.get("CARGA_ADMIN", "admin@demo.test"), os.environ.get("CARGA_ADMIN_PASSWORD", "admin1234"))

# Umbrales para dar la prueba por aprobada (se pueden cambiar por variable de entorno)
MAX_ERRORES_PCT = float(os.environ.get("CARGA_MAX_ERRORES_PCT", "1"))     # % de requests fallidos
MAX_P95_MS = float(os.environ.get("CARGA_MAX_P95_MS", "2000"))            # percentil 95 en ms

# Cada usuario virtual toma una cuenta distinta del CargaDemoSeeder
_supervisores = itertools.cycle([f"carga{n:02d}@demo.test" for n in range(1, 6)])
_empleados = itertools.cycle([f"carga{n:02d}@demo.test" for n in range(6, 51)])
_lock = threading.Lock()

RE_TOKEN = re.compile(r'name="_token"\s+value="([^"]+)"')
RE_CSRF_META = re.compile(r'name="csrf-token"\s+content="([^"]+)"')


class UsuarioRRHH(HttpUser):
    abstract = True
    wait_time = between(2, 6)   # "tiempo de lectura" entre clicks de una persona real
    email = None
    password = None

    def on_start(self):
        self.csrf = None
        self.login()

    # ---------------------------------------------------------------- ayuda

    def login(self):
        with self.client.get("/login", name="GET /login", catch_response=True) as r:
            token = RE_TOKEN.search(r.text)
            if not token:
                r.failure("El formulario de login no trae _token")
                return
        with self.client.post("/login", name="POST /login", catch_response=True, allow_redirects=True,
                              data={"_token": token.group(1), "email": self.email, "password": self.password}) as r:
            if r.status_code == 429:
                r.failure("429: límite de login. Subí LOGIN_INTENTOS_POR_MINUTO para la prueba de carga")
            elif "/login" in r.url:
                r.failure(f"No pudo ingresar {self.email}")
            else:
                self.guardar_csrf(r.text)

    def guardar_csrf(self, html: str):
        m = RE_CSRF_META.search(html)
        if m:
            self.csrf = m.group(1)

    def ver(self, url: str, nombre: str = None, contiene: str = None):
        """GET que valida estado 200, que no lo haya mandado al login y (opcional) un texto esperado."""
        with self.client.get(url, name=nombre or url, catch_response=True) as r:
            if r.status_code != 200:
                r.failure(f"HTTP {r.status_code}")
            elif "/login" in r.url:
                r.failure("Sesión perdida: redirigió al login")
                self.login()
            elif contiene and contiene not in r.text:
                r.failure(f"La página no contiene «{contiene}»")
            else:
                self.guardar_csrf(r.text)
            return r

    @staticmethod
    def ids(html: str, patron: str) -> list:
        return list(set(re.findall(patron, html)))


# ==================================================================== empleado

class Empleado(UsuarioRRHH):
    weight = 8

    def on_start(self):
        with _lock:
            self.email = next(_empleados)
        self.password = PASSWORD_CARGA
        super().on_start()

    @task(6)
    def inicio(self):
        self.ver("/mi", "Portal: inicio", contiene="Fichar")

    @task(4)
    def recibos(self):
        r = self.ver("/mi/recibos", "Portal: mis recibos")
        ids = self.ids(r.text, r"/mi/recibos/(\d+)")
        if ids:
            self.ver(f"/mi/recibos/{random.choice(ids)}", "Portal: ver recibo", contiene="/100")

    @task(3)
    def licencias(self):
        self.ver("/mi/licencias", "Portal: mis licencias")

    @task(2)
    def calcular_dias_licencia(self):
        """El JSON que consulta el formulario de licencias mientras se eligen las fechas."""
        r = self.ver("/mi/licencias/nueva", "Portal: nueva licencia")
        tipos = self.ids(r.text, r'<option value="(\d+)"')
        if not tipos:
            return
        desde = date.today() + timedelta(days=random.randint(10, 60))
        hasta = desde + timedelta(days=random.randint(0, 6))
        with self.client.get("/mi/licencias/calcular-dias", name="Portal: calcular días (JSON)", catch_response=True,
                             headers={"Accept": "application/json"},
                             params={"tipo_id": random.choice(tipos), "desde": desde.isoformat(),
                                     "hasta": hasta.isoformat(), "periodo": date.today().year}) as j:
            if j.status_code != 200 or "dias" not in j.text:
                j.failure(f"HTTP {j.status_code}")

    @task(3)
    def asistencia(self):
        self.ver("/mi/asistencia", "Portal: mi asistencia")

    @task(1)
    def datos(self):
        self.ver("/mi/datos", "Portal: mis datos")

    @task(1)
    def fichar(self):
        if not self.csrf:
            return
        with self.client.post("/mi/fichar", name="Portal: fichar", catch_response=True,
                              data={"_token": self.csrf}) as r:
            if r.status_code == 429:
                r.success()   # límite propio del fichaje (10 por minuto): esperado si ficha muy seguido
            elif r.status_code != 200:
                r.failure(f"HTTP {r.status_code}")


# ==================================================================== supervisor

class Supervisor(UsuarioRRHH):
    weight = 1

    def on_start(self):
        with _lock:
            self.email = next(_supervisores)
        self.password = PASSWORD_CARGA
        super().on_start()

    @task(4)
    def equipo(self):
        self.ver("/mi/equipo", "Supervisor: mi equipo")

    @task(2)
    def inicio(self):
        self.ver("/mi", "Portal: inicio")

    @task(1)
    def licencias(self):
        self.ver("/mi/licencias", "Portal: mis licencias")


# ==================================================================== admin RRHH

class AdminRRHH(UsuarioRRHH):
    weight = 1
    wait_time = between(3, 8)

    def on_start(self):
        self.email, self.password = ADMIN
        super().on_start()

    @task(3)
    def dashboard(self):
        self.ver("/dashboard", "RRHH: inicio")

    @task(4)
    def legajos(self):
        r = self.ver("/empleados", "RRHH: legajos")
        ids = self.ids(r.text, r'/empleados/(\d+)"')
        if ids:
            self.ver(f"/empleados/{random.choice(ids)}", "RRHH: ver legajo")

    @task(2)
    def buscar(self):
        self.ver(f"/empleados?buscar={random.choice(['Carga', 'Gómez', 'Usuario 1', '9012'])}", "RRHH: buscar legajo")

    @task(3)
    def parte_diario(self):
        self.ver("/asistencia/parte", "RRHH: parte diario")

    @task(1)
    def resumen_mensual(self):
        """La pantalla más pesada: calcula la asistencia del mes de todos los empleados."""
        self.ver("/asistencia/resumen", "RRHH: resumen mensual de asistencia")

    @task(2)
    def licencias(self):
        self.ver("/licencias", "RRHH: licencias")

    @task(2)
    def liquidaciones(self):
        r = self.ver(f"/liquidaciones?anio={date.today().year}", "RRHH: liquidaciones")
        ids = self.ids(r.text, r'/liquidaciones/(\d+)"')
        if ids:
            self.ver(f"/liquidaciones/{random.choice(ids)}", "RRHH: ver liquidación")


# ==================================================================== resultado

@events.quitting.add_listener
def evaluar_resultado(environment, **kwargs):
    """Marca la prueba como fallida (código de salida 1) si supera los umbrales."""
    total = environment.stats.total
    if total.num_requests == 0:
        environment.process_exit_code = 1
        print("\nRESULTADO: sin requests. ¿Está corriendo el sistema en la URL configurada?")
        return

    errores_pct = 100 * total.num_failures / total.num_requests
    p95 = total.get_response_time_percentile(0.95)

    print(f"\nRESULTADO: {total.num_requests} requests · errores {errores_pct:.2f} % (máx {MAX_ERRORES_PCT} %) "
          f"· p95 {p95:.0f} ms (máx {MAX_P95_MS:.0f} ms) · {total.total_rps:.1f} req/s")

    if errores_pct > MAX_ERRORES_PCT or p95 > MAX_P95_MS:
        environment.process_exit_code = 1
        print("RESULTADO: NO APROBADA")
    else:
        environment.process_exit_code = 0
        print("RESULTADO: APROBADA")
