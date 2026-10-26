<?php

use App\Models\Concepto;
use App\Services\Liquidacion\CalculoRecibo;

/**
 * Conceptos predeterminados (los mismos que carga el sistema) en memoria, sin base.
 * El id de cada concepto es su código, para poder cargar novedades.
 */
function conceptosPredeterminados(array $cambios = []): array
{
    return array_map(function (array $c) use ($cambios) {
        $concepto = new Concepto(array_merge(
            ['automatico' => true, 'activo' => true, 'valor' => null, 'base' => null, 'tope' => null, 'convenio_id' => null],
            $c,
            $cambios[$c['codigo']] ?? []
        ));
        $concepto->id = $c['codigo'];

        return $concepto;
    }, Concepto::PREDETERMINADOS);
}

function importeDe(array $calculo, int $codigo): ?float
{
    foreach ($calculo['items'] as $item) {
        if ($item['codigo'] === $codigo) {
            return $item['importe'];
        }
    }

    return null;
}

const BASICO = 1000000;

it('fuera de convenio: básico menos 17 % de aportes de ley', function () {
    $r = CalculoRecibo::calcular(['basico' => BASICO, 'dias' => 30, 'anios' => 3], conceptosPredeterminados(), [], 'mensual');

    expect($r['remunerativo'])->toBe(1000000.0)
        ->and(importeDe($r, 300))->toBe(110000.0)    // jubilación 11 %
        ->and(importeDe($r, 310))->toBe(30000.0)     // PAMI 3 %
        ->and(importeDe($r, 320))->toBe(30000.0)     // obra social 3 %
        ->and($r['descuentos'])->toBe(170000.0)
        ->and($r['neto'])->toBe(830000.0);

    // Sin convenio no corren antigüedad, presentismo ni aporte sindical
    expect(importeDe($r, 110))->toBeNull()
        ->and(importeDe($r, 120))->toBeNull()
        ->and(importeDe($r, 330))->toBeNull();
});

it('con convenio: antigüedad, presentismo y aporte sindical', function () {
    $r = CalculoRecibo::calcular(
        ['basico' => BASICO, 'dias' => 30, 'anios' => 5, 'convenio_id' => 1],
        conceptosPredeterminados(), [], 'mensual'
    );

    expect(importeDe($r, 110))->toBe(50000.0)       // 1 % × 5 años sobre el básico
        ->and(importeDe($r, 120))->toBe(87465.0)    // 8,33 % sobre 1.050.000
        ->and($r['remunerativo'])->toBe(1137465.0)
        ->and(importeDe($r, 300))->toBe(125121.15)
        ->and(importeDe($r, 330))->toBe(22749.3)    // 2 % sobre el bruto
        ->and($r['descuentos'])->toBe(216118.35)
        ->and($r['neto'])->toBe(921346.65);
});

it('una ausencia descuenta el día y hace perder el presentismo', function () {
    $r = CalculoRecibo::calcular(
        ['basico' => BASICO, 'dias' => 30, 'anios' => 5, 'convenio_id' => 1, 'ausencias' => 1],
        conceptosPredeterminados(), [], 'mensual'
    );

    expect(importeDe($r, 150))->toBe(-35000.0)     // 1.050.000 / 30
        ->and(importeDe($r, 120))->toBeNull()
        ->and($r['remunerativo'])->toBe(1015000.0)
        ->and($r['notas'])->toContain('No cobra Presentismo (1 ausencia).');
});

it('más de dos llegadas tarde también hacen perder el presentismo', function () {
    $r = CalculoRecibo::calcular(
        ['basico' => BASICO, 'anios' => 0, 'convenio_id' => 1, 'tardanzas' => 3],
        conceptosPredeterminados(), [], 'mensual'
    );

    expect(importeDe($r, 120))->toBeNull()
        ->and($r['notas'])->toContain('No cobra Presentismo (3 llegadas tarde).');
});

it('liquida proporcional cuando trabajó parte del mes', function () {
    $r = CalculoRecibo::calcular(['basico' => BASICO, 'dias' => 15], conceptosPredeterminados(), [], 'mensual');

    expect(importeDe($r, 100))->toBe(500000.0)
        ->and($r['neto'])->toBe(415000.0);
});

