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
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ControlCalidadApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_uses_the_existing_employee_account(): void
    {
        $user = $this->createUser();

        $this->postJson('/api/login', [
            'numero_empleado' => $user->employee_number,
            'password' => 'secret-pass',
            'turno' => '2',
        ])->assertOk()
            ->assertJsonPath('user.nombre', 'Inspectora de Calidad')
            ->assertJsonPath('user.turno', 2)
            ->assertJsonStructure(['token', 'user' => ['nombre', 'rol', 'turno']]);
    }

    public function test_orders_are_mapped_from_existing_production_orders(): void
    {
        $user = $this->createUser();
        $order = $this->createOrder();
        Sanctum::actingAs($user);

        $this->getJson('/api/ordenes')->assertOk()
            ->assertJsonPath('0.id', $order->id)
            ->assertJsonPath('0.cliente', 'MBCC Client')
            ->assertJsonPath('0.so', 'SO-9001')
            ->assertJsonPath('0.estado', 'Abierta');
    }

    public function test_inspection_records_a_serial_defect_and_prevents_duplicates(): void
    {
        $user = $this->createUser();
        $order = $this->createOrder();
        $defect = DefectCatalog::create([
            'code' => 'DEF-COVER-SCRATCH',
            'category' => 'COSMETIC',
            'description' => 'Rayón en carcasa',
            'is_active' => true,
        ]);
        Sanctum::actingAs($user);

        $this->postJson("/api/ordenes/{$order->id}/inspecciones", [
            'serie' => 'SN-9001-001',
            'resultado' => 'NG',
            'defecto' => $defect->description,
            'turno' => 2,
        ])->assertCreated()->assertJsonPath('resultado', 'NG');

        $this->assertDatabaseHas('serial_records', [
            'sort_order_id' => $order->id,
            'serial_number' => 'SN-9001-001',
            'status' => 'FAILED',
            'shift' => 2,
        ]);
        $this->assertDatabaseHas('serial_defect_logs', ['defect_id' => $defect->id]);

        $this->postJson("/api/ordenes/{$order->id}/inspecciones", [
            'serie' => 'SN-9001-001',
            'resultado' => 'OK',
        ])->assertUnprocessable();
    }

    public function test_report_summary_uses_records_and_defect_logs(): void
    {
        $user = $this->createUser();
        $order = $this->createOrder();
        $defect = DefectCatalog::create([
            'code' => 'DEF-MISSING-SCREW',
            'category' => 'MECHANICAL',
            'description' => 'Tornillo faltante',
            'is_active' => true,
        ]);
        Sanctum::actingAs($user);
        $this->postJson("/api/ordenes/{$order->id}/inspecciones", ['serie' => 'SN-9001-002', 'resultado' => 'OK', 'turno' => 1])->assertCreated();
        $this->postJson("/api/ordenes/{$order->id}/inspecciones", ['serie' => 'SN-9001-003', 'resultado' => 'NG', 'defecto' => $defect->description, 'turno' => 1])->assertCreated();

        $this->getJson('/api/reportes/resumen?desde='.today()->toDateString().'&hasta='.today()->toDateString().'&turno=1')
            ->assertOk()
            ->assertJsonPath('kpis.eficiencia.valor', 50)
            ->assertJsonPath('pareto.0.defecto', 'Tornillo faltante')
            ->assertJsonPath('pareto.0.cantidad', 1);
    }

    public function test_csv_report_is_an_authenticated_download(): void
    {
        $user = $this->createUser();
        Sanctum::actingAs($user);

        $this->get('/api/reportes/datos.csv?desde='.today()->toDateString().'&hasta='.today()->toDateString())
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition', 'attachment; filename=inspecciones.csv');
    }

    public function test_logout_revokes_the_current_sanctum_token(): void
    {
        $user = $this->createUser();
        $token = $this->postJson('/api/login', [
            'numero_empleado' => $user->employee_number,
            'password' => 'secret-pass',
        ])->assertOk()->json('token');

        $this->withToken($token)->postJson('/api/logout')->assertNoContent();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => explode('|', $token, 2)[0]]);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/ordenes')->assertUnauthorized();
    }

    private function createUser(): User
    {
        $role = Role::create(['name' => 'inspector', 'description' => 'Inspector']);

        return User::create([
            'role_id' => $role->id,
            'employee_number' => 'EMP-TEST',
            'full_name' => 'Inspectora de Calidad',
            'password_hash' => Hash::make('secret-pass'),
            'is_active' => true,
        ]);
    }

    private function createOrder(): SortOrder
    {
        $client = Client::create(['name' => 'MBCC Client']);
        $partNumber = PartNumber::create([
            'client_id' => $client->id,
            'part_number' => 'MB-9001',
            'description' => 'Assembly housing',
        ]);
        $workInstruction = WorkInstruction::create([
            'part_number_id' => $partNumber->id,
            'code' => 'WI-9001',
            'revision' => 'A',
            'title' => 'Housing inspection',
            'file_path' => 'instructions/wi-9001.pdf',
            'status' => 'ACTIVE',
        ]);

        return SortOrder::create([
            'order_number' => 'SO-9001',
            'part_number_id' => $partNumber->id,
            'work_instruction_id' => $workInstruction->id,
            'target_quantity' => 100,
            'status' => 'OPEN',
            'shift_hours' => '8:00 AM - 4:00 PM',
        ]);
    }
}