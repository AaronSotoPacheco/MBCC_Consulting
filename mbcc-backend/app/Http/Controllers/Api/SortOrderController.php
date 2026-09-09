<?php

namespace App\Http\Controllers\Api;

use App\Models\SortOrder;

class SortOrderController extends QualityResourceController
{
    protected string $modelClass = SortOrder::class;

    protected array $rules = ['order_number' => 'required|string|max:50', 'part_number_id' => 'required|integer|exists:part_numbers,id', 'work_instruction_id' => 'required|integer|exists:work_instructions,id', 'target_quantity' => 'required|integer|min:1', 'status' => 'required|in:OPEN,PAUSED,CLOSED', 'shift_hours' => 'nullable|string|max:50'];
}