it('horas extra: valor hora = base / 200 y las del 100 % no se calculan sobre las del 50 %', function () {
    $r = CalculoRecibo::calcular(
        ['basico' => BASICO, 'min_extra50' => 120, 'min_extra100' => 60],
        conceptosPredeterminados(), [], 'mensual'
    );

    expect(importeDe($r, 140))->toBe(15000.0)      // 2 hs × 5.000 × 1,5
        ->and(importeDe($r, 141))->toBe(10000.0)   // 1 h × 5.000 × 2
        ->and($r['remunerativo'])->toBe(1025000.0);
});

it('plus vacacional por los días de vacaciones', function () {
    $r = CalculoRecibo::calcular(['basico' => 900000, 'dias_vacaciones' => 14], conceptosPredeterminados(), [], 'mensual');

    // 900.000 × (1/25 − 1/30) × 14 = 84.000
    expect(importeDe($r, 130))->toBe(84000.0);
});

it('los conceptos manuales solo se liquidan con novedad', function () {
    $sin = CalculoRecibo::calcular(['basico' => BASICO], conceptosPredeterminados(), [], 'mensual');
    expect(importeDe($sin, 160))->toBeNull()->and(importeDe($sin, 340))->toBeNull();

    $con = CalculoRecibo::calcular(['basico' => BASICO], conceptosPredeterminados(), [
        160 => ['cantidad' => null, 'importe' => 100000],   // premio (remunerativo: paga aportes)
        340 => ['cantidad' => null, 'importe' => 50000],    // anticipo
    ], 'mensual');

    expect($con['remunerativo'])->toBe(1100000.0)
        ->and(importeDe($con, 300))->toBe(121000.0)
        ->and(importeDe($con, 340))->toBe(50000.0)
        ->and($con['neto'])->toBe(1100000.0 - 187000.0 - 50000.0);
});

it('una novedad con cantidad reemplaza los días del sistema', function () {
    $r = CalculoRecibo::calcular(['basico' => 900000, 'dias' => 30], conceptosPredeterminados(), [
        100 => ['cantidad' => 20, 'importe' => null],
    ], 'mensual');

    expect(importeDe($r, 100))->toBe(600000.0);
});

it('aplica el tope de la base imponible de un aporte y lo deja anotado', function () {
    $r = CalculoRecibo::calcular(['basico' => BASICO], conceptosPredeterminados([320 => ['tope' => 500000]]), [], 'mensual');

    expect(importeDe($r, 320))->toBe(15000.0)
        ->and(implode(' ', $r['notas']))->toContain('Obra social: base imponible topeada');
});

it('ignora conceptos inactivos y de otro convenio', function () {
    $r = CalculoRecibo::calcular(
        ['basico' => BASICO, 'convenio_id' => 1],
        conceptosPredeterminados([310 => ['activo' => false], 330 => ['convenio_id' => 99]]),
        [], 'mensual'
    );

    expect(importeDe($r, 310))->toBeNull()
        ->and(importeDe($r, 330))->toBeNull();
});

it('SAC: mitad de la mejor remuneración proporcional a los días, sin básico', function () {
    $r = CalculoRecibo::calcular(
        ['basico' => BASICO, 'sac_mejor' => 1200000, 'sac_dias' => 181, 'sac_dias_semestre' => 181],
        conceptosPredeterminados(), [], 'sac'
    );

    expect(importeDe($r, 100))->toBeNull()
        ->and(importeDe($r, 170))->toBe(600000.0)
        ->and($r['neto'])->toBe(498000.0);

    $medio = CalculoRecibo::calcular(
        ['sac_mejor' => 1200000, 'sac_dias' => 90, 'sac_dias_semestre' => 180],
        conceptosPredeterminados(), [], 'sac'
    );
    expect(importeDe($medio, 170))->toBe(300000.0);
});

it('el neto siempre es remunerativo + no remunerativo − descuentos', function (array $ctx) {
    $r = CalculoRecibo::calcular($ctx, conceptosPredeterminados(), [200 => ['cantidad' => null, 'importe' => 33333.33]], 'mensual');

    expect($r['neto'])->toBe(round($r['remunerativo'] + $r['no_remunerativo'] - $r['descuentos'], 2))
        ->and($r['no_remunerativo'])->toBe(33333.33);
})->with([
    'simple'     => [['basico' => 777777.77]],
    'convenio'   => [['basico' => 1234567.89, 'anios' => 12, 'convenio_id' => 1, 'min_extra50' => 95]],
    'ausencias'  => [['basico' => 999999.99, 'ausencias' => 2, 'dias' => 22]],
]);
