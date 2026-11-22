<?php

namespace App\Http\Controllers\Api;

use App\Models\SortOrder;
use App\Models\WorkInstruction;
use App\Support\Auditoria;
use App\Support\Mensajes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Control documental (supervisor y admin):
 *   subir PDF → DRAFT → aprobar → ACTIVE (la vigente anterior de esa parte pasa a OBSOLETE) → obsoleta.
 * El estado y la aprobación los decide el servidor; el cliente ya no puede escribirlos.
 */
class WorkInstructionController extends QualityResourceController
{
    protected string $modelClass = WorkInstruction::class;

    private const ATRIBUTOS = [
        'part_number_id' => 'número de parte', 'code' => 'código', 'revision' => 'revisión',
        'title' => 'título', 'archivo' => 'archivo PDF',
    ];

    private function formato(WorkInstruction $wi): array
    {
        return [
            'id' => $wi->id,
            'part_number_id' => $wi->part_number_id,
            'part_number' => $wi->partNumber?->part_number,
            'client' => $wi->partNumber?->client?->name,
            'code' => $wi->code,
            'revision' => $wi->revision,
            'title' => $wi->title,
            'status' => $wi->status,
            'key_points' => $wi->key_points,
            'specifications' => $wi->specifications,
            'approved_by_name' => $wi->approver?->full_name,
            'approved_at' => $wi->approved_at?->toDateTimeString(),
            'created_at' => $wi->created_at?->toDateTimeString(),
        ];
    }

