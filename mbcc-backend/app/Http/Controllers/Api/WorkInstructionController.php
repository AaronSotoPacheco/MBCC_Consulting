<?php

namespace App\Http\Controllers\Api;

use App\Models\WorkInstruction;

class WorkInstructionController extends QualityResourceController
{
    protected string $modelClass = WorkInstruction::class;

    protected array $rules = ['part_number_id' => 'required|integer|exists:part_numbers,id', 'code' => 'required|string|max:50', 'revision' => 'required|string|max:10', 'title' => 'required|string|max:200', 'file_path' => 'required|string|max:255', 'status' => 'required|in:DRAFT,ACTIVE,OBSOLETE', 'approved_by' => 'nullable|integer|exists:users,id', 'approved_at' => 'nullable|date'];
}
