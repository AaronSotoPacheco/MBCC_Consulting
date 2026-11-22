<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Support\Auditoria;
use Illuminate\Console\Command;

/**
 * Crea el primer usuario de una instalación nueva (sin cargar los datos de demostración) o restablece
 * la contraseña de alguien que se quedó sin acceso:
 *   php artisan mbcc:crear-admin 9001 "Nombre Apellido"
 *   php artisan mbcc:crear-admin 9001 "Nombre Apellido" --restablecer
 */
class CrearAdmin extends Command
{
    protected $signature = 'mbcc:crear-admin {numero : Número de empleado} {nombre : Nombre completo}
        {--nivel=admin : admin, supervisor o inspector}
        {--password= : Contraseña (si se omite, se pide sin mostrarla)}
        {--restablecer : Si el usuario ya existe, le pone esta contraseña y lo reactiva}';

    protected $description = 'Crea un usuario (por defecto administrador) o restablece su contraseña';

    public function handle(): int
    {
        $nivel = (string) $this->option('nivel');
        if (! in_array($nivel, ['admin', 'supervisor', 'inspector'], true)) {
            $this->error('El nivel debe ser admin, supervisor o inspector.');

            return self::FAILURE;
        }

        $numero = trim((string) $this->argument('numero'));
        $existente = User::where('employee_number', $numero)->first();
        if ($existente && ! $this->option('restablecer')) {
            $this->error("Ya existe el empleado {$numero}. Usa --restablecer para cambiarle la contraseña.");

            return self::FAILURE;
        }

        $password = $this->option('password') ?: $this->secret('Contraseña (mínimo 8 caracteres)');
        if (! is_string($password) || mb_strlen($password) < 8) {
            $this->error('La contraseña debe tener al menos 8 caracteres.');

            return self::FAILURE;
        }

        if ($existente) {
            $existente->update(['password_hash' => $password, 'is_active' => true]);
            $existente->tokens()->delete();
            Auditoria::registrar('usuario.editar', "Restableció la contraseña de {$existente->full_name} (empleado {$numero}) desde la consola", $existente, ['cambios' => ['contraseña']], $existente);
            $this->info("Contraseña restablecida para {$existente->full_name}. Sus sesiones abiertas se cerraron.");

            return self::SUCCESS;
        }

        $rol = Role::firstOrCreate(['name' => $nivel], ['description' => ucfirst($nivel)]);
        $user = User::create([
            'role_id' => $rol->id,
            'employee_number' => $numero,
            'full_name' => trim((string) $this->argument('nombre')),
            'password_hash' => $password,
            'is_active' => true,
        ]);
        Auditoria::registrar('usuario.crear', "Creó al usuario {$user->full_name} (empleado {$numero}, {$nivel}) desde la consola", $user, [], $user);
        $this->info("Usuario {$user->full_name} creado como {$nivel}. Ya puede iniciar sesión con el número {$numero}.");

        return self::SUCCESS;
    }
}
