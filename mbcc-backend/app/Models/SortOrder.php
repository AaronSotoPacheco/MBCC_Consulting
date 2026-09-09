<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SortOrder extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['order_number', 'part_number_id', 'work_instruction_id', 'target_quantity', 'status', 'shift_hours'];

    protected $casts = ['target_quantity' => 'integer'];

    public function partNumber()
    {
        return $this->belongsTo(PartNumber::class);
    }

    public function workInstruction()
    {
        return $this->belongsTo(WorkInstruction::class);
    }

    public function serialRecords()
    {
        return $this->hasMany(SerialRecord::class);
    }
}
