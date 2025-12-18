<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HousekeepingPerformance extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_id',
        'date',
        'score',
    ];

    public function staff()
    {
        return $this->belongsTo(HousekeepingStaff::class, 'staff_id');
    }
}
