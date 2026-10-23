<?php

namespace Database\Seeders;

use App\Models\Concepto;
use App\Models\Empleado;
use App\Models\Empresa;
use App\Models\Liquidacion;
use App\Models\Novedad;
use App\Services\Asistencia\CalculadorAsistencia;
use App\Services\Liquidacion\MotorLiquidacion;
use Illuminate\Database\Seeder;

/**
 * Conceptos de liquidación, la liquidación del mes pasado (cerrada) y la del mes actual (borrador con novedades).
 */
class LiquidacionDemoSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::where('cuit', '30-71234567-1')->first();
        if (! $empresa) {
            return;
        }
        $e = $empresa->id;

        Concepto::cargarPredeterminados($e);

        if (Liquidacion::where('empresa_id', $e)->exists()) {
            return;
        }

        $motor   = new MotorLiquidacion(new CalculadorAsistencia());
        $anterior = today()->subMonth();

        // Mes pasado: calculada y cerrada
        $liq = Liquidacion::create([
            'empresa_id'       => $e,
            'anio'             => $anterior->year,
            'mes'              => $anterior->month,
            'tipo'             => Liquidacion::MENSUAL,
            'fecha_pago'       => today()->startOfMonth()->addDays(3)->toDateString(),
            'lugar_pago'       => 'Acreditación bancaria',
            'deposito_periodo' => $anterior->copy()->subMonth()->format('Y-m'),
            'deposito_fecha'   => $anterior->copy()->startOfMonth()->addDays(9)->toDateString(),
            'deposito_banco'   => 'Banco de la Nación Argentina',
            'estado'           => 'borrador',
        ]);
        $motor->liquidar($liq);
        $liq->update(['estado' => 'cerrada', 'cerrada_at' => now()]);

        // Mes actual: borrador con novedades
        $actual = Liquidacion::create([
            'empresa_id'       => $e,
            'anio'             => today()->year,
            'mes'              => today()->month,
            'tipo'             => Liquidacion::MENSUAL,
            'lugar_pago'       => 'Acreditación bancaria',
            'deposito_periodo' => $anterior->format('Y-m'),
            'deposito_banco'   => 'Banco de la Nación Argentina',
            'estado'           => 'borrador',
        ]);

        $concepto = fn ($codigo) => Concepto::where('empresa_id', $e)->where('codigo', $codigo)->value('id');
        $empleado = fn ($legajo) => Empleado::where('empresa_id', $e)->where('legajo', $legajo)->value('id');

        $novedades = [
            [3, 340, null, 150000, 'Anticipo pedido el día 10'],   // Pereyra: anticipo
            [4, 160, null, 60000, 'Premio por objetivos'],         // Rodríguez: premio
            [5, 200, null, 45000, 'Acuerdo paritario'],            // López: suma no remunerativa
        ];
        foreach ($novedades as [$legajo, $codigo, $cantidad, $importe, $obs]) {
            Novedad::create([
                'empresa_id'     => $e,
                'liquidacion_id' => $actual->id,
                'empleado_id'    => $empleado($legajo),
                'concepto_id'    => $concepto($codigo),
                'cantidad'       => $cantidad,
                'importe'        => $importe,
                'observacion'    => $obs,
            ]);
        }

        $motor->liquidar($actual);
    }
}
