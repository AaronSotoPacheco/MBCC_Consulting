<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $table = 'attendance_records';

    protected $fillable = ['user_id', 'work_date', 'check_in', 'check_out', 'late_minutes', 'source', 'note', 'edited_by'];

    protected function casts(): array
    {
        return ['work_date' => 'date:Y-m-d', 'check_in' => 'datetime', 'check_out' => 'datetime', 'late_minutes' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Minutos trabajados (solo con salida registrada). */
    public function minutes(): ?int
    {
        return $this->check_out ? (int) $this->check_in->diffInMinutes($this->check_out) : null;
    }
}
