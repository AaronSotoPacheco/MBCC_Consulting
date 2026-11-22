<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\PartNumber;
use App\Models\Role;
use App\Models\SortOrder;
use App\Models\User;
use App\Models\WorkInstruction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GestionOrdenesTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------- seguridad

    public function test_resource_routes_require_authentication(): void
    {
        foreach (['users', 'clients', 'part-numbers', 'work-instructions', 'defect-catalog', 'roles'] as $path) {
            $this->getJson("/api/{$path}")->assertUnauthorized();
        }
        $this->postJson('/api/users', [])->assertUnauthorized();
        $this->getJson('/api/turno')->assertUnauthorized();
    }

    public function test_internal_tables_are_not_exposed_as_public_resources(): void
    {
        $this->actingAsLevel('admin');

        foreach (['sort-orders', 'serial-records', 'serial-defect-logs', 'rework-logs'] as $path) {
            $this->getJson("/api/{$path}")->assertNotFound();
        }
    }

    public function test_inspector_cannot_manage_anything(): void
    {
        $this->actingAsLevel('inspector');
        [$part, $wi] = $this->partWithWi();

        $this->postJson('/api/ordenes', [
            'numero_orden' => 'SO-1', 'parte_id' => $part->id, 'instruccion_id' => $wi->id, 'meta' => 10,
        ])->assertForbidden();
        $this->getJson('/api/ordenes/opciones')->assertForbidden();
        $this->getJson('/api/work-instructions')->assertForbidden();
        $this->getJson('/api/users')->assertForbidden();
        $this->postJson('/api/users', [])->assertForbidden();
    }

    public function test_supervisor_cannot_use_admin_only_resources(): void
    {
        $this->actingAsLevel('supervisor');

        $this->getJson('/api/users')->assertForbidden();
        $this->getJson('/api/clients')->assertForbidden();
        $this->getJson('/api/defect-catalog')->assertForbidden();
        $this->getJson('/api/work-instructions')->assertOk();
        $this->getJson('/api/part-numbers')->assertOk();
    }

    // ---------------------------------------------------------------- órdenes

    public function test_supervisor_creates_an_open_order_and_duplicates_are_rejected(): void
    {
        $this->actingAsLevel('supervisor');
        [$part, $wi] = $this->partWithWi();
        $body = ['numero_orden' => 'SO-5001', 'parte_id' => $part->id, 'instruccion_id' => $wi->id, 'meta' => 500, 'turno' => '8:00 AM - 4:00 PM'];

        $this->postJson('/api/ordenes', $body)->assertCreated()
            ->assertJsonPath('so', 'SO-5001')
            ->assertJsonPath('estado', 'OPEN')
            ->assertJsonPath('meta', 500)
            ->assertJsonPath('wi.codigo', 'WI-1');

        $this->postJson('/api/ordenes', $body)->assertUnprocessable()->assertJsonValidationErrors('numero_orden');
    }

    public function test_order_requires_an_active_instruction_that_belongs_to_the_part(): void
    {
        $this->actingAsLevel('supervisor');
        [$part, $wi] = $this->partWithWi();
        $wi->update(['status' => 'DRAFT']);
        $body = ['numero_orden' => 'SO-5002', 'parte_id' => $part->id, 'instruccion_id' => $wi->id, 'meta' => 10];

        $this->postJson('/api/ordenes', $body)->assertUnprocessable()->assertJsonValidationErrors('instruccion_id');

        $otherPart = PartNumber::create(['client_id' => $part->client_id, 'part_number' => 'OTRA']);
        $wi->update(['status' => 'ACTIVE']);
        $this->postJson('/api/ordenes', [...$body, 'parte_id' => $otherPart->id])
            ->assertUnprocessable()->assertJsonValidationErrors('instruccion_id');

        $this->postJson('/api/ordenes', [...$body, 'meta' => 0])->assertUnprocessable()->assertJsonValidationErrors('meta');
    }

    public function test_order_status_transitions_and_admin_only_reopen(): void
    {
        $supervisor = $this->actingAsLevel('supervisor');
        $order = $this->order('SO-6001');

        $this->patchJson("/api/ordenes/{$order->id}/estado", ['estado' => 'PAUSED'])->assertOk()->assertJsonPath('estado', 'PAUSED');
        $this->patchJson("/api/ordenes/{$order->id}/estado", ['estado' => 'OPEN'])->assertOk()->assertJsonPath('estado', 'OPEN');
        $this->patchJson("/api/ordenes/{$order->id}/estado", ['estado' => 'OPEN'])->assertUnprocessable();
        $this->patchJson("/api/ordenes/{$order->id}/estado", ['estado' => 'CLOSED'])->assertOk()->assertJsonPath('estado', 'CLOSED');

        // Una cerrada no se edita ni la reabre un supervisor
        $this->patchJson("/api/ordenes/{$order->id}", ['meta' => 20])->assertUnprocessable();
        $this->patchJson("/api/ordenes/{$order->id}/estado", ['estado' => 'OPEN'])->assertForbidden();
        $this->assertSame('CLOSED', $order->fresh()->status);

        Sanctum::actingAs($this->makeUser('admin', 'ADM-1'));
        $this->patchJson("/api/ordenes/{$order->id}/estado", ['estado' => 'OPEN'])->assertOk()->assertJsonPath('estado', 'OPEN');
    }

    public function test_update_changes_only_target_and_shift(): void
    {
        $this->actingAsLevel('supervisor');
        $order = $this->order('SO-6002');

        $this->patchJson("/api/ordenes/{$order->id}", ['meta' => 250, 'turno' => '4:00 PM - 12:00 AM'])
            ->assertOk()->assertJsonPath('meta', 250)->assertJsonPath('turno', '4:00 PM - 12:00 AM');
    }

    public function test_closed_orders_are_listed_only_for_supervisor_and_admin(): void
    {
        $this->order('SO-7001');
        $closed = $this->order('SO-7002', 'CLOSED');

        $this->actingAsLevel('inspector');
        $this->getJson('/api/ordenes?cerradas=1')->assertOk()->assertJsonMissing(['so' => $closed->order_number]);

        Sanctum::actingAs($this->makeUser('supervisor', 'SUP-1'));
        $this->getJson('/api/ordenes')->assertOk()->assertJsonMissing(['so' => $closed->order_number]);
        $this->getJson('/api/ordenes?cerradas=1')->assertOk()->assertJsonFragment(['so' => 'SO-7002']);
    }

    public function test_inspection_is_rejected_when_the_order_is_paused(): void
    {
        $user = $this->actingAsLevel('inspector');
        $order = $this->order('SO-7003', 'PAUSED');

        $this->postJson("/api/ordenes/{$order->id}/inspecciones", ['serie' => 'SN-1', 'resultado' => 'OK'])->assertUnprocessable();
        $this->assertSame(0, $order->serialRecords()->count());
        $this->assertNotNull($user);
    }

    // ---------------------------------------------------------------- instrucciones de trabajo

    public function test_work_instruction_upload_approval_and_obsolescence(): void
    {
        Storage::fake('local');
        $supervisor = $this->actingAsLevel('supervisor');
        [$part, $active] = $this->partWithWi();

        $created = $this->post('/api/work-instructions', [
            'part_number_id' => $part->id,
            'code' => 'WI-1',
            'revision' => 'B',
            'title' => 'Nueva revisión',
            'archivo' => UploadedFile::fake()->create('rev-b.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('status', 'DRAFT');

        $record = WorkInstruction::findOrFail($created->json('id'));
        $this->assertStringStartsWith('wi/', $record->file_path);
        Storage::disk('local')->assertExists($record->file_path);

        $this->postJson("/api/work-instructions/{$record->id}/aprobar")
            ->assertOk()->assertJsonPath('status', 'ACTIVE')->assertJsonPath('approved_by_name', $supervisor->full_name);
        $this->assertSame('OBSOLETE', $active->fresh()->status);
        $this->assertSame($supervisor->id, $record->fresh()->approved_by);

        // Ya vigente: no se aprueba de nuevo ni se elimina; sí se puede marcar obsoleta
        $this->postJson("/api/work-instructions/{$record->id}/aprobar")->assertUnprocessable();
        $this->deleteJson("/api/work-instructions/{$record->id}")->assertUnprocessable();
        $this->postJson("/api/work-instructions/{$record->id}/obsoleta")->assertOk()->assertJsonPath('status', 'OBSOLETE');
    }

    public function test_work_instruction_rejects_non_pdf_and_duplicate_revisions_and_deletes_drafts(): void
    {
        Storage::fake('local');
        $this->actingAsLevel('supervisor');
        [$part, $active] = $this->partWithWi();
        $campos = ['part_number_id' => $part->id, 'code' => 'WI-9', 'revision' => 'A', 'title' => 'Prueba'];

        $this->post('/api/work-instructions', [...$campos, 'archivo' => UploadedFile::fake()->create('virus.exe', 10)], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('archivo');

        $this->post('/api/work-instructions', [...$campos, 'code' => $active->code, 'revision' => $active->revision, 'archivo' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('revision');

        $id = $this->post('/api/work-instructions', [...$campos, 'archivo' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertCreated()->json('id');
        $ruta = WorkInstruction::findOrFail($id)->file_path;

        $this->deleteJson("/api/work-instructions/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('work_instructions', ['id' => $id]);
        Storage::disk('local')->assertMissing($ruta);
    }

    public function test_work_instruction_pdf_is_served_with_authentication_and_missing_file_is_404_json(): void
    {
        Storage::fake('local');
        [, $wi] = $this->partWithWi();
        $this->getJson("/api/work-instructions/{$wi->id}/archivo")->assertUnauthorized();

        $this->actingAsLevel('supervisor');
        $this->getJson("/api/work-instructions/{$wi->id}/archivo")->assertNotFound()->assertJsonStructure(['message']);

        Storage::disk('local')->put($wi->file_path, '%PDF-1.4 demo');
        $this->get("/api/work-instructions/{$wi->id}/archivo")->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    // ---------------------------------------------------------------- usuarios, clientes y defectos (admin)

    public function test_admin_manages_users_with_safeguards(): void
    {
        $admin = $this->actingAsLevel('admin');
        $inspectorRole = Role::firstOrCreate(['name' => 'inspector'], ['description' => 'Inspector']);

        $created = $this->postJson('/api/users', [
            'role_id' => $inspectorRole->id, 'employee_number' => '5555', 'full_name' => 'Nuevo Inspector', 'password_hash' => 'LargaClave1',
        ])->assertCreated()->assertJsonMissingPath('password_hash');
        $this->assertTrue(Hash::check('LargaClave1', User::findOrFail($created->json('id'))->password_hash));

        $this->postJson('/api/users', [
            'role_id' => $inspectorRole->id, 'employee_number' => '5555', 'full_name' => 'Repetido', 'password_hash' => 'LargaClave1',
        ])->assertUnprocessable()->assertJsonValidationErrors('employee_number');

        $this->postJson('/api/users', [
            'role_id' => $inspectorRole->id, 'employee_number' => '5556', 'full_name' => 'Clave corta', 'password_hash' => 'corta',
        ])->assertUnprocessable()->assertJsonValidationErrors('password_hash');

        // No puede quitarse el acceso a sí mismo
        $this->patchJson("/api/users/{$admin->id}", [
            'role_id' => $inspectorRole->id, 'employee_number' => $admin->employee_number, 'full_name' => $admin->full_name, 'is_active' => true,
        ])->assertUnprocessable();
        $this->patchJson("/api/users/{$admin->id}", [
            'role_id' => $admin->role_id, 'employee_number' => $admin->employee_number, 'full_name' => $admin->full_name, 'is_active' => false,
        ])->assertUnprocessable();
    }

    public function test_deactivating_a_user_revokes_their_tokens(): void
    {
        $this->actingAsLevel('admin');
        $target = $this->makeUser('inspector', 'INS-9');
        $target->createToken('control-calidad');
        $this->assertSame(1, $target->tokens()->count());

        $this->patchJson("/api/users/{$target->id}", [
            'role_id' => $target->role_id, 'employee_number' => $target->employee_number, 'full_name' => $target->full_name, 'is_active' => false,
        ])->assertOk();

        $this->assertSame(0, $target->tokens()->count());
    }

    public function test_catalog_rules_for_clients_parts_and_defects(): void
    {
        $this->actingAsLevel('admin');

        $client = $this->postJson('/api/clients', ['name' => 'ACME', 'contact_email' => 'a@acme.com'])->assertCreated()->json();
        $this->postJson('/api/clients', ['name' => 'ACME'])->assertUnprocessable()->assertJsonValidationErrors('name');

        $this->postJson('/api/part-numbers', ['client_id' => $client['id'], 'part_number' => 'P-1'])->assertCreated();
        $this->postJson('/api/part-numbers', ['client_id' => $client['id'], 'part_number' => 'P-1'])->assertUnprocessable()->assertJsonValidationErrors('part_number');

        $this->postJson('/api/defect-catalog', ['code' => 'def-test-1', 'category' => 'COSMETIC', 'description' => 'Prueba'])
            ->assertCreated()->assertJsonPath('code', 'DEF-TEST-1');
        $this->postJson('/api/defect-catalog', ['code' => 'DEF-TEST-1', 'category' => 'COSMETIC', 'description' => 'Otra'])
            ->assertUnprocessable()->assertJsonValidationErrors('code');

        $this->deleteJson("/api/clients/{$client['id']}")->assertStatus(405);
    }

    // ---------------------------------------------------------------- Mi turno

    public function test_my_shift_counts_only_own_inspections_and_team_view_is_restricted(): void
    {
        $inspector = $this->makeUser('inspector', 'INS-1');
        $other = $this->makeUser('inspector', 'INS-2');
        $order = $this->order('SO-8001');
        $order->serialRecords()->create(['serial_number' => 'A1', 'status' => 'PASSED', 'inspected_by' => $inspector->id]);
        $order->serialRecords()->create(['serial_number' => 'A2', 'status' => 'FAILED', 'inspected_by' => $inspector->id]);
        $order->serialRecords()->create(['serial_number' => 'B1', 'status' => 'PASSED', 'inspected_by' => $other->id]);

        Sanctum::actingAs($inspector);
        $this->getJson('/api/turno?fecha='.today()->toDateString())->assertOk()
            ->assertJsonPath('kpis.inspeccionadas', 2)
            ->assertJsonPath('kpis.ok', 1)
            ->assertJsonPath('kpis.ng', 1)
            ->assertJsonPath('porOrden.0.so', 'SO-8001')
            ->assertJsonCount(2, 'ultimos');
        $this->getJson('/api/turno?alcance=equipo')->assertForbidden();

        Sanctum::actingAs($this->makeUser('supervisor', 'SUP-2'));
        $this->getJson('/api/turno?alcance=equipo&fecha='.today()->toDateString())->assertOk()
            ->assertJsonPath('kpis.inspeccionadas', 3)
            ->assertJsonCount(2, 'porInspector');
        $this->getJson('/api/turno?fecha=ayer')->assertUnprocessable();
    }

    // ---------------------------------------------------------------- utilidades

    private function makeUser(string $nivel, string $numero): User
    {
        $role = Role::firstOrCreate(['name' => $nivel], ['description' => ucfirst($nivel)]);

        return User::create([
            'role_id' => $role->id,
            'employee_number' => $numero,
            'full_name' => 'Usuario '.$numero,
            'password_hash' => Hash::make('secret-pass'),
            'is_active' => true,
        ]);
    }

    private function actingAsLevel(string $nivel): User
    {
        $user = $this->makeUser($nivel, strtoupper(substr($nivel, 0, 3)).'-0');
        Sanctum::actingAs($user);

        return $user;
    }

    /** @return array{0: PartNumber, 1: WorkInstruction} */
    private function partWithWi(): array
    {
        $client = Client::firstOrCreate(['name' => 'MBCC Client']);
        $part = PartNumber::firstOrCreate(['client_id' => $client->id, 'part_number' => 'MB-1'], ['description' => 'Pieza']);
        $wi = WorkInstruction::firstOrCreate(
            ['code' => 'WI-1', 'revision' => 'A'],
            ['part_number_id' => $part->id, 'title' => 'Inspección', 'file_path' => 'wi/wi-1.pdf', 'status' => 'ACTIVE'],
        );

        return [$part, $wi];
    }

    private function order(string $numero, string $estado = 'OPEN'): SortOrder
    {
        [$part, $wi] = $this->partWithWi();

        return SortOrder::create([
            'order_number' => $numero,
            'part_number_id' => $part->id,
            'work_instruction_id' => $wi->id,
            'target_quantity' => 100,
            'status' => $estado,
            'shift_hours' => '8:00 AM - 4:00 PM',
        ]);
    }
}
