<?php

namespace Database\Factories;

use App\Models\Empresa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Uso: EmpresaFactory::new()->create()  (el modelo no usa HasFactory)
 *
 * @extends Factory<Empresa>
 */
class EmpresaFactory extends Factory
{
    protected $model = Empresa::class;

    public function definition(): array
    {
        return [
            'razon_social'    => fake()->company() . ' S.A.',
            'nombre_fantasia' => fake()->optional()->company(),
            'cuit'            => self::cuitValido('30'),
            'condicion_iva'   => 'Responsable Inscripto',
            'provincia'       => 'Buenos Aires',
            'activa'          => true,
        ];
    }

    public function inactiva(): static
    {
        return $this->state(['activa' => false]);
    }

    /** CUIT / CUIL con dígito verificador correcto: 20-12345678-X */
    public static function cuitValido(string $prefijo = '20', ?string $numero = null): string
    {
        static $usados = [];

        do {
            $n    = $numero ?? (string) fake()->numberBetween(10000000, 49999999);
            $base = $prefijo . $n;
            $mult = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
            $suma = 0;
            for ($i = 0; $i < 10; $i++) {
                $suma += (int) $base[$i] * $mult[$i];
            }
            $dv = 11 - ($suma % 11);
            if ($dv === 11) $dv = 0;
            if ($dv === 10) $dv = 9;
            $cuit = "{$prefijo}-{$n}-{$dv}";
        } while ($numero === null && isset($usados[$cuit]));

        $usados[$cuit] = true;

        return $cuit;
    }
}
