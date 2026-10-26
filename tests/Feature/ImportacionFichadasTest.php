<?php

use App\Models\Fichada;
use App\Services\Asistencia\ImportadorFichadas;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->empresa = crearEmpresa();
    $this->admin   = crearAdmin($this->empresa);
    $this->e10     = crearEmpleado($this->empresa, ['legajo' => 10]);
    $this->e20     = crearEmpleado($this->empresa, ['legajo' => 20]);

    $this->importar = function (string $contenido, bool $deducir = true): array {
        $ruta = tempnam(sys_get_temp_dir(), 'fich');
        file_put_contents($ruta, $contenido);

        return app(ImportadorFichadas::class)->importar($ruta, $this->empresa->id, $this->admin->id, $deducir);
    };
});

it('importa con encabezado, separador ; y tipo explícito', function () {
    $r = ($this->importar)("legajo;fecha;hora;tipo\n10;19/10/2026;08:58;E\n10;19/10/2026;18:01;S\n20;2026-10-19;09:05:00;IN\n");

    expect($r)->leidas->toBe(3)->importadas->toBe(3)->duplicadas->toBe(0)->errores->toBeEmpty();
    expect(Fichada::where('empleado_id', $this->e10->id)->orderBy('fecha_hora')->pluck('tipo')->all())->toBe(['entrada', 'salida']);
    expect(Fichada::first())->origen->toBe('importacion')->registrado_por->toBe($this->admin->id);
});

it('detecta coma y tabulación, y fecha y hora en una sola columna', function () {
    ($this->importar)("10,19/10/2026 08:00,E\n");
    ($this->importar)("20\t19-10-2026\t08:30\tS\n");

    expect(Fichada::count())->toBe(2);
});

it('deduce entrada / salida alternando por empleado', function () {
    ($this->importar)("10;19/10/2026;08:00\n10;19/10/2026;17:00\n10;20/10/2026;08:00\n");

    expect(Fichada::orderBy('fecha_hora')->pluck('tipo')->all())->toBe(['entrada', 'salida', 'entrada']);
});

it('sin deducción, exige el tipo', function () {
    $r = ($this->importar)("10;19/10/2026;08:00\n", false);

    expect($r['importadas'])->toBe(0)->and($r['errores'][0])->toContain('no indica si es entrada o salida');
});

it('descarta duplicadas del archivo y de la base', function () {
    ($this->importar)("10;19/10/2026;08:00;E\n");
    $r = ($this->importar)("10;19/10/2026;08:00;E\n10;19/10/2026;08:00;E\n10;19/10/2026;17:00;S\n");

    expect($r)->importadas->toBe(1)->duplicadas->toBe(2);
    expect(Fichada::count())->toBe(2);
});

it('informa errores por línea sin cortar la importación', function () {
    $r = ($this->importar)(implode("\n", [
        '10;19/10/2026;08:00;E',
        '999;19/10/2026;08:00;E',     // legajo inexistente
        '10;32/13/2026;08:00;E',      // fecha inválida
        '10;19/10/2026;08:00;X',      // tipo desconocido
        '10;19/10/2026',              // faltan columnas
        '10;20/10/26;08:00;E',        // año de 2 dígitos válido
    ]));

    expect($r['importadas'])->toBe(2)
        ->and($r['errores'])->toHaveCount(4)
        ->and($r['errores'][0])->toContain('Línea 2')->toContain('999');
});

it('no importa legajos de otra empresa', function () {
    $otra = crearEmpresa();
    crearEmpleado($otra, ['legajo' => 55]);

    $r = ($this->importar)("55;19/10/2026;08:00;E\n");
    expect($r['importadas'])->toBe(0)->and(Fichada::count())->toBe(0);
});

it('acepta archivos en Windows-1252 y con BOM', function () {
    ($this->importar)("\xEF\xBB\xBFlegajo;fecha;hora\n10;19/10/2026;08:00;E\n");
    ($this->importar)(mb_convert_encoding("Legajo;Fecha;Hora;Acción\n20;19/10/2026;08:00;E\n", 'Windows-1252', 'UTF-8'));

    expect(Fichada::count())->toBe(2);
});

it('se puede importar desde la pantalla', function () {
    $this->actingAs($this->admin)->get('/fichadas/importar')->assertOk();

    $this->actingAs($this->admin)->post('/fichadas/importar', [
        'archivo' => UploadedFile::fake()->createWithContent('reloj.csv', "10;19/10/2026;08:00;E\n10;19/10/2026;17:00;S\n"),
    ])->assertRedirect();

    expect(Fichada::count())->toBe(2);
});
