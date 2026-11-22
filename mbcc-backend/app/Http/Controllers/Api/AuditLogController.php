<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

/** Consulta de la bitácora (solo administrador). */
class AuditLogController extends Controller
{
    public const GRUPOS = ['instruccion', 'orden', 'usuario', 'qr', 'sesion', 'contrasena', 'asistencia'];

    public function index(Request $request)
    {
        $f = $request->validate([
            'grupo' => ['nullable', 'in:'.implode(',', self::GRUPOS)],
            'q' => ['nullable', 'string', 'max:100'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $items = AuditLog::query()
            ->when($f['grupo'] ?? null, fn ($q, $g) => $q->where('action', 'like', $g.'.%'))
            ->when($f['q'] ?? null, function ($q, $texto) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $texto).'%';
                $q->where(fn ($w) => $w->where('summary', 'like', $like)->orWhere('user_name', 'like', $like));
            })
            ->when($f['desde'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', $d.' 00:00:00'))
            ->when($f['hasta'] ?? null, fn ($q, $d) => $q->where('created_at', '<=', $d.' 23:59:59'))
            ->orderByDesc('id')
            ->paginate($f['per_page'] ?? 25);

        $items->getCollection()->transform(fn (AuditLog $a) => [
            'id' => $a->id,
            'fecha' => $a->created_at?->toDateTimeString(),
            'usuario' => $a->user_name,
            'accion' => $a->action,
            'resumen' => $a->summary,
            'entidad_tipo' => $a->entity_type,
            'entidad_id' => $a->entity_id,
            'detalle' => $a->detail,
            'ip' => $a->ip,
        ]);

        return $items;
    }
}
