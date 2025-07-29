<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Traits\BelongsToHotel;

class Room extends Model
{
    use HasFactory, BelongsToHotel;

    const STATUS_LIBRE_PROPRE = 'libre_propre';
    const STATUS_LIBRE_SALE = 'libre_sale';
    const STATUS_OCCUPEE_PROPRE = 'occupee_propre';
    const STATUS_OCCUPEE_SALE = 'occupee_sale';
    const STATUS_EN_NETTOYAGE = 'en_nettoyage';
    const STATUS_HORS_SERVICE = 'hors_service';
    const STATUS_RESERVEE = 'reservee';

    protected $fillable = [
        'hotel_id',
        'number',
        'room_type_id',
        'floor',
        'status',
        'features',
        'notes',
    ];

    protected $casts = [
        'floor' => 'integer',
        'features' => 'array',
    ];

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function currentReservation()
    {
        return $this->hasOne(Reservation::class)
            ->where('status', 'en_cours')
            ->whereDate('check_in_date', '<=', now())
            ->whereDate('check_out_date', '>=', now());
    }

    public function isAvailable($checkIn, $checkOut): bool
    {
        if (!in_array($this->status, [self::STATUS_LIBRE_PROPRE, self::STATUS_LIBRE_SALE])) {
            return false;
        }

        return !$this->reservations()
            ->whereIn('status', ['confirmee', 'en_cours'])
            ->where(function ($query) use ($checkIn, $checkOut) {
                $query->whereBetween('check_in_date', [$checkIn, $checkOut])
                    ->orWhereBetween('check_out_date', [$checkIn, $checkOut])
                    ->orWhere(function ($q) use ($checkIn, $checkOut) {
                        $q->where('check_in_date', '<=', $checkIn)
                            ->where('check_out_date', '>=', $checkOut);
                    });
            })
            ->exists();
    }

    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_LIBRE_PROPRE => 'Libre propre',
            self::STATUS_LIBRE_SALE => 'Libre sale',
            self::STATUS_OCCUPEE_PROPRE => 'Occupée propre',
            self::STATUS_OCCUPEE_SALE => 'Occupée sale',
            self::STATUS_EN_NETTOYAGE => 'En nettoyage',
            self::STATUS_HORS_SERVICE => 'Hors service',
            self::STATUS_RESERVEE => 'Réservée',
        ];
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_LIBRE_PROPRE => 'green',
            self::STATUS_LIBRE_SALE => 'yellow',
            self::STATUS_OCCUPEE_PROPRE => 'blue',
            self::STATUS_OCCUPEE_SALE => 'orange',
            self::STATUS_EN_NETTOYAGE => 'purple',
            self::STATUS_HORS_SERVICE => 'red',
            self::STATUS_RESERVEE => 'gray',
            default => 'gray',
        };
    }
}