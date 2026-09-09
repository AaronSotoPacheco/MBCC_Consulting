<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkInstruction extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['part_number_id', 'code', 'revision', 'title', 'file_path', 'status', 'approved_by', 'approved_at'];

    protected $casts = ['approved_at' => 'datetime'];

    public function partNumber()
    {
        return $this->belongsTo(PartNumber::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function sortOrders()
    {
        return $this->hasMany(SortOrder::class);
    }
}
