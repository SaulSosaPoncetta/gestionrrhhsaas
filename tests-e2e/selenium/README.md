# Tests de interfaz con Selenium — Gestión RRHH

26 tests que manejan un Chrome real y recorren el sistema como lo haría una persona:
login por rol, permisos, alta de legajos, solicitud y aprobación de licencias,
liquidación de sueldos y portal del empleado.

## Requisitos

- Python 3.10 o superior (`py --version`)
- Google Chrome instalado (el ChromeDriver lo descarga Selenium solo)
- El sistema corriendo en local con los **datos de demostración**

> Nunca correr contra producción: los tests crean legajos y licencias, y cierran y reabren liquidaciones.

## Cómo correrlos (Windows / PowerShell)

**Terminal 1 — el sistema**

```powershell
php artisan migrate:fresh --seed   # ATENCIÓN: borra la base local y carga los datos de demo
php artisan serve
```

**Terminal 2 — los tests**

```powershell
cd tests-e2e\selenium
py -m pip install -r requirements.txt
py -m pytest --html=reporte\reporte_selenium.html --self-contained-html
```

Al terminar queda el informe en `reporte\reporte_selenium.html`. Si un test falla,
se guarda una captura de pantalla en `capturas\`.

## Opciones

| Variable | Para qué | Ejemplo |
|---|---|---|
| `E2E_URL` | Probar otra URL (staging) | `$env:E2E_URL="https://staging.ejemplo.com"` |
| `E2E_HEADLESS` | Correr sin ventana | `$env:E2E_HEADLESS="1"` |
| `E2E_LENTO` | Pausa entre clicks, para mostrarlo en clase | `$env:E2E_LENTO="1"` |

Correr un solo archivo: `py -m pytest test_04_licencias.py`

## Tener en cuenta

- El login permite 10 intentos por minuto. La suite ingresa una sola vez por rol y reutiliza
  la sesión, pero si la corrés dos veces seguidas esperá un minuto (o `php artisan cache:clear`).
- `test_05` necesita que la liquidación **Octubre 2026** de los datos de demo esté en borrador;
  el test la cierra y al final la reabre.

## Archivos

| Archivo | Contenido |
|---|---|
| `conftest.py` | Navegador, usuarios de demo, sesiones por rol, capturas al fallar |
| `paginas.py` | Acciones comunes: ingresar, escribir, elegir, apretar botones, leer mensajes |
| `utilidades.py` | Datos únicos por corrida (DNI, CUIL válido, fechas, marcas) |
| `test_01_login.py` | Login de cada rol, contraseña incorrecta, cierre de sesión |
| `test_02_permisos.py` | Qué ve y qué no ve cada rol |
| `test_03_legajos.py` | Alta de legajo, CUIL inválido, búsqueda |
| `test_04_licencias.py` | Empleado pide → supervisor aprueba → empleado la ve aprobada |
| `test_05_liquidacion.py` | Crear, recalcular, cerrar y ver el recibo en el portal |
| `test_06_portal.py` | Pantallas del portal, datos de contacto, fichaje web |
