<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SerialDefectLog;
use App\Models\SerialRecord;
use App\Support\Mensajes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Trazabilidad de una pieza por número de serie: dónde se inspeccionó, con qué instrucción, qué defectos tuvo
 * (con su foto de evidencia), qué sugirió la IA y sus retrabajos. Cualquier usuario con sesión puede consultar;
 * los nombres de personas solo los ven supervisor y admin (igual que en Reportes).
 */
class TrazabilidadController extends Controller
{
    private const ESTADO = ['PASSED' => 'OK', 'FAILED' => 'NG', 'REWORK_REQUIRED' => 'RETRABAJO', 'SCRAP' => 'SCRAP'];

    private function esGestor(Request $request): bool
    {
        return in_array($request->user()->role?->name, ['supervisor', 'admin'], true);
    }

    private function formato(SerialRecord $r, bool $gestor): array
    {
        $orden = $r->sortOrder;
        $decisionOriginal = $r->defectLogs->isNotEmpty() ? 'NG' : 'OK'; // un rechazo siempre guarda su defecto

        $x = [
            'id' => $r->id,
            'serie' => $r->serial_number,
            'estado' => self::ESTADO[$r->status] ?? $r->status,
            'fecha' => $r->created_at?->toDateTimeString(),
            'turno' => $r->shift,
            'orden_id' => $orden?->id,
            'orden' => $orden?->order_number,
            'orden_estado' => $orden?->status,
            'parte' => $orden?->partNumber?->part_number,
            'cliente' => $orden?->partNumber?->client?->name,
            'instruccion' => $orden?->workInstruction ? $orden->workInstruction->code.' · Rev. '.$orden->workInstruction->revision : null,
            'defectos' => $r->defectLogs->map(fn (SerialDefectLog $d) => [
                'id' => $d->id,
                'defecto' => $d->defect?->description,
                'zona' => $d->zone,
                'notas' => $d->notes,
                'evidencia' => (bool) $d->photo_evidence_url,
            ])->values(),
            'ia' => $r->ai_verdict ? [
                'resultado' => $r->ai_verdict,
                'defecto' => $r->ai_defect,
                'confianza' => $r->ai_confidence,
                'coincide' => $r->ai_verdict === $decisionOriginal, // ¿la decisión del inspector fue la que sugirió la IA?
            ] : null,
            'retrabajos' => $r->reworkLogs->sortBy('id')->map(fn ($w) => array_filter([
                'accion' => $w->rework_action,
                'resultado' => $w->final_status,
                'fecha' => $w->completed_at?->toDateTimeString(),
                'retrabajo_por' => $gestor ? $w->reworker?->full_name : null,
                'reinspeccion_por' => $gestor ? $w->reinspector?->full_name : null,
            ], fn ($v) => $v !== null))->values(),
            'puede_retrabajo' => in_array($r->status, ['FAILED', 'REWORK_REQUIRED'], true) && $orden?->status !== 'CLOSED',
        ];
        if ($gestor) {
            $x['inspector'] = $r->inspector?->full_name;
        }

        return $x;
    }

    private function conRelaciones()
    {
        return SerialRecord::query()->with([
            'sortOrder.partNumber.client', 'sortOrder.workInstruction', 'inspector:id,full_name',
            'defectLogs.defect', 'reworkLogs.reworker:id,full_name', 'reworkLogs.reinspector:id,full_name',
        ]);
    }

    public function buscar(Request $request)
    {
        $data = $request->validate([
            'serie' => ['required', 'string', 'min:2', 'max:100'],
        ], Mensajes::ES, ['serie' => 'número de serie']);

        $gestor = $this->esGestor($request);
        $texto = addcslashes(trim($data['serie']), '%_\\');

        $registros = $this->conRelaciones()
            ->where('serial_number', 'like', "%{$texto}%")
            ->orderByRaw('CASE WHEN serial_number = ? THEN 0 ELSE 1 END', [trim($data['serie'])]) // coincidencia exacta primero
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();

        return $registros->map(fn (SerialRecord $r) => $this->formato($r, $gestor))->values();
    }

    /** Piezas rechazadas que siguen esperando retrabajo (órdenes no cerradas), las más antiguas primero. */
    public function pendientes(Request $request)
    {
        $gestor = $this->esGestor($request);
        $base = SerialRecord::query()
            ->whereIn('status', ['FAILED', 'REWORK_REQUIRED'])
            ->whereHas('sortOrder', fn ($q) => $q->where('status', '!=', 'CLOSED'));

        return [
            'total' => (clone $base)->count(),
            'piezas' => $this->conRelaciones()
                ->whereIn('status', ['FAILED', 'REWORK_REQUIRED'])
                ->whereHas('sortOrder', fn ($q) => $q->where('status', '!=', 'CLOSED'))
                ->orderBy('created_at')->orderBy('id')
                ->limit(100)->get()
                ->map(fn (SerialRecord $r) => $this->formato($r, $gestor))->values(),
        ];
    }

    public function evidencia(int $id)
    {
        $log = SerialDefectLog::findOrFail($id);
        $ruta = $log->photo_evidence_url;

        if (! $ruta || ! str_starts_with($ruta, 'evidencias/') || str_contains($ruta, '..') || ! Storage::disk('local')->exists($ruta)) {
            return response()->json(['message' => 'La evidencia no está disponible.'], 404);
        }

        return Storage::disk('local')->response($ruta, basename($ruta), [
            'Content-Type' => Storage::disk('local')->mimeType($ruta) ?: 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /** Registra el retrabajo de una pieza rechazada: queda aprobada (PASSED) tras reinspección o desechada (SCRAP). */
    public function retrabajo(Request $request, int $id)
    {
        $data = $request->validate([
            'accion' => ['required', 'string', 'min:3', 'max:255'],
            'resultado' => ['required', 'in:PASSED,SCRAP'],
        ], Mensajes::ES, ['accion' => 'acción realizada', 'resultado' => 'resultado']);

        $error = null;
        DB::transaction(function () use ($id, $data, $request, &$error) {
            $r = SerialRecord::query()->with('sortOrder')->lockForUpdate()->findOrFail($id);

            if (! in_array($r->status, ['FAILED', 'REWORK_REQUIRED'], true)) {
                $error = 'Solo se puede retrabajar una pieza rechazada (NG).';

                return;
            }
            if ($r->sortOrder?->status === 'CLOSED') {
                $error = 'La orden está cerrada. Un administrador debe reabrirla para registrar el retrabajo.';

                return;
            }

            $r->reworkLogs()->create([
                'rework_action' => trim($data['accion']),
                'reworked_by' => $request->user()->id,
                'reinspected_by' => $request->user()->id,
                'final_status' => $data['resultado'],
                'completed_at' => now(),
            ]);
            $r->update(['status' => $data['resultado']]);
        });

        if ($error) {
            return response()->json(['message' => $error], 422);
        }

        return $this->formato($this->conRelaciones()->findOrFail($id), $this->esGestor($request));
    }
}
