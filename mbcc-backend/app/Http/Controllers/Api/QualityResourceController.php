<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

abstract class QualityResourceController extends Controller
{
    protected string $modelClass;

    protected array $rules = [];

    public function index()
    {
        return $this->modelClass::query()->paginate(request()->integer('per_page', 15));
    }

    public function store(Request $request)
    {
        $record = $this->modelClass::create($request->validate($this->rules));

        return response()->json($record, 201);
    }

    public function show(int $id)
    {
        return $this->modelClass::findOrFail($id);
    }

    public function update(Request $request, int $id)
    {
        $record = $this->modelClass::findOrFail($id);
        $record->update($request->validate($this->rules));

        return $record->fresh();
    }

    public function destroy(int $id)
    {
        $record = $this->modelClass::findOrFail($id);
        $record->delete();

        return response()->noContent();
    }
}
