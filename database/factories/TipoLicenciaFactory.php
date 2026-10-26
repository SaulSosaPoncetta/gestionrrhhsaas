<?php

namespace Database\Factories;

use App\Models\TipoLicencia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TipoLicencia>
 */
class TipoLicenciaFactory extends Factory
{
    protected $model = TipoLicencia::class;

    public function definition(): array
    {
        return [
            'empresa_id'           => EmpresaFactory::new(),
            'nombre'               => 'Licencia ' . fake()->unique()->numberBetween(1, 99999),
            'unidad'               => 'corridos',
            'con_goce'             => true,
            'requiere_certificado' => false,
            'es_vacaciones'        => false,
            'color'                => 'info',
            'activo'               => true,
        ];
    }

    public function vacaciones(): static
    {
        return $this->state(['nombre' => 'Vacaciones', 'codigo' => 'VAC', 'es_vacaciones' => true]);
    }

    public function sinGoce(): static
    {
        return $this->state(['nombre' => 'Sin goce de haberes', 'con_goce' => false]);
    }

    public function habiles(): static
    {
        return $this->state(['unidad' => 'habiles']);
    }
}
