# Pruebas de seguridad con OWASP ZAP — Gestión RRHH

Dos escaneos:

| Escaneo | Qué hace | Duración | Login |
|---|---|---|---|
| **Base** | Recorre lo público y analiza las respuestas sin atacar (cabeceras, cookies, formularios) | ~2 min | No |
| **Completo** | Ingresa como admin de RRHH y como empleado, recorre todo y prueba ataques reales: XSS, inyección SQL, path traversal, etc. | 30–60 min | Sí |

> **Nunca contra producción.** El escaneo completo manda miles de requests con datos basura
> y puede crear registros. Usar la base local o un staging. El script corta si detecta la URL de producción.

## Requisitos

Una de estas dos opciones:

- **Docker Desktop** (recomendado): no hay que instalar ZAP, se baja la imagen oficial sola.
- **ZAP Desktop**: instalador para Windows desde zaproxy.org. Necesita **Java 17 o superior** instalado aparte (el instalador de Windows no lo trae).

## Preparar el sistema

1. Datos de demo cargados (`php artisan migrate:fresh --seed`; ojo, borra la base local).
2. En el `.env`: `LOGIN_INTENTOS_POR_MINUTO=1000`. ZAP prueba el login cientos de veces;
   con el límite en 10, a los pocos minutos queda bloqueado y pierde la sesión.
3. `php artisan config:clear`
4. Levantar el sistema **escuchando en todas las interfaces**, así lo ve el contenedor de Docker:

```powershell
php artisan serve --host=0.0.0.0 --port=8000
```

## Correr (PowerShell, en otra terminal)

```powershell
cd tests-e2e\zap

# Escaneo base
powershell -ExecutionPolicy Bypass -File .\correr-zap.ps1 -Modo base

# Escaneo completo autenticado
powershell -ExecutionPolicy Bypass -File .\correr-zap.ps1 -Modo completo

# Con ZAP Desktop en vez de Docker
powershell -ExecutionPolicy Bypass -File .\correr-zap.ps1 -Modo completo -Motor desktop

# Contra un staging
powershell -ExecutionPolicy Bypass -File .\correr-zap.ps1 -Modo completo -Url https://staging.tu-dominio.com.ar
```

Los reportes quedan en `tests-e2e\zap\reporte\` (HTML para leer, JSON para procesar).
Al terminar, volvé `LOGIN_INTENTOS_POR_MINUTO=10`.

### Con la interfaz de ZAP Desktop (para mostrar en clase)

1. Abrí ZAP → menú **File → Import → Automation Plan** (o pestaña *Automation* → *Load Plan*).
2. Elegí `zap-plan.yaml`.
3. ZAP no lee `${ZAP_TARGET}` desde la interfaz: en la pestaña *Automation*, editá el contexto y
   reemplazá `${ZAP_TARGET}` por `http://127.0.0.1:8000`, y `${ZAP_REPORTES}` por una carpeta tuya.
4. **Run Plan**. Las alertas aparecen en vivo en la pestaña *Alerts*.

## Archivos

| Archivo | Para qué |
|---|---|
| `correr-zap.ps1` | Corre el escaneo base o el completo, con Docker o con ZAP Desktop |
| `zap-plan.yaml` | Plan del escaneo completo: login con CSRF, 2 usuarios, spider, escaneo pasivo y activo, reportes |
| `zap-plan-base.yaml` | Plan del escaneo base para ZAP Desktop (en Docker se usa `zap-baseline.py`) |
| `reglas-baseline.conf` | Qué hallazgos hacen fallar el escaneo base y cuáles se aceptan |

## Qué se corrigió antes de escanear

Antes de escanear se revisó el sistema y se corrigió lo que ZAP marca siempre:

| Hallazgo típico de ZAP | Corrección |
|---|---|
| Falta Content-Security-Policy (10038) | CSP: solo recursos propios y de cdn.jsdelivr.net |
| Falta X-Frame-Options / clickjacking (10020) | `X-Frame-Options: SAMEORIGIN` y `frame-ancestors 'self'` |
| Falta X-Content-Type-Options (10021) | `nosniff` en todas las respuestas |
| Revela tecnología en X-Powered-By (10037) | Se quita la cabecera |
| Falta HSTS (10035) | `Strict-Transport-Security` cuando la conexión es HTTPS |
| Falta integridad en scripts de CDN (90003) | `integrity` + `crossorigin` en Bootstrap |
| Cache de páginas con datos sensibles (10015) | `Cache-Control: no-store` con sesión iniciada |

Todo eso lo hace `app/Http/Middleware/CabecerasSeguridad.php`, y lo verifica `tests/Feature/CabecerasSeguridadTest.php`.

## Falsos positivos y riesgos aceptados

Estos hallazgos van a seguir apareciendo. Están revisados:

| Alerta | Riesgo ZAP | Por qué se acepta |
|---|---|---|
| Cookie No HttpOnly Flag en `XSRF-TOKEN` (10010) | Bajo | Laravel la deja legible a propósito, para que el JavaScript la reenvíe como cabecera. La cookie de sesión sí es HttpOnly. Marcada como falso positivo en el plan. |
| CSP: `unsafe-inline` en script-src / style-src (10055) | Medio | Las vistas usan `<script>` y `onclick`/`onsubmit` en línea (13 vistas, 53 atributos). Quitarlo requiere moverlos a archivos JS: queda como mejora pendiente. |
| Cookie sin flag Secure (10011) | Bajo | Solo en local (HTTP). En producción `SESSION_SECURE_COOKIE=true` y todo va por HTTPS. |
| Falta HSTS (10035) | Bajo | Solo en local (HTTP). Con HTTPS se envía. |
| Absence of Anti-CSRF Tokens en formularios GET de búsqueda (10202) | Bajo | Son búsquedas (GET) que no modifican datos; todos los formularios POST/PUT/PATCH/DELETE llevan `@csrf`. |
