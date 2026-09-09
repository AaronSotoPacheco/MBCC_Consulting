<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReworkLog extends Model
{
    const CREATED_AT = null;

    protected $fillable = ['serial_record_id', 'rework_action', 'reworked_by', 'reinspected_by', 'final_status', 'completed_at'];

    protected $casts = ['completed_at' => 'datetime'];

    public function serialRecord()
    {
        return $this->belongsTo(SerialRecord::class);
    }

    public function reworker()
    {
        return $this->belongsTo(User::class, 'reworked_by');
    }

    public function reinspector()
    {
        return $this->belongsTo(User::class, 'reinspected_by');
    }
}
