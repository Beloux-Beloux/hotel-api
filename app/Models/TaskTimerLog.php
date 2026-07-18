<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskTimerLog extends Model
{
    protected $fillable = [
        'task_id',
        'start_time',
        'end_time',
        'elapsed_seconds',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public function timer()
    {
        return $this->belongsTo(TaskTimer::class, 'task_id', 'task_id');
    }
}
