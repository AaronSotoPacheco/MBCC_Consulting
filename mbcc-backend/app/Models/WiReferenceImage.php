<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WiReferenceImage extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['work_instruction_id', 'kind', 'label', 'file_path', 'mime', 'uploaded_by'];

    public function workInstruction()
    {
        return $this->belongsTo(WorkInstruction::class);
    }
}