    public function index(Request $request)
    {
        $items = WorkInstruction::query()
            ->with(['partNumber.client', 'approver:id,full_name'])
            ->orderBy('part_number_id')->orderBy('code')->orderByDesc('id')
            ->paginate(min($request->integer('per_page', 15), 200));

        $items->getCollection()->transform(fn (WorkInstruction $wi) => $this->formato($wi));

        return $items;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'part_number_id' => ['required', 'integer', 'exists:part_numbers,id'],
            'code' => ['required', 'string', 'max:50'],
            'revision' => ['required', 'string', 'max:10'],
            'title' => ['required', 'string', 'max:200'],
            'archivo' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ], Mensajes::con(['archivo.max' => 'El PDF no puede pesar más de 20 MB.']), self::ATRIBUTOS);

        if (WorkInstruction::where('code', $data['code'])->where('revision', $data['revision'])->exists()) {
            return response()->json([
                'message' => 'Esa revisión de ese código ya existe.',
                'errors' => ['revision' => ['Esa revisión de ese código ya existe.']],
            ], 422);
        }

        // Nombre generado por el servidor: nunca se usa el nombre que manda el cliente
        $ruta = $request->file('archivo')->storeAs('wi', Str::uuid().'.pdf', 'local');

        $wi = WorkInstruction::create([
            'part_number_id' => $data['part_number_id'],
            'code' => $data['code'],
            'revision' => $data['revision'],
            'title' => $data['title'],
            'file_path' => $ruta,
            'status' => 'DRAFT',
        ]);

        Auditoria::registrar('instruccion.crear', "Subió la instrucción {$wi->code} rev. {$wi->revision} (borrador)", $wi);

        return response()->json($this->formato($wi->load(['partNumber.client', 'approver'])), 201);
    }

    /** Solo se edita el texto de un borrador; el archivo se cambia eliminando el borrador y subiendo otro. */
    public function update(Request $request, int $id)
    {
        $wi = WorkInstruction::findOrFail($id);
        if ($wi->status !== 'DRAFT') {
            return response()->json(['message' => 'Solo se puede editar una instrucción en borrador.'], 422);
        }

        $data = $request->validate([
            'part_number_id' => ['required', 'integer', 'exists:part_numbers,id'],
            'code' => ['required', 'string', 'max:50'],
            'revision' => ['required', 'string', 'max:10'],
            'title' => ['required', 'string', 'max:200'],
        ], Mensajes::ES, self::ATRIBUTOS);

        $duplicada = WorkInstruction::where('code', $data['code'])->where('revision', $data['revision'])->where('id', '!=', $id)->exists();
        if ($duplicada) {
            return response()->json([
                'message' => 'Esa revisión de ese código ya existe.',
                'errors' => ['revision' => ['Esa revisión de ese código ya existe.']],
            ], 422);
        }

        $wi->update($data);
        Auditoria::registrar('instruccion.editar', "Editó el borrador {$wi->code} rev. {$wi->revision}", $wi);

        return $this->formato($wi->fresh(['partNumber.client', 'approver']));
    }

    /**
     * Puntos clave y especificaciones que ve el inspector en la Estación. Son notas de apoyo (el documento controlado
     * es el PDF), así que se pueden ajustar en borrador o vigente; una obsoleta ya no se edita.
     */
    public function contenido(Request $request, int $id)
    {
        $wi = WorkInstruction::findOrFail($id);
        if ($wi->status === 'OBSOLETE') {
            return response()->json(['message' => 'Una instrucción obsoleta ya no se puede editar.'], 422);
        }

        $data = $request->validate([
            'key_points' => ['nullable', 'string', 'max:2000'],
            'specifications' => ['nullable', 'string', 'max:2000'],
        ], Mensajes::ES, ['key_points' => 'puntos clave', 'specifications' => 'especificaciones']);

        $wi->update([
            'key_points' => isset($data['key_points']) && trim($data['key_points']) !== '' ? trim($data['key_points']) : null,
            'specifications' => isset($data['specifications']) && trim($data['specifications']) !== '' ? trim($data['specifications']) : null,
        ]);
        Auditoria::registrar('instruccion.contenido', "Cambió los puntos clave/especificaciones de {$wi->code} rev. {$wi->revision}", $wi);

        return $this->formato($wi->fresh(['partNumber.client', 'approver']));
    }

    public function aprobar(Request $request, int $id)
    {
        $resultado = DB::transaction(function () use ($request, $id) {
            $wi = WorkInstruction::query()->lockForUpdate()->findOrFail($id);
            if ($wi->status !== 'DRAFT') {
                return null;
            }

            // Una sola vigente por número de parte
            WorkInstruction::where('part_number_id', $wi->part_number_id)->where('status', 'ACTIVE')->update(['status' => 'OBSOLETE']);
            $wi->update(['status' => 'ACTIVE', 'approved_by' => $request->user()->id, 'approved_at' => now()]);

            return $wi;
        });

        if (! $resultado) {
            return response()->json(['message' => 'Solo se puede aprobar una instrucción en borrador.'], 422);
        }

        Auditoria::registrar('instruccion.aprobar', "Aprobó la instrucción {$resultado->code} rev. {$resultado->revision}", $resultado);

        return $this->formato($resultado->fresh(['partNumber.client', 'approver']));
    }

    public function obsoleta(int $id)
    {
        $wi = WorkInstruction::findOrFail($id);
        if ($wi->status !== 'ACTIVE') {
            return response()->json(['message' => 'Solo se puede marcar como obsoleta una instrucción vigente.'], 422);
        }
        $wi->update(['status' => 'OBSOLETE']);
        Auditoria::registrar('instruccion.obsoleta', "Marcó como obsoleta la instrucción {$wi->code} rev. {$wi->revision}", $wi);

        return $this->formato($wi->fresh(['partNumber.client', 'approver']));
    }

    public function destroy(int $id)
    {
        $wi = WorkInstruction::findOrFail($id);
        if ($wi->status !== 'DRAFT') {
            return response()->json(['message' => 'Solo se puede eliminar una instrucción en borrador.'], 422);
        }
        if ($wi->sortOrders()->exists()) {
            return response()->json(['message' => 'La instrucción ya tiene órdenes asociadas y no se puede eliminar.'], 422);
        }

        $ruta = $wi->file_path;
        $fotos = $wi->referenceImages()->pluck('file_path')->all(); // las filas se borran en cascada; los archivos, aquí
        $wi->delete();
        Auditoria::registrar('instruccion.eliminar', "Eliminó el borrador {$wi->code} rev. {$wi->revision}", $wi);
        foreach ($fotos as $foto) {
            if (str_starts_with($foto, 'wi-referencias/')) {
                Storage::disk('local')->delete($foto);
            }
        }
        if (str_starts_with($ruta, 'wi/') && Storage::disk('local')->exists($ruta)) {
            Storage::disk('local')->delete($ruta);
        }

        return response()->noContent();
    }

    public function archivo(int $id)
    {
        return $this->servirPdf(WorkInstruction::findOrFail($id));
    }

    /** El inspector abre el PDF de la instrucción de SU orden (nunca uno cualquiera por id). */
    public function archivoDeOrden(SortOrder $orden)
    {
        if (! $orden->work_instruction_id) {
            return response()->json(['message' => 'Esta orden no tiene instrucción de trabajo.'], 404);
        }

        return $this->servirPdf(WorkInstruction::findOrFail($orden->work_instruction_id));
    }

    private function servirPdf(WorkInstruction $instruction)
    {
        $path = $instruction->file_path;
        $segments = explode('/', $path);

        if (
            ! str_starts_with($path, 'wi/')
            || str_contains($path, '\\')
            || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'pdf'
            || in_array('', $segments, true)
            || in_array('.', $segments, true)
            || in_array('..', $segments, true)
            || ! Storage::disk('local')->exists($path)
        ) {
            return response()->json(['message' => 'El PDF de esta instrucción no está disponible en el servidor.'], 404);
        }

        return Storage::disk('local')->response($path, basename($path), ['Content-Type' => 'application/pdf']);
    }
}
