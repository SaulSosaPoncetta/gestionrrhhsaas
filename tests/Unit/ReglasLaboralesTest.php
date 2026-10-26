<?php

use App\Models\Empleado;
use App\Models\Feriado;
use App\Models\Horario;
use App\Models\Liquidacion;
use App\Services\Licencias\CalculadorVacaciones;
use App\Services\Liquidacion\MotorLiquidacion;
use Carbon\Carbon;

afterEach(fn () => Carbon::setTestNow());

describe('Vacaciones (LCT art. 150)', function () {
    it('asigna los días según la antigüedad al 31/12', function (string $ingreso, int $anio, int $dias) {
        expect(CalculadorVacaciones::diasPorAntiguedad(Carbon::parse($ingreso), $anio))->toBe($dias);
    })->with([
        'menos de 5 años'      => ['2023-03-01', 2026, 14],
        'justo 5 años al 31/12' => ['2021-12-31', 2026, 14],
        'más de 5 años'        => ['2021-06-01', 2026, 21],
        'más de 10 años'       => ['2015-01-10', 2026, 28],
        'más de 20 años'       => ['2000-01-10', 2026, 35],
    ]);

    it('el período vacacional va de octubre a abril', function (string $hoy, int $periodo) {
        Carbon::setTestNow($hoy);
        expect(CalculadorVacaciones::periodoActual())->toBe($periodo);
    })->with([
        ['2027-01-15', 2026],
        ['2027-04-30', 2026],
        ['2027-05-01', 2027],
        ['2026-10-26', 2026],
    ]);

    it('avisa si no empiezan un lunes o caen fuera de temporada', function () {
        $lunes = CalculadorVacaciones::avisos(Carbon::parse('2026-12-07'), Carbon::parse('2026-12-20'));
        expect($lunes)->toBeEmpty();

        $avisos = CalculadorVacaciones::avisos(Carbon::parse('2026-07-08'), Carbon::parse('2026-07-21'));
        expect($avisos)->toHaveCount(2);
    });
});

describe('Horario', function () {
    it('devuelve la programación del día o null si es franco', function () {
        $h = new Horario(['dias' => [1 => ['entrada' => '08:00', 'salida' => '17:00'], 6 => ['entrada' => '', 'salida' => '']]]);

        expect($h->programacion(1))->toBe(['entrada' => '08:00', 'salida' => '17:00'])
            ->and($h->programacion(6))->toBeNull()
            ->and($h->programacion(7))->toBeNull();
    });

    it('calcula minutos de turnos diurnos y nocturnos', function () {
        $h = new Horario(['dias' => [
            1 => ['entrada' => '08:00', 'salida' => '17:00'],
            2 => ['entrada' => '22:00', 'salida' => '06:00'],
        ]]);

        expect($h->minutosDia(1))->toBe(540)
            ->and($h->minutosDia(2))->toBe(480)
            ->and($h->minutos_semanales)->toBe(1020);
    });

    it('resume los días agrupando tramos iguales', function () {
        $t = ['entrada' => '08:00', 'salida' => '17:00'];
        $h = new Horario(['dias' => [1 => $t, 2 => $t, 3 => $t, 4 => $t, 5 => $t, 6 => ['entrada' => '08:00', 'salida' => '13:00']]]);

        expect($h->resumen)->toBe('Lun a Vie 08:00 a 17:00 · Sáb 08:00 a 13:00')
            ->and((new Horario(['dias' => []]))->resumen)->toBe('Sin días asignados');
    });
});

describe('Feriados', function () {
    it('calcula Pascua para los feriados móviles', function (int $anio, string $pascua) {
        expect(Feriado::pascua($anio)->toDateString())->toBe($pascua);
    })->with([[2024, '2024-03-31'], [2025, '2025-04-20'], [2026, '2026-04-05'], [2027, '2027-03-28']]);

    it('arma la lista de feriados fijos con carnaval y viernes santo', function () {
        $fechas = array_column(Feriado::fijosDelAnio(2026), 'fecha');

        expect($fechas)->toContain('2026-02-16', '2026-02-17', '2026-04-03', '2026-07-09', '2026-12-25')
            ->toHaveCount(12);
    });
});

describe('Períodos de liquidación', function () {
    it('mensual: del 1 al último día del mes', function () {
        $l = new Liquidacion(['anio' => 2026, 'mes' => 2, 'tipo' => 'mensual']);

        expect($l->desde()->toDateString())->toBe('2026-02-01')
            ->and($l->hasta()->toDateString())->toBe('2026-02-28')
            ->and($l->periodo_texto)->toBe('Febrero 2026');
    });

    it('SAC: el semestre completo', function () {
        $l = new Liquidacion(['anio' => 2026, 'mes' => 12, 'tipo' => 'sac']);

        expect($l->desde()->toDateString())->toBe('2026-07-01')
            ->and($l->hasta()->toDateString())->toBe('2026-12-31')
            ->and($l->periodo_texto)->toBe('SAC 2do semestre 2026');
    });

    it('días a liquidar: 30 en mes completo, proporcional en ingresos y egresos', function (string $ingreso, ?string $egreso, int $dias) {
        $e = new Empleado(['fecha_ingreso' => $ingreso, 'fecha_egreso' => $egreso]);

        expect(MotorLiquidacion::diasDelPeriodo($e, Carbon::parse('2026-02-01'), Carbon::parse('2026-02-28')))->toBe($dias);
    })->with([
        'mes completo (febrero)' => ['2020-01-01', null, 30],
        'ingresó el 15'          => ['2026-02-15', null, 14],
        'egresó el 10'           => ['2020-01-01', '2026-02-10', 10],
        'egresó antes del mes'   => ['2020-01-01', '2026-01-20', 0],
    ]);
});
