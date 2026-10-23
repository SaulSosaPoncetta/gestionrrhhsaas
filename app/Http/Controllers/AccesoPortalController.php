<?php

namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * RRHH administra el acceso de cada empleado al portal desde su legajo.
 * La contraseña generada se muestra una sola vez y el empleado debe cambiarla al ingresar.
 */
class AccesoPortalController extends Controller
{
    public function crear(Request $request, Empleado $empleado)
    {
        if ($empleado->usuario) {
            return back()->with('error', 'El empleado ya tiene acceso al portal.');
        }
        if (! $empleado->activo) {
            return back()->with('error', 'No se puede dar acceso a un empleado dado de baja.');
        }

        $datos = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'rol'   => ['required', Rule::in([User::ROL_EMPLEADO, User::ROL_SUPERVISOR])],
        ], [
            'email.required' => 'Ingresá el email con el que va a entrar.',
            'email.unique'   => 'Ese email ya lo usa otro usuario.',
        ]);

        $password = self::generarPassword();

        $usuario = User::create([
            'empresa_id'            => $empleado->empresa_id,
            'name'                  => $empleado->nombre . ' ' . $empleado->apellido,
            'email'                 => $datos['email'],
            'password'              => $password,
            'rol'                   => $datos['rol'],
            'activo'                => true,
            'debe_cambiar_password' => true,
        ]);

        $empleado->update([
            'user_id'             => $usuario->id,
            'permite_fichaje_web' => $request->boolean('permite_fichaje_web'),
            'email'               => $empleado->email ?: $datos['email'],
        ]);

        return back()->with('acceso', ['email' => $usuario->email, 'password' => $password, 'nuevo' => true]);
    }

    public function actualizar(Request $request, Empleado $empleado)
    {
        $usuario = $empleado->usuario;
        abort_unless($usuario, 404);

        $datos = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario->id)],
            'rol'   => ['required', Rule::in([User::ROL_EMPLEADO, User::ROL_SUPERVISOR])],
        ], [
            'email.unique' => 'Ese email ya lo usa otro usuario.',
        ]);

        $usuario->update($datos + ['activo' => $request->boolean('activo')]);
        $empleado->update(['permite_fichaje_web' => $request->boolean('permite_fichaje_web')]);

        return back()->with('ok', 'Acceso al portal actualizado.');
    }

    public function resetear(Empleado $empleado)
    {
        $usuario = $empleado->usuario;
        abort_unless($usuario, 404);

        $password = self::generarPassword();
        $usuario->update(['password' => $password, 'debe_cambiar_password' => true, 'activo' => true]);

        return back()->with('acceso', ['email' => $usuario->email, 'password' => $password, 'nuevo' => false]);
    }

    public function quitar(Empleado $empleado)
    {
        $usuario = $empleado->usuario;
        abort_unless($usuario, 404);

        // Se desactiva (no se borra) para conservar el historial de quién aprobó o registró cosas
        $usuario->update(['activo' => false]);
        $empleado->update(['user_id' => null, 'permite_fichaje_web' => false]);

        return back()->with('ok', 'Se quitó el acceso al portal.');
    }

    /** Contraseña temporal fácil de dictar: sin caracteres que se confunden (0/O, 1/l) */
    public static function generarPassword(): string
    {
        $letras  = 'abcdefghjkmnpqrstuvwxyz';
        $numeros = '23456789';
        $p = '';
        for ($i = 0; $i < 6; $i++) {
            $p .= $letras[random_int(0, strlen($letras) - 1)];
        }
        for ($i = 0; $i < 4; $i++) {
            $p .= $numeros[random_int(0, strlen($numeros) - 1)];
        }

        return ucfirst($p);
    }
}
