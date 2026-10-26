<?php

use App\Support\Validadores;

describe('CUIT / CUIL', function () {
    it('acepta CUIL válidos con y sin guiones', function (string $cuil) {
        expect(Validadores::cuit($cuil))->toBeTrue();
    })->with([
        '20-30123456-3',
        '20301234563',
        '27-35111222-6',
        '30-71234567-1',
    ]);

    it('rechaza dígito verificador incorrecto, largo inválido o vacío', function (?string $cuil) {
        expect(Validadores::cuit($cuil))->toBeFalse();
    })->with([
        'dv incorrecto' => '20-30123456-4',
        'corto'         => '20-3012345-3',
        'largo'         => '20-301234567-3',
        'letras'        => 'ab-cdefghij-k',
        'vacío'         => '',
        'null'          => null,
    ]);

    it('formatea 11 dígitos como XX-XXXXXXXX-X', function () {
        expect(Validadores::formatearCuit('20301234563'))->toBe('20-30123456-3')
            ->and(Validadores::formatearCuit('20-30123456-3'))->toBe('20-30123456-3');
    });

    it('devuelve el valor original si no tiene 11 dígitos', function () {
        expect(Validadores::formatearCuit('123'))->toBe('123')
            ->and(Validadores::formatearCuit(null))->toBeNull();
    });
});

describe('CBU', function () {
    it('acepta un CBU con los dos dígitos verificadores correctos', function () {
        expect(Validadores::cbu('0170099200000123456784'))->toBeTrue()
            ->and(Validadores::cbu('0170 0992 0000 0123 4567 84'))->toBeTrue();
    });

    it('rechaza CBU con verificador del banco o de la cuenta mal', function (string $cbu) {
        expect(Validadores::cbu($cbu))->toBeFalse();
    })->with([
        'bloque banco' => '0170099300000123456784',
        'bloque cuenta' => '0170099200000123456785',
        'corto'        => '017009920000012345678',
        'vacío'        => '',
    ]);
});
