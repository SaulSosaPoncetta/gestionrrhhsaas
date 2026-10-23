<?php

namespace App\Support;

class Validadores
{
    /** CUIT / CUIL con dígito verificador (acepta con o sin guiones) */
    public static function cuit(?string $valor): bool
    {
        $n = preg_replace('/\D/', '', (string) $valor);
        if (strlen($n) !== 11) {
            return false;
        }

        $mult = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $suma = 0;
        for ($i = 0; $i < 10; $i++) {
            $suma += (int) $n[$i] * $mult[$i];
        }

        $dv = 11 - ($suma % 11);
        if ($dv === 11) $dv = 0;
        if ($dv === 10) $dv = 9;

        return $dv === (int) $n[10];
    }

    /** CBU de 22 dígitos con sus dos dígitos verificadores */
    public static function cbu(?string $valor): bool
    {
        $n = preg_replace('/\D/', '', (string) $valor);
        if (strlen($n) !== 22) {
            return false;
        }

        // Bloque 1: banco + sucursal (8 dígitos)
        $p1 = [7, 1, 3, 9, 7, 1, 3];
        $s1 = 0;
        for ($i = 0; $i < 7; $i++) {
            $s1 += (int) $n[$i] * $p1[$i];
        }
        if ((10 - $s1 % 10) % 10 !== (int) $n[7]) {
            return false;
        }

        // Bloque 2: cuenta (14 dígitos)
        $p2 = [3, 9, 7, 1, 3, 9, 7, 1, 3, 9, 7, 1, 3];
        $s2 = 0;
        for ($i = 0; $i < 13; $i++) {
            $s2 += (int) $n[8 + $i] * $p2[$i];
        }

        return (10 - $s2 % 10) % 10 === (int) $n[21];
    }

    /** Formatea 20301234563 → 20-30123456-3 */
    public static function formatearCuit(?string $valor): ?string
    {
        $n = preg_replace('/\D/', '', (string) $valor);
        if (strlen($n) !== 11) {
            return $valor;
        }
        return substr($n, 0, 2) . '-' . substr($n, 2, 8) . '-' . substr($n, 10, 1);
    }
}
