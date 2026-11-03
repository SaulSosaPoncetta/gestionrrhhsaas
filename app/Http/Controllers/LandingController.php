<?php

namespace App\Http\Controllers;

use App\Mail\SolicitudDemoMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class LandingController extends Controller
{
    public function index(Request $request)
    {
        // Si ya está logueado, va directo al sistema
        if ($request->user()) {
            return redirect()->route('dashboard');
        }

        return view('landing.index', [
            'planes' => config('landing.planes', []),
        ]);
    }

    public function solicitar(Request $request)
    {
        // Campo trampa: los bots lo completan, las personas no lo ven
        if ($request->filled('sitio_web')) {
            return redirect()->to(route('landing.index') . '#contacto');
        }

        $datos = $request->validate([
            'nombre'    => ['required', 'string', 'max:120'],
            'empresa'   => ['required', 'string', 'max:150'],
            'email'     => ['required', 'email', 'max:150'],
            'telefono'  => ['nullable', 'string', 'max:40'],
            'empleados' => ['required', 'integer', 'min:1', 'max:100000'],
            'mensaje'   => ['nullable', 'string', 'max:2000'],
        ], [
            'nombre.required'    => 'Ingresá tu nombre.',
            'empresa.required'   => 'Ingresá el nombre de tu empresa.',
            'email.required'     => 'Ingresá tu email.',
            'email.email'        => 'El email no es válido.',
            'empleados.required' => 'Indicá cuántos empleados tiene tu empresa.',
            'empleados.integer'  => 'La cantidad de empleados debe ser un número.',
            'empleados.min'      => 'La cantidad de empleados debe ser al menos 1.',
        ]);

        $datos['plan'] = $this->planPara((int) $datos['empleados']);

        $destino = config('landing.contacto_email');

        try {
            if (! $destino) {
                throw new \RuntimeException('Falta LANDING_CONTACTO_EMAIL / MAIL_FROM_ADDRESS.');
            }

            Mail::to($destino)->send(new SolicitudDemoMail($datos));
        } catch (\Throwable $e) {
            Log::error('LandingController@solicitar: ' . $e->getMessage(), ['solicitud' => $datos]);

            return redirect()->to(route('landing.index') . '#contacto')
                ->withInput()
                ->with('error', 'No pudimos enviar tu consulta en este momento. Probá de nuevo en unos minutos.');
        }

        return redirect()->to(route('landing.index') . '#contacto')
            ->with('success', '¡Gracias! Recibimos tu consulta y te vamos a contactar a la brevedad.');
    }

    /** Nombre del plan que corresponde a la cantidad de empleados. */
    private function planPara(int $empleados): ?string
    {
        foreach (config('landing.planes', []) as $plan) {
            $hasta = $plan['hasta'] ?? PHP_INT_MAX;
            if ($empleados >= $plan['desde'] && $empleados <= $hasta) {
                return $plan['nombre'];
            }
        }

        return null;
    }
}
