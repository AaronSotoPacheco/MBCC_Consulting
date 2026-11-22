<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use App\Support\Auditoria;
use App\Support\Mensajes;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Check-in de asistencia. Cada persona registra su entrada y salida con su sesión;
 * supervisor y admin ven quién está presente, el historial, exportan y corrigen olvidos (con bitácora).
 */
class AsistenciaController extends Controller
{
    // ------------------------------------------------------------------ la propia persona

    public function hoy(Request $request)
    {
        $user = $request->user();
        $abierto = $this->abierto($user->id);
        $delDia = Attendance::where('user_id', $user->id)->whereDate('work_date', today())->first();
        $registro = $abierto ?? $delDia;

        return [
            'ahora' => now()->toIso8601String(),
            'fecha' => today()->toDateString(),
            'hora' => now()->format('H:i'),
            'estado' => ! $registro ? 'sin_entrada' : ($registro->check_out ? 'salio' : 'dentro'),
            'registro' => $registro ? $this->fila($registro) : null,
            'recientes' => Attendance::where('user_id', $user->id)->orderByDesc('work_date')->limit(7)->get()->map(fn ($r) => $this->fila($r))->values(),
            'jornada' => $this->jornadaTexto(),
            'tolerancia_min' => (int) config('calidad.asistencia.tolerancia_min'),
            'puede_entrar' => ! $registro && $this->bloqueoEntrada(now()) === null,
            'aviso_entrada' => ! $registro ? $this->bloqueoEntrada(now()) : null,
            'inasistencia' => ! $registro && $this->laborable(today()->toDateString()) && $this->diaCerrado(today()->toDateString()),
        ];
    }

    public function entrada(Request $request)
    {
        $user = $request->user();

        if ($abierto = $this->abierto($user->id)) {
            return $this->rechazo('Ya tienes una entrada abierta desde las '.$abierto->check_in->format('H:i').'. Registra tu salida primero.');
        }
        if (Attendance::where('user_id', $user->id)->whereDate('work_date', today())->exists()) {
            return $this->rechazo('Ya registraste tu entrada y salida de hoy. Si necesitas corregirlas, avisa a tu supervisor.');
        }

        $ahora = now();
        if ($bloqueo = $this->bloqueoEntrada($ahora)) {
            return $this->rechazo($bloqueo);
        }
        try {
            $registro = Attendance::create([
                'user_id' => $user->id,
                'work_date' => $ahora->toDateString(),
                'check_in' => $ahora,
                'late_minutes' => $this->retardo($ahora),
                'source' => 'self',
            ]);
        } catch (QueryException $e) { // doble toque simultáneo: gana el primero
            return $this->rechazo('Tu entrada de hoy ya estaba registrada.');
        }

        return response()->json($this->fila($registro), 201);
    }

    public function salida(Request $request)
    {
        $registro = $this->abierto($request->user()->id);
        if (! $registro) {
            return $this->rechazo('No tienes una entrada abierta. Registra primero tu entrada.');
        }

        $registro->update(['check_out' => now()]);

        return $this->fila($registro->fresh());
    }

    // ------------------------------------------------------------------ supervisor y admin

    /** Quién está presente en una fecha: todos los usuarios activos con su estado. */
    public function dia(Request $request)
    {
        $f = $request->validate(['fecha' => ['nullable', 'date_format:Y-m-d']]);
        $fecha = $f['fecha'] ?? today()->toDateString();

        $registros = Attendance::whereDate('work_date', $fecha)->get()->keyBy('user_id');
        if ($fecha === today()->toDateString()) { // turno nocturno: la entrada de ayer sigue abierta
            foreach (Attendance::whereNull('check_out')->whereDate('work_date', '<', $fecha)->where('check_in', '>=', now()->subHours((int) config('calidad.asistencia.horas_max_turno')))->get() as $r) {
                $registros[$r->user_id] ??= $r;
            }
        }

        $orden = ['dentro' => 0, 'salio' => 1, 'ausente' => 2, 'inasistencia' => 3, 'descanso' => 4];
        $laborable = $this->laborable($fecha);
        $cerrado = $this->diaCerrado($fecha);
        $filas = User::with('role')->where('is_active', true)->get()->map(function (User $u) use ($registros, $fecha, $laborable, $cerrado) {
            $r = $registros->get($u->id);

            return [
                'usuario_id' => $u->id,
                'nombre' => $u->full_name,
                'numero' => $u->employee_number,
                'nivel' => $u->role?->name,
                'estado' => $r ? ($r->check_out ? 'salio' : 'dentro')
                    : (! $laborable || $fecha < ($u->created_at?->toDateString() ?? '0000-00-00') ? 'descanso' : ($cerrado ? 'inasistencia' : 'ausente')),
            ] + ($r ? $this->fila($r) : ['id' => null, 'fecha' => null, 'entrada_hora' => null, 'salida_hora' => null, 'tarde_min' => 0, 'horas' => null, 'origen' => null, 'nota' => null]);
        })->sortBy(fn ($x) => $orden[$x['estado']].'|'.mb_strtolower($x['nombre']))->values();

        return [
            'fecha' => $fecha,
            'resumen' => [
                'total' => $filas->count(),
                'presentes' => $filas->where('estado', 'dentro')->count(),
                'salieron' => $filas->where('estado', 'salio')->count(),
                'ausentes' => $filas->where('estado', 'ausente')->count(),
                'inasistencias' => $filas->where('estado', 'inasistencia')->count(),
                'retardos' => $filas->where('tarde_min', '>', 0)->count(),
            ],
            'filas' => $filas,
        ];
    }

