<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Traits\BelongsToHotel;

class RoomType extends Model
{
    use HasFactory, BelongsToHotel;

    protected $fillable = [
        'hotel_id',
        'name',
        'code',
        'base_price',
        'max_occupancy',
        'description',
        'amenities',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'max_occupancy' => 'integer',
        'amenities' => 'array',
    ];

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->base_price, 2, ',', ' ') . ' €';
    }

    public function getAvailableRoomsCount($checkIn, $checkOut): int
    {
        return $this->rooms()
            ->whereNotIn('id', function ($query) use ($checkIn, $checkOut) {
                $query->select('room_id')
                    ->from('reservations')
                    ->whereIn('status', ['confirmee', 'en_cours'])
                    ->where(function ($q) use ($checkIn, $checkOut) {
                        $q->whereBetween('check_in_date', [$checkIn, $checkOut])
                            ->orWhereBetween('check_out_date', [$checkIn, $checkOut])
                            ->orWhere(function ($q2) use ($checkIn, $checkOut) {
                                $q2->where('check_in_date', '<=', $checkIn)
                                    ->where('check_out_date', '>=', $checkOut);
                            });
                    });
            })
            ->where('status', 'libre_propre')
            ->count();
    }
}