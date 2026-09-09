<?php

namespace App\Http\Controllers\Api;

use App\Models\SerialRecord;

class SerialRecordController extends QualityResourceController
{
    protected string $modelClass = SerialRecord::class;

    protected array $rules = ['sort_order_id' => 'required|integer|exists:sort_orders,id', 'serial_number' => 'required|string|max:100', 'status' => 'required|in:PASSED,FAILED,REWORK_REQUIRED,SCRAP', 'inspected_by' => 'required|integer|exists:users,id', 'station_identifier' => 'nullable|string|max:50'];
}
