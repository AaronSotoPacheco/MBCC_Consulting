<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DefectCatalog;
use App\Models\SerialRecord;
use App\Models\SortOrder;
use App\Support\Auditoria;
use Illuminate\Http\Request;
use App\Support\Mensajes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ControlCalidadInspectionController extends Controller
{
    public function defectos()
    {
        return DefectCatalog::query()
            ->where('is_active', true)
            ->orderBy('description')
            ->get(['code', 'description']);
    }

    public function index(Request $request, SortOrder $orden)
    {
        $data = $request->validate([
            'turno' => ['nullable', 'integer', 'in:1,2,3'],
            'ultimos' => ['nullable', 'integer', 'min:1', 'max:50'], // los N escaneos más recientes de la orden (sin límite de día ni turno)
        ]);
        $puedeVerInspector = in_array($request->user()->role?->name, ['admin', 'supervisor'], true);

        $query = $orden->serialRecords()
            ->with(['defectLogs.defect', 'inspector:id,full_name']);

        if (isset($data['ultimos'])) {
            $query->latest()->orderByDesc('id')->limit((int) $data['ultimos']);
        } else {
            $query->whereDate('created_at', today());
            if (isset($data['turno'])) {
                $query->where('shift', $data['turno']);
            }
            $query->latest();
        }

        return $query->get()->map(function (SerialRecord $record) use ($puedeVerInspector) {
            $inspection = [
                'serie' => $record->serial_number,
                'resultado' => $record->status === 'PASSED' ? 'OK' : 'NG',
                'defecto' => $record->defectLogs->first()?->defect?->description,
                'hora' => $record->created_at->format('H:i'),
                'fecha' => $record->created_at->format('d/m'),
            ];

            if ($puedeVerInspector) {
                $inspection['inspector'] = $record->inspector?->full_name;
            }

            return $inspection;
        });
    }

    public function store(Request $request, SortOrder $orden)
    {
        $data = $request->validate([
            'serie' => ['required', 'string', 'max:100'],
            'resultado' => ['required', Rule::in(['OK', 'NG'])],
            'defecto' => ['nullable', 'string', 'max:255', 'required_if:resultado,NG'],
            'turno' => ['nullable', 'integer', 'in:1,2,3'],
            // Evidencia fotográfica del rechazo y lo que sugirió la IA (opcionales; la decisión es siempre del inspector)
            'evidencia' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'ia_resultado' => ['nullable', 'in:OK,NG'],
            'ia_defecto' => ['nullable', 'string', 'max:255'],
            'ia_confianza' => ['nullable', 'in:alta,media,baja'],
            // Captura sin conexión: id único del dispositivo (reenviar no duplica) y momento real de la captura
            'cliente_uuid' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9-]+$/'],
            'capturado_en' => ['nullable', 'date'],
        ], Mensajes::con(['evidencia.mimes' => 'La evidencia debe ser JPG, PNG o WebP.', 'evidencia.max' => 'La evidencia no puede pesar más de 3 MB.']));

        // Reenvío de una captura que el servidor ya guardó (se perdió la respuesta): se devuelve lo guardado
        if (! empty($data['cliente_uuid'])) {
            $previo = $orden->serialRecords()->where('client_uuid', $data['cliente_uuid'])->first();
            if ($previo) {
                return response()->json($this->respuesta($previo, $orden, $request, $previo->status === 'PASSED' ? 'OK' : 'NG', null, false) + ['repetido' => true]);
            }
        }

        if ($orden->status !== 'OPEN') {
            return response()->json(['message' => 'La orden no está abierta para inspección.'], 422);
        }

        if ($orden->serialRecords()->where('serial_number', $data['serie'])->exists()) {
            return response()->json(['message' => 'Esa serie ya fue inspeccionada en esta orden.'], 422);
        }

        $defect = null;
        if ($data['resultado'] === 'NG') {
            $defect = DefectCatalog::query()
                ->where('is_active', true)
                ->where(fn ($query) => $query
                    ->where('description', $data['defecto'])
                    ->orWhere('code', $data['defecto']))
                ->first();

            if (! $defect) {
                return response()->json(['message' => 'El defecto seleccionado no está activo en el catálogo.'], 422);
            }
        }

        // La foto solo se guarda como evidencia de un rechazo (nombre generado por el servidor)
        $evidencia = null;
        if ($defect && $request->hasFile('evidencia')) {
            $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$request->file('evidencia')->getMimeType()] ?? null;
            if ($ext) {
                $evidencia = $request->file('evidencia')->storeAs('evidencias', Str::uuid().'.'.$ext, 'local');
            }
        }

        // Hora real de captura (solo si es creíble: no futura y dentro del límite de captura sin conexión)
        $capturado = null;
        if (! empty($data['capturado_en'])) {
            $c = \Illuminate\Support\Carbon::parse($data['capturado_en']);
            if ($c->lte(now()->addMinutes(5)) && $c->gte(now()->subHours((int) config('calidad.horas_max_captura_offline')))) {
                $capturado = $c->isFuture() ? now() : $c;
            }
        }

        $cerradaAhora = false;
        try {
            $record = DB::transaction(function () use ($data, $orden, $request, $defect, $evidencia, $capturado, &$cerradaAhora) {
                // Se bloquea la orden para contar y cerrar sin que dos estaciones se pasen de la meta
                $actual = SortOrder::query()->lockForUpdate()->findOrFail($orden->id);
                if ($actual->status !== 'OPEN') {
                    return null;
                }

                $record = $orden->serialRecords()->make([
                    'serial_number' => $data['serie'],
                    'status' => $data['resultado'] === 'OK' ? 'PASSED' : 'FAILED',
                    'inspected_by' => $request->user()->id,
                    'shift' => $data['turno'] ?? null,
                    'ai_verdict' => $data['ia_resultado'] ?? null,
                    'ai_defect' => $data['ia_defecto'] ?? null,
                    'ai_confidence' => $data['ia_confianza'] ?? null,
                ]);
                $record->client_uuid = $data['cliente_uuid'] ?? null;
                if ($capturado) {
                    $record->created_at = $capturado;
                }
                $record->save();

                if ($defect) {
                    $record->defectLogs()->create(['defect_id' => $defect->id, 'photo_evidence_url' => $evidencia]);
                }

                // Meta alcanzada: la orden se cierra sola
                $hechas = $actual->serialRecords()->count();
                if ($hechas >= (int) $actual->target_quantity) {
                    $actual->update(['status' => 'CLOSED']);
                    $cerradaAhora = true;
                    Auditoria::registrar(
                        'orden.cierre_auto',
                        "Cierre automático de la orden {$actual->order_number}: alcanzó la meta ({$hechas}/{$actual->target_quantity})",
                        $actual,
                        ['de' => 'OPEN', 'a' => 'CLOSED', 'automatico' => true, 'inspeccionadas' => $hechas, 'meta' => (int) $actual->target_quantity],
                        $request->user(),
                    );
                }

                return $record;
            });
        } catch (\Throwable $e) {
            if ($evidencia) {
                Storage::disk('local')->delete($evidencia);
            }
            throw $e;
        }

        if (! $record) { // otra estación cerró la orden justo antes
            if ($evidencia) {
                Storage::disk('local')->delete($evidencia);
            }

            return response()->json(['message' => 'La orden no está abierta para inspección.'], 422);
        }

        return response()->json($this->respuesta($record, $orden, $request, $data['resultado'], $defect?->description, $cerradaAhora), 201);
    }

    private function respuesta(SerialRecord $record, SortOrder $orden, Request $request, string $resultado, ?string $defecto, bool $cerradaAhora): array
    {
        $esGestor = in_array($request->user()->role?->name, ['admin', 'supervisor'], true);

        return [
            'serie' => $record->serial_number,
            'resultado' => $resultado,
            'defecto' => $defecto ?? $record->defectLogs()->with('defect')->first()?->defect?->description,
            'hora' => $record->created_at->format('H:i'),
            'inspeccionadas' => $orden->serialRecords()->count(),
            'meta' => (int) $orden->target_quantity,
            'orden_cerrada' => $cerradaAhora,
            ...($esGestor ? ['inspector' => $record->inspector?->full_name ?? $request->user()->full_name] : []),
        ];
    }
}