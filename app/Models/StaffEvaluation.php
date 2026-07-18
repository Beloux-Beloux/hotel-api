<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffEvaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_id',
        'date',
        'score',
        'comments',
    ];

    public function staff()
    {
        return $this->belongsTo(HousekeepingStaff::class, 'staff_id');
    }
}
