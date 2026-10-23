<?php

namespace App\Http\Controllers;

use App\Models\Concepto;
use App\Models\Empleado;
use App\Models\Liquidacion;
use App\Models\Novedad;
use App\Models\Recibo;
use App\Models\ReciboItem;
use App\Services\Liquidacion\MotorLiquidacion;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LiquidacionController extends Controller
{
    public function __construct(private MotorLiquidacion $motor)
    {
    }

    public function index(Request $request)
    {
        $anio = (int) $request->get('anio', now()->year);

        return view('liquidaciones.index', [
            'anio'          => $anio,
            'liquidaciones' => Liquidacion::where('anio', $anio)->orderByDesc('mes')->orderBy('tipo')->get(),
            'hayConceptos'  => Concepto::exists(),
            'sugerido'      => $this->periodoSugerido(),
        ]);
    }

    public function store(Request $request)
    {
        $datos = $this->validarDatos($request, true);

        if ($datos['tipo'] === Liquidacion::SAC) {
            $datos['mes'] = $datos['mes'] <= 6 ? 6 : 12;    // el SAC se identifica por el último mes del semestre
        }

        $existe = Liquidacion::where('anio', $datos['anio'])->where('mes', $datos['mes'])->where('tipo', $datos['tipo'])->first();
        if ($existe) {
            return redirect()->route('liquidaciones.show', $existe)->with('error', 'Esa liquidación ya existe.');
        }

        $liquidacion = Liquidacion::create($datos + ['estado' => 'borrador']);
        $cantidad    = $this->motor->liquidar($liquidacion);

        return redirect()->route('liquidaciones.show', $liquidacion)
            ->with('ok', "Liquidación creada: se calcularon {$cantidad} recibos. Revisalos, cargá las novedades y cerrala.");
    }

    public function show(Liquidacion $liquidacion)
    {
        $liquidacion->load(['recibos', 'cerradaPor']);

        $novedades = Novedad::with(['empleado', 'concepto'])
            ->where('liquidacion_id', $liquidacion->id)
            ->get()
            ->sortBy(fn ($n) => ($n->empleado?->apellido ?? '') . $n->concepto?->codigo);

        $conceptosNovedad = Concepto::where('activo', true)
            ->get()
            ->filter(fn ($c) => $c->aplicaEn($liquidacion->tipo) && $c->forma !== 'basico' && $c->forma !== 'sac')
            ->sortBy(fn ($c) => [$c->forma === 'manual' ? 0 : 1, $c->codigo])
            ->values();

        return view('liquidaciones.show', [
            'liquidacion'      => $liquidacion,
            'novedades'        => $novedades,
            'conceptosNovedad' => $conceptosNovedad,
            'empleados'        => Empleado::where(function ($q) use ($liquidacion) {
                    $q->whereIn('id', $liquidacion->recibos->pluck('empleado_id'))->orWhereNull('fecha_egreso');
                })
                ->orderBy('apellido')->orderBy('nombre')->get(),
        ]);
    }

    /** Datos de pago y del último depósito de aportes */
    public function update(Request $request, Liquidacion $liquidacion)
    {
        $liquidacion->update($this->validarDatos($request, false));

        return back()->with('ok', 'Datos de la liquidación actualizados.');
    }

    public function calcular(Liquidacion $liquidacion)
    {
        if (! $liquidacion->esBorrador()) {
            return back()->with('error', 'La liquidación está cerrada. Reabrila para recalcular.');
        }

        $cantidad = $this->motor->liquidar($liquidacion);

        return back()->with('ok', "Se recalcularon {$cantidad} recibos con los datos actuales de legajos, asistencia y licencias.");
    }

    public function cerrar(Liquidacion $liquidacion)
    {
        if (! $liquidacion->esBorrador()) {
            return back()->with('error', 'La liquidación ya está cerrada.');
        }
        if (! $liquidacion->cantidad_recibos) {
            return back()->with('error', 'No hay recibos para cerrar.');
        }

        $liquidacion->update(['estado' => 'cerrada', 'cerrada_at' => now(), 'cerrada_por' => auth()->id()]);

        return back()->with('ok', 'Liquidación cerrada. Los recibos ya no cambian aunque se modifiquen legajos o asistencia.');
    }

    public function reabrir(Liquidacion $liquidacion)
    {
        if ($liquidacion->esBorrador()) {
            return back();
        }

        $liquidacion->update(['estado' => 'borrador', 'cerrada_at' => null, 'cerrada_por' => null]);

        return back()->with('aviso', 'Liquidación reabierta. Si la recalculás, los recibos se generan de nuevo con los datos actuales.');
    }

    public function destroy(Liquidacion $liquidacion)
    {
        if (! $liquidacion->esBorrador()) {
            return back()->with('error', 'No se puede eliminar una liquidación cerrada. Reabrila primero.');
        }

        $periodo = $liquidacion->periodo_texto;
        $liquidacion->delete();

        return redirect()->route('liquidaciones.index', ['anio' => $liquidacion->anio])->with('ok', "Liquidación {$periodo} eliminada.");
    }

    // ---------- Novedades ----------

    public function guardarNovedad(Request $request, Liquidacion $liquidacion)
    {
        if (! $liquidacion->esBorrador()) {
            return back()->with('error', 'La liquidación está cerrada.');
        }

        $empresaId = auth()->user()->empresa_id;

        $datos = $request->validate([
            'empleado_id' => ['required', Rule::exists('empleados', 'id')->where('empresa_id', $empresaId)],
            'concepto_id' => ['required', Rule::exists('conceptos', 'id')->where('empresa_id', $empresaId)],
            'cantidad'    => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'importe'     => ['nullable', 'numeric', 'min:-999999999', 'max:999999999'],
            'observacion' => ['nullable', 'string', 'max:255'],
        ], [
            'empleado_id.required' => 'Seleccioná el empleado.',
            'concepto_id.required' => 'Seleccioná el concepto.',
        ]);

        $concepto = Concepto::findOrFail($datos['concepto_id']);

        if ($concepto->forma === 'manual' && ($datos['importe'] ?? null) === null) {
            throw ValidationException::withMessages(['importe' => "«{$concepto->nombre}» es manual: ingresá el importe."]);
        }
        if (($datos['cantidad'] ?? null) === null && ($datos['importe'] ?? null) === null) {
            throw ValidationException::withMessages(['cantidad' => 'Ingresá la cantidad o el importe.']);
        }

        Novedad::updateOrCreate(
            ['liquidacion_id' => $liquidacion->id, 'empleado_id' => $datos['empleado_id'], 'concepto_id' => $concepto->id],
            ['cantidad' => $datos['cantidad'] ?? null, 'importe' => $datos['importe'] ?? null, 'observacion' => $datos['observacion'] ?? null]
        );

        $this->motor->liquidar($liquidacion);

        return redirect()->to(route('liquidaciones.show', $liquidacion) . '#novedades')->with('ok', 'Novedad guardada y liquidación recalculada.');
    }

    public function eliminarNovedad(Liquidacion $liquidacion, Novedad $novedad)
    {
        abort_unless((int) $novedad->liquidacion_id === (int) $liquidacion->id, 404);

        if (! $liquidacion->esBorrador()) {
            return back()->with('error', 'La liquidación está cerrada.');
        }

        $novedad->delete();
        $this->motor->liquidar($liquidacion);

        return redirect()->to(route('liquidaciones.show', $liquidacion) . '#novedades')->with('ok', 'Novedad eliminada y liquidación recalculada.');
    }

    // ---------- Recibos ----------

    public function recibo(Liquidacion $liquidacion, Recibo $recibo)
    {
        abort_unless((int) $recibo->liquidacion_id === (int) $liquidacion->id, 404);

        return $this->vistaRecibos($liquidacion, collect([$recibo->load('items')]));
    }

    public function imprimir(Liquidacion $liquidacion)
    {
        return $this->vistaRecibos($liquidacion, $liquidacion->recibos()->with('items')->get());
    }

    /** Libro de sueldos simplificado: una fila por empleado, una columna por concepto */
    public function exportar(Liquidacion $liquidacion)
    {
        $recibos = $liquidacion->recibos()->with('items')->get();
        $columnas = ReciboItem::whereIn('recibo_id', $recibos->pluck('id'))
            ->select('codigo', 'nombre', 'tipo')
            ->distinct()
            ->orderBy('codigo')
            ->get()
            ->unique('codigo');

        $nombre = 'liquidacion-' . $liquidacion->anio . '-' . str_pad($liquidacion->mes, 2, '0', STR_PAD_LEFT) . '-' . $liquidacion->tipo . '.csv';
        $num = fn ($v) => number_format((float) $v, 2, ',', '');

        return response()->streamDownload(function () use ($recibos, $columnas, $num) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, array_merge(
                ['Legajo', 'Apellido y nombre', 'CUIL', 'Categoría', 'Días'],
                $columnas->map(fn ($c) => "{$c->codigo} {$c->nombre}")->all(),
                ['Total remunerativo', 'Total no remunerativo', 'Total descuentos', 'Neto', 'Banco', 'CBU']
            ), ';');

            foreach ($recibos as $r) {
                $porCodigo = $r->items->keyBy('codigo');
                fputcsv($out, array_merge(
                    [$r->legajo, $r->apellido_nombre, $r->cuil, $r->categoria, $r->dias_trabajados],
                    $columnas->map(fn ($c) => isset($porCodigo[$c->codigo]) ? $num($porCodigo[$c->codigo]->importe) : '')->all(),
                    [$num($r->total_remunerativo), $num($r->total_no_remunerativo), $num($r->total_descuentos), $num($r->neto), $r->banco, $r->cbu]
                ), ';');
            }

            fclose($out);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // -----------------------------------------------------------------

    private function vistaRecibos(Liquidacion $liquidacion, $recibos)
    {
        return view('liquidaciones.recibos', [
            'liquidacion' => $liquidacion,
            'recibos'     => $recibos,
            'empresa'     => auth()->user()->empresa,
        ]);
    }

    private function validarDatos(Request $request, bool $alta): array
    {
        $reglas = [
            'descripcion'      => ['nullable', 'string', 'max:255'],
            'fecha_pago'       => ['nullable', 'date'],
            'lugar_pago'       => ['nullable', 'string', 'max:255'],
            'deposito_periodo' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'deposito_fecha'   => ['nullable', 'date'],
            'deposito_banco'   => ['nullable', 'string', 'max:255'],
        ];

        if ($alta) {
            $reglas += [
                'anio' => ['required', 'integer', 'min:2000', 'max:2100'],
                'mes'  => ['required', 'integer', 'min:1', 'max:12'],
                'tipo' => ['required', Rule::in(array_keys(Liquidacion::TIPOS))],
            ];
        }

        return $request->validate($reglas, [
            'deposito_periodo.regex' => 'El período del depósito tiene que ser AAAA-MM.',
        ]);
    }

    /** Mes siguiente al último liquidado (o el mes actual) */
    private function periodoSugerido(): array
    {
        $ultima = Liquidacion::where('tipo', Liquidacion::MENSUAL)->orderByDesc('anio')->orderByDesc('mes')->first();
        if (! $ultima) {
            return ['anio' => now()->year, 'mes' => now()->month];
        }
        $siguiente = $ultima->desde()->addMonth();

        return ['anio' => $siguiente->year, 'mes' => $siguiente->month];
    }
}
