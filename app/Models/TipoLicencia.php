<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;

class TipoLicencia extends Model
{
    use BelongsToEmpresa;

    protected $table = 'tipos_licencia';

    public const UNIDADES = [
        'corridos' => 'Días corridos',
        'habiles'  => 'Días hábiles',
    ];

    public const COLORES = [
        'primary'   => 'Azul',
        'success'   => 'Verde',
        'info'      => 'Celeste',
        'warning'   => 'Amarillo',
        'danger'    => 'Rojo',
        'secondary' => 'Gris',
        'dark'      => 'Negro',
    ];

    /**
     * Tipos de licencia de la Ley de Contrato de Trabajo (20.744).
     * Los convenios colectivos pueden dar más días: se editan desde la pantalla de tipos.
     */
    public const PREDETERMINADOS = [
        ['nombre' => 'Vacaciones', 'codigo' => 'VAC', 'es_vacaciones' => true, 'color' => 'success',
         'descripcion' => 'LCT art. 150: 14, 21, 28 o 35 días corridos según la antigüedad.'],
        ['nombre' => 'Enfermedad inculpable', 'codigo' => 'ENF', 'requiere_certificado' => true, 'color' => 'warning',
         'descripcion' => 'LCT art. 208: hasta 3 meses pagos por año (6 con 5 años o más de antigüedad); el doble con cargas de familia.'],
        ['nombre' => 'Accidente de trabajo (ART)', 'codigo' => 'ART', 'requiere_certificado' => true, 'color' => 'danger',
         'descripcion' => 'Ley 24.557: los primeros 10 días los paga el empleador, después la ART.'],
        ['nombre' => 'Matrimonio', 'codigo' => 'MAT', 'dias_maximos' => 10, 'requiere_certificado' => true, 'color' => 'primary',
         'descripcion' => 'LCT art. 158 c): 10 días corridos.'],
        ['nombre' => 'Nacimiento de hijo', 'codigo' => 'NAC', 'dias_maximos' => 2, 'requiere_certificado' => true, 'color' => 'primary',
         'descripcion' => 'LCT art. 158 a): 2 días corridos.'],
        ['nombre' => 'Fallecimiento de cónyuge, conviviente, hijo o padres', 'codigo' => 'FAL', 'dias_maximos' => 3, 'requiere_certificado' => true, 'color' => 'dark',
         'descripcion' => 'LCT art. 158 b): 3 días corridos.'],
        ['nombre' => 'Fallecimiento de hermano', 'codigo' => 'FALH', 'dias_maximos' => 1, 'requiere_certificado' => true, 'color' => 'dark',
         'descripcion' => 'LCT art. 158 d): 1 día.'],
        ['nombre' => 'Examen', 'codigo' => 'EXA', 'dias_maximos' => 2, 'maximo_anual' => 10, 'requiere_certificado' => true, 'color' => 'info',
         'descripcion' => 'LCT art. 158 e): 2 días corridos por examen, hasta 10 por año calendario.'],
        ['nombre' => 'Maternidad', 'codigo' => 'MATER', 'dias_maximos' => 90, 'requiere_certificado' => true, 'color' => 'primary',
         'descripcion' => 'LCT art. 177: 90 días (45 antes y 45 después del parto, o 30 y 60).'],
        ['nombre' => 'Donación de sangre', 'codigo' => 'DON', 'dias_maximos' => 1, 'requiere_certificado' => true, 'color' => 'danger',
         'descripcion' => 'Ley 22.990: el día de la donación.'],
        ['nombre' => 'Licencia sin goce de haberes', 'codigo' => 'SGH', 'con_goce' => false, 'color' => 'secondary',
         'descripcion' => 'Acordada con el empleador. No se paga.'],
    ];

    protected $fillable = [
        'empresa_id', 'nombre', 'codigo', 'unidad', 'dias_maximos', 'maximo_anual', 'con_goce',
        'requiere_certificado', 'es_vacaciones', 'color', 'descripcion', 'activo',
    ];

    protected $casts = [
        'con_goce'             => 'boolean',
        'requiere_certificado' => 'boolean',
        'es_vacaciones'        => 'boolean',
        'activo'               => 'boolean',
        'dias_maximos'         => 'integer',
        'maximo_anual'         => 'integer',
    ];

    public function licencias()
    {
        return $this->hasMany(Licencia::class);
    }

    public function getUnidadNombreAttribute(): string
    {
        return self::UNIDADES[$this->unidad] ?? $this->unidad;
    }

    public function esHabiles(): bool
    {
        return $this->unidad === 'habiles';
    }

    /** Carga los tipos de la LCT que falten; devuelve cuántos agregó */
    public static function cargarPredeterminados(int $empresaId): int
    {
        $agregados = 0;

        foreach (self::PREDETERMINADOS as $t) {
            $tipo = static::withoutGlobalScopes()->firstOrCreate(
                ['empresa_id' => $empresaId, 'nombre' => $t['nombre']],
                $t + ['unidad' => 'corridos', 'con_goce' => true, 'activo' => true]
            );
            if ($tipo->wasRecentlyCreated) {
                $agregados++;
            }
        }

        return $agregados;
    }

    /** El tipo marcado como vacaciones de la empresa del usuario */
    public static function vacaciones(): ?self
    {
        return static::where('es_vacaciones', true)->first();
    }
}
