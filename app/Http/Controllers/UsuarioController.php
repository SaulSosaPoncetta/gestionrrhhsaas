<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UsuarioController extends Controller
{
    public function index(Request $request)
    {
        $yo     = auth()->user();
        $buscar = trim((string) $request->get('buscar'));
        $rol    = $request->get('rol');
        $empId  = $request->get('empresa_id');

        $usuarios = User::query()
            ->with('empresa')
            ->when(! $yo->esSuperadmin(), fn ($q) => $q->where('empresa_id', $yo->empresa_id))
            ->when($yo->esSuperadmin() && $empId, fn ($q) => $q->where('empresa_id', $empId))
            ->when($rol, fn ($q) => $q->where('rol', $rol))
            ->when($buscar, function ($q) use ($buscar) {
                $q->where(function ($q) use ($buscar) {
                    $q->where('name', 'like', "%{$buscar}%")
                      ->orWhere('email', 'like', "%{$buscar}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('usuarios.index', [
            'usuarios' => $usuarios,
            'buscar'   => $buscar,
            'rol'      => $rol,
            'empId'    => $empId,
            'roles'    => $this->rolesPermitidos(),
            'empresas' => $yo->esSuperadmin() ? Empresa::orderBy('razon_social')->get() : collect(),
        ]);
    }

    public function create()
    {
        return view('usuarios.form', [
            'usuario'  => new User(['activo' => true, 'rol' => User::ROL_EMPLEADO]),
            'roles'    => $this->rolesPermitidos(),
            'empresas' => $this->empresasDisponibles(),
        ]);
    }

    public function store(Request $request)
    {
        $datos = $this->validar($request);

        User::create($datos);

        return redirect()->route('usuarios.index')->with('ok', 'Usuario creado correctamente.');
    }

    public function edit(User $usuario)
    {
        $this->autorizar($usuario);

        return view('usuarios.form', [
            'usuario'  => $usuario,
            'roles'    => $this->rolesPermitidos(),
            'empresas' => $this->empresasDisponibles(),
        ]);
    }

    public function update(Request $request, User $usuario)
    {
        $this->autorizar($usuario);

        $datos = $this->validar($request, $usuario);

        if (empty($datos['password'])) {
            unset($datos['password']);
        }

        // No dejar que uno mismo se desactive o se baje el rol
        if ($usuario->id === auth()->id()) {
            $datos['activo'] = true;
            $datos['rol']    = $usuario->rol;
        }

        $usuario->update($datos);

        return redirect()->route('usuarios.index')->with('ok', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $usuario)
    {
        $this->autorizar($usuario);

        if ($usuario->id === auth()->id()) {
            return back()->with('error', 'No podés eliminar tu propio usuario.');
        }

        $usuario->delete();

        return redirect()->route('usuarios.index')->with('ok', 'Usuario eliminado.');
    }

    private function validar(Request $request, ?User $usuario = null): array
    {
        $yo    = auth()->user();
        $roles = array_keys($this->rolesPermitidos());

        $reglas = [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario?->id)],
            'rol'      => ['required', Rule::in($roles)],
            'password' => [$usuario ? 'nullable' : 'required', 'confirmed', Password::min(8)],
        ];

        if ($yo->esSuperadmin()) {
            $reglas['empresa_id'] = [
                Rule::requiredIf(fn () => $request->rol !== User::ROL_SUPERADMIN),
                'nullable',
                'exists:empresas,id',
            ];
        }

        $datos = $request->validate($reglas, [
            'name.required'          => 'El nombre es obligatorio.',
            'email.required'         => 'El email es obligatorio.',
            'email.unique'           => 'Ya existe un usuario con ese email.',
            'rol.in'                 => 'El rol seleccionado no es válido.',
            'password.required'      => 'La contraseña es obligatoria.',
            'password.confirmed'     => 'Las contraseñas no coinciden.',
            'password.min'           => 'La contraseña debe tener al menos 8 caracteres.',
            'empresa_id.required'    => 'Seleccioná la empresa.',
        ]);

        $datos['activo'] = $request->boolean('activo');

        if ($yo->esSuperadmin()) {
            if ($datos['rol'] === User::ROL_SUPERADMIN) {
                $datos['empresa_id'] = null;
            }
        } else {
            $datos['empresa_id'] = $yo->empresa_id;
        }

        return $datos;
    }

    private function autorizar(User $usuario): void
    {
        $yo = auth()->user();

        if ($yo->esSuperadmin()) {
            return;
        }

        if ($usuario->empresa_id !== $yo->empresa_id || $usuario->esSuperadmin()) {
            abort(403);
        }
    }

    private function rolesPermitidos(): array
    {
        $roles = User::ROLES;

        if (! auth()->user()->esSuperadmin()) {
            unset($roles[User::ROL_SUPERADMIN]);
        }

        return $roles;
    }

    private function empresasDisponibles()
    {
        return auth()->user()->esSuperadmin()
            ? Empresa::where('activa', true)->orderBy('razon_social')->get()
            : collect();
    }
}
