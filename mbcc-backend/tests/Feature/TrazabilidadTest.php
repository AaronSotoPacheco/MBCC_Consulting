<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DefectCatalog;
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

class TrazabilidadTest extends TestCase
{
    use RefreshDatabase;

    public function test_requiere_autenticacion(): void
    {
        $this->getJson('/api/trazabilidad?serie=ABC')->assertUnauthorized();
        $this->postJson('/api/series/1/retrabajo', [])->assertUnauthorized();
    }

    public function test_inspeccion_guarda_evidencia_y_sugerencia_de_ia_y_se_consulta_por_serie(): void
    {
        Storage::fake('local');
        $orden = $this->orden();
        $defecto = DefectCatalog::create(['code' => 'RAYON', 'category' => 'COSMETIC', 'description' => 'Rayón', 'is_active' => true]);
        $this->actingAsRol('inspector');

        $this->post("/api/ordenes/{$orden->id}/inspecciones", [
            'serie' => 'SN-0001', 'resultado' => 'NG', 'defecto' => $defecto->description,
            'evidencia' => UploadedFile::fake()->image('ng.jpg', 200, 200),
            'ia_resultado' => 'OK', 'ia_defecto' => '', 'ia_confianza' => 'alta',
        ], ['Accept' => 'application/json'])->assertSuccessful();

        $r = $this->getJson('/api/trazabilidad?serie=SN-0001')->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.estado', 'NG')
            ->assertJsonPath('0.orden', 'SO-T1')
            ->assertJsonPath('0.defectos.0.evidencia', true)
            ->assertJsonPath('0.ia.resultado', 'OK')
            ->assertJsonPath('0.ia.coincide', false)   // la IA dijo OK y el inspector rechazó
            ->assertJsonPath('0.puede_retrabajo', true)
            ->assertJsonMissingPath('0.inspector');     // el inspector no ve nombres

        $logId = $r->json('0.defectos.0.id');
        $this->get("/api/evidencias/{$logId}")->assertOk();
        $this->assertCount(1, Storage::disk('local')->files('evidencias'));
    }

    public function test_evidencia_solo_se_guarda_en_rechazos(): void
    {
        Storage::fake('local');
        $orden = $this->orden();
        $this->actingAsRol('inspector');

        $this->post("/api/ordenes/{$orden->id}/inspecciones", [
            'serie' => 'SN-OK', 'resultado' => 'OK', 'evidencia' => UploadedFile::fake()->image('x.jpg'),
        ], ['Accept' => 'application/json'])->assertSuccessful();

        $this->assertSame([], Storage::disk('local')->files('evidencias'));
    }

