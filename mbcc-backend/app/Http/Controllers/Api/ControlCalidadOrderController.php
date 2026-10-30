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
            ->whereIn('status', ['OPEN', 'PAUSED'])
            ->orderBy('order_number')
            ->get()
            ->map(fn (SortOrder $order) => [
                'id' => $order->id,
                'cliente' => $order->partNumber?->client?->name ?? '',
                'so' => $order->order_number,
                'parte' => $order->partNumber?->part_number ?? '',
                'descripcion' => $order->partNumber?->description ?: $order->workInstruction?->title ?? '',
                'meta' => $order->target_quantity,
                'estado' => $order->status === 'OPEN' ? 'Abierta' : 'Pausada',
                'instruccion' => $order->workInstruction ? [
                    'codigo' => $order->workInstruction->code.' · Rev. '.$order->workInstruction->revision,
                    'titulo' => $order->workInstruction->title,
                    'archivo' => $order->workInstruction->file_path,
                    'puntos' => [],
                ] : null,
            ]);
    }
}