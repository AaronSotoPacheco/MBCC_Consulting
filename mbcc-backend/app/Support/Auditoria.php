<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Bitácora de auditoría. Un fallo al escribirla nunca debe tumbar la operación del usuario,
 * por eso se registra en el log de la aplicación y se sigue.
 */
class Auditoria
{
    /**
     * @param  string  $accion  grupo.accion (instruccion.aprobar, orden.estado, qr.generar, sesion.fallida…)
     * @param  array<string, mixed>  $detalle  datos útiles (nunca contraseñas, códigos QR ni tokens)
     * @param  User|null  $usuario  quien actúa; por defecto el usuario autenticado
     */
    public static function registrar(string $accion, string $resumen, ?Model $entidad = null, array $detalle = [], ?User $usuario = null): void
    {
        try {
            $usuario ??= auth()->user();

            AuditLog::create([
                'user_id' => $usuario?->id,
                'user_name' => $usuario?->full_name,
                'action' => $accion,
                'entity_type' => $entidad ? self::tipo($entidad) : null,
                'entity_id' => $entidad?->getKey(),
                'summary' => mb_substr($resumen, 0, 255),
                'detail' => $detalle ?: null,
                'ip' => request()?->ip(),
            ]);
        } catch (\Throwable $e) {
            Log::error('No se pudo escribir la bitácora de auditoría: '.$e->getMessage(), ['accion' => $accion]);
        }
    }

    private static function tipo(Model $m): string
    {
        return match (class_basename($m)) {
            'WorkInstruction' => 'instruccion',
            'SortOrder' => 'orden',
            'User' => 'usuario',
            default => strtolower(class_basename($m)),
        };
    }
}
