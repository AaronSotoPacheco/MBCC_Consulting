<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DefectCatalog extends Model
{
    public $timestamps = false;

    protected $table = 'defect_catalog';

    protected $fillable = ['code', 'category', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function defectLogs()
    {
        return $this->hasMany(SerialDefectLog::class, 'defect_id');
    }
}
