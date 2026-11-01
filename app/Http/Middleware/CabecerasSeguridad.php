<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras HTTP de seguridad (hallazgos típicos de OWASP ZAP).
 *
 *  - Content-Security-Policy: solo recursos propios y de cdn.jsdelivr.net (Bootstrap).
 *    'unsafe-inline' sigue habilitado porque las vistas usan <script> y onclick/onsubmit en línea;
 *    sacarlo requiere mover ese JS a archivos (pendiente, ver informe de seguridad).
 *  - X-Frame-Options / frame-ancestors: el sistema no se puede embeber en otro sitio (clickjacking).
 *  - X-Content-Type-Options, Referrer-Policy, Permissions-Policy.
 *  - Strict-Transport-Security solo cuando la conexión es HTTPS.
 *  - Páginas con sesión iniciada: Cache-Control no-store (recibos y legajos no quedan en la caché del navegador).
 *  - Se quita X-Powered-By (no revelar la versión de PHP).
 */
class CabecerasSeguridad
{
    public const CSP = "default-src 'self'; "
        . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; "
        . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; "
        . "font-src 'self' https://cdn.jsdelivr.net data:; "
        . "img-src 'self' data: blob:; "
        . "connect-src 'self'; "
        . "frame-ancestors 'self'; "
        . "form-action 'self'; "
        . "base-uri 'self'; "
        . "object-src 'none'";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $h = $response->headers;

        $h->set('X-Frame-Options', 'SAMEORIGIN');
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $h->set('Cross-Origin-Opener-Policy', 'same-origin');

        // La CSP va solo en HTML: aplicada a un PDF, Chrome puede bloquear su visor
        if (str_contains((string) $h->get('Content-Type'), 'text/html')) {
            $h->set('Content-Security-Policy', self::CSP);
        }

        if ($request->isSecure()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if ($request->user() && ! $h->has('Content-Disposition')) {
            $h->set('Cache-Control', 'no-store, private');
            $h->set('Pragma', 'no-cache');
        }

        $h->remove('X-Powered-By');
        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        return $response;
    }
}
