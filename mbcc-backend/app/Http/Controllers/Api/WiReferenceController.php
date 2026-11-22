<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SortOrder;
use App\Models\WiReferenceImage;
use App\Models\WorkInstruction;
use App\Support\Mensajes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fotos de referencia OK / NG de cada instrucción de trabajo (las sube un supervisor o admin).
 * Cualquier usuario con sesión puede verlas en la Estación; solo supervisor/admin las administra.
 */
class WiReferenceController extends Controller
{
    public const MAX_POR_TIPO = 4;

    private const EXT = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    private function formato(WiReferenceImage $r): array
    {
        return ['id' => $r->id, 'tipo' => $r->kind, 'etiqueta' => $r->label, 'created_at' => $r->created_at?->toDateTimeString()];
    }

    /** Gestión: referencias de una instrucción. */
    public function index(int $id)
    {
        WorkInstruction::findOrFail($id);

        return WiReferenceImage::where('work_instruction_id', $id)->orderBy('kind')->orderBy('id')->get()->map(fn ($r) => $this->formato($r));
    }

    /** Estación: referencias de la instrucción de la orden. */
    public function deOrden(SortOrder $orden)
    {
        if (! $orden->work_instruction_id) {
            return [];
        }

        return $this->index($orden->work_instruction_id);
    }

    public function store(Request $request, int $id)
    {
        $wi = WorkInstruction::findOrFail($id);
        if ($wi->status === 'OBSOLETE') {
            return response()->json(['message' => 'No se pueden agregar fotos a una instrucción obsoleta.'], 422);
        }

        $data = $request->validate([
            'tipo' => ['required', 'in:OK,NG'],
            'etiqueta' => ['nullable', 'string', 'max:120', 'required_if:tipo,NG'],
            'imagen' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
        ], Mensajes::con([
            'imagen.mimes' => 'La foto debe ser JPG, PNG o WebP.',
            'imagen.max' => 'La foto no puede pesar más de 3 MB.',
            'etiqueta.required_if' => 'Indica qué defecto muestra la foto NG.',
        ]), ['tipo' => 'tipo', 'etiqueta' => 'defecto', 'imagen' => 'foto']);

        if (WiReferenceImage::where('work_instruction_id', $id)->where('kind', $data['tipo'])->count() >= self::MAX_POR_TIPO) {
            return response()->json(['message' => 'Máximo '.self::MAX_POR_TIPO.' fotos '.$data['tipo'].' por instrucción. Elimina una para agregar otra.'], 422);
        }

        $archivo = $request->file('imagen');
        $mime = $archivo->getMimeType();
        if (! isset(self::EXT[$mime])) {
            return response()->json(['message' => 'La foto debe ser JPG, PNG o WebP.', 'errors' => ['imagen' => ['La foto debe ser JPG, PNG o WebP.']]], 422);
        }

        $ruta = $archivo->storeAs('wi-referencias', Str::uuid().'.'.self::EXT[$mime], 'local');

        $ref = WiReferenceImage::create([
            'work_instruction_id' => $id,
            'kind' => $data['tipo'],
            'label' => $data['tipo'] === 'NG' ? trim($data['etiqueta']) : null,
            'file_path' => $ruta,
            'mime' => $mime,
            'uploaded_by' => $request->user()->id,
        ]);

        return response()->json($this->formato($ref), 201);
    }

    public function destroy(int $id)
    {
        $ref = WiReferenceImage::findOrFail($id);
        $ruta = $ref->file_path;
        $ref->delete();
        if (str_starts_with($ruta, 'wi-referencias/') && ! WiReferenceImage::where('file_path', $ruta)->exists() && Storage::disk('local')->exists($ruta)) {
            Storage::disk('local')->delete($ruta);
        }

        return response()->noContent();
    }

    public function imagen(int $id)
    {
        $ref = WiReferenceImage::findOrFail($id);
        $ruta = $ref->file_path;

        if (! str_starts_with($ruta, 'wi-referencias/') || str_contains($ruta, '..') || ! Storage::disk('local')->exists($ruta)) {
            return response()->json(['message' => 'La foto de referencia no está disponible en el servidor.'], 404);
        }

        return Storage::disk('local')->response($ruta, basename($ruta), [
            'Content-Type' => $ref->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
