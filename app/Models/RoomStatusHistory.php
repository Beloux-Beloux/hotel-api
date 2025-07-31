<?php

namespace App\Models;

use App\Models\Traits\BelongsToHotel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomStatusHistory extends Model
{
    use HasUuids, BelongsToHotel;

    protected $table = 'room_status_history';

    protected $fillable = [
        'hotel_id',
        'room_id',
        'previous_status',
        'new_status',
        'changed_by',
        'changed_at',
        'reason',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}
