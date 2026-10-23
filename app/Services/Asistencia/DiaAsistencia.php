<?php

namespace App\Services\Asistencia;

use Carbon\Carbon;

/**
 * Resultado del cálculo de asistencia de un empleado en un día.
 */
class DiaAsistencia
{
    /** estado => [etiqueta, color Bootstrap] */
    public const ESTADOS = [
        'presente'          => ['Presente', 'success'],
        'tarde'             => ['Llegada tarde', 'warning'],
        'trabajando'        => ['En jornada', 'primary'],
        'incompleto'        => ['Fichada incompleta', 'info'],
        'ausente'           => ['Ausente', 'danger'],
        'licencia'          => ['Licencia', 'info'],
        'franco'            => ['Franco', 'secondary'],
        'franco_trabajado'  => ['Trabajó en franco', 'primary'],
        'feriado'           => ['Feriado', 'secondary'],
        'feriado_trabajado' => ['Trabajó en feriado', 'primary'],
        'sin_horario'       => ['Sin horario', 'light'],
        'pendiente'         => ['Pendiente', 'light'],
        'fuera'             => ['No vigente', 'light'],
    ];

    public Carbon $fecha;

    public ?Carbon $entradaProg = null;
    public ?Carbon $salidaProg  = null;
    public ?Carbon $entradaReal = null;
    public ?Carbon $salidaReal  = null;

    /** @var array<int, array{entrada: ?\App\Models\Fichada, salida: ?\App\Models\Fichada}> */
    public array $jornadas = [];

    public int $minutosProgramados      = 0;
    public int $minutosTrabajados       = 0;
    public int $minutosTarde            = 0;
    public int $minutosSalidaAnticipada = 0;
    public int $extra50                 = 0;
    public int $extra100                = 0;

    public bool $incompleto = false;

    public ?string $feriado     = null;
    public ?string $feriadoTipo = null;
    public ?string $licencia    = null;   // nombre del tipo de licencia aprobada
    public bool $licenciaConGoce = true;

    public string $estado = 'pendiente';

    public function __construct(Carbon $fecha)
    {
        $this->fecha = $fecha;
    }

    public function etiqueta(): string
    {
        return self::ESTADOS[$this->estado][0] ?? $this->estado;
    }

    public function color(): string
    {
        return self::ESTADOS[$this->estado][1] ?? 'secondary';
    }

    public function tieneProgramacion(): bool
    {
        return $this->entradaProg !== null;
    }

    public function asistio(): bool
    {
        return in_array($this->estado, ['presente', 'tarde', 'trabajando', 'incompleto', 'franco_trabajado', 'feriado_trabajado'], true);
    }

    /** Texto del horario programado: "08:00 a 17:00", "Franco", "Feriado" */
    public function textoProgramado(): string
    {
        if ($this->entradaProg) {
            return $this->entradaProg->format('H:i') . ' a ' . $this->salidaProg->format('H:i');
        }
        if ($this->feriado) {
            return 'Feriado';
        }
        if ($this->licencia) {
            return 'Licencia';
        }
        return in_array($this->estado, ['sin_horario', 'fuera'], true) ? '—' : 'Franco';
    }

    /** Lista plana de fichadas del día, ordenadas */
    public function fichadas(): array
    {
        $lista = [];
        foreach ($this->jornadas as $j) {
            if ($j['entrada']) $lista[] = $j['entrada'];
            if ($j['salida'])  $lista[] = $j['salida'];
        }
        usort($lista, fn ($a, $b) => $a->fecha_hora <=> $b->fecha_hora);

        return $lista;
    }
}
