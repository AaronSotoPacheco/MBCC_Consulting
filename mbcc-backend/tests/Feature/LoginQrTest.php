<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LoginQrTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $rol, string $num, bool $activo = true): User
    {
        $r = Role::firstOrCreate(['name' => $rol], ['description' => $rol]);

        return User::create(['role_id' => $r->id, 'employee_number' => $num, 'full_name' => "Empleado $num", 'password_hash' => Hash::make('x-secret-1'), 'is_active' => $activo]);
    }

    public function test_admin_genera_el_qr_y_el_empleado_inicia_sesion_con_el(): void
    {
        $insp = $this->usuario('inspector', '1001');
        Sanctum::actingAs($this->usuario('admin', '9001'));

        $qr = $this->postJson("/api/users/{$insp->id}/qr")->assertOk()->assertJsonPath('numero_empleado', '1001')->json('qr');
        $this->assertMatchesRegularExpression('/^MBCC-[A-Za-z0-9]{40}$/', $qr);

        // En la base solo queda el hash, nunca el código
        $this->assertDatabaseMissing('users', ['qr_token_hash' => $qr]);
        $this->assertDatabaseHas('users', ['id' => $insp->id, 'qr_token_hash' => hash('sha256', $qr)]);
        $this->getJson('/api/users')->assertOk()->assertJsonMissingPath('data.0.qr_token_hash');

        app('auth')->forgetGuards();
        $this->postJson('/api/login/qr', ['qr' => $qr, 'turno' => 2])
            ->assertOk()
            ->assertJsonPath('user.nombre', 'Empleado 1001')
            ->assertJsonPath('user.rol', 'inspector')
            ->assertJsonPath('user.turno', 2)
            ->assertJsonStructure(['token']);
    }

    public function test_qr_invalido_revocado_regenerado_o_de_usuario_inactivo_no_entra(): void
    {
        $insp = $this->usuario('inspector', '1001');
        Sanctum::actingAs($this->usuario('admin', '9001'));
        $viejo = $this->postJson("/api/users/{$insp->id}/qr")->json('qr');
        $nuevo = $this->postJson("/api/users/{$insp->id}/qr")->json('qr');
        app('auth')->forgetGuards();

        $this->postJson('/api/login/qr', ['qr' => $viejo])->assertStatus(422);   // regenerado: el anterior ya no sirve
        $this->postJson('/api/login/qr', ['qr' => 'hola'])->assertStatus(422)->assertJsonValidationErrors('qr');
        $this->postJson('/api/login/qr', ['qr' => 'MBCC-'.str_repeat('a', 40)])->assertStatus(422);
        $this->postJson('/api/login/qr', ['qr' => $nuevo])->assertOk();

        $insp->update(['is_active' => false]);
        $this->postJson('/api/login/qr', ['qr' => $nuevo])->assertStatus(422);
    }

    public function test_revocar_elimina_el_codigo_y_cierra_sesiones(): void
    {
        $insp = $this->usuario('inspector', '1001');
        $admin = $this->usuario('admin', '9001');
        Sanctum::actingAs($admin);
        $qr = $this->postJson("/api/users/{$insp->id}/qr")->json('qr');
        app('auth')->forgetGuards();
        $this->postJson('/api/login/qr', ['qr' => $qr])->assertOk();
        $this->assertSame(1, $insp->tokens()->count());

        Sanctum::actingAs($admin);
        $this->deleteJson("/api/users/{$insp->id}/qr")->assertNoContent();
        $this->assertSame(0, $insp->tokens()->count());
        $this->assertDatabaseHas('users', ['id' => $insp->id, 'qr_token_hash' => null]);
        app('auth')->forgetGuards();
        $this->postJson('/api/login/qr', ['qr' => $qr])->assertStatus(422);
    }

    public function test_generacion_masiva_solo_para_activos_y_solo_admin(): void
    {
        $a = $this->usuario('inspector', '1001');
        $b = $this->usuario('supervisor', '2001');
        $c = $this->usuario('inspector', '1002', false);

        Sanctum::actingAs($a);
        $this->postJson('/api/users/qr-masivo', ['ids' => [$a->id]])->assertForbidden();
        $this->postJson("/api/users/{$b->id}/qr")->assertForbidden();

        Sanctum::actingAs($this->usuario('admin', '9001'));
        $r = $this->postJson('/api/users/qr-masivo', ['ids' => [$a->id, $b->id, $c->id]])->assertOk()->assertJsonCount(2);
        $this->assertEqualsCanonicalizing(['1001', '2001'], collect($r->json())->pluck('numero_empleado')->all());
        $this->postJson("/api/users/{$c->id}/qr")->assertStatus(422);
        $this->postJson('/api/users/qr-masivo', ['ids' => []])->assertStatus(422);
    }
}
