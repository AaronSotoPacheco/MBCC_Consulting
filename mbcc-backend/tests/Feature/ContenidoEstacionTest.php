<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\PartNumber;
use App\Models\Role;
use App\Models\SortOrder;
use App\Models\User;
use App\Models\WorkInstruction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContenidoEstacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_edita_puntos_y_especificaciones_y_el_inspector_los_ve_en_la_orden(): void
    {
        [$orden, $wi] = $this->ordenConWi('ACTIVE');

        $this->actingAsRol('supervisor');
        $this->patchJson("/api/work-instructions/{$wi->id}/contenido", [
            'key_points' => "Verificar alineación de tornillos.\n\n  Revisar rayones  \n",
            'specifications' => 'Torque 1.2 – 1.5 Nm',
        ])->assertOk()->assertJsonPath('specifications', 'Torque 1.2 – 1.5 Nm');

        $this->actingAsRol('inspector');
        $this->getJson('/api/ordenes')->assertOk()
            ->assertJsonPath('0.wi.id', $wi->id)
            ->assertJsonPath('0.wi.puntos', ['Verificar alineación de tornillos.', 'Revisar rayones'])
            ->assertJsonPath('0.wi.especificaciones', 'Torque 1.2 – 1.5 Nm');
    }

    public function test_contenido_vacio_se_guarda_como_nulo_y_obsoleta_no_se_edita(): void
    {
        [$orden, $wi] = $this->ordenConWi('ACTIVE');
        $this->actingAsRol('admin');

        $this->patchJson("/api/work-instructions/{$wi->id}/contenido", ['key_points' => '   ', 'specifications' => ''])->assertOk();
        $this->assertDatabaseHas('work_instructions', ['id' => $wi->id, 'key_points' => null, 'specifications' => null]);
        $this->getJson('/api/ordenes')->assertJsonPath('0.wi.puntos', []);

        $this->patchJson("/api/work-instructions/{$wi->id}/contenido", ['key_points' => str_repeat('a', 2001)])->assertUnprocessable();

        $wi->update(['status' => 'OBSOLETE']);
        $this->patchJson("/api/work-instructions/{$wi->id}/contenido", ['key_points' => 'x'])->assertUnprocessable();
    }

    public function test_inspector_no_edita_contenido(): void
    {
        [$orden, $wi] = $this->ordenConWi('ACTIVE');
        $this->actingAsRol('inspector');
        $this->patchJson("/api/work-instructions/{$wi->id}/contenido", ['key_points' => 'x'])->assertForbidden();
    }

    public function test_inspector_abre_el_pdf_de_su_orden(): void
    {
        Storage::fake('local');
        [$orden, $wi] = $this->ordenConWi('ACTIVE');

        $this->getJson("/api/ordenes/{$orden->id}/instruccion/archivo")->assertUnauthorized();

        $this->actingAsRol('inspector');
        $this->getJson("/api/ordenes/{$orden->id}/instruccion/archivo")->assertNotFound(); // el archivo no existe aún

        Storage::disk('local')->put($wi->file_path, '%PDF-1.4 prueba');
        $this->get("/api/ordenes/{$orden->id}/instruccion/archivo")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->getJson('/api/ordenes/99999/instruccion/archivo')->assertNotFound();
    }

    /** @return array{0: SortOrder, 1: WorkInstruction} */
    private function ordenConWi(string $estado): array
    {
        $client = Client::firstOrCreate(['name' => 'MBCC Client']);
        $part = PartNumber::firstOrCreate(['client_id' => $client->id, 'part_number' => 'MB-1'], ['description' => 'Pieza']);
        $wi = WorkInstruction::create(['part_number_id' => $part->id, 'code' => 'WI-1', 'revision' => 'A', 'title' => 'Inspección', 'file_path' => 'wi/wi-1.pdf', 'status' => $estado]);
        $orden = SortOrder::create([
            'order_number' => 'SO-C1', 'part_number_id' => $part->id, 'work_instruction_id' => $wi->id,
            'target_quantity' => 10, 'status' => 'OPEN', 'shift_hours' => '8:00 AM - 4:00 PM',
        ]);

        return [$orden, $wi];
    }

    private function actingAsRol(string $nivel): User
    {
        $role = Role::firstOrCreate(['name' => $nivel], ['description' => ucfirst($nivel)]);
        $u = User::firstOrCreate(['employee_number' => strtoupper(substr($nivel, 0, 3)).'-0'], [
            'role_id' => $role->id, 'full_name' => 'Usuario '.$nivel, 'password_hash' => Hash::make('secret-pass'), 'is_active' => true,
        ]);
        Sanctum::actingAs($u);

        return $u;
    }
}
