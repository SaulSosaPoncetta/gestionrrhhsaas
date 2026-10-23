<?php

namespace App\Support;

/**
 * 1234567.89 → "Un millón doscientos treinta y cuatro mil quinientos sesenta y siete con 89/100"
 */
class NumeroALetras
{
    private const UNIDADES = [
        '', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve',
        'diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve',
        'veinte', 'veintiuno', 'veintidós', 'veintitrés', 'veinticuatro', 'veinticinco', 'veintiséis', 'veintisiete', 'veintiocho', 'veintinueve',
    ];

    private const DECENAS = [3 => 'treinta', 4 => 'cuarenta', 5 => 'cincuenta', 6 => 'sesenta', 7 => 'setenta', 8 => 'ochenta', 9 => 'noventa'];

    private const CENTENAS = [
        1 => 'ciento', 2 => 'doscientos', 3 => 'trescientos', 4 => 'cuatrocientos', 5 => 'quinientos',
        6 => 'seiscientos', 7 => 'setecientos', 8 => 'ochocientos', 9 => 'novecientos',
    ];

    public static function convertir(float $numero): string
    {
        $negativo = $numero < 0;
        $centavos = (int) round(abs($numero) * 100);
        $entero   = intdiv($centavos, 100);
        $resto    = $centavos % 100;

        $texto = self::entero($entero) . ' con ' . str_pad((string) $resto, 2, '0', STR_PAD_LEFT) . '/100';

        return ($negativo ? 'Menos ' : '') . self::mayuscula($texto);
    }

    public static function entero(int $n, bool $apocope = false): string
    {
        if ($n === 0) {
            return 'cero';
        }

        $partes   = [];
        $millones = intdiv($n, 1000000);
        $resto    = $n % 1000000;

        if ($millones) {
            $partes[] = $millones === 1 ? 'un millón' : self::entero($millones, true) . ' millones';
        }

        $miles    = intdiv($resto, 1000);
        $unidades = $resto % 1000;

        if ($miles) {
            $partes[] = $miles === 1 ? 'mil' : self::centenas($miles, true) . ' mil';
        }
        if ($unidades) {
            $partes[] = self::centenas($unidades, $apocope);
        }

        return implode(' ', $partes);
    }

    private static function centenas(int $n, bool $apocope): string
    {
        if ($n === 100) {
            return 'cien';
        }

        $c = intdiv($n, 100);
        $r = $n % 100;

        return trim(($c ? self::CENTENAS[$c] . ' ' : '') . self::decenas($r, $apocope));
    }

    private static function decenas(int $n, bool $apocope): string
    {
        if ($n < 30) {
            $texto = self::UNIDADES[$n];
            if ($apocope && $n === 1)  return 'un';
            if ($apocope && $n === 21) return 'veintiún';
            return $texto;
        }

        $d = intdiv($n, 10);
        $u = $n % 10;

        if (! $u) {
            return self::DECENAS[$d];
        }

        return self::DECENAS[$d] . ' y ' . ($apocope && $u === 1 ? 'un' : self::UNIDADES[$u]);
    }

    private static function mayuscula(string $s): string
    {
        return mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1);
    }
}
