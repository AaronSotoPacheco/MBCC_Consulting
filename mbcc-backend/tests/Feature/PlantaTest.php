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

/** Punto 5: cierre automático al llegar a la meta, capturas sin conexión (reenvío seguro) y alertas de rechazos. */
class PlantaTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $nivel): User
    {
        $role = Role::firstOrCreate(['name' => $nivel], ['description' => ucfirst($nivel)]);

        return User::create([
            'role_id' => $role->id, 'employee_number' => strtoupper($nivel).'-1', 'full_name' => 'Usuario '.$nivel,
            'password_hash' => Hash::make('secret-pass'), 'is_active' => true,
        ]);
    }

    private ?int $inspector = null;

    /** Inspector que firma las piezas creadas directamente (la columna es obligatoria). */
    private function inspId(): int
    {
        return $this->inspector ??= $this->usuario('inspector')->id;
    }

    private function orden(int $meta, string $numero = 'SO-50', string $estado = 'OPEN'): SortOrder
    {
        $client = Client::firstOrCreate(['name' => 'Cliente']);
        $part = PartNumber::firstOrCreate(['client_id' => $client->id, 'part_number' => 'P-5'], ['description' => 'Pieza']);
        $wi = WorkInstruction::firstOrCreate(
            ['code' => 'WI-5', 'revision' => 'A'],
            ['part_number_id' => $part->id, 'title' => 'Inspección', 'file_path' => 'wi/x.pdf', 'status' => 'ACTIVE'],
        );

        return SortOrder::create([
            'order_number' => $numero, 'part_number_id' => $part->id, 'work_instruction_id' => $wi->id,
            'target_quantity' => $meta, 'status' => $estado, 'shift_hours' => '8:00 AM - 4:00 PM',
        ]);
    }

    private function defecto(): DefectCatalog
    {
        return DefectCatalog::firstOrCreate(['code' => 'DEF-1'], ['category' => 'MECHANICAL', 'description' => 'Rayón', 'is_active' => true]);
    }

    // ---------------------------------------------------------------- cierre automático

    public function test_order_closes_itself_when_the_target_is_reached(): void
    {
        $orden = $this->orden(2);
        Sanctum::actingAs($this->usuario('inspector'));

        $this->postJson("/api/ordenes/{$orden->id}/inspecciones", ['serie' => 'A-1', 'resultado' => 'OK'])
            ->assertCreated()->assertJsonPath('orden_cerrada', false)->assertJsonPath('inspeccionadas', 1)->assertJsonPath('meta', 2);
        $this->assertSame('OPEN', $orden->fresh()->status);

        $this->postJson("/api/ordenes/{$orden->id}/inspecciones", ['serie' => 'A-2', 'resultado' => 'OK'])
            ->assertCreated()->assertJsonPath('orden_cerrada', true)->assertJsonPath('inspeccionadas', 2);
        $this->assertSame('CLOSED', $orden->fresh()->status);

        $log = \App\Models\AuditLog::where('action', 'orden.cierre_auto')->firstOrFail();
        $this->assertSame($orden->id, $log->entity_id);
        $this->assertTrue($log->detail['automatico']);

        $this->postJson("/api/ordenes/{$orden->id}/inspecciones", ['serie' => 'A-3', 'resultado' => 'OK'])->assertUnprocessable();
        $this->assertSame(2, $orden->serialRecords()->count());
    }

    public function test_rejected_pieces_also_count_toward_the_target(): void
    {
        $orden = $this->orden(2);
        $d = $this->defecto();
        Sanctum::actingAs($this->usuario('inspector'));

        $this->postJson("/api/ordenes/{$orden->id}/inspecciones", ['serie' => 'B-1', 'resultado' => 'NG', 'defecto' => $d->description])->assertCreated();
        $this->postJson("/api/ordenes/{$orden->id}/inspecciones", ['serie' => 'B-2', 'resultado' => 'OK'])->assertCreated()->assertJsonPath('orden_cerrada', true);
    }

    // ---------------------------------------------------------------- captura sin conexión

    public function test_resending_the_same_capture_does_not_duplicate_it(): void
    {
        $orden = $this->orden(50);
        Sanctum::actingAs($this->usuario('inspector'));
        $body = ['serie' => 'C-1', 'resultado' => 'OK', 'cliente_uuid' => 'aaaa-bbbb-1111'];

        $this->postJson("/api/ordenes/{$orden->id}/inspecciones", $body)->assertCreated()->assertJsonMissingPath('repetido');
        $this->postJson("/api/ordenes/{$orden->id}/inspecciones", $body)
            ->assertOk()->assertJsonPath('repetido', true)->assertJsonPath('serie', 'C-1')->assertJsonPath('resultado', 'OK');

        $this->assertSame(1, $orden->serialRecords()->count());
        // Otra pieza con la misma serie y distinto id sí es un duplicado real
        $this->postJson("/api/ordenes/{$orden->id}/inspecciones", ['serie' => 'C-1', 'resultado' => 'OK', 'cliente_uuid' => 'otro-id'])->assertUnprocessable();
    }

    public function test_resending_works_even_if_the_order_was_closed_meanwhile(): void
    {
        $orden = $this->orden(1);
        Sanctum::actingAs($this->usuario('inspector'));
        $body = ['serie' => 'D-1', 'resultado' => 'OK', 'cliente_uuid' => 'uuid-d1'];

        $this->postJson("/api/ordenes/{$orden->id}/inspecciones", $body)->assertCreated()->assertJsonPath('orden_cerrada', true);
        $this->postJson("/api/ordenes/{$orden->id}/inspecciones", $body)->assertOk()->assertJsonPath('repetido', true)->assertJsonPath('orden_cerrada', false);
    }

    public function test_capture_time_from_the_device_is_kept_only_when_believable(): void
    {
        $orden = $this->orden(50);
        Sanctum::actingAs($this->usuario('inspector'));
        $hace2h = now()->subHours(2);

        $this->postJson("/api/ordenes/{$orden->id}/inspecciones", ['serie' => 'E-1', 'resultado' => 'OK', 'capturado_en' => $hace2h->toIso8601String()])->assertCreated();
        $this->postJson("/api/ordenes/{$orden->id}/inspecciones", ['serie' => 'E-2', 'resultado' => 'OK', 'capturado_en' => now()->addDays(3)->toIso8601String()])->assertCreated();
        $this->postJson("/api/ordenes/{$orden->id}/inspecciones", ['serie' => 'E-3', 'resultado' => 'OK', 'capturado_en' => now()->subDays(30)->toIso8601String()])->assertCreated();

        $r = fn (string $s) => $orden->serialRecords()->where('serial_number', $s)->firstOrFail()->created_at;
        $this->assertEqualsWithDelta($hace2h->timestamp, $r('E-1')->timestamp, 2);
        $this->assertEqualsWithDelta(now()->timestamp, $r('E-2')->timestamp, 5); // futura: se ignora
        $this->assertEqualsWithDelta(now()->timestamp, $r('E-3')->timestamp, 5); // demasiado vieja: se ignora
    }

    // ---------------------------------------------------------------- alertas de NG

    private function piezas(SortOrder $orden, int $ok, int $ng, ?DefectCatalog $d = null): void
    {
        $i = 0;
        for ($k = 0; $k < $ok; $k++) {
            $orden->serialRecords()->create(['serial_number' => 'OK-'.(++$i), 'status' => 'PASSED', 'inspected_by' => $this->inspId()]);
        }
        for ($k = 0; $k < $ng; $k++) {
            $r = $orden->serialRecords()->create(['serial_number' => 'NG-'.(++$i), 'status' => 'FAILED', 'inspected_by' => $this->inspId()]);
            if ($d) {
                $r->defectLogs()->create(['defect_id' => $d->id]);
            }
        }
    }

    public function test_alerts_list_orders_with_high_recent_rejection_rate(): void
    {
        $d = $this->defecto();
        $alta = $this->orden(100, 'SO-ALTA');
        $this->piezas($alta, 18, 2, $d);                      // 10 % en 20 piezas → crítica (≥ 2 × 5 %)
        $media = $this->orden(100, 'SO-MEDIA');
        $this->piezas($media, 18, 1, $d);                     // 1 de 19 = 5.3 % → alta
        $sana = $this->orden(100, 'SO-SANA');
        $this->piezas($sana, 40, 0);                          // 0 %
        $poca = $this->orden(100, 'SO-POCA');
        $this->piezas($poca, 3, 3);                           // 50 % pero solo 6 piezas: aún no se evalúa
        $cerrada = $this->orden(100, 'SO-CERRADA', 'CLOSED');
        $this->piezas($cerrada, 5, 15);                       // cerrada: no alerta

        Sanctum::actingAs($this->usuario('supervisor'));
        $r = $this->getJson('/api/alertas')->assertOk()->assertJsonCount(2, 'alertas');
        $this->assertEquals(5, $r->json('umbral'));
        $this->assertEquals(10, $r->json('alertas.0.tasa_ventana'));

        $r->assertJsonPath('alertas.0.so', 'SO-ALTA')
            ->assertJsonPath('alertas.0.nivel', 'critica')
            ->assertJsonPath('alertas.0.defecto_principal', 'Rayón')
            ->assertJsonPath('alertas.1.so', 'SO-MEDIA')
            ->assertJsonPath('alertas.1.nivel', 'alta');
    }

    public function test_alert_uses_the_latest_pieces_not_the_whole_history(): void
    {
        $orden = $this->orden(500, 'SO-RECIENTE');
        // 30 piezas viejas sanas y luego 20 recientes con 4 rechazos
        for ($i = 0; $i < 30; $i++) {
            $orden->serialRecords()->create(['serial_number' => 'V-'.$i, 'status' => 'PASSED', 'inspected_by' => $this->inspId()]);
        }
        for ($i = 0; $i < 16; $i++) {
            $orden->serialRecords()->create(['serial_number' => 'R-'.$i, 'status' => 'PASSED', 'inspected_by' => $this->inspId()]);
        }
        for ($i = 0; $i < 4; $i++) {
            $orden->serialRecords()->create(['serial_number' => 'RNG-'.$i, 'status' => 'FAILED', 'inspected_by' => $this->inspId()]);
        }

        Sanctum::actingAs($this->usuario('admin'));
        $r = $this->getJson('/api/alertas')->assertOk()
            ->assertJsonPath('alertas.0.so', 'SO-RECIENTE')
            ->assertJsonPath('alertas.0.inspeccionadas', 50);
        $this->assertEquals(20, $r->json('alertas.0.tasa_ventana'));
        $this->assertEquals(8, $r->json('alertas.0.tasa_total'));
    }

    public function test_inspectors_cannot_read_alerts(): void
    {
        $this->getJson('/api/alertas')->assertUnauthorized();
        Sanctum::actingAs($this->usuario('inspector'));
        $this->getJson('/api/alertas')->assertForbidden();
    }
}
