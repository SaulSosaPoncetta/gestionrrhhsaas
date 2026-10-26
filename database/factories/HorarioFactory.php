<?php

namespace Database\Factories;

use App\Models\Horario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Por defecto: lunes a viernes de 09:00 a 18:00, 10 min de tolerancia, extra desde 30 min.
 *
 * @extends Factory<Horario>
 */
class HorarioFactory extends Factory
{
    protected $model = Horario::class;

    public function definition(): array
    {
        $tramo = ['entrada' => '09:00', 'salida' => '18:00'];

        return [
            'empresa_id'           => EmpresaFactory::new(),
            'nombre'               => 'Horario ' . fake()->unique()->numberBetween(1, 99999),
            'dias'                 => [1 => $tramo, 2 => $tramo, 3 => $tramo, 4 => $tramo, 5 => $tramo],
            'tolerancia_minutos'   => 10,
            'minimo_extra_minutos' => 30,
            'activo'               => true,
        ];
    }

    /** Agrega o cambia un día (1 = lunes … 7 = domingo) */
    public function dia(int $diaIso, string $entrada, string $salida): static
    {
        return $this->state(function (array $attrs) use ($diaIso, $entrada, $salida) {
            $dias = $attrs['dias'];
            $dias[$diaIso] = ['entrada' => $entrada, 'salida' => $salida];

            return ['dias' => $dias];
        });
    }
}
