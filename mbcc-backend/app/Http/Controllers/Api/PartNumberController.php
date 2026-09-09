<?php

namespace App\Http\Controllers\Api;

use App\Models\PartNumber;

class PartNumberController extends QualityResourceController
{
    protected string $modelClass = PartNumber::class;

    protected array $rules = ['client_id' => 'required|integer|exists:clients,id', 'part_number' => 'required|string|max:100', 'description' => 'nullable|string|max:255'];
}
