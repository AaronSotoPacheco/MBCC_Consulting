<?php

namespace App\Http\Controllers\Api;

use App\Models\SerialDefectLog;

class SerialDefectLogController extends QualityResourceController
{
    protected string $modelClass = SerialDefectLog::class;

    protected array $rules = ['serial_record_id' => 'required|integer|exists:serial_records,id', 'defect_id' => 'required|integer|exists:defect_catalog,id', 'zone' => 'nullable|string|max:10', 'photo_evidence_url' => 'nullable|url|max:255', 'notes' => 'nullable|string'];
}
