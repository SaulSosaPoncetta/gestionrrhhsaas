<?php

namespace App\Services\Asistencia;

use App\Models\Empleado;
use App\Models\Fichada;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Importa fichadas desde un CSV / TXT exportado por un reloj.
 *
 * Formato por línea:   legajo ; fecha ; hora [; tipo]
 *   - Separador: punto y coma, coma o tabulación (se detecta solo).
 *   - Fecha: dd/mm/aaaa, dd-mm-aaaa o aaaa-mm-dd.   Hora: hh:mm o hh:mm:ss.
 *   - Tipo (opcional): E / ENTRADA / IN / I / 0  →  entrada;  S / SALIDA / OUT / O / 1  →  salida.
 *     Si no viene, se puede deducir alternando entrada / salida por empleado.
 *   - La primera línea se saltea si es un encabezado.
 */
class ImportadorFichadas
{
    public function importar(string $ruta, int $empresaId, int $usuarioId, bool $deducirTipo = true): array
    {
        $resultado = ['leidas' => 0, 'importadas' => 0, 'duplicadas' => 0, 'errores' => []];

        $contenido = file_get_contents($ruta);
        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido);           // BOM
        if (! mb_check_encoding($contenido, 'UTF-8')) {
            $contenido = mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252');
        }

        $lineas = preg_split('/\r\n|\r|\n/', trim($contenido));
        if (! $lineas || $lineas === ['']) {
            $resultado['errores'][] = 'El archivo está vacío.';
            return $resultado;
        }

        $separador = $this->detectarSeparador($lineas[0]);
        $legajos   = Empleado::withoutGlobalScopes()->where('empresa_id', $empresaId)->pluck('id', 'legajo');

        // 1) Leer y validar
        $filas = [];
        foreach ($lineas as $i => $linea) {
            $nro = $i + 1;
            if (trim($linea) === '') {
                continue;
            }

            $cols = array_map(fn ($c) => trim($c, " \t\"'"), str_getcsv($linea, $separador));

            if ($i === 0 && ! ctype_digit($cols[0] ?? '')) {
                continue;                                                        // encabezado
            }

            // Algunos relojes exportan fecha y hora en una sola columna: "01/10/2026 08:05"
            if (isset($cols[1]) && preg_match('/^(\S+)\s+(\d{1,2}:\d{2}(:\d{2})?)$/', $cols[1], $m)) {
                array_splice($cols, 1, 1, [$m[1], $m[2]]);
            }

            $resultado['leidas']++;

            if (count($cols) < 3) {
                $this->error($resultado, $nro, 'faltan columnas (legajo; fecha; hora).');
                continue;
            }

            [$legajo, $fecha, $hora] = $cols;
            $tipoTexto = $cols[3] ?? '';

            $empleadoId = $legajos[(int) $legajo] ?? null;
            if (! ctype_digit($legajo) || ! $empleadoId) {
                $this->error($resultado, $nro, "no existe el legajo «{$legajo}».");
                continue;
            }

            $fechaHora = $this->parsearFechaHora($fecha, $hora);
            if (! $fechaHora) {
                $this->error($resultado, $nro, "fecha u hora inválida («{$fecha} {$hora}»).");
                continue;
            }

            $tipo = $this->parsearTipo($tipoTexto);
            if ($tipoTexto !== '' && ! $tipo) {
                $this->error($resultado, $nro, "tipo desconocido «{$tipoTexto}».");
                continue;
            }
            if (! $tipo && ! $deducirTipo) {
                $this->error($resultado, $nro, 'no indica si es entrada o salida.');
                continue;
            }

            $filas[] = ['empleado_id' => $empleadoId, 'fecha_hora' => $fechaHora, 'tipo' => $tipo];
        }

        if (! $filas) {
            return $resultado;
        }

        // 2) Descartar duplicadas (en el archivo y contra la base)
        $min = collect($filas)->min('fecha_hora')->copy()->subDay();
        $max = collect($filas)->max('fecha_hora')->copy()->addDay();

        $existentes = Fichada::withoutGlobalScopes()
            ->whereIn('empleado_id', collect($filas)->pluck('empleado_id')->unique())
            ->whereBetween('fecha_hora', [$min, $max])
            ->get(['empleado_id', 'fecha_hora', 'tipo']);

        $vistas = [];
        foreach ($existentes as $f) {
            $vistas[$f->empleado_id . '|' . $f->fecha_hora->format('Y-m-d H:i:s')] = true;
        }

        $nuevas = [];
        foreach ($filas as $f) {
            $clave = $f['empleado_id'] . '|' . $f['fecha_hora']->format('Y-m-d H:i:s');
            if (isset($vistas[$clave])) {
                $resultado['duplicadas']++;
                continue;
            }
            $vistas[$clave] = true;
            $nuevas[] = $f;
        }

