<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Auditoria;
use App\Support\Mensajes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

/** Acciones de la propia cuenta del usuario con sesión. */
class PerfilController extends Controller
{
    public function cambiarPassword(Request $request)
    {
        $data = $request->validate([
            'actual' => ['required', 'string'],
            'nueva' => ['required', 'string', 'min:8', 'max:100', 'confirmed', 'different:actual'],
        ], Mensajes::con([
            'nueva.confirmed' => 'La confirmación no coincide con la contraseña nueva.',
            'nueva.different' => 'La contraseña nueva debe ser distinta a la actual.',
        ]), ['actual' => 'contraseña actual', 'nueva' => 'contraseña nueva']);

        $user = $request->user();
        if (! Hash::check($data['actual'], $user->password_hash)) {
            Auditoria::registrar('contrasena.fallida', 'Intento de cambio de contraseña con la contraseña actual incorrecta');

            return response()->json([
                'message' => 'La contraseña actual no es correcta.',
                'errors' => ['actual' => ['La contraseña actual no es correcta.']],
            ], 422);
        }

        $user->update(['password_hash' => $data['nueva']]); // el cast "hashed" la cifra

        // Cierra todas las demás sesiones (otros dispositivos); esta sigue abierta
        $token = $user->currentAccessToken();
        $user->tokens()
            ->when($token instanceof PersonalAccessToken, fn ($q) => $q->where('id', '!=', $token->id))
            ->delete();

        Auditoria::registrar('contrasena.cambio', 'Cambió su propia contraseña');

        return response()->json(['message' => 'Contraseña actualizada. Se cerraron tus otras sesiones.']);
    }
}