    public function test_evidencia_rechaza_archivos_que_no_son_imagen(): void
    {
        Storage::fake('local');
        $orden = $this->orden();
        $this->actingAsRol('inspector');

        $this->post("/api/ordenes/{$orden->id}/inspecciones", [
            'serie' => 'SN-X', 'resultado' => 'OK', 'evidencia' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_busqueda_valida_y_supervisor_ve_nombres(): void
    {
        $orden = $this->orden();
        $insp = $this->makeUser('inspector', 'INS-9');
        $orden->serialRecords()->create(['serial_number' => 'ABC-123', 'status' => 'PASSED', 'inspected_by' => $insp->id]);

        $this->actingAsRol('supervisor');
        $this->getJson('/api/trazabilidad')->assertUnprocessable();
        $this->getJson('/api/trazabilidad?serie=ABC')->assertOk()
            ->assertJsonPath('0.serie', 'ABC-123')
            ->assertJsonPath('0.inspector', 'Usuario INS-9');
        $this->getJson('/api/trazabilidad?serie=NOEXISTE')->assertOk()->assertJsonCount(0);
    }

    public function test_retrabajo_aprueba_o_desecha_solo_piezas_rechazadas(): void
    {
        $orden = $this->orden();
        $insp = $this->makeUser('inspector', 'INS-9');
        $ng = $orden->serialRecords()->create(['serial_number' => 'NG-1', 'status' => 'FAILED', 'inspected_by' => $insp->id]);
        $ok = $orden->serialRecords()->create(['serial_number' => 'OK-1', 'status' => 'PASSED', 'inspected_by' => $insp->id]);
        $this->actingAsRol('supervisor');

        $this->postJson("/api/series/{$ok->id}/retrabajo", ['accion' => 'Pulido', 'resultado' => 'PASSED'])->assertUnprocessable();
        $this->postJson("/api/series/{$ng->id}/retrabajo", ['accion' => 'x', 'resultado' => 'PASSED'])->assertUnprocessable();
        $this->postJson("/api/series/{$ng->id}/retrabajo", ['accion' => 'Pulido de rayón', 'resultado' => 'BIEN'])->assertUnprocessable();

        $this->postJson("/api/series/{$ng->id}/retrabajo", ['accion' => 'Pulido de rayón', 'resultado' => 'PASSED'])
            ->assertOk()
            ->assertJsonPath('estado', 'OK')
            ->assertJsonPath('retrabajos.0.accion', 'Pulido de rayón')
            ->assertJsonPath('puede_retrabajo', false);

        $this->assertDatabaseHas('serial_records', ['id' => $ng->id, 'status' => 'PASSED']);
        $this->assertDatabaseCount('rework_logs', 1);
    }

    public function test_retrabajo_scrap_y_orden_cerrada(): void
    {
        $orden = $this->orden();
        $insp = $this->makeUser('inspector', 'INS-9');
        $a = $orden->serialRecords()->create(['serial_number' => 'A', 'status' => 'FAILED', 'inspected_by' => $insp->id]);
        $b = $orden->serialRecords()->create(['serial_number' => 'B', 'status' => 'FAILED', 'inspected_by' => $insp->id]);
        $this->actingAsRol('inspector');

        $this->postJson("/api/series/{$a->id}/retrabajo", ['accion' => 'Sin reparación', 'resultado' => 'SCRAP'])
            ->assertOk()->assertJsonPath('estado', 'SCRAP');

        $orden->update(['status' => 'CLOSED']);
        $this->postJson("/api/series/{$b->id}/retrabajo", ['accion' => 'Pulido', 'resultado' => 'PASSED'])->assertUnprocessable();
        $this->assertDatabaseHas('serial_records', ['id' => $b->id, 'status' => 'FAILED']);
    }

    public function test_lista_de_retrabajos_pendientes(): void
    {
        $orden = $this->orden();
        $cerrada = SortOrder::create(['order_number' => 'SO-C', 'part_number_id' => $orden->part_number_id, 'work_instruction_id' => $orden->work_instruction_id, 'target_quantity' => 10, 'status' => 'CLOSED', 'shift_hours' => '8:00 AM - 4:00 PM']);
        $insp = $this->makeUser('inspector', 'INS-9');
        $pend = $orden->serialRecords()->create(['serial_number' => 'P-1', 'status' => 'FAILED', 'inspected_by' => $insp->id]);
        $orden->serialRecords()->create(['serial_number' => 'P-OK', 'status' => 'PASSED', 'inspected_by' => $insp->id]);
        $orden->serialRecords()->create(['serial_number' => 'P-SCRAP', 'status' => 'SCRAP', 'inspected_by' => $insp->id]);
        $cerrada->serialRecords()->create(['serial_number' => 'P-CERRADA', 'status' => 'FAILED', 'inspected_by' => $insp->id]);
        $this->actingAsRol('inspector');

        $this->getJson('/api/trazabilidad/pendientes')->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonCount(1, 'piezas')
            ->assertJsonPath('piezas.0.serie', 'P-1');

        $this->postJson("/api/series/{$pend->id}/retrabajo", ['accion' => 'Pulido', 'resultado' => 'PASSED'])->assertOk();
        $this->getJson('/api/trazabilidad/pendientes')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_ultimos_escaneos_de_la_orden_limita_a_15_y_no_depende_del_dia(): void
    {
        $orden = $this->orden();
        $insp = $this->makeUser('inspector', 'INS-9');
        for ($i = 1; $i <= 20; $i++) {
            $r = $orden->serialRecords()->create(['serial_number' => sprintf('E-%02d', $i), 'status' => 'PASSED', 'inspected_by' => $insp->id]);
            $r->forceFill(['created_at' => now()->subDays(3)->addMinutes($i)])->save(); // todos de días pasados
        }
        $this->actingAsRol('inspector');

        $this->getJson("/api/ordenes/{$orden->id}/inspecciones")->assertOk()->assertJsonCount(0); // el modo normal sigue siendo "hoy"
        $this->getJson("/api/ordenes/{$orden->id}/inspecciones?ultimos=15")->assertOk()
            ->assertJsonCount(15)
            ->assertJsonPath('0.serie', 'E-20')
            ->assertJsonPath('14.serie', 'E-06')
            ->assertJsonMissingPath('0.inspector');
        $this->getJson("/api/ordenes/{$orden->id}/inspecciones?ultimos=99")->assertUnprocessable();
    }

    private function makeUser(string $nivel, string $numero): User
    {
        $role = Role::firstOrCreate(['name' => $nivel], ['description' => ucfirst($nivel)]);

        return User::create([
            'role_id' => $role->id, 'employee_number' => $numero, 'full_name' => 'Usuario '.$numero,
            'password_hash' => Hash::make('secret-pass'), 'is_active' => true,
        ]);
    }

    private function actingAsRol(string $nivel): User
    {
        $u = $this->makeUser($nivel, strtoupper(substr($nivel, 0, 3)).'-0');
        Sanctum::actingAs($u);

        return $u;
    }

    private function orden(): SortOrder
    {
        $client = Client::firstOrCreate(['name' => 'MBCC Client']);
        $part = PartNumber::firstOrCreate(['client_id' => $client->id, 'part_number' => 'MB-1'], ['description' => 'Pieza']);
        $wi = WorkInstruction::firstOrCreate(
            ['code' => 'WI-1', 'revision' => 'A'],
            ['part_number_id' => $part->id, 'title' => 'Inspección', 'file_path' => 'wi/wi-1.pdf', 'status' => 'ACTIVE'],
        );

        return SortOrder::create([
            'order_number' => 'SO-T1', 'part_number_id' => $part->id, 'work_instruction_id' => $wi->id,
            'target_quantity' => 100, 'status' => 'OPEN', 'shift_hours' => '8:00 AM - 4:00 PM',
        ]);
    }
}
