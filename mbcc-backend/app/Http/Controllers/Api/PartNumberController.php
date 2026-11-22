<?php

namespace App\Http\Controllers\Api;

use App\Models\PartNumber;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PartNumberController extends QualityResourceController
{
    protected string $modelClass = PartNumber::class;

    protected function rules(?int $id = null): array
    {
        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'part_number' => [
                'required', 'string', 'max:100',
                Rule::unique('part_numbers', 'part_number')
                    ->where('client_id', request()->input('client_id'))
                    ->ignore($id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** Incluye el nombre del cliente para mostrarlo sin pedir el catálogo de clientes (que es solo del admin). */
    public function index(Request $request)
    {
        $parts = PartNumber::query()->with('client:id,name')->orderBy('part_number')
            ->paginate(min($request->integer('per_page', 15), 200));

        $parts->getCollection()->transform(fn (PartNumber $part) => [
            'id' => $part->id,
            'client_id' => $part->client_id,
            'client' => $part->client?->name,
            'part_number' => $part->part_number,
            'description' => $part->description,
        ]);

        return $parts;
    }
}
