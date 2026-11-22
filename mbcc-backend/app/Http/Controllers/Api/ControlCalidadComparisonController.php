<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DefectCatalog;
use App\Models\SortOrder;
use App\Models\WiReferenceImage;
use App\Services\VisionComparer;
use App\Services\VisionException;
use App\Support\Mensajes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ControlCalidadComparisonController extends Controller
{
    /**
     * Compara la foto de la pieza contra las referencias OK/NG de la instrucción de la orden.
     * Solo devuelve una sugerencia: la inspección la registra el inspector con los botones OK/NG.
     * La foto no se guarda.
     */
    public function comparar(Request $request, SortOrder $orden, VisionComparer $vision)
    {
        $request->validate([
            'imagen' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
        ], Mensajes::con([
            'imagen.mimes' => 'La foto debe ser JPG, PNG o WebP.',
            'imagen.max' => 'La foto no puede pesar más de 3 MB.',
        ]), ['imagen' => 'foto']);

        if ($orden->status !== 'OPEN') {
            return response()->json(['message' => 'La orden no está abierta para inspección.'], 422);
        }

        $referencias = $orden->work_instruction_id
            ? WiReferenceImage::where('work_instruction_id', $orden->work_instruction_id)->orderBy('kind')->orderBy('id')->get()
            : collect();

        $preparadas = [];
        foreach ($referencias as $ref) {
            if (str_starts_with($ref->file_path, 'wi-referencias/') && Storage::disk('local')->exists($ref->file_path)) {
                $preparadas[] = [
                    'tipo' => $ref->kind,
                    'etiqueta' => $ref->label,
                    'mime' => $ref->mime,
                    'data' => base64_encode(Storage::disk('local')->get($ref->file_path)),
                ];
            }
        }

        if (! collect($preparadas)->contains('tipo', 'OK')) {
            return response()->json(['message' => 'Esta instrucción aún no tiene fotos de referencia OK. Pide a un supervisor que las suba en Configuración → Instrucciones de trabajo.'], 422);
        }

        $archivo = $request->file('imagen');
        $mime = $archivo->getMimeType();
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return response()->json(['message' => 'La foto debe ser JPG, PNG o WebP.'], 422);
        }

        $orden->loadMissing(['partNumber', 'workInstruction']);
        $contexto = sprintf('parte %s, instrucción %s rev. %s — %s',
            $orden->partNumber?->part_number, $orden->workInstruction?->code, $orden->workInstruction?->revision, $orden->workInstruction?->title);

        $defectos = DefectCatalog::where('is_active', true)->orderBy('description')->pluck('description')->all();

        try {
            $resultado = $vision->comparar($preparadas, ['mime' => $mime, 'data' => base64_encode($archivo->get())], $defectos, $contexto);
        } catch (VisionException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        return $resultado + ['referencias' => count($preparadas)];
    }
}
