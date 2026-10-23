<?php

namespace App\Http\Controllers;

use App\Models\Concepto;
use App\Models\Empresa;
use App\Models\Feriado;
use App\Models\TipoLicencia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class EmpresaController extends Controller
{
    public const CONDICIONES_IVA = [
        'Responsable Inscripto',
        'Monotributista',
        'Exento',
        'Consumidor Final',
    ];

    public function index(Request $request)
    {
        $buscar = trim((string) $request->get('buscar'));

        $empresas = Empresa::query()
            ->withCount('usuarios')
            ->when($buscar, function ($q) use ($buscar) {
                $q->where(function ($q) use ($buscar) {
                    $q->where('razon_social', 'like', "%{$buscar}%")
                      ->orWhere('nombre_fantasia', 'like', "%{$buscar}%")
                      ->orWhere('cuit', 'like', "%{$buscar}%");
                });
            })
            ->orderBy('razon_social')
            ->paginate(15)
            ->withQueryString();

        return view('empresas.index', compact('empresas', 'buscar'));
    }

    public function create()
    {
        return view('empresas.form', [
            'empresa'     => new Empresa(['activa' => true]),
            'condiciones' => self::CONDICIONES_IVA,
        ]);
    }

    public function store(Request $request)
    {
        $datos = $this->validar($request);
        $datos['logo'] = $this->guardarLogo($request);

        $empresa = Empresa::create($datos);

        // Configuración inicial: tipos de licencia de la LCT y feriados fijos del año
        TipoLicencia::cargarPredeterminados($empresa->id);
        Feriado::cargarFijos($empresa->id, now()->year);
        Concepto::cargarPredeterminados($empresa->id);

        return redirect()->route('empresas.index')->with('ok', 'Empresa creada con los tipos de licencia de la LCT, los feriados fijos del año y los conceptos de liquidación.');
    }

    public function edit(Empresa $empresa)
    {
        return view('empresas.form', [
            'empresa'     => $empresa,
            'condiciones' => self::CONDICIONES_IVA,
        ]);
    }

    public function update(Request $request, Empresa $empresa)
    {
        $datos = $this->validar($request, $empresa);

        if ($request->hasFile('logo')) {
            if ($empresa->logo) {
                Storage::disk('public')->delete($empresa->logo);
            }
            $datos['logo'] = $this->guardarLogo($request);
        } else {
            unset($datos['logo']);
        }

        $empresa->update($datos);

        return redirect()->route('empresas.index')->with('ok', 'Empresa actualizada correctamente.');
    }

    public function toggle(Empresa $empresa)
    {
        $empresa->update(['activa' => ! $empresa->activa]);

        return back()->with('ok', $empresa->activa ? 'Empresa activada.' : 'Empresa desactivada.');
    }

    public function destroy(Empresa $empresa)
    {
        $empresa->delete(); // soft delete

        return redirect()->route('empresas.index')->with('ok', 'Empresa eliminada.');
    }

    private function validar(Request $request, ?Empresa $empresa = null): array
    {
        $datos = $request->validate([
            'razon_social'    => ['required', 'string', 'max:255'],
            'nombre_fantasia' => ['nullable', 'string', 'max:255'],
            'cuit'            => [
                'required',
                'regex:/^\d{2}-\d{8}-\d$/',
                Rule::unique('empresas', 'cuit')->ignore($empresa?->id),
                function ($attr, $valor, $fail) {
                    if (! self::cuitValido($valor)) {
                        $fail('El CUIT no es válido (dígito verificador incorrecto).');
                    }
                },
            ],
            'condicion_iva'   => ['nullable', Rule::in(self::CONDICIONES_IVA)],
            'domicilio'       => ['nullable', 'string', 'max:255'],
            'localidad'       => ['nullable', 'string', 'max:255'],
            'provincia'       => ['nullable', 'string', 'max:255'],
            'telefono'        => ['nullable', 'string', 'max:50'],
            'email'           => ['nullable', 'email', 'max:255'],
            'logo'            => ['nullable', 'image', 'max:2048'],
        ], [
            'razon_social.required' => 'La razón social es obligatoria.',
            'cuit.required'         => 'El CUIT es obligatorio.',
            'cuit.regex'            => 'El CUIT debe tener el formato 30-12345678-9.',
            'cuit.unique'           => 'Ya existe una empresa con ese CUIT.',
            'logo.image'            => 'El logo debe ser una imagen.',
            'logo.max'              => 'El logo no puede superar los 2 MB.',
        ]);

        $datos['activa'] = $request->boolean('activa');

        return $datos;
    }

    private function guardarLogo(Request $request): ?string
    {
        return $request->hasFile('logo')
            ? $request->file('logo')->store('logos', 'public')
            : null;
    }

    public static function cuitValido(string $cuit): bool
    {
        $n = preg_replace('/\D/', '', $cuit);
        if (strlen($n) !== 11) {
            return false;
        }

        $mult = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $suma = 0;
        for ($i = 0; $i < 10; $i++) {
            $suma += (int) $n[$i] * $mult[$i];
        }

        $dv = 11 - ($suma % 11);
        if ($dv === 11) $dv = 0;
        if ($dv === 10) $dv = 9;

        return $dv === (int) $n[10];
    }
}
