<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Empleado extends Model
{
    use BelongsToEmpresa, SoftDeletes;

    protected $table = 'empleados';

    public const SEXOS = ['M' => 'Masculino', 'F' => 'Femenino', 'X' => 'No binario'];

    public const ESTADOS_CIVILES = [
        'soltero'     => 'Soltero/a',
        'casado'      => 'Casado/a',
        'conviviente' => 'Unión convivencial',
        'divorciado'  => 'Divorciado/a',
        'viudo'       => 'Viudo/a',
    ];

    public const MODALIDADES = [
        'indeterminado' => 'Tiempo indeterminado',
        'plazo_fijo'    => 'Plazo fijo',
        'eventual'      => 'Eventual',
        'temporada'     => 'De temporada',
    ];

    public const JORNADAS = [
        'completa' => 'Completa',
        'parcial'  => 'Parcial',
        'reducida' => 'Reducida',
    ];

    public const MOTIVOS_EGRESO = [
        'renuncia'          => 'Renuncia',
        'despido_sin_causa' => 'Despido sin causa',
        'despido_con_causa' => 'Despido con causa',
        'mutuo_acuerdo'     => 'Mutuo acuerdo',
        'fin_contrato'      => 'Fin de contrato',
        'periodo_prueba'    => 'Fin del período de prueba',
        'jubilacion'        => 'Jubilación',
        'fallecimiento'     => 'Fallecimiento',
        'abandono'          => 'Abandono de trabajo',
        'otro'              => 'Otro',
    ];

    protected $fillable = [
        'empresa_id', 'legajo', 'apellido', 'nombre', 'cuil', 'dni', 'fecha_nacimiento', 'sexo',
        'estado_civil', 'nacionalidad', 'foto', 'domicilio', 'localidad', 'provincia', 'codigo_postal',
        'telefono', 'email', 'contacto_emergencia', 'telefono_emergencia',
        'fecha_ingreso', 'fecha_egreso', 'motivo_egreso', 'area_id', 'puesto_id', 'convenio_id',
        'categoria_id', 'supervisor_id', 'modalidad', 'jornada', 'horas_semanales', 'horario_id', 'sueldo_basico',
        'banco', 'cbu', 'obra_social', 'codigo_obra_social', 'user_id', 'permite_fichaje_web', 'observaciones',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'fecha_ingreso'    => 'date',
        'fecha_egreso'     => 'date',
        'horas_semanales'  => 'decimal:2',
        'sueldo_basico'    => 'decimal:2',
        'permite_fichaje_web' => 'boolean',
    ];

    // ---------- Relaciones ----------

    public function area()       { return $this->belongsTo(Area::class); }
    public function puesto()     { return $this->belongsTo(Puesto::class); }
    public function convenio()   { return $this->belongsTo(Convenio::class); }
    public function categoria()  { return $this->belongsTo(Categoria::class); }
    public function supervisor() { return $this->belongsTo(Empleado::class, 'supervisor_id'); }
    public function usuario()    { return $this->belongsTo(User::class, 'user_id'); }
    public function horario()    { return $this->belongsTo(Horario::class); }

    public function fichadas()
    {
        return $this->hasMany(Fichada::class)->orderBy('fecha_hora');
    }

    public function licencias()
    {
        return $this->hasMany(Licencia::class)->orderByDesc('fecha_desde');
    }

    public function aCargo()
    {
        return $this->hasMany(Empleado::class, 'supervisor_id');
    }

    public function familiares()
    {
        return $this->hasMany(EmpleadoFamiliar::class)->orderBy('parentesco');
    }

    public function documentos()
    {
        return $this->hasMany(EmpleadoDocumento::class)->latest();
    }

    // ---------- Scopes ----------

    public function scopeActivos(Builder $q): Builder
    {
        return $q->whereNull('fecha_egreso');
    }

    public function scopeDeBaja(Builder $q): Builder
    {
        return $q->whereNotNull('fecha_egreso');
    }

    // ---------- Atributos ----------

    public function getNombreCompletoAttribute(): string
    {
        return "{$this->apellido}, {$this->nombre}";
    }

    public function getInicialesAttribute(): string
    {
        return mb_strtoupper(mb_substr($this->nombre, 0, 1) . mb_substr($this->apellido, 0, 1));
    }

    public function getActivoAttribute(): bool
    {
        return is_null($this->fecha_egreso);
    }

    public function getEdadAttribute(): ?int
    {
        return $this->fecha_nacimiento?->age;
    }

    public function getAntiguedadAttribute(): string
    {
        if (! $this->fecha_ingreso) {
            return '—';
        }

        $hasta = $this->fecha_egreso ?? Carbon::today();
        $diff  = $this->fecha_ingreso->diff($hasta);

        $partes = [];
        if ($diff->y) $partes[] = $diff->y . ' ' . ($diff->y === 1 ? 'año' : 'años');
        if ($diff->m) $partes[] = $diff->m . ' ' . ($diff->m === 1 ? 'mes' : 'meses');

        return $partes ? implode(', ', $partes) : 'Menos de un mes';
    }

    /** Años completos de antigüedad (para vacaciones y liquidación) */
    public function getAniosAntiguedadAttribute(): int
    {
        if (! $this->fecha_ingreso) {
            return 0;
        }
        // diff() funciona igual en Carbon 2 y 3 (diffInYears cambió en Carbon 3)
        return $this->fecha_ingreso->diff($this->fecha_egreso ?? Carbon::today())->y;
    }

    /** Básico vigente: el de la categoría, o el propio si está fuera de convenio */
    public function getBasicoVigenteAttribute(): float
    {
        if ($this->categoria_id && $this->categoria) {
            return (float) $this->categoria->sueldo_basico;
        }
        return (float) $this->sueldo_basico;
    }

    public function getFotoUrlAttribute(): ?string
    {
        return $this->foto ? asset('storage/' . $this->foto) : null;
    }

    public function getSexoNombreAttribute(): ?string        { return self::SEXOS[$this->sexo] ?? null; }
    public function getEstadoCivilNombreAttribute(): ?string { return self::ESTADOS_CIVILES[$this->estado_civil] ?? null; }
    public function getModalidadNombreAttribute(): ?string   { return self::MODALIDADES[$this->modalidad] ?? $this->modalidad; }
    public function getJornadaNombreAttribute(): ?string     { return self::JORNADAS[$this->jornada] ?? $this->jornada; }
    public function getMotivoEgresoNombreAttribute(): ?string { return self::MOTIVOS_EGRESO[$this->motivo_egreso] ?? $this->motivo_egreso; }

    /** Próximo número de legajo libre de la empresa del usuario logueado */
    public static function proximoLegajo(): int
    {
        return (int) static::withTrashed()->max('legajo') + 1;
    }
}
