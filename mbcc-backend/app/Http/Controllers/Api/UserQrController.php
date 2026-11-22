<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Auditoria;
use App\Support\Mensajes;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Credenciales QR de empleados (solo administrador). El QR contiene un código aleatorio de un solo uso por persona;
 * en la base solo queda su hash, así que el código en claro solo existe en la respuesta de "generar".
 * Generar uno nuevo invalida el anterior y cierra las sesiones abiertas de ese empleado.
 */
class UserQrController extends Controller
{
    public const PREFIJO = 'MBCC-';

    public static function hash(string $codigo): string
    {
        return hash('sha256', $codigo);
    }

    private function rotar(User $user, User $quien): array
    {
        $codigo = self::PREFIJO.Str::random(40);
        $user->forceFill(['qr_token_hash' => self::hash($codigo), 'qr_generated_at' => now()])->save();

        // El código anterior y las sesiones abiertas dejan de valer (salvo la del administrador que lo genera)
        if ($user->id !== $quien->id) {
            $user->tokens()->delete();
        }

        Auditoria::registrar('qr.generar', "Generó un QR nuevo para {$user->full_name} (empleado {$user->employee_number})", $user, [], $quien);

        return [
            'id' => $user->id,
            'nombre' => $user->full_name,
            'numero_empleado' => $user->employee_number,
            'rol' => $user->role?->name,
            'qr' => $codigo,
        ];
    }

    public function generar(Request $request, int $id)
    {
        $user = User::with('role')->findOrFail($id);
        if (! $user->is_active) {
            return response()->json(['message' => 'No se puede generar un QR para un usuario inactivo.'], 422);
        }

        return $this->rotar($user, $request->user());
    }

    /** Genera (o regenera) el QR de varios empleados activos para imprimir los gafetes de una vez. */
    public function masivo(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ], Mensajes::ES, ['ids' => 'empleados']);

        $usuarios = User::with('role')->whereIn('id', $data['ids'])->where('is_active', true)->orderBy('full_name')->get();

        return $usuarios->map(fn (User $u) => $this->rotar($u, $request->user()))->values();
    }

    public function revocar(Request $request, int $id)
    {
        $user = User::findOrFail($id);
        $user->forceFill(['qr_token_hash' => null, 'qr_generated_at' => null])->save();
        if ($user->id !== $request->user()->id) {
            $user->tokens()->delete();
        }
        Auditoria::registrar('qr.revocar', "Revocó el QR de {$user->full_name} (empleado {$user->employee_number})", $user);

        return response()->noContent();
    }
}
