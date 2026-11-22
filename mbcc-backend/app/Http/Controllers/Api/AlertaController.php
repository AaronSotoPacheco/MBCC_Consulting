<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SerialRecord;
use App\Models\SortOrder;

/**
 * Alertas para supervisor y admin: órdenes (abiertas o pausadas) cuyo porcentaje de rechazos
 * en las últimas piezas rebasa el umbral. Se calcula al consultar; no guarda nada.
 */
class AlertaController extends Controller
{
    private const RECHAZO = ['FAILED', 'REWORK_REQUIRED', 'SCRAP'];

    public function index()
    {
        $umbral = (float) config('calidad.umbral_ng');
        $ventana = max(1, (int) config('calidad.ventana_ng'));
        $minimo = max(1, (int) config('calidad.muestra_minima_ng'));

        $alertas = [];
        $ordenes = SortOrder::query()->with('partNumber.client')->whereIn('status', ['OPEN', 'PAUSED'])->orderBy('order_number')->get();

        foreach ($ordenes as $orden) {
            $ultimas = $orden->serialRecords()->with('defectLogs.defect')->latest()->orderByDesc('id')->limit($ventana)->get();
            if ($ultimas->count() < $minimo) {
                continue;
            }

            $rechazadas = $ultimas->filter(fn (SerialRecord $r) => in_array($r->status, self::RECHAZO, true));
            $tasa = round($rechazadas->count() / $ultimas->count() * 100, 1);
            if ($tasa < $umbral) {
                continue;
            }

            $total = $orden->serialRecords()->count();
            $totalNg = $orden->serialRecords()->whereIn('status', self::RECHAZO)->count();
            $defecto = $rechazadas->flatMap(fn (SerialRecord $r) => $r->defectLogs->map(fn ($l) => $l->defect?->description))
                ->filter()->countBy()->sortDesc()->keys()->first();

            $alertas[] = [
                'orden_id' => $orden->id,
                'so' => $orden->order_number,
                'parte' => $orden->partNumber?->part_number,
                'cliente' => $orden->partNumber?->client?->name,
                'estado' => $orden->status,
                'tasa_ventana' => $tasa,
                'piezas_ventana' => $ultimas->count(),
                'rechazadas_ventana' => $rechazadas->count(),
                'tasa_total' => $total ? round($totalNg / $total * 100, 1) : 0.0,
                'inspeccionadas' => $total,
                'defecto_principal' => $defecto,
                'nivel' => $tasa >= $umbral * 2 ? 'critica' : 'alta',
            ];
        }

        usort($alertas, fn ($a, $b) => $b['tasa_ventana'] <=> $a['tasa_ventana']);

        return ['umbral' => $umbral, 'ventana' => $ventana, 'alertas' => $alertas];
    }
}
