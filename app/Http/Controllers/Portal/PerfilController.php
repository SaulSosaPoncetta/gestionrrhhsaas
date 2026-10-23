<?php

namespace App\Http\Controllers\Portal;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class PerfilController extends PortalController
{
    public function show()
    {
        return view('portal.datos', [
            'empleado' => $this->empleado()->load(['area', 'puesto', 'categoria', 'convenio', 'horario', 'supervisor', 'familiares']),
        ]);
    }

    /** El empleado solo puede actualizar sus datos de contacto */
    public function update(Request $request)
    {
        $datos = $request->validate([
            'domicilio'           => ['nullable', 'string', 'max:255'],
            'localidad'           => ['nullable', 'string', 'max:255'],
            'provincia'           => ['nullable', 'string', 'max:255'],
            'codigo_postal'       => ['nullable', 'string', 'max:10'],
            'telefono'            => ['nullable', 'string', 'max:50'],
            'email'               => ['nullable', 'email', 'max:255'],
            'contacto_emergencia' => ['nullable', 'string', 'max:255'],
            'telefono_emergencia' => ['nullable', 'string', 'max:50'],
        ], [
            'email.email' => 'El email no es válido.',
        ]);

        $this->empleado()->update($datos);

        return back()->with('ok', 'Tus datos se actualizaron.');
    }

    // ---------- Contraseña (para todos los usuarios, no solo el portal) ----------

    public function editarPassword()
    {
        return view('portal.password', ['obligatorio' => (bool) auth()->user()->debe_cambiar_password]);
    }

    public function actualizarPassword(Request $request)
    {
        $request->validate([
            'actual'   => ['required'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'actual.required'    => 'Ingresá tu contraseña actual.',
            'password.required'  => 'Ingresá la contraseña nueva.',
            'password.confirmed' => 'Las contraseñas nuevas no coinciden.',
            'password.min'       => 'La contraseña nueva debe tener al menos 8 caracteres.',
            'password.letters'   => 'La contraseña nueva debe tener al menos una letra.',
            'password.numbers'   => 'La contraseña nueva debe tener al menos un número.',
        ]);

        $user = $request->user();

        if (! Hash::check($request->actual, $user->password)) {
            throw ValidationException::withMessages(['actual' => 'La contraseña actual no es correcta.']);
        }
        if (Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages(['password' => 'La contraseña nueva tiene que ser distinta de la actual.']);
        }

        $user->update(['password' => $request->password, 'debe_cambiar_password' => false]);

        return redirect()->route('dashboard')->with('ok', 'Contraseña actualizada.');
    }
}
