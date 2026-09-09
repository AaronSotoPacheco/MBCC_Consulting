<?php

namespace App\Http\Controllers\Api;

use App\Models\DefectCatalog;

class DefectCatalogController extends QualityResourceController
{
    protected string $modelClass = DefectCatalog::class;

    protected array $rules = ['code' => 'required|string|max:50', 'category' => 'required|in:COSMETIC,MECHANICAL,FUNCTIONAL,PACKAGING', 'description' => 'required|string|max:255', 'is_active' => 'boolean'];
}
