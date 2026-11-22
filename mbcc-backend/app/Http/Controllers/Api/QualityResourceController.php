<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Mensajes;
use Illuminate\Http\Request;

abstract class QualityResourceController extends Controller
{
    protected string $modelClass;

    protected array $rules = [];

    /** Reglas de validación; las subclases las sobrescriben para añadir "unique" (con $id al editar). */
    protected function rules(?int $id = null): array
    {
        return $this->rules;
    }

    protected function validated(Request $request, ?int $id = null): array
    {
        return $request->validate($this->rules($id), Mensajes::ES);
    }

    public function index(Request $request)
    {
        return $this->modelClass::query()->paginate(min($request->integer('per_page', 15), 200));
    }

    public function store(Request $request)
    {
        $record = $this->modelClass::create($this->validated($request));

        return response()->json($record, 201);
    }

    public function show(int $id)
    {
        return $this->modelClass::findOrFail($id);
    }

    public function update(Request $request, int $id)
    {
        $record = $this->modelClass::findOrFail($id);
        $record->update($this->validated($request, $id));

        return $record->fresh();
    }

    /** Los catálogos no se borran (tienen registros relacionados): se desactivan o se editan. */
    public function destroy(int $id)
    {
        return response()->json(['message' => 'Este registro no se puede eliminar.'], 405);
    }
}
