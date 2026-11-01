"""Datos únicos por ejecución, para poder correr los tests muchas veces sin chocar."""
import random
from datetime import date, timedelta


def dni_aleatorio() -> str:
    return str(random.randint(20_000_000, 45_999_999))


def cuil(dni: str, prefijo: str = "20") -> str:
    """CUIL con dígito verificador correcto (mismo algoritmo que App\\Support\\Validadores)."""
    base = prefijo + dni
    mult = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2]
    suma = sum(int(base[i]) * mult[i] for i in range(10))
    dv = 11 - suma % 11
    dv = {11: 0, 10: 9}.get(dv, dv)
    return f"{prefijo}-{dni}-{dv}"


def dia_habil_futuro() -> date:
    """Un día hábil al azar entre 1 y 3 años adelante (evita superponer licencias entre corridas)."""
    d = date.today() + timedelta(days=random.randint(365, 3 * 365))
    while d.weekday() >= 5:
        d += timedelta(days=1)
    return d


def marca() -> str:
    """Texto único para encontrar lo que creó esta ejecución."""
    return f"E2E-{random.randint(100000, 999999)}"
