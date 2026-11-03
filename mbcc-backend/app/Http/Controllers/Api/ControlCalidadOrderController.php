<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SortOrder;

class ControlCalidadOrderController extends Controller
{
    public function index()
    {
        return SortOrder::query()
            ->with(['partNumber.client', 'workInstruction'])
            ->withCount([
                'serialRecords as inspeccionadas',
                'serialRecords as rechazadas' => fn ($query) => $query->whereIn('status', ['FAILED', 'REWORK_REQUIRED', 'SCRAP']),
            ])
            ->whereIn('status', ['OPEN', 'PAUSED'])
            ->orderBy('status')
            ->orderBy('order_number')
            ->get()
            ->map(fn (SortOrder $order) => [
                'id' => $order->id,
                'cliente' => $order->partNumber?->client?->name ?? '',
                'so' => $order->order_number,
                'parte' => $order->partNumber?->part_number ?? '',
                'descripcion' => $order->partNumber?->description ?: $order->workInstruction?->title ?? '',
                'meta' => (int) $order->target_quantity,
                'inspeccionadas' => (int) $order->inspeccionadas,
                'rechazadas' => (int) $order->rechazadas,
                'estado' => $order->status,
                'turno' => $order->shift_hours,
                'wi' => $order->workInstruction ? [
                    'codigo' => $order->workInstruction->code,
                    'revision' => $order->workInstruction->revision,
                    'titulo' => $order->workInstruction->title,
                    'archivo' => $order->workInstruction->file_path,
                ] : null,
            ]);
    }
}