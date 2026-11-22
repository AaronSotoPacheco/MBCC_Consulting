<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SerialRecord extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['sort_order_id', 'serial_number', 'client_uuid', 'status', 'inspected_by', 'station_identifier', 'shift', 'ai_verdict', 'ai_defect', 'ai_confidence'];

    public function sortOrder()
    {
        return $this->belongsTo(SortOrder::class);
    }

    public function inspector()
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    public function defectLogs()
    {
        return $this->hasMany(SerialDefectLog::class);
    }

    public function reworkLogs()
    {
        return $this->hasMany(ReworkLog::class);
    }
}
