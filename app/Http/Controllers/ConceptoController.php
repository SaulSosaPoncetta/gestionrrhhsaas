<?php

namespace App\Http\Controllers;

use App\Models\Concepto;
use App\Models\Convenio;
use App\Models\ReciboItem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ConceptoController extends Controller
{
    /** Formas que necesitan "valor" y "base" */
    private const CON_VALOR = ['antiguedad', 'porcentaje', 'fijo', 'presentismo', 'horas_extra_50', 'horas_extra_100', 'aporte'];
    private const CON_BASE  = ['antiguedad', 'porcentaje', 'presentismo', 'ausencias', 'sin_goce', 'plus_vacacional', 'horas_extra_50', 'horas_extra_100', 'aporte'];

    public function index()
    {
        $conceptos = Concepto::with('convenio')
            ->orderByRaw("CASE tipo WHEN 'remunerativo' THEN 1 WHEN 'no_remunerativo' THEN 2 ELSE 3 END")
            ->orderBy('orden')->orderBy('codigo')
            ->get()
            ->groupBy('tipo');

        return view('conceptos.index', compact('conceptos'));
    }

    public function create()
    {
        return view('conceptos.form', [
            'concepto'  => new Concepto(['tipo' => 'remunerativo', 'forma' => 'manual', 'automatico' => false, 'activo' => true,
                                         'codigo' => (int) Concepto::max('codigo') + 1, 'orden' => 90]),
            'convenios' => Convenio::orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request)
    {
        Concepto::create($this->validar($request));

        return redirect()->route('conceptos.index')->with('ok', 'Concepto creado.');
    }

    public function edit(Concepto $concepto)
    {
        return view('conceptos.form', [
            'concepto'  => $concepto,
            'convenios' => Convenio::orderBy('nombre')->get(),
        ]);
    }

    public function update(Request $request, Concepto $concepto)
    {
        $concepto->update($this->validar($request, $concepto));

        return redirect()->route('conceptos.index')->with('ok', 'Concepto actualizado. Recalculá las liquidaciones en borrador para aplicar el cambio.');
    }

    public function destroy(Concepto $concepto)
    {
        if (ReciboItem::where('concepto_id', $concepto->id)->exists()) {
            return back()->with('error', 'No se puede eliminar: el concepto ya se usó en recibos. Podés desactivarlo.');
        }

        $concepto->delete();

        return redirect()->route('conceptos.index')->with('ok', 'Concepto eliminado.');
    }

    public function predeterminados()
    {
        $agregados = Concepto::cargarPredeterminados(auth()->user()->empresa_id);

        return redirect()->route('conceptos.index')->with('ok', $agregados
            ? "Se agregaron {$agregados} conceptos."
            : 'Los conceptos predeterminados ya estaban cargados.');
    }

    private function validar(Request $request, ?Concepto $concepto = null): array
    {
        $empresaId = auth()->user()->empresa_id;

        $datos = $request->validate([
            'codigo'      => ['required', 'integer', 'min:1', 'max:9999',
                              Rule::unique('conceptos', 'codigo')->where('empresa_id', $empresaId)->ignore($concepto?->id)],
            'nombre'      => ['required', 'string', 'max:255'],
            'tipo'        => ['required', Rule::in(array_keys(Concepto::TIPOS))],
            'forma'       => ['required', Rule::in(array_keys(Concepto::FORMAS))],
            'valor'       => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'base'        => ['nullable', Rule::in(array_keys(Concepto::BASES))],
            'tope'        => ['nullable', 'numeric', 'min:0'],
            'convenio_id' => ['nullable', Rule::exists('convenios', 'id')->where('empresa_id', $empresaId)],
            'orden'       => ['required', 'integer', 'min:0', 'max:9999'],
            'max_ausencias' => ['nullable', 'integer', 'min:0', 'max:31'],
            'max_tardanzas' => ['nullable', 'integer', 'min:0', 'max:31'],
        ], [
            'codigo.required' => 'El código es obligatorio.',
            'codigo.unique'   => 'Ya existe un concepto con ese código.',
            'nombre.required' => 'El nombre es obligatorio.',
        ]);

        $errores = [];
        if (in_array($datos['forma'], self::CON_VALOR, true) && ($datos['valor'] ?? null) === null) {
            $errores['valor'] = 'Esta forma de cálculo necesita un valor (' . (Concepto::FORMAS[$datos['forma']][1] ?? 'valor') . ').';
        }
        if (in_array($datos['forma'], self::CON_BASE, true) && empty($datos['base'])) {
            $errores['base'] = 'Elegí sobre qué base se calcula.';
        }
        if ($datos['forma'] === 'aporte' && $datos['tipo'] !== Concepto::DESCUENTO) {
            $errores['tipo'] = 'Los aportes son descuentos.';
        }
        if (in_array($datos['forma'], ['basico', 'antiguedad', 'presentismo', 'horas_extra_50', 'horas_extra_100', 'sac', 'plus_vacacional', 'ausencias', 'sin_goce'], true)
            && $datos['tipo'] !== Concepto::REMUNERATIVO) {
            $errores['tipo'] = 'Esta forma de cálculo solo puede ser remunerativa.';
        }
        if ($errores) {
            throw ValidationException::withMessages($errores);
        }

        $parametros = [];
        if ($request->boolean('solo_con_convenio')) {
            $parametros['solo_con_convenio'] = true;
        }
        if ($datos['forma'] === 'presentismo') {
            $parametros['max_ausencias'] = (int) ($datos['max_ausencias'] ?? 0);
            $parametros['max_tardanzas'] = (int) ($datos['max_tardanzas'] ?? 2);
        }
        unset($datos['max_ausencias'], $datos['max_tardanzas']);

        if (! in_array($datos['forma'], self::CON_BASE, true)) {
            $datos['base'] = null;
        }
        if ($datos['forma'] !== 'aporte') {
            $datos['tope'] = null;
        }

        return $datos + [
            'parametros' => $parametros ?: null,
            'automatico' => $datos['forma'] === 'manual' ? false : $request->boolean('automatico'),
            'activo'     => $request->boolean('activo'),
        ];
    }
}
