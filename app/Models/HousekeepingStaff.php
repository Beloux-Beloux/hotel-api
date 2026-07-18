<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\BelongsToHotel;

class HousekeepingStaff extends Model
{
    use HasFactory, HasUuids, BelongsToHotel;

    protected $fillable = [
        'hotel_id',
        'user_id',
        'code',
        'phone',
        'hourly_rate',
        'hire_date',
        'floor_preferences',
        'max_rooms_per_day',
        'active',
        'skills'
    ];

    protected $casts = [
        'floor_preferences' => 'array',
        'skills' => 'array',
        'active' => 'boolean',
        'max_rooms_per_day' => 'integer',
        'hourly_rate' => 'decimal:2',
        'hire_date' => 'date'
    ];

    protected $attributes = [
        'active' => true,
        'max_rooms_per_day' => 15
    ];

    protected $appends = ['display_name', 'email'];

    protected static function booted()
    {
        static::creating(function ($staff) {
            if (is_null($staff->skills)) {
                $staff->skills = [];
            }
            if (is_null($staff->floor_preferences)) {
                $staff->floor_preferences = [];
            }
            if (is_null($staff->hire_date)) {
                $staff->hire_date = now();
            }
        });
    }

    /**
     * Get the user associated with the staff member.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the assignments for the staff member.
     */
    public function assignments()
    {
        return $this->hasMany(RoomAssignment::class, 'staff_id');
    }

    /**
     * Get today's assignments.
     */
    public function todayAssignments()
    {
        return $this->assignments()
            ->where('assigned_date', today())
            ->orderBy('assigned_at');
    }

    /**
     * Get assignments for a specific date.
     */
    public function assignmentsForDate($date)
    {
        return $this->assignments()
            ->where('assigned_date', $date)
            ->orderBy('assigned_at');
    }

    /**
     * Check if staff member can accept more rooms for a date.
     */
    public function canAcceptMoreRooms($date)
    {
        $currentCount = $this->assignmentsForDate($date)->count();
        return $currentCount < $this->max_rooms_per_day;
    }

    /**
     * Get the display name for the staff member.
     */
    public function getDisplayNameAttribute()
    {
        return $this->user ? $this->user->name : $this->code;
    }
    
    /**
     * Get email through user relationship
     */
    public function getEmailAttribute()
    {
        return $this->user ? $this->user->email : null;
    }
}