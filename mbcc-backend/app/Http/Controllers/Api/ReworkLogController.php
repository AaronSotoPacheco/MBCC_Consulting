<?php

namespace App\Http\Controllers\Api;

use App\Models\ReworkLog;

class ReworkLogController extends QualityResourceController
{
    protected string $modelClass = ReworkLog::class;

    protected array $rules = ['serial_record_id' => 'required|integer|exists:serial_records,id', 'rework_action' => 'required|string|max:255', 'reworked_by' => 'required|integer|exists:users,id', 'reinspected_by' => 'nullable|integer|exists:users,id', 'final_status' => 'required|in:PASSED,SCRAP', 'completed_at' => 'nullable|date'];
}
