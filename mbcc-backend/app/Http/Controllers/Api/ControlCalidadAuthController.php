<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Auditoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ControlCalidadAuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'numero_empleado' => ['required', 'string'],
            'password' => ['required', 'string'],
            'turno' => ['nullable', 'integer', 'in:1,2,3'],
        ]);

        $user = User::with('role')
            ->where('employee_number', $data['numero_empleado'])
            ->where('is_active', true)
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password_hash)) {
            Auditoria::registrar('sesion.fallida', 'Intento de acceso fallido con el número de empleado '.mb_substr($data['numero_empleado'], 0, 50), null, ['via' => 'contraseña']);

            return response()->json(['message' => 'Número de empleado o contraseña incorrectos.'], 422);
        }

        Auditoria::registrar('sesion.inicio', 'Inició sesión con contraseña', null, ['via' => 'contraseña'], $user);

        return $this->sesion($user, $data['turno'] ?? null);
    }

    /** Inicio de sesión con el QR único del empleado (gafete). El turno se elige igual que en el acceso normal. */
    public function loginQr(Request $request)
    {
        $data = $request->validate([
            'qr' => ['required', 'string', 'max:100', 'regex:/^'.UserQrController::PREFIJO.'[A-Za-z0-9]{40}$/'],
            'turno' => ['nullable', 'integer', 'in:1,2,3'],
        ], ['qr.regex' => 'Ese código no es un QR de MBCC.', 'qr.required' => 'Escanea tu código QR.']);

        $user = User::with('role')
            ->where('qr_token_hash', UserQrController::hash($data['qr']))
            ->where('is_active', true)
            ->first();

        if (! $user) {
            Auditoria::registrar('sesion.fallida', 'Intento de acceso con un QR no válido o revocado', null, ['via' => 'qr']);

            return response()->json(['message' => 'Código QR no válido o revocado. Pide uno nuevo a un administrador.'], 422);
        }

        Auditoria::registrar('sesion.inicio', 'Inició sesión con su QR', null, ['via' => 'qr'], $user);

        return $this->sesion($user, $data['turno'] ?? null);
    }

    private function sesion(User $user, ?int $turno)
    {
        return response()->json([
            'token' => $user->createToken('control-calidad', ['*'], now()->addHours(12))->plainTextToken,
            'user' => [
                'id' => $user->id,
                'nombre' => $user->full_name,
                'rol' => $user->role?->name ?? 'Inspector',
                'turno' => isset($turno) ? (int) $turno : null,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}