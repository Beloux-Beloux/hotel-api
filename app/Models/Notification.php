<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'hotel_id',
        'user_id',
        'type',
        'title',
        'message',
        'data',
        'icon',
        'priority',
        'read',
        'read_at',
        'sound_enabled',
    ];

    protected $casts = [
        'data' => 'array',
        'read' => 'boolean',
        'sound_enabled' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function markAsRead()
    {
        $this->update([
            'read' => true,
            'read_at' => now(),
        ]);
    }

    public function markAsUnread()
    {
        $this->update([
            'read' => false,
            'read_at' => null,
        ]);
    }

    public function scopeUnread($query)
    {
        return $query->where('read', false);
    }

    public function scopeForHotel($query, $hotelId)
    {
        return $query->where('hotel_id', $hotelId);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId)->orWhereNull('user_id');
    }

    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}