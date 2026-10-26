<?php

use App\Support\Formato;
use App\Support\NumeroALetras;

describe('NumeroALetras', function () {
    it('convierte importes a letras para el recibo', function (float $numero, string $texto) {
        expect(NumeroALetras::convertir($numero))->toBe($texto);
    })->with([
        [0, 'Cero con 00/100'],
        [1, 'Uno con 00/100'],
        [21, 'Veintiuno con 00/100'],
        [100, 'Cien con 00/100'],
        [101, 'Ciento uno con 00/100'],
        [1000, 'Mil con 00/100'],
        [21000, 'Veintiún mil con 00/100'],
        [31000, 'Treinta y un mil con 00/100'],
        [100000, 'Cien mil con 00/100'],
        [1000000, 'Un millón con 00/100'],
        [2000000, 'Dos millones con 00/100'],
        [21000000, 'Veintiún millones con 00/100'],
        [1234567.89, 'Un millón doscientos treinta y cuatro mil quinientos sesenta y siete con 89/100'],
        [830000.5, 'Ochocientos treinta mil con 50/100'],
    ]);

    it('redondea los centavos y marca los negativos', function () {
        expect(NumeroALetras::convertir(10.999))->toBe('Once con 00/100')
            ->and(NumeroALetras::convertir(-5.25))->toBe('Menos Cinco con 25/100');
    });
});

describe('Formato', function () {
    it('muestra minutos como horas', function () {
        expect(Formato::horas(125))->toBe('2:05')
            ->and(Formato::horas(60))->toBe('1:00')
            ->and(Formato::horas(-90))->toBe('-1:30')
            ->and(Formato::horas(0))->toBe('—')
            ->and(Formato::horas(null, '-'))->toBe('-');
    });

    it('exporta horas decimales con coma', function () {
        expect(Formato::horasDecimal(125))->toBe('2,08')
            ->and(Formato::horasDecimal(null))->toBe('0,00');
    });

    it('formatea moneda argentina', function () {
        expect(Formato::moneda(1234567.5))->toBe('$ 1.234.567,50')
            ->and(Formato::moneda(null))->toBe('$ 0,00');
    });
});
