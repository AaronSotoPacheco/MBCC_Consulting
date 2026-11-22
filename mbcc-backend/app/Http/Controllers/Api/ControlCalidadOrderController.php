<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\SortOrder;
use App\Models\WorkInstruction;
use App\Support\Auditoria;
use App\Support\Mensajes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ControlCalidadOrderController extends Controller
{
    /** Cambios de estado permitidos: de dónde → a dónde. Reabrir una cerrada es solo del administrador. */
    private const TRANSICIONES = [
        'OPEN' => ['PAUSED', 'CLOSED'],
        'PAUSED' => ['OPEN', 'CLOSED'],
        'CLOSED' => ['OPEN'],
    ];

    private function esGestor(Request $request): bool
    {
        return in_array($request->user()->role?->name, ['supervisor', 'admin'], true);
    }

    private function consulta()
    {
        return SortOrder::query()
            ->with(['partNumber.client', 'workInstruction'])
            ->withCount([
                'serialRecords as inspeccionadas',
                'serialRecords as rechazadas' => fn ($query) => $query->whereIn('status', ['FAILED', 'REWORK_REQUIRED', 'SCRAP']),
            ]);
    }

    /** @return list<string> */
    private static function lineas(?string $texto): array
    {
        return $texto ? array_values(array_filter(array_map('trim', preg_split('/\R/', $texto)), fn ($l) => $l !== '')) : [];
    }

    private function formato(SortOrder $order): array
    {
        return [
            'id' => $order->id,
            'cliente' => $order->partNumber?->client?->name ?? '',
            'so' => $order->order_number,
            'parte' => $order->partNumber?->part_number ?? '',
            'descripcion' => $order->partNumber?->description ?: $order->workInstruction?->title ?? '',
            'meta' => (int) $order->target_quantity,
            'inspeccionadas' => (int) ($order->inspeccionadas ?? 0),
            'rechazadas' => (int) ($order->rechazadas ?? 0),
            'estado' => $order->status,
            'turno' => $order->shift_hours,
            'wi' => $order->workInstruction ? [
                'id' => $order->workInstruction->id,
                'codigo' => $order->workInstruction->code,
                'revision' => $order->workInstruction->revision,
                'titulo' => $order->workInstruction->title,
                'archivo' => $order->workInstruction->file_path,
                'puntos' => self::lineas($order->workInstruction->key_points),
                'especificaciones' => $order->workInstruction->specifications,
            ] : null,
        ];
    }

    /** Abiertas y pausadas. Supervisor y admin pueden pedir también las cerradas con ?cerradas=1. */
    public function index(Request $request)
    {
        $estados = ['OPEN', 'PAUSED'];
        if ($request->boolean('cerradas') && $this->esGestor($request)) {
            $estados[] = 'CLOSED';
        }

        return $this->consulta()
            ->whereIn('status', $estados)
            ->orderByRaw("CASE status WHEN 'OPEN' THEN 0 WHEN 'PAUSED' THEN 1 ELSE 2 END")
            ->orderBy('order_number')
            ->get()
            ->map(fn (SortOrder $order) => $this->formato($order));
    }

    /** Datos para el formulario "Nueva orden": clientes → partes → instrucciones vigentes, y horarios ya usados. */
    public function opciones()
    {
        $clientes = Client::query()
            ->with(['partNumbers' => fn ($q) => $q->orderBy('part_number'), 'partNumbers.workInstructions' => fn ($q) => $q->where('status', 'ACTIVE')->orderBy('code')])
            ->orderBy('name')
            ->get()
            ->map(fn (Client $client) => [
                'id' => $client->id,
                'nombre' => $client->name,
                'partes' => $client->partNumbers->map(fn ($part) => [
                    'id' => $part->id,
                    'parte' => $part->part_number,
                    'descripcion' => $part->description,
                    'instrucciones' => $part->workInstructions->map(fn (WorkInstruction $wi) => [
                        'id' => $wi->id,
                        'codigo' => $wi->code,
                        'revision' => $wi->revision,
                        'titulo' => $wi->title,
                    ])->values(),
                ])->values(),
            ]);

        $turnos = SortOrder::query()->whereNotNull('shift_hours')->distinct()->orderBy('shift_hours')->pluck('shift_hours');

        return ['clientes' => $clientes, 'turnos' => $turnos];
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'numero_orden' => ['required', 'string', 'max:50', 'unique:sort_orders,order_number'],
            'parte_id' => ['required', 'integer', 'exists:part_numbers,id'],
            'instruccion_id' => ['required', 'integer', 'exists:work_instructions,id'],
            'meta' => ['required', 'integer', 'min:1', 'max:1000000'],
            'turno' => ['nullable', 'string', 'max:50'],
        ], Mensajes::con([
            'numero_orden.unique' => 'Ya existe una orden con ese número.',
        ]), [
            'numero_orden' => 'número de orden', 'parte_id' => 'número de parte',
            'instruccion_id' => 'instrucción de trabajo', 'meta' => 'meta de piezas', 'turno' => 'horario',
        ]);

        $instruccion = WorkInstruction::findOrFail($data['instruccion_id']);
        if ((int) $instruccion->part_number_id !== (int) $data['parte_id']) {
            return response()->json([
                'message' => 'La instrucción no corresponde a ese número de parte.',
                'errors' => ['instruccion_id' => ['La instrucción no corresponde a ese número de parte.']],
            ], 422);
        }
        if ($instruccion->status !== 'ACTIVE') {
            return response()->json([
                'message' => 'Solo se puede usar una instrucción de trabajo vigente.',
                'errors' => ['instruccion_id' => ['Solo se puede usar una instrucción de trabajo vigente.']],
            ], 422);
        }

        $order = SortOrder::create([
            'order_number' => trim($data['numero_orden']),
            'part_number_id' => $data['parte_id'],
            'work_instruction_id' => $instruccion->id,
            'target_quantity' => $data['meta'],
            'status' => 'OPEN',
            'shift_hours' => ! empty($data['turno']) ? $data['turno'] : '8:00 AM - 4:00 PM',
        ]);

        Auditoria::registrar('orden.crear', "Creó la orden {$order->order_number} (meta {$order->target_quantity})", $order);

        return response()->json($this->formato($this->consulta()->findOrFail($order->id)), 201);
    }

    /** Solo meta y horario; la parte y la instrucción no cambian una vez creada la orden. */
    public function update(Request $request, SortOrder $orden)
    {
        if ($orden->status === 'CLOSED') {
            return response()->json(['message' => 'Una orden cerrada no se puede editar. Pide a un administrador que la reabra.'], 422);
        }

        $data = $request->validate([
            'meta' => ['required', 'integer', 'min:1', 'max:1000000'],
            'turno' => ['nullable', 'string', 'max:50'],
        ], Mensajes::ES, ['meta' => 'meta de piezas', 'turno' => 'horario']);

        $metaAnterior = (int) $orden->target_quantity;
        $orden->update([
            'target_quantity' => $data['meta'],
            'shift_hours' => ! empty($data['turno']) ? $data['turno'] : $orden->shift_hours,
        ]);

        Auditoria::registrar('orden.editar', "Editó la orden {$orden->order_number}: meta {$metaAnterior} → {$orden->target_quantity}", $orden, ['meta_anterior' => $metaAnterior, 'meta_nueva' => (int) $orden->target_quantity]);

        return $this->formato($this->consulta()->findOrFail($orden->id));
    }

    public function cambiarEstado(Request $request, SortOrder $orden)
    {
        $data = $request->validate(['estado' => ['required', 'in:OPEN,PAUSED,CLOSED']], Mensajes::ES, ['estado' => 'estado']);
        $nuevo = $data['estado'];

        $resultado = DB::transaction(function () use ($request, $orden, $nuevo) {
            $actual = SortOrder::query()->lockForUpdate()->findOrFail($orden->id);

            if (! in_array($nuevo, self::TRANSICIONES[$actual->status] ?? [], true)) {
                return ['error' => 'No se puede pasar una orden de '.$actual->status.' a '.$nuevo.'.', 'codigo' => 422];
            }
            if ($actual->status === 'CLOSED' && $request->user()->role?->name !== 'admin') {
                return ['error' => 'Solo un administrador puede reabrir una orden cerrada.', 'codigo' => 403];
            }

            $antes = $actual->status;
            $actual->update(['status' => $nuevo]);

            return ['id' => $actual->id, 'antes' => $antes, 'orden' => $actual];
        });

        if (isset($resultado['error'])) {
            return response()->json(['message' => $resultado['error']], $resultado['codigo']);
        }

        $verbo = ['OPEN' => $resultado['antes'] === 'CLOSED' ? 'Reabrió' : 'Reanudó', 'PAUSED' => 'Pausó', 'CLOSED' => 'Cerró'][$nuevo];
        Auditoria::registrar('orden.estado', "{$verbo} la orden {$resultado['orden']->order_number}", $resultado['orden'], ['de' => $resultado['antes'], 'a' => $nuevo]);

        return $this->formato($this->consulta()->findOrFail($resultado['id']));
    }
}
