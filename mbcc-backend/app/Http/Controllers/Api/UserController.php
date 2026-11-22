<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Support\Auditoria;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Solo administrador (ver routes/api.php). Mantiene el contrato del frontend:
 * la contraseña llega en `password_hash` y el cast "hashed" del modelo la cifra.
 */
class UserController extends QualityResourceController
{
    protected string $modelClass = User::class;

    protected function rules(?int $id = null): array
    {
        return [
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'employee_number' => ['required', 'string', 'max:50', Rule::unique('users', 'employee_number')->ignore($id)],
            'full_name' => ['required', 'string', 'max:150'],
            'password_hash' => [$id ? 'sometimes' : 'required', 'string', 'min:8', 'max:100'],
            'is_active' => [$id ? 'required' : 'sometimes', 'boolean'],
        ];
    }

    public function index(Request $request)
    {
        return User::query()->orderBy('full_name')->paginate(min($request->integer('per_page', 15), 200));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['is_active'] ??= true;

        $user = User::create($data);
        Auditoria::registrar('usuario.crear', "Creó al usuario {$user->full_name} (empleado {$user->employee_number})", $user);

        return response()->json($user, 201);
    }

    public function update(Request $request, int $id)
    {
        $user = User::findOrFail($id);
        $data = $this->validated($request, $id);

        $cambiaNivel = (int) $data['role_id'] !== (int) $user->role_id;
        $desactiva = $user->is_active && ! $data['is_active'];
        if ($user->id === $request->user()->id && ($cambiaNivel || $desactiva)) {
            return response()->json([
                'message' => 'No puedes cambiar tu propio nivel ni desactivar tu propia cuenta.',
            ], 422);
        }

        $cierraSesiones = $cambiaNivel || $desactiva || isset($data['password_hash']);
        $reactiva = ! $user->is_active && $data['is_active'];
        $cambios = array_values(array_filter([
            $cambiaNivel ? 'nivel' : null,
            $desactiva ? 'desactivado' : null,
            $reactiva ? 'reactivado' : null,
            isset($data['password_hash']) ? 'contraseña' : null,
            $data['full_name'] !== $user->full_name ? 'nombre' : null,
            $data['employee_number'] !== $user->employee_number ? 'número de empleado' : null,
        ]));
        $user->update($data);
        Auditoria::registrar('usuario.editar', "Editó al usuario {$user->full_name} (empleado {$user->employee_number})".($cambios ? ': '.implode(', ', $cambios) : ''), $user, ['cambios' => $cambios]);

        // Si cambió el nivel, se desactivó la cuenta o la contraseña, las sesiones abiertas dejan de valer
        if ($cierraSesiones && $user->id !== $request->user()->id) {
            $user->tokens()->delete();
        }

        return $user->fresh();
    }
}
