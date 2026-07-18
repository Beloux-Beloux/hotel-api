<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HousekeepingSetting extends Model
{
    protected $fillable = [
        'hotel_id',
        'default_cleaning_times',
        'max_rooms_per_staff',
        'working_hours',
        'notifications_enabled',
        'alert_thresholds'
    ];

    protected $casts = [
        'default_cleaning_times' => 'array',
        'working_hours' => 'array',
        'alert_thresholds' => 'array',
    ];

    // Relation avec l'hôtel
    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }
}

