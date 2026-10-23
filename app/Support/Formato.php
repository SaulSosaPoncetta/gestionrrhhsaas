<?php

namespace App\Support;

class Formato
{
    /** 125 → "2:05"   (0 o null → "—") */
    public static function horas(?int $minutos, string $vacio = '—'): string
    {
        if (! $minutos) {
            return $vacio;
        }

        $signo = $minutos < 0 ? '-' : '';
        $m     = abs($minutos);

        return $signo . intdiv($m, 60) . ':' . str_pad((string) ($m % 60), 2, '0', STR_PAD_LEFT);
    }

    /** 125 → "2,08"  (horas decimales, para exportar a la liquidación) */
    public static function horasDecimal(?int $minutos): string
    {
        return number_format(($minutos ?? 0) / 60, 2, ',', '');
    }

    public static function moneda(?float $valor): string
    {
        return '$ ' . number_format((float) $valor, 2, ',', '.');
    }
}
