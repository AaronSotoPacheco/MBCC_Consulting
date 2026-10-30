<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
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
            return response()->json(['message' => 'Número de empleado o contraseña incorrectos.'], 422);
        }

        return response()->json([
            'token' => $user->createToken('control-calidad', ['*'], now()->addHours(12))->plainTextToken,
            'user' => [
                'nombre' => $user->full_name,
                'rol' => $user->role?->name ?? 'Inspector',
                'turno' => isset($data['turno']) ? (int) $data['turno'] : null,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}