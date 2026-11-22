<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Check-in de asistencia: registro propio, retardos, turno nocturno, vista del equipo, corrección manual y CSV. */
class AsistenciaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['calidad.asistencia.turnos' => [['inicio' => '08:00', 'fin' => '16:00']], 'calidad.asistencia.tolerancia_min' => 10, 'calidad.asistencia.anticipo_min' => 60,
            'calidad.asistencia.dias' => [1, 2, 3, 4, 5, 6, 7], 'calidad.asistencia.horas_max_turno' => 18]);
        $this->travelTo(now()->setTime(7, 55));
    }

    private function usuario(string $nivel, ?string $numero = null, string $nombre = 'Persona', bool $activo = true): User
    {
        $role = Role::firstOrCreate(['name' => $nivel], ['description' => ucfirst($nivel)]);

        return User::create([
            'role_id' => $role->id,
            'employee_number' => $numero ?? strtoupper($nivel).'-1',
            'full_name' => $nombre,
            'password_hash' => Hash::make('clave-actual-1'),
            'is_active' => $activo,
        ]);
    }

    public function test_entrada_y_salida_del_dia(): void
    {
        $u = $this->usuario('inspector');
        Sanctum::actingAs($u);

        $this->getJson('/api/asistencia/hoy')->assertOk()->assertJsonPath('estado', 'sin_entrada');

        $this->postJson('/api/asistencia/entrada')->assertCreated()->assertJsonPath('tarde_min', 0)->assertJsonPath('origen', 'self');
        $this->getJson('/api/asistencia/hoy')->assertJsonPath('estado', 'dentro');

        // no se puede entrar dos veces
        $this->postJson('/api/asistencia/entrada')->assertStatus(422);

        $this->travelTo(now()->setTime(16, 0));
        $this->postJson('/api/asistencia/salida')->assertOk()->assertJsonPath('salida_hora', '16:00');
        $this->getJson('/api/asistencia/hoy')->assertJsonPath('estado', 'salio');

        // ni salir sin entrada abierta, ni volver a entrar el mismo día
        $this->postJson('/api/asistencia/salida')->assertStatus(422);
        $this->postJson('/api/asistencia/entrada')->assertStatus(422);
        $this->assertSame(1, Attendance::count());
    }

    public function test_retardo_se_cuenta_solo_fuera_de_la_tolerancia(): void
    {
        $this->travelTo(now()->setTime(8, 10));
        Sanctum::actingAs($this->usuario('inspector', 'A-1'));
        $this->postJson('/api/asistencia/entrada')->assertCreated()->assertJsonPath('tarde_min', 0);

        $this->travelTo(now()->setTime(8, 25));
        Sanctum::actingAs($this->usuario('inspector', 'A-2'));
        $this->postJson('/api/asistencia/entrada')->assertCreated()->assertJsonPath('tarde_min', 25);
    }

    public function test_turno_nocturno_cruza_la_medianoche(): void
    {
        config(['calidad.asistencia.turnos' => [['inicio' => '22:00', 'fin' => '06:00']]]);
        $this->travelTo(now()->setTime(22, 0));
        $u = $this->usuario('inspector');
        Sanctum::actingAs($u);
        $dia = today()->toDateString();

        $this->postJson('/api/asistencia/entrada')->assertCreated();
        $this->travelTo(now()->addDay()->setTime(6, 0));

        $this->getJson('/api/asistencia/hoy')->assertJsonPath('estado', 'dentro')->assertJsonPath('registro.fecha', $dia);
        $this->postJson('/api/asistencia/salida')->assertOk()->assertJsonPath('fecha', $dia)->assertJsonPath('horas', 8);
    }

    public function test_entrada_olvidada_de_hace_mas_de_18_horas_no_bloquea(): void
    {
        $u = $this->usuario('inspector');
        Attendance::create([
            'user_id' => $u->id, 'work_date' => today()->subDay()->toDateString(),
            'check_in' => now()->subDay()->setTime(8, 0), 'late_minutes' => 0, 'source' => 'self',
        ]);
        Sanctum::actingAs($u);

        $this->postJson('/api/asistencia/salida')->assertStatus(422); // ya no cuenta como abierta
        $this->postJson('/api/asistencia/entrada')->assertCreated();
    }

    public function test_inspector_no_ve_el_equipo_ni_corrige(): void
    {
        $otro = $this->usuario('supervisor', 'S-1');
        Sanctum::actingAs($this->usuario('inspector'));

        $this->getJson('/api/asistencia')->assertForbidden();
        $this->getJson('/api/asistencia/historial')->assertForbidden();
        $this->get('/api/asistencia/datos.csv')->assertForbidden();
        $this->postJson('/api/asistencia/manual', ['usuario_id' => $otro->id, 'fecha' => today()->toDateString(), 'entrada' => '08:00', 'nota' => 'Olvido'])->assertForbidden();
    }

    public function test_recientes_solo_incluye_los_propios(): void
    {
        $yo = $this->usuario('inspector', 'A-1');
        $otro = $this->usuario('inspector', 'A-2');
        foreach ([$yo, $otro] as $u) {
            Attendance::create(['user_id' => $u->id, 'work_date' => today()->subDay()->toDateString(), 'check_in' => now()->subDay(), 'check_out' => now()->subDay()->addHours(8), 'source' => 'self']);
        }
        Sanctum::actingAs($yo);

        $this->getJson('/api/asistencia/hoy')->assertJsonCount(1, 'recientes');
    }

    public function test_el_dia_lista_presentes_salieron_y_ausentes(): void
    {
        $sup = $this->usuario('supervisor', 'S-1', 'Sara Supervisora');
        $ana = $this->usuario('inspector', 'I-1', 'Ana');
        $beto = $this->usuario('inspector', 'I-2', 'Beto');
        $this->usuario('inspector', 'I-3', 'Carla');
        $this->usuario('inspector', 'I-4', 'Baja', activo: false);

        Attendance::create(['user_id' => $ana->id, 'work_date' => today()->toDateString(), 'check_in' => now()->setTime(7, 0), 'source' => 'self']);
        Attendance::create(['user_id' => $beto->id, 'work_date' => today()->toDateString(), 'check_in' => now()->setTime(6, 0), 'check_out' => now()->setTime(7, 30), 'late_minutes' => 15, 'source' => 'self']);

        Sanctum::actingAs($sup);
        $r = $this->getJson('/api/asistencia')->assertOk();

        $r->assertJsonPath('resumen.total', 4) // sin el usuario inactivo
            ->assertJsonPath('resumen.presentes', 1)
            ->assertJsonPath('resumen.salieron', 1)
            ->assertJsonPath('resumen.ausentes', 2)
            ->assertJsonPath('resumen.retardos', 1);
        $this->assertSame('dentro', $r->json('filas.0.estado')); // presentes primero
        $this->assertNotContains('Baja', array_column($r->json('filas'), 'nombre'));
    }

    public function test_el_dia_incluye_la_entrada_nocturna_de_ayer_todavia_abierta(): void
    {
        $sup = $this->usuario('supervisor', 'S-1');
        $noche = $this->usuario('inspector', 'I-1', 'Nocturno');
        Attendance::create(['user_id' => $noche->id, 'work_date' => today()->subDay()->toDateString(), 'check_in' => now()->subHours(6), 'source' => 'self']);

        Sanctum::actingAs($sup);
        $fila = collect($this->getJson('/api/asistencia')->json('filas'))->firstWhere('usuario_id', $noche->id);
        $this->assertSame('dentro', $fila['estado']);
    }

    public function test_correccion_manual_crea_actualiza_y_deja_bitacora(): void
    {
        $sup = $this->usuario('supervisor', 'S-1');
        $ana = $this->usuario('inspector', 'I-1', 'Ana');
        Sanctum::actingAs($sup);
        $ayer = today()->subDay()->toDateString();

        $this->postJson('/api/asistencia/manual', ['usuario_id' => $ana->id, 'fecha' => $ayer, 'entrada' => '08:00', 'salida' => '16:30', 'nota' => 'Olvidó checar'])
            ->assertCreated()->assertJsonPath('origen', 'manual')->assertJsonPath('horas', 8.5);

        $this->postJson('/api/asistencia/manual', ['usuario_id' => $ana->id, 'fecha' => $ayer, 'entrada' => '08:30', 'salida' => '17:00', 'nota' => 'Hora corregida'])
            ->assertOk()->assertJsonPath('tarde_min', 30);

        $this->assertSame(1, Attendance::count());
        $this->assertSame($sup->id, Attendance::first()->edited_by);
        $this->assertSame(2, AuditLog::where('action', 'asistencia.manual')->count());
    }

    public function test_correccion_manual_nocturna_y_validaciones(): void
    {
        $sup = $this->usuario('supervisor', 'S-1');
        $ana = $this->usuario('inspector', 'I-1', 'Ana');
        Sanctum::actingAs($sup);
        $ayer = today()->subDay()->toDateString();

        // salida menor o igual a la entrada = al día siguiente
        $this->postJson('/api/asistencia/manual', ['usuario_id' => $ana->id, 'fecha' => $ayer, 'entrada' => '22:00', 'salida' => '06:00', 'nota' => 'Turno noche'])
            ->assertCreated()->assertJsonPath('horas', 8);

        $base = ['usuario_id' => $ana->id, 'entrada' => '08:00', 'nota' => 'Motivo'];
        $this->postJson('/api/asistencia/manual', $base + ['fecha' => today()->addDay()->toDateString()])->assertStatus(422)->assertJsonValidationErrors('fecha');
        $this->postJson('/api/asistencia/manual', ['usuario_id' => $ana->id, 'fecha' => $ayer, 'entrada' => '08:00'])->assertStatus(422)->assertJsonValidationErrors('nota');
        $this->postJson('/api/asistencia/manual', array_merge($base, ['fecha' => $ayer, 'entrada' => '8am']))->assertStatus(422)->assertJsonValidationErrors('entrada');
        $this->postJson('/api/asistencia/manual', ['usuario_id' => 9999, 'fecha' => $ayer, 'entrada' => '08:00', 'nota' => 'x y z'])->assertStatus(422)->assertJsonValidationErrors('usuario_id');
    }

    public function test_correccion_no_acepta_horas_futuras_de_hoy(): void
    {
        $sup = $this->usuario('supervisor', 'S-1');
        Sanctum::actingAs($sup);

        $this->postJson('/api/asistencia/manual', ['usuario_id' => $sup->id, 'fecha' => today()->toDateString(), 'entrada' => '23:00', 'nota' => 'Prueba'])->assertStatus(422);
    }

    public function test_historial_filtra_por_rango_y_persona_y_resume(): void
    {
        $sup = $this->usuario('admin', 'AD-1');
        $ana = $this->usuario('inspector', 'I-1', 'Ana');
        $beto = $this->usuario('inspector', 'I-2', 'Beto');
        $hace = fn (int $d) => today()->subDays($d)->toDateString();

        Attendance::create(['user_id' => $ana->id, 'work_date' => $hace(1), 'check_in' => now()->subDay()->setTime(8, 0), 'check_out' => now()->subDay()->setTime(16, 0), 'source' => 'self']);
        Attendance::create(['user_id' => $ana->id, 'work_date' => $hace(2), 'check_in' => now()->subDays(2)->setTime(8, 30), 'late_minutes' => 30, 'source' => 'self']);
        Attendance::create(['user_id' => $beto->id, 'work_date' => $hace(1), 'check_in' => now()->subDay()->setTime(8, 0), 'check_out' => now()->subDay()->setTime(12, 0), 'source' => 'self']);
        Attendance::create(['user_id' => $ana->id, 'work_date' => $hace(30), 'check_in' => now()->subDays(30)->setTime(8, 0), 'source' => 'self']);

        Sanctum::actingAs($sup);
        $r = $this->getJson("/api/asistencia/historial?desde={$hace(7)}&hasta={$hace(0)}")->assertOk();
        $r->assertJsonPath('resumen.registros', 3)->assertJsonPath('resumen.horas', 12)->assertJsonPath('resumen.retardos', 1)->assertJsonPath('resumen.sin_salida', 1);

        $this->getJson("/api/asistencia/historial?desde={$hace(7)}&hasta={$hace(0)}&usuario_id={$beto->id}")->assertJsonPath('resumen.registros', 1);
        $this->getJson('/api/asistencia/historial?desde=2020-01-01&hasta=2020-12-31')->assertStatus(422); // rango > 3 meses
    }

    public function test_csv_se_exporta_y_neutraliza_formulas(): void
    {
        $sup = $this->usuario('supervisor', 'S-1');
        $malo = $this->usuario('inspector', 'I-1', '=HYPERLINK("http://x")');
        Attendance::create(['user_id' => $malo->id, 'work_date' => today()->subDay()->toDateString(), 'check_in' => now()->subDay()->setTime(8, 0), 'check_out' => now()->subDay()->setTime(16, 0), 'source' => 'manual', 'note' => '+cmd']);

        Sanctum::actingAs($sup);
        $res = $this->get('/api/asistencia/datos.csv?desde='.today()->subDays(3)->toDateString().'&hasta='.today()->toDateString());
        $res->assertOk();
        $cuerpo = $res->streamedContent();

        $this->assertStringContainsString('Fecha,Empleado,Nombre,Entrada,Salida,Horas', $cuerpo);
        $this->assertStringContainsString("'=HYPERLINK", $cuerpo);
        $this->assertStringContainsString("'+cmd", $cuerpo);
        $this->assertStringContainsString('Corrección', $cuerpo);
    }

    public function test_la_entrada_solo_se_registra_dentro_de_la_jornada(): void
    {
        Sanctum::actingAs($this->usuario('inspector'));

        $this->travelTo(now()->setTime(6, 30));
        $this->getJson('/api/asistencia/hoy')->assertJsonPath('puede_entrar', false)->assertJsonPath('jornada', '08:00–16:00');
        $this->postJson('/api/asistencia/entrada')->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'abre a las 07:00'));

        $this->travelTo(now()->setTime(16, 30));
        $this->getJson('/api/asistencia/hoy')->assertJsonPath('inasistencia', true);
        $this->postJson('/api/asistencia/entrada')->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'ya terminó'));
        $this->assertSame(0, Attendance::count());

        $this->travelTo(now()->setTime(7, 0)); // abre una hora antes del inicio
        $this->postJson('/api/asistencia/entrada')->assertCreated()->assertJsonPath('tarde_min', 0);
    }

    public function test_el_dia_marca_inasistencia_al_cerrar_la_jornada_y_descanso_en_dias_no_laborables(): void
    {
        $sup = $this->usuario('supervisor', 'S-1');
        $ana = $this->usuario('inspector', 'I-1', 'Ana');
        Sanctum::actingAs($sup);
        $estado = fn () => collect($this->getJson('/api/asistencia')->json('filas'))->firstWhere('usuario_id', $ana->id)['estado'];

        $this->assertSame('ausente', $estado());                       // 7:55, la jornada aún no empieza
        $this->travelTo(now()->setTime(16, 5));
        $this->assertSame('inasistencia', $estado());
        $this->getJson('/api/asistencia')->assertJsonPath('resumen.inasistencias', 2);

        config(['calidad.asistencia.dias' => []]);
        $this->assertSame('descanso', $estado());
    }

    public function test_historial_y_csv_incluyen_inasistencias_de_dias_laborables_cerrados(): void
    {
        $sup = $this->usuario('supervisor', 'S-1');
        $ana = $this->usuario('inspector', 'I-1', 'Ana');
        $ana->forceFill(['created_at' => now()->subDays(5)])->save();
        Attendance::create(['user_id' => $ana->id, 'work_date' => today()->subDays(2)->toDateString(), 'check_in' => now()->subDays(2)->setTime(8, 0), 'check_out' => now()->subDays(2)->setTime(16, 0), 'source' => 'self']);

        Sanctum::actingAs($sup);
        $q = 'desde='.today()->subDays(3)->toDateString().'&hasta='.today()->toDateString().'&usuario_id='.$ana->id;
        $r = $this->getJson("/api/asistencia/historial?$q")->assertOk();
        $r->assertJsonPath('resumen.registros', 1)->assertJsonPath('resumen.inasistencias', 2); // hoy aún no cierra; hace 3 y 1 días sí

        $csv = $this->get("/api/asistencia/datos.csv?$q")->streamedContent();
        $this->assertSame(2, substr_count($csv, 'Inasistencia'));
        $this->assertStringContainsString('Asistencia', $csv);
    }
}
