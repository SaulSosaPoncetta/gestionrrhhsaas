<?php

use App\Http\Middleware\CabecerasSeguridad;
use App\Models\Concepto;
use App\Models\Liquidacion;

it('el login trae las cabeceras de seguridad', function () {
    $r = $this->get('/login')->assertOk();

    $r->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Content-Security-Policy', CabecerasSeguridad::CSP)
        ->assertHeaderMissing('X-Powered-By')
        ->assertHeaderMissing('Strict-Transport-Security');   // solo en HTTPS

    expect($r->headers->get('Permissions-Policy'))->toContain('camera=()');
});

it('la CSP no permite embeber el sistema ni enviar formularios a otro dominio', function () {
    expect(CabecerasSeguridad::CSP)
        ->toContain("frame-ancestors 'self'")
        ->toContain("form-action 'self'")
        ->toContain("object-src 'none'")
        ->toContain("default-src 'self'");
});

it('las páginas con sesión no se guardan en la caché del navegador', function () {
    $this->actingAs(crearAdmin())->get('/empleados')
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private');
});

it('HSTS cuando la conexión es HTTPS', function () {
    $this->get('https://localhost/login')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('las respuestas de error también llevan las cabeceras', function () {
    $this->actingAs(crearAdmin())->get('/empresas')
        ->assertForbidden()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
});

it('las descargas no llevan CSP pero sí nosniff', function () {
    $empresa = crearEmpresa();
    $admin   = crearAdmin($empresa);
    Concepto::cargarPredeterminados($empresa->id);
    crearEmpleado($empresa);
    $this->actingAs($admin)->post('/liquidaciones', ['anio' => 2026, 'mes' => 9, 'tipo' => 'mensual']);

    $this->actingAs($admin)->get('/liquidaciones/' . Liquidacion::first()->id . '/exportar')
        ->assertOk()
        ->assertHeaderMissing('Content-Security-Policy')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('los recursos de Bootstrap se cargan con integridad (SRI)', function () {
    $html = $this->get('/login')->getContent();

    expect(substr_count($html, 'integrity="sha384-'))->toBe(2)
        ->and($html)->toContain('crossorigin="anonymous"');
});
