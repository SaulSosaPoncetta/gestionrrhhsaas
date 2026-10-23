<?php

namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Models\EmpleadoDocumento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Los documentos se guardan en el disco privado (storage/app/private):
 * no son accesibles por URL, solo se descargan a través de este controlador.
 */
class EmpleadoDocumentoController extends Controller
{
    public function store(Request $request, Empleado $empleado)
    {
        $request->validate([
            'tipo'        => ['required', Rule::in(array_keys(EmpleadoDocumento::TIPOS))],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'vencimiento' => ['nullable', 'date'],
            'archivo'     => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx'],
        ], [
            'tipo.required'    => 'Seleccioná el tipo de documento.',
            'archivo.required' => 'Seleccioná el archivo a subir.',
            'archivo.max'      => 'El archivo no puede superar los 10 MB.',
            'archivo.mimes'    => 'Formatos permitidos: PDF, imágenes, Word o Excel.',
        ]);

        $archivo = $request->file('archivo');
        $ruta    = $archivo->store("empresas/{$empleado->empresa_id}/empleados/{$empleado->id}", 'local');

        EmpleadoDocumento::create([
            'empleado_id'     => $empleado->id,
            'tipo'            => $request->tipo,
            'descripcion'     => $request->descripcion,
            'vencimiento'     => $request->vencimiento,
            'archivo'         => $ruta,
            'nombre_original' => $archivo->getClientOriginalName(),
            'tamanio'         => $archivo->getSize(),
            'subido_por'      => auth()->id(),
        ]);

        return redirect()->to(route('empleados.show', $empleado) . '#documentos')->with('ok', 'Documento subido.');
    }

    public function descargar(Empleado $empleado, EmpleadoDocumento $documento)
    {
        abort_unless((int) $documento->empleado_id === (int) $empleado->id, 404);
        abort_unless(Storage::disk('local')->exists($documento->archivo), 404, 'El archivo no se encuentra.');

        return Storage::disk('local')->download($documento->archivo, $documento->nombre_original);
    }

    public function ver(Empleado $empleado, EmpleadoDocumento $documento)
    {
        abort_unless((int) $documento->empleado_id === (int) $empleado->id, 404);
        abort_unless(Storage::disk('local')->exists($documento->archivo), 404, 'El archivo no se encuentra.');

        return Storage::disk('local')->response($documento->archivo, $documento->nombre_original);
    }

    public function destroy(Empleado $empleado, EmpleadoDocumento $documento)
    {
        abort_unless((int) $documento->empleado_id === (int) $empleado->id, 404);
        Storage::disk('local')->delete($documento->archivo);
        $documento->delete();

        return redirect()->to(route('empleados.show', $empleado) . '#documentos')->with('ok', 'Documento eliminado.');
    }
}
