# Prueba de carga con Locust — Gestión RRHH

Simula **50 usuarios simultáneos** usando el sistema como lo haría una empresa:

| Perfil | Proporción | Qué hace |
|---|---|---|
| Empleado (portal) | 80 % | Inicio, recibos (y abre uno), licencias, cálculo de días, asistencia, datos, fichaje web |
| Supervisor | 10 % | Su equipo, inicio, licencias |
| Admin RRHH | 10 % | Legajos, búsqueda, parte diario, resumen mensual de asistencia, licencias, liquidaciones |

Cada usuario espera entre 2 y 6 segundos entre clicks (tiempo de lectura de una persona).

**Criterio de aprobación:** menos de 1 % de errores y percentil 95 por debajo de 2 segundos.
Si no se cumple, Locust termina con código de salida 1 y lo indica como `NO APROBADA`.

## Dónde correrla

Contra un **entorno de staging** en el VPS (mismo servidor y configuración que producción, otra base).
**Nunca contra producción:** 50 usuarios fichan, consultan y generan carga real.

En Windows, `php artisan serve` atiende un pedido por vez: sirve para comprobar que el script anda,
pero los tiempos que mide no representan al servidor real.

## Preparar el sistema a probar

En el `.env` del entorno de prueba:

```
LOGIN_INTENTOS_POR_MINUTO=1000
```

Sin esto, el límite de 10 logins por minuto por IP deja afuera a 40 de los 50 usuarios
(todos entran desde la misma máquina). Después de la prueba, volvelo a 10.

Cargar los datos de demo y los 50 usuarios de carga:

```bash
php artisan db:seed                         # si la base no tiene los datos de demo
php artisan db:seed --class=CargaDemoSeeder # carga01…carga50@demo.test / empleado123
php artisan config:clear
```

## Correr (Windows / PowerShell)

```powershell
cd tests-e2e\locust
py -m pip install -r requirements.txt

# Sin interfaz, 5 minutos, deja reportes en reporte\
py -m locust --config carga.conf --host https://staging.tu-dominio.com.ar

# Con interfaz web (gráficos en vivo): abrí http://localhost:8089
py -m locust --host https://staging.tu-dominio.com.ar
```

Otros escenarios: `--users 100 --spawn-rate 10 --run-time 10m`.

## Reportes

| Archivo | Contenido |
|---|---|
| `reporte/reporte_carga.html` | Informe con gráficos de usuarios, req/s y tiempos de respuesta |
| `reporte/carga_stats.csv` | Tiempos por pantalla (mediana, p95, p99, máximo) |
| `reporte/carga_failures.csv` | Errores, si los hubo |
