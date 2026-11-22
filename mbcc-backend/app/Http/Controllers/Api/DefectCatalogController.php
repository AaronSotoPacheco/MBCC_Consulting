<?php

namespace App\Http\Controllers\Api;

use App\Models\DefectCatalog;
use Illuminate\Validation\Rule;

class DefectCatalogController extends QualityResourceController
{
    protected string $modelClass = DefectCatalog::class;

    protected function rules(?int $id = null): array
    {
        return [
            // El código es el identificador del defecto: mayúsculas, números y guiones
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9-]+$/', Rule::unique('defect_catalog', 'code')->ignore($id)],
            'category' => ['required', 'in:COSMETIC,MECHANICAL,FUNCTIONAL,PACKAGING'],
            'description' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }

    public function store(\Illuminate\Http\Request $request)
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        return parent::store($request);
    }

    public function update(\Illuminate\Http\Request $request, int $id)
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        return parent::update($request, $id);
    }
}