        // 3) Deducir el tipo de las que no lo traen
        if ($deducirTipo) {
            $nuevas = $this->deducirTipos($nuevas, $existentes);
        }

        // 4) Guardar
        $ahora = now();
        DB::transaction(function () use ($nuevas, $empresaId, $usuarioId, $ahora) {
            foreach (array_chunk($nuevas, 500) as $lote) {
                Fichada::insert(array_map(fn ($f) => [
                    'empresa_id'     => $empresaId,
                    'empleado_id'    => $f['empleado_id'],
                    'fecha_hora'     => $f['fecha_hora']->format('Y-m-d H:i:s'),
                    'tipo'           => $f['tipo'],
                    'origen'         => 'importacion',
                    'registrado_por' => $usuarioId,
                    'created_at'     => $ahora,
                    'updated_at'     => $ahora,
                ], $lote));
            }
        });

        $resultado['importadas'] = count($nuevas);

        return $resultado;
    }

    /**
     * Por empleado, en orden cronológico: después de una entrada viene una salida
     * (si pasaron menos de 16 hs); si no, es una entrada.
     */
    private function deducirTipos(array $nuevas, $existentes): array
    {
        $porEmpleado = [];

        foreach ($existentes as $f) {
            $porEmpleado[$f->empleado_id][] = ['fecha_hora' => $f->fecha_hora, 'tipo' => $f->tipo, 'idx' => null];
        }
        foreach ($nuevas as $idx => $f) {
            $porEmpleado[$f['empleado_id']][] = ['fecha_hora' => $f['fecha_hora'], 'tipo' => $f['tipo'], 'idx' => $idx];
        }

        foreach ($porEmpleado as $lista) {
            usort($lista, fn ($a, $b) => $a['fecha_hora'] <=> $b['fecha_hora']);

            $previa = null;
            foreach ($lista as $item) {
                $tipo = $item['tipo'];

                if (! $tipo) {
                    $sigueJornada = $previa
                        && $previa['tipo'] === Fichada::ENTRADA
                        && ($item['fecha_hora']->getTimestamp() - $previa['fecha_hora']->getTimestamp())
                            <= CalculadorAsistencia::MAX_HORAS_JORNADA * 3600;

                    $tipo = $sigueJornada ? Fichada::SALIDA : Fichada::ENTRADA;
                    $nuevas[$item['idx']]['tipo'] = $tipo;
                }

                $previa = ['fecha_hora' => $item['fecha_hora'], 'tipo' => $tipo];
            }
        }

        return $nuevas;
    }

    private function detectarSeparador(string $linea): string
    {
        $conteos = [';' => substr_count($linea, ';'), "\t" => substr_count($linea, "\t"), ',' => substr_count($linea, ',')];
        arsort($conteos);

        return array_key_first($conteos);
    }

    private function parsearFechaHora(string $fecha, string $hora): ?Carbon
    {
        $fecha = str_replace('.', '/', trim($fecha));
        $hora  = trim($hora);
        if (preg_match('/^\d{1,2}:\d{2}$/', $hora)) {
            $hora .= ':00';
        }

        foreach (['d/m/Y H:i:s', 'd-m-Y H:i:s', 'Y-m-d H:i:s', 'Y/m/d H:i:s', 'd/m/y H:i:s'] as $formato) {
            $dt      = \DateTime::createFromFormat('!' . $formato, "{$fecha} {$hora}");
            $errores = \DateTime::getLastErrors();
            $limpio  = ! $errores || ($errores['warning_count'] === 0 && $errores['error_count'] === 0);

            // El año >= 2000 evita que "1/10/26" se lea como el año 0026
            if ($dt && $limpio && (int) $dt->format('Y') >= 2000) {
                return Carbon::instance($dt);
            }
        }

        return null;
    }

    private function parsearTipo(string $texto): ?string
    {
        $t = mb_strtoupper(trim($texto));

        return match (true) {
            in_array($t, ['E', 'ENT', 'ENTRADA', 'IN', 'I', '0', 'CHECKIN', 'C/IN'], true)    => Fichada::ENTRADA,
            in_array($t, ['S', 'SAL', 'SALIDA', 'OUT', 'O', '1', 'CHECKOUT', 'C/OUT'], true) => Fichada::SALIDA,
            default                                                                         => null,
        };
    }

    private function error(array &$resultado, int $linea, string $motivo): void
    {
        if (count($resultado['errores']) < 50) {
            $resultado['errores'][] = "Línea {$linea}: {$motivo}";
        }
    }
}
