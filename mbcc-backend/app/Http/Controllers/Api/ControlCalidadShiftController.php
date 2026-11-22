<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SerialRecord;
use App\Support\Mensajes;
use Illuminate\Http\Request;

/**
 * "Mi turno": lo inspeccionado en un día.
 *  - alcance=mio    → solo lo que inspeccionó el usuario (cualquier nivel)
 *  - alcance=equipo → todos los inspectores (solo supervisor y admin)
 */
class ControlCalidadShiftController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            'alcance' => ['nullable', 'in:mio,equipo'],
        ], Mensajes::ES, ['fecha' => 'fecha', 'alcance' => 'alcance']);

        $fecha = $data['fecha'] ?? today()->toDateString();
        $alcance = $data['alcance'] ?? 'mio';
        $user = $request->user();
        $esGestor = in_array($user->role?->name, ['supervisor', 'admin'], true);

        if ($alcance === 'equipo' && ! $esGestor) {
            return response()->json(['message' => 'No tienes permiso para ver el turno del equipo.'], 403);
        }

        $registros = SerialRecord::query()
            ->with(['sortOrder.partNumber.client', 'inspector:id,full_name'])
            ->whereDate('created_at', $fecha)
            ->when($alcance === 'mio', fn ($q) => $q->where('inspected_by', $user->id))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $ok = fn ($items) => $items->where('status', 'PASSED')->count();

        $porOrden = $registros->groupBy('sort_order_id')->map(function ($items) use ($ok) {
            $orden = $items->first()->sortOrder;

            return [
                'so' => $orden?->order_number,
                'cliente' => $orden?->partNumber?->client?->name ?? '',
                'parte' => $orden?->partNumber?->part_number ?? '',
                'inspeccionadas' => $items->count(),
                'ok' => $ok($items),
                'ng' => $items->count() - $ok($items),
            ];
        })->sortBy('so')->values();

        $respuesta = [
            'fecha' => $fecha,
            'alcance' => $alcance,
            'kpis' => [
                'inspeccionadas' => $registros->count(),
                'ok' => $ok($registros),
                'ng' => $registros->count() - $ok($registros),
            ],
            'porOrden' => $porOrden,
        ];

        if ($alcance === 'equipo') {
            $respuesta['porInspector'] = $registros->groupBy('inspected_by')->map(fn ($items) => [
                'nombre' => $items->first()->inspector?->full_name ?? '—',
                'inspeccionadas' => $items->count(),
                'ok' => $ok($items),
                'ng' => $items->count() - $ok($items),
            ])->sortBy('nombre')->values();
        } else {
            $respuesta['ultimos'] = $registros->take(15)->map(fn (SerialRecord $r) => [
                'serie' => $r->serial_number,
                'so' => $r->sortOrder?->order_number,
                'resultado' => $r->status === 'PASSED' ? 'OK' : 'NG',
                'hora' => $r->created_at->format('H:i'),
            ])->values();
        }

        return $respuesta;
    }
}
