<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SerialRecord;
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

        return [
            'kpis' => [
                'eficiencia' => ['valor' => $total ? round($passed / $total * 100, 1) : 0, 'delta' => 'del periodo seleccionado'],
                'retrabajos' => ['valor' => $reworks, 'detalle' => $total ? round($reworks / $total * 100, 1).'% del total' : '0% del total'],
                'scrap' => ['valor' => $scrap, 'detalle' => $total ? round($scrap / $total * 100, 1).'% del total' : '0% del total'],
            ],
            'pareto' => $pareto->map(fn ($item) => ['defecto' => $item->defecto, 'cantidad' => (int) $item->cantidad]),
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