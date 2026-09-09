<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SerialDefectLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['serial_record_id', 'defect_id', 'zone', 'photo_evidence_url', 'notes'];

    public function serialRecord()
    {
        return $this->belongsTo(SerialRecord::class);
    }

    public function defect()
    {
        return $this->belongsTo(DefectCatalog::class, 'defect_id');
    }
}
