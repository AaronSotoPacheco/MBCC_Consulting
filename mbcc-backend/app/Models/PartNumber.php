<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartNumber extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['client_id', 'part_number', 'description'];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function workInstructions()
    {
        return $this->hasMany(WorkInstruction::class);
    }

    public function sortOrders()
    {
        return $this->hasMany(SortOrder::class);
    }
}
