<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\PartNumber;
use App\Models\Role;
use App\Models\SortOrder;
use App\Models\User;
use App\Models\WorkInstruction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Punto 4: reportes restringidos, cambio de contraseña propio, bitácora de auditoría y cabeceras de seguridad. */
class SeguridadTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $nivel, ?string $numero = null): User
    {
        $role = Role::firstOrCreate(['name' => $nivel], ['description' => ucfirst($nivel)]);

        return User::create([
            'role_id' => $role->id,
            'employee_number' => $numero ?? strtoupper($nivel).'-1',
            'full_name' => 'Usuario '.$nivel,
            'password_hash' => Hash::make('clave-actual-1'),
            'is_active' => true,
        ]);
    }

    private function orden(string $estado = 'OPEN'): SortOrder
    {
        $client = Client::firstOrCreate(['name' => 'Cliente']);
        $part = PartNumber::firstOrCreate(['client_id' => $client->id, 'part_number' => 'P-1'], ['description' => 'Pieza']);
        $wi = WorkInstruction::firstOrCreate(
            ['code' => 'WI-9', 'revision' => 'A'],
            ['part_number_id' => $part->id, 'title' => 'Inspección', 'file_path' => 'wi/x.pdf', 'status' => 'DRAFT'],
        );

        return SortOrder::create([
            'order_number' => 'SO-77', 'part_number_id' => $part->id, 'work_instruction_id' => $wi->id,
            'target_quantity' => 10, 'status' => $estado, 'shift_hours' => '8:00 AM - 4:00 PM',
        ]);
    }

    // ---------------------------------------------------------------- reportes

    public function test_reports_are_for_supervisors_and_admins_only(): void
    {
        $rango = 'desde='.today()->toDateString().'&hasta='.today()->toDateString();

        Sanctum::actingAs($this->usuario('inspector'));
        $this->getJson("/api/reportes/resumen?$rango")->assertForbidden();
        $this->get("/api/reportes/datos.csv?$rango")->assertForbidden();

        Sanctum::actingAs($this->usuario('supervisor'));
        $this->getJson("/api/reportes/resumen?$rango")->assertOk();
        $this->get("/api/reportes/datos.csv?$rango")->assertOk();
    }

    // ---------------------------------------------------------------- contraseña propia

    public function test_user_changes_own_password_and_other_sessions_are_closed(): void
    {
        $user = $this->usuario('inspector', '1001');
        $t1 = $this->postJson('/api/login', ['numero_empleado' => '1001', 'password' => 'clave-actual-1'])->json('token');
        $t2 = $this->postJson('/api/login', ['numero_empleado' => '1001', 'password' => 'clave-actual-1'])->json('token');
        $this->app['auth']->forgetGuards();

        $this->withToken($t1)->postJson('/api/me/password', [
            'actual' => 'clave-actual-1', 'nueva' => 'clave-nueva-22', 'nueva_confirmation' => 'clave-nueva-22',
        ])->assertOk();

        $this->assertTrue(Hash::check('clave-nueva-22', $user->fresh()->password_hash));
        $this->assertSame(1, $user->tokens()->count()); // solo queda la sesión desde la que se cambió
        $this->assertDatabaseHas('audit_logs', ['action' => 'contrasena.cambio', 'user_id' => $user->id]);

        $this->postJson('/api/login', ['numero_empleado' => '1001', 'password' => 'clave-actual-1'])->assertUnprocessable();
        $this->postJson('/api/login', ['numero_empleado' => '1001', 'password' => 'clave-nueva-22'])->assertOk();
        $this->assertNotNull($t2);
    }

    public function test_password_change_validates_current_confirmation_and_strength(): void
    {
        $user = $this->usuario('inspector');
        Sanctum::actingAs($user);

        $this->postJson('/api/me/password', ['actual' => 'mala', 'nueva' => 'clave-nueva-22', 'nueva_confirmation' => 'clave-nueva-22'])
            ->assertUnprocessable()->assertJsonValidationErrors('actual');
        $this->postJson('/api/me/password', ['actual' => 'clave-actual-1', 'nueva' => 'clave-nueva-22', 'nueva_confirmation' => 'otra-cosa-123'])
            ->assertUnprocessable()->assertJsonValidationErrors('nueva');
        $this->postJson('/api/me/password', ['actual' => 'clave-actual-1', 'nueva' => 'corta', 'nueva_confirmation' => 'corta'])
            ->assertUnprocessable()->assertJsonValidationErrors('nueva');
        $this->postJson('/api/me/password', ['actual' => 'clave-actual-1', 'nueva' => 'clave-actual-1', 'nueva_confirmation' => 'clave-actual-1'])
            ->assertUnprocessable()->assertJsonValidationErrors('nueva');

        $this->assertTrue(Hash::check('clave-actual-1', $user->fresh()->password_hash));
        $this->assertDatabaseHas('audit_logs', ['action' => 'contrasena.fallida', 'user_id' => $user->id]);
    }

    public function test_password_change_requires_a_session(): void
    {
        $this->postJson('/api/me/password', [])->assertUnauthorized();
    }

    // ---------------------------------------------------------------- bitácora

    public function test_audit_log_records_approvals_order_changes_and_qr_regeneration(): void
    {
        $orden = $this->orden();
        $wi = $orden->workInstruction;
        $supervisor = $this->usuario('supervisor');
        Sanctum::actingAs($supervisor);

        $this->postJson("/api/work-instructions/{$wi->id}/aprobar")->assertOk();
        $this->patchJson("/api/ordenes/{$orden->id}/estado", ['estado' => 'CLOSED'])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'instruccion.aprobar', 'user_id' => $supervisor->id, 'entity_type' => 'instruccion', 'entity_id' => $wi->id,
        ]);
        $cierre = AuditLog::where('action', 'orden.estado')->firstOrFail();
        $this->assertSame($supervisor->id, $cierre->user_id);
        $this->assertSame('Usuario supervisor', $cierre->user_name);
        $this->assertSame(['de' => 'OPEN', 'a' => 'CLOSED'], $cierre->detail);
        $this->assertStringContainsString('Cerró la orden SO-77', $cierre->summary);

        $admin = $this->usuario('admin');
        $inspector = $this->usuario('inspector');
        Sanctum::actingAs($admin);
        $this->postJson("/api/users/{$inspector->id}/qr")->assertOk();
        $this->deleteJson("/api/users/{$inspector->id}/qr")->assertNoContent();
        $this->assertDatabaseHas('audit_logs', ['action' => 'qr.generar', 'user_id' => $admin->id, 'entity_id' => $inspector->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'qr.revocar', 'user_id' => $admin->id, 'entity_id' => $inspector->id]);

        // El código QR en claro nunca llega a la bitácora
        $this->assertStringNotContainsString('MBCC-', AuditLog::query()->get()->toJson());
    }

    public function test_audit_log_records_user_changes_and_login_attempts(): void
    {
        $admin = $this->usuario('admin');
        $inspector = $this->usuario('inspector', '3003');
        $role = $inspector->role_id;

        // Un intento fallido ocurre sin sesión: queda sin usuario y sin la contraseña escrita
        $this->postJson('/api/login', ['numero_empleado' => 'NO-EXISTE', 'password' => 'x-secreto-9'])->assertUnprocessable();
        $fallo = AuditLog::where('action', 'sesion.fallida')->firstOrFail();
        $this->assertNull($fallo->user_id);
        $this->assertStringContainsString('NO-EXISTE', $fallo->summary);
        $this->assertStringNotContainsString('x-secreto-9', AuditLog::query()->get()->toJson());

        Sanctum::actingAs($admin);

        $this->putJson("/api/users/{$inspector->id}", [
            'role_id' => $role, 'employee_number' => '3003', 'full_name' => 'Usuario inspector',
            'password_hash' => 'otra-clave-123', 'is_active' => false,
        ])->assertOk();
        $cambio = AuditLog::where('action', 'usuario.editar')->firstOrFail();
        $this->assertSame(['desactivado', 'contraseña'], $cambio->detail['cambios']);
        $this->assertStringNotContainsString('otra-clave-123', AuditLog::query()->get()->toJson());

        $this->postJson('/api/login', ['numero_empleado' => $admin->employee_number, 'password' => 'clave-actual-1'])->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'sesion.inicio', 'user_id' => $admin->id]);
    }

    public function test_only_admin_reads_the_audit_log_with_filters(): void
    {
        AuditLog::create(['user_name' => 'Ana', 'action' => 'orden.estado', 'summary' => 'Cerró la orden SO-1']);
        AuditLog::create(['user_name' => 'Luis', 'action' => 'qr.generar', 'summary' => 'Generó un QR nuevo para Pedro']);
        AuditLog::create(['user_name' => 'Luis', 'action' => 'instruccion.aprobar', 'summary' => 'Aprobó la instrucción WI-1 rev. A']);

        Sanctum::actingAs($this->usuario('inspector'));
        $this->getJson('/api/auditoria')->assertForbidden();
        Sanctum::actingAs($this->usuario('supervisor'));
        $this->getJson('/api/auditoria')->assertForbidden();

        Sanctum::actingAs($this->usuario('admin'));
        $this->getJson('/api/auditoria')->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonPath('data.0.accion', 'instruccion.aprobar'); // lo más reciente primero
        $this->getJson('/api/auditoria?grupo=qr')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.usuario', 'Luis');
        $this->getJson('/api/auditoria?q=SO-1')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/auditoria?q=100%25')->assertOk()->assertJsonPath('total', 0); // % se toma literal
        $this->getJson('/api/auditoria?grupo=otra')->assertUnprocessable();
        $this->getJson('/api/auditoria?desde='.today()->addDay()->toDateString())->assertOk()->assertJsonPath('total', 0);
    }

    // ---------------------------------------------------------------- cabeceras

    public function test_api_responses_carry_security_headers(): void
    {
        $this->getJson('/api/user')->assertUnauthorized()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()')
            ->assertHeaderMissing('Strict-Transport-Security');

        $this->getJson('https://localhost/api/user')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
}
