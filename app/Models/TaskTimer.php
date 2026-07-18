<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskTimer extends Model
{
    protected $fillable = [
        'task_id',
        'elapsed_seconds',
        'status',
        'start_time',
    ];

    protected $casts = [
        'start_time' => 'datetime',
    ];

    public function logs()
    {
        return $this->hasMany(TaskTimerLog::class, 'task_id', 'task_id');
    }
}