    public function historial(Request $request)
    {
        [$desde, $hasta, $usuario] = $this->filtros($request);
        $filas = collect($this->filasHistorial($desde, $hasta, $usuario));
        $reg = $filas->where('estado', 'registro');

        return [
            'desde' => $desde, 'hasta' => $hasta,
            'resumen' => [
                'registros' => $reg->count(),
                'horas' => round($reg->sum(fn ($f) => $f['horas'] ?? 0), 1),
                'retardos' => $reg->where('tarde_min', '>', 0)->count(),
                'sin_salida' => $reg->whereNull('salida_hora')->count(),
                'inasistencias' => $filas->where('estado', 'inasistencia')->count(),
            ],
            'usuarios' => User::where('is_active', true)->orderBy('full_name')->get(['id', 'full_name', 'employee_number'])
                ->map(fn ($u) => ['id' => $u->id, 'nombre' => $u->full_name, 'numero' => $u->employee_number])->values(),
            'filas' => $filas->values(),
        ];
    }

    public function csv(Request $request)
    {
        [$desde, $hasta, $usuario] = $this->filtros($request);
        $filas = $this->filasHistorial($desde, $hasta, $usuario);

        return response()->streamDownload(function () use ($filas) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Fecha', 'Empleado', 'Nombre', 'Entrada', 'Salida', 'Horas', 'Retardo (min)', 'Estado', 'Origen', 'Nota']);
            foreach ($filas as $f) {
                $estado = $f['estado'] === 'inasistencia' ? 'Inasistencia' : (! $f['salida_hora'] ? 'Sin salida' : ($f['tarde_min'] > 0 ? 'Retardo' : 'Asistencia'));
                fputcsv($out, array_map([$this, 'celda'], [
                    $f['fecha'], $f['numero'], $f['nombre'], $f['entrada_hora'] ?? '', $f['salida_hora'] ?? '',
                    $f['horas'] ?? '', $f['tarde_min'], $estado, $f['origen'] === 'manual' ? 'Corrección' : ($f['origen'] ? 'Propio' : ''), $f['nota'] ?? '',
                ]));
            }
            fclose($out);
        }, 'asistencia.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Registra o corrige la asistencia de alguien (olvidos). Exige motivo y queda en la bitácora. */
    public function manual(Request $request)
    {
        $d = $request->validate([
            'usuario_id' => ['required', 'integer', 'exists:users,id'],
            'fecha' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'entrada' => ['required', 'date_format:H:i'],
            'salida' => ['nullable', 'date_format:H:i'],
            'nota' => ['required', 'string', 'min:3', 'max:200'],
        ], Mensajes::con([
            'fecha.before_or_equal' => 'No se puede registrar asistencia en una fecha futura.',
            'nota.required' => 'Escribe el motivo de la corrección.',
        ]), ['usuario_id' => 'empleado', 'nota' => 'motivo']);

        $entrada = Carbon::createFromFormat('Y-m-d H:i', $d['fecha'].' '.$d['entrada']);
        $salida = null;
        if (! empty($d['salida'])) {
            $salida = Carbon::createFromFormat('Y-m-d H:i', $d['fecha'].' '.$d['salida']);
            if ($salida->lte($entrada)) { // turno nocturno: termina al día siguiente
                $salida->addDay();
            }
        }
        if ($entrada->isFuture() || ($salida && $salida->isFuture())) {
            return $this->rechazo('La hora indicada todavía no ha ocurrido.');
        }

        $existia = Attendance::where('user_id', $d['usuario_id'])->whereDate('work_date', $d['fecha'])->exists();
        $registro = Attendance::updateOrCreate(
            ['user_id' => $d['usuario_id'], 'work_date' => $d['fecha']],
            ['check_in' => $entrada, 'check_out' => $salida, 'late_minutes' => $this->retardo($entrada), 'source' => 'manual', 'note' => $d['nota'], 'edited_by' => $request->user()->id],
        );

        $persona = User::find($d['usuario_id']);
        Auditoria::registrar(
            'asistencia.manual',
            ($existia ? 'Corrigió' : 'Registró').' la asistencia de '.$persona->full_name.' del '.$d['fecha'].': entrada '.$d['entrada'].', '.($salida ? 'salida '.$salida->format('H:i') : 'sin salida').' ('.$d['nota'].')',
            $registro,
            ['usuario_id' => $persona->id, 'fecha' => $d['fecha'], 'entrada' => $d['entrada'], 'salida' => $salida?->format('H:i'), 'motivo' => $d['nota']],
        );

        return response()->json($this->fila($registro->fresh()), $existia ? 200 : 201);
    }

    // ------------------------------------------------------------------ auxiliares

    private function abierto(int $userId): ?Attendance
    {
        return Attendance::where('user_id', $userId)->whereNull('check_out')
            ->where('check_in', '>=', now()->subHours((int) config('calidad.asistencia.horas_max_turno')))
            ->orderByDesc('check_in')->first();
    }

    /** Minutos de retardo contra el inicio de su turno; 0 si cae dentro de la tolerancia. */
    private function retardo(Carbon $entrada): int
    {
        if ($vigente = $this->turnoVigente($entrada)) {
            $dif = $vigente[0]->diffInMinutes($entrada, false);
        } else { // entrada corregida fuera de ventana: se mide contra el inicio más cercano
            $dif = null;
            foreach ([-1, 0, 1] as $dias) {
                foreach ($this->turnosDelDia($entrada->copy()->addDays($dias)) as [$inicio]) {
                    $d = $inicio->diffInMinutes($entrada, false);
                    if ($dif === null || abs($d) < abs($dif)) {
                        $dif = $d;
                    }
                }
            }
        }

        return ($dif !== null && $dif > (int) config('calidad.asistencia.tolerancia_min')) ? (int) $dif : 0;
    }

    /** Turnos de un día como pares [inicio, fin] (fin al día siguiente si cruza la medianoche). */
    private function turnosDelDia(Carbon $dia): array
    {
        $pares = [];
        foreach (config('calidad.asistencia.turnos') as $t) {
            $inicio = $dia->copy()->startOfDay()->setTimeFromTimeString($t['inicio']);
            $fin = $dia->copy()->startOfDay()->setTimeFromTimeString($t['fin']);
            if ($fin->lte($inicio)) {
                $fin->addDay();
            }
            $pares[] = [$inicio, $fin];
        }

        return $pares;
    }

    /** Turno en el que cae el instante dado, contando el anticipo con que abre el registro. */
    private function turnoVigente(Carbon $t): ?array
    {
        $anticipo = (int) config('calidad.asistencia.anticipo_min');
        foreach ([-1, 0] as $d) {
            foreach ($this->turnosDelDia($t->copy()->addDays($d)) as [$i, $f]) {
                if ($t->gte($i->copy()->subMinutes($anticipo)) && $t->lte($f)) {
                    return [$i, $f];
                }
            }
        }

        return null;
    }

    /** Motivo por el que no se puede registrar la entrada ahora (null = se puede). */
    private function bloqueoEntrada(Carbon $t): ?string
    {
        if ($this->turnoVigente($t)) {
            return null;
        }
        $anticipo = (int) config('calidad.asistencia.anticipo_min');
        $abre = null;
        foreach ($this->turnosDelDia($t) as [$i]) {
            $a = $i->copy()->subMinutes($anticipo);
            if ($t->lt($a) && (! $abre || $a->lt($abre))) {
                $abre = $a;
            }
        }

        return $abre
            ? 'Todavía no puedes registrar tu entrada: el registro abre a las '.$abre->format('H:i').' (jornada '.$this->jornadaTexto().').'
            : 'La jornada ('.$this->jornadaTexto().') ya terminó y no se puede registrar la entrada. Si trabajaste hoy, pide a tu supervisor que la registre.';
    }

    private function jornadaTexto(): string
    {
        return collect(config('calidad.asistencia.turnos'))->map(fn ($t) => $t['inicio'].'–'.$t['fin'])->implode(' / ');
    }

    private function laborable(string $fecha): bool
    {
        return in_array(Carbon::parse($fecha)->isoWeekday(), config('calidad.asistencia.dias'), true);
    }

    /** Un día está cerrado cuando ya terminó su último turno: sin registro, cuenta como inasistencia. */
    private function diaCerrado(string $fecha): bool
    {
        if ($fecha < today()->toDateString()) {
            return true;
        }
        if ($fecha > today()->toDateString()) {
            return false;
        }
        $fin = collect($this->turnosDelDia(Carbon::parse($fecha)))->max(fn ($p) => $p[1]->timestamp);

        return now()->timestamp > $fin;
    }

    /** Registros del rango más las inasistencias (días laborables cerrados sin registro) de cada usuario activo. */
    private function filasHistorial(string $desde, string $hasta, ?int $usuario): array
    {
        $registros = $this->consulta($desde, $hasta, $usuario)->with('user:id,full_name,employee_number')->limit(2000)->get();
        $filas = $registros->map(fn (Attendance $r) => $this->fila($r) + ['estado' => 'registro', 'usuario_id' => $r->user_id, 'nombre' => $r->user?->full_name, 'numero' => $r->user?->employee_number])->all();
        $tiene = $registros->mapWithKeys(fn ($r) => [$r->user_id.'|'.$r->work_date->toDateString() => true]);

        foreach (User::where('is_active', true)->when($usuario, fn ($q) => $q->where('id', $usuario))->get() as $u) {
            $alta = $u->created_at?->toDateString() ?? '0000-00-00';
            for ($c = Carbon::parse($desde), $fin = Carbon::parse($hasta); $c->lte($fin); $c->addDay()) {
                $f = $c->toDateString();
                if ($f < $alta || ! $this->laborable($f) || ! $this->diaCerrado($f) || isset($tiene[$u->id.'|'.$f])) {
                    continue;
                }
                $filas[] = [
                    'id' => null, 'fecha' => $f, 'entrada' => null, 'entrada_hora' => null, 'salida' => null, 'salida_hora' => null,
                    'tarde_min' => 0, 'horas' => null, 'origen' => null, 'nota' => null, 'estado' => 'inasistencia',
                    'usuario_id' => $u->id, 'nombre' => $u->full_name, 'numero' => $u->employee_number,
                ];
            }
        }
        usort($filas, fn ($a, $b) => strcmp($b['fecha'], $a['fecha']) ?: strcmp((string) $a['nombre'], (string) $b['nombre']));

        return $filas;
    }

    private function fila(Attendance $r): array
    {
        $min = $r->minutes();

        return [
            'id' => $r->id,
            'fecha' => $r->work_date->toDateString(),
            'entrada' => $r->check_in->toIso8601String(),
            'entrada_hora' => $r->check_in->format('H:i'),
            'salida' => $r->check_out?->toIso8601String(),
            'salida_hora' => $r->check_out?->format('H:i'),
            'tarde_min' => (int) $r->late_minutes,
            'horas' => $min !== null ? round($min / 60, 2) : null,
            'origen' => $r->source,
            'nota' => $r->note,
        ];
    }

    private function filtros(Request $request): array
    {
        $f = $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'usuario_id' => ['nullable', 'integer'],
        ]);
        $hasta = $f['hasta'] ?? today()->toDateString();
        $desde = $f['desde'] ?? Carbon::parse($hasta)->subDays(6)->toDateString();
        abort_if(Carbon::parse($desde)->diffInDays(Carbon::parse($hasta)) > 92, 422, 'El rango máximo es de 3 meses.');

        return [$desde, $hasta, $f['usuario_id'] ?? null];
    }

    private function consulta(string $desde, string $hasta, ?int $usuario)
    {
        return Attendance::query()
            ->whereDate('work_date', '>=', $desde)->whereDate('work_date', '<=', $hasta)
            ->when($usuario, fn ($q) => $q->where('user_id', $usuario))
            ->orderByDesc('work_date')->orderBy('check_in');
    }

    /** Evita que Excel ejecute fórmulas escritas en nombres o notas (=, +, -, @). */
    private function celda(mixed $v): mixed
    {
        return is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$v : $v;
    }

    private function rechazo(string $mensaje)
    {
        return response()->json(['message' => $mensaje], 422);
    }
}
