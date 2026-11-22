<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/** Salud del sistema (para monitores externos y Docker) y recepción de errores del navegador. */
class MonitoreoController extends Controller
{
    /** Público: no expone datos, solo si la base de datos y el almacenamiento de archivos responden. */
    public function salud()
    {
        $bd = false;
        $archivos = false;

        try {
            DB::select('select 1');
            $bd = true;
        } catch (\Throwable $e) {
            Log::error('Salud: la base de datos no responde: '.$e->getMessage());
        }

        try {
            $marca = 'salud-'.bin2hex(random_bytes(4)).'.tmp';
            $archivos = Storage::disk('local')->put($marca, 'ok') && Storage::disk('local')->delete($marca);
        } catch (\Throwable $e) {
            Log::error('Salud: el almacenamiento no es escribible: '.$e->getMessage());
        }

        $ok = $bd && $archivos;

        return response()->json([
            'estado' => $ok ? 'ok' : 'falla',
            'base_de_datos' => $bd,
            'almacenamiento' => $archivos,
            'hora' => now()->toIso8601String(),
        ], $ok ? 200 : 503);
    }

    /** Errores de JavaScript que ocurren en el navegador de la planta; quedan en storage/logs/frontend-*.log. */
    public function errorCliente(Request $request)
    {
        $d = $request->validate([
            'mensaje' => ['required', 'string', 'max:500'],
            'detalle' => ['nullable', 'string', 'max:4000'],
            'url' => ['nullable', 'string', 'max:300'],
            'tipo' => ['nullable', 'in:error,promesa,render'],
        ]);

        Log::channel('frontend')->error($d['mensaje'], [
            'tipo' => $d['tipo'] ?? 'error',
            'url' => $d['url'] ?? null,
            'detalle' => $d['detalle'] ?? null,
            'usuario_id' => $request->user('sanctum')?->id,
            'ip' => $request->ip(),
            'navegador' => mb_substr((string) $request->userAgent(), 0, 200),
        ]);

        return response()->noContent();
    }
}
