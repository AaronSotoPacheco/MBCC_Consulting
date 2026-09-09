<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['name', 'contact_email'];

    public function partNumbers()
    {
        return $this->hasMany(PartNumber::class);
    }
}
