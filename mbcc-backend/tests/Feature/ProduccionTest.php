<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

/** Punto 6: salud del sistema, errores del navegador y alta del primer administrador por consola. */
class ProduccionTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_is_public_and_reports_database_and_storage(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        $this->getJson('/api/salud')->assertOk()
            ->assertJsonPath('estado', 'ok')
            ->assertJsonPath('base_de_datos', true)
            ->assertJsonPath('almacenamiento', true)
            ->assertJsonMissingPath('usuarios');
    }

    public function test_browser_errors_are_logged_in_their_own_channel(): void
    {
        $canal = Mockery::mock();
        $canal->shouldReceive('error')->once()->withArgs(fn ($m, $ctx) => $m === 'Cannot read x' && $ctx['tipo'] === 'render' && $ctx['url'] === 'http://planta/#/estacion/1');
        Log::shouldReceive('channel')->with('frontend')->once()->andReturn($canal);

        $this->postJson('/api/errores-cliente', ['mensaje' => 'Cannot read x', 'detalle' => 'at Station', 'url' => 'http://planta/#/estacion/1', 'tipo' => 'render'])
            ->assertNoContent();
    }

    public function test_browser_error_reports_are_validated_and_size_limited(): void
    {
        $this->postJson('/api/errores-cliente', [])->assertUnprocessable();
        $this->postJson('/api/errores-cliente', ['mensaje' => str_repeat('x', 501)])->assertUnprocessable();
        $this->postJson('/api/errores-cliente', ['mensaje' => 'ok', 'tipo' => 'otro'])->assertUnprocessable();
    }

    public function test_command_creates_the_first_admin_who_can_log_in(): void
    {
        $this->artisan('mbcc:crear-admin', ['numero' => 'A-1', 'nombre' => 'Ana Admin', '--password' => 'clave-segura-1'])->assertSuccessful();

        $user = User::where('employee_number', 'A-1')->firstOrFail();
        $this->assertSame('admin', $user->role->name);
        $this->assertTrue(Hash::check('clave-segura-1', $user->password_hash));
        $this->postJson('/api/login', ['numero_empleado' => 'A-1', 'password' => 'clave-segura-1'])->assertOk()->assertJsonPath('user.rol', 'admin');
        $this->assertDatabaseHas('audit_logs', ['action' => 'usuario.crear', 'entity_id' => $user->id]);
    }

    public function test_command_asks_for_the_password_when_not_given(): void
    {
        $this->artisan('mbcc:crear-admin', ['numero' => 'S-1', 'nombre' => 'Sara', '--nivel' => 'supervisor'])
            ->expectsQuestion('Contraseña (mínimo 8 caracteres)', 'otra-clave-22')
            ->assertSuccessful();

        $this->assertSame('supervisor', User::where('employee_number', 'S-1')->firstOrFail()->role->name);
    }

    public function test_command_refuses_short_passwords_bad_levels_and_duplicates(): void
    {
        $this->artisan('mbcc:crear-admin', ['numero' => 'A-1', 'nombre' => 'Ana', '--password' => 'corta'])->assertFailed();
        $this->artisan('mbcc:crear-admin', ['numero' => 'A-1', 'nombre' => 'Ana', '--password' => 'clave-segura-1', '--nivel' => 'dios'])->assertFailed();
        $this->assertDatabaseCount('users', 0);

        $this->artisan('mbcc:crear-admin', ['numero' => 'A-1', 'nombre' => 'Ana', '--password' => 'clave-segura-1'])->assertSuccessful();
        $this->artisan('mbcc:crear-admin', ['numero' => 'A-1', 'nombre' => 'Ana', '--password' => 'clave-segura-2'])->assertFailed();
        $this->assertTrue(Hash::check('clave-segura-1', User::firstOrFail()->password_hash));
    }

    public function test_command_can_reset_the_password_and_reactivate_an_account(): void
    {
        $this->artisan('mbcc:crear-admin', ['numero' => 'A-1', 'nombre' => 'Ana', '--password' => 'clave-segura-1'])->assertSuccessful();
        $user = User::firstOrFail();
        $user->update(['is_active' => false]);
        Sanctum::actingAs($user);
        $user->createToken('x');

        $this->artisan('mbcc:crear-admin', ['numero' => 'A-1', 'nombre' => 'Ana', '--password' => 'clave-nueva-33', '--restablecer' => true])->assertSuccessful();

        $user->refresh();
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('clave-nueva-33', $user->password_hash));
        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(1, Role::where('name', 'admin')->count());
    }
}
