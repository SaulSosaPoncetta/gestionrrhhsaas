<?php

namespace Database\Factories;

use App\Models\Empleado;
use App\Models\Empresa;
use App\Models\Horario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Uso: EmpleadoFactory::new()->deEmpresa($empresa)->create()
 * Por defecto: fuera de convenio, básico $ 1.000.000, ingreso hace 3 años, sin horario.
 *
 * @extends Factory<Empleado>
 */
class EmpleadoFactory extends Factory
{
    protected $model = Empleado::class;

    public function definition(): array
    {
        $dni = (string) fake()->unique()->numberBetween(20000000, 45999999);

        return [
            'empresa_id'    => EmpresaFactory::new(),
            'legajo'        => fake()->unique()->numberBetween(1, 999999),
            'apellido'      => fake()->lastName(),
            'nombre'        => fake()->firstName(),
            'cuil'          => EmpresaFactory::cuitValido('20', $dni),
            'dni'           => $dni,
            'fecha_ingreso' => now()->subYears(3)->startOfMonth()->toDateString(),
            'modalidad'     => 'indeterminado',
            'jornada'       => 'completa',
            'sueldo_basico' => 1000000,
            'nacionalidad'  => 'Argentina',
        ];
    }

    public function deEmpresa(Empresa $empresa): static
    {
        return $this->state(['empresa_id' => $empresa->id]);
    }

    public function conHorario(Horario $horario): static
    {
        return $this->state(['horario_id' => $horario->id]);
    }

    public function ingreso(string $fecha): static
    {
        return $this->state(['fecha_ingreso' => $fecha]);
    }

    public function deBaja(string $fecha, string $motivo = 'renuncia'): static
    {
        return $this->state(['fecha_egreso' => $fecha, 'motivo_egreso' => $motivo]);
    }
}
