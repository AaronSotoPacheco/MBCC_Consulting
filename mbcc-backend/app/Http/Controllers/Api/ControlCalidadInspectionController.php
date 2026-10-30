<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DefectCatalog;
use App\Models\SerialRecord;
use App\Models\SortOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        ]);

        $query = $orden->serialRecords()
            ->with('defectLogs.defect')
            ->whereDate('created_at', today());

        if (isset($data['turno'])) {
            $query->where('shift', $data['turno']);
        }

        return $query->latest()->get()->map(fn (SerialRecord $record) => [
            'serie' => $record->serial_number,
            'resultado' => $record->status === 'PASSED' ? 'OK' : 'NG',
            'defecto' => $record->defectLogs->first()?->defect?->description,
            'hora' => $record->created_at->format('H:i'),
        ]);
    }

    public function store(Request $request, SortOrder $orden)
    {
        $data = $request->validate([
            'serie' => ['required', 'string', 'max:100'],
            'resultado' => ['required', Rule::in(['OK', 'NG'])],
            'defecto' => ['nullable', 'string', 'max:255', 'required_if:resultado,NG'],
            'turno' => ['nullable', 'integer', 'in:1,2,3'],
        ]);

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

        $record = DB::transaction(function () use ($data, $orden, $request, $defect) {
            $record = $orden->serialRecords()->create([
                'serial_number' => $data['serie'],
                'status' => $data['resultado'] === 'OK' ? 'PASSED' : 'FAILED',
                'inspected_by' => $request->user()->id,
                'shift' => $data['turno'] ?? null,
            ]);

            if ($defect) {
                $record->defectLogs()->create(['defect_id' => $defect->id]);
            }

            return $record;
        });

        return response()->json([
            'serie' => $record->serial_number,
            'resultado' => $data['resultado'],
            'defecto' => $defect?->description,
            'hora' => $record->created_at->format('H:i'),
        ], 201);
    }
}