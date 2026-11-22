<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DefectCatalog;
use App\Models\PartNumber;
use App\Models\Role;
use App\Models\SortOrder;
use App\Models\User;
use App\Models\WiReferenceImage;
use App\Models\WorkInstruction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ComparacionIATest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $rol): User
    {
        $r = Role::firstOrCreate(['name' => $rol], ['description' => $rol]);

        return User::create([
            'role_id' => $r->id, 'employee_number' => strtoupper($rol).'-1', 'full_name' => "Usuario $rol",
            'password_hash' => Hash::make('x-secret-1'), 'is_active' => true,
        ]);
    }

    private function orden(string $estado = 'OPEN'): SortOrder
    {
        $c = Client::create(['name' => 'Cliente']);
        $p = PartNumber::create(['client_id' => $c->id, 'part_number' => 'MB-1', 'description' => 'Carcasa']);
        $wi = WorkInstruction::create(['part_number_id' => $p->id, 'code' => 'WI-1', 'revision' => 'A', 'title' => 'Carcasa', 'file_path' => 'wi/x.pdf', 'status' => 'ACTIVE']);

        return SortOrder::create(['order_number' => 'SO-1', 'part_number_id' => $p->id, 'work_instruction_id' => $wi->id, 'target_quantity' => 10, 'status' => $estado, 'shift_hours' => '8-4']);
    }

    private function foto(string $nombre = 'f.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($nombre, 80, 80);
    }

    private function conReferenciaOk(SortOrder $o): void
    {
        Storage::disk('local')->put('wi-referencias/ok.jpg', 'jpgdata');
        WiReferenceImage::create(['work_instruction_id' => $o->work_instruction_id, 'kind' => 'OK', 'file_path' => 'wi-referencias/ok.jpg', 'mime' => 'image/jpeg']);
    }

    private function respuestaIA(array $input): array
    {
        return ['https://api.anthropic.com/v1/messages' => Http::response([
            'content' => [['type' => 'tool_use', 'name' => 'registrar_veredicto', 'input' => $input]],
        ])];
    }

    public function test_supervisor_sube_y_borra_referencias_y_el_inspector_las_ve_pero_no_gestiona(): void
    {
        Storage::fake('local');
        $o = $this->orden();

        Sanctum::actingAs($this->usuario('supervisor'));
        $id = $this->postJson("/api/work-instructions/{$o->work_instruction_id}/referencias", ['tipo' => 'OK', 'imagen' => $this->foto()])
            ->assertCreated()->assertJsonPath('tipo', 'OK')->json('id');
        $this->postJson("/api/work-instructions/{$o->work_instruction_id}/referencias", ['tipo' => 'NG', 'imagen' => $this->foto()])
            ->assertStatus(422)->assertJsonValidationErrors('etiqueta');
        $this->postJson("/api/work-instructions/{$o->work_instruction_id}/referencias", ['tipo' => 'NG', 'etiqueta' => 'Rayón', 'imagen' => $this->foto()])
            ->assertCreated()->assertJsonPath('etiqueta', 'Rayón');

        Sanctum::actingAs($this->usuario('inspector'));
        $this->getJson("/api/ordenes/{$o->id}/referencias")->assertOk()->assertJsonCount(2);
        $this->get("/api/referencias/{$id}/imagen")->assertOk();
        $this->postJson("/api/work-instructions/{$o->work_instruction_id}/referencias", ['tipo' => 'OK', 'imagen' => $this->foto()])->assertForbidden();
        $this->deleteJson("/api/referencias/{$id}")->assertForbidden();

        Sanctum::actingAs($this->usuario('admin'));
        $this->deleteJson("/api/referencias/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('wi_reference_images', ['id' => $id]);
    }

    public function test_limite_de_fotos_por_tipo_y_formato(): void
    {
        Storage::fake('local');
        $o = $this->orden();
        Sanctum::actingAs($this->usuario('supervisor'));

        for ($i = 0; $i < 4; $i++) {
            $this->postJson("/api/work-instructions/{$o->work_instruction_id}/referencias", ['tipo' => 'OK', 'imagen' => $this->foto()])->assertCreated();
        }
        $this->postJson("/api/work-instructions/{$o->work_instruction_id}/referencias", ['tipo' => 'OK', 'imagen' => $this->foto()])->assertStatus(422);
        $this->postJson("/api/work-instructions/{$o->work_instruction_id}/referencias", ['tipo' => 'NG', 'etiqueta' => 'X', 'imagen' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])
            ->assertStatus(422)->assertJsonValidationErrors('imagen');
    }

    public function test_comparar_devuelve_el_veredicto_y_valida_el_defecto_contra_el_catalogo(): void
    {
        Storage::fake('local');
        config(['services.anthropic.key' => 'test-key']);
        DefectCatalog::create(['code' => 'RAYON', 'category' => 'COSMETIC', 'description' => 'Rayón en carcasa', 'is_active' => true]);
        $o = $this->orden();
        $this->conReferenciaOk($o);
        Http::fake($this->respuestaIA(['evaluable' => true, 'resultado' => 'NG', 'defecto' => 'rayón en carcasa', 'confianza' => 'alta', 'explicacion' => 'Hay un rayón.']));

        Sanctum::actingAs($this->usuario('inspector'));
        $this->postJson("/api/ordenes/{$o->id}/comparar", ['imagen' => $this->foto()])
            ->assertOk()
            ->assertJsonPath('resultado', 'NG')
            ->assertJsonPath('defecto', 'Rayón en carcasa')
            ->assertJsonPath('confianza', 'alta')
            ->assertJsonPath('evaluable', true)
            ->assertJsonPath('referencias', 1);

        Http::assertSent(fn ($r) => $r->hasHeader('x-api-key', 'test-key') && $r['tool_choice']['name'] === 'registrar_veredicto');
    }

    public function test_un_defecto_inventado_por_la_ia_se_descarta(): void
    {
        Storage::fake('local');
        config(['services.anthropic.key' => 'test-key']);
        $o = $this->orden();
        $this->conReferenciaOk($o);
        Http::fake($this->respuestaIA(['evaluable' => true, 'resultado' => 'NG', 'defecto' => 'Algo raro', 'confianza' => 'media', 'explicacion' => 'x']));

        Sanctum::actingAs($this->usuario('inspector'));
        $this->postJson("/api/ordenes/{$o->id}/comparar", ['imagen' => $this->foto()])->assertOk()->assertJsonPath('defecto', null);
    }

    public function test_comparar_requiere_referencia_ok_clave_y_orden_abierta(): void
    {
        Storage::fake('local');
        $o = $this->orden();
        Sanctum::actingAs($this->usuario('inspector'));

        config(['services.anthropic.key' => 'test-key']);
        $this->postJson("/api/ordenes/{$o->id}/comparar", ['imagen' => $this->foto()])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'referencia OK'));

        $this->conReferenciaOk($o);
        config(['services.anthropic.key' => null]);
        $this->postJson("/api/ordenes/{$o->id}/comparar", ['imagen' => $this->foto()])->assertStatus(503);

        $o->update(['status' => 'PAUSED']);
        $this->postJson("/api/ordenes/{$o->id}/comparar", ['imagen' => $this->foto()])->assertStatus(422);
    }

    public function test_errores_del_servicio_de_ia_se_traducen(): void
    {
        Storage::fake('local');
        config(['services.anthropic.key' => 'test-key']);
        $o = $this->orden();
        $this->conReferenciaOk($o);
        Sanctum::actingAs($this->usuario('inspector'));

        // Un solo Http::fake con respuestas en secuencia (un segundo fake no reemplazaría al primero)
        Http::fake(['https://api.anthropic.com/v1/messages' => Http::sequence()
            ->push(['error' => 'x'], 401)
            ->push(['error' => 'x'], 429)]);

        $this->postJson("/api/ordenes/{$o->id}/comparar", ['imagen' => $this->foto()])->assertStatus(503);
        $this->postJson("/api/ordenes/{$o->id}/comparar", ['imagen' => $this->foto()])->assertStatus(429);
    }

    public function test_comparar_exige_sesion(): void
    {
        $o = $this->orden();
        $this->postJson("/api/ordenes/{$o->id}/comparar", [])->assertUnauthorized();
    }
}
