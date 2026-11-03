<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReworkLog;
use App\Models\SerialRecord;
use App\Models\SortOrder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ControlCalidadReportController extends Controller
{
    private function consulta(Request $request)
    {
        $filters = $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'turno' => ['nullable', Rule::in(['todos', '1', '2', '3', 1, 2, 3])],
            'orden' => ['nullable', 'string', 'max:50'],
        ]);

        $query = SerialRecord::query()
            ->whereDate('serial_records.created_at', '>=', $filters['desde'] ?? now()->startOfMonth()->toDateString())
            ->whereDate('serial_records.created_at', '<=', $filters['hasta'] ?? now()->toDateString());

        if (! empty($filters['turno']) && $filters['turno'] !== 'todos') {
            $query->where('shift', $filters['turno']);
        }
        if (! empty($filters['orden']) && $filters['orden'] !== 'todas') {
            $query->whereHas('sortOrder', fn ($orders) => $orders->where('order_number', $filters['orden']));
        }

        return $query;
    }

    public function resumen(Request $request)
    {
        $query = $this->consulta($request);
        $total = (clone $query)->count();
        $passed = (clone $query)->where('status', 'PASSED')->count();
        $reworks = (clone $query)->where('status', 'REWORK_REQUIRED')->count();
        $scrap = (clone $query)->where('status', 'SCRAP')->count();

        $pareto = (clone $query)
            ->join('serial_defect_logs', 'serial_records.id', '=', 'serial_defect_logs.serial_record_id')
            ->join('defect_catalog', 'serial_defect_logs.defect_id', '=', 'defect_catalog.id')
            ->selectRaw('defect_catalog.description as defecto, count(*) as cantidad')
            ->groupBy('defect_catalog.description')
            ->orderByDesc('cantidad')
            ->get();

        $ordenesActivas = SortOrder::query()
            ->with(['partNumber.client', 'workInstruction'])
            ->withCount([
                'serialRecords as inspeccionadas',
                'serialRecords as rechazadas' => fn ($serialQuery) => $serialQuery->whereIn('status', ['FAILED', 'REWORK_REQUIRED', 'SCRAP']),
            ])
            ->whereIn('status', ['OPEN', 'PAUSED'])
            ->orderBy('status')
            ->orderBy('order_number')
            ->get()
            ->map(fn (SortOrder $order) => [
                'id' => $order->id,
                'orden' => $order->order_number,
                'cliente' => $order->partNumber?->client?->name ?? '—',
                'parte' => $order->partNumber?->part_number ?? '—',
                'descripcion' => $order->partNumber?->description ?: $order->workInstruction?->title ?? '—',
                'estado' => $order->status,
                'meta' => (int) $order->target_quantity,
                'inspeccionadas' => (int) $order->inspeccionadas,
                'rechazadas' => (int) $order->rechazadas,
            ]);

        $retrabajos = ReworkLog::query()
            ->join('serial_records', 'serial_records.id', '=', 'rework_logs.serial_record_id')
            ->join('sort_orders', 'sort_orders.id', '=', 'serial_records.sort_order_id')
            ->leftJoin('part_numbers', 'part_numbers.id', '=', 'sort_orders.part_number_id')
            ->leftJoin('clients', 'clients.id', '=', 'part_numbers.client_id')
            ->select([
                'rework_logs.id',
                'sort_orders.order_number as orden',
                'clients.name as cliente',
                'part_numbers.part_number as parte',
                'rework_logs.rework_action as accion',
                'rework_logs.final_status as resultado',
                'serial_records.serial_number as serie',
                'rework_logs.completed_at as fecha',
            ])
            ->whereDate('rework_logs.completed_at', '>=', $request->input('desde') ?? now()->startOfMonth()->toDateString())
            ->whereDate('rework_logs.completed_at', '<=', $request->input('hasta') ?? now()->toDateString())
            ->when($request->input('turno') && $request->input('turno') !== 'todos', fn ($query) => $query->where('serial_records.shift', $request->input('turno')))
            ->when($request->input('orden') && $request->input('orden') !== 'todas', fn ($query) => $query->where('sort_orders.order_number', $request->input('orden')))
            ->orderByDesc('rework_logs.completed_at')
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'orden' => $item->orden,
                'cliente' => $item->cliente ?? '—',
                'parte' => $item->parte ?? '—',
                'serie' => $item->serie,
                'accion' => $item->accion,
                'resultado' => $item->resultado,
                'fecha' => $item->fecha ? \Illuminate\Support\Carbon::parse($item->fecha)->toDateTimeString() : null,
            ]);

        return [
            'kpis' => [
                'eficiencia' => ['valor' => $total ? round($passed / $total * 100, 1) : 0, 'delta' => 'del periodo seleccionado'],
                'retrabajos' => ['valor' => $reworks, 'detalle' => $total ? round($reworks / $total * 100, 1).'% del total' : '0% del total'],
                'scrap' => ['valor' => $scrap, 'detalle' => $total ? round($scrap / $total * 100, 1).'% del total' : '0% del total'],
            ],
            'pareto' => $pareto->map(fn ($item) => ['defecto' => $item->defecto, 'cantidad' => (int) $item->cantidad]),
            'ordenesActivas' => $ordenesActivas,
            'retrabajos' => $retrabajos,
        ];
    }

    public function csv(Request $request)
    {
        $records = $this->consulta($request)
            ->with(['sortOrder.partNumber', 'defectLogs.defect'])
            ->orderBy('created_at')
            ->get();

        return response()->streamDownload(function () use ($records) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Fecha', 'Orden', 'Parte', 'Serie', 'Resultado', 'Defecto', 'Turno']);

            foreach ($records as $record) {
                fputcsv($output, [
                    $record->created_at?->toDateTimeString(),
                    $record->sortOrder?->order_number,
                    $record->sortOrder?->partNumber?->part_number,
                    $record->serial_number,
                    $record->status === 'PASSED' ? 'OK' : 'NG',
                    $record->defectLogs->first()?->defect?->description,
                    $record->shift,
                ]);
            }

            fclose($output);
        }, 'inspecciones.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}